<?php

$root = dirname(__DIR__);
$routerPath = $root . '/includes/api/mobile-v1-router.php';
$docs = file_get_contents($root . '/docs/NATIVE_APP_API.md');
$traceability = file_get_contents($root . '/docs/IOS_MVP_TRACEABILITY.md');
$client = file_get_contents($root . '/ios/FoxDesk/FoxDeskKit/Sources/API/FoxDeskAPIClient.swift');

if (!file_exists($routerPath) || $docs === false || $traceability === false || $client === false) {
    fwrite(STDERR, "Unable to read iOS MVP endpoint matrix files.\n");
    exit(1);
}

require_once $routerPath;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$endpointMatrix = [
    ['login', 'POST', '/api/mobile/v1/login', 'mobile-login', 'POST /api/mobile/v1/login', ['public func login(', 'path: "login"']],
    ['me', 'GET', '/api/mobile/v1/me', 'mobile-me', 'GET /api/mobile/v1/me', ['public func me(', 'path: "me"']],
    ['work dashboard', 'GET', '/api/mobile/v1/work?limit=5', 'app-home', 'GET /api/mobile/v1/work?limit=5', ['public func home(', 'path: "work"']],
    ['ticket list', 'GET', '/api/mobile/v1/tickets?view=new', 'app-ticket-list', 'GET /api/mobile/v1/tickets?view=new&limit=25&offset=0', ['public func ticketList(', 'path: "tickets"']],
    ['create ticket options', 'GET', '/api/mobile/v1/tickets/create-options', 'app-ticket-create-options', 'GET /api/mobile/v1/tickets/create-options', ['public func createTicketOptions(', 'path: "tickets/create-options"']],
    ['create ticket', 'POST', '/api/mobile/v1/tickets', 'app-create-ticket', 'POST /api/mobile/v1/tickets', ['public func createTicket(', 'path: "tickets"']],
    ['ticket detail', 'GET', '/api/mobile/v1/tickets/745', 'app-ticket-detail', 'GET /api/mobile/v1/tickets/{id}', ['public func ticketDetail(', 'path: "tickets/\\(ticketId)"']],
    ['ticket actions', 'GET', '/api/mobile/v1/tickets/745/actions', 'app-ticket-actions', 'GET /api/mobile/v1/tickets/{id}/actions', ['public func ticketActions(', 'path: "tickets/\\(ticketId)/actions"']],
    ['update ticket', 'POST', '/api/mobile/v1/tickets/745', 'app-update-ticket', 'POST /api/mobile/v1/tickets/{id}', ['public func updateTicket(', 'path: "tickets/\\(request.ticketId)"']],
    ['add comment', 'POST', '/api/mobile/v1/tickets/745/comments', 'app-add-comment', 'POST /api/mobile/v1/tickets/{id}/comments', ['public func addComment(', '"tickets/\\(request.ticketId)/comments"']],
    ['add comment with time', 'POST', '/api/mobile/v1/tickets/745/comment-with-time', 'app-add-comment-with-time', 'POST /api/mobile/v1/tickets/{id}/comment-with-time', ['public func addComment(', '"tickets/\\(request.ticketId)/comment-with-time"']],
    ['timer state', 'GET', '/api/mobile/v1/tickets/745/timer', 'app-ticket-timer', 'GET /api/mobile/v1/tickets/{id}/timer', ['public func ticketTimer(', 'path: "tickets/\\(ticketId)/timer"']],
    ['timer action', 'POST', '/api/mobile/v1/tickets/745/timer', 'app-ticket-timer-action', 'POST /api/mobile/v1/tickets/{id}/timer', ['public func ticketTimerAction(', 'path: "tickets/\\(ticketId)/timer"']],
    ['ticket attachment upload', 'POST', '/api/mobile/v1/tickets/745/attachments', 'upload', 'POST /api/mobile/v1/tickets/{id}/attachments', ['public func uploadAttachment(', 'path: "tickets/\\(ticketId)/attachments"']],
    ['generic attachment upload', 'POST', '/api/mobile/v1/attachments', 'upload', 'POST /api/mobile/v1/attachments', ['sendMultipart(']],
    ['attachment metadata', 'GET', '/api/mobile/v1/attachments/55', 'app-attachment-metadata', 'GET /api/mobile/v1/attachments/{id}', ['public func attachmentMetadata(', 'path: "attachments/\\(attachmentId)"']],
    ['attachment download', 'GET', '/api/mobile/v1/attachments/55/download', 'app-attachment-download', 'GET /api/mobile/v1/attachments/{id}/download', ['public func downloadResourceToTemporaryFile(']],
    ['client overview', 'GET', '/api/mobile/v1/clients/12', 'app-client-overview', 'GET /api/mobile/v1/clients/{id}?view=open', ['public func clientOverview(', 'path: "clients/\\(organizationId)"']],
    ['global search', 'GET', '/api/mobile/v1/search?q=vpn', 'global-search', 'GET /api/mobile/v1/search?q=vpn&limit=8', ['public func globalSearch(', 'path: "search"']],
    ['device registration', 'POST', '/api/mobile/v1/device-token', 'mobile-register-device', 'POST /api/mobile/v1/device-token', ['public func registerDevice(', 'path: "device-token"']],
    ['device unregister', 'POST', '/api/mobile/v1/device-token/unregister', 'mobile-unregister-device', 'POST /api/mobile/v1/device-token/unregister', ['public func unregisterDevice(', 'path: "device-token/unregister"']],
    ['notifications', 'GET', '/api/mobile/v1/notifications', 'app-notifications', 'GET /api/mobile/v1/notifications?limit=25&offset=0', ['public func notifications(', 'path: "notifications"']],
    ['notification read state', 'POST', '/api/mobile/v1/notifications/read-state', 'app-notification-read-state', 'POST /api/mobile/v1/notifications/read-state', ['public func setNotificationReadState(', 'path: "notifications/read-state"']],
];

foreach ($endpointMatrix as [$label, $method, $path, $action, $docsNeedle, $clientNeedles]) {
    $route = foxdesk_mobile_v1_route_from_request($method, $path);
    $assert(($route['action'] ?? '') === $action, "{$label} must route to {$action}.");
    $assert(str_contains($docs, $docsNeedle), "Native API docs must document {$docsNeedle}.");
    foreach ($clientNeedles as $needle) {
        $assert(str_contains($client, $needle), "Swift API client missing {$label} evidence: {$needle}");
    }
}

$assert(str_contains($traceability, 'POST /api/mobile/v1/device-token/unregister'), 'Traceability must use the real device unregister endpoint.');
$assert(str_contains($traceability, 'tests/ios-mvp-endpoint-matrix-contract-test.php'), 'Traceability must mention the endpoint matrix contract.');

echo "iOS MVP endpoint matrix contract OK\n";
