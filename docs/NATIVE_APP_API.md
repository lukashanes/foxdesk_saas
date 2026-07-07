# Native App API

Status: frozen for the first iOS agent/admin beta. The same contract can be
used by future Android clients.

FoxDesk native apps must use these endpoints instead of scraping PHP pages. The
first iOS release is an agent/admin work companion for existing FoxDesk Cloud
workspaces. It must not expose platform admin, Stripe checkout, public pricing,
or self-hosted setup screens. The API source language is English. Localized text
can be rendered by the client or returned by future localized display fields,
but response keys stay English.

## Response Envelope

All `app-*` endpoints return the same envelope:

```json
{
  "success": true,
  "data": {},
  "meta": {
    "schema_version": 1,
    "generated_at": "2026-06-14T10:00:00+02:00",
    "resource": "app_shell"
  },
  "errors": []
}
```

Frozen top-level keys:

- `data`
- `meta`
- `errors`

The frozen key registry lives in `app_contract_frozen_response_keys()`.

## Authentication

Preferred base path for native apps:

```text
https://app.foxdesk.net/api/mobile/v1
```

The legacy query-string API remains supported for the web app and older
clients, but new native clients should use the versioned paths below.

Mobile clients authenticate with the mobile session endpoints:

- `POST /api/mobile/v1/login`
- `POST /api/mobile/v1/verify-2fa`
- `POST /api/mobile/v1/refresh`
- `GET /api/mobile/v1/me`
- `POST /api/mobile/v1/logout`
- `POST /api/mobile/v1/device-token`
- `POST /api/mobile/v1/device-token/unregister`

The native app registers APNs with `device-token` after notification
permission is granted and calls `device-token/unregister` during sign-out before
local tokens are cleared. Logout must still finish if device unregister fails.

Compatibility aliases:

- `POST index.php?page=api&action=mobile-login`
- `POST index.php?page=api&action=mobile-verify-2fa`
- `POST index.php?page=api&action=mobile-refresh`
- `GET index.php?page=api&action=mobile-me`
- `POST index.php?page=api&action=mobile-logout`
- `POST index.php?page=api&action=mobile-register-device`
- `POST index.php?page=api&action=mobile-unregister-device`

Mobile access tokens are independent from browser cookies and are sent as:

```http
Authorization: Bearer fdm_at_...
```

`login`, `verify-2fa`, `refresh`, and `me` return a compact `user` object for
native identity surfaces. Frozen user keys are `id`, `email`, `first_name`,
`last_name`, `name`, `role`, `language`, `tenant_id`, and `avatar`. `avatar` may
be null, an upload path, a data URL, or an absolute URL; native clients should
display initials when the image cannot be loaded.

Native clients should store mobile session tokens securely, retry a failed
authenticated request once after `401 Unauthorized` by calling
`POST /api/mobile/v1/refresh`, persist the rotated session tokens, and then
repeat the original request. If refresh fails, clear local tokens and return to
sign-in.

Cookie-authenticated browser sessions still need CSRF on write endpoints. Mobile
Bearer-token requests do not use browser CSRF because they do not rely on
ambient cookies.

## Frozen App Read Models

### App Shell

`GET /api/mobile/v1/shell`

Compatibility: `GET index.php?page=api&action=app-shell`

Frozen `data.app_shell` keys:

- `schema_version`
- `generated_at`
- `home_page`
- `user`
- `navigation`
- `capabilities`
- `work_queues`
- `inbox_queues`
- `search_sections`
- `reporting`

### App Home

`GET /api/mobile/v1/work?limit=5`

Compatibility: `GET index.php?page=api&action=app-home&limit=5`

Frozen `data.home` keys:

- `schema_version`
- `generated_at`
- `limit`
- `work`
- `inbox`
- `timers`
- `time`
- `notifications`

`work` and `inbox` are keyed queue maps. Native clients should render known
queues first, tolerate unknown queue keys, and drill into tickets through
`app-ticket-detail`. `timers` is a compact list of active timer rows for the
signed-in user.

`notifications` contains a compact Dashboard notification surface:

