<?php
/**
 * Versioned native/mobile API path aliases.
 *
 * The legacy query-string API stays the canonical dispatcher internally:
 * index.php?page=api&action=...
 *
 * This layer lets native apps call readable, versioned paths such as:
 * /api/mobile/v1/tickets/123/comments
 */

function foxdesk_mobile_v1_normalize_path(string $uri): ?string
{
    $path = (string) (parse_url($uri, PHP_URL_PATH) ?? '');
    $path = '/' . ltrim($path, '/');

    if (!preg_match('~(?:^|/)api/mobile/v1(?P<tail>/.*)?$~', $path, $matches)) {
        return null;
    }

    $tail = (string) ($matches['tail'] ?? '');
    $tail = '/' . trim($tail, '/');

    return $tail === '/' ? '/' : $tail;
}

function foxdesk_mobile_v1_ticket_id_from_segment(string $value): array
{
    if (ctype_digit($value)) {
        return [
            'query' => ['id' => (int) $value, 'ticket_id' => (int) $value],
            'input_defaults' => ['ticket_id' => (int) $value],
            'post_defaults' => ['ticket_id' => (int) $value],
        ];
    }

    return [
        'query' => ['ticket_hash' => $value, 'hash' => $value],
        'input_defaults' => ['ticket_hash' => $value],
        'post_defaults' => ['ticket_hash' => $value],
    ];
}

function foxdesk_mobile_v1_route_from_request(string $method, string $uri): ?array
{
    $path = foxdesk_mobile_v1_normalize_path($uri);
    if ($path === null) {
        return null;
    }

    $method = strtoupper($method);
    $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn($part) => $part !== ''));
    $route = [
        'action' => null,
        'query' => [],
        'input_defaults' => [],
        'post_defaults' => [],
    ];

    $set_action = static function (string $action) use (&$route): array {
        $route['action'] = $action;
        return $route;
    };

    if ($segments === []) {
        return $method === 'GET' ? $set_action('app-shell') : null;
    }

    $first = $segments[0] ?? '';
    $second = $segments[1] ?? '';
    $third = $segments[2] ?? '';

    if ($first === 'login' && $method === 'POST') {
        return $set_action('mobile-login');
    }
    if ($first === 'verify-2fa' && $method === 'POST') {
        return $set_action('mobile-verify-2fa');
    }
    if ($first === 'refresh' && $method === 'POST') {
        return $set_action('mobile-refresh');
    }
    if ($first === 'me' && $method === 'GET') {
        return $set_action('mobile-me');
    }
    if ($first === 'logout' && $method === 'POST') {
        return $set_action('mobile-logout');
    }

    if (in_array($first, ['shell', 'app-shell'], true) && $method === 'GET') {
        return $set_action('app-shell');
    }
    if (in_array($first, ['home', 'work'], true) && $method === 'GET') {
        return $set_action('app-home');
    }
    if ($first === 'tenant-state' && $method === 'GET') {
        return $set_action('app-tenant-state');
    }

    if ($first === 'tickets') {
        if ($second === '') {
            return $method === 'POST' ? $set_action('app-create-ticket') : ($method === 'GET' ? $set_action('app-ticket-list') : null);
        }
        if ($second === 'create-options' && $method === 'GET') {
            return $set_action('app-ticket-create-options');
        }

        $ticket_defaults = foxdesk_mobile_v1_ticket_id_from_segment(rawurldecode($second));
        $route['query'] = array_merge($route['query'], $ticket_defaults['query']);
        $route['input_defaults'] = array_merge($route['input_defaults'], $ticket_defaults['input_defaults']);
        $route['post_defaults'] = array_merge($route['post_defaults'], $ticket_defaults['post_defaults']);

        if ($third === '' && $method === 'GET') {
            return $set_action('app-ticket-detail');
        }
        if ($third === '' && $method === 'POST') {
            return $set_action('app-update-ticket');
        }
        if ($third === 'actions' && $method === 'GET') {
            return $set_action('app-ticket-actions');
        }
        if ($third === 'comments' && $method === 'POST') {
            return $set_action('app-add-comment');
        }
        if ($third === 'comment-with-time' && $method === 'POST') {
            return $set_action('app-add-comment-with-time');
        }
        if ($third === 'time' && $method === 'POST') {
            return $set_action('app-log-time');
        }
        if ($third === 'timer' && $method === 'GET') {
            return $set_action('app-ticket-timer');
        }
        if ($third === 'timer' && $method === 'POST') {
            return $set_action('app-ticket-timer-action');
        }
        if ($third === 'attachments' && $method === 'POST') {
            return $set_action('upload');
        }
    }

    if ($first === 'attachments') {
        if ($second !== '' && $third === 'download' && $method === 'GET') {
            $route['query']['attachment_id'] = (int) $second;
            return $set_action('app-attachment-download');
        }
        if ($second !== '' && $method === 'GET') {
            $route['query']['attachment_id'] = (int) $second;
            return $set_action('app-attachment-metadata');
        }
        if ($second === '' && $method === 'POST') {
            return $set_action('upload');
        }
    }

    if ($first === 'clients' && $second !== '' && $method === 'GET') {
        $route['query']['organization_id'] = (int) $second;
        return $set_action('app-client-overview');
    }

    if ($first === 'search' && $method === 'GET') {
        return $set_action('global-search');
    }

    if ($first === 'device-token') {
        if ($method === 'POST' && $second === '') {
            return $set_action('mobile-register-device');
        }
        if ($method === 'POST' && in_array($second, ['delete', 'unregister'], true)) {
            return $set_action('mobile-unregister-device');
        }
    }

    if ($first === 'notifications') {
        if ($second === '' && $method === 'GET') {
            return $set_action('app-notifications');
        }
        if ($second === 'summary' && $method === 'GET') {
            return $set_action('app-notifications-summary');
        }
        if ($second === 'read-state' && $method === 'POST') {
            return $set_action('app-notification-read-state');
        }
    }

    if ($first === 'reporting-review' && $method === 'GET') {
        return $set_action('app-reporting-review');
    }

    return null;
}

function foxdesk_apply_mobile_v1_route_from_request(): void
{
    $route = foxdesk_mobile_v1_route_from_request(
        (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
        (string) ($_SERVER['REQUEST_URI'] ?? '')
    );

    if (!$route || empty($route['action'])) {
        return;
    }

    $_GET['page'] = 'api';
    $_REQUEST['page'] = 'api';
    $_GET['action'] = (string) $route['action'];
    $_REQUEST['action'] = (string) $route['action'];

    foreach (($route['query'] ?? []) as $key => $value) {
        if (!array_key_exists($key, $_GET)) {
            $_GET[$key] = $value;
            $_REQUEST[$key] = $value;
        }
    }

    foreach (($route['post_defaults'] ?? []) as $key => $value) {
        if (!array_key_exists($key, $_POST)) {
            $_POST[$key] = $value;
        }
    }

    $GLOBALS['api_mobile_v1_route'] = $route;
    $GLOBALS['api_mobile_v1_input_defaults'] = $route['input_defaults'] ?? [];
}
