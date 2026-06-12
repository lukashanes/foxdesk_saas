# Monolith Exit Inventory

This is the working queue for breaking large route files into tested modules.
FoxDesk SaaS stays the primary track. Shared customer workflow improvements may
also ship to the self-hosted PHP edition when they do not expose platform-only
controls.

## Rules

- Every extraction starts with a named contract test.
- Route files keep request routing, authorization handoff, and final includes.
- Business rules move to `includes/modules/<area>/*`.
- Reusable rendering moves to `includes/components/*`.
- Browser behavior moves to versioned assets under `assets/js/*` or `assets/css/*`.
- Platform-only code stays under `includes/modules/platform/*` and must not leak
  into self-hosted update packages.

## Status Labels

- `already modular`: route is small or already delegates the important behavior.
- `needs module extraction`: route owns business/UI logic that should move out.
- `SaaS-only platform page`: route belongs to hosted SaaS control plane only.
- `self-hosted migration/update page`: route exists for migration or update
  compatibility and must stay outside platform billing/tenant controls.

## Page Ownership Inventory

| Page | Lines | Status | Owner Track | Next Step |
| --- | ---: | --- | --- | --- |
| `pages/ticket-detail.php` | 3116 | needs module extraction | shared customer workflow | Extract context, timeline, sidebar, composer, and detail JS. |
| `pages/admin/reports.php` | 2917 | needs module extraction | shared customer workflow | Extract filters, query/totals, billing adjustments, and export actions. |
| `pages/admin/settings.php` | 2913 | needs module extraction | shared customer workflow plus self-hosted update compatibility | Extract action handlers by tab before changing UI. |
| `pages/tickets.php` | 2727 | needs module extraction | shared customer workflow | Move list filters and row view model into ticket list modules. |
| `pages/admin/users.php` | 2526 | needs module extraction | shared customer workflow | Extract team permissions, invite/reset actions, and user table rendering. |
| `pages/dashboard.php` | 1738 | needs module extraction | shared customer workflow | Keep dashboard as compatibility view; move cards/feed into app modules. |
| `pages/new-ticket.php` | 1114 | needs module extraction | shared customer workflow | Extract ticket create form model and assignment defaults. |
| `pages/admin/organizations.php` | 993 | needs module extraction | shared customer workflow | Extract client CRUD, access rules, and list rendering. |
| `pages/admin/recurring-tasks.php` | 968 | needs module extraction | shared customer workflow | Extract recurring task CRUD and scheduler preview. |
| `pages/admin/agent-connect.php` | 882 | needs module extraction | shared customer workflow | Extract OAuth/token state and provider rendering. |
| `pages/admin/report-builder.php` | 862 | needs module extraction | shared customer workflow | Extract report template builder state and validation. |
| `pages/platform.php` | 860 | SaaS-only platform page | SaaS platform | Keep operator control-plane logic in `includes/modules/platform/*`. |
| `pages/profile.php` | 827 | needs module extraction | shared customer workflow | Extract profile update, 2FA, preferences, and notification settings. |
| `pages/notifications.php` | 648 | needs module extraction | shared customer workflow | Move notification list/read actions into notification module. |
| `pages/admin/activity.php` | 617 | needs module extraction | shared customer workflow | Extract filters and audit table rendering. |
| `pages/report-public.php` | 534 | already modular | shared customer workflow | Keep public token access isolated; add module only when export logic grows. |
| `pages/login.php` | 450 | already modular | shared customer workflow | Keep route thin; preserve host/session split tests. |
| `pages/admin/reports-list.php` | 442 | needs module extraction | shared customer workflow | Share report template list rendering with report builder. |
| `pages/admin/ticket-types.php` | 429 | already modular | shared customer workflow | Uses content component; keep CRUD helper coverage. |
| `pages/admin/priorities.php` | 397 | already modular | shared customer workflow | Uses content component; keep CRUD helper coverage. |
| `pages/admin/statuses.php` | 364 | already modular | shared customer workflow | Uses content component; keep workflow mapping coverage. |
| `pages/admin/clients.php` | 363 | already modular | shared customer workflow | Prefer client overview module for new behavior. |
| `pages/admin/ticket-types-content.php` | 346 | needs module extraction | shared customer workflow | Fold into workflow admin CRUD components. |
| `pages/admin/statuses-content.php` | 322 | needs module extraction | shared customer workflow | Fold into workflow admin CRUD components. |
| `pages/admin/priorities-content.php` | 307 | needs module extraction | shared customer workflow | Fold into workflow admin CRUD components. |
| `pages/report-share.php` | 279 | already modular | shared customer workflow | Keep token generation rules near report access helpers. |
| `pages/user-profile.php` | 263 | already modular | shared customer workflow | Keep profile display components shared. |
| `pages/cloud.php` | 242 | already modular | SaaS public web | Keep public marketing isolated from app/platform controls. |
| `pages/ticket-share.php` | 219 | already modular | shared customer workflow | Keep public ticket-share access isolated. |
| `pages/cron.php` | 211 | self-hosted migration/update page | shared maintenance | Keep scheduler entrypoint thin; reuse CLI helpers. |
| `pages/billing.php` | 208 | already modular | SaaS platform | Keep billing action state in billing module/helpers. |
| `pages/client.php` | 192 | already modular | shared customer workflow | Extend client overview module rather than route logic. |
| `pages/reset-password.php` | 168 | already modular | shared customer workflow | Keep public hardening tests green. |
| `pages/legal.php` | 162 | already modular | SaaS public web | Keep operator legal pages small and public-safe. |
| `pages/forgot-password.php` | 156 | already modular | shared customer workflow | Keep Turnstile/rate-limit guard isolated. |
| `pages/signup.php` | 145 | already modular | SaaS public web | Keep provisioning in tenant/signup helpers. |
| `pages/admin/migration-export.php` | 84 | self-hosted migration/update page | self-hosted maintenance | Keep export package generation out of SaaS platform runtime. |
| `pages/work.php` | 64 | already modular | shared customer workflow | Work queues live in `includes/modules/work/work-queues.php`. |
| `pages/inbox.php` | 62 | already modular | shared customer workflow | Inbox behavior lives in `includes/modules/inbox/inbox-service.php`. |
| `pages/report-theme.php` | 61 | already modular | shared customer workflow | Keep theme rendering isolated. |
| `pages/stripe-webhook.php` | 38 | already modular | SaaS platform | Keep signed webhook handling in billing helpers. |

