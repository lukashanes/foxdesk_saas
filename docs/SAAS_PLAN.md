# FoxDesk SaaS Plan

This repository is the SaaS track for FoxDesk. The starting point is the current PHP/MariaDB FoxDesk application, with the immediate goal of making it deployable as a hosted multi-tenant product.

## Target Architecture

Initial production architecture:

- Hetzner VPS for PHP, MariaDB, web server, and background jobs.
- Cloudflare DNS/proxy in front of the app.
- Cloudflare WAF, SSL, rate limiting, and Turnstile for edge security.
- Cloudflare R2 for attachments, generated exports, and backups.
- Cloudflare Email Routing for inbound email-to-ticket flows.
- Cloudflare Email Sending or a dedicated SMTP provider for outbound email.
- Stripe or Paddle for subscriptions and billing.

Cloudflare-only is a later option. It would require a larger rewrite from PHP/MariaDB to a Workers/D1/R2/Queues architecture.

## SaaS Milestones

1. Stabilize single-tenant hosted deployment.
2. Add tenant model and tenant isolation.
3. Move uploaded files and backups to object storage.
4. Add subscription billing and plan limits.
5. Add tenant provisioning and a superadmin console.
6. Add SaaS-grade observability, backups, audit logs, and support tooling.
7. Harden security for public self-serve signup.

## Core Multi-Tenant Changes

- Add `tenants` table.
- Add `tenant_id` to tenant-owned tables: users, organizations, tickets, comments, attachments, time entries, report templates, API tokens, notifications, settings, logs where applicable.
- Scope all queries by tenant.
- Bind every request/session/API token to exactly one active tenant.
- Add superadmin-only global views outside tenant scope.
- Make R2 object keys tenant-prefixed, for example `tenants/{tenant_uuid}/attachments/...`.
- Add tenant-aware backup/export/restore boundaries.

## Billing Model

Recommended first plans:

- Free trial: limited users/tickets/storage.
- Starter: small team, basic email-to-ticket, limited storage.
- Pro: more users, automation, reports, larger storage.
- Business: priority support, custom domain, higher limits, advanced audit/export.

Billing integration requirements:

- Customer/subscription records.
- Webhook signature verification.
- Plan feature flags.
- Usage counters.
- Grace period and failed payment states.
- Tenant suspension without deleting data.

## First Implementation Checklist

- Keep the current E2E suite green.
- Add tenant schema migration.
- Add tenant resolver middleware.
- Add tenant-scoped database helper patterns.
- Convert authentication and user lookup to tenant-aware logic.
- Convert ticket list/detail/create flows.
- Add tests proving one tenant cannot see another tenant.
- Add R2 abstraction behind the existing upload/attachment functions.
- Add Cloudflare deployment docs for DNS, WAF, R2, Email Routing, and Turnstile.

