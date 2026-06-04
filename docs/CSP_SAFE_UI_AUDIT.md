# CSP-Safe UI Audit

Date: 2026-06-04
Status: baseline created, refactor pending

## Problem

FoxDesk SaaS production uses a strict stylesheet policy:

```text
style-src 'self'
```

That is the correct production posture, but several SaaS pages still depend on
inline `<style>` blocks and `style=""` attributes. Browsers block page-level
inline style blocks under the production CSP, which can make logged-in pages
render as unstyled HTML.

The login regression was one symptom. `Work` and `Inbox` have the same class of
problem because their layout CSS is inside the page file.

## Automated Audit

Run:

```bash
npm run audit:csp-ui
npm run test:csp-ui
```

Files:

- `bin/audit-csp-ui.js`: scans PHP UI files for CSP-risky styling.
- `docs/csp-ui-baseline.json`: current baseline. Counts must go down, not up.
- `tests/csp-ui-baseline.test.js`: fails when a file adds more inline/page
  styling than the baseline allows.

Current baseline after Work/Inbox/Tickets/Ticket detail refactor:

- Affected files: 48
- `<style>` blocks: 22
- Inline `style=""` attributes: 1421
- Unversioned `theme.css` links: 5
- Unversioned `tailwind.min.css` links: 8
- Priority files affected: 15

Completed in milestone 3a:

- `pages/work.php`: page-level `<style>` removed, queue layout moved to
  `theme.css`.
- `pages/inbox.php`: page-level `<style>` removed, queue layout moved to
  `theme.css`.
- `tests/smoke/local-smoke.js`: now checks that Work and Inbox render as styled
  queue surfaces after login.

Completed in milestone 4a:

- `pages/tickets.php`: page-level `<style>` blocks removed, list, filter,
  kanban, quick-add, and inline-edit styles moved to `theme.css`.
- `tests/smoke/local-smoke.js`: now checks that All tickets list and board
  render with external CSS after login.

Completed in milestone 5a:

- `pages/ticket-detail.php`: page-level `<style>` blocks removed, editor,
  rich content, link preview, work panel, and timeline modal styles moved to
  `theme.css`.
- Timeline modal no longer uses inline layout styles for its hidden/open state.
- `tests/smoke/local-smoke.js`: now checks that ticket detail renders with
  external CSS, opens/closes the activity timeline, and keeps versioned
  `theme.css`.

## Priority Order

### P0 - Blocks Cloud Usability

These pages can look broken under production CSP and must be converted first:

1. `pages/new-ticket.php`

Already converted:

- `pages/work.php`
- `pages/inbox.php`
- `pages/tickets.php`
- `pages/ticket-detail.php`

Done means:

- no page-level `<style>` block
- critical layout moved to `theme.css`
- no unversioned `theme.css` link
- desktop and mobile browser screenshots pass
- production console has no CSP style errors

### P1 - Blocks Daily Admin/Reporting

6. `pages/admin/reports.php`
7. `pages/billing.php`
8. `pages/client.php`
9. `pages/dashboard.php`
10. `pages/admin/settings.php`

Done means:

- reports and billing controls are styled and usable
- admin pages keep compact SaaS layout
- dynamic billable-row editing still works
- no new inline style debt is introduced

### P2 - Platform And Auth Polish

11. `pages/platform.php`
12. `pages/signup.php`
13. `pages/forgot-password.php`
14. `pages/reset-password.php`
15. `includes/header.php`
16. `includes/footer.php`

Done means:

- auth pages use shared auth-shell styles
- platform admin is compact and styled
- header/footer do not depend on inline layout styles

## Refactor Rules

- Do not weaken CSP to make the UI pass.
- Do not add new inline `<style>` blocks.
- Avoid adding new `style=""` attributes. Use CSS classes.
- Dynamic colors may stay temporarily only when data-driven, for example status
  colors. Replace them later with CSS variables or predefined badge classes.
- Shared layout goes into `theme.css` first, then can be split into modules
  once the app is stable.
- Every page refactor must lower the baseline for the touched file.

## Release Gate

A cloud build is not usable until these pass:

```bash
npm run test:csp-ui
npm run local:smoke
npm run prod:smoke
```

In addition, browser QA must capture desktop and mobile screenshots for:

- Work
- Inbox
- All tickets
- Ticket detail
- New ticket
- Reports
- Admin/settings
