<?php

return static function (PDO $db): void {
    $tableExists = static function (string $table) use ($db): bool {
        $stmt = $db->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    };
    $columnExists = static function (string $table, string $column) use ($db): bool {
        $stmt = $db->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
        $stmt->execute([$column]);
        return (bool) $stmt->fetchColumn();
    };
    $indexExists = static function (string $table, string $index) use ($db): bool {
        $stmt = $db->prepare("SHOW INDEX FROM `{$table}` WHERE Key_name = ?");
        $stmt->execute([$index]);
        return (bool) $stmt->fetchColumn();
    };
    $addColumn = static function (string $table, string $column, string $definition) use ($db, $tableExists, $columnExists): void {
        if ($tableExists($table) && !$columnExists($table, $column)) {
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    };
    $addIndex = static function (string $table, string $index, string $definition) use ($db, $tableExists, $indexExists): void {
        if ($tableExists($table) && !$indexExists($table, $index)) {
            $db->exec("ALTER TABLE `{$table}` ADD {$definition}");
        }
    };

    $columns = [
        'users' => [
            'remember_token' => 'VARCHAR(64) DEFAULT NULL',
            'totp_secret' => 'VARCHAR(64) NULL',
            'totp_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'totp_backup_codes' => 'TEXT NULL',
            'last_notifications_seen_at' => 'DATETIME NULL',
            'notification_preferences' => 'JSON NULL',
            'is_ai_agent' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'ai_model' => 'VARCHAR(100) NULL DEFAULT NULL',
            'billable_rate' => 'DECIMAL(10,2) DEFAULT 0',
        ],
        'organizations' => [
            'logo' => 'TEXT DEFAULT NULL',
        ],
        'tickets' => [
            'hash' => 'VARCHAR(16) DEFAULT NULL',
            'source' => "VARCHAR(20) DEFAULT 'web'",
            'custom_billable_rate' => 'DECIMAL(10,2) NULL DEFAULT NULL',
        ],
        'ticket_time_entries' => [
            'paused_at' => 'DATETIME DEFAULT NULL',
            'paused_seconds' => 'INT DEFAULT 0',
        ],
        'recurring_tasks' => [
            'due_days' => 'INT DEFAULT 7',
            'paused_at' => 'DATETIME NULL DEFAULT NULL',
            'resume_date' => 'DATE NULL DEFAULT NULL',
            'tags' => 'TEXT NULL DEFAULT NULL',
        ],
        'report_templates' => [
            'custom_billable_rate' => 'DECIMAL(10,2) NULL DEFAULT NULL',
            'tags' => 'TEXT NULL DEFAULT NULL',
            'agent_ids' => 'TEXT NULL DEFAULT NULL',
            'expires_at' => 'DATETIME NULL',
            'schedule_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'schedule_interval' => "VARCHAR(20) NOT NULL DEFAULT 'monthly'",
            'schedule_day' => 'INT NOT NULL DEFAULT 1',
            'schedule_recipients' => 'TEXT NULL',
            'schedule_last_sent' => 'DATETIME NULL',
            'schedule_next_due' => 'DATE NULL',
        ],
        'report_shares' => [
            'report_template_id' => 'INT NULL',
            'share_secret' => 'VARCHAR(64) NULL',
        ],
        'notifications' => [
            'actor_id' => 'INT NULL',
            'data' => 'JSON NULL',
            'is_resolved' => 'TINYINT(1) NOT NULL DEFAULT 0',
        ],
        'email_ingest_logs' => [
            'sender_email' => 'VARCHAR(255) NULL',
            'subject' => 'VARCHAR(255) NULL',
            'ticket_id' => 'INT NULL',
        ],
        'email_templates' => [
            'language' => "VARCHAR(5) NOT NULL DEFAULT 'en'",
        ],
        'migration_connections' => [
            'attachment_sync_count' => 'INT NOT NULL DEFAULT 0',
            'attachment_sync_bytes' => 'BIGINT NOT NULL DEFAULT 0',
            'attachment_sync_last_at' => 'DATETIME NULL',
            'attachment_sync_last_key' => 'VARCHAR(700) NULL',
            'attachment_sync_last_checksum' => 'CHAR(64) NULL',
            'attachment_sync_last_source_id' => 'INT NULL',
        ],
    ];
    foreach ($columns as $table => $definitions) {
        foreach ($definitions as $column => $definition) {
            $addColumn($table, $column, $definition);
        }
    }

    $indexes = [
        ['tickets', 'idx_hash', 'UNIQUE INDEX `idx_hash` (`hash`)'],
        ['tickets', 'idx_source', 'INDEX `idx_source` (`source`)'],
        ['report_shares', 'idx_report_template', 'INDEX `idx_report_template` (`report_template_id`)'],
        ['notifications', 'idx_notifications_tenant_user', 'INDEX `idx_notifications_tenant_user` (`tenant_id`, `user_id`)'],
        ['email_ingest_logs', 'idx_sender_email', 'INDEX `idx_sender_email` (`sender_email`)'],
        ['email_ingest_logs', 'idx_ticket_id', 'INDEX `idx_ticket_id` (`ticket_id`)'],
    ];
    foreach ($indexes as [$table, $index, $definition]) {
        $addIndex($table, $index, $definition);
    }

    if ($tableExists('email_templates')) {
        $indexes = $db->query('SHOW INDEX FROM email_templates')->fetchAll(PDO::FETCH_ASSOC);
        $uniqueColumns = [];
        foreach ($indexes as $row) {
            if ((int) ($row['Non_unique'] ?? 1) === 0) {
                $uniqueColumns[(string) $row['Key_name']][(int) $row['Seq_in_index']] = (string) $row['Column_name'];
            }
        }
        $hasLanguageKey = false;
        foreach ($uniqueColumns as $name => $parts) {
            ksort($parts);
            $parts = array_values($parts);
            if ($parts === ['template_key', 'language']) {
                $hasLanguageKey = true;
            } elseif ($parts === ['template_key'] && $name !== 'PRIMARY') {
                $safeName = str_replace('`', '', $name);
                $db->exec("ALTER TABLE email_templates DROP INDEX `{$safeName}`");
            }
        }
        if (!$hasLanguageKey && !$indexExists('email_templates', 'uniq_key_lang')) {
            $db->exec('ALTER TABLE email_templates ADD UNIQUE KEY uniq_key_lang (template_key, language)');
        }
    }

    if ($tableExists('allowed_senders')) {
        if ($indexExists('allowed_senders', 'uniq_type_value')) {
            $db->exec('ALTER TABLE allowed_senders DROP INDEX uniq_type_value');
        }
        if (!$indexExists('allowed_senders', 'uniq_tenant_type_value')) {
            $db->exec('ALTER TABLE allowed_senders ADD UNIQUE KEY uniq_tenant_type_value (tenant_id, type, value)');
        }
    }

    $defaultTenant = $tableExists('tenants')
        ? (int) ($db->query("SELECT id FROM tenants WHERE slug = 'default' LIMIT 1")->fetchColumn() ?: 0)
        : 0;
    if ($defaultTenant > 0) {
        $tenantTables = [
            'billing_usage_events', 'migration_imports', 'migration_connections', 'migration_object_map',
            'allowed_senders', 'ticket_messages', 'ticket_message_attachments', 'email_ingest_logs',
            'api_tokens', 'api_token_audit_logs', 'api_idempotency_keys', 'mobile_auth_challenges',
            'mobile_sessions', 'mobile_idempotency_keys', 'mobile_devices', 'notifications',
            'push_subscriptions', 'report_templates', 'report_shares', 'ticket_shares', 'page_views',
            'agent_client_billable_rates',
        ];
        foreach ($tenantTables as $table) {
            if ($tableExists($table) && $columnExists($table, 'tenant_id')) {
                $stmt = $db->prepare("UPDATE `{$table}` SET tenant_id = ? WHERE tenant_id IS NULL");
                $stmt->execute([$defaultTenant]);
            }
        }
    }

    if ($tableExists('notifications') && $columnExists('notifications', 'is_resolved')) {
        $db->exec("UPDATE notifications n
            JOIN tickets t ON n.ticket_id = t.id
            JOIN statuses s ON t.status_id = s.id
            SET n.is_resolved = 1
            WHERE s.is_closed = 1 AND n.is_resolved = 0
              AND n.type IN ('assigned_to_you','due_date_reminder')");
    }

    if ($tableExists('tickets') && $columnExists('tickets', 'hash')) {
        $stmt = $db->query("SELECT id FROM tickets WHERE hash IS NULL OR hash = ''");
        $update = $db->prepare('UPDATE tickets SET hash = ? WHERE id = ?');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ticket) {
            do {
                $hash = substr(bin2hex(random_bytes(12)), 0, 16);
                $check = $db->prepare('SELECT 1 FROM tickets WHERE hash = ? LIMIT 1');
                $check->execute([$hash]);
            } while ($check->fetchColumn());
            $update->execute([$hash, (int) $ticket['id']]);
        }
    }
};
