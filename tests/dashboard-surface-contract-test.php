<?php

$root = dirname(__DIR__);
$page = file_get_contents($root . '/pages/dashboard.php');
$module = file_get_contents($root . '/includes/modules/app/dashboard-compat.php');
$theme = file_get_contents($root . '/theme.css');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$assert($page !== false && $module !== false && $theme !== false, 'Dashboard files must be readable.');

foreach ([
    'function dashboard_width_class',
    'function dashboard_avatar_class',
    'function dashboard_status_text_class',
    'function dashboard_status_dot_class',
    'function dashboard_priority_badge_class',
    'function dashboard_notification_type_class',
] as $needle) {
    $assert(str_contains($module, $needle), 'Dashboard compatibility module missing surface contract: ' . $needle);
}

foreach ([
    'dashboard_width_class($get_started',
    'dashboard_avatar_class($n_actor_name)',
    'dashboard_notification_type_class((string) $notif',
    'dashboard_notification_type_class((string) $child',
    'dashboard_status_text_class($ticket)',
    'dashboard_status_dot_class($ticket)',
    'dashboard_priority_badge_class($priority_name)',
    "classList.toggle('is-hidden'",
    "classList.add('is-hidden'",
] as $needle) {
    $assert(str_contains($page, $needle), 'Dashboard page missing surface contract: ' . $needle);
}

foreach ([
    '.db-width--0',
    '.db-width--20',
    '.db-avatar-tone--0',
    '.db-avatar-tone--11',
    '.dbnotif-avatar-fallback',
    '.dbnotif-type-icon--new-ticket',
    '.dbnotif-type-icon--due-date-reminder',
    '.dbnotif-type-icon--default',
    '.db-ticket-status--new',
    '.db-ticket-status--waiting',
    '.db-ticket-status-dot--done',
    '.db-priority-badge--low',
    '.db-priority-badge--medium',
    '.db-priority-badge--high',
    '.db-priority-badge--urgent',
] as $needle) {
    $assert(str_contains($theme, $needle), 'theme.css missing dashboard selector: ' . $needle);
}

$assert(!str_contains($page, 'style="'), 'Dashboard must not use inline style attributes.');
$assert(!str_contains($page, '<style'), 'Dashboard must not emit page-level style blocks.');
$assert(!str_contains($page, 'style.'), 'Dashboard JS must not write inline styles.');
$assert(!str_contains($page, '$priority_color'), 'Dashboard priority badges must use normalized CSS classes.');
$assert(!str_contains($page, '$avatar_bg'), 'Dashboard notification avatars must use CSS avatar classes.');
$assert(!str_contains($page, '$type_color'), 'Dashboard notification icons must use CSS type classes.');
$assert(!str_contains($page, '$c_color'), 'Dashboard grouped notification icons must use CSS type classes.');

echo "Dashboard surface contract OK\n";
