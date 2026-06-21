<?php

$root = dirname(__DIR__);
$workspace_root = dirname($root);
$self_hosted_root = $workspace_root . '/FoxDesk';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$matrix_path = $root . '/docs/EDITION_PARITY_MATRIX.md';
$release_path = $root . '/docs/RELEASE_CHANNELS.md';
$technical_plan_path = $root . '/docs/TECHNICAL_DEBT_PLAN.md';

$assert(is_file($matrix_path), 'SaaS edition parity matrix must exist.');
$matrix = file_get_contents($matrix_path);
$release = file_get_contents($release_path);
$technical_plan = file_get_contents($technical_plan_path);

$assert($matrix !== false && $release !== false && $technical_plan !== false, 'Unable to read edition parity documents.');

foreach ([
    '| Work | shared |',
    '| Intake queues | shared internal |',
    '| Tickets | shared |',
    '| Ticket detail | shared |',
    '| New ticket | shared |',
    '| Clients | shared |',
    '| Reports | shared |',
    '| Search | shared |',
    '| Notifications | shared |',
    '| Email rendering | shared |',
    '| Billing | saas |',
    '| Platform console | saas |',
    '| Public updater | self-hosted |',
    '| Migration source | self-hosted |',
] as $needle) {
    $assert(str_contains($matrix, $needle), 'Edition parity matrix is missing classification: ' . $needle);
}

foreach ([
    '`mine`, `unassigned`, `overdue`, `waiting`, `done_today`',
    '`triage`, `customer_replies`, `email_imports`',
    '`open`, `waiting`, `done`, `all`, `archived`',
    'Complete must not map to cancelled',
    'no random client fallback',
    'one meaningful email',
] as $needle) {
    $assert(str_contains($matrix, $needle), 'Edition parity matrix is missing shared behavior rule: ' . $needle);
}

foreach ([
    'pages/platform.php',
    'pages/billing.php',
    'pages/cloud.php',
    'pages/signup.php',
    'pages/stripe-webhook.php',
] as $route) {
    $assert(is_file($root . '/' . $route), 'SaaS repository must own ' . $route . '.');
}

$assert(!is_file($root . '/pages/admin/migration-export.php'), 'SaaS workspace admin must not expose self-hosted migration-export page.');
$assert(str_contains($release, 'Public self-hosted updates must not include'), 'Release docs must keep self-hosted SaaS exclusion rules.');
$assert(str_contains($technical_plan, 'docs/EDITION_PARITY_MATRIX.md'), 'Technical debt plan must reference the edition parity matrix.');

if (is_dir($self_hosted_root)) {
    $self_matrix_path = $self_hosted_root . '/docs/EDITION_PARITY_MATRIX.md';
    $assert(is_file($self_matrix_path), 'Self-hosted repository must carry its edition parity matrix.');
    $self_matrix = file_get_contents($self_matrix_path);
    $assert($self_matrix !== false, 'Unable to read self-hosted edition parity matrix.');

    foreach ([
        'pages/admin/migration-export.php',
        'install.php',
        'upgrade.php',
    ] as $route) {
        $assert(is_file($self_hosted_root . '/' . $route), 'Self-hosted repository must own ' . $route . '.');
    }

    foreach ([
        'pages/platform.php',
        'pages/billing.php',
        'pages/cloud.php',
        'pages/signup.php',
        'pages/stripe-webhook.php',
    ] as $route) {
        $assert(!is_file($self_hosted_root . '/' . $route), 'Self-hosted repository must not expose SaaS-only route ' . $route . '.');
        $assert(str_contains($self_matrix, $route), 'Self-hosted exclusion list must name ' . $route . '.');
    }
}

echo "Edition parity contract OK\n";
