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
    $addColumn = static function (string $table, string $column, string $definition) use ($db, $columnExists): void {
        if (!$columnExists($table, $column)) {
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    };
    $addIndex = static function (string $table, string $index, string $definition) use ($db, $indexExists): void {
        if (!$indexExists($table, $index)) {
            $db->exec("ALTER TABLE `{$table}` ADD {$definition}");
        }
    };

    if (!$tableExists('tenants')) {
        $db->exec("CREATE TABLE tenants (
            id INT AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(120) NOT NULL UNIQUE,
            primary_domain VARCHAR(255) NULL,
            plan VARCHAR(50) NOT NULL DEFAULT 'cloud',
            status ENUM('active','trialing','past_due','trial_expired','suspended','blocked','canceled') NOT NULL DEFAULT 'active',
            owner_user_id INT NULL,
            billing_email VARCHAR(255) NULL,
            stripe_customer_id VARCHAR(255) NULL,
            stripe_subscription_id VARCHAR(255) NULL,
            subscription_status VARCHAR(50) NOT NULL DEFAULT 'manual',
            max_users INT NOT NULL DEFAULT 1000000,
            max_agents INT NOT NULL DEFAULT 1000000,
            billing_override_reason VARCHAR(500) NULL,
            billing_override_at DATETIME NULL,
            billing_override_by INT NULL,
            trial_ends_at DATETIME NULL,
            suspended_at DATETIME NULL,
            blocked_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_subscription_status (subscription_status),
            INDEX idx_domain (primary_domain)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } else {
        $tenantColumns = [
            'owner_user_id' => 'INT NULL AFTER `status`',
            'billing_email' => 'VARCHAR(255) NULL AFTER `owner_user_id`',
            'stripe_customer_id' => 'VARCHAR(255) NULL AFTER `billing_email`',
            'stripe_subscription_id' => 'VARCHAR(255) NULL AFTER `stripe_customer_id`',
            'subscription_status' => "VARCHAR(50) NOT NULL DEFAULT 'manual' AFTER `stripe_subscription_id`",
            'max_users' => 'INT NOT NULL DEFAULT 1000000 AFTER `subscription_status`',
            'max_agents' => 'INT NOT NULL DEFAULT 1000000 AFTER `max_users`',
            'billing_override_reason' => 'VARCHAR(500) NULL AFTER `max_agents`',
            'billing_override_at' => 'DATETIME NULL AFTER `billing_override_reason`',
            'billing_override_by' => 'INT NULL AFTER `billing_override_at`',
            'suspended_at' => 'DATETIME NULL AFTER `trial_ends_at`',
            'blocked_at' => 'DATETIME NULL AFTER `suspended_at`',
        ];
        foreach ($tenantColumns as $column => $definition) {
            $addColumn('tenants', $column, $definition);
        }
    }

    $defaultTenant = $db->query("SELECT id FROM tenants WHERE slug = 'default' LIMIT 1")->fetchColumn();
    if (!$defaultTenant) {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
        $stmt = $db->prepare("INSERT INTO tenants (uuid, name, slug, status, created_at) VALUES (?, 'Default workspace', 'default', 'active', NOW())");
        $stmt->execute([$uuid]);
        $defaultTenant = (int) $db->lastInsertId();
    }
    $defaultTenant = (int) $defaultTenant;

    if ($tableExists('users')) {
        $addColumn('users', 'tenant_id', 'INT NULL AFTER `id`');
        $addColumn('users', 'is_platform_admin', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER `role`');
        $addIndex('users', 'idx_tenant_id', 'INDEX `idx_tenant_id` (`tenant_id`)');
        $addIndex('users', 'idx_platform_admin', 'INDEX `idx_platform_admin` (`is_platform_admin`)');
    }

    if ($tableExists('attachments')) {
        $addColumn('attachments', 'tenant_id', 'INT NULL AFTER `id`');
        $addColumn('attachments', 'storage_driver', "VARCHAR(20) NOT NULL DEFAULT 'local' AFTER `file_size`");
        $addColumn('attachments', 'storage_bucket', 'VARCHAR(255) NULL AFTER `storage_driver`');
        $addColumn('attachments', 'storage_key', 'VARCHAR(700) NULL AFTER `storage_bucket`');
        $addIndex('attachments', 'idx_tenant_id', 'INDEX `idx_tenant_id` (`tenant_id`)');
        if (!$indexExists('attachments', 'idx_storage_key')) {
            $db->exec('CREATE INDEX `idx_storage_key` ON `attachments` (`storage_key`(191))');
        }
    }

    $tenantTables = [
        'organizations', 'tickets', 'comments', 'ticket_time_entries', 'agent_client_billable_rates',
        'ticket_shares', 'report_shares', 'ticket_access', 'activity_log', 'api_tokens',
        'api_token_audit_logs', 'api_idempotency_keys', 'mobile_auth_challenges', 'mobile_sessions',
        'mobile_devices', 'mobile_idempotency_keys', 'notifications', 'push_subscriptions',
        'allowed_senders', 'recurring_tasks', 'report_templates', 'report_snapshots',
        'billing_usage_events', 'ticket_messages', 'ticket_message_attachments', 'email_ingest_logs',
        'security_log', 'debug_log', 'page_views',
    ];
    foreach ($tenantTables as $table) {
        if (!$tableExists($table)) {
            continue;
        }
        $addColumn($table, 'tenant_id', 'INT NULL AFTER `id`');
        $addIndex($table, 'idx_tenant_id', 'INDEX `idx_tenant_id` (`tenant_id`)');
        $stmt = $db->prepare("UPDATE `{$table}` SET tenant_id = ? WHERE tenant_id IS NULL");
        $stmt->execute([$defaultTenant]);
    }

    if ($tableExists('allowed_senders') && $columnExists('allowed_senders', 'tenant_id')) {
        if ($indexExists('allowed_senders', 'uniq_type_value')) {
            $db->exec('ALTER TABLE allowed_senders DROP INDEX uniq_type_value');
        }
        if (!$indexExists('allowed_senders', 'uniq_tenant_type_value')) {
            $db->exec('ALTER TABLE allowed_senders ADD UNIQUE KEY uniq_tenant_type_value (tenant_id, type, value)');
        }
    }
};
