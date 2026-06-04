# CSP-Safe UI Audit

Date: 2026-06-04
Status: active refactor baseline updated

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

Current baseline after the CSP cleanup milestone:

- Affected files: 34
- `<style>` blocks: 0
- Inline `style=""` attributes: 434
- Unversioned `theme.css` links: 0
- Unversioned `tailwind.min.css` links: 0
- Priority files affected: 10

Completed in milestone 1 cleanup:

- `pages/report-public.php`: final page-level `<style>` block removed.
- `pages/report-theme.php`: public first-party stylesheet endpoint added for
  report-specific theme colors without inline styles.
- `pages/report-share.php`, `pages/ticket-share.php`, and `pages/login.php`:
  public CSS links now use local versioned assets.
- Static repeated inline colors/backgrounds were converted to shared utility
  classes in `theme.css`, reducing inline style attributes from 1341 to 434.

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

Completed in milestone 6a:

- `pages/new-ticket.php`: page-level `<style>` block removed, editor and
  option pill styles moved to `theme.css`.
- `tests/smoke/local-smoke.js`: now checks that New ticket renders as a styled
  card, uses an external versioned `theme.css`, shows a styled editor and
  upload zone, renders option pills, and previews selected attachments.

Completed in milestone 7a:

- `pages/admin/reports.php`: print-only page-level `<style>` block moved to
  `theme.css`.
- `theme.css`: removed the global `body` fade-in animation so app pages do not
  render temporarily washed out or hidden during reloads and viewport changes.
- `tests/smoke/local-smoke.js`: now checks that Reports renders the admin
  shell, tabs, cards, versioned `theme.css`, a fully visible page body, and no
  page-level report styles.

Completed in milestone 8a:

- `pages/billing.php`: removed all scoped inline `style=""` attributes from the
  billing content and moved plan, usage, storage progress, and action layout to
  `theme.css`.
- `tests/smoke/local-smoke.js`: now checks that Billing renders as a styled
  card, uses versioned `theme.css`, has a valid storage progress control, keeps
  visible page body opacity, and has no scoped billing inline styles.

Completed in milestone 9a:

- `pages/client.php`: removed the page-level `<style>` block and moved client
  detail layout, stats, ticket list, profile, and contact styles to
  `theme.css`.
- `tests/smoke/local-smoke.js`: now opens a real client detail through the
  organization admin page, verifies the external CSS layout, allows only the
  dynamic ticket status color CSS variable, and checks ticket tab switching.

Completed in milestone 10a:

- `pages/dashboard.php`: page-level dashboard styles moved to `theme.css`.
  Hidden dashboard widgets now use the shared `is-hidden` class instead of
  generated `display:none` attributes.
- `includes/components/widget-wrap-open.php`: widget visibility class handling
  is shared with dashboard JS.
- `tests/smoke/local-smoke.js`: now checks dashboard grid/KPI/widget layout,
  versioned `theme.css`, and the customize panel interaction.

Completed in milestone 10b:

- `pages/admin/settings.php`: update success redirect pages now use one
  `settings_render_update_redirect()` helper and shared
  `system-notice-*` classes.
- `includes/update-functions.php`: update and rollback interstitial pages now
  use one `render_update_interstitial()` helper and shared external CSS.
- `index.php`: maintenance mode page uses the same external system notice
  layout.

Completed in milestone 10c:

- `pages/signup.php`, `pages/forgot-password.php`, and
  `pages/reset-password.php`: local auth/signup CSS moved to `theme.css`,
  theme/tailwind links are versioned, and repeated inline color styles were
  replaced with shared utility classes.
- `pages/platform.php`: operator console styles moved to `theme.css` and scoped
  under `body.op-page` so they do not leak into the customer workspace UI.
- `pages/legal.php`: legal document styles moved to `theme.css` and scoped
  under `body.legal-page`.
- `includes/header.php`: header search dark-mode and notification center style
  blocks moved to `theme.css`.
- `pages/notifications.php`, `pages/admin/activity.php`,
  `pages/admin/organizations.php`, and `pages/admin/agent-connect.php`: local
  static page style blocks moved to `theme.css`.
- `includes/functions.php` and `includes/email-ingest-functions.php`: sanitizer
  regex literals were split so the CSP audit no longer reports false page-level
  `<style>` blocks.
- `tests/smoke/local-smoke.js`: now checks public signup/legal, dashboard,
  settings, notifications, activity, and the local platform-login fallback.

## Priority Order

### P0 - Blocks Cloud Usability

No remaining P0 page-level style blocks after milestone 6a.

Already converted:

- `pages/work.php`
- `pages/inbox.php`
- `pages/tickets.php`
- `pages/ticket-detail.php`
- `pages/new-ticket.php`

Done means:

- no page-level `<style>` block
- critical layout moved to `theme.css`
- no unversioned `theme.css` link
- desktop and mobile browser screenshots pass
- production console has no CSP style errors

### P1 - Blocks Daily Admin/Reporting

No remaining P1 page-level style blocks. The remaining P1 debt is inline
`style=""` cleanup on complex admin/reporting pages.

Done means:

- reports and billing controls are styled and usable
- admin pages keep compact SaaS layout
- dynamic billable-row editing still works
- no new inline style debt is introduced

Already converted:

- `pages/admin/reports.php`
- `pages/billing.php`
- `pages/client.php`
- `pages/dashboard.php`
- `pages/admin/settings.php` update interstitials

### P2 - Platform And Auth Polish

Remaining:

- `includes/footer.php`
- remaining dynamic inline styles in `includes/header.php`,
  `pages/admin/settings.php`, `pages/admin/reports.php`, `pages/tickets.php`,
  and `pages/ticket-detail.php`

Done means:

- auth pages use shared auth-shell styles
- platform admin is compact and styled
- header/footer do not depend on inline layout styles

Already converted:

- `pages/platform.php`
- `pages/signup.php`
- `pages/forgot-password.php`
- `pages/reset-password.php`
- `pages/legal.php`
- `pages/notifications.php`
- `pages/admin/activity.php`
- `pages/admin/organizations.php`
- `pages/admin/agent-connect.php`
- `includes/header.php` page-level style blocks

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
- Public signup/legal
- Notifications/activity
