<?php
/** Builds the section-specific view state for the SaaS settings page. */
$incoming_mail_logs = [];
$incoming_mail_log_error = '';
$workspace_inbound_address = '';
$workspace_public_inbound_address = '';
$workspace_inbound_domain = '';
$workspace_inbound_enabled = false;
$email_surface = settings_email_support_surface($settings);

$imap_enabled_default = (defined('IMAP_ENABLED') && IMAP_ENABLED)
    || (
        (defined('IMAP_HOST') && trim((string) IMAP_HOST) !== '') &&
        (defined('IMAP_USERNAME') && trim((string) IMAP_USERNAME) !== '')
    );
$imap_view = [
    'enabled' => $settings['imap_enabled'] ?? ($imap_enabled_default ? '1' : '0'),
    'host' => $settings['imap_host'] ?? (defined('IMAP_HOST') ? (string) IMAP_HOST : ''),
    'port' => $settings['imap_port'] ?? (defined('IMAP_PORT') ? (string) IMAP_PORT : '993'),
    'encryption' => $settings['imap_encryption'] ?? (defined('IMAP_ENCRYPTION') ? strtolower((string) IMAP_ENCRYPTION) : 'ssl'),
    'username' => $settings['imap_username'] ?? (defined('IMAP_USERNAME') ? (string) IMAP_USERNAME : ''),
    'password_set' => !empty($settings['imap_password']) || (defined('IMAP_PASSWORD') && trim((string) IMAP_PASSWORD) !== ''),
    'folder' => $settings['imap_folder'] ?? (defined('IMAP_FOLDER') ? (string) IMAP_FOLDER : 'INBOX'),
    'processed_folder' => $settings['imap_processed_folder'] ?? (defined('IMAP_PROCESSED_FOLDER') ? (string) IMAP_PROCESSED_FOLDER : 'Processed'),
    'failed_folder' => $settings['imap_failed_folder'] ?? (defined('IMAP_FAILED_FOLDER') ? (string) IMAP_FAILED_FOLDER : 'Failed'),
    'max_emails_per_run' => $settings['imap_max_emails_per_run'] ?? (defined('IMAP_MAX_EMAILS_PER_RUN') ? (string) IMAP_MAX_EMAILS_PER_RUN : '50'),
    'max_attachment_size_mb' => $settings['imap_max_attachment_size_mb'] ?? (string) ((int) ((defined('IMAP_MAX_ATTACHMENT_SIZE') ? (int) IMAP_MAX_ATTACHMENT_SIZE : 10485760) / 1048576)),
    'validate_cert' => $settings['imap_validate_cert'] ?? (defined('IMAP_VALIDATE_CERT') && IMAP_VALIDATE_CERT ? '1' : '0'),
    'mark_seen_on_skip' => $settings['imap_mark_seen_on_skip'] ?? (defined('IMAP_MARK_SEEN_ON_SKIP') && IMAP_MARK_SEEN_ON_SKIP ? '1' : '0'),
    'allow_unknown_senders' => $settings['imap_allow_unknown_senders'] ?? '0',
    'storage_base' => $settings['imap_storage_base'] ?? (defined('IMAP_STORAGE_BASE') ? (string) IMAP_STORAGE_BASE : 'storage/tickets'),
];
$imap_extension_loaded = extension_loaded('imap') && function_exists('imap_open');

