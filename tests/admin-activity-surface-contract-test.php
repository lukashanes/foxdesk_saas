<?php

$root = dirname(__DIR__);
$page = file_get_contents($root . '/pages/admin/activity.php');
$theme = file_get_contents($root . '/theme.css');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$assert($page !== false && $theme !== false, 'Admin activity files must be readable.');

foreach ([
    'function pv_meter_class',
    'function pv_spark_class',
    'function pv_avatar_class',
    'act-card--flush',
    'act-table-wrap',
    'act-th--numeric',
    'act-td--numeric',
    'act-muted-cell',
    'act-filter-submit',
    'act-page-link',
] as $needle) {
    $assert(str_contains($page, $needle), 'Activity page missing surface contract: ' . $needle);
}

foreach ([
    '.act-card--flush',
    '.act-table-wrap',
    '.act-th--numeric',
    '.act-td--numeric',
    '.act-meter--20',
    '.act-spark-bar--10',
    '.act-avatar--lg',
    '.act-avatar--11',
    '.act-filter-submit',
    '.act-page-link',
] as $needle) {
    $assert(str_contains($theme, $needle), 'theme.css missing activity selector: ' . $needle);
}

$assert(!str_contains($page, 'style="'), 'Admin activity page must not use inline style attributes.');
$assert(!str_contains($page, '<style'), 'Admin activity page must not emit page-level style blocks.');
$assert(!str_contains($page, '$ua_bg'), 'Activity avatars must use CSS avatar classes, not generated inline colors.');
$assert(!str_contains($page, '$du_bg'), 'Activity detail avatar must use CSS avatar classes, not generated inline colors.');
$assert(!str_contains($page, 'act-role-<?php echo e('), 'Activity role badges must use normalized role CSS classes.');

echo "Admin activity surface contract OK\n";
