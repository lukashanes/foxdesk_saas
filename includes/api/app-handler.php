<?php
/**
 * API Handler: application shell contract.
 */

function api_app_contract_envelope(array $data, array $meta = []): array
{
    return [
        'data' => $data,
        'meta' => array_merge([
            'schema_version' => function_exists('app_contract_schema_version') ? app_contract_schema_version() : 1,
            'generated_at' => date('c'),
        ], $meta),
        'errors' => [],
    ];
}

function api_app_contract_success(array $data, array $meta = [], array $legacy = []): void
{
    api_success(array_merge($legacy, api_app_contract_envelope($data, $meta)));
}

function api_app_clamp_limit($value, int $default = 25, int $max = 100): int
{
    $limit = (int) ($value ?? $default);
    return max(1, min($max, $limit));
}

function api_app_require_write_auth(): void
{
    if (empty($GLOBALS['is_mobile_token_auth']) && empty($GLOBALS['is_api_token_auth'])) {
        require_csrf_token(true);
    }
}

function api_app_require_timer_functions(): void
{
    if (!function_exists('get_active_ticket_timer') && defined('BASE_PATH') && file_exists(BASE_PATH . '/includes/ticket-time-functions.php')) {
        require_once BASE_PATH . '/includes/ticket-time-functions.php';
    }
}

function api_app_require_api_token_scope(string $scope): void
{
    if (empty($GLOBALS['is_api_token_auth'])) {
        return;
    }

    if (function_exists('api_token_has_scope') && api_token_has_scope($scope)) {
        return;
    }

    api_error('API token scope is not allowed for this action.', 403);
}

function api_app_shell()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        api_error('Method not allowed', 405);
    }

    $user = current_user();
    if (!$user) {
        api_error('Unauthorized', 401);
    }

    if (!function_exists('app_shell_payload')) {
        api_error('App shell is not available.', 500);
    }

    $app_shell = app_shell_payload($user);
    api_app_contract_success(
        ['app_shell' => $app_shell],
        ['resource' => 'app_shell'],
        ['app_shell' => $app_shell]
    );
}

function api_app_home()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        api_error('Method not allowed', 405);
    }

    $user = current_user();
    if (!$user) {
        api_error('Unauthorized', 401);
    }

    if (!function_exists('app_shell_payload') || !function_exists('app_feed_payload')) {
        api_error('App home is not available.', 500);
    }

    $limit = (int) ($_GET['limit'] ?? 5);

    $app_shell = app_shell_payload($user);
    $home = app_feed_payload($user, $limit, [
        'period' => (string) ($_GET['period'] ?? 'last_30_days'),
        'time_scope' => (string) ($_GET['time_scope'] ?? 'mine'),
    ]);

    api_app_contract_success(
        ['app_shell' => $app_shell, 'home' => $home],
        ['resource' => 'app_home'],
        ['app_shell' => $app_shell, 'home' => $home]
    );
}

function api_app_resolve_ticket(array $source, array $user)
{
    $hash = trim((string) ($source['hash'] ?? $source['ticket_hash'] ?? ''));
    $ticket_id = (int) ($source['id'] ?? $source['ticket_id'] ?? 0);
    $ticket = null;

    if ($hash !== '') {
        $ticket = get_ticket_by_hash($hash);
        if (!$ticket
            && function_exists('is_platform_admin')
            && is_platform_admin($user)
            && function_exists('get_ticket_by_hash_unscoped')) {
            $ticket = get_ticket_by_hash_unscoped($hash);
        }
    } elseif ($ticket_id > 0) {
        $ticket = get_ticket($ticket_id);
        if (!$ticket
            && function_exists('is_platform_admin')
            && is_platform_admin($user)
            && function_exists('get_ticket_unscoped')) {
            $ticket = get_ticket_unscoped($ticket_id);
        }
    } else {
        api_error('Provide ticket hash or id.', 422);
    }

    if (!$ticket) {
        api_error('Ticket not found.', 404);
    }

    if (!can_see_ticket($ticket, $user)) {
        if (function_exists('log_security_event')) {
            log_security_event('app_ticket_access_denied', (int) ($user['id'] ?? 0), json_encode([
                'ticket_id' => (int) ($ticket['id'] ?? 0),
            ]));
        }
        api_error('Forbidden', 403);
    }

    return $ticket;
}

function api_app_comment_time_requested(array $input): bool
{
    foreach ([
        'duration_minutes',
        'manual_duration_minutes',
        'started_at',
        'ended_at',
        'manual_date',
        'manual_start_time',
        'manual_end_time',
    ] as $key) {
        if (array_key_exists($key, $input) && trim((string) $input[$key]) !== '') {
            return true;
        }
    }

    return false;
}

function api_app_bool(array $input, string $key, bool $default = false): bool
{
    if (!array_key_exists($key, $input)) {
        return $default;
    }

    $value = $input[$key];
    if (is_bool($value)) {
        return $value;
    }
    if (is_numeric($value)) {
        return (int) $value === 1;
    }

    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
}

function api_app_normalize_datetime_for_time($value, string $field): string
{
    if (!function_exists('foxdesk_normalize_backdated_datetime_input')) {
        api_error('Date normalization is not available.', 500);
    }

    $normalized = foxdesk_normalize_backdated_datetime_input($value);
    if ($normalized === false || $normalized === null) {
        api_error($field . ' is invalid.', 422);
    }

    return $normalized;
}

