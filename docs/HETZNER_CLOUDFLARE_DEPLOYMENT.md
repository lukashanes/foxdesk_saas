# Hetzner + Cloudflare Deployment Notes

This is the recommended first deployment path for FoxDesk SaaS work. It keeps the current PHP application intact and uses Cloudflare for edge, security, storage, and email services.

## Target Hostnames

- `foxdesk.org` stays the public open-source/self-hosted website.
- `app.foxdesk.net` is the production SaaS application.
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
2. Point `app.foxdesk.net` DNS A record to `46.224.66.79` in Cloudflare.
3. Keep the DNS record proxied.
4. Run `deploy/hetzner/bootstrap.sh` on the server.
5. Clone this repository into `/opt/foxdesk_saas`.
6. Copy `.env.production.example` to `.env.production`.
7. Fill production secrets in `.env.production`.
8. Copy `config.production.example.php` to `config.php`.
9. Run `deploy/hetzner/deploy.sh`.
10. Visit `https://app.foxdesk.net/install.php` for first install or run existing migration flow.
11. Enable Cloudflare WAF/rate limiting rules.
12. Configure backups and restore test.
13. Add monitoring and uptime checks.

## Production Files

The prepared production files are:

- `.env.production.example`: secrets and environment template.
- `config.production.example.php`: PHP config template reading from environment.
- `docker-compose.prod.yml`: Caddy + PHP/Apache app + MariaDB + cron worker.
- `docker/prod/Dockerfile`: production PHP/Apache image.
- `docker/caddy/Caddyfile`: reverse proxy and security headers for `app.foxdesk.net`.
- `deploy/hetzner/bootstrap.sh`: base Docker/firewall setup for a fresh Ubuntu server.
- `deploy/hetzner/deploy.sh`: build, deploy, and health-check.
- `deploy/hetzner/backup-db.sh`: local DB backup with short retention.

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
sudo nano .env.production
sudo deploy/hetzner/deploy.sh
```

Database backup:

```bash
deploy/hetzner/backup-db.sh
```

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
