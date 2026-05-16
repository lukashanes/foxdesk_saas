<?php
/**
 * Self-hosted to SaaS migration helpers.
 */

function migration_reference_tables(): array
{
    return ['statuses', 'priorities', 'ticket_types'];
}

function migration_tenant_tables(): array
{
    return [
        'organizations',
        'users',
        'tickets',
        'comments',
        'ticket_time_entries',
        'attachments',
        'ticket_shares',
        'report_shares',
        'ticket_access',
        'activity_log',
        'api_tokens',
        'notifications',
        'allowed_senders',
        'recurring_tasks',
        'report_templates',
        'report_snapshots',
        'ticket_messages',
        'ticket_message_attachments',
        'email_ingest_logs',
        'security_log',
        'debug_log',
        'page_views',
    ];
}

function migration_export_tables(): array
{
    return array_merge(migration_reference_tables(), migration_tenant_tables(), ['settings', 'email_templates']);
}

function migration_ensure_imports_table(): void
{
    db_query("
        CREATE TABLE IF NOT EXISTS migration_imports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NULL,
            source_url VARCHAR(255) NULL,
            source_version VARCHAR(40) NULL,
            package_hash CHAR(64) NOT NULL,
            status ENUM('imported', 'failed') NOT NULL DEFAULT 'imported',
            summary_json JSON NULL,
            error_message TEXT NULL,
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tenant_id (tenant_id),
            INDEX idx_package_hash (package_hash),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function migration_table_columns(string $table): array
{
    validate_sql_identifier($table);
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }

    if (!table_exists($table)) {
        return $cache[$table] = [];
    }

    $rows = db_fetch_all("SHOW COLUMNS FROM {$table}");
    $columns = [];
    foreach ($rows as $row) {
        $columns[] = $row['Field'];
    }
    return $cache[$table] = $columns;
}

function migration_select_rows(string $table, int $tenant_id): array
{
    validate_sql_identifier($table);
    if (!table_exists($table)) {
        return [];
    }

    if (in_array($table, migration_reference_tables(), true) || in_array($table, ['settings', 'email_templates'], true)) {
        return db_fetch_all("SELECT * FROM {$table} ORDER BY id ASC");
    }

    if (column_exists($table, 'tenant_id')) {
        return db_fetch_all("SELECT * FROM {$table} WHERE tenant_id = ? ORDER BY id ASC", [$tenant_id]);
    }

    return db_fetch_all("SELECT * FROM {$table} ORDER BY id ASC");
}

function migration_attachment_absolute_path(array $attachment): ?string
{
    if (($attachment['storage_driver'] ?? 'local') === 'r2' && function_exists('storage_read_object')) {
        return null;
    }

    $filename = basename((string) ($attachment['filename'] ?? ''));
    if ($filename === '') {
        return null;
    }

    $upload_dir = trim((defined('UPLOAD_DIR') ? UPLOAD_DIR : 'uploads/'), '/\\');
    $path = BASE_PATH . '/' . $upload_dir . '/' . $filename;
    return is_file($path) ? $path : null;
}

function migration_create_export_package(?int $tenant_id = null): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('PHP ZipArchive extension is required to create migration packages.');
    }

    ensure_tenant_baseline();
    $tenant_id = $tenant_id ?: current_tenant_id();
    $tenant = table_exists('tenants')
        ? db_fetch_one("SELECT * FROM tenants WHERE id = ? LIMIT 1", [$tenant_id])
        : null;

    $export_id = date('Ymd-His') . '-' . bin2hex(random_bytes(4));
    $safe_slug = preg_replace('/[^a-z0-9_-]+/i', '-', (string) ($tenant['slug'] ?? 'self-hosted'));
    $filename = 'foxdesk-cloud-migration-' . trim($safe_slug, '-') . '-' . $export_id . '.zip';
    $dir = BASE_PATH . '/backups';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $path = $dir . '/' . $filename;

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Unable to create migration package.');
    }

    $manifest = [
        'format' => 'foxdesk-cloud-migration',
        'format_version' => 1,
        'created_at' => gmdate('c'),
        'app_version' => defined('APP_VERSION') ? APP_VERSION : null,
        'source' => [
            'base_url' => defined('APP_URL') ? APP_URL : '',
            'tenant_id' => $tenant_id,
            'tenant_slug' => $tenant['slug'] ?? 'default',
            'tenant_name' => $tenant['name'] ?? get_setting('app_name', 'FoxDesk'),
        ],
        'tables' => [],
        'files' => [],
    ];

    foreach (migration_export_tables() as $table) {
        if (!table_exists($table)) {
            continue;
        }
        $rows = migration_select_rows($table, $tenant_id);
        $manifest['tables'][$table] = count($rows);
        $zip->addFromString('tables/' . $table . '.json', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    if (table_exists('attachments')) {
        $attachments = migration_select_rows('attachments', $tenant_id);
        foreach ($attachments as $attachment) {
            $attachment_id = (int) ($attachment['id'] ?? 0);
            if ($attachment_id <= 0) {
                continue;
            }

            $package_path = 'files/attachments/' . $attachment_id . '/' . basename((string) ($attachment['filename'] ?? 'file.bin'));
            $absolute_path = migration_attachment_absolute_path($attachment);
            if ($absolute_path !== null) {
                $zip->addFile($absolute_path, $package_path);
            } elseif (($attachment['storage_driver'] ?? '') === 'r2' && function_exists('storage_read_object')) {
                $body = storage_read_object($attachment);
                if ($body !== null) {
                    $zip->addFromString($package_path, $body);
                }
            } else {
                continue;
            }

            $manifest['files']['attachments'][(string) $attachment_id] = [
                'package_path' => $package_path,
                'filename' => $attachment['filename'] ?? '',
                'original_name' => $attachment['original_name'] ?? '',
                'file_size' => (int) ($attachment['file_size'] ?? 0),
            ];
        }
    }

    $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $zip->close();

    return [
        'path' => $path,
        'filename' => $filename,
        'bytes' => filesize($path) ?: 0,
        'manifest' => $manifest,
        'sha256' => hash_file('sha256', $path),
    ];
}

function migration_download_export_package(): void
{
    require_admin();
    require_csrf_token();

    $package = migration_create_export_package(current_tenant_id());
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $package['filename'] . '"');
    header('Content-Length: ' . (string) $package['bytes']);
    header('X-FoxDesk-Migration-SHA256: ' . $package['sha256']);
    readfile($package['path']);
    @unlink($package['path']);
    exit;
}

