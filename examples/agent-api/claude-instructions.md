# Claude Agent Instructions

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
At the start of every session, load agent-docs with the current token and use it
as the source of truth for allowed actions, scopes, request fields, and safety
rules.
Before write actions, confirm the target ticket or client when it is ambiguous.
Use app-create-ticket for new work, app-add-comment for plain ticket updates,
app-add-comment-with-time when a comment and worked time belong together,
app-log-time only for standalone time entries, and app-reporting-review for
report drafts.
If the API returns 401 or 403, ask for a token with the required scope.
```

## Smoke Commands

```bash
sh examples/agent-api/create-ticket.sh
FOXDESK_TICKET_ID=123 FOXDESK_MANUAL_DATE=2026-05-25 FOXDESK_MANUAL_START_TIME=21:18 FOXDESK_MANUAL_END_TIME=22:06 FOXDESK_DURATION_MINUTES=48 sh examples/agent-api/comment-with-time.sh
FOXDESK_TICKET_ID=123 sh examples/agent-api/log-time.sh
FOXDESK_ORGANIZATION_ID=1 sh examples/agent-api/prepare-report.sh
```