function api_app_resolve_comment_time_input(array $input, array $user, string $fallback_end_at): ?array
{
    if (!api_app_comment_time_requested($input)) {
        return null;
    }

    if (!is_agent() && !is_admin()) {
        api_error('Forbidden', 403);
    }

    api_app_require_api_token_scope('tickets:read');
    api_app_require_api_token_scope('comments:write');
    api_app_require_api_token_scope('time:write');

    if (!function_exists('add_manual_time_entry')) {
        api_error('Time tracking is not available.', 400);
    }

    $duration = (int) ($input['duration_minutes'] ?? $input['manual_duration_minutes'] ?? 0);
    $started_raw = trim((string) ($input['started_at'] ?? ''));
    $ended_raw = trim((string) ($input['ended_at'] ?? ''));

    $manual_start = trim((string) ($input['manual_start_time'] ?? ''));
    $manual_end = trim((string) ($input['manual_end_time'] ?? ''));
    if ($manual_start !== '' || $manual_end !== '') {
        $manual_date = trim((string) ($input['manual_date'] ?? ''));
        if ($manual_date === '') {
            api_error('manual_date is required when manual_start_time or manual_end_time is provided.', 422);
        }
        if ($manual_start === '' || $manual_end === '') {
            api_error('manual_start_time and manual_end_time are required together.', 422);
        }

        $started_raw = $manual_date . 'T' . $manual_start;
        $end_date = $manual_date;
        if ($manual_end < $manual_start) {
            $end_date = date('Y-m-d', strtotime($manual_date . ' +1 day'));
        }
        $ended_raw = $end_date . 'T' . $manual_end;
    }

    if ($started_raw !== '' || $ended_raw !== '') {
        if ($started_raw === '' || $ended_raw === '') {
            api_error('started_at and ended_at are required together.', 422);
        }
        if (!foxdesk_can_backdate_records($user)) {
            api_error('Only admins and agents can set historical dates.', 403);
        }

        $started_at = api_app_normalize_datetime_for_time($started_raw, 'started_at');
        $ended_at = api_app_normalize_datetime_for_time($ended_raw, 'ended_at');
        $start_ts = strtotime($started_at);
        $end_ts = strtotime($ended_at);
        if (!$start_ts || !$end_ts || $end_ts <= $start_ts) {
            api_error('ended_at must be after started_at.', 422);
        }

        $computed_duration = max(1, (int) floor(($end_ts - $start_ts) / 60));
        if ($duration > 0 && $duration !== $computed_duration) {
            api_error('duration_minutes must match started_at and ended_at.', 422);
        }
        $duration = $computed_duration;

        return [
            'started_at' => $started_at,
            'ended_at' => $ended_at,
            'duration_minutes' => $duration,
            'is_billable' => api_app_bool($input, 'is_billable', true) ? 1 : 0,
            'summary' => trim((string) ($input['time_summary'] ?? $input['summary'] ?? '')) ?: null,
            'source' => 'manual',
        ];
    }

    if ($duration < 1 || $duration > 1440) {
        api_error('duration_minutes must be between 1 and 1440.', 422);
    }

    $ended_at = api_app_normalize_datetime_for_time($fallback_end_at, 'created_at');
    $end_dt = new DateTime($ended_at);
    $start_dt = (clone $end_dt)->modify('-' . $duration . ' minutes');

    return [
        'started_at' => $start_dt->format('Y-m-d H:i:s'),
        'ended_at' => $end_dt->format('Y-m-d H:i:s'),
        'duration_minutes' => $duration,
        'is_billable' => api_app_bool($input, 'is_billable', true) ? 1 : 0,
        'summary' => trim((string) ($input['time_summary'] ?? $input['summary'] ?? '')) ?: null,
        'source' => 'manual',
    ];
}

function api_app_resolve_log_time_input(array $input): array
{
    $duration = (int) ($input['duration_minutes'] ?? 0);
    if ($duration < 1 || $duration > 1440) {
        api_error('duration_minutes must be between 1 and 1440.', 422);
    }

    $started_raw = trim((string) ($input['started_at'] ?? ''));
    $ended_raw = trim((string) ($input['ended_at'] ?? ''));
    $started_at = $started_raw !== '' ? api_app_normalize_datetime_for_time($started_raw, 'started_at') : null;
    $ended_at = $ended_raw !== '' ? api_app_normalize_datetime_for_time($ended_raw, 'ended_at') : null;

    if ($started_at !== null && $ended_at !== null) {
        $start_ts = strtotime($started_at);
        $end_ts = strtotime($ended_at);
        if (!$start_ts || !$end_ts || $end_ts <= $start_ts) {
            api_error('ended_at must be after started_at.', 422);
        }
        $computed_duration = max(1, (int) floor(($end_ts - $start_ts) / 60));
        if ($computed_duration !== $duration) {
            api_error('duration_minutes must match started_at and ended_at.', 422);
        }
    } elseif ($started_at !== null) {
        $ended_at = date('Y-m-d H:i:s', strtotime($started_at) + ($duration * 60));
    } elseif ($ended_at !== null) {
        $started_at = date('Y-m-d H:i:s', strtotime($ended_at) - ($duration * 60));
    } else {
        $ended_at = date('Y-m-d H:i:s');
        $started_at = date('Y-m-d H:i:s', strtotime($ended_at) - ($duration * 60));
    }

    return [
        'started_at' => $started_at,
        'ended_at' => $ended_at,
        'duration_minutes' => $duration,
    ];
}

function api_app_ticket_list()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        api_error('Method not allowed', 405);
    }

    $user = current_user();
    if (!$user) {
        api_error('Unauthorized', 401);
    }
    if (!function_exists('get_tickets') || !function_exists('ticket_list_view_apply_filters')) {
        api_error('Ticket list contract is not available.', 500);
    }

    $limit = api_app_clamp_limit($_GET['limit'] ?? null, 25, 100);
    $offset = max(0, (int) ($_GET['offset'] ?? 0));
    $view = ticket_list_view_normalize($_GET['view'] ?? $_GET['work_view'] ?? 'open', true);
    $base_filters = app_contract_ticket_filters_from_request($_GET, $user, $limit, $offset);
    $filters = ticket_list_view_apply_filters($base_filters, $view);

    $count_filters = $filters;
    unset($count_filters['limit'], $count_filters['offset']);

    $count_base_filters = $base_filters;
    unset($count_base_filters['limit'], $count_base_filters['offset']);

    $tickets = array_map('app_contract_ticket_list_item', get_tickets($filters));
    $total = function_exists('get_tickets_count') ? get_tickets_count($count_filters) : count($tickets);
    $counts = function_exists('ticket_list_view_counts') ? ticket_list_view_counts($count_base_filters, true) : [];
    $views = function_exists('ticket_list_view_definitions') ? ticket_list_view_definitions(true) : [];

    api_app_contract_success([
        'tickets' => $tickets,
        'view' => $view,
        'views' => $views,
        'counts' => $counts,
        'pagination' => [
            'limit' => $limit,
            'offset' => $offset,
            'total' => $total,
            'has_more' => ($offset + $limit) < $total,
        ],
        'filters' => [
            'search' => (string) ($_GET['search'] ?? ''),
            'sort' => (string) ($base_filters['sort'] ?? 'last_updated'),
        ],
    ], ['resource' => 'ticket_list']);
}

function api_app_ticket_actions()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        api_error('Method not allowed', 405);
    }

    $user = current_user();
    if (!$user) {
        api_error('Unauthorized', 401);
    }

    $ticket = api_app_resolve_ticket($_GET, $user);
    api_app_contract_success([
        'ticket' => app_contract_ticket_payload($ticket),
        'actions' => app_contract_ticket_actions($ticket, $user),
    ], ['resource' => 'ticket_actions']);
}

function api_app_ticket_create_options()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        api_error('Method not allowed', 405);
    }

    $user = current_user();
    if (!$user) {
        api_error('Unauthorized', 401);
    }

    if (!function_exists('app_contract_ticket_create_options')) {
        api_error('Ticket create options are not available.', 500);
    }

    api_app_contract_success(app_contract_ticket_create_options($user), ['resource' => 'ticket_create_options']);
}

