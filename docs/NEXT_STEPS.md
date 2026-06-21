# Next Steps

## Current State

- SaaS production is deployed on Hetzner behind Cloudflare:
  - public site: `https://foxdesk.net`
  - workspace app: `https://app.foxdesk.net`
  - platform console: `https://platform.foxdesk.net`
- Local Docker deployment is available and smoke-tested.
- Multi-tenant workspace baseline is active: tenants, hosted signup, tenant-aware
  users/data, tenant isolation checks, and platform admin console.
- Stripe billing is implemented for the public SaaS model: 14-day trial without
  card, Checkout, Customer Portal, signed webhooks, VAT ID/tax support, billing
  lifecycle matrix, failed-payment grace, manual free override, and metered
  storage usage reporting.
- R2 is the production storage target for new customer attachments, with
  tenant-prefixed keys and smoke-test coverage.
- Cloudflare Email is the SaaS path for outbound transactional email and inbound
  plus-address ticket replies.
- Agent/API control is implemented through Profile API access, scoped Bearer
  tokens, idempotency keys, audit logging, and local Codex/Claude/MCP examples.
- Native/mobile API contracts exist for app shell, app home, ticket detail,
  comments, attachments, timers, and notifications.
- Product voice, app shell, and visual QA gates are active, including CSS token
  reduction and desktop/mobile screenshot smoke.
- Production deploys are gated by preflight, Docker build/restart, app health,
  production smoke, restore evidence, and deployment evidence archive.

## Immediate Next Steps

1. Keep the technical-debt execution plan current in
   `docs/TECHNICAL_DEBT_PLAN.md`. SaaS remains the primary product track;
   self-hosted remains a compatibility, update, and migration-source channel.
2. Run and archive production deployment evidence after every production deploy:

```bash
npm run prod:smoke
npm run prod:deploy:evidence
```

3. Complete the manual paid-public-beta checks:
   - human/legal approval for Privacy, Terms, DPA, Refunds, and Security
   - live Stripe checkout/portal/VAT/cancellation/recovery test evidence
   - real inbound Cloudflare reply test with attachment archive evidence
   - external monitoring for health, cron, backups, webhook failures, R2, email,
     disk, and SSL/DNS
4. Keep restore evidence current after schema-sensitive releases. Production
   deploys should not be marked complete with stale or missing restore evidence.
5. Run the self-hosted to SaaS migration bridge against a real source instance
   before cutting over any production customer. Verify users, clients, tickets,
   comments, time entries, reports, email data, and attachment bytes.
6. Continue reducing CSP inline-style debt and route monolith debt as tracked in
   `docs/CSP_SAFE_UI_AUDIT.md` and `docs/MONOLITH_EXIT_INVENTORY.md`.
7. Start native iOS/Android work against `docs/NATIVE_APP_API.md`; do not scrape
   PHP pages from native clients.

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

Private beta can run with paid-launch warnings when production smoke, R2,
outbound email, public/legal pages, core app smoke, and deployment evidence pass.
Paid public beta requires the manual acknowledgements listed in
`docs/PUBLIC_BETA_GO_NO_GO.md`.