- `unread_count`: current unread badge count for the signed-in user.
- `items`: up to three recent notification items using the same shape as
  `GET /notifications`. Native Dashboard clients should show these as recent
  updates and open `ticket_id` directly when present.

`time` is the native Dashboard worked-time block. It reuses the same Work page
read model as the web app and returns exact, non-rounded work time:

- `period`: default `last_30_days` range metadata.
- `totals`: `today`, `week`, `month`, and `selected` values with minutes and
  display labels.
- `entries`: recent work records with ticket id/hash/code/title, client,
  started/ended timestamps, and duration.
- `chart`: per-day series for the selected period, including optional user
  breakdowns for admin/team views.

Native clients should display `time` near the top of Dashboard and use the
ticket ids from `entries` to drill into `app-ticket-detail`.

### Tenant State

`GET /api/mobile/v1/tenant-state`

Compatibility: `GET index.php?page=api&action=app-tenant-state`

Native apps use this endpoint to mirror the SaaS workspace lifecycle without
opening checkout or billing portal UI inside the app.

Frozen `data` keys:

- `tenant`
- `access`
- `billing_actions`
- `usage`
- `capabilities`
- `links`

Native clients may display workspace name, access status, trial/past-due/free
copy, and usage summaries. The first iOS release must not render pricing,
upgrade buttons, Stripe Checkout, or Customer Portal links. If
`data.access.allowed` is false, block work screens and show the access message
with a neutral "contact your workspace admin or FoxDesk support" action.

### Ticket List

`GET /api/mobile/v1/tickets?view=new&limit=25&offset=0`

Compatibility: `GET index.php?page=api&action=app-ticket-list&view=new&limit=25&offset=0`

Use this for native ticket lists and saved work views. For the iOS `Mine`
screen, call the same endpoint with `view=open&assigned_to=me`; the server
resolves `me` to the signed-in agent/admin.

Supported first-release ticket views are `mine` in the iOS UI, implemented as
`view=open&assigned_to=me`, plus API views `new`, `waiting`, `done`, and `all`.
Use `view=new` for the native New tab.

Frozen `data` keys:

- `tickets`
- `view`
- `views`
- `counts`
- `pagination`
- `filters`

### Ticket Detail

`GET /api/mobile/v1/tickets/{id}`

Compatibility: `GET index.php?page=api&action=app-ticket-detail&id=123`

Frozen `data` keys:

- `ticket`
- `comments`
- `attachments`
- `time_entries`
- `actions`

### Ticket Actions

`GET /api/mobile/v1/tickets/{id}/actions`

Compatibility: `GET index.php?page=api&action=app-ticket-actions&id=123`

Use this when a native client needs to refresh available actions without
reloading the full ticket detail.

Frozen `data` keys:

- `ticket`
- `actions`

### Update Ticket

`POST /api/mobile/v1/tickets/{id}`

Compatibility: `POST index.php?page=api&action=app-update-ticket`

Use this from the native agent/admin app to update lightweight ticket workflow
fields without loading the full web admin. The endpoint is staff-only and uses
the same ticket visibility and edit checks as the web app.

JSON body:

```json
{
  "ticket_id": 123,
  "status_id": 5,
  "priority_id": 2,
  "assignee_id": 4
}
```

Send `assignee_id: null` to leave the ticket unassigned. Unknown or unauthorized
status, priority, and assignee values are rejected.

Frozen response keys:

- `ticket`
- `actions`
- `updated_fields`

### Ticket Create Options

`GET /api/mobile/v1/tickets/create-options`

Compatibility: `GET index.php?page=api&action=app-ticket-create-options`

Returns the safe option lists needed by the native new-ticket screen. Clients
are filtered through the current user's organization permissions. Assignees are
filtered through the same staff assignment permission checks used by the web app.

Frozen `data` keys:

- `clients`
- `statuses`
- `priorities`
- `assignees`
- `defaults`

### Create Ticket

`POST /api/mobile/v1/tickets`

Compatibility: `POST index.php?page=api&action=app-create-ticket`

JSON body:

