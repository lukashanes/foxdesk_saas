<?php
/**
 * App home feed for web and native clients.
 *
 * The feed is intentionally small: it gives clients enough data to render the
 * first screen without exposing page-specific HTML assumptions.
 */

function app_feed_ticket_card(array $ticket): array
{
    $status_label = function_exists('ticket_status_group_display_name')
        ? ticket_status_group_display_name([
            'id' => (int) ($ticket['status_id'] ?? 0),
            'name' => (string) ($ticket['status_name'] ?? ''),
            'color' => $ticket['status_color'] ?? null,
            'is_closed' => $ticket['is_closed'] ?? 0,
            'status_group' => $ticket['status_group'] ?? null,
        ])
        : (string) ($ticket['status_name'] ?? '');
    $status_group = function_exists('ticket_status_group_from_status')
        ? ticket_status_group_from_status([
            'name' => $ticket['status_name'] ?? '',
            'is_closed' => $ticket['is_closed'] ?? 0,
            'status_group' => $ticket['status_group'] ?? null,
        ])
        : 'active';

    if (!empty($ticket['is_archived'])) {
        $status_group = 'archived';
    }

    $tags = [];
    if (function_exists('normalize_ticket_tags')) {
        $tags = normalize_ticket_tags($ticket['tags'] ?? '', true);
    } elseif (!empty($ticket['tags'])) {
        $tags = array_values(array_filter(array_map('trim', explode(',', (string) $ticket['tags']))));
    }

    $ticket_id = (int) ($ticket['id'] ?? 0);

    $worked_minutes = max(0, (int) ($ticket['worked_minutes'] ?? 0));

    return [
        'id' => $ticket_id,
        'hash' => $ticket['hash'] ?? null,
        'code' => function_exists('get_ticket_code') ? get_ticket_code($ticket_id) : ('#' . $ticket_id),
        'title' => (string) ($ticket['title'] ?? ''),
        'description_preview' => function_exists('app_contract_text_excerpt')
            ? app_contract_text_excerpt($ticket['description'] ?? '', 180)
            : mb_substr(trim(strip_tags((string) ($ticket['description'] ?? ''))), 0, 180),
        'status' => [
            'name' => $status_label,
            'color' => $ticket['status_color'] ?? null,
            'group' => $status_group,
        ],
        'priority' => [
            'name' => (string) ($ticket['priority_name'] ?? ''),
            'color' => $ticket['priority_color'] ?? null,
        ],
        'client' => [
            'id' => isset($ticket['organization_id']) ? (int) $ticket['organization_id'] : null,
            'name' => (string) ($ticket['organization_name'] ?? ''),
        ],
        'requester' => trim((string) (($ticket['first_name'] ?? '') . ' ' . ($ticket['last_name'] ?? ''))),
        'assignee' => trim((string) (($ticket['assignee_first_name'] ?? '') . ' ' . ($ticket['assignee_last_name'] ?? ''))),
        'source' => (string) ($ticket['source'] ?? 'web'),
        'tags' => $tags,
        'due_date' => $ticket['due_date'] ?? null,
        'created_at' => $ticket['created_at'] ?? null,
        'updated_at' => $ticket['updated_at'] ?? null,
        'worked_minutes' => $worked_minutes,
        'worked_label' => app_feed_duration_label($worked_minutes),
        'url' => function_exists('ticket_url') ? ticket_url($ticket) : url('ticket', ['id' => $ticket_id]),
    ];
}

function app_feed_queue_sections(array $summary): array
{
    $sections = [];
    foreach ($summary as $key => $queue) {
        $items = [];
        foreach (($queue['items'] ?? []) as $ticket) {
            $items[] = app_feed_ticket_card($ticket);
        }

        $sections[$key] = [
            'definition' => $queue['definition'] ?? [],
            'count' => (int) ($queue['count'] ?? count($items)),
            'items' => $items,
        ];
    }

    return $sections;
}

