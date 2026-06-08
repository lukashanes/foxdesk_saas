# Next Steps

## Current State

- SaaS repository is initialized and published.
- Local Docker deployment is available and smoke-tested.
- Multi-tenant baseline exists: tenants, workspace signup, tenant-aware users, tenant isolation checks, and platform admin console.
- E2E baseline is active and currently covers core app flows, security regressions, permissions, SaaS signup, billing webhook handling, and blocked tenant access.
- Stripe billing foundation is prepared: Checkout, Customer Portal, signed webhooks, tenant subscription state, and billing documentation.
- Metered storage usage reporting is prepared with dry-run mode, idempotent local report rows, and a Stripe validation command for test-mode meter checks.
- Billing lifecycle rules are prepared: trial grace, failed-payment grace, suspension after grace, and paid-invoice reactivation.
- Usage counters now split local/R2 attachment storage and expose monthly email/API volume for abuse monitoring.
- Platform console now includes operator tenant detail, subscription history, usage overview, manual lifecycle controls, and owner reset/invite flow.
- Public signup, login, and password reset forms have optional Cloudflare Turnstile guards that stay disabled until production keys are configured.
- Platform lifecycle actions and billing checkout/portal actions are written to the security log with sanitized operator context.
- The public health endpoint now checks database connectivity, writable upload/storage paths, PHP baseline, host mode, version, and timestamp for uptime monitoring.

## Immediate Next Steps

0. Follow the technical-debt execution plan in
   `docs/TECHNICAL_DEBT_PLAN.md`. SaaS is the primary product track; the
   self-hosted PHP app is maintained as a separate compatibility, update, and
   migration channel.
1. Create real Stripe test products, recurring prices, and storage meter for FoxDesk Cloud.
2. Add production-style environment handling for secrets on Hetzner: app config, database credentials, Stripe keys, webhook secret, mail credentials, and backup credentials.
3. Validate Stripe usage reporting end to end:
   - set `STRIPE_SECRET_KEY`, `STRIPE_PRICE_STORAGE_OVERAGE`, and `STRIPE_STORAGE_METER_EVENT_NAME` in the local or staging config
   - run `php bin/validate-stripe-usage.php --tenant-id=<tenant_id> --live --json`
   - verify the Stripe meter event was accepted, then re-run after Stripe aggregation catches up if summaries or invoice preview lag
4. Validate billing lifecycle against real Stripe test data:
   - test-mode checkout
   - failed invoice webhook
   - paid invoice webhook after suspension
   - trial expiration after configured grace period
5. Move attachments/backups toward object storage:
   - first add storage abstraction
   - then support local disk and Cloudflare R2
   - finally switch production to R2
6. Prepare real Hetzner + Cloudflare deployment:
   - reverse proxy
   - TLS through Cloudflare
   - DB backups
   - app backups
   - cron jobs
   - health checks
   - deploy/update script
7. Harden public endpoints:
   - rate limiting
   - configure Cloudflare Turnstile production keys for signup/login/reset
8. Add production observability:
   - point an external uptime monitor at `https://app.foxdesk.net/index.php?page=health`
   - failed webhook alerting
   - backup success/failure alerting

## Definition of Ready for SaaS Beta

- Tenant isolation covered by E2E tests.
- Backups are automated and restore-tested.
- Attachments are stored outside the app server disk.
- Stripe subscription state is enforced in-app.
- Storage overage is metered and reported to Stripe.
- Admin can suspend/reactivate tenants.
- Public routes are rate-limited and bot-protected.
- Error logging and uptime monitoring are active.
- A fresh server can be provisioned from documentation/scripts without manual code edits.
