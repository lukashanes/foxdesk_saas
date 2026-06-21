# FoxDesk Cloud Documentation

This directory is the source documentation for FoxDesk Cloud. Docs are written
in English as the source language; Czech and other languages belong in
translation files or customer-facing localized content.

## Start Here

- [SaaS plan](SAAS_PLAN.md): product and architecture direction.
- [Release channels](RELEASE_CHANNELS.md): SaaS vs. self-hosted ownership.
- [Edition parity matrix](EDITION_PARITY_MATRIX.md): what must be shared across editions and what must stay edition-specific.
- [Technical debt plan](TECHNICAL_DEBT_PLAN.md): active refactor and hardening roadmap.
- [Monolith exit inventory](MONOLITH_EXIT_INVENTORY.md): page/module extraction map.

## Run Locally

- [Local deployment](LOCAL_DEPLOYMENT.md): local Docker app, installer, seed, smoke.
- [Local SaaS testing](LOCAL_SAAS_TESTING.md): test layers, gates, and expected local behavior.

## Production

- [Hetzner + Cloudflare deployment](HETZNER_CLOUDFLARE_DEPLOYMENT.md): production architecture and deploy flow.
- [Production environment values](PRODUCTION_ENV_VALUES.md): required non-secret env keys and where values come from.
- [Deployment recovery evidence](DEPLOYMENT_RECOVERY_EVIDENCE.md): restore evidence and deployment evidence expectations.
- [Public beta go/no-go](PUBLIC_BETA_GO_NO_GO.md): launch decision checklist.
- [Launch readiness](LAUNCH_READINESS.md): paid public launch checklist.

## Commercial Infrastructure

- [Stripe billing](STRIPE_BILLING.md): billing lifecycle, tax, trial, portal, usage reporting.
- [Stripe public beta setup](STRIPE_PUBLIC_BETA_SETUP.md): dashboard setup checklist.
- [R2 storage](R2_STORAGE.md): attachment storage, tenant-prefixed keys, smoke testing.
- [Cloudflare email](CLOUDFLARE_EMAIL.md): outbound sending and inbound ticket replies.

## Product And UI

- [Product voice and visual restyle](PRODUCT_VOICE_AND_VISUAL_RESTYLE.md): copy, visual principles, and completed QA gates.
- [CSP-safe UI audit](CSP_SAFE_UI_AUDIT.md): inline style debt and CSP baseline.
- [Product architecture refactor](product-architecture-refactor.md): workflow simplification and module direction.

## Migration And Cutover

- [Self-hosted to SaaS migration](SELF_HOSTED_TO_SAAS_MIGRATION.md): API sync, attachments, and cutover flow.
- [Cloud cutover hold](CLOUD_CUTOVER_HOLD.md): production cutover hold points.
- [Migration from Vas Hosting to Hetzner](MIGRATION_VAS_HOSTING_TO_HETZNER.md): legacy hosting move notes.
- [Self-hosted release checklist](SELF_HOSTED_RELEASE_CHECKLIST.md): allowed public PHP release scope.

## Agent And Mobile APIs

- [Agent API quickstart](AGENT_API_QUICKSTART.md): creating and using scoped assistant keys.
- [Agent API control](AGENT_API_CONTROL.md): token permissions, safety, and audit model.
- [Agent MCP server](AGENT_MCP_SERVER.md): local stdio MCP wrapper.
- [Agent API milestones](AGENT_API_MILESTONES.md): assistant/API roadmap.
- [Native app API](NATIVE_APP_API.md): stable mobile endpoints for future iOS/Android apps.
- [iOS app launch plan](IOS_APP_LAUNCH_PLAN.md): native iOS planning.

## Current Production Snapshot

Production is deployed on the Hetzner Docker stack behind Cloudflare:

- public site: `https://foxdesk.net`
- customer app: `https://app.foxdesk.net`
- platform console: `https://platform.foxdesk.net`
- health: `https://app.foxdesk.net/index.php?page=health`

Current production deploys are considered complete only when
`deploy/hetzner/deploy.sh` finishes successfully. That script runs preflight,
Docker build/restart, app health, production smoke, restore evidence validation,
and deployment evidence archive generation.

## Common Verification Commands

```bash
npm run lint:php
npm run test:csp-ui
npm run test:app-frontend
npm run test:app-shell-visual
npm run test:visual-qa
npm run visual:qa
npm run local:smoke
npm run prod:smoke
```

For a production deploy:

```bash
deploy/hetzner/preflight.sh
deploy/hetzner/deploy.sh
```
