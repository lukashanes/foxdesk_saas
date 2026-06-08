import PostalMime from "postal-mime";

type PostalAddress = {
  address?: string;
  name?: string;
};

type PostalAttachment = {
  filename?: string;
  mimeType?: string;
  contentId?: string;
  content?: ArrayBuffer | Uint8Array;
};

type ParsedEmail = {
  subject?: string;
  text?: string;
  html?: string;
  messageId?: string;
  from?: PostalAddress | string;
  to?: PostalAddress[] | PostalAddress | string;
  cc?: PostalAddress[] | PostalAddress | string;
  bcc?: PostalAddress[] | PostalAddress | string;
  attachments?: PostalAttachment[];
};

type AttachmentPayload = {
  filename: string;
  mime: string;
  size: number;
  r2_key: string;
  archive_verified: boolean;
  content_id?: string;
};

type EnvWithSecrets = Env & {
  FOXDESK_EMAIL_WEBHOOK_SECRET?: string;
};

function stringEnv(value: unknown, fallback = ""): string {
  return typeof value === "string" && value.trim() !== "" ? value.trim() : fallback;
}

function boolEnv(value: unknown, fallback = false): boolean {
  if (typeof value !== "string" || value.trim() === "") {
    return fallback;
  }
  return ["1", "true", "yes", "on"].includes(value.trim().toLowerCase());
}

function numberEnv(value: unknown, fallback: number): number {
  const parsed = Number.parseInt(typeof value === "string" ? value : "", 10);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
}

async function readAll(stream: ReadableStream<Uint8Array>, maxBytes: number): Promise<Uint8Array> {
  const reader = stream.getReader();
  const chunks: Uint8Array[] = [];
  let total = 0;

  try {
    while (true) {
      const { done, value } = await reader.read();
      if (done) {
        break;
      }
      if (value) {
        total += value.byteLength;
        if (total > maxBytes) {
          throw new Error("Email exceeds configured MAX_RAW_BYTES");
        }
        chunks.push(value);
      }
    }
  } finally {
    reader.releaseLock();
  }

  const out = new Uint8Array(total);
  let offset = 0;
  for (const chunk of chunks) {
    out.set(chunk, offset);
    offset += chunk.byteLength;
  }
  return out;
}

function bytesToArrayBuffer(bytes: Uint8Array): ArrayBuffer {
  const copy = new Uint8Array(bytes.byteLength);
  copy.set(bytes);
  return copy.buffer;
}

function sanitizePathSegment(value: string, fallback: string): string {
  const cleaned = value
    .toLowerCase()
    .replace(/[^a-z0-9._-]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .slice(0, 120);
  return cleaned || fallback;
}

function headersToObject(headers: Headers): Record<string, string> {
  const out: Record<string, string> = {};
  headers.forEach((value, key) => {
    out[key.toLowerCase()] = value;
  });
  return out;
}

function rawHeadersFromBytes(raw: Uint8Array): string {
  const preview = new TextDecoder("utf-8").decode(raw.slice(0, 512 * 1024));
  const boundary = preview.search(/\r?\n\r?\n/);
  return boundary >= 0 ? preview.slice(0, boundary) : preview;
}

function addressToStrings(value: ParsedEmail["to"] | ParsedEmail["from"]): string[] {
  if (!value) {
    return [];
  }
  if (typeof value === "string") {
    return [value];
  }
  if (Array.isArray(value)) {
    return value.flatMap((item) => addressToStrings(item));
  }
  if (typeof value.address === "string" && value.address.trim() !== "") {
    return [value.address.trim()];
  }
  return [];
}

function firstAddress(value: ParsedEmail["from"], fallback: string): string {
  return addressToStrings(value)[0] ?? fallback;
}

async function hmacSha256Hex(secret: string, value: string): Promise<string> {
  const encoder = new TextEncoder();
  const key = await crypto.subtle.importKey(
    "raw",
    encoder.encode(secret),
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign"],
  );
  const signature = await crypto.subtle.sign("HMAC", key, encoder.encode(value));
  return [...new Uint8Array(signature)].map((byte) => byte.toString(16).padStart(2, "0")).join("");
}

async function storeAttachments(
  env: EnvWithSecrets,
  archivePrefix: string,
  attachments: PostalAttachment[],
): Promise<AttachmentPayload[]> {
  const stored: AttachmentPayload[] = [];

  for (const [index, attachment] of attachments.entries()) {
    const content = attachment.content;
    if (!content) {
      continue;
    }

    const filename = attachment.filename || `attachment-${index + 1}`;
    const mime = attachment.mimeType || "application/octet-stream";
    const bytes = content instanceof Uint8Array ? content : new Uint8Array(content);
    const key = `${archivePrefix}/attachments/${index + 1}-${sanitizePathSegment(filename, "attachment")}`;

    await env.EMAIL_ARCHIVE.put(key, bytes, {
      httpMetadata: { contentType: mime },
      customMetadata: {
        filename,
        content_id: attachment.contentId || "",
      },
    });
    const storedHead = await env.EMAIL_ARCHIVE.head(key);
    if (!storedHead) {
      throw new Error(`Attachment archive verification failed for ${key}`);
    }

    stored.push({
      filename,
      mime,
      size: bytes.byteLength,
      r2_key: key,
      archive_verified: true,
      ...(attachment.contentId ? { content_id: attachment.contentId } : {}),
    });
  }

  return stored;
}

async function postToFoxDesk(env: EnvWithSecrets, payload: unknown): Promise<Response> {
  const ingestUrl = stringEnv(env.FOXDESK_INGEST_URL);
  const secret = stringEnv(env.FOXDESK_EMAIL_WEBHOOK_SECRET);
  if (ingestUrl === "" || secret === "") {
    throw new Error("FOXDESK_INGEST_URL and FOXDESK_EMAIL_WEBHOOK_SECRET are required");
  }

  const body = JSON.stringify(payload);
  const timestamp = Math.floor(Date.now() / 1000).toString();
  const signature = await hmacSha256Hex(secret, `${timestamp}.${body}`);

  return fetch(ingestUrl, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-FoxDesk-Email-Timestamp": timestamp,
      "X-FoxDesk-Email-Signature": `sha256=${signature}`,
    },
    body,
  });
}