if ($tab === 'email') {
    require_once BASE_PATH . '/includes/email-routing-functions.php';
    require_once BASE_PATH . '/includes/email-ingest-functions.php';
    $workspace_inbound_enabled = function_exists('foxdesk_cloudflare_email_ingest_enabled')
        && foxdesk_cloudflare_email_ingest_enabled();
    if ($workspace_inbound_enabled && function_exists('foxdesk_workspace_inbound_address')) {
        $workspace_tenant = ['id' => current_tenant_id()];
        try {
            if (function_exists('db_fetch_one') && (!function_exists('table_exists') || table_exists('tenants'))) {
                $tenant_row = db_fetch_one("SELECT id, slug FROM tenants WHERE id = ? LIMIT 1", [current_tenant_id()]);
                if (!empty($tenant_row)) {
                    $workspace_tenant = $tenant_row;
                }
            }
        } catch (Throwable $e) {
            $workspace_tenant = ['id' => current_tenant_id()];
        }
        $workspace_inbound_address = foxdesk_workspace_inbound_address($workspace_tenant);
        if (function_exists('foxdesk_workspace_public_inbound_address')) {
            $workspace_public_inbound_address = foxdesk_workspace_public_inbound_address($workspace_tenant);
        }
        $workspace_inbound_domain = function_exists('foxdesk_ticket_email_domain') ? foxdesk_ticket_email_domain() : '';
    }
    $email_surface = settings_email_support_surface($settings, $workspace_public_inbound_address);

    if (($email_surface['type'] ?? '') !== 'managed') {
        try {
        email_ingest_ensure_schema();
        $incoming_mail_logs = db_fetch_all("
            SELECT
                l.created_at,
                l.mailbox,
                l.uid,
                l.status,
                l.reason,
                l.error,
                COALESCE(l.sender_email, tm.sender_email) AS sender_email,
                COALESCE(l.subject, tm.subject) AS subject,
                COALESCE(l.ticket_id, tm.ticket_id) AS ticket_id,
                t.hash AS ticket_hash,
                t.title AS ticket_title
            FROM email_ingest_logs l
            LEFT JOIN ticket_messages tm
                ON tm.id = (
                    SELECT tm2.id
                    FROM ticket_messages tm2
                    WHERE (tm2.mailbox = l.mailbox AND tm2.uid = l.uid)
                       OR (l.message_id IS NOT NULL AND l.message_id <> '' AND tm2.message_id = l.message_id)
                    ORDER BY tm2.id DESC
                    LIMIT 1
                )
            LEFT JOIN tickets t ON t.id = COALESCE(l.ticket_id, tm.ticket_id)
            ORDER BY l.created_at DESC
            LIMIT 100
        ");
        } catch (Throwable $e) {
            $incoming_mail_log_error = $e->getMessage();
        }
    }

    // Load allowed senders for the allowlist UI
	    try {
	        $allowed_senders = db_fetch_all(
	            "SELECT s.*, CONCAT(u.first_name, ' ', u.last_name) AS user_name
	             FROM allowed_senders s
	             LEFT JOIN users u ON s.user_id = u.id AND u.tenant_id = s.tenant_id
	             WHERE s.tenant_id = ?
	             ORDER BY s.type, s.value",
	            [current_tenant_id()]
	        );
	    } catch (Throwable $e) {
	        $allowed_senders = [];
	    }
	    $all_users = db_fetch_all("SELECT id, first_name, last_name, email FROM users WHERE is_active = 1 AND tenant_id = ? ORDER BY first_name, last_name", [current_tenant_id()]);
}

$api_scope_catalog = [];
$profile_api_tokens = [];
$new_profile_api_token = null;
$new_profile_api_token_scopes = [];
$api_permission_presets = [];
$api_agents_available = false;
$api_ai_agents = [];
$api_ai_agent_tokens = [];
$ai_agent_token_scope_groups = [];
$ai_agent_token_default_scope_groups = [];
$ai_agent_token_group_scopes = [];
$new_ai_token = null;
$new_ai_agent_id = null;
$settings_api_base_url = '';
$api_organizations = [];
$organization_names_by_id = [];
$ai_agent_col_exists = false;

if ($tab === 'api') {
    $settings_api_user = current_user() ?: [];
    $api_scope_catalog = function_exists('api_token_scope_catalog') ? api_token_scope_catalog($settings_api_user) : [];
    $api_permission_presets = settings_api_scope_presets($api_scope_catalog);
    $profile_api_tokens = settings_fetch_user_api_tokens((int) ($settings_api_user['id'] ?? 0));
    $new_profile_api_token = $_SESSION['new_profile_api_token'] ?? null;
    $new_profile_api_token_scopes = $_SESSION['new_profile_api_token_scopes'] ?? [];
    unset($_SESSION['new_profile_api_token'], $_SESSION['new_profile_api_token_scopes']);
    if (defined('APP_URL') && (string) APP_URL !== '') {
        $settings_api_base_url = rtrim((string) APP_URL, '/');
    } elseif ((string) getenv('APP_URL') !== '') {
        $settings_api_base_url = rtrim((string) getenv('APP_URL'), '/');
    } else {
        $settings_api_scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $settings_api_host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $settings_api_path = rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/\\');
        $settings_api_base_url = $settings_api_scheme . '://' . $settings_api_host . ($settings_api_path === '' || $settings_api_path === '.' ? '' : $settings_api_path);
    }

    $api_user_table_capabilities = function_exists('team_users_table_capabilities') ? team_users_table_capabilities() : [];
    $ai_agent_col_exists = !empty($api_user_table_capabilities['ai_agent']);
    $api_agents_available = $ai_agent_col_exists;
    try {
        $api_organizations = get_organizations(true);
    } catch (Throwable $e) {
        $api_organizations = [];
    }
    foreach ($api_organizations as $organization) {
        $organization_names_by_id[(int) ($organization['id'] ?? 0)] = (string) ($organization['name'] ?? '');
    }

    if ($api_agents_available) {
        $api_ai_agents = team_ai_agents_fetch(!empty($api_user_table_capabilities['deleted_at']));
        $api_ai_agent_tokens = team_ai_agent_tokens_fetch($api_ai_agents);
        $ai_agent_token_scope_groups = function_exists('team_ai_agent_token_scope_groups') ? team_ai_agent_token_scope_groups() : [];
        $ai_agent_token_default_scope_groups = function_exists('team_ai_agent_token_default_scope_groups') ? team_ai_agent_token_default_scope_groups() : [];
        foreach ($ai_agent_token_scope_groups as $group_key => $group) {
            $ai_agent_token_group_scopes[$group_key] = $group['scopes'] ?? [];
        }
    }

    $new_ai_token = $_SESSION['new_ai_agent_token'] ?? null;
    $new_ai_agent_id = $_SESSION['new_ai_agent_id'] ?? null;
    unset($_SESSION['new_ai_agent_token'], $_SESSION['new_ai_agent_id']);
}

// Get template language
$template_lang = strtolower(trim((string) ($_GET['lang'] ?? 'en')));
if (!in_array($template_lang, ['en', 'cs', 'de', 'it', 'es'], true)) {
    $template_lang = 'en';
}

// Get email templates for selected language
try {
    $templates = db_fetch_all("
        SELECT t.*
        FROM email_templates t
        WHERE t.language = ?
        ORDER BY t.template_key
    ", [$template_lang]);

    // If we have missing templates for this language, we might want to show defaults from English or code
    // But for now, let's just show what's in DB.
} catch (Exception $e) {
    $templates = [];
}

function settings_section_partial(string $tab): string
{
    $sections = [
        'general' => 'general.php',
        'api' => 'api.php',
        'email' => 'email.php',
        'templates' => 'templates.php',
        'workflow' => 'workflow.php',
        'system' => 'system.php',
        'logs' => 'logs.php',
        'security' => 'security.php',
    ];

    return BASE_PATH . '/includes/modules/settings/views/' . ($sections[$tab] ?? $sections['general']);
}
