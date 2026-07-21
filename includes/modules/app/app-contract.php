<?php
/**
 * App API read models.
 *
 * These helpers shape PHP domain data for the Next shell and native clients.
 * Endpoint handlers stay thin: auth, permissions, and response wrapping only.
 */

function app_contract_schema_version(): int
{
    return 1;
}

function app_contract_frozen_response_keys(): array
{
    return [
        'envelope' => ['data', 'meta', 'errors'],
        'app_shell' => [
            'schema_version',
            'generated_at',
            'home_page',
            'user',
            'navigation',
            'capabilities',
            'work_queues',
            'inbox_queues',
            'search_sections',
            'reporting',
        ],
        'app_home' => [
            'schema_version',
            'generated_at',
            'limit',
            'work',
            'inbox',
            'timers',
            'time',
            'notifications',
        ],
        'ticket_list' => ['tickets', 'view', 'views', 'counts', 'pagination', 'filters'],
        'ticket_detail' => ['ticket', 'comments', 'attachments', 'time_entries', 'actions'],
        'ticket_actions' => ['ticket', 'actions'],
        'ticket_create_options' => ['clients', 'statuses', 'priorities', 'assignees', 'defaults'],
        'update_ticket' => ['ticket', 'actions', 'updated_fields'],
        'create_ticket' => ['ticket_id', 'ticket_hash', 'ticket_code', 'ticket'],
        'add_comment' => ['comment_id', 'time_entry_id'],
        'attachment_metadata' => ['attachment'],
        'ticket_timer' => ['ticket', 'timer'],
        'timer_action' => ['ticket', 'timer', 'action', 'result'],
        'log_time' => ['ticket', 'time_entry_id', 'duration_minutes'],
        'client_overview' => ['client', 'view', 'counts', 'tickets', 'contacts', 'time', 'links'],
        'reporting_review' => ['filters', 'range', 'entries', 'totals', 'total_labels', 'actions', 'bulk_actions', 'pagination'],
        'notifications' => ['unread_count', 'items', 'pagination'],
        'notification_read_state' => ['unread_count', 'updated'],
        'tenant_state' => ['tenant', 'access', 'billing_actions', 'usage', 'capabilities', 'links'],
        'mobile_session' => ['token_type', 'access_token', 'refresh_token', 'expires_in', 'refresh_expires_in'],
        'upload' => ['file'],
    ];
}

