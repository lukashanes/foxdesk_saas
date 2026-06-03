# Launch Readiness

FoxDesk SaaS is deployable as a private/beta hosted service, but paid public launch needs the checklist below closed.

## Current State

- App domain: `https://app.foxdesk.net`.
- Public cloud page: `https://foxdesk.net` or `https://www.foxdesk.net` once DNS is pointed correctly.
- Self-hosted migration export is public in the free PHP app release `v0.3.115`.
- SaaS import is available in Platform Console under Migrations.
- Billing foundation exists: Stripe Checkout, Customer Portal, signed webhooks, tenant subscription state, and metered storage reporting.
- Cloudflare Email Sending and R2 configuration are prepared through environment values.
- Legal launch pages now exist at:
  - `/index.php?page=legal&type=privacy`
  - `/index.php?page=legal&type=terms`
  - `/index.php?page=legal&type=dpa`
  - `/index.php?page=legal&type=refunds`
  - `/index.php?page=legal&type=security`

## Must Be Done Before Paid Public Launch

### 1. Domains and DNS

- Keep `app.foxdesk.net` for the customer app login and platform admin.
- Use `foxdesk.net` or `www.foxdesk.net` for the public SaaS website.
- Keep `foxdesk.org` for the open-source/self-hosted edition.
- Verify Cloudflare proxy/TLS for both public site and app.
- Add redirects so only one public SaaS canonical hostname is indexed.
- Confirm health endpoint: `https://app.foxdesk.net/index.php?page=health`.

### 2. Legal and Trust Pages

- Review and finalize Privacy Policy, Terms, DPA, and Security page with legal counsel.
- Add company/operator identity and jurisdiction.
- Review refund/cancellation wording for monthly subscriptions.
- Review provider/subprocessor wording in the DPA with counsel. No public subprocessor page is exposed.
- Add Cookie Policy only if analytics or non-essential cookies are introduced.
- Legal links are present in the public footer and signup flow.

### 3. Stripe Go-Live

- Create Stripe live product `FoxDesk Cloud`.
- Create recurring base Price: EUR 9.90/month launch price through May 31, 2026.
- After launch, create a new recurring base Price and switch `STRIPE_PRICE_CLOUD_BASE` if the public price changes.
- Create recurring metered storage Price for extra GB. Current app default is EUR 1.90 per started extra GB/month.
- Create Stripe meter event name: `foxdesk_storage_extra_gb`.
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

- Test with Stripe test mode first: checkout, portal, failed payment, cancellation, reactivation, webhook signature failure, and usage reporting.

### 4. Email

- Use Cloudflare Email Sending for outbound transactional mail.
- Verify SPF, DKIM, and DMARC for `foxdesk.net`.
- Use sender identities such as `noreply@foxdesk.net`, `support@foxdesk.net`, and `billing@foxdesk.net`.
- Confirm app environment:

```env
MAIL_PROVIDER=cloudflare
CLOUDFLARE_ACCOUNT_ID=...
CLOUDFLARE_EMAIL_API_TOKEN=...
CLOUDFLARE_EMAIL_FROM=noreply@foxdesk.net
CLOUDFLARE_EMAIL_REPLY_TO=support@foxdesk.net
```

- Before launch, send test mails for signup, password reset, ticket notification, billing contact, and migration confirmation.

### 5. Storage and Backups

- Use Cloudflare R2 for new production attachment storage.
- Confirm bucket, endpoint, access key, and secret key.
- Run an upload/download test from a real workspace.
- Add backup destination and restore test.
- Keep old self-hosted files untouched until imported workspace is verified.

### 6. Security and Operations

- Rotate production `SECRET_KEY`, database passwords, Cloudflare tokens, R2 keys, and Stripe keys before launch.
- Add Turnstile or equivalent bot protection to signup, login, and password reset.
- Add rate limits for public routes behind Cloudflare.
- Confirm secure cookies behind proxy.
- Confirm upload denylist and max upload limits.
- Add monitoring for health, cron, backups, webhook failures, and disk usage.
- Keep platform admin access limited to your operator account.

### 7. Migration From Self-Hosted

- Update the source self-hosted FoxDesk to `v0.3.115` or newer.
- Open self-hosted admin: `index.php?page=admin&section=migration-export`.
- Create migration ZIP.
- In SaaS Platform Console, open Migrations and import ZIP into a new workspace.
- Verify users, clients, tickets, attachments, reports, permissions, outbound email, and billing state.
- Switch DNS only after the imported workspace is verified.

## Launch Order

1. Fix DNS for `foxdesk.net` public SaaS site.
2. Deploy current SaaS build to `app.foxdesk.net`.
3. Review production legal copy and operator identity.
4. Configure Stripe in test mode and run the billing flow.
5. Configure R2 and test attachment storage.
6. Run full local E2E and production smoke checks.
7. Import your existing FoxDesk as the first real migration.
8. Switch Stripe to live mode.
9. Invite first beta customers.
10. Monitor webhooks, mail delivery, health, logs, backups, and R2 storage for the first billing cycle.

## Not Ready Until These Are True

- Stripe live keys, prices, storage meter, and webhook are configured.
- Legal pages are approved and linked.
- Cloudflare DNS/TLS is clean for the public and app domains.
- R2 upload/download works in production.
- Backups are restorable.
- At least one full migration from self-hosted to SaaS has been verified end to end.

## Verification Commands

```bash
npm run lint:php
npm run e2e
npm run prod:smoke
```

Use `PROD_BASE_URL` and `PROD_PUBLIC_URL` when checking a staging hostname instead of production.
