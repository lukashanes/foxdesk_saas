# Launch Readiness

FoxDesk SaaS is deployable as a private/beta hosted service. Paid public launch
still requires the manual business, legal, billing, inbound email, monitoring,
and recovery checks below to be completed and acknowledged.

## Current State

- Public cloud page: `https://foxdesk.net`.
- Customer app domain: `https://app.foxdesk.net`.
- Platform admin domain: `https://platform.foxdesk.net`.
- Self-hosted migration export/API sync support exists in public PHP releases
  with the migration bridge (`v0.3.115+`; use the latest available release).
- SaaS migration bridge and ZIP fallback are available through the Platform
  Console.
- Billing foundation exists: Stripe Checkout, Customer Portal, signed webhooks,
  tenant subscription state, 14-day trial, VAT ID/tax support, manual free
  override, failed-payment lifecycle, and metered storage reporting.
- Cloudflare Email Sending and R2 production configuration are part of the
  production preflight.
- Deployment evidence is required before a production deploy can be called
  complete.
- Legal launch pages now exist at:
  - `/index.php?page=legal&type=privacy`
  - `/index.php?page=legal&type=terms`
  - `/index.php?page=legal&type=dpa`
  - `/index.php?page=legal&type=refunds`
  - `/index.php?page=legal&type=security`

## Must Be Done Before Paid Public Launch

### 1. Domains and DNS

- Keep `app.foxdesk.net` for customer workspace login.
- Use `platform.foxdesk.net` for the SaaS operator console.
- Use `foxdesk.net` or `www.foxdesk.net` for the public SaaS website.
- Keep `foxdesk.org` for the open-source/self-hosted edition.
- Verify Cloudflare proxy/TLS for both public site and app.
- Add redirects so only one public SaaS canonical hostname is indexed.
- Confirm health endpoint: `https://app.foxdesk.net/index.php?page=health`.
- Keep platform admin access on `platform.foxdesk.net`; do not expose it through
  the public SaaS website.

### 2. Legal and Trust Pages

- Review and finalize Privacy Policy, Terms, DPA, Refunds, and Security page
  with legal counsel.
- Operator identity is `Aenze s.r.o.`; review jurisdiction and consumer/business
  wording with counsel before paid launch.
- Review refund/cancellation wording for monthly subscriptions.
- Review provider/subprocessor wording in the DPA with counsel. No public subprocessor page is exposed.
- Add Cookie Policy only if analytics or non-essential cookies are introduced.
- Legal links are present in the public footer and signup flow.

### 3. Stripe Go-Live

- Create/confirm Stripe live product `FoxDesk Cloud`.
- Create/confirm recurring base Price: EUR 9.90/month introductory price.
- Keep existing active workspaces on the Stripe Price they subscribed to while
  their subscription stays active.
- When the public price changes for new customers, create a new recurring base
  Price and switch `STRIPE_PRICE_CLOUD_BASE`; do not rewrite existing active
  subscriptions as part of the public price change.
- Create/confirm recurring metered storage Price for extra GB. Current app
  default is EUR 1.90 per started extra GB/month.
- Create/confirm Stripe meter event name: `foxdesk_storage_extra_gb`.
- Configure webhook endpoint:

```text
https://app.foxdesk.net/index.php?page=stripe-webhook
```

- Subscribe webhook to:
  - `checkout.session.completed`
  - `customer.subscription.created`
  - `customer.subscription.updated`
  - `customer.subscription.deleted`
  - `invoice.paid`
  - `invoice.payment_failed`
- Set production env values:

```env
BILLING_ENABLED=true
STRIPE_SECRET_KEY=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_PRICE_CLOUD_BASE=price_...
STRIPE_PRICE_STORAGE_OVERAGE=price_...
STRIPE_STORAGE_METER_EVENT_NAME=foxdesk_storage_extra_gb
BILLING_TRIAL_GRACE_DAYS=3
BILLING_PAST_DUE_GRACE_DAYS=7
STRIPE_SUCCESS_URL=https://app.foxdesk.net/index.php?page=platform&billing=success
STRIPE_CANCEL_URL=https://app.foxdesk.net/index.php?page=platform&billing=cancelled
```

- Test with Stripe test mode and live mode before paid public beta: checkout,
  portal, VAT ID update, failed payment, cancellation, reactivation, webhook
  signature failure, and usage reporting.

### 4. Email

- Use Cloudflare Email Sending for outbound transactional mail.
- Verify SPF, DKIM, and DMARC for `foxdesk.net`.
- Use sender identities such as `notifications@foxdesk.net`, `support@foxdesk.net`, and `billing@foxdesk.net`.
- Route inbound ticket replies through `tickets@foxdesk.net` plus addressing to Worker `foxdesk-email-router`.
- Confirm app environment:

```env
MAIL_PROVIDER=cloudflare
CLOUDFLARE_ACCOUNT_ID=...
CLOUDFLARE_EMAIL_API_TOKEN=...
CLOUDFLARE_EMAIL_FROM=notifications@foxdesk.net
CLOUDFLARE_EMAIL_REPLY_TO=support@foxdesk.net
FOXDESK_TICKET_EMAIL_DOMAIN=foxdesk.net
FOXDESK_TICKET_EMAIL_LOCAL_PART=tickets
FOXDESK_EMAIL_ROUTE_SECRET=...
```

