# Next Steps

## Immediate

- Create the initial commit in this SaaS repository.
- Decide the first hosting target: Hetzner VPS with Cloudflare proxy.
- Keep the current Playwright E2E suite as the baseline safety net.
- Add tenant schema design before touching broad application flows.

## Engineering Order

1. Tenant schema and migrations.
2. Tenant request resolver.
3. Tenant-aware auth/session/API token flow.
4. Tenant-scoped tickets and organizations.
5. Tenant-scoped users and roles.
6. Tenant-safe attachments and R2 storage abstraction.
7. Billing tables and webhook handling.
8. Superadmin console.
9. Public signup/onboarding.
10. Production deployment automation.

## Definition of Ready for SaaS Beta

- Tenant isolation covered by E2E tests.
- Backups are automated and restore-tested.
- Attachments are stored outside the app server disk.
- Stripe/Paddle subscription state is enforced in-app.
- Admin can suspend/reactivate tenants.
- Public routes are rate-limited and bot-protected.
- Error logging and uptime monitoring are active.

