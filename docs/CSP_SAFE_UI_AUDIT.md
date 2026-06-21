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

Current baseline after technical debt milestone 4:

- Affected files: 23
- `<style>` blocks: 0
- Inline `style=""` attributes: 106
- Unversioned `theme.css` links: 0
- Unversioned `tailwind.min.css` links: 0
- Priority files affected: 3

Email-only HTML templates are deliberately excluded from the web CSP count:

- `includes/modules/email/email-renderer.php`
- `includes/report-functions.php`

Those files still use inline CSS because many email clients ignore external
stylesheets. The audit keeps them visible in `emailInlineStyleFiles` so this
does not hide web UI debt.

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
- `tests/smoke/local-smoke.js`: now checks that Work renders as styled
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

Completed in public beta hardening:

- `pages/admin/reports.php`: remaining inline progress widths, weekly stacked
  bars, legend colors, closed-ticket toggles, and column picker display changes
  were moved to class-based styling.
- `theme.css`: report width steps, report tone classes, weekly report layout,
  closed-ticket toggle state, and shared report dot styles were added.
- `tests/reporting-flow-contract-test.php`: now fails if reports reintroduce
  inline `style=""`, `style.` mutations, or the old PHP-generated color map.
- `bin/audit-csp-ui.js`: email-only inline CSS is explicitly allowlisted and
  reported separately from web UI CSP debt.
- `tests/csp-ui-baseline.test.js`: verifies the email allowlist and the reduced
  baseline before release.

Completed in technical debt milestone 4:

- `pages/tickets.php`: status and priority badges now use semantic CSS modifier
  classes plus `data-tone-class`/`data-row-accent-class` update targets instead
  of inline `style=""` attributes. Search suggestions no longer write inline
  colors.
- `pages/ticket-detail.php`: edit-history avatar background, generated CC
  dropdown muted text, toast hiding, edited indicators, and comment removal now
  use CSS classes instead of inline styles or direct color/opacity writes.
- `theme.css`: shared tone, detail-muted, history-avatar, toast hiding, and
  comment-removal states were added.
- `docs/csp-ui-baseline.json`: web UI baseline was reduced from 115 to 106
  inline style attributes, with no page-level style blocks.

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

- Priority pages with small dynamic inline debt:
  - `pages/tickets.php`
  - `pages/new-ticket.php`
  - `pages/platform.php`
  - `pages/ticket-detail.php`
  - `pages/client.php`
- Non-priority admin/component pages with mostly legacy dynamic styles:
  - `pages/admin/agent-connect.php`
  - `pages/admin/statuses-content.php`
  - `pages/admin/ticket-types-content.php`
  - `pages/admin/priorities-content.php`
  - `pages/admin/users.php`
  - `pages/admin/organizations.php`
  - `pages/notifications.php`
  - `pages/profile.php`
  - `includes/footer.php`

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