function api_app_ticket_comments(int $ticket_id, bool $include_internal): array
{
    $comments = [];
    foreach (function_exists('get_ticket_comments') ? get_ticket_comments($ticket_id) : [] as $comment) {
        if (!$include_internal && !empty($comment['is_internal'])) {
            continue;
        }

        $comments[] = [
            'id' => (int) ($comment['id'] ?? 0),
            'user_id' => (int) ($comment['user_id'] ?? 0),
            'author_name' => trim((string) (($comment['first_name'] ?? '') . ' ' . ($comment['last_name'] ?? ''))),
            'author_email' => $comment['email'] ?? null,
            'content_html' => (string) ($comment['content'] ?? ''),
            'content_text' => function_exists('app_contract_plain_text')
                ? app_contract_plain_text($comment['content'] ?? '')
                : trim(strip_tags((string) ($comment['content'] ?? ''))),
            'is_internal' => !empty($comment['is_internal']),
            'created_at' => $comment['created_at'] ?? null,
        ];
    }

    return $comments;
}

function api_app_ticket_attachments(int $ticket_id, array $visible_comments): array
{
    $visible_comment_ids = [];
    foreach ($visible_comments as $comment) {
        $visible_comment_ids[(int) ($comment['id'] ?? 0)] = true;
    }

    $attachments = [];
    foreach (function_exists('get_ticket_attachments') ? get_ticket_attachments($ticket_id) : [] as $attachment) {
        $comment_id = (int) ($attachment['comment_id'] ?? 0);
        if ($comment_id > 0 && !isset($visible_comment_ids[$comment_id])) {
            continue;
        }

        $attachments[] = function_exists('app_contract_attachment_payload')
            ? app_contract_attachment_payload($attachment)
            : [
                'id' => (int) ($attachment['id'] ?? 0),
                'comment_id' => $comment_id ?: null,
                'filename' => (string) ($attachment['original_name'] ?? $attachment['filename'] ?? ''),
                'mime_type' => $attachment['mime_type'] ?? null,
                'file_size' => isset($attachment['file_size']) ? (int) $attachment['file_size'] : null,
                'download_url' => function_exists('attachment_download_url') ? attachment_download_url($attachment) : '',
                'created_at' => $attachment['created_at'] ?? null,
            ];
    }

    return $attachments;
}

function api_app_resolve_attachment(array $source, array $user): array
{
    $attachment_id = (int) ($source['attachment_id'] ?? $source['id'] ?? 0);
    if ($attachment_id <= 0) {
        api_error('Missing attachment_id.', 422);
    }

    if (!function_exists('get_attachment') || !function_exists('attachment_user_can_access')) {
        api_error('Attachment metadata is not available.', 500);
    }

    $attachment = get_attachment($attachment_id);
    if (!$attachment) {
        api_error('Attachment not found.', 404);
    }

    if (!empty($attachment['comment_id']) && !array_key_exists('comment_is_internal', $attachment)) {
        try {
            $comment = db_fetch_one('SELECT is_internal FROM comments WHERE id = ? LIMIT 1', [(int) $attachment['comment_id']]);
            $attachment['comment_is_internal'] = !empty($comment['is_internal']);
        } catch (Throwable $e) {
            $attachment['comment_is_internal'] = false;
        }
    }

    if (!attachment_user_can_access($attachment, $user)) {
        if (function_exists('log_security_event')) {
            log_security_event('app_attachment_access_denied', (int) ($user['id'] ?? 0), json_encode([
                'attachment_id' => $attachment_id,
                'ticket_id' => (int) ($attachment['ticket_id'] ?? 0),
            ]));
        }
        api_error('Forbidden', 403);
    }

    return $attachment;
}

function api_app_attachment_metadata()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        api_error('Method not allowed', 405);
    }

    $user = current_user();
    if (!$user) {
        api_error('Unauthorized', 401);
    }

    $attachment = api_app_resolve_attachment($_GET, $user);

    api_app_contract_success([
        'attachment' => app_contract_attachment_payload($attachment),
    ], ['resource' => 'attachment_metadata']);
}

function api_app_attachment_download(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        api_error('Method not allowed', 405);
    }

    $user = current_user();
    if (!$user) {
        api_error('Unauthorized', 401);
    }

    $attachment = api_app_resolve_attachment($_GET, $user);
    $mime = trim((string) ($attachment['mime_type'] ?? '')) ?: 'application/octet-stream';
    $filename = basename((string) ($attachment['original_name'] ?? $attachment['filename'] ?? 'attachment'));
    $disposition = str_starts_with($mime, 'image/') ? 'inline' : 'attachment';

    header('Content-Type: ' . $mime);
    header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $filename) . '"');
    header('Cache-Control: private, max-age=3600');
    header('X-Content-Type-Options: nosniff');

    if (($attachment['storage_driver'] ?? '') === 'r2' && function_exists('storage_read_object')) {
        try {
            $body = storage_read_object($attachment);
        } catch (Throwable $e) {
            error_log('R2 mobile attachment read failed: ' . $e->getMessage());
            $body = null;
        }
        if ($body === null) {
            http_response_code(404);
            exit;
        }
        header('Content-Length: ' . strlen($body));
        echo $body;
        exit;
    }

    $path = function_exists('attachment_absolute_path') ? attachment_absolute_path($attachment) : '';
    $real_path = $path !== '' ? realpath($path) : false;
    $allowed_roots = [
        realpath(BASE_PATH . '/' . trim((defined('UPLOAD_DIR') ? UPLOAD_DIR : 'uploads/'), '/\\')),
        realpath(BASE_PATH . '/storage/tickets'),
    ];
    $allowed = false;
    if ($real_path !== false && is_file($real_path)) {
        foreach (array_filter($allowed_roots) as $root) {
            if ($real_path === $root || str_starts_with($real_path, $root . DIRECTORY_SEPARATOR)) {
                $allowed = true;
                break;
            }
        }
    }
    if (!$allowed) {
        http_response_code(404);
        exit;
    }

    header('Content-Length: ' . filesize($real_path));
    $handle = fopen($real_path, 'rb');
    if ($handle === false) {
        http_response_code(404);
        exit;
    }
    fpassthru($handle);
    fclose($handle);
    exit;
}

function api_app_ticket_time_entries(int $ticket_id): array
{
    if (!is_agent() || !function_exists('get_ticket_time_entries')) {
        return [];
    }

    $entries = [];
    foreach (get_ticket_time_entries($ticket_id) as $entry) {
        $entries[] = [
            'id' => (int) ($entry['id'] ?? 0),
            'comment_id' => !empty($entry['comment_id']) ? (int) $entry['comment_id'] : null,
            'user_name' => trim((string) (($entry['first_name'] ?? '') . ' ' . ($entry['last_name'] ?? ''))),
            'started_at' => $entry['started_at'] ?? null,
            'ended_at' => $entry['ended_at'] ?? null,
            'duration_minutes' => (int) ($entry['duration_minutes'] ?? 0),
            'summary' => $entry['summary'] ?? null,
            'is_billable' => !empty($entry['is_billable']),
        ];
    }

    return $entries;
}

