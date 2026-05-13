# Launch Readiness

FoxDesk SaaS can run locally as a hosted multi-tenant PHP/MariaDB app, but it is not production-ready until the items below are closed.

## Ready Now

- Local Docker deployment works.
- Core E2E suite is green.
- Tenant baseline exists.
- Public workspace signup works.
- Platform admin can see and manage workspaces.
- Stripe Checkout, Customer Portal, signed webhooks, and storage usage reporting are scaffolded.
- One-plan pricing model is implemented: FoxDesk Cloud with 1 GB included and metered extra storage.

## Blocking Before Public Launch

1. Production environment
   - Provision Hetzner server.
   - Configure Cloudflare DNS/proxy/TLS.
   - Install PHP, MariaDB, web server, cron, backups, and deploy user.
   - Move secrets out of code into server environment or protected local config.

2. Storage
   - Add Cloudflare R2 storage adapter.
   - Keep local disk only for development.
   - Migrate attachments/backups to tenant-prefixed object keys.
   - Add restore test from R2 backup.

3. Billing go-live
   - Create Stripe live products, prices, and storage meter.
   - Configure live webhook endpoint.
   - Test subscription creation, failed payment, cancellation, and reactivation.
   - Add webhook alerting for failed processing.

4. Email
   - Decide outbound provider.
   - Configure sender domain, SPF, DKIM, DMARC.
   - Configure inbound email routing for ticket creation.
   - Add production bounce/failed-send logging.

5. Security hardening
   - Add Cloudflare Turnstile to signup, login, and password reset.
   - Add public route rate limits.
   - Add stricter audit log coverage for platform, billing, impersonation, and account changes.
   - Verify headers, proxy trust, cookie flags, and upload restrictions behind Cloudflare.

6. Operations
   - Add uptime monitoring for `/index.php?page=health`.
   - Add cron monitoring.
   - Add backup success/failure alerts.
   - Add log retention rules.
   - Add disaster recovery runbook.

7. Product launch
   - Terms of service.
   - Privacy policy.
   - Cookie policy if analytics/cookies are added.
   - Support contact and billing contact.
   - Onboarding emails.

## Recommended Launch Order

1. Deploy private staging on Hetzner behind Cloudflare.
2. Connect test Stripe products, meter, and webhook.
3. Move attachment storage to R2.
4. Run full E2E suite against staging.
5. Turn on production backups and restore test.
6. Switch Stripe to live mode.
7. Invite first internal/beta workspace.
8. Watch logs, billing reports, webhook events, and storage growth for at least one billing cycle.
