# Cloudflare Email Setup

FoxDesk Cloud uses Cloudflare for both transactional sending and mailbox-less inbound ticket replies.

## Recommended Addresses

- Outbound sender: `notifications@foxdesk.net`
- Human support mailbox/alias: `support@foxdesk.net`
- Billing mailbox/alias: `billing@foxdesk.net`
- Security mailbox/alias: `security@foxdesk.net`
- Ticket reply address: `tickets+...@foxdesk.net`

Ticket notifications are sent from `notifications@foxdesk.net`, but their `Reply-To` is a signed per-ticket plus address such as:

```text
tickets+tk-123-<token>@foxdesk.net
```

That keeps customer replies attached to the right ticket without creating SMTP/IMAP mailboxes.

## Outbound Email Sending

In Cloudflare Email Sending:

1. Select zone `foxdesk.net`.
2. Leave the subdomain field blank for top-level sending.
3. Let Cloudflare add bounce handling, SPF, DKIM, and DMARC records.
4. Create an API token with Email Sending permission.
5. Verify sender addresses used by FoxDesk.

FoxDesk production values:

```env
MAIL_PROVIDER=cloudflare
CLOUDFLARE_ACCOUNT_ID=...
CLOUDFLARE_EMAIL_API_TOKEN=...
CLOUDFLARE_EMAIL_FROM=notifications@foxdesk.net
CLOUDFLARE_EMAIL_FROM_NAME=FoxDesk
CLOUDFLARE_EMAIL_REPLY_TO=support@foxdesk.net
```

`CLOUDFLARE_EMAIL_REPLY_TO` is only the fallback for non-ticket system email. Ticket emails override it with a per-ticket `Reply-To`.

## Inbound Ticket Replies

Enable Cloudflare Email Routing subaddressing, then add a custom address route:

```text
tickets@foxdesk.net -> Worker foxdesk-email-router
```

Backend values:

```env
FOXDESK_TICKET_EMAIL_DOMAIN=foxdesk.net
FOXDESK_TICKET_EMAIL_LOCAL_PART=tickets
FOXDESK_EMAIL_ROUTE_SECRET=<openssl rand -hex 32>
FOXDESK_EMAIL_ALLOW_UNKNOWN_SENDERS=false
```

Use the same `FOXDESK_EMAIL_ROUTE_SECRET` value as the Worker secret `FOXDESK_EMAIL_WEBHOOK_SECRET`.

Set `FOXDESK_EMAIL_ALLOW_UNKNOWN_SENDERS=true` only if workspace inbound addresses should allow public ticket creation from unknown senders. Keeping it `false` means inbound email must match `allowed_senders`.

## Worker Deploy

Worker source lives in:

```text
cloudflare/email-router
```

Deploy flow:

```bash
cd cloudflare/email-router
npm install
npx wrangler login
npx wrangler r2 bucket create foxdesk-email-archive
npx wrangler secret put FOXDESK_EMAIL_WEBHOOK_SECRET
npm run deploy
```

The Worker posts to:

```text
https://app.foxdesk.net/index.php?page=api&action=cf-email-ingest
```

It stores raw emails and attachment bodies in R2 before calling FoxDesk, so failed backend delivery can be recovered from `foxdesk-email-archive`.

## Test

1. Send a ticket notification from FoxDesk.
2. Confirm the received message has `From: notifications@foxdesk.net`.
3. Confirm `Reply-To` starts with `tickets+` and ends with `@foxdesk.net`.
4. Reply to the email.
5. Confirm a public comment is added to the ticket.

## Inbound Archive Smoke

Before public beta, run a real inbound archive smoke against production. It
creates a temporary ticket, sends a plus-addressed reply with a small attachment,
waits for ingest, prints the R2 archive keys, and cleans the temporary DB rows:

```bash
php bin/test-cloudflare-inbound-archive.php --tenant-id=3 --json
```

Then verify both printed keys in the Worker archive bucket:

```bash
npx wrangler r2 object get foxdesk-email-archive/<raw_r2_key> --remote --file /tmp/foxdesk-raw.eml
npx wrangler r2 object get foxdesk-email-archive/<attachment_r2_key> --remote --file /tmp/foxdesk-attachment.txt
```

The bucket must be `foxdesk-email-archive`; application attachments use the
separate `foxdesk-production` bucket.

Useful Cloudflare docs:

- [Email Service](https://developers.cloudflare.com/email-service/)
- [Email Routing to Workers](https://developers.cloudflare.com/email-service/api/route-emails/email-handler/)
- [Email Routing subdomains](https://developers.cloudflare.com/email-routing/setup/subdomains/)
