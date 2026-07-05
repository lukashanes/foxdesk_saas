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
4. Route each visible workspace alias to the same Worker.
5. Set the destination action to Worker.
6. Select `foxdesk-email-router`.

Cloudflare Email Routing catch-all rules only support forward/drop actions, so
FoxDesk cannot route `*@foxdesk.net` directly to the Worker. Friendly workspace
aliases such as `aenze-helpdesk@foxdesk.net` need explicit per-address routing
rules.

For automatic provisioning, configure the app with a Cloudflare token that can
manage Email Routing rules:

```env
CLOUDFLARE_EMAIL_ROUTING_API_TOKEN=...
CLOUDFLARE_ZONE_ID=...
FOXDESK_EMAIL_ROUTER_WORKER=foxdesk-email-router
```

New workspaces then create their friendly route during provisioning. Existing
workspaces can be reconciled with:

```bash
php bin/sync-cloudflare-email-routes.php --dry-run --json
php bin/sync-cloudflare-email-routes.php --json
```

FoxDesk never assigns the base mailbox to a workspace by itself. Each workspace
gets a friendly public support address such as:

```text
aenze-helpdesk@foxdesk.net
```

FoxDesk also keeps an internal signed workspace route such as:

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
