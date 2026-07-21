<?php
/**
 * Create an assigned draft ticket and start its timer as one atomic action.
 */

function quick_start_work_status_id(): ?int
{
    $best_id = null;
    $best_score = -1;

    foreach (get_statuses() as $status) {
        if (!empty($status['is_closed']) || ticket_status_group_from_status($status) !== 'active') {
            continue;
        }

        $name = ticket_status_group_search_text($status['name'] ?? '');
        $score = preg_match('/\b(in progress|progress|working|active|rozpracovano|pracuje se|laufend|en curso|in corso)\b/u', $name)
            ? 100
            : 10;

        if ($score > $best_score) {
            $best_score = $score;
            $best_id = (int) ($status['id'] ?? 0);
        }
    }

    if ($best_id) {
        return $best_id;
    }

    $default = get_default_status();
    return !empty($default['id']) ? (int) $default['id'] : null;
}

function quick_start_work_create(array $user): array
{
    require_once BASE_PATH . '/includes/ticket-crud-functions.php';

    $db = get_db();
    $now = date('Y-m-d H:i:s');
    $started_transaction = !$db->inTransaction();

    try {
        if ($started_transaction) {
            $db->beginTransaction();
        }

        $ticket_data = [
            'title' => t('Untitled work'),
            'description' => '',
            'user_id' => (int) $user['id'],
            'organization_id' => null,
            'assignee_id' => (int) $user['id'],
        ];
        $status_id = quick_start_work_status_id();
        if ($status_id) {
            $ticket_data['status_id'] = $status_id;
        }

        $ticket_id = (int) create_ticket($ticket_data);
        if ($ticket_id <= 0) {
            throw new RuntimeException('Draft ticket could not be created.');
        }

        $ticket = get_ticket($ticket_id);
        if (!$ticket) {
            throw new RuntimeException('Draft ticket could not be loaded.');
        }

        $entry = [
            'ticket_id' => $ticket_id,
            'user_id' => (int) $user['id'],
            'started_at' => $now,
            'ended_at' => null,
            'duration_minutes' => 0,
            'is_billable' => 1,
            'billable_rate' => function_exists('get_ticket_effective_billable_rate')
                ? get_ticket_effective_billable_rate($ticket, (int) $user['id'])
                : 0.0,
            'cost_rate' => (float) ($user['cost_rate'] ?? 0),
            'is_manual' => 0,
            'created_at' => $now,
        ];
        if (function_exists('time_entry_source_column_exists') && time_entry_source_column_exists()) {
            $entry['source'] = 'timer';
        }

        $entry_id = (int) db_insert('ticket_time_entries', $entry);
        if ($entry_id <= 0) {
            throw new RuntimeException('Timer could not be started.');
        }

        update_ticket($ticket_id, ['updated_at' => $now]);
        log_activity($ticket_id, (int) $user['id'], 'ticket_created', 'Work draft created');
        log_activity($ticket_id, (int) $user['id'], 'time_started', 'Timer started');

        if ($started_transaction) {
            $db->commit();
        }
    } catch (Throwable $e) {
        if ($started_transaction && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    if (function_exists('log_ticket_history')) {
        log_ticket_history($ticket_id, (int) $user['id'], 'timer_started', null, $now);
    }

    $ticket = get_ticket($ticket_id);
    $url = ticket_url($ticket);
    $url .= (str_contains($url, '?') ? '&' : '?') . 'quick_start=1';

    return [
        'ticket_id' => $ticket_id,
        'entry_id' => $entry_id,
        'started_at' => $now,
        'url' => $url,
    ];
}
