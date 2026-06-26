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
| `pages/admin/reports.php` | 2128 | needs module extraction | shared customer workflow | Query, totals, billing adjustments, CSV export, and billing review JS are extracted; continue only if report rendering grows again. |
| `pages/admin/settings.php` | 2094 | needs module extraction | shared customer workflow plus self-hosted update compatibility | Main POST actions, workflow POST router, tab view model, tab navigation, templates, and workflow cards are extracted; continue splitting only large rendering sections. |
| `pages/tickets.php` | 1445 | needs module extraction | shared customer workflow | Filters, bulk actions, row view model, and ticket-list JS are extracted; continue with search result mapping only if API payload grows. |
| `pages/ticket-detail.php` | 637 | needs module extraction | shared customer workflow | Context, share state, read model, surfaces, and detail JS are extracted; continue only with smaller rendering slices if the route grows again. |
| `pages/admin/users.php` | 762 | needs module extraction | shared customer workflow | Team permission payloads, filters, organization assignment, list read model, time totals, AI-agent token read model, and users/AI tabs are extracted. |
| `pages/dashboard.php` | 1538 | needs module extraction | shared customer workflow | Dashboard compatibility helpers are extracted; keep dashboard as an analytics view, not a competing work model. |
| `pages/new-ticket.php` | 1114 | needs module extraction | shared customer workflow | Extract ticket create form model and assignment defaults. |
| `pages/admin/organizations.php` | 993 | needs module extraction | shared customer workflow | Extract client CRUD, access rules, and list rendering. |
| `pages/admin/recurring-tasks.php` | 968 | needs module extraction | shared customer workflow | Extract recurring task CRUD and scheduler preview. |
| `pages/admin/agent-connect.php` | 882 | needs module extraction | shared customer workflow | Extract OAuth/token state and provider rendering. |
| `pages/admin/report-builder.php` | 862 | needs module extraction | shared customer workflow | Extract report template builder state and validation. |
| `pages/platform.php` | 889 | SaaS-only platform page | SaaS platform | Keep operator control-plane logic in `includes/modules/platform/*`. |
| `pages/profile.php` | 827 | needs module extraction | shared customer workflow | Extract profile update, 2FA, preferences, and notification settings. |
| `pages/notifications.php` | 648 | needs module extraction | shared customer workflow | Move notification list/read actions into notification module. |
| `pages/admin/activity.php` | 617 | needs module extraction | shared customer workflow | Extract filters and audit table rendering. |
| `pages/report-public.php` | 534 | already modular | shared customer workflow | Keep public token access isolated; add module only when export logic grows. |
| `pages/login.php` | 453 | already modular | shared customer workflow | Keep route thin; preserve host/session split tests. |
| `pages/admin/reports-list.php` | 442 | needs module extraction | shared customer workflow | Share report template list rendering with report builder. |
| `pages/admin/ticket-types.php` | 429 | already modular | shared customer workflow | Uses content component; keep CRUD helper coverage. |
| `pages/admin/priorities.php` | 397 | already modular | shared customer workflow | Uses content component; keep CRUD helper coverage. |
| `pages/admin/statuses.php` | 364 | already modular | shared customer workflow | Uses content component; keep workflow mapping coverage. |
| `pages/admin/clients.php` | 363 | already modular | shared customer workflow | Prefer client overview module for new behavior. |
| `pages/admin/ticket-types-content.php` | 346 | needs module extraction | shared customer workflow | Workflow CRUD delegates to `includes/admin-crud-helper.php`; keep extracting repeated rendering only if the card markup grows. |
| `pages/admin/statuses-content.php` | 322 | needs module extraction | shared customer workflow | Workflow CRUD delegates to `includes/admin-crud-helper.php`; keep extracting repeated rendering only if the card markup grows. |
| `pages/admin/priorities-content.php` | 307 | needs module extraction | shared customer workflow | Workflow CRUD delegates to `includes/admin-crud-helper.php`; keep extracting repeated rendering only if the card markup grows. |
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
| `pages/work.php` | 64 | already modular | shared customer workflow | Work queues live in `includes/modules/work/work-queues.php`. |
| `pages/inbox.php` | 62 | already modular | shared customer workflow | Inbox behavior lives in `includes/modules/inbox/inbox-service.php`. |
| `pages/report-theme.php` | 61 | already modular | shared customer workflow | Keep theme rendering isolated. |
| `pages/stripe-webhook.php` | 38 | already modular | SaaS platform | Keep signed webhook handling in billing helpers. |

## Existing Module Map

