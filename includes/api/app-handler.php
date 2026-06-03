<?php
/**
 * API Handler: application shell contract.
 */

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

    api_success([
        'app_shell' => app_shell_payload($user),
    ]);
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

    api_success([
        'app_shell' => app_shell_payload($user),
        'home' => app_feed_payload($user, $limit),
    ]);
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

function api_app_ticket_payload(array $ticket): array
{
    $ticket_id = (int) ($ticket['id'] ?? 0);

    return [
        'id' => $ticket_id,
        'hash' => $ticket['hash'] ?? null,
        'code' => function_exists('get_ticket_code') ? get_ticket_code($ticket_id) : ('#' . $ticket_id),
        'title' => (string) ($ticket['title'] ?? ''),
        'description_html' => (string) ($ticket['description'] ?? ''),
        'description_text' => trim(strip_tags((string) ($ticket['description'] ?? ''))),
        'status' => [
            'id' => (int) ($ticket['status_id'] ?? 0),
            'name' => (string) ($ticket['status_name'] ?? ''),
            'color' => $ticket['status_color'] ?? null,
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
        'tags' => function_exists('normalize_ticket_tags')
            ? normalize_ticket_tags($ticket['tags'] ?? '', true)
            : [],
        'due_date' => $ticket['due_date'] ?? null,
        'created_at' => $ticket['created_at'] ?? null,
        'updated_at' => $ticket['updated_at'] ?? null,
    ];
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

    api_success([
        'ticket' => api_app_ticket_payload($ticket),
        'comments' => $comments,
        'attachments' => api_app_ticket_attachments($ticket_id, $comments),
        'time_entries' => api_app_ticket_time_entries($ticket_id),
    ]);
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

    api_success($response);
}