## Existing Module Map

| Area | Existing Modules | Current Role |
| --- | --- | --- |
| App shell | `includes/modules/app/app-shell.php`, `includes/modules/app/app-feed.php`, `includes/modules/app/app-contract.php` | Native/mobile and app-home contracts. |
| Work and inbox | `includes/modules/work/work-queues.php`, `includes/modules/inbox/inbox-service.php` | iOS-like queue model and triage flow. |
| Tickets | `includes/modules/tickets/ticket-detail-actions.php`, `ticket-events.php`, `ticket-list-views.php`, `ticket-status-groups.php` | Action state, event metadata, list views, status grouping. |
| Reports | `includes/modules/reports/reporting-flow.php`, `billing-review.php` | Report navigation, billing review calculations. |
| Platform | `includes/modules/platform/operator-console.php` | SaaS-only tenant/operator surface. |
| Search | `includes/modules/search/global-search.php` | Global search model. |
| Email | `includes/modules/email/email-renderer.php` | Transactional email rendering. |
| Notifications | `includes/modules/notifications/notification-policy.php` | Notification noise reduction policy. |
| Clients | `includes/modules/clients/client-overview.php` | Client detail summary. |

## Priority Extractions

### 1. `pages/ticket-detail.php`

Reason: largest route, high daily usage, mixes context loading, rendering,
timeline assembly, time tracking UI, sharing, sidebar metadata, and inline JS.

Target modules/components:

