# Cloudflare R2 Storage

FoxDesk supports local disk storage and Cloudflare R2 for ticket attachments.

## Recommended Production Setup

- Keep R2 bucket private.
- Do not expose a public bucket URL.
- Let FoxDesk serve attachments through `attachment.php`, which checks ticket/share permissions before reading from R2.
- Store objects with tenant-prefixed keys:

```text
tenants/{tenant_id}/uploads/{file}
tenants/{tenant_id}/storage/tickets/{ticket_id}/{message_id}/{file}
```

## Cloudflare Values Needed

Create these in Cloudflare Dashboard:

1. Go to **R2 Object Storage**.
2. Create bucket:

```text
foxdesk-production
```

3. Go to **Manage R2 API Tokens**.
4. Create token with object read/write access for the bucket.
5. Copy:
   - Access Key ID
   - Secret Access Key
   - S3 API endpoint

Cloudflare R2 S3 endpoint format:

```text
https://<account_id>.r2.cloudflarestorage.com
```

Cloudflare docs: R2 uses an S3-compatible API at `https://<ACCOUNT_ID>.r2.cloudflarestorage.com` and requires an Access Key ID and Secret Access Key for S3 clients.

## FoxDesk Config

Set in `.env.production`:

```env
STORAGE_DRIVER=r2
R2_BUCKET=foxdesk-production
R2_ENDPOINT=https://<account_id>.r2.cloudflarestorage.com
R2_ACCESS_KEY_ID=...
R2_SECRET_ACCESS_KEY=...
```

Local development should normally stay on:

```env
STORAGE_DRIVER=local
```

## Current Behavior

- New private ticket uploads are written to R2 when `STORAGE_DRIVER=r2`.
- Public assets such as logos and avatars stay on local disk so public image serving remains simple and cacheable.
- New IMAP inbound email attachments are written to R2 when `STORAGE_DRIVER=r2`.
- Cloudflare Email Routing archives raw emails and inbound attachments in the Worker R2 archive bucket; the app stores metadata for those archived inbound attachments.
- Attachment metadata stores `storage_driver`, `storage_bucket`, and `storage_key`.
- Existing local attachments continue to work.
- Attachment downloads still go through FoxDesk authorization.
- Image previews still go through FoxDesk authorization and support R2-backed protected image attachments.

## Production Check

Before deploy, Hetzner preflight must pass with:

- `STORAGE_DRIVER=r2`
- `R2_BUCKET`
- `R2_ENDPOINT`
- `R2_ACCESS_KEY_ID`
- `R2_SECRET_ACCESS_KEY`

`deploy/hetzner/preflight.sh` fails production setup when SaaS storage is not
R2 or when the R2 endpoint is not the Cloudflare S3 endpoint shape.

After deploy, run the R2 write/read/delete roundtrip smoke test:

```bash
php bin/test-r2-storage.php --tenant-id=<test_tenant_id> --json
```

The reported `object_key` must start with:

```text
tenants/<test_tenant_id>/
```

Then test the app workflow:

1. Upload a small image attachment in a test workspace.
2. Confirm the object exists in R2 under the `tenants/` prefix.
3. Open the ticket detail and verify image preview works.
4. Download the same attachment through `attachment.php`.
5. Delete the test attachment/ticket and confirm the R2 object can be deleted by the storage client.

The public health endpoint reports R2 configuration status under
`checks.storage_r2`. A write/read/delete health mutation is intentionally
disabled by default; enable it only for a controlled monitor or manual check:

```env
FOXDESK_HEALTH_STORAGE_MUTATION=true
```

## Migration Attachment Evidence

Self-hosted to SaaS sync uploads attachments through the migration bridge.
Successful unique attachment imports update the migration connection with:

- synced attachment count
- synced attachment bytes
- last attachment sync time
- last attachment storage key
- last attachment checksum

The platform tenant detail shows this evidence in the migration bridge panel so
cutover review can confirm attachments were included before switching traffic.

## Attachment Backup Outside The Server

R2 is the production off-server attachment store when `STORAGE_DRIVER=r2`. For migrated or legacy local attachments, keep a second backup process outside the app server:

```text
backups/attachments/{YYYY-MM-DD}/tenants/{tenant_id}/...
```

Use a separate R2 API token for backup jobs. It should have write access to the backup prefix/bucket and should not be reused by the web application.

Backup legacy local attachments:

```bash
php bin/backup-attachments-to-r2.php --dry-run --json
php bin/backup-attachments-to-r2.php --tenant-id=<tenant_id> --limit=500 --json
```

This copies local/non-R2 attachment files to R2 and leaves the application metadata unchanged.