```json
{
  "title": "VPN access stopped working",
  "description": "<p>The VPN client rejects MFA codes.</p>",
  "organization_id": 12,
  "assignee_id": 4,
  "priority_id": 2,
  "status_id": 1,
  "due_date": "2026-07-08",
  "tags": "vpn, access"
}
```

Admins and agents may send `created_at` for backdated work. The server validates
the date and permissions. `due_date` is optional and should only be sent when
the agent explicitly sets it in the native form.

New-ticket attachments are a two-step flow because files need a ticket id. The
native form may stage camera photos, library photos, or Files selections locally;
after this endpoint returns `ticket_id`, upload each staged file through
`POST /api/mobile/v1/tickets/{id}/attachments`. If attachment upload fails after
ticket creation, retry the upload against the returned `ticket_id` instead of
creating a duplicate ticket.

Frozen response keys:

- `ticket_id`
- `ticket_hash`
- `ticket_code`
- `ticket`

### Add Comment

`POST /api/mobile/v1/tickets/{id}/comments`

Compatibility: `POST index.php?page=api&action=app-add-comment`

JSON body:

```json
{
  "ticket_id": 123,
  "content": "Customer-facing reply",
  "is_internal": false,
  "duration_minutes": 15,
  "time_summary": "Investigated the issue"
}
```

Frozen response keys:

- `comment_id`
- `time_entry_id`

`time_entry_id` is present only when a time entry is created.

### Add Comment With Time

`POST /api/mobile/v1/tickets/{id}/comment-with-time`

Compatibility: `POST index.php?page=api&action=app-add-comment-with-time`

Use this when an agent needs one visible work record: a comment and a linked
time entry. `ticket_time_entries.comment_id` is always set for successful calls.

JSON body:

```json
{
  "ticket_id": 123,
  "content": "<p>Checked the VPN profile and regenerated the user config.</p>",
  "is_internal": false,
  "skip_notification": true,
  "manual_date": "2026-07-05",
  "manual_start_time": "09:10",
  "manual_end_time": "09:35",
  "duration_minutes": 25,
  "is_billable": true
}
```

Alternative datetime payload:

```json
{
  "ticket_hash": "u26Y7GdxUmnG",
  "content": "<p>Checked the VPN profile.</p>",
  "started_at": "2026-07-05 09:10:00",
  "ended_at": "2026-07-05 09:35:00",
  "duration_minutes": 25
}
```

Frozen response keys:

- `ticket_id`
- `comment_id`
- `time_entry_id`
- `duration_minutes`
- `started_at`
- `ended_at`

### Delete Comment / Time Entry

These are available for correction flows when the signed-in user has the
required delete scope/permission.

- `POST index.php?page=api&action=app-delete-comment`
- `POST index.php?page=api&action=app-delete-time-entry`

## Attachments

### Upload Attachment Or Editor Image

Preferred ticket-scoped path:

`POST /api/mobile/v1/tickets/{id}/attachments`

Generic multipart path, useful for native upload services that already include
`ticket_id` as form data:

`POST /api/mobile/v1/attachments`

Compatibility: `POST index.php?page=api&action=upload`

Use multipart form data:

- `file`: uploaded file
- `ticket_id`: required for ticket attachments when using the generic
  `/attachments` path; automatically filled from `{id}` on the ticket-scoped
  path
- `purpose=editor-image`: allowed for inline editor images before a comment is
  submitted

Mobile Bearer-token uploads do not use browser CSRF. Cookie-authenticated web
uploads still require CSRF. The response contains `file`; for ticket
attachments it also contains `file.attachment_id`. Native apps should prefer
the ticket-scoped path for normal ticket attachments and may use the generic
path when the same upload helper is shared across ticket/comment flows.

### Attachment Metadata

`GET /api/mobile/v1/attachments/{id}`

Compatibility: `GET index.php?page=api&action=app-attachment-metadata&attachment_id=123`

Frozen `data.attachment` keys:

- `id`
- `ticket_id`
- `comment_id`
- `filename`
- `mime_type`
- `file_size`
- `file_size_label`
- `storage_driver`
- `download_url`
- `preview_url`
- `can_preview`
- `created_at`