| Area | Existing Modules | Current Role |
| --- | --- | --- |
| App shell | `includes/modules/app/app-shell.php`, `includes/modules/app/app-feed.php`, `includes/modules/app/app-contract.php`, `includes/modules/app/dashboard-compat.php` | Native/mobile/app-home contracts, dashboard tags, selected-agent activity, and dashboard CSS class helpers. |
| Work and inbox | `includes/modules/work/work-queues.php`, `includes/modules/inbox/inbox-service.php` | iOS-like queue model and triage flow. |
| Tickets | `includes/modules/tickets/ticket-bulk-actions.php`, `includes/modules/tickets/ticket-detail-actions.php`, `includes/modules/tickets/ticket-detail-context.php`, `includes/modules/tickets/ticket-detail-read-model.php`, `includes/modules/tickets/ticket-share-state.php`, `includes/modules/tickets/ticket-events.php`, `includes/modules/tickets/ticket-list-filters.php`, `includes/modules/tickets/ticket-list-views.php`, `includes/modules/tickets/ticket-row-view-model.php`, `includes/modules/tickets/ticket-status-groups.php`, `assets/js/ticket-list.js`, `assets/js/ticket-detail.js` | Bulk actions, action state, detail context, visibility/read models, share state, event metadata, list filters, list views, row/board view models, status grouping, ticket-list and ticket-detail browser behavior. |
| Reports | `includes/modules/reports/reporting-flow.php`, `includes/modules/reports/billing-review.php`, `includes/modules/reports/report-filters.php`, `includes/modules/reports/report-query.php`, `includes/modules/reports/report-totals.php`, `includes/modules/reports/report-adjustments.php`, `includes/modules/reports/report-export.php` | Report navigation, billing review calculations, filters, query/aggregation, POST adjustments, and CSV export. |
| Settings | `includes/modules/settings/settings-actions.php`, `includes/modules/settings/settings-email.php`, `includes/modules/settings/settings-updates.php`, `includes/modules/settings/settings-security.php`, `includes/modules/settings/settings-workflow.php`, `includes/modules/settings/settings-view-model.php`, `includes/modules/settings/settings-templates.php`, `includes/components/admin-settings-tabs.php`, `includes/components/admin-workflow-card.php` | Settings POST routing, action metadata, workflow CRUD routing, tab normalization, email-template display rows, tab navigation, and workflow card rendering. |
| Workflow CRUD | `includes/admin-crud-helper.php` | Shared status, priority, and ticket-type slugging, sort order, default clearing, tenant-aware record lookup/update/delete, and usage-guarded deletion. Covered by `tests/workflow-crud-contract-test.php`. |
| Platform | `includes/modules/platform/operator-console.php` | SaaS-only tenant/operator surface. |
| Search | `includes/modules/search/global-search.php` | Global search model. |
| Email | `includes/modules/email/email-renderer.php` | Transactional email rendering. |
| Notifications | `includes/modules/notifications/notification-policy.php` | Notification noise reduction policy. |
| Clients | `includes/modules/clients/client-overview.php` | Client detail summary. |
| Team | `includes/modules/team/team-users.php` | Users/team filter state, organization assignment normalization, permission payloads, user list read model, time totals, and AI-agent token read model. |

## Priority Extractions

### 1. `pages/ticket-detail.php`

Reason: largest route, high daily usage, mixes context loading, rendering,
timeline assembly, time tracking UI, sharing, sidebar metadata, and inline JS.

Target modules/components:

- `includes/modules/tickets/ticket-detail-context.php`
- `includes/modules/tickets/ticket-detail-timeline.php`
- `includes/modules/tickets/ticket-share-state.php`
- `includes/components/ticket-detail-composer.php` already exists and should stay
  the rendering boundary for reply/internal note/time logging controls.
- `includes/components/ticket-detail-modals.php` already exists and should stay
  the rendering boundary for edit ticket/comment/time modal markup.
- `includes/components/ticket-detail-sidebar.php` already exists and should stay
  the rendering boundary for sidebar metadata.
- `includes/components/ticket-detail-surface.php` already exists and should stay
  the rendering boundary for the outer surface.
- `assets/js/ticket-detail.js`

Contract tests before extraction:

- `tests/ticket-detail-actions-test.php`
- `tests/ticket-detail-surface-contract-test.php`
- `tests/ticket-activity-surface-contract-test.php`
- `tests/ticket-composer-surface-contract-test.php`
- `tests/ticket-detail-modals-contract-test.php`
- `tests/ticket-sidebar-surface-contract-test.php`
- `tests/ticket-detail-context-contract-test.php`
- `tests/ticket-detail-timeline-contract-test.php`
- `tests/ticket-detail-js-contract-test.php`
- `tests/ticket-share-state-contract-test.php`

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
- `tests/report-filter-contract-test.php`
- `tests/report-adjustment-contract-test.php`
- `tests/report-export-contract-test.php`

