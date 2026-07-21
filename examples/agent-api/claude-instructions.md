# Claude Agent Instructions

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

Use this when connecting Claude Desktop, Claude Code, or a Claude Project to
FoxDesk through a scoped API key.

## Secret Setup

Store the key outside the prompt:

```bash
cp examples/agent-api/.env.example examples/agent-api/.env
```

Then edit `.env` and paste the key from **Settings -> API & agents**.

## Project Prompt

```text
You can operate FoxDesk only through the scripts in examples/agent-api.
Never print FOXDESK_API_TOKEN.
Treat FOXDESK_BASE_URL as an API host, not a browser page. Do not open
/index.php?page=login and do not wait for cookies. Use Authorization: Bearer
FOXDESK_API_TOKEN on every request.
At the start of every session, load agent-docs with the current token and use it
as the source of truth for allowed actions, scopes, request fields, and safety
rules.
Before write actions, confirm the target ticket or client when it is ambiguous.
Use agent-create-ticket for the concise main ticket, agent-add-update for a
comment without time, agent-add-work-entry for tracked work, and
app-reporting-review for report drafts.
For retries, reuse the same Idempotency-Key with an unchanged body. If FoxDesk
returns 409 with Retry-After, wait and retry; do not generate a second key.
If the API returns 401 or 403, ask for a token with the required scope.
```

## Smoke Commands

```bash
sh examples/agent-api/create-ticket.sh
FOXDESK_TICKET_ID=123 FOXDESK_COMMENT='<p><strong>13 Jul 2026 - 27 min</strong></p><ul><li>Reviewed campaign performance.</li></ul>' FOXDESK_SKIP_NOTIFICATION=1 sh examples/agent-api/add-comment.sh
FOXDESK_ORGANIZATION_ID=1 sh examples/agent-api/prepare-report.sh
```

Use `comment-with-time.sh` for tracked work. Use `log-time.sh` only when no
comment belongs to the time entry.