function app_feed_active_timers(array $user): array
{
    if (($user['role'] ?? '') === 'user' || !function_exists('ticket_time_table_exists') || !ticket_time_table_exists()) {
        return [];
    }
    if (!function_exists('get_user_all_active_timers')) {
        return [];
    }

    $timers = [];
    foreach (get_user_all_active_timers((int) ($user['id'] ?? 0)) as $timer) {
        $elapsed_seconds = function_exists('calculate_timer_elapsed') ? calculate_timer_elapsed($timer) : 0;
        $elapsed_minutes = (int) floor(max(0, $elapsed_seconds) / 60);
        $timers[] = [
            'entry_id' => (int) ($timer['id'] ?? 0),
            'ticket_id' => (int) ($timer['ticket_id'] ?? 0),
            'ticket_hash' => $timer['ticket_hash'] ?? null,
            'ticket_title' => (string) ($timer['ticket_title'] ?? ''),
            'started_at' => $timer['started_at'] ?? null,
            'is_paused' => function_exists('is_timer_paused') ? is_timer_paused($timer) : !empty($timer['paused_at']),
            'elapsed_minutes' => $elapsed_minutes,
            'elapsed_label' => function_exists('format_duration_minutes') ? format_duration_minutes($elapsed_minutes) : ($elapsed_minutes . ' min'),
            'url' => !empty($timer['ticket_hash'])
                ? url('ticket', ['t' => $timer['ticket_hash']])
                : url('ticket', ['id' => (int) ($timer['ticket_id'] ?? 0)]),
        ];
    }

    return $timers;
}

function app_feed_notifications(array $user): array
{
    $result = function_exists('get_user_notifications')
        ? get_user_notifications((int) ($user['id'] ?? 0), 3, 0, true)
        : ['notifications' => [], 'unread_count' => 0];

    return [
        'unread_count' => (int) ($result['unread_count'] ?? 0),
        'items' => array_map(
            static fn(array $notification): array => function_exists('app_contract_notification_summary_item')
                ? app_contract_notification_summary_item($notification)
                : [],
            array_slice($result['notifications'] ?? [], 0, 3)
        ),
    ];
}

function app_feed_duration_label(int $minutes): string
{
    return function_exists('format_duration_minutes') ? format_duration_minutes($minutes) : ($minutes . ' min');
}

function app_feed_time_entry(array $entry): array
{
    $minutes = (int) ($entry['actual_minutes'] ?? $entry['duration_minutes'] ?? 0);
    $ticket_id = (int) ($entry['ticket_id'] ?? 0);

    return [
        'id' => (int) ($entry['id'] ?? 0),
        'ticket_id' => $ticket_id,
        'ticket_hash' => $entry['ticket_hash'] ?? null,
        'ticket_code' => function_exists('get_ticket_code') ? get_ticket_code($ticket_id) : ('#' . $ticket_id),
        'ticket_title' => (string) ($entry['ticket_title'] ?? ''),
        'client_name' => (string) ($entry['organization_name'] ?? ''),
        'status_name' => (string) ($entry['status_name'] ?? ''),
        'summary' => (string) ($entry['summary'] ?? ''),
        'started_at' => $entry['started_at'] ?? null,
        'ended_at' => $entry['ended_at'] ?? null,
        'minutes' => $minutes,
        'minutes_label' => app_feed_duration_label($minutes),
        'url' => !empty($entry['ticket_hash'])
            ? url('ticket', ['t' => $entry['ticket_hash']])
            : url('ticket', ['id' => $ticket_id]),
    ];
}

function app_feed_team_time_member(array $row): array
{
    $user = $row['user'] ?? [];
    $totals = [];
    foreach (($row['totals'] ?? []) as $key => $minutes) {
        $minutes = (int) $minutes;
        $totals[$key] = [
            'minutes' => $minutes,
            'label' => app_feed_duration_label($minutes),
        ];
    }

    $entries = [];
    foreach (($row['entries'] ?? []) as $entry) {
        $entries[] = app_feed_time_entry($entry);
    }

    return [
        'user_id' => (int) ($user['id'] ?? 0),
        'name' => (string) ($row['name'] ?? ''),
        'email' => (string) ($user['email'] ?? ''),
        'role' => (string) ($user['role'] ?? ''),
        'avatar' => $user['avatar'] ?? null,
        'is_running' => !empty($row['is_running']),
        'totals' => $totals,
        'entries' => $entries,
        'latest_entry' => !empty($row['latest_entry']) ? app_feed_time_entry($row['latest_entry']) : null,
    ];
}

