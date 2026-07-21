CREATE TABLE IF NOT EXISTS pending_deletions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    user_id INT NOT NULL,
    ticket_id INT NOT NULL,
    resource_type VARCHAR(32) NOT NULL,
    resource_id BIGINT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    payload_json MEDIUMTEXT NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_pending_deletion_token (token_hash),
    INDEX idx_pending_deletion_tenant_expiry (tenant_id, expires_at),
    INDEX idx_pending_deletion_ticket (ticket_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
