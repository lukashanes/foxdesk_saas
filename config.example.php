<?php
/**
 * FoxDesk - Configuration
 * Sample configuration template
 */

// Shared hosting/FTP usually uses localhost here.
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');

define('SECRET_KEY', 'generate_64_hex_secret_here');

define('APP_NAME', 'FoxDesk');
define('APP_URL', 'https://your-domain.tld');
// define('APP_DEBUG', false); // Set true to enable debug mode
// define('TRUST_PROXY', false); // Set true only when FoxDesk is behind your trusted reverse proxy.

// SaaS billing with Stripe Billing + Checkout.
define('BILLING_ENABLED', false);
define('STRIPE_SECRET_KEY', '');
define('STRIPE_WEBHOOK_SECRET', '');
define('STRIPE_PRICE_CLOUD_BASE', '');
define('STRIPE_PRICE_STORAGE_OVERAGE', '');
define('STRIPE_STORAGE_METER_EVENT_NAME', 'foxdesk_storage_extra_gb');
define('BILLING_CURRENCY', 'EUR');
define('BILLING_CLOUD_BASE_PRICE_CENTS', 1900);
define('BILLING_STORAGE_OVERAGE_PRICE_CENTS', 79);
define('BILLING_INCLUDED_STORAGE_BYTES', 1073741824);
define('STRIPE_SUCCESS_URL', APP_URL . '/index.php?page=platform&billing=success');
define('STRIPE_CANCEL_URL', APP_URL . '/index.php?page=platform&billing=cancelled');

// Incoming email ingest (IMAP)
define('IMAP_ENABLED', false);
define('IMAP_HOST', '');
define('IMAP_PORT', 993);
define('IMAP_ENCRYPTION', 'ssl'); // ssl|tls|none
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

define('UPLOAD_DIR', 'uploads/');
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024);

date_default_timezone_set('Europe/Prague');