function api_app_ticket_detail()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        api_error('Method not allowed', 405);
    }

    $user = current_user();
    if (!$user) {
        api_error('Unauthorized', 401);
    }

    $ticket = api_app_resolve_ticket($_GET, $user);
    $ticket_id = (int) $ticket['id'];
    if (function_exists('get_ticket_time_total')) {
        $ticket['worked_minutes'] = get_ticket_time_total($ticket_id);
    }
    $comments = api_app_ticket_comments($ticket_id, is_agent());

    $payload = [
        'ticket' => app_contract_ticket_payload($ticket),
        'comments' => $comments,
        'attachments' => api_app_ticket_attachments($ticket_id, $comments),
        'time_entries' => api_app_ticket_time_entries($ticket_id),
        'actions' => app_contract_ticket_actions($ticket, $user),
    ];

    api_app_contract_success($payload, ['resource' => 'ticket_detail'], $payload);
}

function api_app_update_ticket()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_error('Method not allowed', 405);
    }

    api_app_require_write_auth();
    api_app_require_api_token_scope('tickets:read');
    api_app_require_api_token_scope('tickets:write');

    $user = current_user();
    if (!$user) {
        api_error('Unauthorized', 401);
    }

    if (!is_agent() && !is_admin()) {
        api_error('Forbidden', 403);
    }

    $input = get_json_input();
    $ticket = api_app_resolve_ticket($input, $user);
    if (!can_edit_ticket($ticket, $user)) {
        api_error('Forbidden', 403);
    }

    $ticket_id = (int) $ticket['id'];
    $updates = [];
    $updated_fields = [];
    $events = [];

    if (array_key_exists('status_id', $input)) {
        $status_id = (int) ($input['status_id'] ?? 0);
        if ($status_id <= 0) {
            api_error('Status is required.', 422);
        }
        $new_status = get_status($status_id);
        if (!$new_status) {
            api_error('Status not found.', 404);
        }
        if ((int) ($ticket['status_id'] ?? 0) !== $status_id) {
            $updates['status_id'] = $status_id;
            $old_status = get_status((int) ($ticket['status_id'] ?? 0));
            $updated_fields[] = 'status_id';
            $events[] = [
                'name' => 'ticket.status_changed',
                'extra' => [
                    'old_status' => $old_status['name'] ?? '',
                    'new_status' => $new_status['name'] ?? '',
                ],
                'activity' => 'status_changed',
                'message' => 'Status changed from "' . ($old_status['name'] ?? 'Unknown') . '" to "' . ($new_status['name'] ?? 'Unknown') . '"',
                'history_field' => 'status_id',
                'old_value' => (int) ($ticket['status_id'] ?? 0),
                'new_value' => $status_id,
            ];
        }
    }

    if (array_key_exists('priority_id', $input)) {
        $priority_raw = $input['priority_id'];
        $priority_id = $priority_raw === null || $priority_raw === '' ? null : (int) $priority_raw;
        $priority = null;
        if ($priority_id !== null) {
            $priority = get_priority($priority_id);
            if (!$priority) {
                api_error('Priority not found.', 404);
            }
        }
        $old_priority_id = isset($ticket['priority_id']) ? (int) $ticket['priority_id'] : null;
        if ($old_priority_id !== $priority_id) {
            $updates['priority_id'] = $priority_id;
            $updated_fields[] = 'priority_id';
            $events[] = [
                'name' => 'ticket.priority_changed',
                'extra' => [
                    'priority' => $priority['name'] ?? '',
                ],
                'activity' => 'ticket_edited',
                'message' => 'Priority changed' . (!empty($priority['name']) ? ' to ' . $priority['name'] : ''),
                'history_field' => 'priority_id',
                'old_value' => $old_priority_id,
                'new_value' => $priority_id,
            ];
        }
    }

    if (array_key_exists('assignee_id', $input)) {
        $assignee_raw = $input['assignee_id'];
        $assignee_id = $assignee_raw === null || $assignee_raw === '' ? null : (int) $assignee_raw;
        $assignee = null;
        if ($assignee_id !== null) {
            $assignee = function_exists('api_get_active_staff_user_by_id')
                ? api_get_active_staff_user_by_id($assignee_id)
                : get_user($assignee_id);
            if (!$assignee || !can_user_assign_to_staff($assignee, $user)) {
                api_error('Forbidden', 403);
            }
        }
        $old_assignee_id = isset($ticket['assignee_id']) ? (int) $ticket['assignee_id'] : null;
        if ($old_assignee_id !== $assignee_id) {
            $updates['assignee_id'] = $assignee_id;
            $updated_fields[] = 'assignee_id';
            $assignee_name = $assignee ? trim((string) (($assignee['first_name'] ?? '') . ' ' . ($assignee['last_name'] ?? ''))) : '';
            $events[] = [
                'name' => $assignee_id ? 'ticket.assigned' : 'ticket.updated',
                'extra' => [
                    'assignee_id' => $assignee_id,
                    'assignee_name' => $assignee_name,
                    'field' => 'assignee',
                ],
                'activity' => $assignee_id ? 'assigned' : 'unassigned',
                'message' => $assignee_id ? 'Ticket assigned to ' . ($assignee_name !== '' ? $assignee_name : ('#' . $assignee_id)) : 'Assignment removed',
                'history_field' => 'assignee_id',
                'old_value' => $old_assignee_id,
                'new_value' => $assignee_id,
            ];
        }
    }

    if (array_key_exists('due_date', $input)) {
        $due_date_input = trim((string) ($input['due_date'] ?? ''));
        $due_date = normalize_due_date_input($due_date_input);
        if ($due_date_input !== '' && $due_date === false) {
            api_error('Due date is invalid.', 422);
        }
        $old_due_date = $ticket['due_date'] ?? null;
        if ((string) ($old_due_date ?? '') !== (string) ($due_date ?? '')) {
            $updates['due_date'] = $due_date;
            $updated_fields[] = 'due_date';
            $events[] = [
                'name' => 'ticket.updated',
                'extra' => [
                    'field' => 'due_date',
                    'detail' => $due_date ? (function_exists('format_date') ? format_date($due_date) : $due_date) : '',
                ],
                'activity' => $due_date ? 'due_date_updated' : 'due_date_removed',
                'message' => $due_date ? 'Due date set to ' . (function_exists('format_date') ? format_date($due_date) : $due_date) : 'Due date removed',
                'history_field' => 'due_date',
                'old_value' => $old_due_date,
                'new_value' => $due_date,
            ];
        }
    }

    if (empty($updates)) {
        $fresh_ticket = get_ticket($ticket_id) ?: $ticket;
        api_app_contract_success([
            'ticket' => app_contract_ticket_payload($fresh_ticket),
            'actions' => app_contract_ticket_actions($fresh_ticket, $user),
            'updated_fields' => [],
        ], ['resource' => 'update_ticket']);
    }

    if (!update_ticket($ticket_id, $updates)) {
        api_error('Failed to update ticket.', 500);
    }

    foreach ($events as $event) {
        if (function_exists('log_ticket_history') && isset($event['history_field'])) {
            log_ticket_history($ticket_id, (int) $user['id'], (string) $event['history_field'], $event['old_value'], $event['new_value']);
        }
        if (function_exists('log_activity')) {
            log_activity($ticket_id, (int) $user['id'], (string) $event['activity'], (string) $event['message']);
        }
        if ($event['history_field'] === 'assignee_id' && !empty($event['new_value']) && function_exists('add_ticket_access')) {
            add_ticket_access($ticket_id, (int) $event['new_value'], (int) $user['id']);
        }
        if ($event['history_field'] === 'assignee_id' && !empty($event['old_value']) && function_exists('resolve_action_notifications')) {
            resolve_action_notifications($ticket_id, (int) $event['old_value']);
        }
        if (function_exists('ticket_event_dispatch_in_app')) {
            ticket_event_dispatch_in_app((string) $event['name'], $ticket_id, (int) $user['id'], (array) $event['extra']);
        }
    }

    $fresh_ticket = get_ticket($ticket_id) ?: $ticket;
    api_app_contract_success([
        'ticket' => app_contract_ticket_payload($fresh_ticket),
        'actions' => app_contract_ticket_actions($fresh_ticket, $user),
        'updated_fields' => $updated_fields,
    ], ['resource' => 'update_ticket']);
}

