<?php

$root = dirname(__DIR__);
require_once __DIR__ . '/support/settings-source.php';

function assert_product_copy(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function read_product_file(string $root, string $path): string
{
    $contents = file_get_contents($root . '/' . $path);
    assert_product_copy($contents !== false, 'Unable to read ' . $path);
    return $contents;
}

$customer_files = [
    'pages/cloud.php',
    'pages/billing.php',
    'pages/work.php',
    'pages/inbox.php',
    'includes/components/workspace-surface.php',
    'includes/modules/reports/reporting-flow.php',
];

foreach ($customer_files as $path) {
    $contents = read_product_file($root, $path);
    assert_product_copy(
        !preg_match('/[ěščřžýáíéůúďťňĚŠČŘŽÝÁÍÉŮÚĎŤŇ]/u', $contents),
        'Customer-facing source copy must stay English outside includes/lang: ' . $path
    );
}

$cloud = read_product_file($root, 'pages/cloud.php');
assert_product_copy(str_contains($cloud, 'Helpdesk & time tracking.'), 'Public SaaS front must lead with clear product category copy.');
assert_product_copy(!str_contains($cloud, 'Support work in one place.'), 'Public SaaS front must not use generic one-place copy.');
assert_product_copy(!str_contains($cloud, 'Simple price'), 'Public SaaS front must not use weak pricing copy.');
assert_product_copy(str_contains($cloud, 'Regular price EUR 19.90/month'), 'Public pricing must show the regular public price.');
assert_product_copy(str_contains($cloud, 'Introductory price EUR 9.90/month'), 'Public pricing must show the introductory price.');
assert_product_copy(str_contains($cloud, 'Join during the introductory period and keep EUR 9.90/month.'), 'Public pricing must explain that the introductory price is retained for the active workspace.');
assert_product_copy(str_contains($cloud, 'One workspace. Unlimited seats, clients, tickets and team members.'), 'Public pricing must use the compact plan scope copy.');
assert_product_copy(!str_contains($cloud, 'Launch price until'), 'Public pricing must not show a dated launch deadline.');
assert_product_copy(!str_contains($cloud, 'May 31, 2026'), 'Public pricing must not show the expired launch date.');
assert_product_copy(!str_contains($cloud, 'Then '), 'Public pricing must not imply an automatic move to a future price.');
assert_product_copy(!str_contains($cloud, 'EUR 19.80'), 'Public pricing must not expose the old future price.');
assert_product_copy(!str_contains($cloud, 'One workspace, one invoice, unlimited seats.'), 'Public pricing must not use the old invoice-focused scope copy.');
assert_product_copy(!str_contains($cloud, 'Excl. VAT'), 'Public pricing must not show VAT implementation copy.');
assert_product_copy(!str_contains($cloud, 'Valid EU VAT IDs'), 'Public pricing must not show VAT implementation copy.');
assert_product_copy(!str_contains($cloud, 'Open-source FoxDesk remains available'), 'Public footer must not include the old self-hosted explanatory sentence.');
assert_product_copy(!str_contains($cloud, 'FoxDesk Cloud runs at'), 'Public footer must not include the old app host explanatory sentence.');
assert_product_copy(str_contains($cloud, '<h2>One plan. No per-seat math.</h2>'), 'Public pricing headline must stay in one heading without manual line breaks.');

$billing = read_product_file($root, 'pages/billing.php');
$billing_functions = read_product_file($root, 'includes/billing-functions.php');
$index = read_product_file($root, 'index.php');
$settings = settings_source_bundle($root);

assert_product_copy(str_contains($billing_functions, 'function billing_lifecycle_display_label'), 'Billing display labels helper is missing.');
assert_product_copy(str_contains($billing_functions, 'function billing_payment_display_label'), 'Billing payment display helper is missing.');
assert_product_copy(str_contains($billing, 'billing_lifecycle_display_label'), 'Billing page must use lifecycle display labels.');
assert_product_copy(str_contains($billing, 'billing_payment_display_label'), 'Billing page must use payment display labels.');
assert_product_copy(str_contains($billing, 't($access_label)'), 'Billing lifecycle label must go through translations.');
assert_product_copy(!str_contains($billing, '<dt>Workspace status</dt>'), 'Billing page must not expose raw workspace status.');
assert_product_copy(!str_contains($billing, '<dt>Subscription</dt>'), 'Billing page must not expose raw subscription status.');
assert_product_copy(!str_contains($billing, "echo e(\$tenant['status'])"), 'Billing page must not echo raw tenant status.');
assert_product_copy(!str_contains($billing, "echo e(\$tenant['subscription_status']"), 'Billing page must not echo raw subscription status.');
assert_product_copy(!str_contains($billing_functions, 'platform-approved'), 'Workspace billing copy must not expose platform internals.');
assert_product_copy(!str_contains($billing_functions, 'Billing is off'), 'Workspace billing copy must not expose internal setup state.');
assert_product_copy(str_contains($billing, 'Excl. VAT where applicable. Valid EU VAT IDs can be added at checkout or later in billing.'), 'Billing page must show the shared VAT copy.');
assert_product_copy(!str_contains($index, 'Workspace access is restricted'), 'Workspace access guard must use actionable customer copy.');
assert_product_copy(!str_contains($settings, 'Cloudflare Email Service'), 'SaaS workspace settings must not expose the email provider.');
assert_product_copy(!str_contains($settings, 'Outbound email is configured from server config'), 'SaaS workspace settings must not expose server config copy.');
assert_product_copy(str_contains($settings, 'Support email'), 'SaaS email settings must lead with support email copy.');

$reporting = read_product_file($root, 'includes/modules/reports/reporting-flow.php');
foreach ([
    'Pick a client and period.',
    'Check billable rows.',
    'Tune rates, discounts, or totals.',
    'Send the final report.',
] as $needle) {
    assert_product_copy(str_contains($reporting, $needle), 'Reporting flow is missing concise source copy: ' . $needle);
}
foreach ([
    'Start with one client and one billing period.',
    'Open the detailed report with money columns visible.',
    'Create a client-facing report when the numbers are final.',
] as $forbidden) {
    assert_product_copy(!str_contains($reporting, $forbidden), 'Reporting flow still contains verbose copy: ' . $forbidden);
}

echo "Source language and product copy contract OK\n";
