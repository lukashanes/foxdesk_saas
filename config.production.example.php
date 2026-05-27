<?php
/**
 * FoxDesk SaaS production config template.
 *
 * Copy to config.php on the server. Secrets are read from the container
 * environment loaded by .env.production.
 */

function foxdesk_env(string $name, $default = '')
{
    $value = getenv($name);
    return $value !== false && $value !== '' ? $value : $default;
}

function foxdesk_env_bool(string $name, bool $default = false): bool
{
    $value = foxdesk_env($name, $default ? 'true' : 'false');
    return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
}

define('DB_HOST', foxdesk_env('DB_HOST', 'db'));
define('DB_PORT', foxdesk_env('DB_PORT', '3306'));
define('DB_NAME', foxdesk_env('DB_NAME', 'foxdesk_saas'));
define('DB_USER', foxdesk_env('DB_USER', 'foxdesk'));
define('DB_PASS', foxdesk_env('DB_PASS'));

define('SECRET_KEY', foxdesk_env('SECRET_KEY'));

define('APP_NAME', foxdesk_env('APP_NAME', 'FoxDesk'));
define('APP_URL', rtrim(foxdesk_env('APP_URL', 'https://app.foxdesk.net'), '/'));
define('APP_DEBUG', foxdesk_env_bool('APP_DEBUG', false));
define('TRUST_PROXY', foxdesk_env_bool('TRUST_PROXY', true));

define('BILLING_ENABLED', foxdesk_env_bool('BILLING_ENABLED', false));
define('STRIPE_SECRET_KEY', foxdesk_env('STRIPE_SECRET_KEY'));
define('STRIPE_WEBHOOK_SECRET', foxdesk_env('STRIPE_WEBHOOK_SECRET'));
define('STRIPE_PRICE_CLOUD_BASE', foxdesk_env('STRIPE_PRICE_CLOUD_BASE'));
define('STRIPE_PRICE_STORAGE_OVERAGE', foxdesk_env('STRIPE_PRICE_STORAGE_OVERAGE'));
define('STRIPE_STORAGE_METER_EVENT_NAME', foxdesk_env('STRIPE_STORAGE_METER_EVENT_NAME', 'foxdesk_storage_extra_gb'));
define('BILLING_CURRENCY', foxdesk_env('BILLING_CURRENCY', 'EUR'));
define('BILLING_CLOUD_BASE_PRICE_CENTS', (int) foxdesk_env('BILLING_CLOUD_BASE_PRICE_CENTS', 990));
define('BILLING_STORAGE_OVERAGE_PRICE_CENTS', (int) foxdesk_env('BILLING_STORAGE_OVERAGE_PRICE_CENTS', 190));
define('BILLING_INCLUDED_STORAGE_BYTES', (int) foxdesk_env('BILLING_INCLUDED_STORAGE_BYTES', 1073741824));
define('BILLING_TRIAL_DAYS', (int) foxdesk_env('BILLING_TRIAL_DAYS', 14));
define('STRIPE_SUCCESS_URL', APP_URL . '/index.php?page=billing&checkout=success');
define('STRIPE_CANCEL_URL', APP_URL . '/index.php?page=billing&checkout=cancelled');

define('MAIL_PROVIDER', foxdesk_env('MAIL_PROVIDER', 'cloudflare'));
define('CLOUDFLARE_ACCOUNT_ID', foxdesk_env('CLOUDFLARE_ACCOUNT_ID'));
define('CLOUDFLARE_EMAIL_API_TOKEN', foxdesk_env('CLOUDFLARE_EMAIL_API_TOKEN'));
define('CLOUDFLARE_EMAIL_FROM', foxdesk_env('CLOUDFLARE_EMAIL_FROM', 'noreply@foxdesk.net'));
define('CLOUDFLARE_EMAIL_FROM_NAME', foxdesk_env('CLOUDFLARE_EMAIL_FROM_NAME', 'FoxDesk'));
define('CLOUDFLARE_EMAIL_REPLY_TO', foxdesk_env('CLOUDFLARE_EMAIL_REPLY_TO', 'support@foxdesk.net'));

define('IMAP_ENABLED', foxdesk_env_bool('IMAP_ENABLED', false));
define('IMAP_HOST', foxdesk_env('IMAP_HOST'));
define('IMAP_PORT', (int) foxdesk_env('IMAP_PORT', 993));
define('IMAP_ENCRYPTION', foxdesk_env('IMAP_ENCRYPTION', 'ssl'));
define('IMAP_VALIDATE_CERT', foxdesk_env_bool('IMAP_VALIDATE_CERT', true));
define('IMAP_USERNAME', foxdesk_env('IMAP_USERNAME'));
define('IMAP_PASSWORD', foxdesk_env('IMAP_PASSWORD'));
define('IMAP_FOLDER', foxdesk_env('IMAP_FOLDER', 'INBOX'));
define('IMAP_PROCESSED_FOLDER', foxdesk_env('IMAP_PROCESSED_FOLDER', 'Processed'));
define('IMAP_FAILED_FOLDER', foxdesk_env('IMAP_FAILED_FOLDER', 'Failed'));
define('IMAP_MAX_EMAILS_PER_RUN', (int) foxdesk_env('IMAP_MAX_EMAILS_PER_RUN', 50));
define('IMAP_MAX_ATTACHMENT_SIZE', (int) foxdesk_env('IMAP_MAX_ATTACHMENT_SIZE', 10 * 1024 * 1024));
define('IMAP_DENY_EXTENSIONS', foxdesk_env('IMAP_DENY_EXTENSIONS', 'php,phtml,php3,php4,php5,phar,exe,bat,cmd,js,vbs,ps1,sh'));
define('IMAP_STORAGE_BASE', foxdesk_env('IMAP_STORAGE_BASE', 'storage/tickets'));
define('IMAP_MARK_SEEN_ON_SKIP', foxdesk_env_bool('IMAP_MARK_SEEN_ON_SKIP', true));
define('IMAP_ALLOW_UNKNOWN_SENDERS', foxdesk_env_bool('IMAP_ALLOW_UNKNOWN_SENDERS', false));

define('STORAGE_DRIVER', foxdesk_env('STORAGE_DRIVER', 'local'));
define('R2_BUCKET', foxdesk_env('R2_BUCKET'));
define('R2_ENDPOINT', foxdesk_env('R2_ENDPOINT'));
define('R2_ACCESS_KEY_ID', foxdesk_env('R2_ACCESS_KEY_ID'));
define('R2_SECRET_ACCESS_KEY', foxdesk_env('R2_SECRET_ACCESS_KEY'));

define('UPLOAD_DIR', foxdesk_env('UPLOAD_DIR', 'uploads/'));
define('MAX_UPLOAD_SIZE', (int) foxdesk_env('MAX_UPLOAD_SIZE', 10 * 1024 * 1024));

date_default_timezone_set(foxdesk_env('APP_TIMEZONE', 'Europe/Prague'));