function api_app_create_ticket()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_error('Method not allowed', 405);
    }

    api_app_require_write_auth();

    $user = current_user();
    if (!$user) {
        api_error('Unauthorized', 401);
    }

    $input = get_json_input();
    $title = trim((string) ($input['title'] ?? ''));
    if ($title === '') {
        api_error('Ticket title is required.', 422);
    }
    $skip_notification = api_app_bool($input, 'skip_notification', false);

    $owner_id = isset($input['user_id']) ? (int) $input['user_id'] : (int) $user['id'];
    $owner = get_user($owner_id);
    if (!$owner || !can_user_create_ticket_for($owner, $user)) {
        api_error('Forbidden', 403);
    }

    $data = [
        'title' => $title,
        'description' => (string) ($input['description'] ?? ''),
        'user_id' => $owner_id,
        'type' => (string) ($input['type'] ?? 'general'),
    ];

    if (!empty($input['status_id'])) {
        if (!get_status((int) $input['status_id'])) {
            api_error('Status not found.', 404);
        }
        $data['status_id'] = (int) $input['status_id'];
    }

    if (!empty($input['priority_id'])) {
        if (!get_priority((int) $input['priority_id'])) {
            api_error('Priority not found.', 404);
        }
        $data['priority_id'] = (int) $input['priority_id'];
    }

    if (array_key_exists('organization_id', $input)) {
        $organization_id = $input['organization_id'] ? (int) $input['organization_id'] : null;
        if ($organization_id !== null && !can_user_use_organization($organization_id, $user)) {
            api_error('Forbidden', 403);
        }
        $data['organization_id'] = $organization_id;
    }

    if (!empty($input['assignee_id'])) {
        $assignee = get_user((int) $input['assignee_id']);
        if (!$assignee || !can_user_assign_to_staff($assignee, $user)) {
            api_error('Forbidden', 403);
        }
        $data['assignee_id'] = (int) $input['assignee_id'];
    }

    if (!empty($input['due_date'])) {
        $due_date = normalize_due_date_input($input['due_date']);
        if ($due_date === false) {
            api_error('Due date is invalid.', 422);
        }
        $data['due_date'] = $due_date;
    }
    if (array_key_exists('created_at', $input) && trim((string) $input['created_at']) !== '') {
        if (!foxdesk_can_backdate_records($user)) {
            api_error('Only admins and agents can set historical dates.', 403);
        }
        $created_at = foxdesk_normalize_backdated_datetime_input($input['created_at']);
        if ($created_at === false) {
            api_error('Created date is invalid.', 422);
        }
        if ($created_at !== null) {
            $data['created_at'] = $created_at;
            $data['allow_backdated_created_at'] = true;
        }
    }

    if (!empty($input['tags'])) {
        $data['tags'] = function_exists('normalize_ticket_tags')
            ? normalize_ticket_tags((string) $input['tags'])
            : (string) $input['tags'];
    }

    $ticket_id = create_ticket($data);
    if (!$ticket_id) {
        api_error('Failed to create ticket.', 500);
    }

    $ticket = get_ticket((int) $ticket_id);
    if (!$skip_notification && function_exists('ticket_event_dispatch_in_app')) {
        ticket_event_dispatch_in_app('ticket.created', (int) $ticket_id, (int) $user['id'], [
            'comment_preview' => mb_substr(trim(strip_tags((string) ($input['description'] ?? ''))), 0, 80),
        ]);
    }

    $response = [
        'ticket_id' => (int) $ticket_id,
        'ticket_hash' => $ticket['hash'] ?? null,
        'ticket_code' => function_exists('get_ticket_code') ? get_ticket_code((int) $ticket_id) : ('TK-' . (int) $ticket_id),
        'ticket' => $ticket ? app_contract_ticket_payload($ticket) : null,
    ];

    api_app_contract_success($response, ['resource' => 'create_ticket'], $response);
}

