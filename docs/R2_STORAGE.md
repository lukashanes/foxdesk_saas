# Cloudflare R2 Storage

FoxDesk supports local disk storage and Cloudflare R2 for ticket attachments.

## Recommended Production Setup

- Keep R2 bucket private.
- Do not expose a public bucket URL.
- Let FoxDesk serve attachments through `attachment.php`, which checks ticket/share permissions before reading from R2.
- Store objects with tenant-prefixed keys:

```text
tenants/{tenant_id}/uploads/{file}
tenants/{tenant_id}/storage/tickets/{ticket_id}/{message_id}/{file}
```

## Cloudflare Values Needed

Create these in Cloudflare Dashboard:

1. Go to **R2 Object Storage**.
2. Create bucket:

```text
foxdesk-production
```

3. Go to **Manage R2 API Tokens**.
4. Create token with object read/write access for the bucket.
5. Copy:
   - Access Key ID
   - Secret Access Key
   - S3 API endpoint

Cloudflare R2 S3 endpoint format:

```text
https://<account_id>.r2.cloudflarestorage.com
```

Cloudflare docs: R2 uses an S3-compatible API at `https://<ACCOUNT_ID>.r2.cloudflarestorage.com` and requires an Access Key ID and Secret Access Key for S3 clients.

## FoxDesk Config

Set in `.env.production`:

```env
STORAGE_DRIVER=r2
R2_BUCKET=foxdesk-production
R2_ENDPOINT=https://<account_id>.r2.cloudflarestorage.com
R2_ACCESS_KEY_ID=...
R2_SECRET_ACCESS_KEY=...
```

Local development should normally stay on:

```env
STORAGE_DRIVER=local
```

## Current Behavior

- New manual uploads are written to R2 when `STORAGE_DRIVER=r2`.
- New inbound email attachments are written to R2 when `STORAGE_DRIVER=r2`.
- Attachment metadata stores `storage_driver`, `storage_bucket`, and `storage_key`.
- Existing local attachments continue to work.
- Attachment downloads still go through FoxDesk authorization.

## Production Check

After deploy, upload a small attachment in a test workspace and download it through the ticket detail page. Then confirm the object exists in R2 under the `tenants/` prefix.
