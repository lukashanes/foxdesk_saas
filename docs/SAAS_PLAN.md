# FoxDesk SaaS Plan

This repository is the SaaS track for FoxDesk. The product is a hosted
PHP/MariaDB helpdesk with tenant isolation, Stripe billing, Cloudflare R2
storage, Cloudflare email, platform operations, and a public SaaS website.

## Target Architecture

Production architecture:

- Hetzner VPS for PHP, MariaDB, web server, and background jobs.
- Cloudflare DNS/proxy in front of the app.
- Cloudflare WAF, SSL, rate limiting, and Turnstile for edge security.
- Cloudflare R2 for attachments, generated exports, and backups.
- Cloudflare Email Routing for inbound email-to-ticket flows.
- Cloudflare Email Sending for outbound transactional email.
- Stripe for subscriptions, checkout, portal, tax/VAT ID collection, webhooks,
  and storage usage metering.

Cloudflare-only remains a later option. It would require a larger rewrite from
PHP/MariaDB to a Workers/D1/R2/Queues architecture.

## SaaS Milestones

1. Stabilize hosted deployment. **Done for private beta.**
2. Add tenant model and tenant isolation. **Done with contract/E2E coverage.**
3. Move production uploaded files to object storage. **Done for new production attachments through R2.**
4. Add subscription billing and plan limits. **Done for one public plan, trial, Stripe lifecycle, and storage overage.**
5. Add tenant provisioning and a platform console. **Done for operator workflows.**
6. Add SaaS-grade observability, backups, audit logs, and support tooling.
   **Implemented as deployment evidence, restore evidence, health, audit, and smoke gates; external monitoring remains a paid-launch manual check.**
7. Harden security for public self-serve signup. **Partially done; Turnstile/rate limits must be production-reviewed before paid public beta.**
8. Add scoped agent/API control so Codex, Claude, CLI tools, and future MCP
   clients can operate tickets, time entries, reports, and work queues through
   revocable API keys that inherit the creator's permissions. **Implemented v1.**
9. Prepare native iOS/Android clients against stable app/mobile APIs. **API contracts exist; native apps not started in this repository.**

## Core Multi-Tenant Changes

- `tenants` table and tenant-owned table mapping exist.
- `tenant_id` is present on core tenant-owned data.
- Workspace sessions and API tokens resolve to one active tenant.
- Platform/operator queries are isolated in platform modules.
- R2 object keys are tenant-prefixed.
- Migration sync records attachment evidence and supports final cutover.

## Billing Model

Current commercial model:

- One public plan: FoxDesk Cloud.
- Unlimited users, clients, agents, and tickets.
- 1 GB storage included.
- Extra storage is billed monthly per started GB.
- Keep fair-use protection, rate limits, and abuse monitoring for extreme workloads.

Billing integration includes customer/subscription records, signed webhooks,
usage counters, storage overage metering, trial grace, failed-payment grace,
suspension without deleting data, cancellation state, and manual free override
with operator reason/audit log.

## Current Verification Checklist

- Keep the current E2E suite green.
- Keep `npm run lint:php`, `npm run test:csp-ui`, `npm run test:app-frontend`,
  `npm run test:app-shell-visual`, `npm run test:visual-qa`, and
  `npm run local:smoke` green before deploy.
- Run `npm run prod:smoke` and `npm run prod:deploy:evidence` after deploy.
- Keep tenant isolation, security boundary, agent API, billing lifecycle, R2,
  Cloudflare email, and visual QA contract tests green.
- Keep self-hosted release packages free of SaaS platform/billing internals.
- Keep native/mobile API contracts stable before starting native apps.
