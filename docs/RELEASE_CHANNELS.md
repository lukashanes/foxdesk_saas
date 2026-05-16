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
- migration export tooling that lets an admin create a transfer package for
  FoxDesk Cloud

Public self-hosted updates must not include:

- SaaS platform operator dashboards
- tenant billing control-plane screens
- internal SaaS unit economics
- hosted-only deployment assumptions

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

The transfer from self-hosted FoxDesk to FoxDesk SaaS is intentionally split:

1. Public self-hosted FoxDesk receives a stable update that adds a migration
   export page.
2. The self-hosted admin downloads a `foxdesk-cloud-migration-*.zip` package.
3. The SaaS platform admin imports that ZIP in the FoxDesk Cloud operator
   console.
4. DNS/customer cutover happens only after the imported workspace is verified.
