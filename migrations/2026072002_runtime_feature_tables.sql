-- billing_usage_reports
CREATE TABLE IF NOT EXISTS billing_usage_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    stripe_customer_id VARCHAR(255) NOT NULL,
    event_name VARCHAR(120) NOT NULL,
    period_key VARCHAR(20) NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    idempotency_key VARCHAR(255) NOT NULL,
    status ENUM('pending', 'reported', 'dry_run', 'failed', 'skipped') NOT NULL DEFAULT 'pending',
    error_message TEXT NULL,
    reported_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_billing_usage_idempotency (idempotency_key),
    INDEX idx_billing_usage_tenant_period (tenant_id, period_key),
    INDEX idx_billing_usage_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- billing_usage_events
CREATE TABLE IF NOT EXISTS billing_usage_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    event_type VARCHAR(80) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    metadata_json TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_billing_usage_events_tenant_created (tenant_id, created_at),
    INDEX idx_billing_usage_events_type_created (event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- billing_stripe_events
CREATE TABLE IF NOT EXISTS billing_stripe_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(255) NOT NULL,
    event_type VARCHAR(120) NOT NULL,
    tenant_id INT NULL,
    status ENUM('pending', 'processed', 'ignored', 'failed') NOT NULL DEFAULT 'pending',
    error_message TEXT NULL,
    processed_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_billing_stripe_event_id (event_id),
    INDEX idx_billing_stripe_events_tenant (tenant_id),
    INDEX idx_billing_stripe_events_status (status),
    INDEX idx_billing_stripe_events_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- billing_trial_email_events
CREATE TABLE IF NOT EXISTS billing_trial_email_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    status ENUM('sent', 'skipped', 'failed') NOT NULL DEFAULT 'sent',
    error_message TEXT NULL,
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_trial_email_event (tenant_id, event_type),
    INDEX idx_trial_email_tenant (tenant_id),
    INDEX idx_trial_email_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- migration_imports
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- migration_connections
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
    attachment_sync_count INT NOT NULL DEFAULT 0,
    attachment_sync_bytes BIGINT NOT NULL DEFAULT 0,
    attachment_sync_last_at DATETIME NULL,
    attachment_sync_last_key VARCHAR(700) NULL,
    attachment_sync_last_checksum CHAR(64) NULL,
    attachment_sync_last_source_id INT NULL,
    created_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NULL,
    revoked_at DATETIME NULL,
    cutover_at DATETIME NULL,
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_status (status),
    INDEX idx_source_instance (source_instance_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- migration_object_map
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- allowed_senders
CREATE TABLE IF NOT EXISTS allowed_senders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    type ENUM('email','domain') NOT NULL,
    value VARCHAR(255) NOT NULL,
    user_id INT NULL,
    active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_type_value (type, value),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_active (active),
    INDEX idx_user (user_id),
    CONSTRAINT fk_allowed_senders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ticket_messages
CREATE TABLE IF NOT EXISTS ticket_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    ticket_id INT NOT NULL,
    direction ENUM('in','out') NOT NULL DEFAULT 'in',
    user_id INT NULL,
    comment_id INT NULL,
    sender_email VARCHAR(255) NULL,
    subject VARCHAR(255) NULL,
    body_text MEDIUMTEXT,
    body_html MEDIUMTEXT NULL,
    body_html_raw MEDIUMTEXT NULL,
    raw_headers MEDIUMTEXT,
    message_id VARCHAR(255) NULL,
    in_reply_to VARCHAR(255) NULL,
    references_header TEXT NULL,
    mailbox VARCHAR(120) NULL,
    uid INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_ticket_messages_message_id (message_id),
    INDEX idx_tenant_id (tenant_id),
    UNIQUE KEY uniq_ticket_messages_mailbox_uid (mailbox, uid),
    INDEX idx_ticket (ticket_id),
    INDEX idx_comment (comment_id),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at),
    CONSTRAINT fk_ticket_messages_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_ticket_messages_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_ticket_messages_comment FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ticket_message_attachments
CREATE TABLE IF NOT EXISTS ticket_message_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    ticket_message_id INT NOT NULL,
    attachment_id INT NULL,
    filename VARCHAR(255) NOT NULL,
    mime VARCHAR(120) NULL,
    size INT DEFAULT 0,
    storage_path VARCHAR(500) NOT NULL,
    content_id VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_message (ticket_message_id),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_attachment (attachment_id),
    CONSTRAINT fk_tma_message FOREIGN KEY (ticket_message_id) REFERENCES ticket_messages(id) ON DELETE CASCADE,
    CONSTRAINT fk_tma_attachment FOREIGN KEY (attachment_id) REFERENCES attachments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- email_ingest_logs
CREATE TABLE IF NOT EXISTS email_ingest_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    mailbox VARCHAR(120) NOT NULL,
    uid INT NOT NULL,
    message_id VARCHAR(255) NULL,
    sender_email VARCHAR(255) NULL,
    subject VARCHAR(255) NULL,
    ticket_id INT NULL,
    status ENUM('processed','skipped','failed') NOT NULL,
    reason VARCHAR(100) NULL,
    error TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_mailbox_uid (mailbox, uid),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_message_id (message_id),
    INDEX idx_sender_email (sender_email),
    INDEX idx_ticket_id (ticket_id),
    INDEX idx_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- email_ingest_state
CREATE TABLE IF NOT EXISTS email_ingest_state (
    mailbox VARCHAR(120) PRIMARY KEY,
    last_seen_uid INT DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- api_tokens
CREATE TABLE IF NOT EXISTS api_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    token_prefix VARCHAR(10) NOT NULL,
    scopes_json TEXT NULL,
    expires_at DATETIME NULL,
    is_active TINYINT(1) DEFAULT 1,
    revoked_at DATETIME NULL,
    last_used_at DATETIME NULL,
    last_used_ip VARCHAR(45) NULL,
    last_used_user_agent VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_token_hash (token_hash),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_user (user_id),
    INDEX idx_active (is_active),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- api_token_audit_logs
CREATE TABLE IF NOT EXISTS api_token_audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    token_id INT NULL,
    user_id INT NOT NULL,
    action VARCHAR(120) NOT NULL,
    method VARCHAR(10) NOT NULL,
    resource_type VARCHAR(80) NULL,
    resource_id INT NULL,
    status_code INT DEFAULT 200,
    request_id VARCHAR(64) NULL,
    idempotency_key VARCHAR(128) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_token_created (token_id, created_at),
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_action_created (action, created_at),
    FOREIGN KEY (token_id) REFERENCES api_tokens(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- api_idempotency_keys
CREATE TABLE IF NOT EXISTS api_idempotency_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    token_id INT NOT NULL,
    user_id INT NOT NULL,
    idempotency_key VARCHAR(128) NOT NULL,
    action VARCHAR(120) NOT NULL,
    request_hash CHAR(64) NOT NULL,
    response_json MEDIUMTEXT NULL,
    status_code INT DEFAULT 200,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    UNIQUE KEY uniq_api_idempotency_token_key (token_id, action, idempotency_key),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (token_id) REFERENCES api_tokens(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- mobile_auth_challenges
CREATE TABLE IF NOT EXISTS mobile_auth_challenges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    user_id INT NOT NULL,
    challenge_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_mobile_challenge_hash (challenge_hash),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_user (user_id),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- mobile_sessions
CREATE TABLE IF NOT EXISTS mobile_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    user_id INT NOT NULL,
    platform VARCHAR(20) NOT NULL DEFAULT 'ios',
    device_id VARCHAR(191) NULL,
    device_name VARCHAR(255) NULL,
    app_version VARCHAR(50) NULL,
    access_token_hash CHAR(64) NOT NULL,
    refresh_token_hash CHAR(64) NOT NULL,
    token_prefix VARCHAR(16) NOT NULL,
    access_expires_at DATETIME NOT NULL,
    refresh_expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    last_used_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_mobile_access_token_hash (access_token_hash),
    UNIQUE KEY uniq_mobile_refresh_token_hash (refresh_token_hash),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_user (user_id),
    INDEX idx_device (device_id),
    INDEX idx_access_expires (access_expires_at),
    INDEX idx_refresh_expires (refresh_expires_at),
    INDEX idx_revoked (revoked_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- mobile_idempotency_keys
CREATE TABLE IF NOT EXISTS mobile_idempotency_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    mobile_session_id INT NOT NULL,
    user_id INT NOT NULL,
    idempotency_key VARCHAR(128) NOT NULL,
    action VARCHAR(120) NOT NULL,
    request_hash CHAR(64) NOT NULL,
    response_json MEDIUMTEXT NULL,
    status_code INT DEFAULT 200,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    UNIQUE KEY uniq_mobile_idempotency_session_key (mobile_session_id, action, idempotency_key),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (mobile_session_id) REFERENCES mobile_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- mobile_devices
CREATE TABLE IF NOT EXISTS mobile_devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    user_id INT NOT NULL,
    mobile_session_id INT NULL,
    platform VARCHAR(20) NOT NULL DEFAULT 'ios',
    device_id VARCHAR(191) NULL,
    device_name VARCHAR(255) NULL,
    app_version VARCHAR(50) NULL,
    apns_environment ENUM('sandbox', 'production') NOT NULL DEFAULT 'sandbox',
    apns_token TEXT NOT NULL,
    apns_token_hash CHAR(64) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_mobile_apns_token_hash (apns_token_hash),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_user (user_id),
    INDEX idx_session (mobile_session_id),
    INDEX idx_device (device_id),
    INDEX idx_active (is_active),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (mobile_session_id) REFERENCES mobile_sessions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- notifications
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    user_id INT NOT NULL,
    ticket_id INT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'info',
    actor_id INT NULL,
    data JSON NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    is_resolved TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_user (user_id),
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_notifications_tenant_user (tenant_id, user_id),
    INDEX idx_ticket (ticket_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- push_subscriptions
CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    user_id INT NOT NULL,
    endpoint TEXT NOT NULL,
    p256dh VARCHAR(255) NOT NULL DEFAULT '',
    auth_key VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_push_user (user_id),
    INDEX idx_push_tenant_user (tenant_id, user_id),
    INDEX idx_push_endpoint (endpoint(255)),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- signup_magic_links
CREATE TABLE IF NOT EXISTS signup_magic_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_created (email, created_at),
    INDEX idx_expires_at (expires_at),
    INDEX idx_consumed_at (consumed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- recurring_tasks
CREATE TABLE IF NOT EXISTS recurring_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    ticket_type_id INT,
    organization_id INT,
    assigned_user_id INT,
    priority_id INT,
    status_id INT NOT NULL,
    due_days INT DEFAULT 7,
    recurrence_type ENUM('daily', 'weekly', 'monthly', 'yearly') DEFAULT 'weekly',
    recurrence_interval INT DEFAULT 1,
    recurrence_day_of_week TINYINT,
    recurrence_day_of_month TINYINT,
    recurrence_month TINYINT,
    start_date DATE NOT NULL,
    end_date DATE,
    next_run_date DATETIME,
    last_run_date DATETIME,
    send_email_notification TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    paused_at DATETIME NULL DEFAULT NULL,
    resume_date DATE NULL DEFAULT NULL,
    tags TEXT NULL DEFAULT NULL,
    created_by_user_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_type_id) REFERENCES ticket_types(id) ON DELETE SET NULL,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (priority_id) REFERENCES priorities(id) ON DELETE SET NULL,
    FOREIGN KEY (status_id) REFERENCES statuses(id),
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_active (is_active),
    INDEX idx_next_run (next_run_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- recurring_task_runs
CREATE TABLE IF NOT EXISTS recurring_task_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recurring_task_id INT NOT NULL,
    ticket_id INT NULL,
    status ENUM('success','failed') DEFAULT 'success',
    error_message TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_task_id (recurring_task_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- report_templates
CREATE TABLE IF NOT EXISTS report_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    uuid CHAR(36) NOT NULL UNIQUE,
    organization_id INT NOT NULL,
    created_by_user_id INT,
    title VARCHAR(255) NOT NULL,
    report_language VARCHAR(5) DEFAULT 'en',
    date_from DATE NOT NULL,
    date_to DATE NOT NULL,
    executive_summary TEXT,
    show_financials TINYINT(1) DEFAULT 1,
    show_team_attribution TINYINT(1) DEFAULT 1,
    show_cost_breakdown TINYINT(1) DEFAULT 0,
    custom_billable_rate DECIMAL(10,2) NULL DEFAULT NULL,
    tags TEXT NULL DEFAULT NULL,
    agent_ids TEXT NULL DEFAULT NULL,
    group_by VARCHAR(50) DEFAULT 'none',
    rounding_minutes INT DEFAULT 15,
    theme_color VARCHAR(50),
    hide_branding TINYINT(1) DEFAULT 0,
    is_draft TINYINT(1) DEFAULT 1,
    is_archived TINYINT(1) DEFAULT 0,
    expires_at DATETIME NULL,
    schedule_enabled TINYINT(1) NOT NULL DEFAULT 0,
    schedule_interval VARCHAR(20) NOT NULL DEFAULT 'monthly',
    schedule_day INT NOT NULL DEFAULT 1,
    schedule_recipients TEXT NULL,
    schedule_last_sent DATETIME NULL,
    schedule_next_due DATE NULL,
    last_generated_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_org (organization_id),
    INDEX idx_uuid (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- report_shares
CREATE TABLE IF NOT EXISTS report_shares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    organization_id INT NOT NULL,
    report_template_id INT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    share_secret VARCHAR(64) NULL,
    created_by INT,
    expires_at DATETIME,
    is_revoked TINYINT(1) DEFAULT 0,
    last_accessed_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_org (organization_id),
    INDEX idx_report_template (report_template_id),
    INDEX idx_revoked (is_revoked),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ticket_shares
CREATE TABLE IF NOT EXISTS ticket_shares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    ticket_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    created_by INT,
    expires_at DATETIME,
    is_revoked TINYINT(1) DEFAULT 0,
    last_accessed_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_ticket (ticket_id),
    INDEX idx_revoked (is_revoked),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ticket_history
CREATE TABLE IF NOT EXISTS ticket_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    old_value TEXT,
    new_value TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ticket (ticket_id),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at),
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- app_sessions
CREATE TABLE IF NOT EXISTS app_sessions (
    id VARCHAR(128) NOT NULL PRIMARY KEY,
    session_data MEDIUMBLOB NOT NULL,
    last_activity INT UNSIGNED NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- page_views
CREATE TABLE IF NOT EXISTS page_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    user_id INT NOT NULL,
    page VARCHAR(50) NOT NULL,
    section VARCHAR(50) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_page (page),
    INDEX idx_created (created_at),
    INDEX idx_user_page (user_id, page)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- agent_client_billable_rates
CREATE TABLE IF NOT EXISTS agent_client_billable_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    organization_id INT NOT NULL,
    user_id INT NOT NULL,
    billable_rate DECIMAL(10,2) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_agent_client_rate (tenant_id, organization_id, user_id),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_organization (organization_id),
    INDEX idx_user (user_id),
    INDEX idx_active (is_active),
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
