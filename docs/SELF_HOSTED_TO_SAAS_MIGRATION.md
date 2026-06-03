# Self-hosted FoxDesk to FoxDesk Cloud migration

This migration path moves an existing self-hosted FoxDesk into one SaaS tenant
workspace. The preferred production path is API sync followed by final cutover:
only one instance remains active after the switch.

## Preferred API sync

1. Log in as a platform admin.
2. Open the target workspace in:

   `index.php?page=platform`

3. In **Migration bridge**, create a migration token.
4. Copy the token immediately. It is shown once and stored only as a hash.
5. In the self-hosted FoxDesk admin, open:

   `index.php?page=admin&section=migration-export`

6. Enter the SaaS URL and migration token.
7. Run **Connect** and **Analyze sync**.
8. Run data and attachment sync from the self-hosted server:

   `php bin/sync-to-cloud.php --cloud-url=https://app.foxdesk.net --token=fdmig_...`

9. Verify users, tickets, time entries, comments, email messages, and attachments
   in the SaaS workspace.
10. Trigger **Final cutover** in the self-hosted app.

After cutover the self-hosted app redirects to SaaS and disables local
IMAP/email notification processing. SaaS becomes the single active source.

Sync includes attachments through a streaming API upload. API tokens and global
email/settings secrets are not activated during migration; API tokens are rotated
and email credentials are re-entered in SaaS.

## Fallback ZIP package

Use ZIP import only when API sync is not available.

1. Update the self-hosted FoxDesk to public version `0.3.129` or newer.
2. Log in as an admin.
3. Open `index.php?page=admin&section=migration-export`.
4. Click **Download migration package**.

The package is a ZIP file containing:

- tenant/workspace data
- users and password hashes
- organizations/clients
- statuses, priorities, ticket types
- tickets, comments, time entries and reports
- notifications and activity metadata
- attachments
- package manifest with source version and checksum

API tokens are imported as inactive so customers can rotate them after migration.

## SaaS control plane

1. Log in as a platform admin.
2. Open:

   `index.php?page=platform`

3. Open the target workspace detail.
4. Use **Migration bridge** for API sync or ZIP import as fallback.
5. Verify the imported workspace in **Workspace catalog**.

## What the importer does

- Creates a new tenant.
- Imports source data into that tenant.
- Remaps primary keys and foreign keys.
- Preserves user password hashes.
- Preserves ticket history and report data.
- Stores attachments through the configured storage driver.
- Uses existing statuses/priorities/ticket types when matching slugs already exist.
- Records the import in `migration_imports`.

## After import

1. Verify users, tickets, clients, reports and attachments.
2. Ask the customer to rotate API tokens.
3. Configure inbound/outbound support email for the SaaS workspace.
4. Run final sync.
5. Trigger final cutover so the self-hosted app stops acting as an active helpdesk.
