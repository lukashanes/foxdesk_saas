# Native App API

Status: frozen for the first iOS and Android beta.

FoxDesk native apps must use these endpoints instead of scraping PHP pages. The
API source language is English. Localized text can be rendered by the client or
returned by future localized display fields, but response keys stay English.

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

Mobile clients authenticate with the mobile session endpoints:

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

Cookie-authenticated browser sessions still need CSRF on write endpoints. Mobile
Bearer-token requests do not use browser CSRF because they do not rely on
ambient cookies.

## Frozen App Read Models

### App Shell

`GET index.php?page=api&action=app-shell`

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

`GET index.php?page=api&action=app-home&limit=5`

Frozen `data.home` keys:

- `schema_version`
- `generated_at`
- `limit`
- `work`
- `inbox`
- `timers`
- `notifications`

### Ticket List

`GET index.php?page=api&action=app-ticket-list&view=open&limit=25&offset=0`

Use this for native ticket lists and saved work views.

### Ticket Detail

`GET index.php?page=api&action=app-ticket-detail&id=123`

Frozen `data` keys:

- `ticket`
- `comments`
- `attachments`
- `time_entries`
- `actions`

### Add Comment

`POST index.php?page=api&action=app-add-comment`

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

## Attachments

### Attachment Metadata

`GET index.php?page=api&action=app-attachment-metadata&attachment_id=123`

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

## Timers

### Timer State

`GET index.php?page=api&action=app-ticket-timer&id=123`

Frozen `data` keys:

- `ticket`
- `timer`

### Timer Action

`POST index.php?page=api&action=app-ticket-timer-action`

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

### Notification List

`GET index.php?page=api&action=app-notifications&limit=25&offset=0`

Frozen `data` keys:

- `unread_count`
- `items`
- `pagination`

### Notification Read State

`POST index.php?page=api&action=app-notification-read-state`

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

## Native Beta Boundary

The first native beta should be able to implement:

- sign in, 2FA, refresh, logout
- home/work queues
- ticket list
- ticket detail
- add comment
- attachment preview/download
- timer start/pause/resume/stop/discard
- notification list and read/unread

Any new native screen must either use these read models or add a focused app API
contract before client work starts.
