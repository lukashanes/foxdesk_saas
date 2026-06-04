# Cloud Cutover Hold

Status: active hold
Date: 2026-06-04

## Decision

Do not cut over `helpdesk.aenze.com` to FoxDesk SaaS yet.

The self-hosted application stays the production system until the SaaS workspace
passes a logged-in visual and functional QA gate under the real production
security policy.

## Current State

- Source production: `https://helpdesk.aenze.com`
- SaaS target: `https://app.foxdesk.net`
- SaaS tenant: `Aenze Helpdesk`
- SaaS tenant id: `3`
- SaaS slug: `aenze-helpdesk`
- Migration connection state: `ready_for_cutover`
- Cutover timestamp: `NULL`

## Why The Hold Exists

Imported data is present in SaaS, but the hosted application still has UI
surfaces that depend on inline page styles. Production CSP blocks those inline
styles, so some logged-in pages can render as unstyled HTML.

Cutover would make the cloud workspace the production system before the app is
usable enough for day-to-day support work.

## Conditions To Lift The Hold

The hold can be removed only after all of these pass:

- `Work` renders as a styled queue view on desktop and mobile.
- `Inbox` renders as a styled triage view on desktop and mobile.
- `All tickets` renders styled tabs, filters, and ticket rows.
- `Ticket detail` renders styled timeline, editor, status controls, and side
  panel.
- `New ticket` creates a ticket with correct client/assignee behavior.
- Global search finds open, done, and archived tickets.
- Reports can show, edit, discount, and total billable time rows.
- Attachments can be opened or downloaded from imported tickets.
- Admin/settings pages render styled and remain usable.
- Browser smoke tests fail if a logged-in page renders as unstyled HTML.
- Production smoke tests pass against `https://app.foxdesk.net`.
- QA screenshots are captured for desktop and mobile logged-in flows.

## Milestone 2 Cutover Gate

Run the local logged-in cutover gate before any production cutover decision:

```bash
npm run cutover:gate
```

The gate verifies the cloud app workflow on desktop and mobile, creates a
ticket with an attachment, downloads that attachment, checks global search
against open/done/archived fixtures, reviews billing report rows, and captures
screenshots under `/tmp/foxdesk-cutover-gate-*`.

This gate does not lift the hold by itself. The hold is lifted only after the
same checklist also passes against the real production workspace.

## Milestone 3 Production Cutover Gate

Run the same logged-in gate against the production SaaS workspace before
redirecting any live self-hosted domain:

```bash
FOXDESK_CUTOVER_ADMIN_EMAIL="operator@example.com" \
FOXDESK_CUTOVER_ADMIN_PASSWORD="..." \
FOXDESK_CUTOVER_SEARCH_QUERY="A real ticket/client/report term" \
npm run prod:cutover:gate
```

Production mode is read-only by default. It verifies health, login, Work, Inbox,
ticket list, ticket detail, settings, global search, and report review without
creating a ticket or uploading an attachment.

Run the full production gate only when a real workspace can safely receive a QA
ticket and attachment:

```bash
FOXDESK_CUTOVER_ADMIN_EMAIL="operator@example.com" \
FOXDESK_CUTOVER_ADMIN_PASSWORD="..." \
FOXDESK_CUTOVER_SEARCH_QUERY="A real ticket/client/report term" \
FOXDESK_CUTOVER_ALLOW_MUTATION=1 \
npm run prod:cutover:gate
```

The hold can be lifted only after the production gate passes with
`FOXDESK_CUTOVER_ALLOW_MUTATION=1`, the captured screenshots are reviewed, and
the imported workspace still has correct users, clients, tickets, attachments,
reports, billing state, and email settings.

## Milestone 4 Cutover Evidence Pack

Every cutover gate run writes durable evidence into the screenshot directory:

- `result.json` for automation and audit history.
- `report.md` for manual review.
- Desktop and mobile screenshots for the checked logged-in screens.

The report includes the target URL, run mode, mutation state, searched term,
checklist coverage, raw checks, screenshots, and a cutover hold verdict.

Only this combination can mark the run as eligible for manual cutover review:

- `status` is `passed`
- `mode` is `production`
- `FOXDESK_CUTOVER_ALLOW_MUTATION=1`

All other successful runs keep the hold active. This includes local runs,
production read-only runs, and any run that skipped the ticket/attachment
mutation check.

## Milestone 5 Cutover Evidence Verification

Before final DNS or self-hosted cutover, verify the production evidence pack
with:

```bash
npm run cutover:verify -- /path/to/foxdesk-cutover-gate/result.json
```

The verifier fails unless the evidence is fresh, passed in production mode, used
`FOXDESK_CUTOVER_ALLOW_MUTATION=1`, has `canLiftHold=true`, has every checklist
item passing, points at a non-local production URL, and contains non-empty
screenshot files.

This command is the preflight gate for manual cutover approval. Do not switch
`helpdesk.aenze.com`, disable self-hosted ingest, or mark the migration as
single-active until `cutover:verify` passes against the latest production
evidence pack.

## Milestone 6 Final Cutover Preflight

After `cutover:verify` passes, run the final preflight:

```bash
npm run cutover:preflight -- /path/to/foxdesk-cutover-gate/result.json
```

This command verifies the evidence pack again, runs production smoke checks
against `app.foxdesk.net` and the public site, then writes:

- `cutover-preflight.json`
- `cutover-preflight.md`

The preflight is approved only when both the production evidence and production
smoke checks pass. It still does not change DNS, disable IMAP, stop
notifications, or mark the migration as cut over. It is the final written
go/no-go artifact before a human operator performs those actions.

If the command exits non-zero, the cutover hold remains active.

## Milestone 7 Post-Cutover Confirmation

After the human operator performs final sync, marks the migration as
single-active, disables self-hosted ingest/notifications, and changes the
customer-facing redirect or DNS, run:

```bash
npm run cutover:postcheck -- /path/to/foxdesk-cutover-gate/cutover-preflight.json
```

The postcheck verifies that the preflight was approved, SaaS still passes
production smoke checks, and the old source URL redirects to the SaaS target.
It writes:

- `cutover-postcheck.json`
- `cutover-postcheck.md`

The cutover is considered confirmed only when the postcheck decision is
`cutover_confirmed`. If the command exits non-zero, treat cutover as incomplete
and keep rollback or hold actions available.

## Milestone 8 Cutover Status Summary

At any point during the migration, generate one lifecycle summary from the
available artifacts:

```bash
npm run cutover:status -- --dir=/path/to/foxdesk-cutover-gate
```

The status command reads `result.json`, `cutover-preflight.json`, and
`cutover-postcheck.json` when present, then writes:

- `cutover-status.json`
- `cutover-status.md`

The summary reports the current phase:

- `hold_active`
- `evidence_ready`
- `preflight_approved`
- `preflight_blocked`
- `postcheck_failed`
- `cutover_confirmed`

Use this report as the operator-facing cutover state instead of manually
inspecting several JSON files.

## Explicit Non-Goals During Hold

- Do not redirect `helpdesk.aenze.com` to SaaS.
- Do not disable self-hosted IMAP ingest on `helpdesk.aenze.com`.
- Do not disable self-hosted notifications on `helpdesk.aenze.com`.
- Do not treat the imported SaaS workspace as the production system.
