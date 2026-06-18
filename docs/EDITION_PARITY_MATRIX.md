# FoxDesk Edition Parity Matrix

This matrix defines what must stay shared across FoxDesk editions and what must
remain edition-specific. The SaaS repository is the primary product track for
hosted FoxDesk. The public self-hosted PHP edition receives shared helpdesk
workflow fixes, security fixes, migration tooling, and compatibility updates.

## Classification Rules

- `shared`: same product concept, behavior, source-language labels, and tests in
  SaaS and self-hosted. Implementation may differ only where storage, hosting, or
  deployment requires it.
- `saas`: hosted-only platform, billing, tenant, object-storage, managed email,
  deployment, or operator behavior. It must not ship in public self-hosted update
  packages.
- `self-hosted`: local install, local update, IMAP fallback, migration source,
  and cutover behavior. It must not appear as normal workspace administration in
  SaaS.
- `legacy`: kept only for compatibility, migration, or redirects. New features
  must not be built on legacy surfaces.

## Product And Flow Matrix

| Area | Owner | Required parity |
| --- | --- | --- |
| Work | shared | Same queue keys: `mine`, `unassigned`, `overdue`, `waiting`, `done_today`. Same purpose: what needs attention now. |
| Inbox | shared | Same triage keys: `triage`, `customer_replies`, `email_imports`. Same rule: decision layer, not the full registry. |
| Tickets | shared | Same registry views: `open`, `waiting`, `done`, `all`, `archived`. Done/closed tickets must remain discoverable. |
| Ticket detail | shared | Same action model: Reply, Start work, Assign, Complete/Edit when allowed. Complete must not map to cancelled. |
| New ticket | shared | Same assignment rule: no random client fallback. Empty stays empty unless selected or deterministically inferred. |
| Clients | shared | Same client center model: profile, contacts, open/done work, time this month, billable summary, rates. |
| Reports | shared | Same billing review model: client + period, editable line items, item rates, discounts, live totals, share/export. |
| Search | shared | Same global sections: open tickets, done tickets, clients, and report/template history where available. |
| Notifications | shared | Same policy: one user action creates at most one meaningful email. Activity log remains the audit trail. |
| Email rendering | shared | Same readable formatting rules for paragraphs, lists, links, quoted history stripping, and next action. |
| Team/users | shared | Same staff/client roles and permission concepts. SaaS may add platform-admin separately. |
| Settings | shared + edition overlays | Shared workflow/security/profile settings. Hosted update controls are SaaS-only; ZIP updates are self-hosted-only. |
| Storage | edition overlay | Shared attachment permissions. SaaS uses tenant-prefixed R2 keys; self-hosted keeps local disk compatibility. |
| Inbound email | edition overlay | Shared ticket-event output. SaaS uses Cloudflare Email Routing; self-hosted uses IMAP plus pseudo-cron/CLI fallback. |
| Billing | saas | Stripe checkout, portal, VAT/tax, trials, failed payments, and manual free override. Not part of self-hosted app flow. |
| Platform console | saas | Aenze operator console for tenants, lifecycle, owner reset, migration tokens, and billing overrides. |
| Public SaaS web | saas | `foxdesk.net` marketing, pricing, signup, legal pages, and public SaaS conversion flow. |
| Migration bridge target | saas | Receives API sync, attachment sync, import evidence, and final cutover status. |
| Installer | self-hosted | Local/shared-hosting install flow and config bootstrap. Not part of hosted customer workspace UI. |
| Public updater | self-hosted | ZIP update channel for free PHP app. Hosted workspaces are centrally deployed. |
| Migration source | self-hosted | Export/sync client, IMAP shutdown on cutover, and ZIP export fallback. |
| Legacy dashboard | legacy | May remain as analytics/compatibility view. Work is the default daily workflow. |

## Route Ownership

| Route/surface | Owner | Notes |
| --- | --- | --- |
| `pages/work.php` | shared | Must use shared work queue modules. |
| `pages/inbox.php` | shared | Must use shared inbox modules. |
| `pages/tickets.php` | shared | Must use shared ticket list view/status group modules. |
| `pages/ticket-detail.php` | shared | Must keep action semantics aligned across editions. |
| `pages/client.php` and client admin surfaces | shared | Must use client overview/rate concepts consistently. |
| `pages/admin/reports.php` | shared | Must keep item-level billing review parity. |
| `pages/admin/settings.php` | shared + edition overlays | Must hide hosted update controls in SaaS and hide SaaS platform controls in self-hosted. |
| `pages/billing.php` | saas | Workspace billing only; no self-hosted equivalent. |
| `pages/platform.php` | saas | Platform admin only; never part of public self-hosted updates. |
| `pages/cloud.php` | saas | Public SaaS website only. |
| `pages/signup.php` | saas | Hosted workspace provisioning only. |
| `pages/stripe-webhook.php` | saas | Signed Stripe webhook only. |
| `pages/admin/migration-export.php` | self-hosted | Self-hosted migration source and ZIP fallback only. |
| `install.php` and `upgrade.php` | self-hosted + local compatibility | Hosted production deploys use managed deployment, not tenant-admin ZIP updates. |

## Completion Rules

- A shared feature is not complete until SaaS and self-hosted either both expose
  the same product behavior or the matrix explicitly marks the difference as an
  edition overlay.
- A SaaS-only feature is not complete if it appears in a public self-hosted
  update package.
- A self-hosted-only feature is not complete if SaaS workspace admins see it as
  normal workspace administration.
- Every new shared workflow module must have a contract test that protects its
  source-language keys and user-facing behavior.
