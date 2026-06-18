# Technical Debt Plan

Status: active
Primary product track: FoxDesk SaaS
Secondary track: public self-hosted PHP FoxDesk for compatibility, updates, and
self-hosted to SaaS migration only.

## Product Boundary

FoxDesk SaaS is now the main application track. New product functionality should
land in the SaaS repository first and be designed around hosted workspaces,
platform operations, billing, object storage, managed email, and future native
mobile clients.

The public self-hosted PHP app remains a separate release channel. It should get
security fixes, migration bridge improvements, data compatibility fixes, and
small workflow parity fixes where needed, but it should not receive SaaS platform
operator features, billing internals, tenant console screens, or production
infrastructure assumptions.

## Non-Negotiable Rules

- English is the source language for code symbols, product concepts, events,
  modules, APIs, tests, and docs.
- Czech and other languages are translations only.
- Renaming labels is not a product improvement by itself.
- Page files stay thin. Business logic moves to modules or focused service
  files.
- SaaS-specific code must not leak into the public self-hosted update channel.
- Self-hosted migration code must be one-way and cutover-safe.
- Security fixes should be applied to both tracks when the vulnerable surface
  exists in both.
- Every milestone must have a verification command or a contract test.
- UI refactors must reduce CSP debt or keep the baseline unchanged.
- Native app preparation must expose stable API contracts instead of making iOS
  or Android scrape PHP pages.

## Current Debt Map

### A. Architecture Boundary Debt

Problem:

- SaaS and self-hosted still share much of the PHP app shape.
- Some code and docs can make it unclear which repository owns which behavior.
- Platform admin, public SaaS website, customer workspace, and self-hosted admin
  are conceptually different products but still visually and structurally close.

Target:

- SaaS owns hosted workspace UX, platform console, billing, R2, Cloudflare email,
  tenant lifecycle, mobile API, and production deployment.
- Self-hosted owns local install/update, IMAP ingest fallback, migration bridge,
  and public free release compatibility.

Done means:

- README and release docs clearly say SaaS is primary for hosted product work.
- Self-hosted docs say it is not the SaaS platform repository.
- Tests protect the release-channel split.
- No public update package contains platform-only operator internals.

### B. Page Monolith Debt

Problem:

- Several page controllers still mix request handling, queries, UI rendering,
  business rules, and JavaScript behavior in one file.
- This slows changes, makes regressions hard to isolate, and blocks native app
  reuse.

Target:

- Page files orchestrate only: auth, input parsing, module calls, render.
- Domain logic lives in:
  - `includes/modules/work/*`
  - `includes/modules/inbox/*`
  - `includes/modules/tickets/*`
  - `includes/modules/clients/*`
  - `includes/modules/reports/*`
  - `includes/modules/notifications/*`
  - `includes/modules/email/*`
  - `includes/modules/platform/*`

Done means:

- New workflow code has a focused module and contract test.
- Ticket detail, report billing review, work queues, inbox, global search, and
  mobile home APIs share module functions instead of duplicated SQL.
- Page-specific JavaScript is either tiny or moved to `assets/js/*`.

### C. Tenant Isolation And SaaS Boundary Debt

Problem:

- Tenant isolation exists, but future features can accidentally bypass it if
  they use raw queries or old helper paths.

Target:

- Every tenant-owned table access is tenant-scoped by default.
- Platform-admin global queries are explicit and isolated in platform modules.
- API tokens, mobile sessions, migration tokens, attachments, notifications, and
  billing records always resolve to exactly one tenant unless the request is an
  operator console request.

Done means:

- New contract tests fail when a handler reads tenant-owned data without a
  tenant predicate or shared tenant helper.
- E2E permissions tests cover cross-tenant tickets, users, attachments, reports,
  and mobile app endpoints.

### D. CSP And UI Runtime Debt

Problem:

- Page-level `<style>` blocks are gone, but inline `style=""` attributes remain.
- Hard CSP without `unsafe-inline` would still break parts of the UI.

Target:

- Keep reducing `docs/csp-ui-baseline.json`.
- Move static styles into `theme.css`.
- Keep only intentionally data-driven dynamic styles until they can become CSS
  variables or mapped classes.

Current measured baseline:

- Affected files: 34
- Page-level style blocks: 0
- Inline style attributes: 434

Done means:

- No new page-level style blocks.
- Any touched UI page reduces inline style count for that file or documents why
  the count must stay.
- `npm run test:csp-ui` passes.
- Browser smoke verifies styled desktop and mobile views for changed pages.

