<?php

$root = dirname(__DIR__);
$router_path = $root . '/includes/api/mobile-v1-router.php';
$index = file_get_contents($root . '/index.php');
$htaccess = file_get_contents($root . '/.htaccess');
$helper = file_get_contents($root . '/includes/admin-crud-helper.php');
$native_docs = file_get_contents($root . '/docs/NATIVE_APP_API.md');

if ($index === false || $htaccess === false || $helper === false || $native_docs === false || !file_exists($router_path)) {
    fwrite(STDERR, "Unable to read mobile API v1 routing files.\n");
    exit(1);
}

require_once $router_path;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$route = foxdesk_mobile_v1_route_from_request('GET', '/api/mobile/v1/work?limit=5');
$assert(($route['action'] ?? '') === 'app-home', 'GET /api/mobile/v1/work must route to app-home.');

$route = foxdesk_mobile_v1_route_from_request('POST', '/api/mobile/v1/login');
$assert(($route['action'] ?? '') === 'mobile-login', 'POST /api/mobile/v1/login must route to mobile-login.');

$route = foxdesk_mobile_v1_route_from_request('GET', '/api/mobile/v1/tickets/745');
$assert(($route['action'] ?? '') === 'app-ticket-detail', 'GET /api/mobile/v1/tickets/{id} must route to ticket detail.');
$assert(($route['query']['id'] ?? 0) === 745, 'Ticket detail route must expose id query value.');

$route = foxdesk_mobile_v1_route_from_request('GET', '/api/mobile/v1/tickets/create-options');
$assert(($route['action'] ?? '') === 'app-ticket-create-options', 'GET /api/mobile/v1/tickets/create-options must route to ticket create options.');
$assert(empty($route['query']['ticket_hash'] ?? null), 'Create-options route must not be treated as a ticket hash.');

$route = foxdesk_mobile_v1_route_from_request('POST', '/api/mobile/v1/tickets/745/comments');
$assert(($route['action'] ?? '') === 'app-add-comment', 'POST /api/mobile/v1/tickets/{id}/comments must route to app-add-comment.');
$assert(($route['input_defaults']['ticket_id'] ?? 0) === 745, 'Comment route must add ticket_id JSON default.');

$route = foxdesk_mobile_v1_route_from_request('POST', '/api/mobile/v1/tickets/745/comment-with-time');
$assert(($route['action'] ?? '') === 'app-add-comment-with-time', 'POST /api/mobile/v1/tickets/{id}/comment-with-time must route to app-add-comment-with-time.');
$assert(($route['input_defaults']['ticket_id'] ?? 0) === 745, 'Comment-with-time route must add ticket_id JSON default.');

$route = foxdesk_mobile_v1_route_from_request('POST', '/api/mobile/v1/tickets/745/attachments');
$assert(($route['action'] ?? '') === 'upload', 'POST /api/mobile/v1/tickets/{id}/attachments must route to upload.');
$assert(($route['post_defaults']['ticket_id'] ?? 0) === 745, 'Attachment route must add ticket_id POST default.');

$route = foxdesk_mobile_v1_route_from_request('POST', '/api/mobile/v1/attachments');
$assert(($route['action'] ?? '') === 'upload', 'POST /api/mobile/v1/attachments must route to upload.');
$assert(empty($route['post_defaults']['ticket_id'] ?? null), 'Generic attachment route must require multipart ticket_id from the client.');

$route = foxdesk_mobile_v1_route_from_request('GET', '/subdir/api/mobile/v1/clients/12');
$assert(($route['action'] ?? '') === 'app-client-overview', 'Mobile v1 router must work when app is installed in a subdirectory.');
$assert(($route['query']['organization_id'] ?? 0) === 12, 'Client route must expose organization_id.');

$route = foxdesk_mobile_v1_route_from_request('GET', '/api/mobile/v1/notifications');
$assert(($route['action'] ?? '') === 'app-notifications', 'GET /api/mobile/v1/notifications must route to app-notifications.');

$route = foxdesk_mobile_v1_route_from_request('POST', '/api/mobile/v1/notifications/read-state');
$assert(($route['action'] ?? '') === 'app-notification-read-state', 'POST /api/mobile/v1/notifications/read-state must route to app-notification-read-state.');

$_GET = [];
$_POST = [];
$_REQUEST = [];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/mobile/v1/tickets/745/attachments';
foxdesk_apply_mobile_v1_route_from_request();
$assert(($_GET['page'] ?? '') === 'api', 'Applied mobile v1 route must set page=api.');
$assert(($_GET['action'] ?? '') === 'upload', 'Applied mobile v1 route must set action=upload.');
$assert(($_POST['ticket_id'] ?? 0) === 745, 'Applied mobile v1 route must set multipart ticket_id default.');

$assert(str_contains($index, "require_once BASE_PATH . '/includes/api/mobile-v1-router.php'"), 'Index must load mobile v1 router.');
$assert(str_contains($index, 'foxdesk_apply_mobile_v1_route_from_request();'), 'Index must apply mobile v1 routes before page dispatch.');
$assert(str_contains($htaccess, 'RewriteRule ^api/mobile/v1'), '.htaccess must rewrite mobile v1 paths to index.php.');
$assert(str_contains($helper, 'api_mobile_v1_input_defaults'), 'JSON input helper must merge mobile v1 route defaults.');
$assert(str_contains($native_docs, '/api/mobile/v1/login'), 'Native docs must document the versioned login endpoint.');
$assert(str_contains($native_docs, '/api/mobile/v1/tickets/{id}/comment-with-time'), 'Native docs must document versioned comment-with-time endpoint.');
$assert(str_contains($native_docs, '/api/mobile/v1/attachments'), 'Native docs must document the generic versioned attachment upload endpoint.');

echo "Mobile API v1 routing contract OK\n";
