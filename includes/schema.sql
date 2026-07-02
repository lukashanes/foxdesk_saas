-- FoxDesk Database Schema
-- Compatible with MySQL 5.7+ and MariaDB 10.2+

-- Tenants table
CREATE TABLE IF NOT EXISTS tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    primary_domain VARCHAR(255) NULL,
    plan VARCHAR(50) NOT NULL DEFAULT 'cloud',
    status ENUM('active', 'trialing', 'past_due', 'trial_expired', 'suspended', 'blocked', 'canceled') NOT NULL DEFAULT 'active',
    owner_user_id INT NULL,
    billing_email VARCHAR(255) NULL,
    stripe_customer_id VARCHAR(255) NULL,
    stripe_subscription_id VARCHAR(255) NULL,
    subscription_status VARCHAR(50) NOT NULL DEFAULT 'manual',
    max_users INT NOT NULL DEFAULT 1000000,
    max_agents INT NOT NULL DEFAULT 1000000,
    trial_ends_at DATETIME NULL,
    suspended_at DATETIME NULL,
    blocked_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_subscription_status (subscription_status),
    INDEX idx_domain (primary_domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- Organizations table
CREATE TABLE IF NOT EXISTS organizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    name VARCHAR(255) NOT NULL,
    ico VARCHAR(20),
    address TEXT,
    contact_email VARCHAR(255),
    contact_phone VARCHAR(50),
    notes TEXT,
    logo TEXT,
    billable_rate DECIMAL(10,2) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_name (name),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100),
    contact_phone VARCHAR(50),
    notes TEXT,
    role ENUM('user', 'agent', 'admin') DEFAULT 'user',
    is_platform_admin TINYINT(1) NOT NULL DEFAULT 0,
    cost_rate DECIMAL(10,2) DEFAULT 0,
    billable_rate DECIMAL(10,2) DEFAULT 0,
    permissions TEXT,
    dashboard_layout TEXT NULL,
    organization_id INT,
    avatar TEXT,
    language VARCHAR(5) DEFAULT 'en',
    email_notifications_enabled TINYINT(1) DEFAULT 1,
    in_app_notifications_enabled TINYINT(1) DEFAULT 1,
    in_app_sound_enabled TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    deleted_at DATETIME NULL,
    reset_token VARCHAR(100),
    reset_token_expires DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    totp_secret VARCHAR(64) NULL,
    totp_enabled TINYINT(1) DEFAULT 0,
    totp_backup_codes TEXT NULL,
    last_notifications_seen_at DATETIME NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL,
    INDEX idx_email (email),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_role (role),
    INDEX idx_platform_admin (is_platform_admin),
    INDEX idx_reset_token (reset_token),
    INDEX idx_organization (organization_id),
    INDEX idx_name (first_name, last_name),
    INDEX idx_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email-only SaaS signup magic links
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

-- Statuses table
CREATE TABLE IF NOT EXISTS statuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE,
    color VARCHAR(7) DEFAULT '#0a84ff',
    sort_order INT DEFAULT 0,
    is_default TINYINT(1) DEFAULT 0,
    is_closed TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Priorities table
CREATE TABLE IF NOT EXISTS priorities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE,
    color VARCHAR(7) DEFAULT '#0a84ff',
    icon VARCHAR(50) DEFAULT 'fa-flag',
    sort_order INT DEFAULT 0,
    is_default TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket types table
CREATE TABLE IF NOT EXISTS ticket_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE,
    icon VARCHAR(50) DEFAULT 'fa-file-alt',
    color VARCHAR(7) DEFAULT '#0a84ff',
    sort_order INT DEFAULT 0,
    is_default TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tickets table