### E. Upload, Attachment, And Storage Debt

Problem:

- Historical uploads started as local files.
- SaaS production should use object storage and clear public/private boundaries.
- Self-hosted must keep local disk compatibility.

Target:

- SaaS stores customer attachments in R2 with tenant-prefixed keys.
- Public assets such as avatars, logos, and editor images are isolated under a
  public namespace.
- Private ticket attachments require ticket permission checks.
- Self-hosted keeps local disk, but uses the same safety helpers.

Done means:

- Upload helpers expose visibility and storage metadata.
- Image proxy fails closed.
- R2 upload/download/delete smoke tests pass.
- Migration sync preserves attachments and maps them to SaaS storage.

### F. Email And Notification Debt

Problem:

- Email ingest, ticket comments, notification rules, and email rendering used to
  be tightly coupled.
- Too many emails can be sent for one action if status/comment/assignment flows
  are not normalized.

Target:

- Web UI, IMAP ingest, Cloudflare ingest, API, and future mobile clients emit the
  same ticket events.
- Notification policy decides whether to email, notify in-app, or only audit.
- Email renderer controls visual format.
- Email is a work channel, not an audit log.

Done means:

- One user action sends at most one actionable email unless explicitly configured
  otherwise.
- Incoming email formatting keeps paragraphs/lists readable.
- Duplicate inbound messages are skipped idempotently.
- Cloudflare Email Routing is the SaaS ingest path.
- Self-hosted IMAP ingest remains for public PHP installs and migration source
  systems.

### G. Billing And Commercial State Debt

Problem:

- Billing is prepared but must be impossible to confuse with workspace admin.
- Trial, paid, free/manual, past-due, suspended, cancelled, and migration states
  need consistent UI actions.

Target:

- Platform admin controls tenant lifecycle and billing overrides.
- Workspace admin sees only its own billing/customer portal.
- Superadmin can grant free/manual access with an explicit reason and audit log.
- VAT/Stripe Tax configuration is handled through Stripe and shown clearly in
  customer billing.

Done means:

- State matrix controls buttons, banners, and access gates.
- Billing actions are audit logged.
- Stripe test flow covers checkout, trial expiration, failed payment, recovery,
  cancellation, portal update, VAT ID update, and manual free override.

### H. Deployment, Observability, And Recovery Debt

Problem:

- Local Docker works, but production needs repeatable deployment, backups,
  monitor checks, and rollback evidence.

Target:

- Hetzner + Cloudflare is the first production stack.
- Deployments are scripted and health-gated.
- Backups are automated and restore-tested.
- Cutover has preflight, archive, and postcheck evidence.

Done means:

- Fresh server can be provisioned from docs/scripts.
- `prod:smoke`, cutover preflight, and postcheck pass.
- Backup restore has a dated evidence file.
- Health, cron, webhook, email, R2, disk, and backup failures alert.

### I. Native Mobile Readiness Debt

Problem:

- Native iOS/Android apps need stable APIs, not PHP-page coupling.

Target:

- Mobile apps use stable endpoints for app shell, app home, ticket detail,
  add-comment, timers, notifications, search, and attachments.
- Web and mobile share the same modules and permissions.

Done means:

- Mobile API contract tests cover auth, 2FA, refresh/logout, app shell, app home,
  ticket detail, add comment, and attachment metadata.
- API responses use stable source-language English keys.
- Native beta can target `app.foxdesk.net` without self-hosted-specific behavior.

## Milestone Plan

### Milestone 1 - Lock Product Boundary

Owner track: SaaS primary, self-hosted maintenance

Tasks:

1. Update SaaS README and docs to state that hosted product work lands in SaaS.
2. Add a technical-debt plan contract test.
3. Add self-hosted note that SaaS platform work does not belong in public update
   packages.
4. Check release-channel docs for platform data leakage.

Done when:

- `docs/EDITION_PARITY_MATRIX.md` defines shared, SaaS-only, self-hosted-only,
  and legacy ownership for the main workflow and platform surfaces.
- `docs/TECHNICAL_DEBT_PLAN.md` exists.
- Contract test verifies SaaS primary boundary and self-hosted maintenance
  boundary.
- `npm run lint:php` passes.

### Milestone 2 - Monolith Exit List

Owner track: SaaS first; self-hosted parity only where shared workflow exists

Tasks:

1. Create a page/module ownership inventory.
2. Mark each heavy page as:
   - already modular
   - needs module extraction
   - SaaS-only platform page
   - self-hosted migration/update page
