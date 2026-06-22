<?php
$root = dirname(__DIR__);
$index = file_get_contents($root . '/index.php');
$header = file_get_contents($root . '/includes/header.php');
$work = file_get_contents($root . '/pages/work.php');

function assert_work_page($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

assert_work_page(strpos($index, "case 'work'") !== false, 'work route is not registered.');
assert_work_page(strpos($index, 'function foxdesk_authenticated_home_page') !== false, 'authenticated home helper is missing.');
assert_work_page(strpos($index, "return 'platform';") !== false, 'authenticated platform host should route to platform.');
assert_work_page(strpos($index, "return 'work';") !== false, 'authenticated SaaS workspace home should route to work.');
assert_work_page(strpos($header, "url('work')") !== false, 'sidebar should link to work.');
assert_work_page(strpos($work, 'work_queue_summary') !== false, 'work page should use the work queue module.');
assert_work_page(strpos($work, 'time_activity_work_model') !== false, 'work page should use exact worked-time summaries.');
assert_work_page(strpos($work, 'data-work-time-overview') !== false, 'work page must expose a stable time overview hook.');
assert_work_page(strpos($work, 'data-work-team-time') !== false, 'admin work page must expose a stable team time hook.');
assert_work_page(strpos($work, 'workspace_render_queue_page') !== false, 'work page should use the shared workspace queue renderer.');
assert_work_page(strpos($work, "'show_assignee' => true") !== false, 'work ticket rows should show assignee context.');
assert_work_page(strpos($work, "url('tickets', ['work_view' => 'waiting']") !== false, 'work page should link waiting queue to the ticket list view.');
assert_work_page(strpos($work, "workspace_surface_action(url('dashboard'), 'Analytics'") === false, 'work page should not expose dashboard as a parallel agenda.');
assert_work_page(strpos($work, '($_GET[\'signup\'] ?? \'\') === \'trial\'') !== false, 'work page should show first-run onboarding after verified signup.');
assert_work_page(strpos($work, 'data-signup-onboarding') !== false, 'first-run onboarding must have a stable visual-smoke hook.');
assert_work_page(strpos($work, "url('admin', ['section' => 'settings'])") !== false, 'first-run onboarding should link workspace settings.');
assert_work_page(strpos($work, "url('admin', ['section' => 'users'])") !== false, 'first-run onboarding should link team setup.');
assert_work_page(strpos($work, "url('billing')") !== false, 'first-run onboarding should link billing setup.');
foreach ([
    'Work queues',
    'Start with the queue that needs attention now.',
    'Current queue',
    'Tickets assigned to the current user.',
    'This queue is clear.',
] as $forbidden_copy) {
    assert_work_page(strpos($work, $forbidden_copy) === false, 'work page should not render redundant helper copy: ' . $forbidden_copy);
}
$workspaceSurface = file_get_contents($root . '/includes/components/workspace-surface.php');
assert_work_page($workspaceSurface !== false && strpos($workspaceSurface, "t('All clear')") !== false, 'empty work queue should use concise state copy from the shared renderer.');

echo "Work page contract tests passed\n";
