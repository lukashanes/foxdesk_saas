# Public Beta Go/No-Go

This is the single launch decision checklist for FoxDesk Cloud.

## Launch decision

FoxDesk has two different release decisions:

- **Private Beta GO**: allowed when production deploy, health, R2, outbound email dry-run, public/legal pages, and core smoke tests pass. Manual legal, Stripe, inbound email, restore, and monitoring checks may still be warnings.
- **Paid Public Beta GO**: allowed only when all warnings are closed and explicitly acknowledged.

Run:

```bash
npm run launch:go-no-go
npm run beta:gate
npm run prod:smoke
```

For paid public beta, run strict mode after the manual launch checks are complete:

```bash
FOXDESK_ACK_LEGAL_APPROVED=true \
FOXDESK_ACK_STRIPE_LIVE_TESTED=true \
FOXDESK_ACK_INBOUND_EMAIL_TESTED=true \
FOXDESK_ACK_RESTORE_MONITORING_READY=true \
npm run launch:go-no-go -- --strict-paid
```

## Domain roles

- `foxdesk.net`: public SaaS website and pricing.
- `app.foxdesk.net`: customer workspace login and hosted FoxDesk app.
- `platform.foxdesk.net`: Aenze operator console only.
- `foxdesk.org`: open-source/self-hosted FoxDesk edition.

Done means:

- public marketing links to app login/signup, not platform admin
- workspace pages do not expose platform controls to normal customers
- platform login rejects non-platform admins
- separate session cookies are used for public, workspace, and platform hosts

Verification:

```bash
php tests/platform-workspace-host-contract-test.php
npm run prod:smoke
```

## Legal and trust

Required public pages:

- Privacy Policy
- Terms of Service
- Data Processing Addendum
- Refund and Cancellation Policy
- Security

Rules:

- operator is `Aenze s.r.o.`
- public subprocessors page stays disabled
- signup links Terms, Privacy, and Refunds before account creation
- public footer links Privacy, Terms, DPA, Refunds, and Security
- final paid public beta requires human/legal approval

Verification:

```bash
php tests/legal-copy-contract-test.php
npm run launch:go-no-go
```

## Production health

Required before each production deploy is considered complete:

- `https://app.foxdesk.net/index.php?page=health` returns `status=ok`
- DB check is true
- app container is healthy
- public site and login layout smoke pass
- legal pages return 200

Verification:

```bash
npm run prod:smoke
```

## Storage

Required before private beta:

- `STORAGE_DRIVER=r2`
- production bucket is `foxdesk-production`
- test upload uses tenant-prefixed keys
- test object is deleted after smoke

Verification on the production server:

```bash
php bin/test-r2-storage.php --tenant-id=3 --json
```

## Email

Required before private beta:

- Cloudflare Email Sending env values are present
- outbound dry-run supports signup, reset, new-ticket, ticket-reply, and billing scenarios

Required before paid public beta:

- real outbound messages are received and formatted acceptably
- a real reply to a ticket email routes through `tickets+...@foxdesk.net`
- inbound reply creates a public comment on the ticket
- inbound attachment archive is recoverable from `foxdesk-email-archive`

Verification:

```bash
php bin/test-cloudflare-email.php --to=lh@aenze.com --scenario=all --dry-run --json
php bin/test-cloudflare-inbound-archive.php --tenant-id=3 --json
```

## Stripe billing

Required before paid public beta:

- 14-day trial works without a card
- checkout collects billing address and VAT ID
- EU VAT payer treatment is handled by Stripe Tax
- webhook activates the tenant after checkout
- customer portal allows card, invoice, billing address, VAT ID, and cancellation updates
- failed payment moves the tenant through the intended lifecycle

Verification:

```bash
php tests/billing-lifecycle-contract-test.php
npm run e2e -- tests/e2e/05-saas-control-plane.spec.js
php bin/validate-stripe-usage.php
```

## Backup and operations

Required before paid public beta:

- DB backup exists outside the running container
- attachment backup path is defined
- restore test has dated evidence
- monitoring covers health, cron, disk, backups, webhook failures, R2 failures, and email failures
- operator access is limited to Aenze platform admins

Verification:

```bash
deploy/hetzner/backup-db.sh
npm run cutover:postcheck
```

## Current decision

Current code target: **Private Beta GO with paid-launch warnings**.

Paid Public Beta remains **NO-GO** until legal approval, Stripe live lifecycle, inbound email, restore evidence, and monitoring are acknowledged in strict mode.
