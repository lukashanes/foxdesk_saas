# Local SaaS Testing

The local SaaS baseline should behave like the current FoxDesk app while keeping data tenant-scoped under the hood.

## Test Layers

Run PHP syntax checks:

```bash
npm run lint:php
```

Run full isolated E2E:

```bash
npm run e2e
```

Run smoke against the real local deployment:

```bash
npm run local:install
npm run local:seed
npm run local:smoke
```

## What Must Stay Working

- Installer creates a default tenant and admin.
- Login opens the dashboard.
- Ticket creation works.
- Attachment upload and download work.
- Admin settings render.
- Update/report smoke tests pass.
- Tenant A cannot search tickets from tenant B.
- Organization-scoped agents cannot see or create tickets outside their scope.

## Current SaaS Boundary

Implemented:

- `tenants` table.
- `tenant_id` on core tenant-owned tables.
- Default tenant migration for legacy rows.
- Tenant context from session/API token/current user.
- Tenant-scoped user, organization, ticket, attachment, and search paths.
- E2E tenant search isolation.

Not implemented yet:

- Public signup.
- Tenant subdomain/domain resolver.
- Billing.
- Superadmin console.
- R2 object storage.
- Cloudflare Email Routing integration.