function app_contract_plain_text($html): string
{
    $text = (string) $html;
    if ($text === '') {
        return '';
    }

    $text = preg_replace('/<(br|hr)\b[^>]*>/i', "\n", $text);
    $text = preg_replace('/<\/(p|div|li|ul|ol|h[1-6]|tr|blockquote)>/i', "\n", $text);
    $text = preg_replace('/<(p|div|li|ul|ol|h[1-6]|tr|blockquote)\b[^>]*>/i', "\n", $text);
    $text = html_entity_decode(strip_tags($text ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
    $text = preg_replace('/ *\R */u', "\n", $text);
    $text = preg_replace('/\R{2,}/u', "\n", $text);

    return trim($text ?? '');
}

function app_contract_text_excerpt($html, int $limit = 180): string
{
    $plain = app_contract_plain_text($html);
    $plain = preg_replace('/\s+/u', ' ', $plain);
    return mb_substr(trim($plain ?? ''), 0, $limit);
}

function app_contract_ticket_payload(array $ticket): array
{
    $ticket_id = (int) ($ticket['id'] ?? 0);
    $status_label = function_exists('ticket_status_group_display_name')
        ? ticket_status_group_display_name([
            'id' => (int) ($ticket['status_id'] ?? 0),
            'name' => (string) ($ticket['status_name'] ?? ''),
            'color' => $ticket['status_color'] ?? null,
            'is_closed' => $ticket['is_closed'] ?? 0,
        ])
        : (string) ($ticket['status_name'] ?? '');

    $worked_minutes = max(0, (int) ($ticket['worked_minutes'] ?? 0));

    return [
        'id' => $ticket_id,
        'hash' => $ticket['hash'] ?? null,
        'code' => function_exists('get_ticket_code') ? get_ticket_code($ticket_id) : ('#' . $ticket_id),
        'title' => (string) ($ticket['title'] ?? ''),
        'description_html' => (string) ($ticket['description'] ?? ''),
        'description_text' => app_contract_plain_text($ticket['description'] ?? ''),
        'status' => [
            'id' => (int) ($ticket['status_id'] ?? 0),
            'name' => $status_label,
            'color' => $ticket['status_color'] ?? null,
            'group' => function_exists('ticket_status_group_for_status_id')
                ? ticket_status_group_for_status_id(isset($ticket['status_id']) ? (int) $ticket['status_id'] : null)
                : (!empty($ticket['is_closed']) ? 'done' : 'active'),
            'is_closed' => !empty($ticket['is_closed']),
        ],
        'priority' => [
            'id' => isset($ticket['priority_id']) ? (int) $ticket['priority_id'] : null,
            'name' => (string) ($ticket['priority_name'] ?? ''),
            'color' => $ticket['priority_color'] ?? null,
        ],
        'client' => [
            'id' => isset($ticket['organization_id']) ? (int) $ticket['organization_id'] : null,
            'name' => (string) ($ticket['organization_name'] ?? ''),
        ],
        'requester' => [
            'id' => (int) ($ticket['user_id'] ?? 0),
            'name' => trim((string) (($ticket['first_name'] ?? '') . ' ' . ($ticket['last_name'] ?? ''))),
        ],
        'assignee' => [
            'id' => isset($ticket['assignee_id']) ? (int) $ticket['assignee_id'] : null,
            'name' => trim((string) (($ticket['assignee_first_name'] ?? '') . ' ' . ($ticket['assignee_last_name'] ?? ''))),
        ],
        'source' => (string) ($ticket['source'] ?? 'web'),
        'is_archived' => !empty($ticket['is_archived']),
        'tags' => function_exists('normalize_ticket_tags')
            ? normalize_ticket_tags($ticket['tags'] ?? '', true)
            : [],
        'due_date' => $ticket['due_date'] ?? null,
        'created_at' => $ticket['created_at'] ?? null,
        'updated_at' => $ticket['updated_at'] ?? null,
        'worked_minutes' => $worked_minutes,
        'worked_label' => function_exists('format_duration_minutes')
            ? format_duration_minutes($worked_minutes)
            : ($worked_minutes . ' min'),
        'url' => function_exists('ticket_url') ? ticket_url($ticket) : url('ticket', ['id' => $ticket_id]),
    ];
}

function app_contract_ticket_list_item(array $ticket): array
{
    $payload = app_contract_ticket_payload($ticket);
    $payload['description_preview'] = app_contract_text_excerpt($ticket['description'] ?? '', 180);
    $payload['attachment_count'] = (int) ($ticket['attachment_count'] ?? 0);
    $payload['is_archived'] = !empty($ticket['is_archived']);
    return $payload;
}

function app_contract_ticket_filters_from_request(array $request, array $user, int $limit, int $offset): array
{
    $sort = (string) ($request['sort'] ?? 'last_updated');
    $allowed_sorts = ['oldest', 'priority', 'status', 'tags', 'due_date', 'last_updated', 'ticket_number', 'ticket_number_asc'];
    if (!in_array($sort, $allowed_sorts, true)) {
        $sort = 'last_updated';
    }

    $filters = [
        'is_archived' => 0,
        'sort' => $sort,
        'limit' => $limit,
        'offset' => $offset,
    ];

    foreach (['search', 'source', 'due_date_filter', 'tag', 'tags', 'view_mode'] as $key) {
        if (isset($request[$key]) && trim((string) $request[$key]) !== '') {
            $filters[$key] = $request[$key];
        }
    }

    foreach (['organization_id', 'priority_id', 'type_id', 'status_id', 'user_id'] as $key) {
        $value = (int) ($request[$key] ?? 0);
        if ($value > 0) {
            $filters[$key] = $value;
        }
    }

    if (isset($request['assigned_to'])) {
        $assigned_to = (string) $request['assigned_to'];
        if ($assigned_to === 'me') {
            $filters['assigned_to'] = (int) ($user['id'] ?? 0);
        } elseif ((int) $assigned_to > 0) {
            $filters['assigned_to'] = (int) $assigned_to;
        }
    }

    if (function_exists('build_ticket_visibility_filters_for_user')) {
        $filters = build_ticket_visibility_filters_for_user($user, $filters);
    }

    return $filters;
}

function app_contract_ticket_timer(int $ticket_id, array $user): array
{
    $state = 'stopped';
    $elapsed_minutes = 0;
    $entry_id = null;

    if (function_exists('ticket_time_table_exists') && ticket_time_table_exists() && function_exists('get_active_ticket_timer')) {
        $timer = get_active_ticket_timer($ticket_id, (int) ($user['id'] ?? 0));
        if ($timer) {
            $entry_id = (int) ($timer['id'] ?? 0);
            $state = (function_exists('is_timer_paused') && is_timer_paused($timer)) ? 'paused' : 'running';
            $elapsed_seconds = function_exists('calculate_timer_elapsed') ? calculate_timer_elapsed($timer) : 0;
            $elapsed_minutes = max(0, (int) floor($elapsed_seconds / 60));
        }
    }

    return [
        'state' => $state,
        'entry_id' => $entry_id,
        'elapsed_minutes' => $elapsed_minutes,
        'elapsed_label' => function_exists('format_duration_minutes') ? format_duration_minutes($elapsed_minutes) : ($elapsed_minutes . ' min'),
    ];
}

function app_contract_ticket_status_options(): array
{
    $status_options = [];
    foreach (function_exists('get_statuses') ? get_statuses() : [] as $status) {
        $status_options[] = [
            'id' => (int) ($status['id'] ?? 0),
            'name' => (string) ($status['name'] ?? ''),
            'color' => $status['color'] ?? null,
            'group' => function_exists('ticket_status_group_from_status') ? ticket_status_group_from_status($status) : (!empty($status['is_closed']) ? 'done' : 'active'),
            'is_closed' => !empty($status['is_closed']),
            'is_canceled' => function_exists('ticket_detail_status_is_canceled') ? ticket_detail_status_is_canceled($status) : false,
            'is_default' => !empty($status['is_default']),
        ];
    }

    return $status_options;
}

function app_contract_ticket_priority_options(): array
{
    $priority_options = [];
    foreach (function_exists('get_priorities') ? get_priorities() : [] as $priority) {
        $priority_options[] = [
            'id' => (int) ($priority['id'] ?? 0),
            'name' => (string) ($priority['name'] ?? ''),
            'color' => $priority['color'] ?? null,
            'is_default' => !empty($priority['is_default']),
        ];
    }

    return $priority_options;
}

function app_contract_ticket_assignee_options(array $user): array
{
    $assignee_options = [];
    if (!function_exists('db_fetch_all')) {
        return $assignee_options;
    }

    try {
        $params = [];
        $sql = "SELECT id, first_name, last_name, email, role FROM users WHERE role IN ('admin', 'agent') AND is_active = 1";
        if (function_exists('users_deleted_at_column_exists') && users_deleted_at_column_exists()) {
            $sql .= " AND deleted_at IS NULL";
        }
        if (function_exists('tenant_sql_filter')) {
            $sql .= tenant_sql_filter('users', '', $params);
        }
        $sql .= " ORDER BY first_name ASC, last_name ASC, email ASC";
        foreach (db_fetch_all($sql, $params) as $staff) {
            if (function_exists('can_user_assign_to_staff') && !can_user_assign_to_staff($staff, $user)) {
                continue;
            }
            $name = trim((string) (($staff['first_name'] ?? '') . ' ' . ($staff['last_name'] ?? '')));
            $assignee_options[] = [
                'id' => (int) ($staff['id'] ?? 0),
                'name' => $name !== '' ? $name : (string) ($staff['email'] ?? ''),
                'email' => (string) ($staff['email'] ?? ''),
                'role' => (string) ($staff['role'] ?? ''),
            ];
        }
    } catch (Throwable $e) {
        return [];
    }

    return $assignee_options;
}

function app_contract_ticket_client_options(array $user): array
{
    $client_options = [];
    foreach (function_exists('get_organizations') ? get_organizations(false) : [] as $organization) {
        $organization_id = (int) ($organization['id'] ?? 0);
        if ($organization_id <= 0) {
            continue;
        }
        if (function_exists('can_user_use_organization') && !can_user_use_organization($organization_id, $user)) {
            continue;
        }
        $client_options[] = [
            'id' => $organization_id,
            'name' => (string) ($organization['name'] ?? ''),
            'email' => (string) ($organization['contact_email'] ?? $organization['email'] ?? ''),
            'is_active' => !empty($organization['is_active']),
        ];
    }

    return $client_options;
}

function app_contract_ticket_create_options(array $user): array
{
    $statuses = app_contract_ticket_status_options();
    $priorities = app_contract_ticket_priority_options();

    $default_status_id = null;
    foreach ($statuses as $status) {
        if (!empty($status['is_default'])) {
            $default_status_id = (int) $status['id'];
            break;
        }
    }

    $default_priority_id = null;
    foreach ($priorities as $priority) {
        if (!empty($priority['is_default'])) {
            $default_priority_id = (int) $priority['id'];
            break;
        }
    }

    return [
        'clients' => app_contract_ticket_client_options($user),
        'statuses' => $statuses,
        'priorities' => $priorities,
        'assignees' => app_contract_ticket_assignee_options($user),
        'defaults' => [
            'status_id' => $default_status_id,
            'priority_id' => $default_priority_id,
            'assignee_id' => (int) ($user['id'] ?? 0),
        ],
    ];
}

function app_contract_attachment_can_preview(array $attachment): bool
{
    $mime_type = strtolower(trim((string) ($attachment['mime_type'] ?? '')));
    if ($mime_type === '') {
        return false;
    }

    if (str_starts_with($mime_type, 'image/')) {
        return true;
    }

    return in_array($mime_type, [
        'application/pdf',
        'text/plain',
        'text/csv',
    ], true);
}

function app_contract_attachment_payload(array $attachment): array
{
    $file_size = isset($attachment['file_size']) ? (int) $attachment['file_size'] : null;
    $attachment_id = (int) ($attachment['id'] ?? 0);
    $download_url = $attachment_id > 0
        ? '/api/mobile/v1/attachments/' . $attachment_id . '/download'
        : (function_exists('attachment_download_url') ? attachment_download_url($attachment) : '');
    $can_preview = app_contract_attachment_can_preview($attachment);

    return [
        'id' => $attachment_id,
        'ticket_id' => (int) ($attachment['ticket_id'] ?? 0),
        'comment_id' => !empty($attachment['comment_id']) ? (int) $attachment['comment_id'] : null,
        'filename' => (string) ($attachment['original_name'] ?? $attachment['filename'] ?? ''),
        'mime_type' => $attachment['mime_type'] ?? null,
        'file_size' => $file_size,
        'file_size_label' => ($file_size !== null && function_exists('format_file_size')) ? format_file_size($file_size) : null,
        'storage_driver' => (string) ($attachment['storage_driver'] ?? 'local'),
        'download_url' => $download_url,
        'preview_url' => $can_preview ? $download_url : null,
        'can_preview' => $can_preview,
        'created_at' => $attachment['created_at'] ?? null,
    ];
}

function app_contract_ticket_actions(array $ticket, array $user): array
{
    $statuses = function_exists('get_statuses') ? get_statuses() : [];
    $timer = app_contract_ticket_timer((int) ($ticket['id'] ?? 0), $user);
    $actions = function_exists('ticket_detail_primary_actions')
        ? ticket_detail_primary_actions($ticket, $user, $statuses, [
            'time_tracking_available' => function_exists('ticket_time_table_exists') && ticket_time_table_exists(),
            'timer_state' => (string) ($timer['state'] ?? 'stopped'),
        ])
        : [];

    $actions = array_map(static function (array $action): array {
        unset($action['onclick'], $action['visible']);
        return $action;
    }, $actions);

    return [
        'primary' => $actions,
        'statuses' => app_contract_ticket_status_options(),
        'priorities' => app_contract_ticket_priority_options(),
        'assignees' => app_contract_ticket_assignee_options($user),
        'timer' => $timer,
    ];
}

function app_contract_client_contact(array $contact): array
{
    return [
        'id' => (int) ($contact['id'] ?? 0),
        'name' => trim((string) (($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''))),
        'email' => (string) ($contact['email'] ?? ''),
        'role' => (string) ($contact['role'] ?? ''),
        'is_active' => !empty($contact['is_active']),
        'avatar' => $contact['avatar'] ?? null,
    ];
}

function app_contract_client_overview_payload(array $overview, string $view): array
{
    $organization = $overview['organization'] ?? [];
    $time = $overview['time'] ?? ['minutes' => 0, 'billable_minutes' => 0, 'billable_amount' => 0.0];
    $organization_id = (int) ($organization['id'] ?? 0);

    return [
        'client' => [
            'id' => $organization_id,
            'name' => (string) ($organization['name'] ?? ''),
            'email' => (string) ($organization['contact_email'] ?? $organization['email'] ?? ''),
            'phone' => (string) ($organization['contact_phone'] ?? $organization['phone'] ?? ''),
            'is_active' => !empty($organization['is_active']),
            'billable_rate' => isset($organization['billable_rate']) ? (float) $organization['billable_rate'] : null,
        ],
        'view' => $view,
        'counts' => $overview['counts'] ?? [],
        'tickets' => array_map('app_contract_ticket_list_item', $overview['tickets'] ?? []),
        'contacts' => array_map('app_contract_client_contact', $overview['contacts'] ?? []),
        'time' => [
            'minutes' => (int) ($time['minutes'] ?? 0),
            'billable_minutes' => (int) ($time['billable_minutes'] ?? 0),
            'billable_amount' => (float) ($time['billable_amount'] ?? 0),
            'minutes_label' => function_exists('format_duration_minutes') ? format_duration_minutes((int) ($time['minutes'] ?? 0)) : ((int) ($time['minutes'] ?? 0) . ' min'),
            'billable_amount_label' => function_exists('format_money') ? format_money((float) ($time['billable_amount'] ?? 0)) : (string) ($time['billable_amount'] ?? 0),
        ],
        'links' => [
            'tickets' => function_exists('url') ? url('tickets', ['organization_id' => $organization_id]) : '',
            'reports' => function_exists('url') ? url('admin', ['section' => 'reports', 'organizations[]' => $organization_id, 'tab' => 'detailed']) : '',
        ],
    ];
}

function app_contract_notification_summary_item(array $notification): array
{
    $actor_name = trim((string) (($notification['actor_first_name'] ?? '') . ' ' . ($notification['actor_last_name'] ?? '')));
    return [
        'id' => (int) ($notification['id'] ?? 0),
        'type' => (string) ($notification['type'] ?? ''),
        'ticket_id' => !empty($notification['ticket_id']) ? (int) $notification['ticket_id'] : null,
        'ticket_hash' => !empty($notification['ticket_hash']) ? (string) $notification['ticket_hash'] : null,
        'is_read' => !empty($notification['is_read']),
        'is_resolved' => !empty($notification['is_resolved']),
        'created_at' => $notification['created_at'] ?? null,
        'time_ago' => function_exists('notification_time_ago') ? notification_time_ago((string) ($notification['created_at'] ?? '')) : '',
        'text' => function_exists('format_notification_text') ? format_notification_text($notification) : '',
        'action_text' => function_exists('format_notification_action') ? format_notification_action($notification) : '',
        'snippet' => function_exists('get_notification_snippet') ? get_notification_snippet($notification) : '',
        'is_action' => function_exists('is_action_required_notification')
            ? is_action_required_notification((string) ($notification['type'] ?? ''), $notification['data'] ?? [])
            : false,
        'actor' => [
            'name' => $actor_name,
            'email' => $notification['actor_email'] ?? null,
            'avatar' => $notification['actor_avatar'] ?? null,
        ],
    ];
}

function app_contract_tenant_payload(array $tenant): array
{
    return [
        'id' => (int) ($tenant['id'] ?? 0),
        'name' => (string) ($tenant['name'] ?? ''),
        'slug' => (string) ($tenant['slug'] ?? ''),
        'status' => (string) ($tenant['status'] ?? ''),
        'subscription_status' => (string) ($tenant['subscription_status'] ?? ''),
        'billing_email' => (string) ($tenant['billing_email'] ?? ''),
        'billing_override_reason' => (string) ($tenant['billing_override_reason'] ?? ''),
        'trial_ends_at' => $tenant['trial_ends_at'] ?? null,
        'suspended_at' => $tenant['suspended_at'] ?? null,
    ];
}
