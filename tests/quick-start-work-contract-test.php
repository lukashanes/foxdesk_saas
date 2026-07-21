<?php

$root = dirname(__DIR__);

function assert_quick_start(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function quick_start_file(string $root, string $path): string
{
    $contents = file_get_contents($root . '/' . $path);
    assert_quick_start($contents !== false, 'Unable to read ' . $path);
    return $contents;
}

$bootstrap = quick_start_file($root, 'includes/modules/bootstrap.php');
$handler = quick_start_file($root, 'includes/api/ticket-handler.php');
$router = quick_start_file($root, 'includes/api/router.php');
$header = quick_start_file($root, 'includes/header.php');
$footer_js = quick_start_file($root, 'assets/js/app-footer.js');
$detail_page = quick_start_file($root, 'pages/ticket-detail.php');
$detail_js = quick_start_file($root, 'assets/js/ticket-detail.js');
$modals = quick_start_file($root, 'includes/components/ticket-detail-modals.php');

assert_quick_start(!str_contains($bootstrap, 'work/quick-start-work.php'), 'Quick-start module must not be loaded.');
assert_quick_start(!str_contains($handler, 'function api_quick_start'), 'Quick-start API must not create a ticket before form submission.');
assert_quick_start(!str_contains($router, "'quick-start'"), 'Quick-start API route must not be public.');
assert_quick_start(str_contains($header, "href=\"<?php echo url('new-ticket'); ?>\""), 'Staff navigation must open the normal new-ticket form.');
assert_quick_start(str_contains($header, "<span><?php echo e(t('New ticket')); ?></span>"), 'Primary ticket action must be labelled New ticket.');
assert_quick_start(!str_contains($header, 'data-quick-start-work'), 'Navigation must not start work implicitly.');
assert_quick_start(!str_contains($footer_js, 'action=quick-start'), 'Global app JS must not call the removed quick-start endpoint.');
assert_quick_start(!str_contains($detail_page, "'quickStart'"), 'Ticket detail must not expose removed quick-start state.');
assert_quick_start(!str_contains($detail_js, 'config.quickStart'), 'Ticket detail must not open an implicit draft completion flow.');
assert_quick_start(!str_contains($modals, 'data-quick-start-optional'), 'Ticket edit fields must not be hidden by removed quick-start state.');

echo "Normal new-ticket flow contract OK\n";