- `includes/modules/tickets/ticket-detail-context.php`
- `includes/modules/tickets/ticket-detail-timeline.php`
- `includes/modules/tickets/ticket-detail-sidebar.php`
- `includes/modules/tickets/ticket-detail-composer.php`
- `includes/modules/tickets/ticket-share-state.php`
- `includes/components/ticket-detail-surface.php` already exists and should stay
  the rendering boundary for the outer surface.
- `assets/js/ticket-detail.js`

Contract tests before extraction:

- `tests/ticket-detail-actions-test.php`
- `tests/ticket-detail-surface-contract-test.php`
- `tests/ticket-activity-surface-contract-test.php`
- `tests/ticket-composer-surface-contract-test.php`
- `tests/ticket-sidebar-surface-contract-test.php`
- add `tests/ticket-detail-context-contract-test.php` before moving data loading
- add `tests/ticket-detail-timeline-contract-test.php` before moving timeline
  assembly

Done when:

- route delegates data loading and timeline assembly to named modules
- inline detail JS is moved to `assets/js/ticket-detail.js`
- existing E2E ticket create/detail/comment/attachment flow stays green

### 2. `pages/admin/reports.php`

Reason: business-critical billing/time reporting page; it mixes filters, POST
mutations, calculations, tables, and billing review UI.

Target modules/components:

- `includes/modules/reports/report-filters.php`
- `includes/modules/reports/report-query.php`
- `includes/modules/reports/report-totals.php`
- `includes/modules/reports/report-adjustments.php`
- `includes/modules/reports/report-export.php`
- `includes/modules/reports/billing-review.php` already exists and remains the
  billing review calculation boundary.
- `assets/js/report-billing-review.js`

Contract tests before extraction:

- `tests/reporting-flow-contract-test.php`
- `tests/billing-review-test.php`
- add `tests/report-filter-contract-test.php` before moving filter defaults
- add `tests/report-adjustment-contract-test.php` before moving item/bulk
  adjustment actions
- add `tests/report-export-contract-test.php` before moving export generation

Done when:

- route delegates item/bulk price adjustments to `report-adjustments.php`
- dynamic billed total behavior stays covered
- client/month report views remain editable per line item

### 3. `pages/admin/settings.php`

Reason: route mixes self-hosted update logic, email ingest, security settings,
workflow admin, branding, upload settings, backup/rollback, and rendering.

Target modules/components:

- `includes/modules/settings/settings-actions.php`
- `includes/modules/settings/settings-email.php`
- `includes/modules/settings/settings-updates.php`
- `includes/modules/settings/settings-workflow.php`
- `includes/modules/settings/settings-security.php`
- `includes/modules/settings/settings-view-model.php`
- `includes/components/admin-settings-tabs.php`

Contract tests before extraction:

- `tests/admin-settings-surface-contract-test.php`
- `tests/security-debt-contract-test.php`
- `tests/email-routing-plus-address-contract-test.php`
- add `tests/settings-action-contract-test.php` before moving POST handlers
- add `tests/settings-email-contract-test.php` before moving SMTP/IMAP test/run
  actions
- add `tests/settings-update-contract-test.php` before moving update/backup
  actions

Done when:

- route delegates POST handlers by tab/action to modules
- self-hosted update/backup behavior stays isolated from SaaS platform billing
- email ingest manual run/test remains available for self-hosted installations

## Next Queue After The First Three

1. `pages/tickets.php`: list filters, tab state, bulk actions, search result
   mapping.
2. `pages/admin/users.php`: team permissions, invite/reset flows, row view
   model.
3. `pages/dashboard.php`: keep as compatibility dashboard, delegate cards/feed
   to app/work modules.
4. Workflow content pages: merge statuses/priorities/types into one reusable
   CRUD surface.
5. `pages/admin/organizations.php`: client access rules and client billing-rate
   forms.

## Verification For This Milestone

```bash
npm run test:monolith-inventory
npm run lint:php
./bin/run-php.sh tests/app-shell-contract-test.php
./bin/run-php.sh tests/reporting-flow-contract-test.php
./bin/run-php.sh tests/ticket-detail-actions-test.php
```