3. Pick the next three files by risk and usage:
   - `pages/ticket-detail.php`
   - `pages/admin/reports.php`
   - `pages/admin/settings.php`
4. For each picked file, define exact extraction targets and contract tests.

Done when:

- Inventory is committed in `docs/MONOLITH_EXIT_INVENTORY.md`.
- Each high-risk page has a named target module.
- No implementation starts without a test target.
- `tests/monolith-exit-inventory-contract-test.php` protects the page inventory,
  target modules, and test-first rule.

Verification:

```bash
npm run test:monolith-inventory
npm run lint:php
./bin/run-php.sh tests/app-shell-contract-test.php
./bin/run-php.sh tests/reporting-flow-contract-test.php
./bin/run-php.sh tests/ticket-detail-actions-test.php
```

### Milestone 3 - SaaS Tenant Guard Rails

Owner track: SaaS only

Tasks:

1. Add a tenant-owned table map contract test.
2. Scan API handlers for raw tenant-owned selects/updates.
3. Move any new platform-global query into `includes/modules/platform/*`.
4. Add cross-tenant attachment/report/mobile assertions to E2E where missing.

Done when:

- New tenant boundary test exists.
- No new SaaS API handler can access tenant-owned data without a tenant helper or
  explicit operator context.
- E2E permissions pass.

Verification:

```bash
npm run e2e -- tests/e2e/04-permissions.spec.js
npm run e2e -- tests/e2e/05-saas-control-plane.spec.js
```

### Milestone 4 - CSP Inline Style Reduction

Owner track: SaaS first; self-hosted only for shared pages

Tasks:

1. Use `npm run audit:csp-ui` to list top offenders.
2. Convert static inline styles in priority order:
   - `pages/admin/reports.php`
   - `pages/tickets.php`
   - `pages/ticket-detail.php`
   - `includes/header.php`
   - `pages/admin/activity.php`
3. Use CSS classes or CSS variables for dynamic colors.
4. Update `docs/csp-ui-baseline.json` only after counts go down.

Done when:

- Inline style count is reduced by at least 25 percent from 434.
- No page-level style blocks return.
- Browser smoke screenshots are acceptable on desktop and mobile.

Verification:

```bash
npm run audit:csp-ui
npm run test:csp-ui
npm run local:smoke
```

### Milestone 5 - Storage Finalization For SaaS

Owner track: SaaS primary; self-hosted compatibility

Tasks:

1. Confirm R2 config values are documented and required in production preflight.
2. Add storage health check for R2 write/read/delete.
3. Ensure new SaaS attachments use tenant-prefixed storage keys.
4. Ensure public assets stay separate from private attachments.
5. Add migration attachment sync evidence.

Done when:

- Real SaaS workspace can upload, preview, download, and delete attachments from
  R2.
- Local disk is still valid for self-hosted and local development.
- Migration attachments are included in sync and postcheck.

Verification:

```bash
php bin/test-r2-storage.php
npm run local:smoke
npm run cutover:gate
```

Completed in technical debt milestone 5:

- Production preflight now fails SaaS deploys unless `STORAGE_DRIVER=r2` and the
  R2 endpoint has the Cloudflare S3 endpoint shape.
- R2 storage exposes a shared write/read/delete health helper used by the CLI
  smoke test and by opt-in health checks.
- Health JSON reports R2 configuration status under `checks.storage_r2`.
- Migration bridge records attachment sync count, bytes, last key, last checksum,
  and last sync timestamp, and platform tenant detail displays that evidence.

### Milestone 6 - Email Event Unification

Owner track: SaaS first; self-hosted IMAP kept stable

Tasks:

1. Route web UI, API, mobile, IMAP, and Cloudflare ingest through shared ticket
   event helpers.
2. Audit duplicate email triggers for:
   - new ticket
   - assignment
   - status plus comment
   - customer reply
   - internal note
3. Add one-action-one-email contract tests.
4. Keep Cloudflare ingest SaaS-only and IMAP self-hosted-compatible.

Done when:

- Creating and assigning a ticket to self does not send three emails.
- Status plus comment sends one actionable email.
- Email formatting contract covers paragraphs, lists, links, and quoted history.

Verification:

```bash
php tests/email-format-test.php
php tests/email-notification-contract-test.php
php tests/notification-policy-test.php
```

Completed in technical debt milestone 6:

- Web UI, agent API, mobile/app API, quick-edit API, and due-date scheduler now
  route in-app ticket notifications through `ticket_event_dispatch_in_app()`.
- Stable ticket event names now cover public replies, internal notes,
  assignments, status changes, priority changes, due reminders, and legacy
  notification names.