CREATE TABLE IF NOT EXISTS tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    hash VARCHAR(16) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    type VARCHAR(100) DEFAULT 'general',
    priority_id INT,
    user_id INT NOT NULL,
    organization_id INT,
    status_id INT NOT NULL,
    ticket_type_id INT,
    source VARCHAR(20) DEFAULT 'web',
    is_archived TINYINT(1) DEFAULT 0,
    assignee_id INT,
    due_date DATETIME,
    custom_billable_rate DECIMAL(10,2) NULL DEFAULT NULL,
    tags TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL,
    FOREIGN KEY (status_id) REFERENCES statuses(id),
    FOREIGN KEY (priority_id) REFERENCES priorities(id) ON DELETE SET NULL,
    FOREIGN KEY (assignee_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (ticket_type_id) REFERENCES ticket_types(id) ON DELETE SET NULL,
    UNIQUE INDEX idx_hash (hash),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_user (user_id),
    INDEX idx_organization (organization_id),
    INDEX idx_status (status_id),
    INDEX idx_priority (priority_id),
    INDEX idx_type (type),
    INDEX idx_archived (is_archived),
    INDEX idx_created (created_at),
    INDEX idx_assignee (assignee_id),
    INDEX idx_due_date (due_date),
    INDEX idx_ticket_type (ticket_type_id),
    INDEX idx_source (source),
    INDEX idx_updated (updated_at),
    FULLTEXT INDEX idx_ticket_search (title, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comments table
CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    is_internal TINYINT(1) DEFAULT 0,
    time_spent INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_ticket (ticket_id),
    INDEX idx_user (user_id),
    INDEX idx_ticket_created (ticket_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket time tracking entries
CREATE TABLE IF NOT EXISTS ticket_time_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    comment_id INT,
    started_at DATETIME NOT NULL,
    ended_at DATETIME DEFAULT NULL,
    paused_at DATETIME DEFAULT NULL,
    paused_seconds INT DEFAULT 0,
    duration_minutes INT DEFAULT 0,
    is_billable TINYINT(1) DEFAULT 1,
    billable_rate DECIMAL(10,2) DEFAULT 0,
    cost_rate DECIMAL(10,2) DEFAULT 0,
    is_manual TINYINT(1) DEFAULT 0,
    summary TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE SET NULL,
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_ticket (ticket_id),
    INDEX idx_user (user_id),
    INDEX idx_comment (comment_id),
    INDEX idx_started (started_at),
    INDEX idx_ended (ended_at),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-agent/client billable rate overrides for time reports
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

-- Attachments table
CREATE TABLE IF NOT EXISTS attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    ticket_id INT NOT NULL,
    comment_id INT,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100),
    file_size BIGINT,
    storage_driver VARCHAR(20) NOT NULL DEFAULT 'local',
    storage_bucket VARCHAR(255) NULL,
    storage_key VARCHAR(700) NULL,
    uploaded_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_ticket (ticket_id),
    INDEX idx_comment (comment_id),
    INDEX idx_uploaded_by (uploaded_by),
    INDEX idx_storage_key (storage_key(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket shares table (public links)
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

-- Report shares table (public links)
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

-- Ticket access table (internal shared access)
CREATE TABLE IF NOT EXISTS ticket_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_ticket_user (ticket_id, user_id),
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_ticket (ticket_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity log table
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    ticket_id INT NOT NULL,
    user_id INT,
    action VARCHAR(50) NOT NULL,
    details TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_ticket (ticket_id),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate limits table
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rate_key VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    window_start DATETIME NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_rate_ip (rate_key, ip_address),
    INDEX idx_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Security log table
CREATE TABLE IF NOT EXISTS security_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    event_type VARCHAR(50) NOT NULL,
    user_id INT NULL,
    ip_address VARCHAR(45) NOT NULL,
    context TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event (event_type),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings table
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email templates table
CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(100) NOT NULL,
    language VARCHAR(5) DEFAULT 'en',
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_key_lang (template_key, language),
    INDEX idx_key (template_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recurring tasks table
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

-- Report templates table
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
    last_generated_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_org (organization_id),
    INDEX idx_uuid (uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Report snapshots table
CREATE TABLE IF NOT EXISTS report_snapshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    report_template_id INT NOT NULL,
    kpi_data JSON,
    chart_data JSON,
    generation_time_ms INT,
    generated_by_user_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_template_id) REFERENCES report_templates(id) ON DELETE CASCADE,
    FOREIGN KEY (generated_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_template (report_template_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Debug log table
CREATE TABLE IF NOT EXISTS debug_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    channel VARCHAR(50) DEFAULT 'general',
    level VARCHAR(20) DEFAULT 'info',
    message TEXT,
    context JSON,
    user_id INT,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_channel (channel),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_level (level),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Page views table (lightweight user activity tracking)
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

-- Allowed inbound senders table
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

-- Ticket messages from email ingest
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

-- Email message attachment metadata mapping
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

-- Ingest logs for idempotency and troubleshooting
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

-- Optional fast checkpoint per mailbox
CREATE TABLE IF NOT EXISTS email_ingest_state (
    mailbox VARCHAR(120) PRIMARY KEY,
    last_seen_uid INT DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API tokens for agent/automation access
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

-- Native mobile login challenges for TOTP verification
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

-- Native mobile sessions for iOS and future mobile clients
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

-- Native mobile device records for APNs registration
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

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    user_id INT NOT NULL,
    ticket_id INT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'info',
    actor_id INT NULL,
    data JSON NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_user (user_id),
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_ticket (ticket_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Browser push subscriptions
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

-- Persistent application sessions
CREATE TABLE IF NOT EXISTS app_sessions (
    id VARCHAR(128) NOT NULL PRIMARY KEY,
    session_data MEDIUMBLOB NOT NULL,
    last_activity INT UNSIGNED NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
