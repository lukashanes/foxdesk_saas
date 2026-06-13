# FoxDesk release channels

FoxDesk now has two separate delivery paths.

## Public self-hosted PHP FoxDesk

Repository: `lukashanes/foxdesk`

This is the free/self-hosted PHP application. Stable releases from this
repository are allowed to publish public update ZIP packages for the in-app
updater.

Public self-hosted updates may include:

- bug fixes for the PHP helpdesk application
- security fixes
- compatibility updates
- IMAP ingest stability fixes and pseudo-cron fallback fixes
- migration bridge tooling that lets an admin sync a self-hosted instance into
  FoxDesk Cloud and run final cutover
- migration ZIP export tooling only as a fallback when API sync is unavailable

Public self-hosted updates must not include:

- SaaS platform operator dashboards
- tenant billing control-plane screens
- internal SaaS unit economics
- Stripe customer/subscription administration internals
- Cloudflare/R2 production deployment secrets or hosted-only assumptions

## FoxDesk SaaS / Cloud

Repository: `lukashanes/foxdesk_saas`

This repository contains the hosted SaaS platform, the public SaaS website, the
platform operator console, and production deployment configuration.

SaaS deployments use:

```text
FOXDESK_EDITION=saas
FOXDESK_UPDATE_CHANNEL=managed
```

That disables the in-app ZIP updater. Hosted workspaces are updated centrally by
the platform operator through deployment, not by tenant admins uploading public
update packages.

Releases from this repository are not the public self-hosted update channel. If
release artifacts are created here for deployment or testing, they must not be
treated as stable public updater releases for the free PHP app.

## Transfer path

The transfer from self-hosted FoxDesk to FoxDesk SaaS is intentionally split and
API sync is the preferred production path:

1. Public self-hosted FoxDesk receives a stable maintenance update that includes
   the migration bridge client, IMAP fallback fixes, and final cutover controls.
2. The SaaS platform admin creates a one-time migration token for the target
   workspace.
3. The self-hosted admin connects to the SaaS migration bridge and runs data
   plus attachment sync.
4. The SaaS platform admin verifies users, tickets, comments, time entries,
   reports, email data, and attachment evidence.
5. Final cutover marks SaaS as the single active instance and disables active
   self-hosted ingest/notification processing.
6. ZIP export/import is kept only as a fallback when API sync cannot run.

Before any public self-hosted update is published, run the compatibility
checklist in `docs/SELF_HOSTED_RELEASE_CHECKLIST.md`.