function api_app_add_comment()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_error('Method not allowed', 405);
    }

    api_app_require_write_auth();
    api_app_require_api_token_scope('tickets:read');
    api_app_require_api_token_scope('comments:write');

    $user = current_user();
    if (!$user) {
        api_error('Unauthorized', 401);
    }

    $input = get_json_input();
    $content = trim((string) ($input['content'] ?? ''));
    if ($content === '') {
        api_error('Comment content is required.', 422);
    }

    $ticket = api_app_resolve_ticket($input, $user);
    $ticket_id = (int) $ticket['id'];
    $is_internal = !empty($input['is_internal']) && is_agent();
    $time_required = !empty($GLOBALS['api_app_comment_time_required']);
    $time_requested = api_app_comment_time_requested($input);
    if ($time_required && !$time_requested) {
        api_error('Time fields are required for this endpoint.', 422);
    }

    $comment_created_at = date('Y-m-d H:i:s');
    $explicit_comment_created_at = array_key_exists('created_at', $input) && trim((string) $input['created_at']) !== '';
    if (!$explicit_comment_created_at && array_key_exists('comment_created_at', $input) && trim((string) $input['comment_created_at']) !== '') {
        $input['created_at'] = $input['comment_created_at'];
        $explicit_comment_created_at = true;
    }
    if ($explicit_comment_created_at) {
        if (!foxdesk_can_backdate_records($user)) {
            api_error('Only admins and agents can set historical dates.', 403);
        }
        $created_at = foxdesk_normalize_backdated_datetime_input($input['created_at']);
        if ($created_at === false) {
            api_error('Created date is invalid.', 422);
        }
        if ($created_at !== null) {
            $comment_created_at = $created_at;
        }
    }

    $time_payload = api_app_resolve_comment_time_input($input, $user, $comment_created_at);
    if ($time_payload && !$explicit_comment_created_at) {
        $comment_created_at = (string) $time_payload['ended_at'];
    }

    $comment_id = null;
    $time_entry_id = null;
    $db = get_db();
    $started_transaction = false;

    try {
        if (!$db->inTransaction()) {
            $db->beginTransaction();
            $started_transaction = true;
        }

        $comment_id = add_comment($ticket_id, (int) $user['id'], $content, $is_internal ? 1 : 0, [
            'created_at' => $comment_created_at,
            'time_spent' => (int) ($time_payload['duration_minutes'] ?? 0),
        ]);
        if (!$comment_id) {
            throw new RuntimeException('Failed to add comment.');
        }

        log_activity($ticket_id, (int) $user['id'], 'commented', 'Comment added');

        if ($time_payload) {
            $time_entry_id = add_manual_time_entry($ticket_id, (int) $user['id'], [
                'comment_id' => (int) $comment_id,
                'started_at' => $time_payload['started_at'],
                'ended_at' => $time_payload['ended_at'],
                'duration_minutes' => (int) $time_payload['duration_minutes'],
                'summary' => $time_payload['summary'],
                'is_billable' => (int) $time_payload['is_billable'],
                'source' => 'manual',
            ]);
            if (!$time_entry_id) {
                throw new RuntimeException('Failed to log time.');
            }
            log_activity($ticket_id, (int) $user['id'], 'time_manual', 'Manual time added (' . (int) $time_payload['duration_minutes'] . ' min)');
        }

        if ($started_transaction) {
            $db->commit();
        }
    } catch (Throwable $e) {
        if ($started_transaction && $db->inTransaction()) {
            $db->rollBack();
        }
        api_error($time_payload ? 'Failed to add comment with time.' : 'Failed to add comment.', 500);
    }

    $response = [
        'ticket_id' => $ticket_id,
        'comment_id' => (int) $comment_id,
    ];
    if ($time_payload) {
        $response['time_entry_id'] = (int) $time_entry_id;
        $response['duration_minutes'] = (int) $time_payload['duration_minutes'];
        $response['started_at'] = (string) $time_payload['started_at'];
        $response['ended_at'] = (string) $time_payload['ended_at'];
    }

    $skip_notification = api_app_bool($input, 'skip_notification', false);
    if (!$is_internal && !$skip_notification && function_exists('ticket_event_dispatch_in_app')) {
        $preview = mb_strlen($content) > 80 ? mb_substr($content, 0, 77) . '...' : $content;
        ticket_event_dispatch_in_app(ticket_event_comment_name($user, false), $ticket_id, (int) $user['id'], [
            'comment_preview' => strip_tags($preview),
            'comment_id' => (int) $comment_id,
        ]);
    }

    api_app_contract_success($response, ['resource' => 'ticket_comment'], $response);
}

function api_app_add_comment_with_time()
{
    $GLOBALS['api_app_comment_time_required'] = true;
    api_app_add_comment();
}

function api_app_log_time()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_error('Method not allowed', 405);
    }

    api_app_require_write_auth();
    api_app_require_api_token_scope('tickets:read');
    api_app_require_api_token_scope('time:write');

    $user = current_user();
    if (!$user) {
        api_error('Unauthorized', 401);
    }
    if (!is_agent() && !is_admin()) {
        api_error('Forbidden', 403);
    }

    $input = get_json_input();
    $time_input = api_app_resolve_log_time_input($input);
    $duration = (int) $time_input['duration_minutes'];

    $ticket = api_app_resolve_ticket($input, $user);
    $ticket_id = (int) $ticket['id'];
    if (!function_exists('add_manual_time_entry')) {
        api_error('Time tracking is not available.', 400);
    }

    $entry_id = add_manual_time_entry($ticket_id, (int) $user['id'], [
        'started_at' => $time_input['started_at'],
        'ended_at' => $time_input['ended_at'],
        'duration_minutes' => $duration,
        'summary' => $input['summary'] ?? null,
        'is_billable' => isset($input['is_billable']) ? (!empty($input['is_billable']) ? 1 : 0) : 1,
        'source' => (function_exists('is_ai_user') && is_ai_user((int) $user['id'])) ? 'ai' : 'manual',
    ]);

    if (!$entry_id) {
        api_error('Failed to log time.', 500);
    }

    $response = [
        'ticket' => app_contract_ticket_payload($ticket),
        'time_entry_id' => (int) $entry_id,
        'duration_minutes' => $duration,
    ];

    api_app_contract_success($response, ['resource' => 'log_time'], $response);
}

function api_app_delete_comment()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_error('Method not allowed', 405);
    }

    api_app_require_write_auth();
    api_app_require_api_token_scope('tickets:read');
    api_app_require_api_token_scope('comments:write');
    api_app_require_api_token_scope('delete:write');

    $user = current_user();
    if (!$user) {
        api_error('Unauthorized', 401);
    }

    $input = get_json_input();
    $comment_id = (int) ($input['comment_id'] ?? $input['id'] ?? 0);
    if ($comment_id <= 0) {
        api_error('comment_id is required.', 422);
    }

    $comment = db_fetch_one("SELECT * FROM comments WHERE id = ? LIMIT 1", [$comment_id]);
    if (!$comment) {
        api_error('Comment not found.', 404);
    }

    $ticket = get_ticket((int) $comment['ticket_id']);
    if (!$ticket || !can_see_ticket($ticket, $user)) {
        api_error('Forbidden', 403);
    }
    if (!can_manage_comment($comment, $user)) {
        api_error('Forbidden', 403);
    }

    try {
        if (function_exists('log_ticket_history')) {
            log_ticket_history((int) $comment['ticket_id'], (int) $user['id'], 'comment_deleted', (string) ($comment['content'] ?? ''), null);
        }
        db_delete('comments', 'id = ?', [$comment_id]);
        log_activity((int) $comment['ticket_id'], (int) $user['id'], 'comment_deleted', 'Comment deleted through API');
    } catch (Throwable $e) {
        api_error('Failed to delete comment.', 500);
    }

    api_app_contract_success([
        'ticket_id' => (int) $comment['ticket_id'],
        'comment_id' => $comment_id,
        'deleted' => true,
    ], ['resource' => 'delete_comment'], [
        'ticket_id' => (int) $comment['ticket_id'],
        'comment_id' => $comment_id,
        'deleted' => true,
    ]);
}

