# Self-hosted FoxDesk to FoxDesk Cloud migration

This migration path moves an existing self-hosted FoxDesk into a new SaaS tenant workspace.

## Source self-hosted app

1. Update the self-hosted FoxDesk to a version that contains the Cloud migration page.
2. Log in as an admin.
3. Open:

   `index.php?page=admin&section=migration-export`

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

3. Use **Import self-hosted FoxDesk**.
4. Upload the ZIP package.
5. Choose the new workspace name and billing state.
6. Verify the imported workspace in **Customer FoxDesks**.

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
4. Switch DNS/domain routing only after validation.
5. Keep the self-hosted app read-only during final cutover.
