<?php

$root = dirname(__DIR__);

$files = [
    'dashboard' => $root . '/ios/FoxDesk/FoxDesk/Sources/DashboardView.swift',
    'identity' => $root . '/ios/FoxDesk/FoxDesk/Sources/DashboardIdentitySectionsView.swift',
    'worked_time' => $root . '/ios/FoxDesk/FoxDesk/Sources/DashboardWorkedTimeView.swift',
    'queues' => $root . '/ios/FoxDesk/FoxDesk/Sources/DashboardWorkQueuesView.swift',
    'quick_actions' => $root . '/ios/FoxDesk/FoxDesk/Sources/DashboardQuickActionsView.swift',
    'home_models' => $root . '/ios/FoxDesk/FoxDeskKit/Sources/Models/HomeModels.swift',
];

$sources = [];
foreach ($files as $key => $path) {
    $content = file_get_contents($path);
    if ($content === false) {
        fwrite(STDERR, "Unable to read {$path}.\n");
        exit(1);
    }
    $sources[$key] = $content;
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$dashboard = $sources['dashboard'];
$identity = $sources['identity'];
$workedTime = $sources['worked_time'];
$queues = $sources['queues'];
$quickActions = $sources['quick_actions'];
$homeModels = $sources['home_models'];

$workedTimePosition = strpos($dashboard, 'WorkedTimeSection(time: time)');
$activeTimersPosition = strpos($dashboard, 'ActiveTimersSection(timers: home.timers ?? [])');
$queuesPosition = strpos($dashboard, 'WorkQueueSections(home: home)');
$recentUpdatesPosition = strpos($dashboard, 'RecentUpdatesSection(notifications:');

$assert(str_contains($dashboard, 'ActiveTimersSection(timers: home.timers ?? [])'), 'iOS Dashboard must surface active timers.');
$assert(str_contains($dashboard, 'WorkedTimeSection(time: time)'), 'iOS Dashboard must surface worked-time summary.');
$assert(str_contains($dashboard, 'WorkQueueSections(home: home)'), 'iOS Dashboard must surface ticket work queues.');
$assert(str_contains($dashboard, 'RecentUpdatesSection(notifications:'), 'iOS Dashboard must surface recent ticket updates.');
$assert(!str_contains($dashboard, 'QuickActionsSection('), 'iOS Dashboard must not duplicate bottom-tab destinations as quick actions.');
$assert(!str_contains($dashboard, 'NewTicketView'), 'iOS Dashboard must not duplicate the bottom New ticket tab.');
$assert(!str_contains($dashboard, 'DashboardTicketRoute'), 'iOS Dashboard must not own a dashboard-only created-ticket route.');
$assert(!str_contains($dashboard, 'ToolbarItem'), 'iOS Dashboard must not show top add/reload toolbar buttons.');
$assert(str_contains($dashboard, '.refreshable'), 'iOS Dashboard must keep pull-to-refresh.');
$assert($workedTimePosition !== false && $activeTimersPosition !== false && $workedTimePosition < $activeTimersPosition, 'iOS Dashboard must show KPI/chart before in-progress work.');
$assert($activeTimersPosition !== false && $queuesPosition !== false && $activeTimersPosition < $queuesPosition, 'iOS Dashboard must show in-progress work before ticket queues.');
$assert($queuesPosition !== false && $recentUpdatesPosition !== false && $queuesPosition < $recentUpdatesPosition, 'iOS Dashboard must show ticket queues before recent updates.');
$assert(str_contains($dashboard, 'HomeFeedCacheStore'), 'iOS Dashboard must keep cached fallback for fast/offline launch.');
$assert(str_contains($dashboard, 'await homeCache.save'), 'iOS Dashboard must persist refreshed home data.');
$assert(str_contains($dashboard, 'await homeCache.load'), 'iOS Dashboard must load cached home data when offline.');

$assert(str_contains($identity, 'Section("In progress now")'), 'In-progress section title is missing.');
$assert(str_contains($identity, 'TicketDetailView(ticketID: timer.ticketId, ticketHash: timer.ticketHash)'), 'Active timers must deep-link to the running ticket with a hash fallback.');
$assert(str_contains($identity, 'timer.elapsedLabel'), 'Active timers must show elapsed time.');

foreach (['"mine"', '"unassigned"', '"overdue"', '"waiting"', '"done_today"'] as $queueKey) {
    $assert(str_contains($queues, $queueKey), "Dashboard queue order must keep {$queueKey}.");
}
$assert(str_contains($queues, 'HomeTicketCardRow'), 'Dashboard work queues must render ticket rows, not only counters.');
$assert(str_contains($queues, 'TicketDetailView(ticketID: ticket.id, ticketHash: ticket.hash)'), 'Dashboard work queue tickets must open ticket detail with a hash fallback.');

foreach (['"Today"', '"This week"', '"This month"'] as $timeLabel) {
    $assert(str_contains($workedTime, $timeLabel), "Worked-time totals must include {$timeLabel}.");
}
$assert(str_contains($workedTime, 'WorkedTimeChart(chart: chart)'), 'Dashboard worked time must include the period chart when provided.');
$assert(str_contains($workedTime, 'Text("Last 30 days")'), 'Dashboard worked-time chart must default to a last-30-days visual.');
$assert(str_contains($workedTime, 'Text("Recent work")'), 'Dashboard must show recent ticket work under worked time.');
$assert(str_contains($workedTime, 'TeamActivityList(team: team)'), 'Admin-capable Dashboard must expose team activity when the API provides it.');
$assert(str_contains($workedTime, 'TeamMemberWorkSheet'), 'Team activity must let admins inspect a team member workload.');
$assert(str_contains($workedTime, 'TicketDetailView(ticketID: entry.ticketId, ticketHash: entry.ticketHash)'), 'Recent work entries must open ticket detail with a hash fallback.');

$assert(str_contains($quickActions, 'Section("Recent updates")'), 'Recent updates section title is missing.');
$assert(str_contains($quickActions, 'TicketDetailView(ticketID: ticketID, ticketHash: notification.ticketHash)'), 'Recent updates with ticket ids must open ticket detail with a hash fallback.');
$assert(!str_contains($quickActions, 'struct QuickActionsSection'), 'Dashboard quick actions must be removed in favor of the bottom tab bar.');
$assert(!str_contains($quickActions, 'TicketsView()'), 'Dashboard quick actions must not duplicate the bottom Tickets tab.');
$assert(!str_contains($quickActions, 'SearchView()'), 'Dashboard quick actions must not duplicate the bottom Search tab.');
$assert(!str_contains($quickActions, 'NotificationsView()'), 'Dashboard quick actions must not duplicate notification navigation.');

foreach (['public let work', 'public let timers', 'public let time', 'public let notifications'] as $modelNeedle) {
    $assert(str_contains($homeModels, $modelNeedle), "HomeFeed model must keep {$modelNeedle}.");
}
$assert(str_contains($homeModels, 'public struct HomeTimeActivity'), 'HomeFeed must keep worked-time activity model.');
$assert(str_contains($homeModels, 'public struct HomeTeamTimeMember'), 'HomeFeed must keep team time model for admin dashboard.');
$assert(str_contains($homeModels, 'public struct HomeTimeChart'), 'HomeFeed must keep worked-time chart model.');

echo "iOS Dashboard contract OK\n";
