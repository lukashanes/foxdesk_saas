# Hetzner + Cloudflare Deployment Notes

This is the recommended first deployment path for FoxDesk SaaS work. It keeps the current PHP application intact and uses Cloudflare for edge, security, storage, and email services.

## Target Hostnames

- `foxdesk.org` stays the public open-source/self-hosted website.
- `app.foxdesk.net` is the production customer workspace application.
- `platform.foxdesk.net` is the SaaS operator console for platform admins only.
- `foxdesk.net` is the public SaaS website or canonical redirect target.
- `api.foxdesk.net`, `status.foxdesk.net`, and `support.foxdesk.net` are reserved for later.

## Hetzner Responsibilities

- Ubuntu VPS.
- Docker and Docker Compose.
- FoxDesk app container.
- MariaDB container.
- Caddy reverse proxy container.
- App code deployment.
- Cron/background jobs.
- Local health checks.

## Cloudflare Responsibilities

- DNS and proxied app hostname.
- TLS/SSL.
- WAF and DDoS protection.
- Turnstile for public forms and signup.
- R2 for attachment and backup storage.
- Email Routing for inbound email processing.
- Cloudflare Email Service integration for outbound notifications.
- Optional Workers for inbound email webhooks, lightweight API glue, and async helpers.

## Deployment Sequence

1. Provision Hetzner VPS with Ubuntu.
2. Point `app.foxdesk.net` and `platform.foxdesk.net` DNS A records to `46.224.66.79` in Cloudflare.
3. Keep the DNS record proxied.
4. Run `deploy/hetzner/bootstrap.sh` on the server.
5. Clone this repository into `/opt/foxdesk_saas`.
6. Copy `.env.production.example` to `.env.production`.
7. Fill production secrets in `.env.production`.
8. Copy `config.production.example.php` to `config.php`.
9. Run `npm ci && npx playwright install --with-deps chromium`.
10. Run `deploy/hetzner/preflight.sh`.
11. Run `deploy/hetzner/deploy.sh`.
12. Visit `https://app.foxdesk.net/install.php` for first install or run existing migration flow.
13. Use `https://platform.foxdesk.net/index.php?page=platform` only for the operator console.
14. Enable Cloudflare WAF/rate limiting rules.
15. Configure backups and restore test.
16. Run `npm run prod:deploy:evidence` and store the archive outside the app server.
17. Add monitoring and uptime checks.

## Production Files

The prepared production files are:

- `.env.production.example`: secrets and environment template.
- `config.production.example.php`: PHP config template reading from environment.
- `docker-compose.prod.yml`: Caddy + PHP/Apache app + MariaDB + cron worker.
- `docker/prod/Dockerfile`: production PHP/Apache image.
- `docker/caddy/Caddyfile`: reverse proxy and security headers for `app.foxdesk.net`.
- `deploy/hetzner/bootstrap.sh`: base Docker/firewall setup for a fresh Ubuntu server.
- `deploy/hetzner/deploy.sh`: build, deploy, health-check, and deployment evidence gate.
- `deploy/hetzner/backup-db.sh`: local DB backup with short retention.
- `bin/deployment-evidence.js`: production smoke plus restore evidence archive.

Do not commit `.env.production` or `config.php`.

## First Production Commands

On the server:

```bash
sudo mkdir -p /opt
cd /opt
sudo git clone https://github.com/lukashanes/foxdesk_saas.git
cd foxdesk_saas
sudo cp .env.production.example .env.production
sudo cp config.production.example.php config.php
npm ci
sudo npx playwright install --with-deps chromium
sudo nano .env.production
sudo deploy/hetzner/preflight.sh
sudo deploy/hetzner/deploy.sh
```

Database backup:

```bash
deploy/hetzner/backup-db.sh
```

Restore evidence and deployment archive:

```bash
sudo mkdir -p /var/lib/foxdesk/evidence/deployments
sudo cp docs/operations/backup-restore-evidence.template.json /var/lib/foxdesk/evidence/restore-latest.json
sudo nano /var/lib/foxdesk/evidence/restore-latest.json
npm run prod:deploy:evidence
```

The deploy is not complete until `deployment-evidence.json`,
`deployment-evidence.md`, `foxdesk-deploy-evidence-*.tar.gz`, and the matching
`.sha256` file exist in `FOXDESK_DEPLOY_EVIDENCE_DIR`.

Suggested root crontab:

```cron
*/15 * * * * cd /opt/foxdesk_saas && docker compose --env-file .env.production -f docker-compose.prod.yml exec -T app php bin/run-maintenance.php --json >> /var/log/foxdesk-maintenance.log 2>&1
15 2 * * * cd /opt/foxdesk_saas/deploy/hetzner && ./backup-db.sh >> /var/log/foxdesk-backup.log 2>&1
```

The Docker cron worker already runs `php bin/run-maintenance.php` every five minutes. The crontab maintenance line is a fallback if you intentionally disable the `cron` service.

## Cloudflare Services Mapping

- Workers: edge glue, webhooks, email processing endpoints.
- R2: attachments, exports, backups.
- D1: not used initially; possible future rewrite target.
- KV: cache/feature flags, not primary app data.
- Queues: future background jobs and retry pipelines.
- Workflows: future multi-step tenant provisioning and report generation.
- Durable Objects: future real-time coordination or locking.
- Hyperdrive: useful only if Workers need to talk to an external database.

## Do Not Do First

- Do not rewrite the full PHP app to Workers before the hosted PHP version is stable.
- Do not split every service prematurely.
- Do not make tenant isolation optional.
- Do not store customer attachments only on local disk for a SaaS launch.
