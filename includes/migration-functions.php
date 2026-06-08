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
        'billing_usage_events',
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

function migration_table_column_meta(string $table, string $column): ?array
{
    validate_sql_identifier($table);
    validate_sql_identifier($column);

    if (!table_exists($table)) {
        return null;
    }

    static $cache = [];
    $key = $table . '.' . $column;
    if (!array_key_exists($key, $cache)) {
        $quoted_column = get_db()->quote($column);
        $row = db_fetch_one("SHOW COLUMNS FROM {$table} LIKE {$quoted_column}");
        $cache[$key] = $row ?: null;
    }

    return $cache[$key];
}

function migration_column_allows_null(string $table, string $column): bool
{
    $meta = migration_table_column_meta($table, $column);
    if (!$meta) {
        return true;
    }

    return strtoupper((string) ($meta['Null'] ?? 'YES')) === 'YES';
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

function migration_normalize_zip_entry_name(string $name): string
{
    $name = str_replace('\\', '/', $name);

    if (
        $name === ''
        || str_contains($name, "\0")
        || str_starts_with($name, '/')
        || preg_match('/^[A-Za-z]:\//', $name)
        || str_contains($name, '://')
    ) {
        throw new RuntimeException('Migration package contains an unsafe path.');
    }

    $parts = [];
    foreach (explode('/', $name) as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..') {
            throw new RuntimeException('Migration package contains a path traversal entry.');
        }
        $parts[] = $part;
    }

    $normalized = implode('/', $parts);
    if ($normalized === '') {
        throw new RuntimeException('Migration package contains an empty path.');
    }

    return $normalized;
}

function migration_zip_entry_is_symlink(ZipArchive $zip, int $index): bool
{
    if (!method_exists($zip, 'getExternalAttributesIndex')) {
        return false;
    }

    $opsys = 0;
    $attributes = 0;
    if (!$zip->getExternalAttributesIndex($index, $opsys, $attributes)) {
        return false;
    }

    if ($opsys !== ZipArchive::OPSYS_UNIX) {
        return false;
    }

    $mode = ($attributes >> 16) & 0170000;
    return $mode === 0120000;
}

function migration_safe_extract_target(string $extract_dir, string $entry_name): string
{
    $normalized = migration_normalize_zip_entry_name($entry_name);
    $target = $extract_dir . '/' . $normalized;
    $parent = dirname($target);

    if (!is_dir($parent) && !mkdir($parent, 0700, true) && !is_dir($parent)) {
        throw new RuntimeException('Unable to create migration import directory.');
    }

    $root = realpath($extract_dir);
    $real_parent = realpath($parent);
    if ($root === false || $real_parent === false || ($real_parent !== $root && !str_starts_with($real_parent, $root . DIRECTORY_SEPARATOR))) {
        throw new RuntimeException('Migration package contains an unsafe path.');
    }

    return $target;
}

