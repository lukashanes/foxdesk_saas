# Local Deployment

This local deployment runs FoxDesk SaaS like a small production stack on your machine:

- PHP 8.2 + Apache app container
- MariaDB 11
- Mailpit for local SMTP and email preview
- persistent Docker volumes for database, uploads, storage, and backups

## URLs

- App: http://127.0.0.1:8090
- Installer: http://127.0.0.1:8090/install.php
- Mailpit: http://127.0.0.1:8025

## Commands

Install dependencies:

```bash
npm ci
```

Start and install the local app:

```bash
npm run local:install
```

Seed demo data:

```bash
npm run local:seed
```

Run a smoke test against the running local app:

```bash
npm run local:smoke
```

Follow logs:

```bash
npm run local:logs
```

Stop containers but keep data:

```bash
npm run local:down
```

Reset everything, including database/storage volumes and generated `config.php`:

```bash
npm run local:reset
```

## Default Accounts

Installer-created admin:

- Email: `admin@example.test`
- Password: `AdminPass123!`

Seed-created demo users:

- Agent: `agent@example.test` / `AgentPass123!`
- Client: `client@example.test` / `ClientPass123!`

## Email Testing

Mailpit listens on:

- SMTP: `mailpit:1025` from inside Docker
- Web UI: http://127.0.0.1:8025

Use these SMTP values in FoxDesk settings for local email tests:

- Host: `mailpit`
- Port: `1025`
- Encryption: none
- Auth: off

## Notes

- The local stack uses the real installer and creates a default tenant.
- Attachments and backups are stored in Docker volumes, not your working tree.
- `local:smoke` verifies health, login, ticket creation, attachment upload, and attachment download.
- The Playwright E2E suite still uses its own isolated temporary Docker environment.

