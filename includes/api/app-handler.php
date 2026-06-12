<?php
/**
 * API Handler: application shell contract.
 */

function api_app_contract_envelope(array $data, array $meta = []): array
{
    return [
        'data' => $data,
        'meta' => array_merge([
            'schema_version' => 1,
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
    $home = app_feed_payload($user, $limit);

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
    } elseif ($ticket_id > 0) {
        $ticket = get_ticket($ticket_id);
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
            'content_text' => trim(strip_tags((string) ($comment['content'] ?? ''))),
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

        $attachments[] = [
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

function api_app_ticket_time_entries(int $ticket_id): array
{
    if (!is_agent() || !function_exists('get_ticket_time_entries')) {
        return [];
    }

    $entries = [];
    foreach (get_ticket_time_entries($ticket_id) as $entry) {
        $entries[] = [
            'id' => (int) ($entry['id'] ?? 0),
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

function api_app_add_comment()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_error('Method not allowed', 405);
    }

    require_csrf_token(true);

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

    $comment_id = add_comment($ticket_id, (int) $user['id'], $content, $is_internal ? 1 : 0);
    if (!$comment_id) {
        api_error('Failed to add comment.', 500);
    }

    $response = ['comment_id' => (int) $comment_id];

    $duration = (int) ($input['duration_minutes'] ?? 0);
    if ($duration > 0 && is_agent() && function_exists('add_manual_time_entry')) {
        $started_at = date('Y-m-d H:i:s');
        $time_entry_id = add_manual_time_entry($ticket_id, (int) $user['id'], [
            'started_at' => $started_at,
            'ended_at' => date('Y-m-d H:i:s', strtotime($started_at) + ($duration * 60)),
            'duration_minutes' => $duration,
            'summary' => $input['time_summary'] ?? null,
            'is_billable' => 1,
            'source' => 'manual',
        ]);
        if ($time_entry_id) {
            $response['time_entry_id'] = (int) $time_entry_id;
        }
    }

    if (!$is_internal && function_exists('dispatch_ticket_notifications')) {
        $preview = mb_strlen($content) > 80 ? mb_substr($content, 0, 77) . '...' : $content;
        dispatch_ticket_notifications('new_comment', $ticket_id, (int) $user['id'], [
            'comment_preview' => strip_tags($preview),
            'comment_id' => (int) $comment_id,
        ]);
    }

    api_app_contract_success($response, ['resource' => 'ticket_comment'], $response);
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
