# Codex Agent Instructions

## Canonical ticket workflow

- Use only the FoxDesk Agent API. Never use a web browser.
- Start with `agent-docs`, then `agent-me`.
- Before changing an existing ticket, call `agent-get-ticket`.
- Every POST requires a unique `Idempotency-Key`.
- Keep the main ticket concise: title, general description, client, assignee,
  status, and priority only.
- Use `agent-add-update` for a comment without tracked time.
- Use one `agent-add-work-entry` request for each tracked work record; send the
  formatted comment and `duration_minutes` together.
- Finish with `agent-get-ticket`: every tracked-work comment must have a linked
  time entry with `comment_id`, totals must match, and no duplicate may exist.

Use this when connecting Codex to FoxDesk through a scoped API key.

## Local Secret

Create `examples/agent-api/.env` from `.env.example` and store:

```bash
FOXDESK_BASE_URL=https://app.foxdesk.net
FOXDESK_API_TOKEN=fdx_replace_with_token_from_settings
```

Never paste a production token into a shared prompt. Never print FOXDESK_API_TOKEN.
Prefer the local `.env` file or your secret manager.

## API, Not Browser Login

Treat `FOXDESK_BASE_URL` as the API host. Do not open
`/index.php?page=login`, do not wait for a browser session, and do not use
cookies. A FoxDesk API token only works through HTTP requests that include:

```bash
Authorization: Bearer $FOXDESK_API_TOKEN
```

## Start Every Session

Before choosing a FoxDesk action, load the live documentation for the current
token:

```bash
curl -s "$FOXDESK_BASE_URL/index.php?page=api&action=agent-docs" \
  -H "Authorization: Bearer $FOXDESK_API_TOKEN"
```

Use that response as the source of truth for allowed actions, required scopes,
request fields, and safety rules.

## Allowed Tools

Use these scripts for the canonical ticket workflow:

```bash
sh examples/agent-api/create-ticket.sh
sh examples/agent-api/add-comment.sh
sh examples/agent-api/comment-with-time.sh
sh examples/agent-api/log-time.sh
sh examples/agent-api/prepare-report.sh
```

Use the comment-with-time helper for tracked work. Use a standalone time entry
only when no comment belongs to the work.

## Behavior

- Read the user's intent first, then set the relevant `FOXDESK_*` variables.
- Use `FOXDESK_TICKET_ID` or `FOXDESK_TICKET_HASH` before commenting or logging
  time.
- Treat 401/403 as permission or token-scope problems, not application bugs.
- For write actions, keep the default `Idempotency-Key` header or set
  `FOXDESK_IDEMPOTENCY_KEY` when retrying the same action.
- If the API returns `409` with `Retry-After`, wait and retry the unchanged
  request with the same idempotency key. Never reuse that key for new content.
- Summarize the created ticket id, daily comments, or report totals back to the
  user without revealing the API token.