Native clients should use `download_url` for authorized download and
`preview_url` only when `can_preview` is true. The API never exposes R2 secret
credentials or raw bucket secrets.

## Clients

### Client Overview

`GET /api/mobile/v1/clients/{id}?view=open`

Compatibility: `GET index.php?page=api&action=app-client-overview&organization_id=12&view=open`

Frozen `data` keys:

- `client`
- `view`
- `counts`
- `tickets`
- `contacts`
- `time`
- `links`

Native clients should use this endpoint for lightweight client context inside a
ticket flow: counts, recent tickets, contacts, and current-month time. It is not
a full client administration endpoint.

## Search

### Global Search

`GET /api/mobile/v1/search?q=vpn&limit=8`

Compatibility: `GET index.php?page=api&action=global-search&q=vpn&limit=8`

Use this for Spotlight-style search across open tickets, completed tickets,
clients, and contacts. The response uses the existing grouped search payload
returned by the web command palette. Native clients should render groups
defensively and ignore unknown future groups. Contact results may include
`organization_id`; when present, open the same native client context as a client
result.

## Timers

### Timer State

`GET /api/mobile/v1/tickets/{id}/timer`

Compatibility: `GET index.php?page=api&action=app-ticket-timer&id=123`

Frozen `data` keys:

- `ticket`
- `timer`

### Timer Action

`POST /api/mobile/v1/tickets/{id}/timer`

Compatibility: `POST index.php?page=api&action=app-ticket-timer-action`

JSON body:

```json
{
  "ticket_id": 123,
  "action": "start"
}
```

Supported actions:

- `start`
- `pause`
- `resume`
- `stop`
- `discard`

Frozen response keys:

- `ticket`
- `timer`
- `action`
- `result`

## Notifications

### Push Notification Payload

APNs ticket notifications should include a ticket identifier so the native app
can open the right ticket after a user taps the notification:

```json
{
  "aps": {
    "alert": {
      "title": "New reply",
      "body": "Eva replied to VPN access stopped working"
    },
    "sound": "default"
  },
  "ticket_id": 123
}
```

The iOS client also accepts `ticketId`, `ticketID`, and nested
`data.ticket_id` for compatibility with future provider payload formats.

First-release ticket push types are:

- `new_ticket`
- `new_comment`
- `assigned_to_you`
- `mentioned`
- `ticket_updated`
- `status_changed`
- `priority_changed`
- `due_date_reminder`

APNs delivery is dispatched only from already-created in-app notifications, so
it follows the same user visibility rules, tenant boundary, and notification
preferences as the web notification center.

Server-side APNs dispatch is enabled only when the production environment has
Apple push credentials configured:

- `APNS_TEAM_ID`
- `APNS_KEY_ID`
- `APNS_AUTH_KEY` or `APNS_AUTH_KEY_PATH`
- `APNS_BUNDLE_ID`

If these values are missing, FoxDesk still stores device tokens through
`mobile-register-device`, but APNs delivery is skipped. This keeps local,
self-hosted, and staging environments safe by default.

APNs smoke testing:

```bash
npm run ios:apns:smoke -- --json
APNS_TEST_DEVICE_TOKEN=<hex-token> npm run ios:apns:smoke -- --send --environment=production
```

The first command is a safe dry-run that validates payload shape and configured
credentials without sending. The dry-run reports `validated_types` and
`validated_payloads` for every first-release ticket push type. The second
command sends one real push for the selected `--type` and must use a token
captured from a physical device. In an internal debug/staging build, enable
notifications, open FoxDesk Account → Push diagnostics, and use
`Copy APNs token`. That diagnostics section is guarded behind debug builds and
must not appear in Production/App Store UI.

### Notification List

`GET /api/mobile/v1/notifications?limit=25&offset=0`

Compatibility: `GET index.php?page=api&action=app-notifications&limit=25&offset=0`

Frozen `data` keys:

- `unread_count`
- `items`
- `pagination`

### Notification Read State

`POST /api/mobile/v1/notifications/read-state`

Compatibility: `POST index.php?page=api&action=app-notification-read-state`

Single notification:

