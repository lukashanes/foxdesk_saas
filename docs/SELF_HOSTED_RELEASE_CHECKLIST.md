# Self-Hosted Release Checklist

Use this checklist before publishing any public update package for the free
self-hosted PHP edition.

The self-hosted release channel is maintenance-only. It can receive security,
stability, IMAP, compatibility, and migration bridge changes. It must not expose
FoxDesk SaaS operator controls, tenant billing internals, or hosted production
configuration.

## Allowed Scope

The release may include:

- security fixes that apply to the self-hosted PHP surface
- stability fixes for shared customer workflow pages
- IMAP ingest fixes
- pseudo-cron and CLI maintenance fixes
- migration bridge client and final cutover controls
- ZIP migration export fallback
- database compatibility migrations required by the allowed scope

The release must not include:

- platform operator console screens
- platform tenant lifecycle controls
- Stripe customer, subscription, portal, checkout, VAT, or metered billing admin
  internals
- SaaS unit economics or internal pricing operations
- R2 production bucket secrets or Cloudflare account operational secrets
- hosted-only deployment scripts as an in-app updater feature
- SaaS public marketing pages as self-hosted app pages

## Required Checks

1. Confirm the update channel points to the public self-hosted repository, not
   the SaaS deployment repository.
2. Confirm `FOXDESK_EDITION=saas` and `FOXDESK_UPDATE_CHANNEL=managed` behavior
   still disables tenant-admin ZIP updates in hosted workspaces.
3. Confirm the self-hosted package contains no `pages/platform.php` operator
   console exposure.
4. Confirm billing pages in the public package do not include SaaS operator
   override controls.
5. Confirm IMAP ingest works through:
   - manual admin test
   - CLI maintenance
   - pseudo-cron inline fallback when loopback HTTP is blocked
6. Confirm migration bridge is the recommended transfer path:
   - connect
   - plan
   - table sync
   - attachment sync
   - status
   - final cutover
7. Confirm ZIP export/import is documented as fallback only.
8. Confirm final cutover disables active self-hosted ingest and notification
   processing so only one instance remains active.
9. Confirm shared security fixes from SaaS are present when the same vulnerable
   surface exists in self-hosted.
10. Confirm the update has a rollback note and database backup instruction.

## Verification Commands

Run from the SaaS repository while preparing the mirrored public maintenance
release:

```bash
npm run lint:php
./bin/run-php.sh tests/cloud-migration-bridge-contract-test.php
./bin/run-php.sh tests/pseudo-cron-email-test.php
```

Run in the public self-hosted repository before publishing:

```bash
php -l $(find . -name '*.php' -not -path './vendor/*')
php ./tests/cloud-migration-bridge-contract-test.php
php ./tests/pseudo-cron-email-test.php
```

## Release Decision

Publish only when:

- all required checks pass
- the change set contains no SaaS-only operator UI
- API sync and attachment sync are the preferred migration path
- ZIP export remains fallback only
- final cutover keeps the single-active-instance rule