Done when:

- route delegates item/bulk price adjustments to `report-adjustments.php`
- route delegates report query/totals to `report-query.php` and `report-totals.php`
- route delegates CSV export to `report-export.php`
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
- `includes/modules/settings/settings-templates.php`
- `includes/components/admin-settings-tabs.php`
- `includes/components/admin-workflow-card.php`
- `includes/admin-crud-helper.php`

Contract tests before extraction:

- `tests/admin-settings-surface-contract-test.php`
- `tests/security-debt-contract-test.php`
- `tests/email-routing-plus-address-contract-test.php`
- add `tests/settings-action-contract-test.php` before moving POST handlers
- add `tests/settings-email-contract-test.php` before moving SMTP/IMAP test/run
  actions
- add `tests/settings-update-contract-test.php` before moving update/backup
  actions
- `tests/settings-render-contract-test.php`
- `tests/workflow-crud-contract-test.php`

Done when:

- route delegates POST handlers by tab/action to modules
- route delegates email-template display rows and workflow card rendering to
  modules/components
- workflow content pages delegate slug, sort order, default clearing,
  record update/delete, and usage-guarded deletion to `includes/admin-crud-helper.php`
- self-hosted update/backup behavior stays isolated from SaaS platform billing
- email ingest manual run/test remains available for self-hosted installations

### 4. `pages/admin/users.php`

Target modules/components:

- `includes/modules/team/team-users.php`
- `includes/components/team-ai-agents-tab.php`
- `includes/components/team-users-tab.php`

Contract tests:

- `tests/team-users-contract-test.php`

Done when:

- route delegates table capability flags, filter state, organization assignment,
  permission payloads, user list query, time totals, and AI-agent token queries
  to `team-users.php`
- route delegates AI-agent and users tab rendering to dedicated components
- tenant filtering stays inside the module and the workspace route does not own
  platform-specific SQL

### 5. `pages/dashboard.php`

Target modules/components:

- `includes/modules/app/dashboard-compat.php`
- `includes/modules/app/app-shell.php`
- `includes/modules/app/app-feed.php`

Contract tests:

- `tests/dashboard-compat-contract-test.php`

Done when:

- route delegates tag parsing, selected-agent activity, tenant-aware dashboard
  compatibility filters, and dashboard class helpers to `dashboard-compat.php`
- dashboard remains a compatibility/analytics view instead of becoming a new
  competing work model

## Next Queue After The First Three

1. `pages/tickets.php`: search result mapping, if API payload or grouping grows.
   Filters already live in `includes/modules/tickets/ticket-list-filters.php`,
   bulk actions live in `includes/modules/tickets/ticket-bulk-actions.php`,
   row/board view state lives in
   `includes/modules/tickets/ticket-row-view-model.php`, ticket-list browser
   behavior lives in `assets/js/ticket-list.js`, and these are covered
   by `tests/ticket-list-filter-contract-test.php`,
   `tests/ticket-bulk-actions-contract-test.php`, and
   `tests/ticket-row-view-model-contract-test.php`, plus
   `tests/ticket-list-js-contract-test.php`. Keep
   `tests/shared-workflow-contract-test.php` and
   `tests/core-ux-flow-parity-contract-test.php` green.
2. `pages/admin/users.php`: keep route/controller thin; only extract additional
   invite/reset fragments if the users tab component grows again.
3. `pages/dashboard.php`: keep as compatibility dashboard, continue extracting
   widget rendering and inline CSS.
4. Workflow content pages: merge statuses/priorities/types into one reusable
   CRUD surface.
5. `pages/admin/organizations.php`: client access rules and client billing-rate
   forms.

## Verification For This Milestone

```bash
npm run test:monolith-inventory
npm run test:ticket-list-filters
npm run test:ticket-bulk-actions
npm run test:ticket-row-view-model
npm run test:ticket-list-js
npm run lint:php
./bin/run-php.sh tests/app-shell-contract-test.php
./bin/run-php.sh tests/reporting-flow-contract-test.php
./bin/run-php.sh tests/ticket-detail-actions-test.php
./bin/run-php.sh tests/ticket-detail-context-contract-test.php
./bin/run-php.sh tests/ticket-share-state-contract-test.php
```
