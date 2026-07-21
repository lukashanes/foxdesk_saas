<?php

$root = dirname(__DIR__);
require_once __DIR__ . '/support/ticket-detail-source.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$line_count = static function (string $path): int {
    $lines = file($path);
    if ($lines === false) {
        fwrite(STDERR, 'Unable to read ' . $path . PHP_EOL);
        exit(1);
    }

    return count($lines);
};

$new_ticket_route = file_get_contents($root . '/pages/new-ticket.php');
$ticket_detail_page = file_get_contents($root . '/pages/ticket-detail.php');
$ticket_detail_core = file_get_contents($root . '/assets/js/ticket-detail.js');

$assert($new_ticket_route !== false, 'New-ticket route must be readable.');
$assert($ticket_detail_page !== false, 'Ticket-detail page must be readable.');
$assert($ticket_detail_core !== false, 'Ticket-detail core asset must be readable.');
$assert($line_count($root . '/pages/new-ticket.php') < 700, 'pages/new-ticket.php must stay below 700 lines.');
$assert($line_count($root . '/assets/js/ticket-detail.js') < 900, 'assets/js/ticket-detail.js must stay below 900 lines.');

foreach ([
    'includes/components/new-ticket-form.php',
    'includes/components/new-ticket-assets.php',
] as $component) {
    $assert(str_contains($new_ticket_route, $component), 'New-ticket route must delegate to ' . $component);
    $assert(is_file($root . '/' . $component), 'Missing extracted component: ' . $component);
}

$ordered_assets = [
    'assets/js/ticket-detail-timer.js',
    'assets/js/ticket-detail-records.js',
    'assets/js/ticket-detail-admin.js',
    'assets/js/ticket-detail.js',
];
$last_position = -1;
foreach ($ordered_assets as $asset) {
    $position = strpos($ticket_detail_page, $asset);
    $assert($position !== false, 'Ticket detail must load ' . $asset);
    $assert($position > $last_position, 'Ticket-detail feature assets must load before the core in dependency order.');
    $assert(is_file($root . '/' . $asset), 'Missing ticket-detail asset: ' . $asset);
    $last_position = $position;
}

$assert(str_contains($ticket_detail_core, 'window.FoxDeskTicketDetailFeatures'), 'Ticket-detail core must install extracted feature modules.');
$assert(str_contains(ticket_detail_browser_source($root), 'function initTimer'), 'Timer behavior must remain in the composed browser source.');
$assert(str_contains(ticket_detail_browser_source($root), 'function initTags'), 'Tag behavior must remain in the composed browser source.');
$assert(str_contains(ticket_detail_browser_source($root), 'function initPermanentDelete'), 'Admin behavior must remain in the composed browser source.');

echo "New-ticket and ticket-detail extraction contract OK\n";
