# FoxDesk Email Router Worker

Cloudflare Email Routing Worker for signed FoxDesk ticket addresses.

It receives inbound email, parses MIME with `postal-mime`, stores raw email and attachments in R2, signs a JSON payload, and posts it to:

```text
https://app.foxdesk.net/index.php?page=api&action=cf-email-ingest
```

## Setup

```bash
cd cloudflare/email-router
npm install
npm run check
npx wrangler r2 bucket create foxdesk-email-archive
npx wrangler secret put FOXDESK_EMAIL_WEBHOOK_SECRET
npm run deploy
```

`FOXDESK_EMAIL_WEBHOOK_SECRET` must equal the backend `FOXDESK_EMAIL_ROUTE_SECRET`.

## Cloudflare Routing

1. Enable Email Routing for `foxdesk.net`.
2. Enable subaddressing.
3. Create a custom address route for the base mailbox `tickets@foxdesk.net`.
4. Set the destination action to Worker.
5. Select `foxdesk-email-router`.

FoxDesk never assigns the base mailbox to a workspace by itself. Each workspace
gets a signed inbound address such as:

```text
tickets+aenze-helpdesk-3-<token>@foxdesk.net
```

Ticket notifications use signed per-ticket reply addresses such as:

```text
tickets+tk-123-<token>@foxdesk.net
```

Cloudflare routes those plus-addressed messages through the base mailbox rule.
The backend validates the token and then chooses the target workspace or ticket.
Unsigned messages to `tickets@foxdesk.net` are not routed to a customer
workspace.

## Recovery

Every accepted email is archived in R2 under:

```text
emails/YYYY-MM-DD/<uuid>/raw.eml
```

Attachments are stored under the same prefix in `attachments/`. If the FoxDesk backend rejects a webhook, check Worker logs and recover the raw message from R2.
