# FoxDesk Product Architecture Refactor

This document defines the direction for the Work, Inbox, Tickets, Clients,
Reports, notifications, and email refactor.

## Language

- Product concepts, code symbols, events, modules, database-oriented names, and
  tests use English as the source language.
- Czech and other languages are translations only.
- Renaming UI strings is not considered a product change. A product change must
  improve workflow, domain structure, or system behavior.

## Core Product Concepts

- Work: personal and team queues that answer what needs attention now.
- Inbox: triage for new, unassigned, imported, or customer-replied tickets.
- Tickets: searchable registry of all ticket history.
- Clients: customer context, contacts, work history, rates, and billing context.
- Reports: time, billable work, adjustments, and invoice preparation.
- Activity log: complete audit trail inside the application.
- Notification policy: rules for when an event deserves an email or only an
  in-app/activity record.
- Status group: stable workflow group above customizable statuses.

## Status Groups

Custom statuses map into stable internal groups:

- new
- active
- waiting
- done
- archived

Queues, search, reports, and email rules should depend on status groups instead
of raw status names.

## Module Rules

- Page files should stay thin.
- Business logic belongs under `includes/modules/*` or existing focused service
  files.
- New notification and email behavior must go through policy/dispatcher modules.
- Email is not an audit log. Audit data belongs in the application.
- New inbound email behavior should parse mail, resolve/create a ticket, create a
  comment, and dispatch the same ticket events as the web UI.

## Current First Step

The first refactor step adds:

- `includes/modules/tickets/ticket-status-groups.php`
- `includes/modules/tickets/ticket-events.php`
- `includes/modules/notifications/notification-policy.php`
- `includes/modules/email/email-renderer.php`
- `includes/modules/work/work-queues.php`
- `includes/modules/inbox/inbox-service.php`
- `includes/modules/search/global-search.php`

The first behavior change is status-change email suppression for non-actionable
workflow moves. Status changes still email when they include a customer-facing
comment/time or move into an actionable group such as `waiting` or `done`.

The second behavior change adds shared Work queue filters for `mine`,
`unassigned`, `overdue`, `waiting`, and `done_today`. UI pages should consume
these queues instead of recreating queue logic inside page files.

The third behavior change adds shared Inbox triage filters for `triage`,
`customer_replies`, and `email_imports`. Inbox is the decision layer for new or
customer-replied work, not a replacement for the full ticket registry.

The fourth behavior change adds shared Global Search sections for `open_tickets`,
`done_tickets`, and `clients`. Search should behave like a Spotlight layer and
show completed history without forcing users to switch ticket filters first.

The fifth behavior change adds a compact Reports publishing flow above the heavy
time-report tables. Billing review should start from a client and period, then
move to detailed billable items, adjustments, and client-facing report sharing.

The sixth behavior change adds an authenticated `app-shell` API contract for the
web app and future native iOS/Android clients. Native clients should consume
stable concepts such as Work, Inbox, Tickets, Clients, Reports, queue counts,
capabilities, search sections, and reporting entrypoints instead of scraping or
duplicating PHP page logic.

The seventh behavior change adds an authenticated `app-home` API contract for
the first native app screen. It combines the app shell with compact Work and
Inbox queue items, active timers, and unread notification counts so mobile
clients can render a fast, native first screen from one stable endpoint.

The eighth behavior change tightens email as a work channel. Incoming email body
cleanup now preserves readable paragraphs and lists, strips quoted history and
mobile signatures, and picks the better text/html body. Ticket notification
emails are rendered through the shared email renderer, and combined
status-plus-comment submits send one actionable notification instead of two.
The pseudo-cron endpoint also respects disabled IMAP settings while the inline
fallback keeps shared-hosting ingest available without a real server cron.

The ninth behavior change extracts billing review math from the reports page
into `includes/modules/reports/billing-review.php`. Detailed time reports can
now review a client and period at item level, set an effective hourly rate,
apply percent or fixed-amount discounts, set item totals, and keep live totals in
sync while preserving the current storage model on `ticket_time_entries`.

The tenth behavior change adds a frontend contract bridge. Existing PHP pages
remain server-rendered, but shared surfaces expose stable data attributes and a
small JavaScript client consumes the authenticated `app-*` API contracts. This
creates a clean migration boundary for the SaaS web shell and native apps
without duplicating business rules in the browser or parsing rendered HTML.

The eleventh behavior change makes Work and Inbox contract-first surfaces. PHP
still renders a complete fallback, but the shared workspace surface declares its
active queue and collection, then the frontend bridge refreshes queue counts and
ticket rows from the `app-home` contract. Work reads `home.work`; Inbox reads
`home.inbox`. Browser rendering stays DOM-based and does not inject HTML
strings.

The twelfth behavior change makes the Tickets registry consume the
`app-ticket-list` contract without replacing the existing inline editing and bulk
action controls. Ticket rows and mobile cards expose stable field mounts, and the
frontend bridge refreshes text/status data in place from the API. This keeps the
page usable if JavaScript or the API fails while moving ticket list reads toward
the same contract that native apps will consume.

The thirteenth behavior change makes the Client center consume the
`app-client-overview` contract. The page still renders from PHP, but client
stats, ticket tabs, recent tickets, contacts, and month billing summaries expose
stable mount points. The frontend bridge refreshes those values from the shared
read model in place, so web, SaaS shell, and future native apps use the same
client overview semantics.

The fourteenth behavior change makes the detailed Reports billing review consume
the `app-reporting-review` contract. The existing PHP table and item-level
adjustment forms stay intact, while totals and rows expose stable mounts and the
frontend bridge verifies them against the shared billing-review read model. This
keeps rate, discount, target-total, and report-publishing work tied to one
contract instead of separate web and native interpretations.

The fifteenth behavior change reduces duplicate notification noise. When a
ticket is created already assigned to a staff member, that staff member receives
the actionable assignment signal instead of also receiving the generic
new-ticket signal. The same rule applies to email policy and in-app
notifications; the activity log remains the full audit trail.

The sixteenth behavior change tightens SaaS host separation. Public marketing,
customer workspace, and platform console links keep their own hosts, and Stripe
Billing Portal return URLs send platform admins back to the platform host while
workspace admins return to the workspace host. Billing state actions must follow
the tenant lifecycle instead of showing generic activation buttons.
