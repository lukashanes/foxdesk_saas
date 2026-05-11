# Hetzner + Cloudflare Deployment Notes

This is the recommended first deployment path for FoxDesk SaaS work. It keeps the current PHP application intact and uses Cloudflare for edge, security, storage, and email services.

## Hetzner Responsibilities

- Ubuntu VPS.
- PHP 8.2+ with required extensions.
- MariaDB.
- Nginx or Apache.
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
- Email Sending or SMTP integration for outbound notifications.
- Optional Workers for inbound email webhooks, lightweight API glue, and async helpers.

## Deployment Sequence

1. Provision Hetzner VPS.
2. Install PHP, MariaDB, web server, Composer/system packages if needed.
3. Deploy FoxDesk code.
4. Configure database and app config.
5. Run installer or migration.
6. Put domain on Cloudflare DNS.
7. Point proxied DNS record to Hetzner.
8. Enable SSL full/strict.
9. Add WAF/rate limiting rules.
10. Configure backups.
11. Add monitoring and uptime checks.

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