- Notification policy now exposes `ticket_email_action_plan()` for the
  one-action-one-email contract, including self-assignment, internal requester
  confirmations, status-plus-reply, and internal-note suppression.
- Cloudflare Email Routing ingest is gated to SaaS/cloud editions while IMAP
  remains available for self-hosted installs.
- Email HTML-to-text conversion now preserves safe links as readable text and
  URL pairs.

Verified with:

```bash
./bin/run-php.sh tests/email-format-test.php
./bin/run-php.sh tests/email-notification-contract-test.php
./bin/run-php.sh tests/notification-policy-test.php
./bin/run-php.sh tests/email-routing-plus-address-contract-test.php
npm run lint:php
npm run test:app-frontend
npm run local:smoke
```

### Milestone 7 - Billing State Matrix

Owner track: SaaS only

Tasks:

1. Define state matrix for:
   - trialing
   - active
   - manual_free
   - past_due_grace
   - suspended
   - cancelled
   - migrated_pending_cutover
2. Map each state to:
   - app access
   - banner copy
   - platform admin buttons
   - workspace billing buttons
   - Stripe portal/checkout availability
3. Add UI contract test for hidden/visible buttons.
4. Add audit log for manual free override and reactivation.

Done when:

- Active tenant never sees "Activate FoxDesk".
- Suspended tenant gets a clear action and reason.
- Superadmin can grant free access with reason.
- Workspace admin can test billing portal and VAT ID update when allowed.

Verification:

```bash
php tests/billing-lifecycle-contract-test.php
php tests/billing-review-test.php
npm run e2e -- tests/e2e/05-saas-control-plane.spec.js
```

Completed in technical debt milestone 7:

- Billing lifecycle is now driven by `billing_lifecycle_state_matrix()` for
  trialing, active, manual free, past-due grace, suspended, cancelled, blocked,
  trial expired, and migrated-pending-cutover states.
- Workspace access, banner copy, Checkout visibility, Portal visibility,
  platform actions, and workspace billing actions now resolve from the same
  lifecycle contract.
- Suspended past-due workspaces are blocked from app pages and show the payment
  failure reason instead of a generic restricted-access message.
- Active paid workspaces do not show Checkout/activation actions; they show the
  billing portal when a Stripe customer exists.
- Platform free/reactivation actions now require and persist an operator reason
  with `billing_override_reason`, `billing_override_at`, and
  `billing_override_by`, and audit context includes the reason.
- App contract payload now exposes the billing override reason for native/client
  consumers.

Verified with:

```bash
./bin/run-php.sh tests/billing-lifecycle-contract-test.php
./bin/run-php.sh tests/billing-review-test.php
set -e; for test in tests/*.php; do ./bin/run-php.sh "$test"; done
npm run lint:php
npm run test:app-frontend
npm run test:launch-go-no-go
npm run test:csp-ui
npm run e2e -- tests/e2e/05-saas-control-plane.spec.js
npm run local:smoke
```

### Milestone 8 - Deployment And Recovery Evidence

Owner track: SaaS only

Tasks:

1. Make production preflight fail on missing Stripe, R2, mail, database, health,
   and backup values.
2. Add backup restore evidence template.
3. Add deployment evidence archive.
4. Add production smoke gate before marking deploy complete.

Done when:

- A deploy cannot be called successful without health and smoke evidence.
- Backup restore has a tested date and target.
- Cutover postcheck proves source redirects only after approval.

Verification:

```bash
npm run prod:smoke
npm run prod:deploy:evidence
npm run cutover:preflight
npm run cutover:postcheck
```

Completed in technical debt milestone 8:

- Hetzner preflight now fails when production Stripe, R2, Cloudflare mail,
  database, health URL, backup path, restore evidence path, deploy evidence
  path, or monitoring contact values are missing, local-only, placeholders, or
  production-unsafe.
- `.env.production.example` now includes production smoke URLs, backup paths,
  restore evidence settings, deployment evidence directory, and monitoring
  values.
- `bin/deployment-evidence.js` validates production env values without printing
  secrets, verifies dated restore evidence, runs production smoke, writes JSON
  and Markdown evidence, copies restore evidence, and creates a tar.gz archive
  with a SHA256 checksum.
- `deploy/hetzner/deploy.sh` now refuses to mark a deploy complete unless the
  app passes container health and `npm run prod:deploy:evidence`.
- Backup restore evidence has a reusable template at
  `docs/operations/backup-restore-evidence.template.json`.
