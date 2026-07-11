# Agent API Control

Goal: let a trusted assistant control FoxDesk through a scoped API key without
giving it more power than the person who created the key.

## Status

Implemented v1:

- Settings API key creation/revocation with scoped permissions and expiry.
- Bearer-token authentication for agent/app/upload API actions.
- Scope enforcement, rate limiting metadata, idempotency keys for write retries,
  and write audit logging.
- Tenant-bound token storage and audit/idempotency tables.
- Full SaaS app read surface for Work/app shell, ticket list, ticket detail,
  ticket actions, attachment metadata, timers, client overview, reporting
  review, notifications, plus write actions for creating tickets, adding
  comments, logging time, timer actions, and uploads.
- Live `agent-docs` endpoint for assistants to read current token scopes,
  allowed actions, missing scopes, request shapes, examples, and safety rules
  before doing work.
- Practical quickstart examples for Codex, Claude, and curl live in
  `docs/AGENT_API_QUICKSTART.md` and `examples/agent-api/`.
- A local stdio MCP server wrapper lives in `examples/agent-api/mcp-server.js`
  and is documented in `docs/AGENT_MCP_SERVER.md`.
- Agent tool manifest, MCP write dry-run/confirmation, and local agent smoke
  gates are tracked in `docs/AGENT_API_MILESTONES.md`.

Still future-facing:

- Destructive write endpoints beyond ticket/comment/time workflows.

## User Flow

1. An admin opens **Settings -> API & agents**.
2. The user creates an API key for an assistant, CLI, or MCP client.
3. The user chooses an expiry and optional scopes such as tickets, comments,
   time entries, reports, clients, attachments, or read-only access.
4. The key is copied once and stored as a local secret, for example:

```bash
FOXDESK_BASE_URL=https://app.foxdesk.net
FOXDESK_API_TOKEN=fdx_...
```

5. Codex, Claude, a CLI script, or a future MCP server uses the key through the
   public API. Pasting a key into a conversation can work for short tests, but a
   local environment secret is the safer default.
6. The agent must treat the FoxDesk URL as an API host, not as a browser login
   page. It must not open `/index.php?page=login` or wait for cookies; every
   request uses `Authorization: Bearer $FOXDESK_API_TOKEN`.
7. The agent starts each session with:

```bash
GET /index.php?page=api&action=agent-docs
Authorization: Bearer $FOXDESK_API_TOKEN
```

This endpoint is available to every valid API token and returns the live
permission-aware documentation for that token.

Important distinction:

- **Settings -> API & agents** creates scoped keys and manages AI-agent records.
- **Profile** only links to the API page; it is no longer the main setup path.

External tools such as Codex, Claude, curl scripts, and MCP clients should use
scoped workspace API keys from Settings. They should not require a
platform/admin-only setup path.

## Permission Model

- The token inherits the creator's role, tenant, organization visibility, and
  permission map.
- A token cannot request a scope the creator does not have.
- Disabling the user disables the user's tokens.
- Revoking the token immediately blocks future requests.
- SaaS tokens are tenant-bound. Self-hosted tokens are instance-bound.
- Platform-admin actions require explicit platform credentials and never use a
  normal workspace token.

## Agent Actions

- Read app shell, Work queues, ticket lists, ticket detail, clients, and reports.
- Read live API documentation and its own allowed actions through `agent-docs`.
- Search open and done tickets.
- Create tickets.
- Add public or internal comments.
- Add manual time entries to a ticket.
- Start, pause, resume, stop, or discard a timer.
- Attach files when the user can attach files.
- Prepare report drafts and export/share reports when the user can access
  reports.
- Read notifications and mark them read.

## Safety Rules

- Every write creates an audit entry with token id, token label, user id,
  action, target resource, timestamp, IP/user agent when available, and request
  id.
- Write endpoints accept idempotency keys to avoid duplicate ticket/comment/time
  creation when an agent retries.
- A concurrent request with the same key returns `409` and `Retry-After`; the
  agent must retry the unchanged request instead of generating another key.
- MCP write actions require `confirm:true` and support `dry_run:true`.
- Destructive actions need explicit scopes and should support dry-run before
  they are exposed.
- Tokens have rate limits and last-used metadata.
- Tokens are stored hashed; raw token values are shown only once.
- Migration imports API tokens as inactive so customers rotate them after
  cutover.

## Acceptance Criteria

- A token cannot see a ticket outside the creator's organization or tenant.
- A read-only token cannot create tickets, comments, time entries, or reports.
- A user without report permission cannot create a report through an agent.
- A disabled user or revoked token cannot call the API.
- Agent-created tickets, comments, time entries, attachments, and reports appear
  in the same UI as manually created work.
- Agent writes are visible in the activity/audit log.
- Repeated retries with the same idempotency key do not create duplicates.
- Contract tests cover token permission inheritance, tenant isolation,
  organization isolation, write audit entries, and idempotency.