```json
{
  "scope": "notification",
  "notification_id": 123,
  "is_read": true
}
```

Mark one notification unread:

```json
{
  "scope": "notification",
  "notification_id": 123,
  "is_read": false
}
```

Mark a ticket group read:

```json
{
  "scope": "ticket",
  "ticket_id": 123,
  "is_read": true
}
```

Mark all read:

```json
{
  "scope": "all",
  "is_read": true
}
```

Frozen response keys:

- `unread_count`
- `updated`

## Reporting Preview

`GET /api/mobile/v1/reporting-review?time_range=this_month&organization_ids[]=12`

Compatibility: `GET index.php?page=api&action=app-reporting-review&time_range=this_month&organization_ids[]=12`

This endpoint is available to admins and report-capable agents for previewing
billable work. It is not part of the first iOS release UI, but it remains in the
native contract for future admin/report slices.

Frozen `data` keys:

- `filters`
- `range`
- `entries`
- `totals`
- `total_labels`
- `actions`
- `bulk_actions`
- `pagination`

## First iOS Beta Boundary

The first native iOS build should use:

- `mobile-login`, `mobile-verify-2fa`, `mobile-refresh`, `mobile-me`,
  `mobile-logout`
- `app-shell`, `app-home`, `app-tenant-state`
- `app-ticket-list`, `app-ticket-detail`, `app-ticket-actions`,
  `app-ticket-create-options`, `app-update-ticket`
- `app-create-ticket`, `app-add-comment`, `app-add-comment-with-time`
- `app-ticket-timer`, `app-ticket-timer-action`
- `upload`, `app-attachment-metadata`
- `app-client-overview`
- `global-search`
- `app-notifications`, `app-notification-read-state`
- `mobile-register-device`, `mobile-unregister-device`

The first native iOS build must not implement platform admin, billing checkout,
pricing, upgrade prompts, or self-hosted instance setup.

## Live Mobile API Smoke

Use this smoke before TestFlight handoff or when a new machine needs to verify
the SaaS mobile API contract against a real workspace:

```bash
npm run ios:api:smoke -- --json
FOXDESK_IOS_SMOKE_EMAIL=<agent@example.com> \
FOXDESK_IOS_SMOKE_PASSWORD=<password> \
npm run ios:api:smoke -- --require-credentials --json
```

Without credentials the command is a safe preflight and prints the required
environment variables. With credentials it calls only the versioned
`/api/mobile/v1` endpoints and verifies mobile login, optional 2FA via
`FOXDESK_IOS_SMOKE_2FA_CODE`, `me`, `work`, tickets, first ticket detail,
search, and logout.

The default live smoke is read-only. For the final agent workflow proof before
TestFlight, explicitly enable the write smoke. Use staging when possible. If
the smoke runs on production, use a disposable workspace and set
`FOXDESK_IOS_ALLOW_PRODUCTION_WRITE_SMOKE=1` for that run.

```bash
FOXDESK_IOS_SMOKE_EMAIL=<agent@example.com> \
FOXDESK_IOS_SMOKE_PASSWORD=<password> \
FOXDESK_IOS_SMOKE_WRITE=1 \
npm run ios:api:smoke -- --require-credentials --json
```

With `FOXDESK_IOS_SMOKE_WRITE=1`, the smoke loads `tickets/create-options`,
creates one internal smoke ticket through `POST /api/mobile/v1/tickets`, adds a
linked internal timed comment through
`POST /api/mobile/v1/tickets/{id}/comment-with-time`, uploads a small
attachment through `POST /api/mobile/v1/attachments`, and reloads the created
ticket detail to verify the comment body, linked 5-minute time entry, and
attachment are visible together. It uses `skip_notification: true` and an
internal comment so it does not email customers. Optional overrides are available when the smoke account
needs specific defaults: `FOXDESK_IOS_SMOKE_CLIENT_ID`,
`FOXDESK_IOS_SMOKE_ASSIGNEE_ID`, `FOXDESK_IOS_SMOKE_PRIORITY_ID`, and
`FOXDESK_IOS_SMOKE_STATUS_ID`.