function app_feed_time_day(array $day): array
{
    $minutes = (int) ($day['minutes'] ?? 0);
    $users = [];
    foreach (($day['users'] ?? []) as $chart_user) {
        $user_minutes = (int) ($chart_user['minutes'] ?? 0);
        $users[] = [
            'user_id' => (int) ($chart_user['user_id'] ?? 0),
            'name' => (string) ($chart_user['name'] ?? ''),
            'minutes' => $user_minutes,
            'minutes_label' => app_feed_duration_label($user_minutes),
        ];
    }

    return [
        'key' => (string) ($day['key'] ?? ''),
        'label' => (string) ($day['label'] ?? ''),
        'full_label' => (string) ($day['full_label'] ?? ''),
        'minutes' => $minutes,
        'minutes_label' => app_feed_duration_label($minutes),
        'users' => $users,
    ];
}

function app_feed_time_activity(array $user, array $request = []): array
{
    if (!function_exists('time_activity_work_model')) {
        return [
            'period' => null,
            'totals' => [],
            'entries' => [],
            'chart' => ['days' => [], 'max_minutes' => 0, 'total_minutes' => 0, 'total_label' => app_feed_duration_label(0)],
        ];
    }

    $model = time_activity_work_model($user, [
        'period' => (string) ($request['period'] ?? 'last_30_days'),
        'time_scope' => (string) ($request['time_scope'] ?? 'mine'),
        'my_activity' => 'last3',
        'team_activity' => 'last3',
    ]);

    $totals = [];
    foreach (($model['display_totals'] ?? $model['my_totals'] ?? []) as $key => $minutes) {
        $minutes = (int) $minutes;
        $totals[$key] = [
            'minutes' => $minutes,
            'label' => app_feed_duration_label($minutes),
        ];
    }

    $entries = [];
    foreach (($model['my_entries'] ?? []) as $entry) {
        $entries[] = app_feed_time_entry($entry);
    }

    $team = [];
    foreach (($model['team'] ?? []) as $row) {
        $team[] = app_feed_team_time_member($row);
    }

    $chart = $model['period_chart'] ?? [];
    $chart_days = [];
    foreach (($chart['days'] ?? []) as $day) {
        $chart_days[] = app_feed_time_day($day);
    }
    $chart_total = (int) ($chart['total_minutes'] ?? 0);

    return [
        'scope' => [
            'key' => (string) ($model['view_scope']['key'] ?? 'mine'),
            'label' => (string) ($model['view_scope']['label'] ?? t('My time')),
            'can_view_team' => !empty($model['view_scope']['can_view_team']),
        ],
        'period' => [
            'key' => (string) ($model['period']['period'] ?? 'last_30_days'),
            'label' => (string) ($model['period']['label'] ?? t('Last 30 days')),
            'start' => $model['period']['start'] ?? null,
            'end' => $model['period']['end'] ?? null,
        ],
        'totals' => $totals,
        'entries' => $entries,
        'team' => $team,
        'chart' => [
            'days' => $chart_days,
            'max_minutes' => (int) ($chart['max_minutes'] ?? 0),
            'total_minutes' => $chart_total,
            'total_label' => app_feed_duration_label($chart_total),
        ],
    ];
}

function app_feed_payload(array $user, int $limit = 5, array $request = []): array
{
    $limit = max(1, min(20, $limit));

    return [
        'schema_version' => function_exists('app_contract_schema_version') ? app_contract_schema_version() : 1,
        'generated_at' => date('c'),
        'limit' => $limit,
        'work' => function_exists('work_queue_summary')
            ? app_feed_queue_sections(work_queue_summary($user, $limit))
            : [],
        'inbox' => function_exists('inbox_summary')
            ? app_feed_queue_sections(inbox_summary($user, $limit))
            : [],
        'timers' => app_feed_active_timers($user),
        'time' => app_feed_time_activity($user, $request),
        'notifications' => app_feed_notifications($user),
    ];
}
