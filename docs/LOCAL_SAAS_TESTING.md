# Local SaaS Testing

Local testing should prove that the hosted FoxDesk product still behaves like a
usable helpdesk while keeping SaaS-only boundaries, tenant isolation, billing
states, storage behavior, and release gates intact.

## Local URLs

- App/workspace: `http://127.0.0.1:8090`
- Mailpit: `http://127.0.0.1:8025`

The production platform host is `platform.foxdesk.net`. Local platform testing
can use the normal app host unless a local virtual host is explicitly configured.
The visual QA smoke has a fallback for local platform-host redirects so local
TLS setup does not block app screenshots.

## Core Local Flow

```bash
npm ci
npm run local:install
npm run local:seed
npm run local:smoke
```

`local:install` starts Docker, runs the installer, and creates the default
tenant/admin. `local:seed` adds demo workspace data. `local:smoke` verifies
health, login, ticket creation, attachment upload, attachment download, and key
workspace routes.

## Standard Developer Gate

Run this before a feature branch is considered locally safe:

```bash
npm run lint:php
npm run test:csp-ui
npm run test:app-frontend
npm run test:app-shell-visual
npm run test:visual-qa
npm run local:smoke
```

Run rendered visual QA when touching layout, app shell, public pages, ticket
detail, billing, reports, client detail, or platform surfaces:

```bash
npm run visual:qa
```

Visual QA writes screenshots outside the repository and checks that public web,
login, Work, Inbox, Tickets, Billing, Reports, Client, Ticket detail, and
Platform surfaces render meaningful content without horizontal overflow.

## Full E2E Gate

Use the isolated Playwright suite for workflow/security/billing regressions:

```bash
npm run e2e
```

Useful targeted runs:

```bash
npm run e2e -- tests/e2e/01-core.spec.js
npm run e2e -- tests/e2e/04-permissions.spec.js
npm run e2e -- tests/e2e/05-saas-control-plane.spec.js
npm run e2e -- tests/e2e/06-public-cloud.spec.js
```

## Focused Contract Tests

Run these when changing specific areas:

```bash
npm run test:edition-parity
npm run test:tenant-boundary
npm run test:security-boundary
npm run test:workflow-contract
npm run test:core-ux-flow
npm run test:report-modules
npm run test:settings-modules
npm run test:email-policy
npm run test:agent-api-control
npm run test:module-extraction
```

## What Must Stay Working

- Installer creates a default tenant and admin.
- Login opens the workspace app, not the platform console.
- Work is the daily queue model; dashboard is compatibility/analytics.
- Inbox feeds intake queues without becoming a competing daily agenda.
- Tickets can be created, assigned, worked, completed, and found again.
- Completed tickets remain searchable and visible in `All`.
- New-ticket assignment never falls back to a random client.
- Reports allow item-level rate/discount edits and live totals.
- API/agent tokens inherit creator permissions and can be scoped/revoked.
- Tenant A cannot read or mutate Tenant B data.
- Workspace admins do not see platform-only controls.
- Platform admins do not use normal workspace tokens for operator actions.
- Local storage works locally; production storage must be R2.
- Cloudflare email is the SaaS path; IMAP remains self-hosted compatibility.

## Current SaaS Boundary

Implemented:

- Tenant model, tenant-owned tables, and tenant-aware helpers.
- Hosted signup, login, billing, platform console, and host separation.
- Stripe Checkout, Customer Portal, signed webhooks, 14-day trial, VAT ID/tax
  support, lifecycle state matrix, failed-payment grace, manual free override.
- R2-backed production attachment storage with tenant-prefixed keys.
- Cloudflare outbound email and Cloudflare Email Routing ingest contract.
- Agent/Profile API access with scoped Bearer tokens, idempotency, and audit.
- Native/mobile app contract endpoints for app shell, home, tickets, comments,
  attachments, timers, and notifications.
- Visual QA and CSS token audit.
- Deployment evidence gate and restore evidence validation.

Edition-specific:

- SaaS owns platform, billing, tenants, R2, Cloudflare email, production deploy,
  and public SaaS web.
- Self-hosted owns public ZIP updates, IMAP fallback, local install/update, and
  migration-source tooling.

## Production Sanity Check From Local

After deployment or production config changes:

```bash
npm run prod:smoke
```

Production deploy completion is checked on the server through:

```bash
npm run prod:deploy:evidence
```