export default {
  async fetch(): Promise<Response> {
    return Response.json({ ok: true, service: "foxdesk-email-router" });
  },

  async email(message: ForwardableEmailMessage, env: EnvWithSecrets, ctx: ExecutionContext): Promise<void> {
    try {
      const maxBytes = numberEnv(env.MAX_RAW_BYTES, 25 * 1024 * 1024);
      const receivedAt = new Date().toISOString();
      const rawId = crypto.randomUUID();
      const archivePrefix = `emails/${receivedAt.slice(0, 10)}/${rawId}`;
      const rawBytes = await readAll(message.raw, maxBytes);
      const rawKey = `${archivePrefix}/raw.eml`;

      await env.EMAIL_ARCHIVE.put(rawKey, rawBytes, {
        httpMetadata: { contentType: "message/rfc822" },
        customMetadata: {
          from: message.from,
          to: message.to,
          received_at: receivedAt,
        },
      });
      const rawHead = await env.EMAIL_ARCHIVE.head(rawKey);
      if (!rawHead) {
        throw new Error(`Raw email archive verification failed for ${rawKey}`);
      }

      const parsed = (await PostalMime.parse(bytesToArrayBuffer(rawBytes))) as ParsedEmail;
      const attachments = await storeAttachments(env, archivePrefix, parsed.attachments ?? []);
      const headers = headersToObject(message.headers);
      const recipients = Array.from(
        new Set([
          message.to,
          ...addressToStrings(parsed.to),
          ...addressToStrings(parsed.cc),
          ...addressToStrings(parsed.bcc),
        ].filter((value) => value.trim() !== "")),
      );

      const payload = {
        id: rawId,
        received_at: receivedAt,
        from: firstAddress(parsed.from, message.from),
        to: addressToStrings(parsed.to),
        recipients,
        subject: parsed.subject || headers.subject || "",
        message_id: parsed.messageId || headers["message-id"] || "",
        in_reply_to: headers["in-reply-to"] || "",
        references: headers.references || "",
        headers,
        raw_headers: rawHeadersFromBytes(rawBytes),
        text: parsed.text || "",
        html: parsed.html || "",
        raw_r2_key: rawKey,
        raw_archive_verified: true,
        raw_size: message.rawSize,
        attachments,
      };

      const response = await postToFoxDesk(env, payload);
      if (!response.ok) {
        const responseText = await response.text();
        console.error(
          JSON.stringify({
            event: "foxdesk_email_ingest_failed",
            status: response.status,
            raw_r2_key: rawKey,
            response: responseText.slice(0, 500),
          }),
        );
        if (boolEnv(env.REJECT_ON_BACKEND_FAILURE, false)) {
          message.setReject("FoxDesk could not process this email.");
        }
      }
    } catch (error) {
      console.error(
        JSON.stringify({
          event: "foxdesk_email_router_exception",
          error: error instanceof Error ? error.message : String(error),
        }),
      );
      message.setReject("FoxDesk email router failed.");
    }

    void ctx;
  },
} satisfies ExportedHandler<EnvWithSecrets>;