function api_app_delete_time_entry()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_error('Method not allowed', 405);
    }

    api_app_require_write_auth();
    api_app_require_api_token_scope('tickets:read');
    api_app_require_api_token_scope('time:write');
    api_app_require_api_token_scope('delete:write');

    $user = current_user();
    if (!$user) {
        api_error('Unauthorized', 401);
    }
    if (!is_agent() && !is_admin()) {
        api_error('Forbidden', 403);
    }
    if (!ticket_time_table_exists()) {
        api_error('Time tracking is not available.', 400);
    }

    $input = get_json_input();
    $entry_id = (int) ($input['time_entry_id'] ?? $input['entry_id'] ?? $input['id'] ?? 0);
    if ($entry_id <= 0) {
        api_error('time_entry_id is required.', 422);
    }

    $entry = db_fetch_one("SELECT * FROM ticket_time_entries WHERE id = ? LIMIT 1", [$entry_id]);
    if (!$entry) {
        api_error('Time entry not found.', 404);
    }

    $ticket = get_ticket((int) $entry['ticket_id']);
    if (!$ticket || !can_see_ticket($ticket, $user)) {
        api_error('Forbidden', 403);
    }
    if (!can_manage_time_entry($entry, $user)) {
        api_error('Forbidden', 403);
    }

    require_once BASE_PATH . '/includes/ticket-time-functions.php';
    if (!delete_time_entry($entry_id)) {
        api_error('Failed to delete time entry.', 500);
    }

    log_activity((int) $entry['ticket_id'], (int) $user['id'], 'time_deleted', 'Deleted time entry through API (' . format_duration_minutes((int) ($entry['duration_minutes'] ?? 0)) . ')');

    api_app_contract_success([
        'ticket_id' => (int) $entry['ticket_id'],
        'time_entry_id' => $entry_id,
        'comment_id' => !empty($entry['comment_id']) ? (int) $entry['comment_id'] : null,
        'deleted' => true,
    ], ['resource' => 'delete_time_entry'], [
        'ticket_id' => (int) $entry['ticket_id'],
        'time_entry_id' => $entry_id,
        'comment_id' => !empty($entry['comment_id']) ? (int) $entry['comment_id'] : null,
        'deleted' => true,
    ]);
}

function api_app_ticket_timer()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        api_error('Method not allowed', 405);
    }

    $user = current_user();
    if (!$user) {
        api_error('Unauthorized', 401);
    }
    if (!is_agent() && !is_admin()) {
        api_error('Forbidden', 403);
    }

    api_app_require_timer_functions();

    $ticket = api_app_resolve_ticket($_GET, $user);

    api_app_contract_success([
        'ticket' => app_contract_ticket_payload($ticket),
        'timer' => app_contract_ticket_timer((int) $ticket['id'], $user),
    ], ['resource' => 'ticket_timer']);
}

function api_app_ticket_timer_action()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_error('Method not allowed', 405);
    }

    api_app_require_write_auth();

    $user = current_user();
    if (!$user) {
        api_error('Unauthorized', 401);
    }
    if (!is_agent() && !is_admin()) {
        api_error('Forbidden', 403);
    }
    api_app_require_timer_functions();
    if (!function_exists('ticket_time_table_exists') || !ticket_time_table_exists()) {
        api_error('Time tracking is not available.', 400);
    }

    $input = get_json_input();
    $ticket = api_app_resolve_ticket($input, $user);
    $ticket_id = (int) $ticket['id'];
    $action = strtolower(trim((string) ($input['action'] ?? '')));
    $result = ['success' => false];

    if ($action === 'start') {
        $active = get_active_ticket_timer($ticket_id, (int) $user['id']);
        if ($active) {
            api_error('Timer is already running.', 400);
        }

        $ticket_billable_rate = function_exists('get_ticket_effective_billable_rate')
            ? get_ticket_effective_billable_rate($ticket, (int) $user['id'])
            : 0.0;
        $entry_id = (int) db_insert('ticket_time_entries', [
            'ticket_id' => $ticket_id,
            'user_id' => (int) $user['id'],
            'started_at' => date('Y-m-d H:i:s'),
            'ended_at' => null,
            'duration_minutes' => 0,
            'is_billable' => 1,
            'billable_rate' => $ticket_billable_rate,
            'cost_rate' => (float) ($user['cost_rate'] ?? 0),
            'is_manual' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        log_activity($ticket_id, (int) $user['id'], 'time_started', 'Timer started');
        if (function_exists('log_ticket_history')) {
            log_ticket_history($ticket_id, (int) $user['id'], 'timer_started', null, date('Y-m-d H:i:s'));
        }
        $result = ['success' => true, 'entry_id' => $entry_id];
    } elseif ($action === 'pause') {
        $result = pause_ticket_timer($ticket_id, (int) $user['id']);
        if (!empty($result['success'])) {
            log_activity($ticket_id, (int) $user['id'], 'time_paused', 'Timer paused');
        }
    } elseif ($action === 'resume') {
        $result = resume_ticket_timer($ticket_id, (int) $user['id']);
        if (!empty($result['success'])) {
            log_activity($ticket_id, (int) $user['id'], 'time_resumed', 'Timer resumed');
        }
    } elseif ($action === 'stop') {
        $active = get_active_ticket_timer($ticket_id, (int) $user['id']);
        if (!$active) {
            api_error('No active timer found.', 400);
        }
        $elapsed = calculate_timer_elapsed($active);
        $duration = max(1, (int) floor($elapsed / 60));
        db_update('ticket_time_entries', [
            'ended_at' => date('Y-m-d H:i:s'),
            'duration_minutes' => $duration,
            'paused_at' => null,
        ], 'id = ?', [(int) $active['id']]);
        log_activity($ticket_id, (int) $user['id'], 'time_stopped', "Timer stopped ({$duration} min)");
        $result = [
            'success' => true,
            'entry_id' => (int) $active['id'],
            'duration_minutes' => $duration,
        ];
    } elseif ($action === 'discard') {
        $result = discard_ticket_timer($ticket_id, (int) $user['id']);
        if (!empty($result['success'])) {
            log_activity($ticket_id, (int) $user['id'], 'time_discarded', 'Timer discarded');
        }
    } else {
        api_error('Unsupported timer action.', 422);
    }

    if (empty($result['success'])) {
        api_error((string) ($result['error'] ?? 'Timer action failed.'), 400);
    }

    api_app_contract_success([
        'ticket' => app_contract_ticket_payload($ticket),
        'timer' => app_contract_ticket_timer($ticket_id, $user),
        'action' => $action,
        'result' => $result,
    ], ['resource' => 'timer_action']);
}

function api_app_client_overview()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        api_error('Method not allowed', 405);
    }

    $user = current_user();
    if (!$user) {
        api_error('Unauthorized', 401);
    }
    if (!is_admin() && !is_agent()) {
        api_error('Forbidden', 403);
    }
    if (!function_exists('client_overview')) {
        api_error('Client overview is not available.', 500);
    }

    $organization_id = (int) ($_GET['organization_id'] ?? $_GET['client_id'] ?? 0);
    if ($organization_id <= 0) {
        api_error('Missing organization_id', 422);
    }

    $view = function_exists('ticket_list_view_normalize')
        ? ticket_list_view_normalize($_GET['view'] ?? 'open', true)
        : 'open';
    $overview = client_overview($organization_id, $view);
    if (!$overview) {
        api_error('Client not found.', 404);
    }

    $payload = app_contract_client_overview_payload($overview, $view);

    api_app_contract_success($payload, ['resource' => 'client_overview']);
}

