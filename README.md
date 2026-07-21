# FoxDesk Cloud

FoxDesk Cloud is the hosted SaaS edition of FoxDesk: a managed helpdesk, time
tracking, reporting, billing, and platform control plane built on the PHP/MariaDB
FoxDesk core.

**Public SaaS website:** [foxdesk.net](https://foxdesk.net)
**Customer app:** [app.foxdesk.net](https://app.foxdesk.net)
**Platform console:** [platform.foxdesk.net](https://platform.foxdesk.net)
**Self-hosted edition website:** [foxdesk.org](https://foxdesk.org)

## Release channels

This repository is the **SaaS/managed deployment** repository. It is not the
public self-hosted updater channel for the free PHP app.

- Public self-hosted PHP releases live in `lukashanes/foxdesk`.
- SaaS deployments from this repository use `FOXDESK_UPDATE_CHANNEL=managed`.
- Hosted customer workspaces are updated centrally by deployment, not by tenant
  admins installing public ZIP packages.
- Self-hosted to SaaS transfer is primarily API sync with attachment sync and
  final cutover. ZIP export/import remains a fallback only.

See [docs/RELEASE_CHANNELS.md](docs/RELEASE_CHANNELS.md).
For the documentation map, see [docs/README.md](docs/README.md). For the current
technical-debt execution plan, see
[docs/TECHNICAL_DEBT_PLAN.md](docs/TECHNICAL_DEBT_PLAN.md).

---

## Features

**Workspace Helpdesk**
- Work queues, inbox intake, ticket registry, and client detail views
- Create, assign, comment, resolve, and archive tickets
- Custom statuses, priorities, and ticket types
- Tags, due dates, and organization assignment
- Internal notes (agent-only) and public comments
- Public ticket sharing via secure links with expiration
- Inline ticket-list editing for subject, type, status, priority, due date, company, and assignee
- Compact Quick Add row for creating tickets directly from the list
- Bulk actions (status, priority, assignment)
- Advanced filtering and full-text search
- Edit history tracking on all fields

**Time Tracking**
- Built-in timers with start, pause, resume, stop
- Quick Start mode for instant timer launch
- Sidebar timer widget with global visibility
- Manual time entry with descriptions and quick minute presets
- Billable vs. non-billable hours
- Configurable rounding (0/15/30/60 min)
- Cost rates per user, billing rates per organization, and optional custom rates per ticket with admin sidebar override
- Time reports with optional custom report rates, PDF, and CSV export

**Notifications**
- In-app notification center with full-page view
- Notifications grouped by ticket
- Header dropdown with real-time badge count
- Dashboard notification widget
- Email notifications for ticket events
- Browser push notifications with one-click opt-in
- Per-user notification preferences (email, in-app, sound)

**Reporting & Analytics**
- Report builder with date ranges and filters
- Financial reports (billable, cost, profit)
- Public shareable report links with expiration
- Dashboard KPI cards and activity feed
- User activity tracking (admin)

**AI Agent Integration**
- REST API for AI automation
- Profile API access for scoped assistant tokens
- Agent Connect page with instructions for Codex, Claude, curl, and MCP clients
- Bearer token authentication with usage tracking, idempotency, and audit logs
- Endpoints for ticket creation, lookup, status updates, comments, time logs, and metadata lists

**Email Integration**
- SaaS outbound transactional email through Cloudflare Email Sending
- SaaS inbound ticket replies through Cloudflare Email Routing plus-addresses
- Self-hosted compatibility path for IMAP email-to-ticket ingest
- Inbound email attachments linked directly to ticket threads
- HTML email renderer with readable paragraphs, lists, and links
- CC/BCC recipients on ticket replies

**User Roles**
- **Admin** — Full access, settings, user management, reports, AI agents
- **Agent** — Ticket handling, time tracking, internal notes
- **Client** — Submit and view own tickets, reply

**Organizations & Clients**
- Organization management with contact info and billing rates
- Client portal with limited ticket access
- Multi-organization user assignment

**SaaS Platform**
- Tenant/workspace provisioning and lifecycle management
- Platform console separated from customer workspace admin
- Stripe Checkout, Customer Portal, VAT ID collection, tax support, and billing lifecycle state matrix
- 14-day trial without card, manual free override, past-due grace, suspension, and reactivation
- Cloudflare R2 attachment storage with tenant-prefixed keys
- Deployment evidence, production smoke, restore evidence, and backup gates

**Recurring Tasks**
- Scheduled ticket creation (weekly, monthly, yearly, custom)
- Configurable assignee, type, priority, organization

**PWA Support**
- Installable as desktop and mobile app
- Dynamic app manifest with custom icons

**Multi-language**
- English, Czech, German, Spanish, Italian
- Per-user language preference

**Updates**
- SaaS workspaces are updated centrally through managed deployment.
- Public ZIP update flow belongs to the self-hosted edition only.

**Security & Ops**
- TOTP 2FA with backup codes and optional per-role enforcement
- Attachment downloads respect ticket access and public share permissions
- Database-backed sessions survive app or container restarts
- Public health check endpoint for uptime monitoring
- Post-update health checks and automatic maintenance recovery

**More**
- Dark mode with CSS variable theming
- Responsive design (mobile, tablet, desktop)
- Keyboard shortcuts
- Pseudo-cron (works without system cron)
- Remember-me persistent login
- Allowed senders management for email ingest

---

## Requirements

| Requirement | Minimum |
|-------------|---------|
| PHP         | 8.1     |
| MySQL       | 5.7+ / MariaDB 10.2+ |
| Disk space  | 50 MB   |

Recommended production runtime is PHP 8.2 with MariaDB 10.11+ in Docker.

Required PHP extensions: `pdo_mysql`, `mysqli`, `mbstring`, `json`, `openssl`,
`zip`, `imap`

---

## Local SaaS Start

```bash
npm ci
npm run local:install
npm run local:seed
npm run local:smoke
```

Local URLs:

- App: `http://127.0.0.1:8090`
- Mailpit: `http://127.0.0.1:8025`

See [docs/LOCAL_DEPLOYMENT.md](docs/LOCAL_DEPLOYMENT.md) for the full local flow.

## Production Start

Production is Docker-based and intended for `app.foxdesk.net` behind Cloudflare:

```bash
cp .env.production.example .env.production
cp config.production.example.php config.php
deploy/hetzner/preflight.sh
deploy/hetzner/deploy.sh
```

See [docs/HETZNER_CLOUDFLARE_DEPLOYMENT.md](docs/HETZNER_CLOUDFLARE_DEPLOYMENT.md) and [docs/LAUNCH_READINESS.md](docs/LAUNCH_READINESS.md).

The deployment script is not considered complete until app health, production
smoke, restore evidence, and deployment evidence pass.

---

## Tech Stack

- **Backend:** PHP 8.1+ (no framework)
- **Database:** MySQL / MariaDB
- **Frontend:** server-rendered PHP views, Tailwind CSS utilities, Alpine.js, focused JS assets
- **Styling:** custom `theme.css`, public `assets/public/cloud.css`, design tokens, dark mode support
- **SaaS services:** Cloudflare DNS/WAF/R2/Email, Stripe Billing, Hetzner Docker host

---

## Project Structure

```
index.php              Entry point and router
config.example.php     Configuration template
upgrade.php            Database migration tool
rescue.php             Emergency recovery
theme.css              Custom styles + dark mode
tailwind.min.css       Tailwind CSS
version.json           Version info for auto-updates
assets/js/             JavaScript modules
includes/              Core PHP (auth, DB, functions, API, languages)
includes/api/          REST API handlers
includes/components/   Reusable UI components
includes/lang/         Translation files
includes/modules/      Tested domain modules and read models
pages/                 Page controllers
pages/admin/           Admin panel pages
bin/                   CLI scripts (cron, email ingest, maintenance)
deploy/hetzner/        Production deployment, preflight, and backup scripts
cloudflare/            Cloudflare Worker code for email routing
examples/agent-api/    Assistant/CLI/MCP examples for scoped API keys
```

---

## Cron Jobs

```bash
# Email ingest (every 5 min, if IMAP enabled)
*/5 * * * * php /path/to/bin/ingest-emails.php

# Recurring tasks (hourly)
0 * * * * php /path/to/bin/process-recurring-tasks.php

# Maintenance (daily)
0 3 * * * php /path/to/bin/run-maintenance.php
```

FoxDesk also includes a **pseudo-cron** system that runs tasks on page load, so
cron jobs are optional on shared hosting. In SaaS production, the Docker cron
worker runs maintenance every five minutes. IMAP ingest is kept for self-hosted
compatibility; SaaS inbound replies use Cloudflare Email Routing.

---

## API

FoxDesk includes a REST API for automation and AI agent integrations.

**Endpoints:**
- `GET agent-me` — Current token identity
- `GET agent-list-statuses` — List ticket statuses
- `GET agent-list-priorities` — List priority levels
- `GET agent-list-users` — List users / agents
- `POST agent-create-ticket` — Create tickets
- `GET agent-get-ticket` — Fetch full ticket detail
- `POST agent-update-status` — Change ticket status
- `POST agent-add-comment` — Add comments
- `POST agent-log-time` — Log time entries
- `GET agent-list-tickets` — List and filter tickets

Authentication via Bearer token generated in **Settings -> API & agents**. Tokens
inherit the creator's permissions and can be scoped by capability.

The **Agent Connect** page and `examples/agent-api/` provide ready-to-use
instructions for Codex, Claude, curl, and local MCP clients.

See:

- [docs/AGENT_API_QUICKSTART.md](docs/AGENT_API_QUICKSTART.md)
- [docs/AGENT_API_CONTROL.md](docs/AGENT_API_CONTROL.md)
- [docs/AGENT_MCP_SERVER.md](docs/AGENT_MCP_SERVER.md)

## Release Gates

Common local gate:

```bash
npm run lint:php
npm run test:csp-ui
npm run test:app-frontend
npm run test:app-shell-visual
npm run test:visual-qa
npm run local:smoke
```

Production gate:

```bash
npm run prod:smoke
npm run prod:deploy:evidence
```

---

## License

GNU Affero General Public License v3 (AGPL-3.0). See [LICENSE.md](LICENSE.md).

Created by [Lukas Hanes](https://lukashanes.com).