function migration_extract_package(string $zip_path): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('PHP ZipArchive extension is required to import migration packages.');
    }

    $hash = hash_file('sha256', $zip_path);
    $extract_dir = sys_get_temp_dir() . '/foxdesk-migration-' . bin2hex(random_bytes(8));
    if (!mkdir($extract_dir, 0700, true) && !is_dir($extract_dir)) {
        throw new RuntimeException('Unable to create migration import directory.');
    }

    $zip = new ZipArchive();
    if ($zip->open($zip_path) !== true) {
        throw new RuntimeException('Unable to open migration package.');
    }
    $zip->extractTo($extract_dir);
    $zip->close();

    $manifest_path = $extract_dir . '/manifest.json';
    if (!is_file($manifest_path)) {
        throw new RuntimeException('Migration package is missing manifest.json.');
    }

    $manifest = json_decode((string) file_get_contents($manifest_path), true);
    if (!is_array($manifest) || ($manifest['format'] ?? '') !== 'foxdesk-cloud-migration') {
        throw new RuntimeException('Invalid FoxDesk migration package.');
    }

    return [$extract_dir, $manifest, $hash];
}

function migration_remove_dir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = scandir($dir);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            migration_remove_dir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

function migration_load_table_rows(string $extract_dir, string $table): array
{
    validate_sql_identifier($table);
    $path = $extract_dir . '/tables/' . $table . '.json';
    if (!is_file($path)) {
        return [];
    }
    $rows = json_decode((string) file_get_contents($path), true);
    return is_array($rows) ? $rows : [];
}