function api_app_reporting_review()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        api_error('Method not allowed', 405);
    }

    $user = current_user();
    if (!$user) {
        api_error('Unauthorized', 401);
    }
    if (!is_admin() && (!function_exists('can_view_time') || !can_view_time($user))) {
        api_error('Forbidden', 403);
    }
    if (!function_exists('billing_review_payload') || !function_exists('billing_review_filters_from_request')) {
        api_error('Billing review is not available.', 500);
    }

    $limit = api_app_clamp_limit($_GET['limit'] ?? null, 100, 250);
    $offset = max(0, (int) ($_GET['offset'] ?? 0));
    $filters = billing_review_filters_from_request($_GET);
    $payload = billing_review_payload($filters, $user, $limit, $offset);

    api_app_contract_success($payload, ['resource' => 'reporting_review']);
}

function api_app_notifications_summary()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        api_error('Method not allowed', 405);
    }

    $user = current_user();
    if (!$user) {
        api_error('Unauthorized', 401);
    }

    $limit = api_app_clamp_limit($_GET['limit'] ?? null, 10, 25);
    $result = function_exists('get_user_notifications')
        ? get_user_notifications((int) $user['id'], $limit, 0, true)
        : ['notifications' => [], 'unread_count' => 0];

    api_app_contract_success([
        'unread_count' => (int) ($result['unread_count'] ?? 0),
        'items' => array_map('app_contract_notification_summary_item', $result['notifications'] ?? []),
    ], ['resource' => 'notifications_summary']);
}

function api_app_notifications()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        api_error('Method not allowed', 405);
    }

    $user = current_user();
    if (!$user) {
        api_error('Unauthorized', 401);
    }

    $limit = api_app_clamp_limit($_GET['limit'] ?? null, 25, 100);
    $offset = max(0, (int) ($_GET['offset'] ?? 0));
    $include_resolved = (int) ($_GET['include_resolved'] ?? 0) === 1;
    $result = function_exists('get_user_notifications')
        ? get_user_notifications((int) $user['id'], $limit, $offset, !$include_resolved)
        : ['notifications' => [], 'unread_count' => 0];
    $items = array_map('app_contract_notification_summary_item', $result['notifications'] ?? []);

    api_app_contract_success([
        'unread_count' => (int) ($result['unread_count'] ?? 0),
        'items' => $items,
        'pagination' => [
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => count($items) === $limit,
        ],
    ], ['resource' => 'notifications']);
}

function api_app_notification_read_state()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_error('Method not allowed', 405);
    }

    api_app_require_write_auth();

    $user = current_user();
    if (!$user) {
        api_error('Unauthorized', 401);
    }

    $input = get_json_input();
    $scope = strtolower(trim((string) ($input['scope'] ?? 'notification')));
    $is_read = array_key_exists('is_read', $input) ? (bool) $input['is_read'] : true;
    $updated = false;

    if ($scope === 'notification') {
        $notification_id = (int) ($input['notification_id'] ?? $input['id'] ?? 0);
        if ($notification_id <= 0) {
            api_error('Missing notification_id.', 422);
        }
        $updated = $is_read
            ? mark_notification_read($notification_id, (int) $user['id'])
            : (function_exists('mark_notification_unread') && mark_notification_unread($notification_id, (int) $user['id']));
    } elseif ($scope === 'ticket') {
        if (!$is_read) {
            api_error('Ticket notification groups can only be marked read.', 422);
        }
        $ticket_id = (int) ($input['ticket_id'] ?? 0);
        if ($ticket_id <= 0) {
            api_error('Missing ticket_id.', 422);
        }
        $updated = mark_ticket_notifications_read($ticket_id, (int) $user['id']);
    } elseif ($scope === 'all') {
        if (!$is_read) {
            api_error('All notifications can only be marked read.', 422);
        }
        $updated = mark_all_notifications_read((int) $user['id']);
    } else {
        api_error('Unsupported notification scope.', 422);
    }

    api_app_contract_success([
        'unread_count' => function_exists('get_unread_notification_count')
            ? get_unread_notification_count((int) $user['id'])
            : 0,
        'updated' => (bool) $updated,
    ], ['resource' => 'notification_read_state']);
}

function api_app_tenant_state()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        api_error('Method not allowed', 405);
    }

    $user = current_user();
    if (!$user) {
        api_error('Unauthorized', 401);
    }

    $tenant_id = (int) ($_GET['tenant_id'] ?? (function_exists('current_tenant_id') ? current_tenant_id() : 0));
    if ($tenant_id <= 0 || !function_exists('billing_get_tenant')) {
        api_error('Tenant is not available.', 500);
    }
    if ($tenant_id !== (int) (function_exists('current_tenant_id') ? current_tenant_id() : 0) && (!function_exists('is_platform_admin') || !is_platform_admin($user))) {
        api_error('Forbidden', 403);
    }

    $tenant = billing_get_tenant($tenant_id);
    if (!$tenant) {
        api_error('Tenant not found.', 404);
    }

    $access_state = function_exists('billing_workspace_access_state') ? billing_workspace_access_state($tenant) : ['allowed' => true, 'reason' => '', 'message' => ''];
    $action_state = function_exists('billing_tenant_billing_action_state') ? billing_tenant_billing_action_state($tenant, $access_state) : [];
    $usage = function_exists('billing_tenant_usage') ? billing_tenant_usage($tenant_id) : [];

    api_app_contract_success([
        'tenant' => app_contract_tenant_payload($tenant),
        'access' => $access_state,
        'billing_actions' => $action_state,
        'usage' => $usage,
        'capabilities' => [
            'manage_billing' => is_admin() || (function_exists('is_platform_admin') && is_platform_admin($user)),
            'platform_admin' => function_exists('is_platform_admin') && is_platform_admin($user),
        ],
        'links' => [
            'billing' => url('billing', ['tenant_id' => $tenant_id]),
        ],
    ], ['resource' => 'tenant_state']);
}
