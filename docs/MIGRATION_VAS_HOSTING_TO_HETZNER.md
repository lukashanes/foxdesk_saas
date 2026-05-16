# Migration from Vas-Hosting to the new FoxDesk SaaS server

This is the recommended path for moving an existing FoxDesk installation from
Vas-Hosting, PSP/shared hosting, or another PHP host to the newer Docker-based
server stack.

## Target Stack

- Hetzner server for the PHP app, database, and reverse proxy
- Docker Compose production stack from this repository
- MariaDB for FoxDesk data
- Cloudflare DNS and proxy
- Cloudflare Email Sending for outbound app mail
- Cloudflare R2 for attachments and long-term file storage

## Recommended Variant: Backup, Restore, Test, Switch DNS

This is the safest option because the old host stays available as rollback until
the new server is verified.

### 1. Freeze risky changes

- Pick a short maintenance window.
- Lower DNS TTL for the app domain to 300 seconds at least several hours before
  the migration.
- Avoid creating new tickets or uploading attachments during final export.

### 2. Export the current FoxDesk

From the old hosting, collect:

- Full database dump.
- All uploaded files, usually `uploads/` and any configured storage folder.
- Current `config.php` without sharing secrets publicly.
- Current FoxDesk version.
- Cron configuration.
- Mail/SMTP/IMAP settings.
- Any custom logos, favicon, theme settings, or local edits.

Example DB export on a host with shell access:

```bash
mysqldump --single-transaction --default-character-set=utf8mb4 \
  -u DB_USER -p DB_NAME > foxdesk-backup.sql
```

Example file archive:

```bash
tar -czf foxdesk-files.tar.gz uploads storage config.php
```

If the old host has no shell, use phpMyAdmin for DB export and SFTP/File Manager
for files.

### 3. Restore on the new server

On the new Hetzner server:

- Deploy the production Docker stack.
- Create the production `.env` and `config.php`.
- Import the database into MariaDB.
- Copy uploads/storage files into the expected volume/path.
- Configure Cloudflare Email Sending.
- Configure R2 attachment storage.
- Run any required FoxDesk upgrade/migration scripts.

Example DB import:

```bash
docker compose -f docker-compose.prod.yml exec -T db \
  mysql -u foxdesk -p foxdesk < foxdesk-backup.sql
```

### 4. Test before DNS switch

Verify on a temporary hostname or direct server mapping:

- Login as admin, agent, and client.
- Dashboard loads.
- Ticket list and ticket detail load.
- Create a test ticket.
- Upload and download an attachment.
- Outbound email works.
- Inbound email/IMAP works if used.
- Cron jobs run.
- Health endpoint returns OK.
- Admin permissions and platform admin access are correct.
- Public report/ticket share links work if used.

### 5. Switch production traffic

After tests pass:

- Point the app domain DNS record to the new server.
- Keep Cloudflare proxy enabled if the server is configured for it.
- Watch app logs, reverse proxy logs, and MariaDB logs.
- Keep the old host untouched for rollback during the first days.

### 6. Rollback plan

If something breaks:

- Point DNS back to the old hosting.
- Disable writes on the new server to avoid split-brain data.
- Compare what changed during the test window before retrying.

## Notes

- Do not move DNS before login, tickets, attachments, email, and cron are tested.
- Do not paste production secrets into GitHub or chat.
- For SaaS mode, each future customer should become a tenant/workspace instead
  of a separate unmanaged installation.
