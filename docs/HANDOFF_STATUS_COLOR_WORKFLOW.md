# Handoff: Status Colors And Workflow Projection

Date: 2026-07-02

## Context

The latest reported issue was that workflow/status colors in Settings did not match the colors shown in ticket lists and status pickers. Production data already contains distinct status colors, for example:

- New: `#0a84ff`
- Testing: `#5e5ce6`
- Waiting for customer: `#ff9f0a`
- In progress: `#30b0c7`
- Done: `#34c759`
- Cancelled: `#ff3b30`

The visible mismatch had two causes:

1. Customer-facing ticket UI uses normalized workflow groups (`new`, `active`, `waiting`, `done`) for behavior and CSS tone classes.
2. Settings color swatches used inline `style=""` custom properties, which can be blocked by the production CSP, causing the fallback blue color to appear instead of the saved DB color.

## What Changed

- Added shared status color helpers in `includes/modules/tickets/ticket-status-groups.php`.
- Status colors are normalized to valid six-digit hex values with group-based fallbacks.
- Settings status swatches now render as small inline SVGs with a safe `fill` attribute instead of blocked inline style attributes.
- Ticket list and kanban status dots now use the saved status color through the same SVG helper.
- Ticket rows and status pills still use normalized workflow group classes for layout, state, and accessible tone.
- Removed the attempted inline CSS-variable approach so the app keeps its CSP-friendly UI contract.

## Important Product Decision Still Open

The system still infers workflow group from status name and `is_closed`. That is why raw settings can contain statuses like `Cancelled` and `Dokončeno`, while customer-facing workflow may display closed states under a canonical `Done` group.

If this needs to be fully explicit later, add a real `status_group` field to statuses and expose it in Settings as:

- New
- In progress
- Waiting
- Done
- Archived

Do not delete duplicate closed statuses in production without a migration that remaps existing tickets.

## Files Touched

- `includes/modules/tickets/ticket-status-groups.php`
- `includes/modules/tickets/ticket-row-view-model.php`
- `pages/admin/statuses-content.php`
- `pages/admin/statuses.php`
- `pages/tickets.php`
- `theme.css`
- `tests/ticket-row-view-model-contract-test.php`

## Verification To Run

```bash
./bin/run-php.sh -l includes/modules/tickets/ticket-status-groups.php
./bin/run-php.sh -l includes/modules/tickets/ticket-row-view-model.php
./bin/run-php.sh -l pages/tickets.php
./bin/run-php.sh -l pages/admin/statuses-content.php
./bin/run-php.sh tests/ticket-row-view-model-contract-test.php
./bin/run-php.sh tests/ticket-registry-surface-contract-test.php
./bin/run-php.sh tests/dashboard-surface-contract-test.php
npm run test:app-frontend
npm run test:admin-ui
npm run local:smoke
```

## Manual QA

- Open `Settings -> Workflow` and confirm each status swatch uses the saved color.
- Open `Tickets` and confirm the status dropdown dot colors match Settings.
- Change a status color, save, reload, and confirm the swatch and ticket dropdown update.
- Confirm closed statuses still behave as closed even when their visual color changes.