- Before paid public beta, send real test mails for signup, password reset,
  ticket notification, ticket reply, billing contact, and migration confirmation.

### 5. Storage and Backups

- Use Cloudflare R2 for new production attachment storage.
- Confirm bucket, endpoint, access key, and secret key.
- Confirm `deploy/hetzner/preflight.sh` passes with `STORAGE_DRIVER=r2`.
- Run `php bin/test-r2-storage.php --tenant-id=<tenant_id> --json` and keep the output with launch evidence.
- Run an upload/download test from a real workspace.
- Confirm migration bridge evidence shows synced attachment count/bytes before cutover.
- Add backup destination and restore test.
- Store dated restore evidence at `FOXDESK_RESTORE_EVIDENCE_PATH`.
- Run `npm run prod:deploy:evidence` and keep the archive/checksum.
- Keep old self-hosted files untouched until imported workspace is verified.

### 6. Security and Operations

- Rotate production `SECRET_KEY`, database passwords, Cloudflare tokens, R2 keys,
  and Stripe keys before paid public launch or whenever a secret may have been
  exposed.
- Add Turnstile or equivalent bot protection to signup, login, and password reset.
- Add rate limits for public routes behind Cloudflare.
- Confirm secure cookies behind proxy.
- Confirm upload denylist and max upload limits.
- Add monitoring for health, cron, backups, webhook failures, and disk usage.
- Confirm deployment evidence passes before marking any production deploy complete.
- Keep platform admin access limited to your operator account.

### 7. Migration From Self-Hosted

- Update the source self-hosted FoxDesk to the latest release that contains the
  migration bridge (`v0.3.115+` minimum).
- Open self-hosted admin: `index.php?page=admin&section=migration-export`.
- Create migration ZIP.
- In SaaS Platform Console, open Migrations and import ZIP into a new workspace.
- Verify users, clients, tickets, attachments, reports, permissions, outbound email, and billing state.
- Switch DNS only after the imported workspace is verified.

### 8. Public Beta Verification

The public beta gate is green when these local checks pass:

```bash
npm run launch:go-no-go
npm run beta:gate
npm run test:csp-ui
node tests/launch-go-no-go.test.js
node tests/public-beta-gate.test.js
node tests/stripe-beta-configurator.test.js
```

PHP contract checks must also pass inside the PHP runtime:

```bash
./bin/run-php.sh tests/reporting-flow-contract-test.php
./bin/run-php.sh tests/billing-review-test.php
./bin/run-php.sh tests/email-notification-contract-test.php
./bin/run-php.sh tests/email-format-test.php
./bin/run-php.sh tests/notification-policy-test.php
./bin/run-php.sh tests/platform-workspace-host-contract-test.php
./bin/run-php.sh tests/billing-lifecycle-contract-test.php
./bin/run-php.sh tests/r2-storage-contract-test.php
./bin/run-php.sh tests/email-routing-plus-address-contract-test.php
./bin/run-php.sh tests/legal-copy-contract-test.php
./bin/run-php.sh tests/security-debt-contract-test.php
./bin/run-php.sh tests/health-endpoint-contract-test.php
./bin/run-php.sh tests/home-redirect-contract-test.php
```

Current beta verification covers:

- public/app/platform host separation
- 14-day trial and Stripe checkout handoff
- VAT ID collection support
- idempotent Stripe webhook handling
- R2 attachment storage contract
- Cloudflare plus-address inbound reply contract
- email formatting and reduced notification spam rules
- legal, security, health, and home redirect contracts
- CSP-safe UI baseline with no page-level `<style>` blocks

## Launch Order

1. Confirm DNS/TLS for `foxdesk.net`, `app.foxdesk.net`, and
   `platform.foxdesk.net`.
2. Deploy current SaaS build to production and keep deployment evidence.
3. Review production legal copy and operator identity.
4. Run Stripe billing flow in test and live mode.
5. Run R2 upload/download/delete and real workspace attachment checks.
6. Run full local E2E and production smoke checks.
7. Import a real self-hosted FoxDesk through API sync and verify attachment
   evidence.
8. Run final cutover so only SaaS remains active.
9. Invite first beta customers.
10. Monitor webhooks, mail delivery, health, logs, backups, and R2 storage for
    the first billing cycle.

## Not Ready Until These Are True

- Stripe live keys, prices, storage meter, and webhook are configured and tested.
- Legal pages are approved and linked.
- Cloudflare DNS/TLS is clean for public, app, and platform domains.
- R2 upload/download/delete works in production.
- Backups are restorable and restore evidence is current.
- Inbound ticket replies through Cloudflare Email Routing are tested with a real
  message and attachment archive.
- At least one full migration from self-hosted to SaaS has been verified end to
  end.

## Verification Commands

```bash
npm run lint:php
npm run test:csp-ui
npm run test:app-frontend
npm run test:app-shell-visual
npm run test:visual-qa
npm run e2e
npm run launch:go-no-go
npm run beta:gate
npm run prod:smoke
npm run prod:deploy:evidence
```

Use `PROD_BASE_URL` and `PROD_PUBLIC_URL` when checking a staging hostname instead of production.
