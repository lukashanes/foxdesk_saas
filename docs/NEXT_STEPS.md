# Next Steps

## Current State

- SaaS repository is initialized and published.
- Local Docker deployment is available and smoke-tested.
- Multi-tenant baseline exists: tenants, workspace signup, tenant-aware users, tenant isolation checks, and platform admin console.
- E2E baseline is active and currently covers core app flows, security regressions, permissions, SaaS signup, billing webhook handling, and blocked tenant access.
- Stripe billing foundation is prepared: Checkout, Customer Portal, signed webhooks, tenant subscription state, and billing documentation.

## Immediate Next Steps

1. Create real Stripe test products and recurring prices for `starter` and `pro`.
2. Add production-style environment handling for secrets on Hetzner: app config, database credentials, Stripe keys, webhook secret, mail credentials, and backup credentials.
3. Add plan limit enforcement in the app:
   - max users
   - max agents
   - storage quota
   - optional ticket/month limit
   - feature flags per plan
4. Add billing lifecycle rules:
   - trial grace period
   - `past_due` grace period
   - suspension after failed payment grace period
   - reactivation after successful payment
5. Add usage counters and display them in Platform and Billing:
   - users
   - agents
   - tickets
   - storage used
6. Move attachments/backups toward object storage:
   - first add storage abstraction
   - then support local disk and Cloudflare R2
   - finally switch production to R2
7. Prepare real Hetzner + Cloudflare deployment:
   - reverse proxy
   - TLS through Cloudflare
   - DB backups
   - app backups
   - cron jobs
   - health checks
   - deploy/update script
8. Add SaaS operator screens:
   - tenant detail
   - subscription history
   - usage overview
   - manual suspend/reactivate
   - owner reset/invite flow
9. Harden public endpoints:
   - rate limiting
   - Cloudflare Turnstile on signup/login/reset
   - stricter audit logging for billing/platform actions
10. Add production observability:
   - structured error logs
   - uptime checks
   - failed webhook alerting
   - backup success/failure alerting

## Definition of Ready for SaaS Beta

- Tenant isolation covered by E2E tests.
- Backups are automated and restore-tested.
- Attachments are stored outside the app server disk.
- Stripe subscription state is enforced in-app.
- Plan limits are enforced in-app.
- Admin can suspend/reactivate tenants.
- Public routes are rate-limited and bot-protected.
- Error logging and uptime monitoring are active.
- A fresh server can be provisioned from documentation/scripts without manual code edits.