function migration_import_reference_table(string $extract_dir, string $table, array &$id_maps): int
{
    $rows = migration_load_table_rows($extract_dir, $table);
    if (!$rows || !table_exists($table)) {
        return 0;
    }

    $columns = migration_table_columns($table);
    $count = 0;
    foreach ($rows as $row) {
        $old_id = (int) ($row['id'] ?? 0);
        $slug = (string) ($row['slug'] ?? '');
        $existing = $slug !== '' && column_exists($table, 'slug')
            ? db_fetch_one("SELECT id FROM {$table} WHERE slug = ? LIMIT 1", [$slug])
            : null;

        if (!$existing && !empty($row['name'])) {
            $existing = db_fetch_one("SELECT id FROM {$table} WHERE name = ? LIMIT 1", [$row['name']]);
        }

        if ($existing) {
            $id_maps[$table][$old_id] = (int) $existing['id'];
            continue;
        }

        $data = [];
        foreach ($row as $key => $value) {
            if ($key === 'id' || !in_array($key, $columns, true)) {
                continue;
            }
            $data[$key] = $value;
        }
        if (!$data) {
            continue;
        }
        $id_maps[$table][$old_id] = (int) db_insert($table, $data);
        $count++;
    }

    return $count;
}

function migration_map_value(array $id_maps, string $table, $value)
{
    if ($value === null || $value === '') {
        return $value;
    }
    $old = (int) $value;
    return $id_maps[$table][$old] ?? $value;
}

function migration_remap_row(string $table, array $row, int $tenant_id, array $id_maps): array
{
    if (array_key_exists('tenant_id', $row)) {
        $row['tenant_id'] = $tenant_id;
    }

    $map = [
        'organization_id' => 'organizations',
        'user_id' => 'users',
        'uploaded_by' => 'users',
        'created_by' => 'users',
        'created_by_user_id' => 'users',
        'generated_by_user_id' => 'users',
        'actor_id' => 'users',
        'assignee_id' => 'users',
        'ticket_id' => 'tickets',
        'comment_id' => 'comments',
        'report_template_id' => 'report_templates',
        'ticket_message_id' => 'ticket_messages',
        'attachment_id' => 'attachments',
        'status_id' => 'statuses',
        'priority_id' => 'priorities',
        'ticket_type_id' => 'ticket_types',
    ];

    foreach ($map as $column => $target_table) {
        if (array_key_exists($column, $row)) {
            $row[$column] = migration_map_value($id_maps, $target_table, $row[$column]);
        }
    }

    if ($table === 'users') {
        $row['is_platform_admin'] = 0;
        if (!empty($row['reset_token'])) {
            $row['reset_token'] = null;
            $row['reset_token_expires'] = null;
        }
    }

    if ($table === 'tickets' && !empty($row['hash']) && db_fetch_one("SELECT id FROM tickets WHERE hash = ? LIMIT 1", [$row['hash']])) {
        $row['hash'] = substr(bin2hex(random_bytes(8)), 0, 16);
    }

    if ($table === 'api_tokens') {
        $row['is_active'] = 0;
        $row['last_used_at'] = null;
    }

    return $row;
}

function migration_store_imported_attachment(array $row, array $file_manifest, string $extract_dir, int $tenant_id): array
{
    $old_id = (string) ((int) ($row['id'] ?? 0));
    $file = $file_manifest[$old_id] ?? null;
    if (!$file || empty($file['package_path'])) {
        return $row;
    }

    $source = $extract_dir . '/' . ltrim(str_replace('\\', '/', (string) $file['package_path']), '/');
    if (!is_file($source)) {
        return $row;
    }

    $extension = pathinfo((string) ($row['filename'] ?? ''), PATHINFO_EXTENSION);
    $safe_extension = $extension !== '' ? ('.' . preg_replace('/[^a-z0-9]+/i', '', $extension)) : '';
    $new_filename = 'migrated_' . $tenant_id . '_' . bin2hex(random_bytes(8)) . $safe_extension;
    $relative_path = 'uploads/' . $new_filename;
    $target = BASE_PATH . '/' . $relative_path;
    if (!is_dir(dirname($target))) {
        @mkdir(dirname($target), 0755, true);
    }
    if (!copy($source, $target)) {
        throw new RuntimeException('Unable to stage imported attachment.');
    }

    $storage = storage_store_file($target, $relative_path, (string) ($row['mime_type'] ?? ''), $tenant_id);
    $row['filename'] = $new_filename;
    $row['storage_driver'] = $storage['driver'] ?? 'local';
    $row['storage_bucket'] = $storage['bucket'] ?? '';
    $row['storage_key'] = $storage['key'] ?? $relative_path;

    return $row;
}

