<?php
/**
 * Versioned database migration runner used only by install/update tooling.
 */

function foxdesk_ensure_schema_migrations_table(PDO $db): void
{
    $db->exec(
        "CREATE TABLE IF NOT EXISTS foxdesk_schema_migrations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(191) NOT NULL,
            checksum CHAR(64) NOT NULL,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_foxdesk_schema_migration (migration)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function foxdesk_migration_files(string $directory): array
{
    $files = array_merge(
        glob(rtrim($directory, '/') . '/*.sql') ?: [],
        glob(rtrim($directory, '/') . '/*.php') ?: []
    );
    sort($files, SORT_STRING);
    return $files;
}

function foxdesk_run_versioned_migrations(string $directory): array
{
    $result = ['success' => true, 'messages' => [], 'errors' => []];
    if (!is_dir($directory)) {
        return $result;
    }

    $db = get_db();
    foxdesk_ensure_schema_migrations_table($db);

    foreach (foxdesk_migration_files($directory) as $file) {
        $name = basename($file);
        $checksum = hash_file('sha256', $file);
        $applied = db_fetch_one(
            'SELECT checksum FROM foxdesk_schema_migrations WHERE migration = ? LIMIT 1',
            [$name]
        );

        if ($applied) {
            if (!hash_equals((string) $applied['checksum'], (string) $checksum)) {
                $result['success'] = false;
                $result['errors'][] = "Migration {$name} changed after it was applied.";
            } else {
                $result['messages'][] = "Migration {$name} already applied.";
            }
            continue;
        }

        try {
            if (str_ends_with($name, '.php')) {
                $migration = require $file;
                if (!is_callable($migration)) {
                    throw new RuntimeException("PHP migration {$name} must return a callable.");
                }
                $migration($db);
            } else {
                $sql = (string) file_get_contents($file);
                foreach (split_sql_statements($sql) as $statement) {
                    if (trim((string) $statement) !== '') {
                        $db->exec($statement);
                    }
                }
            }

            db_insert('foxdesk_schema_migrations', [
                'migration' => $name,
                'checksum' => $checksum,
                'applied_at' => date('Y-m-d H:i:s'),
            ]);
            $result['messages'][] = "Migration {$name} applied.";
        } catch (Throwable $e) {
            $result['success'] = false;
            $result['errors'][] = "Migration {$name} failed: " . $e->getMessage();
            break;
        }
    }

    return $result;
}
