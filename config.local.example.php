<?php
/**
 * Local Docker configuration for FoxDesk SaaS development.
 */

define('DB_HOST', 'db');
define('DB_PORT', '3306');
define('DB_NAME', 'foxdesk_saas');
define('DB_USER', 'foxdesk');
define('DB_PASS', 'foxpass');

define('SECRET_KEY', 'local_dev_replace_with_64_hex_chars_before_public_use');

define('APP_NAME', 'FoxDesk SaaS Local');
define('APP_URL', 'http://127.0.0.1:8090');
define('APP_DEBUG', true);
define('TRUST_PROXY', false);

define('BILLING_ENABLED', false);
define('STRIPE_SECRET_KEY', '');
define('STRIPE_WEBHOOK_SECRET', 'whsec_test');
define('STRIPE_PRICE_CLOUD_BASE', '');
define('STRIPE_PRICE_STORAGE_OVERAGE', '');
define('STRIPE_STORAGE_METER_EVENT_NAME', 'foxdesk_storage_extra_gb');
define('BILLING_CURRENCY', 'EUR');
define('BILLING_CLOUD_BASE_PRICE_CENTS', 1900);
define('BILLING_STORAGE_OVERAGE_PRICE_CENTS', 79);
define('BILLING_INCLUDED_STORAGE_BYTES', 1073741824);
define('STRIPE_SUCCESS_URL', APP_URL . '/index.php?page=platform&billing=success');
define('STRIPE_CANCEL_URL', APP_URL . '/index.php?page=platform&billing=cancelled');

define('MAIL_PROVIDER', 'php_mail');
define('CLOUDFLARE_ACCOUNT_ID', '');
define('CLOUDFLARE_EMAIL_API_TOKEN', '');
define('CLOUDFLARE_EMAIL_FROM', 'noreply@foxdesk.net');
define('CLOUDFLARE_EMAIL_FROM_NAME', 'FoxDesk Local');
define('CLOUDFLARE_EMAIL_REPLY_TO', 'support@foxdesk.net');

define('IMAP_ENABLED', false);
define('IMAP_HOST', '');
define('IMAP_PORT', 993);
define('IMAP_ENCRYPTION', 'ssl');
define('IMAP_VALIDATE_CERT', false);
define('IMAP_USERNAME', '');
define('IMAP_PASSWORD', '');
define('IMAP_FOLDER', 'INBOX');
define('IMAP_PROCESSED_FOLDER', 'Processed');
define('IMAP_FAILED_FOLDER', 'Failed');
define('IMAP_MAX_EMAILS_PER_RUN', 50);
define('IMAP_MAX_ATTACHMENT_SIZE', 10 * 1024 * 1024);
define('IMAP_DENY_EXTENSIONS', 'php,phtml,php3,php4,php5,phar,exe,bat,cmd,js,vbs,ps1,sh');
define('IMAP_STORAGE_BASE', 'storage/tickets');
define('IMAP_MARK_SEEN_ON_SKIP', true);
define('IMAP_ALLOW_UNKNOWN_SENDERS', false);

define('STORAGE_DRIVER', 'local');
define('R2_BUCKET', '');
define('R2_ENDPOINT', '');
define('R2_ACCESS_KEY_ID', '');
define('R2_SECRET_ACCESS_KEY', '');

define('UPLOAD_DIR', 'uploads/');
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024);

date_default_timezone_set('Europe/Prague');
