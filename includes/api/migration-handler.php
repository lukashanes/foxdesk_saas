<?php
/**
 * API Handler: Self-hosted to SaaS migration bridge.
 */

function api_migration_connect(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_error('Method not allowed', 405);
    }

    $connection = migration_bridge_authenticate();
    $input = get_json_input();
    $inventory = is_array($input['inventory'] ?? null) ? $input['inventory'] : [];

    $plan = [];
    if ($inventory) {
        $plan = migration_bridge_build_plan($connection, $inventory);
        migration_bridge_record_inventory($connection, $inventory, $plan, 'connected');
    } else {
        db_update('migration_connections', [
            'status' => 'connected',
            'last_seen_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [(int) $connection['id']]);
    }

    api_success([
        'connection' => [
            'id' => (int) $connection['id'],
            'status' => 'connected',
            'tenant_id' => (int) $connection['tenant_id'],
            'tenant_name' => (string) ($connection['tenant_name'] ?? ''),
            'tenant_slug' => (string) ($connection['tenant_slug'] ?? ''),
        ],
        'mode' => 'self_hosted_to_saas_then_cutover',
        'plan' => $plan,
        'endpoints' => [
            'plan' => 'migration-plan',
            'status' => 'migration-status',
        ],
    ]);
}

function api_migration_plan(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_error('Method not allowed', 405);
    }

    $connection = migration_bridge_authenticate();
    $input = get_json_input();
    $inventory = is_array($input['inventory'] ?? null) ? $input['inventory'] : [];
    if (!$inventory) {
        api_error('Migration inventory is required.', 422);
    }

    try {
        $plan = migration_bridge_build_plan($connection, $inventory);
        migration_bridge_record_inventory($connection, $inventory, $plan, 'connected');
    } catch (Throwable $e) {
        api_error($e->getMessage(), 422);
    }

    api_success([
        'plan' => $plan,
    ]);
}

function api_migration_status(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_error('Method not allowed', 405);
    }

    $connection = migration_bridge_authenticate();
    db_update('migration_connections', [
        'last_seen_at' => date('Y-m-d H:i:s'),
    ], 'id = ?', [(int) $connection['id']]);

    api_success([
        'connection' => [
            'id' => (int) $connection['id'],
            'tenant_id' => (int) $connection['tenant_id'],
            'status' => (string) $connection['status'],
            'source_instance_id' => (string) ($connection['source_instance_id'] ?? ''),
            'source_url' => (string) ($connection['source_url'] ?? ''),
            'source_version' => (string) ($connection['source_version'] ?? ''),
            'expires_at' => (string) ($connection['expires_at'] ?? ''),
            'last_seen_at' => (string) ($connection['last_seen_at'] ?? ''),
        ],
    ]);
}

function api_migration_push_table(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_error('Method not allowed', 405);
    }

    $connection = migration_bridge_authenticate();
    $input = get_json_input();
    $table = trim((string) ($input['table'] ?? ''));
    $rows = is_array($input['rows'] ?? null) ? $input['rows'] : [];
    $checksum = trim((string) ($input['checksum'] ?? ''));

    if ($table === '' || !$rows) {
        api_error('Table and rows are required.', 422);
    }

    $calculated = hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    if ($checksum !== '' && !hash_equals($calculated, $checksum)) {
        api_error('Chunk checksum mismatch.', 422);
    }

    try {
        $summary = migration_bridge_import_table_chunk($connection, $table, $rows);
        db_update('migration_connections', [
            'status' => 'syncing',
            'last_seen_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [(int) $connection['id']]);
    } catch (Throwable $e) {
        api_error($e->getMessage(), 422);
    }

    api_success([
        'table' => $table,
        'checksum' => $calculated,
        'summary' => $summary,
    ]);
}

function api_migration_push_attachment(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_error('Method not allowed', 405);
    }

    $connection = migration_bridge_authenticate();
    $metadata = json_decode((string) ($_POST['metadata'] ?? ''), true);
    if (!is_array($metadata)) {
        api_error('Attachment metadata is required.', 422);
    }

    $file = $_FILES['file'] ?? null;
    if (!$file || !is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        api_error('Attachment file is required.', 422);
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_file($tmp)) {
        api_error('Uploaded attachment file is missing.', 422);
    }

    $checksum = trim((string) ($_POST['checksum'] ?? ''));
    $calculated = hash_file('sha256', $tmp);
    if ($checksum !== '' && !hash_equals($calculated, $checksum)) {
        api_error('Attachment checksum mismatch.', 422);
    }

    try {
        $result = migration_bridge_import_attachment_upload($connection, $metadata, $tmp);
        $evidence = migration_bridge_record_attachment_sync_evidence(
            $connection,
            $metadata,
            $result,
            $calculated ?: $checksum,
            (int) filesize($tmp)
        );
        db_update('migration_connections', [
            'status' => 'syncing',
            'last_seen_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [(int) $connection['id']]);
    } catch (Throwable $e) {
        api_error($e->getMessage(), 422);
    }

    api_success([
        'source_id' => (int) ($metadata['id'] ?? $metadata['source_id'] ?? 0),
        'checksum' => $calculated,
        'attachment' => $result,
        'attachment_sync' => $evidence,
        'mapped' => !empty($result['mapped']),
    ]);
}