function migration_extract_zip_safely(ZipArchive $zip, string $extract_dir): void
{
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        $name = is_array($stat) ? (string) ($stat['name'] ?? '') : '';
        if ($name === '') {
            throw new RuntimeException('Migration package contains an invalid entry.');
        }

        if (migration_zip_entry_is_symlink($zip, $i)) {
            throw new RuntimeException('Migration package contains an unsafe symlink entry.');
        }

        $normalized = migration_normalize_zip_entry_name($name);
        if (str_ends_with(str_replace('\\', '/', $name), '/')) {
            migration_safe_extract_target($extract_dir, $normalized . '/.foxdesk-dir-check');
            continue;
        }

        $target = migration_safe_extract_target($extract_dir, $normalized);
        $source = $zip->getStream($name);
        if ($source === false) {
            throw new RuntimeException('Unable to read migration package entry.');
        }

        $destination = fopen($target, 'wb');
        if ($destination === false) {
            fclose($source);
            throw new RuntimeException('Unable to extract migration package entry.');
        }

        stream_copy_to_stream($source, $destination);
        fclose($source);
        fclose($destination);
        @chmod($target, 0600);
    }
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
        migration_remove_dir($extract_dir);
        throw new RuntimeException('Unable to open migration package.');
    }

    try {
        migration_extract_zip_safely($zip, $extract_dir);
    } catch (Throwable $e) {
        $zip->close();
        migration_remove_dir($extract_dir);
        throw $e;
    }
    $zip->close();

    $manifest_path = $extract_dir . '/manifest.json';
    if (!is_file($manifest_path)) {
        migration_remove_dir($extract_dir);
        throw new RuntimeException('Migration package is missing manifest.json.');
    }

    $manifest = json_decode((string) file_get_contents($manifest_path), true);
    if (!is_array($manifest) || ($manifest['format'] ?? '') !== 'foxdesk-cloud-migration') {
        migration_remove_dir($extract_dir);
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

function migration_map_value(array $id_maps, string $target_table, $value, bool &$missing = false)
{
    if ($value === null || $value === '') {
        return $value;
    }
    $old = (int) $value;
    if (isset($id_maps[$target_table][$old])) {
        return $id_maps[$target_table][$old];
    }

    $missing = true;
    return null;
}

function migration_remap_row(string $table, array $row, int $tenant_id, array $id_maps, bool &$skip = false): array
{
    if (tenant_scoped_table_has_column($table)) {
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
            $missing = false;
            $mapped = migration_map_value($id_maps, $target_table, $row[$column], $missing);
            if ($missing && !migration_column_allows_null($table, $column)) {
                $skip = true;
                return $row;
            }
            $row[$column] = $mapped;
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

    try {
        $source = migration_safe_extract_target($extract_dir, (string) $file['package_path']);
    } catch (Throwable $e) {
        return $row;
    }
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
        $skip = false;
        $row = migration_remap_row($table, $row, $tenant_id, $id_maps, $skip);
        if ($skip) {
            continue;
        }
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
            'trial_ends_at' => $options['trial_ends_at'] ?? (function_exists('billing_trial_ends_at_for_new_workspace') ? billing_trial_ends_at_for_new_workspace() : date('Y-m-d H:i:s', strtotime('+14 days'))),
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

function migration_bridge_ensure_connections_table(): void
{
    db_query("
        CREATE TABLE IF NOT EXISTS migration_connections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            label VARCHAR(160) NULL,
            source_instance_id VARCHAR(80) NULL,
            source_url VARCHAR(255) NULL,
            source_version VARCHAR(40) NULL,
            status ENUM('issued', 'connected', 'syncing', 'ready_for_cutover', 'cutover_complete', 'revoked') NOT NULL DEFAULT 'issued',
            last_seen_at DATETIME NULL,
            last_plan_json JSON NULL,
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NULL,
            revoked_at DATETIME NULL,
            cutover_at DATETIME NULL,
            INDEX idx_tenant_id (tenant_id),
            INDEX idx_status (status),
            INDEX idx_source_instance (source_instance_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function migration_bridge_token_hash(string $token): string
{
    return hash('sha256', trim($token));
}

function migration_bridge_generate_token(): string
{
    return 'fdmig_' . bin2hex(random_bytes(32));
}

function migration_bridge_create_connection(int $tenant_id, int $created_by = 0, string $label = ''): array
{
    if ($tenant_id <= 0 || !db_fetch_one("SELECT id FROM tenants WHERE id = ? LIMIT 1", [$tenant_id])) {
        throw new InvalidArgumentException('Workspace does not exist.');
    }

    migration_bridge_ensure_connections_table();
    $token = migration_bridge_generate_token();
    $expires_at = date('Y-m-d H:i:s', strtotime('+14 days'));

    $connection_id = (int) db_insert('migration_connections', [
        'tenant_id' => $tenant_id,
        'token_hash' => migration_bridge_token_hash($token),
        'label' => trim($label) !== '' ? trim($label) : 'Self-hosted sync',
        'status' => 'issued',
        'created_by' => $created_by > 0 ? $created_by : null,
        'created_at' => date('Y-m-d H:i:s'),
        'expires_at' => $expires_at,
    ]);

    if (function_exists('log_security_event')) {
        log_security_event('migration_connection_created', (int) ($_SESSION['user_id'] ?? 0), 'tenant_id=' . $tenant_id . ';connection_id=' . $connection_id);
    }

    return [
        'id' => $connection_id,
        'token' => $token,
        'expires_at' => $expires_at,
    ];
}

function migration_bridge_connection_by_token(string $token): ?array
{
    $token = trim($token);
    if ($token === '') {
        return null;
    }

    migration_bridge_ensure_connections_table();
    $connection = db_fetch_one("
        SELECT mc.*, t.name AS tenant_name, t.slug AS tenant_slug, t.status AS tenant_status
        FROM migration_connections mc
        INNER JOIN tenants t ON t.id = mc.tenant_id
        WHERE mc.token_hash = ?
        LIMIT 1
    ", [migration_bridge_token_hash($token)]);

    if (!$connection) {
        return null;
    }
    if (($connection['status'] ?? '') === 'revoked') {
        return null;
    }
    if (!empty($connection['expires_at']) && strtotime((string) $connection['expires_at']) < time()) {
        return null;
    }

    return $connection;
}

function migration_bridge_extract_bearer_token(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(.+)$/i', (string) $header, $m)) {
        return trim((string) $m[1]);
    }

    $input = function_exists('get_json_input') ? get_json_input() : [];
    return trim((string) ($input['token'] ?? $_POST['token'] ?? ''));
}

function migration_bridge_authenticate(): array
{
    $token = migration_bridge_extract_bearer_token();
    $rate_key = function_exists('rate_limit_key')
        ? rate_limit_key('migration_bridge', substr(hash('sha256', $token), 0, 24))
        : 'migration_bridge';
    if (function_exists('rate_limit_is_blocked') && rate_limit_is_blocked($rate_key, 20, 300)) {
        api_error('Too many migration authentication attempts.', 429);
    }

    $connection = migration_bridge_connection_by_token($token);
    if (!$connection) {
        if (function_exists('rate_limit_record')) {
            rate_limit_record($rate_key, 300);
        }
        if (function_exists('log_security_event')) {
            log_security_event('migration_bridge_auth_failed', null, 'token_hash=' . substr(hash('sha256', $token), 0, 16));
        }
        api_error('Invalid or expired migration token.', 401);
    }

    if (function_exists('rate_limit_clear')) {
        rate_limit_clear($rate_key);
    }
    return $connection;
}

function migration_bridge_allowed_sync_tables(): array
{
    return migration_export_tables();
}

function migration_bridge_build_plan(array $connection, array $inventory): array
{
    if (($inventory['format'] ?? '') !== 'foxdesk-cloud-sync-inventory') {
        throw new InvalidArgumentException('Invalid migration inventory.');
    }

    $tables = is_array($inventory['tables'] ?? null) ? $inventory['tables'] : [];
    $allowed = migration_bridge_allowed_sync_tables();
    $table_plan = [];
    $unsupported = [];
    $total_rows = 0;

    foreach ($tables as $table => $meta) {
        $table = (string) $table;
        $rows = (int) ($meta['rows'] ?? 0);
        if (!in_array($table, $allowed, true)) {
            $unsupported[] = $table;
            continue;
        }
        $total_rows += $rows;
        $table_plan[$table] = [
            'rows' => $rows,
            'chunk_size' => $table === 'attachments' ? 50 : 500,
            'mode' => in_array($table, migration_reference_tables(), true) ? 'match_or_create' : 'idempotent_upsert',
        ];
    }

    $attachments = $inventory['files']['attachments'] ?? [];
    return [
        'connection_id' => (int) $connection['id'],
        'tenant_id' => (int) $connection['tenant_id'],
        'tenant_name' => (string) ($connection['tenant_name'] ?? ''),
        'target_workspace_url' => rtrim((defined('APP_URL') ? APP_URL : ''), '/') . '/',
        'direction' => 'self_hosted_to_saas',
        'cutover' => [
            'mode' => 'single_active_instance',
            'source_after_cutover' => 'redirect_and_disable_ingest',
            'final_delta_required' => true,
        ],
        'total_rows' => $total_rows,
        'tables' => $table_plan,
        'unsupported_tables' => $unsupported,
        'attachments' => [
            'rows' => (int) ($attachments['rows'] ?? ($tables['attachments']['rows'] ?? 0)),
            'bytes' => (int) ($attachments['bytes'] ?? 0),
            'upload' => 'stream_or_r2_presigned_url',
        ],
        'secrets_policy' => [
            'api_tokens' => 'import_disabled',
            'smtp_imap_passwords' => 'reenter_in_saas',
            'password_hashes' => 'preserve_when_compatible',
        ],
        'stages' => [
            'connect',
            'initial_table_sync',
            'attachment_sync',
            'delta_sync',
            'final_sync',
            'cutover',
        ],
    ];
}

function migration_bridge_record_inventory(array $connection, array $inventory, array $plan, string $status): void
{
    $source = is_array($inventory['source'] ?? null) ? $inventory['source'] : [];
    db_update('migration_connections', [
        'source_instance_id' => (string) ($source['instance_id'] ?? ''),
        'source_url' => (string) ($source['base_url'] ?? ''),
        'source_version' => (string) ($source['app_version'] ?? ''),
        'status' => $status,
        'last_seen_at' => date('Y-m-d H:i:s'),
        'last_plan_json' => json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ], 'id = ?', [(int) $connection['id']]);
}

function migration_bridge_ensure_object_map_table(): void
{
    db_query("
        CREATE TABLE IF NOT EXISTS migration_object_map (
            id INT AUTO_INCREMENT PRIMARY KEY,
            connection_id INT NOT NULL,
            tenant_id INT NOT NULL,
            source_table VARCHAR(80) NOT NULL,
            source_id INT NOT NULL,
            target_id INT NOT NULL,
            source_updated_at DATETIME NULL,
            row_hash CHAR(64) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_connection_object (connection_id, source_table, source_id),
            INDEX idx_tenant_table (tenant_id, source_table),
            INDEX idx_target (target_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function migration_bridge_chunk_tables(): array
{
    return array_values(array_diff(migration_export_tables(), [
        'settings',
        'email_templates',
        'api_tokens',
        'attachments',
    ]));
}

function migration_bridge_mapped_id(int $connection_id, string $table, $source_id): ?int
{
    $source_id = (int) $source_id;
    if ($source_id <= 0) {
        return null;
    }

    migration_bridge_ensure_object_map_table();
    $row = db_fetch_one(
        "SELECT target_id FROM migration_object_map WHERE connection_id = ? AND source_table = ? AND source_id = ? LIMIT 1",
        [$connection_id, $table, $source_id]
    );

    return $row ? (int) $row['target_id'] : null;
}

function migration_bridge_store_map(int $connection_id, int $tenant_id, string $table, int $source_id, int $target_id, array $row): void
{
    if ($source_id <= 0 || $target_id <= 0) {
        return;
    }

    migration_bridge_ensure_object_map_table();
    $source_updated_at = $row['updated_at'] ?? $row['created_at'] ?? null;
    $row_hash = hash('sha256', json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $existing = db_fetch_one(
        "SELECT id FROM migration_object_map WHERE connection_id = ? AND source_table = ? AND source_id = ? LIMIT 1",
        [$connection_id, $table, $source_id]
    );

    $data = [
        'tenant_id' => $tenant_id,
        'target_id' => $target_id,
        'source_updated_at' => $source_updated_at,
        'row_hash' => $row_hash,
    ];
    if ($existing) {
        db_update('migration_object_map', $data, 'id = ?', [(int) $existing['id']]);
        return;
    }

    db_insert('migration_object_map', array_merge($data, [
        'connection_id' => $connection_id,
        'source_table' => $table,
        'source_id' => $source_id,
        'created_at' => date('Y-m-d H:i:s'),
    ]));
}

function migration_bridge_import_reference_row(string $table, array $row, int $connection_id, int $tenant_id): array
{
    $old_id = (int) ($row['id'] ?? 0);
    $existing = null;
    if (!empty($row['slug']) && column_exists($table, 'slug')) {
        $existing = db_fetch_one("SELECT id FROM {$table} WHERE slug = ? LIMIT 1", [(string) $row['slug']]);
    }
    if (!$existing && !empty($row['name']) && column_exists($table, 'name')) {
        $existing = db_fetch_one("SELECT id FROM {$table} WHERE name = ? LIMIT 1", [(string) $row['name']]);
    }

    if ($existing) {
        migration_bridge_store_map($connection_id, $tenant_id, $table, $old_id, (int) $existing['id'], $row);
        return ['created' => 0, 'updated' => 0, 'mapped' => 1, 'skipped' => 0];
    }

    $columns = migration_table_columns($table);
    $data = [];
    foreach ($row as $key => $value) {
        if ($key === 'id' || !in_array($key, $columns, true)) {
            continue;
        }
        $data[$key] = $value;
    }
    if (!$data) {
        return ['created' => 0, 'updated' => 0, 'mapped' => 0, 'skipped' => 1];
    }

    $target_id = (int) db_insert($table, $data);
    migration_bridge_store_map($connection_id, $tenant_id, $table, $old_id, $target_id, $row);
    return ['created' => 1, 'updated' => 0, 'mapped' => 0, 'skipped' => 0];
}

function migration_bridge_remap_chunk_row(array $connection, string $table, array $row, bool &$skip = false): array
{
    $connection_id = (int) $connection['id'];
    $tenant_id = (int) $connection['tenant_id'];

    if (tenant_scoped_table_has_column($table)) {
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
        if (!array_key_exists($column, $row) || $row[$column] === null || $row[$column] === '') {
            continue;
        }
        $mapped = migration_bridge_mapped_id($connection_id, $target_table, $row[$column]);
        if ($mapped === null && !migration_column_allows_null($table, $column)) {
            $skip = true;
            return $row;
        }
        $row[$column] = $mapped;
    }

    if ($table === 'users') {
        $row['is_platform_admin'] = 0;
        $row['reset_token'] = null;
        $row['reset_token_expires'] = null;
    }

    if ($table === 'tickets' && !empty($row['hash'])) {
        $existing = db_fetch_one("SELECT id FROM tickets WHERE hash = ? AND tenant_id = ? LIMIT 1", [$row['hash'], $tenant_id]);
        if ($existing) {
            $mapped_ticket_id = migration_bridge_mapped_id($connection_id, 'tickets', $row['id'] ?? 0);
            if ($mapped_ticket_id === null || (int) $existing['id'] !== $mapped_ticket_id) {
                $row['hash'] = substr(bin2hex(random_bytes(8)), 0, 16);
            }
        }
    }

    if ($table === 'ticket_message_attachments' && !empty($row['attachment_id'])) {
        $target_attachment = db_fetch_one(
            "SELECT storage_key FROM attachments WHERE id = ? AND tenant_id = ? LIMIT 1",
            [(int) $row['attachment_id'], $tenant_id]
        );
        if ($target_attachment && !empty($target_attachment['storage_key'])) {
            $row['storage_path'] = (string) $target_attachment['storage_key'];
        }
    }

    return $row;
}

function migration_bridge_import_chunk_row(array $connection, string $table, array $row): array
{
    $connection_id = (int) $connection['id'];
    $tenant_id = (int) $connection['tenant_id'];
    $old_id = (int) ($row['id'] ?? 0);
    if ($old_id <= 0) {
        return ['created' => 0, 'updated' => 0, 'mapped' => 0, 'skipped' => 1];
    }

    if (in_array($table, migration_reference_tables(), true)) {
        return migration_bridge_import_reference_row($table, $row, $connection_id, $tenant_id);
    }

    $skip = false;
    $remapped = migration_bridge_remap_chunk_row($connection, $table, $row, $skip);
    if ($skip || !table_exists($table)) {
        return ['created' => 0, 'updated' => 0, 'mapped' => 0, 'skipped' => 1];
    }

    $columns = migration_table_columns($table);
    $data = [];
    foreach ($remapped as $key => $value) {
        if ($key === 'id' || !in_array($key, $columns, true)) {
            continue;
        }
        $data[$key] = $value;
    }
    if (!$data) {
        return ['created' => 0, 'updated' => 0, 'mapped' => 0, 'skipped' => 1];
    }

    if ($table === 'users' && !empty($data['email'])) {
        $existing_user = db_fetch_one("SELECT id, tenant_id FROM users WHERE email = ? LIMIT 1", [(string) $data['email']]);
        if ($existing_user && (int) ($existing_user['tenant_id'] ?? 0) === $tenant_id) {
            $target_id = (int) $existing_user['id'];
            db_update('users', $data, 'id = ? AND tenant_id = ?', [$target_id, $tenant_id]);
            migration_bridge_store_map($connection_id, $tenant_id, $table, $old_id, $target_id, $row);
            return ['created' => 0, 'updated' => 1, 'mapped' => 0, 'skipped' => 0];
        }
        if ($existing_user) {
            return ['created' => 0, 'updated' => 0, 'mapped' => 0, 'skipped' => 1];
        }
    }

    $target_id = migration_bridge_mapped_id($connection_id, $table, $old_id);
    if ($target_id !== null) {
        $where = tenant_scoped_table_has_column($table) ? 'id = ? AND tenant_id = ?' : 'id = ?';
        $params = tenant_scoped_table_has_column($table) ? [$target_id, $tenant_id] : [$target_id];
        db_update($table, $data, $where, $params);
        migration_bridge_store_map($connection_id, $tenant_id, $table, $old_id, $target_id, $row);
        return ['created' => 0, 'updated' => 1, 'mapped' => 0, 'skipped' => 0];
    }

    $target_id = (int) db_insert($table, $data);
    migration_bridge_store_map($connection_id, $tenant_id, $table, $old_id, $target_id, $row);
    return ['created' => 1, 'updated' => 0, 'mapped' => 0, 'skipped' => 0];
}

function migration_bridge_import_table_chunk(array $connection, string $table, array $rows): array
{
    validate_sql_identifier($table);
    if (!in_array($table, migration_bridge_chunk_tables(), true)) {
        throw new InvalidArgumentException('This table is not enabled for API chunk sync yet.');
    }
    if (!table_exists($table)) {
        throw new InvalidArgumentException('Target table does not exist.');
    }

    migration_bridge_ensure_object_map_table();
    $summary = ['created' => 0, 'updated' => 0, 'mapped' => 0, 'skipped' => 0, 'rows' => count($rows)];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            $summary['skipped']++;
            continue;
        }
        $result = migration_bridge_import_chunk_row($connection, $table, $row);
        foreach (['created', 'updated', 'mapped', 'skipped'] as $key) {
            $summary[$key] += (int) ($result[$key] ?? 0);
        }
    }

    return $summary;
}

function migration_bridge_import_attachment_upload(array $connection, array $row, string $uploaded_path): array
{
    if (!is_file($uploaded_path)) {
        throw new RuntimeException('Uploaded attachment file is missing.');
    }

    $connection_id = (int) $connection['id'];
    $tenant_id = (int) $connection['tenant_id'];
    $old_id = (int) ($row['id'] ?? $row['source_id'] ?? 0);
    if ($old_id <= 0) {
        throw new InvalidArgumentException('Attachment source ID is required.');
    }

    $mapped_id = migration_bridge_mapped_id($connection_id, 'attachments', $old_id);
    if ($mapped_id !== null) {
        $existing = db_fetch_one("SELECT id FROM attachments WHERE id = ? AND tenant_id = ? LIMIT 1", [$mapped_id, $tenant_id]);
        if ($existing) {
            return ['attachment_id' => $mapped_id, 'mapped' => true, 'created' => false];
        }
    }

    $skip = false;
    $remapped = migration_bridge_remap_chunk_row($connection, 'attachments', $row, $skip);
    if ($skip) {
        throw new RuntimeException('Attachment references missing parent objects.');
    }

    $original = basename((string) ($row['original_name'] ?? $row['filename'] ?? 'attachment.bin'));
    $source_filename = basename((string) ($row['filename'] ?? $original));
    $extension = pathinfo($source_filename !== '' ? $source_filename : $original, PATHINFO_EXTENSION);
    $safe_extension = $extension !== '' ? ('.' . preg_replace('/[^a-z0-9]+/i', '', $extension)) : '';
    $stored_name = 'migrated_' . $tenant_id . '_' . bin2hex(random_bytes(8)) . $safe_extension;
    $relative_path = 'uploads/' . $stored_name;
    $target = BASE_PATH . '/' . $relative_path;

    if (!is_dir(dirname($target))) {
        @mkdir(dirname($target), 0755, true);
    }
    if (!copy($uploaded_path, $target)) {
        throw new RuntimeException('Unable to stage uploaded attachment.');
    }

    $mime = (string) ($row['mime_type'] ?? '');
    if ($mime === '' && function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string) finfo_file($finfo, $target);
            finfo_close($finfo);
        }
    }
    $size = filesize($target) ?: (int) ($row['file_size'] ?? 0);

    $storage = function_exists('storage_store_file')
        ? storage_store_file($target, $relative_path, $mime, $tenant_id)
        : ['driver' => 'local', 'key' => $relative_path, 'bucket' => ''];

    $columns = migration_table_columns('attachments');
    $data = [
        'tenant_id' => $tenant_id,
        'ticket_id' => (int) ($remapped['ticket_id'] ?? 0),
        'comment_id' => !empty($remapped['comment_id']) ? (int) $remapped['comment_id'] : null,
        'filename' => $stored_name,
        'original_name' => $original !== '' ? $original : $stored_name,
        'mime_type' => $mime !== '' ? $mime : 'application/octet-stream',
        'file_size' => $size,
        'uploaded_by' => !empty($remapped['uploaded_by']) ? (int) $remapped['uploaded_by'] : null,
        'storage_driver' => $storage['driver'] ?? 'local',
        'storage_bucket' => $storage['bucket'] ?? '',
        'storage_key' => $storage['key'] ?? $relative_path,
        'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
    ];
    $data = array_filter($data, static fn($value, string $key): bool => in_array($key, $columns, true), ARRAY_FILTER_USE_BOTH);

    if (empty($data['ticket_id'])) {
        throw new RuntimeException('Attachment target ticket is missing.');
    }

    $target_id = (int) db_insert('attachments', $data);
    migration_bridge_store_map($connection_id, $tenant_id, 'attachments', $old_id, $target_id, $row);

    return [
        'attachment_id' => $target_id,
        'mapped' => false,
        'created' => true,
        'storage_driver' => $data['storage_driver'] ?? 'local',
        'storage_key' => $data['storage_key'] ?? $relative_path,
    ];
}