- Cutover preflight can optionally require deployment evidence and restore
  evidence files before manual cutover approval.
- Public beta and launch gates now include the deployment evidence script and
  documented recovery evidence requirements.

Verified with:

```bash
node tests/deployment-evidence.test.js
node tests/cutover-preflight.test.js
./bin/run-php.sh tests/deployment-recovery-contract-test.php
node tests/launch-go-no-go.test.js
npm run beta:gate -- --json
```

### Milestone 9 - Native App API Freeze

Owner track: SaaS primary

Tasks:

1. Freeze API response keys for app shell and app home.
2. Add endpoint docs for native clients.
3. Add attachment metadata endpoint for native preview/download.
4. Add timers and notification read/unread endpoints if missing.
5. Keep mobile session auth independent from browser sessions.

Done when:

- iOS can implement first beta screens without scraping HTML.
- API contracts protect app shell, app home, ticket detail, add comment, and
  refresh/logout.

Verification:

```bash
php tests/mobile-api-contract-test.php
php tests/app-shell-contract-test.php
php tests/app-home-contract-test.php
php tests/native-app-api-freeze-contract-test.php
```

Completed in technical debt milestone 9:

- Native app response envelopes now use the shared app contract schema version
  helper and keep a frozen response-key registry in
  `app_contract_frozen_response_keys()`.
- Native endpoint docs are published in `docs/NATIVE_APP_API.md` for auth, app
  shell, app home, ticket list/detail, comments, attachments, timers, and
  notifications.
- Attachment metadata is available through `app-attachment-metadata` with stable
  preview/download metadata and no storage secrets.
- Timer state and timer actions are available through `app-ticket-timer` and
  `app-ticket-timer-action`.
- Notification list and read/unread state are available through
  `app-notifications` and `app-notification-read-state`.
- App write endpoints keep CSRF for cookie sessions while allowing authenticated
  mobile Bearer-token writes, so mobile sessions remain independent from browser
  sessions.

Verified with:

```bash
./bin/run-php.sh tests/mobile-api-contract-test.php
./bin/run-php.sh tests/app-shell-contract-test.php
./bin/run-php.sh tests/app-home-contract-test.php
./bin/run-php.sh tests/app-contract-api-test.php
./bin/run-php.sh tests/native-app-api-freeze-contract-test.php
```

### Milestone 10 - Self-Hosted Final Maintenance Gate

Owner track: self-hosted PHP

Tasks:

1. Keep public update package focused on security, stability, IMAP, and migration
   bridge.
2. Ensure public update does not expose SaaS operator console or billing
   internals.
3. Make migration bridge the preferred transfer path.
4. Keep ZIP export as fallback only.
5. Add release checklist for self-hosted compatibility.

Done when:

- Self-hosted can sync to SaaS, final cutover, and stop active ingest.
- Self-hosted update channel does not contain platform admin screens.
- Security fixes shared with SaaS are present.

Verification:

```bash
npm run lint:php
php tests/cloud-migration-bridge-contract-test.php
php tests/pseudo-cron-email-test.php
```

Completed in technical debt milestone 10:

- Release-channel docs now state that API sync is the preferred self-hosted to
  SaaS transfer path and ZIP export/import is fallback only.
- `docs/SELF_HOSTED_RELEASE_CHECKLIST.md` defines the allowed self-hosted
  maintenance scope and explicitly excludes SaaS platform operator screens,
  billing internals, unit economics, and hosted production secrets.
- Cloud migration bridge contract coverage verifies connect, plan, status,
  table sync, attachment sync, attachment evidence, one-time token handling, and
  single-active-instance cutover language.
- Pseudo-cron/IMAP contract coverage verifies manual/CLI/pseudo-cron ingest
  paths, disabled IMAP handling, five-minute page-load fallback, and release
  checklist coverage.

Verified with:

```bash
./bin/run-php.sh tests/cloud-migration-bridge-contract-test.php
./bin/run-php.sh tests/pseudo-cron-email-test.php
```

## Execution Order

1. Finish Milestone 1 immediately.
2. Run Milestones 2, 3, and 4 before large UI or mobile work.
3. Run Milestones 5, 7, and 8 before paid production launch.
4. Run Milestone 6 before using SaaS email for real customers.
5. Run Milestone 9 before starting native iOS implementation.
6. Keep Milestone 10 active for public self-hosted releases.

## Current Next Action

Run the milestone 9 and 10 verification set, then start native iOS
implementation in a dedicated app thread. Keep the self-hosted maintenance gate
active for every future public PHP release.