function migration_import_table(string $extract_dir, string $table, int $tenant_id, array &$id_maps, array $manifest): int
{
    if (!table_exists($table)) {
        return 0;
    }

    $rows = migration_load_table_rows($extract_dir, $table);
    if (!$rows) {
        return 0;
    }

    $columns = migration_table_columns($table);
    $count = 0;
    $file_manifest = $manifest['files']['attachments'] ?? [];

    foreach ($rows as $row) {
        $old_id = (int) ($row['id'] ?? 0);
        $row = migration_remap_row($table, $row, $tenant_id, $id_maps);
        if ($table === 'attachments') {
            $row = migration_store_imported_attachment($row, is_array($file_manifest) ? $file_manifest : [], $extract_dir, $tenant_id);
        }
        if ($table === 'ticket_message_attachments' && !empty($row['attachment_id'])) {
            $new_attachment = db_fetch_one("SELECT storage_key FROM attachments WHERE id = ? LIMIT 1", [(int) $row['attachment_id']]);
            if ($new_attachment && !empty($new_attachment['storage_key'])) {
                $row['storage_path'] = $new_attachment['storage_key'];
            }
        }

        $data = [];
        foreach ($row as $key => $value) {
            if ($key === 'id' || !in_array($key, $columns, true)) {
                continue;
            }
            $data[$key] = $value;
        }
        if (!$data) {
            continue;
        }

        $id_maps[$table][$old_id] = (int) db_insert($table, $data);
        $count++;
    }

    return $count;
}

function migration_import_package(string $zip_path, array $options = []): array
{
    ensure_tenant_baseline();
    migration_ensure_imports_table();

    [$extract_dir, $manifest, $hash] = migration_extract_package($zip_path);
    $db = get_db();
    $id_maps = [];
    $summary = ['tables' => [], 'tenant_id' => null, 'package_hash' => $hash];

    try {
        $db->beginTransaction();

        foreach (migration_reference_tables() as $reference_table) {
            $summary['tables'][$reference_table] = migration_import_reference_table($extract_dir, $reference_table, $id_maps);
        }

        $source = $manifest['source'] ?? [];
        $workspace_name = trim((string) ($options['workspace_name'] ?? $source['tenant_name'] ?? 'Migrated FoxDesk'));
        $slug = tenant_unique_slug($workspace_name);
        $tenant_id = (int) db_insert('tenants', [
            'uuid' => tenant_generate_uuid(),
            'name' => $workspace_name,
            'slug' => $slug,
            'status' => $options['status'] ?? 'trialing',
            'plan' => billing_plan_code(),
            'billing_email' => $options['billing_email'] ?? null,
            'subscription_status' => $options['subscription_status'] ?? 'manual',
            'max_users' => 1000000,
            'max_agents' => 1000000,
            'trial_ends_at' => $options['trial_ends_at'] ?? date('Y-m-d H:i:s', strtotime('+14 days')),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $summary['tenant_id'] = $tenant_id;
        $summary['tenant_slug'] = $slug;

        foreach (migration_tenant_tables() as $table) {
            $summary['tables'][$table] = migration_import_table($extract_dir, $table, $tenant_id, $id_maps, $manifest);
        }

        $owner = db_fetch_one("SELECT id, email FROM users WHERE tenant_id = ? AND role = 'admin' AND is_active = 1 ORDER BY id ASC LIMIT 1", [$tenant_id]);
        if (!$owner) {
            throw new RuntimeException('Migration package did not contain an active admin user.');
        }
        db_update('tenants', [
            'owner_user_id' => (int) $owner['id'],
            'billing_email' => $options['billing_email'] ?? $owner['email'],
        ], 'id = ?', [$tenant_id]);

        db_insert('migration_imports', [
            'tenant_id' => $tenant_id,
            'source_url' => $source['base_url'] ?? '',
            'source_version' => $manifest['app_version'] ?? '',
            'package_hash' => $hash,
            'status' => 'imported',
            'summary_json' => json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_by' => $_SESSION['user_id'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $db->commit();
        return $summary;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        try {
            db_insert('migration_imports', [
                'tenant_id' => null,
                'source_url' => $manifest['source']['base_url'] ?? '',
                'source_version' => $manifest['app_version'] ?? '',
                'package_hash' => $hash,
                'status' => 'failed',
                'summary_json' => json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'error_message' => $e->getMessage(),
                'created_by' => $_SESSION['user_id'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $ignored) {
        }
        throw $e;
    } finally {
        migration_remove_dir($extract_dir);
    }
}
