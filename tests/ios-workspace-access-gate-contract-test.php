<?php

$root = dirname(__DIR__);
$rootViewPath = $root . '/ios/FoxDesk/FoxDesk/Sources/RootView.swift';
$sessionPath = $root . '/ios/FoxDesk/FoxDeskKit/Sources/Session/AppSession.swift';

$rootView = is_file($rootViewPath) ? file_get_contents($rootViewPath) : false;
$session = is_file($sessionPath) ? file_get_contents($sessionPath) : false;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$assert($rootView !== false, 'Unable to read the iOS RootView.');
$assert($session !== false, 'Unable to read the iOS AppSession.');

$gateStart = strpos($rootView, 'private struct WorkspaceAccessGate');
$blockedStart = strpos($rootView, 'private struct WorkspaceAccessBlockedView');
$assert($gateStart !== false && $blockedStart !== false && $gateStart < $blockedStart, 'Workspace access gate is missing.');

$gate = substr($rootView, $gateStart, $blockedStart - $gateStart);
$unresolvedPosition = strpos($gate, 'if session.tenantState == nil');
$allowedPosition = strpos($gate, 'else if session.workspaceAccessAllowed');
$contentPosition = strpos($gate, "else if session.workspaceAccessAllowed {\n                content");

$assert(str_contains($gate, 'WorkspaceAccessCheckView()'), 'Unresolved workspace access must render a dedicated checking state.');
$assert($unresolvedPosition !== false && $allowedPosition !== false && $unresolvedPosition < $allowedPosition, 'Workspace access must be resolved before the allowed state is evaluated.');
$assert($contentPosition !== false && $allowedPosition === $contentPosition, 'Workspace content must not render before access is confirmed.');
$assert(str_contains($gate, 'session.tenantStateError == nil'), 'A failed access check must wait for an explicit retry instead of looping.');
$assert(str_contains($rootView, 'Could not check workspace access'), 'A failed access check must have a clear user-facing error.');
$assert(str_contains($rootView, 'Button("Try again")'), 'A failed access check must offer an explicit retry.');
$assert(str_contains($rootView, 'Workspace access is paused'), 'Blocked workspace UI must use a generic access title.');
$assert(str_contains($rootView, 'Contact your workspace administrator or FoxDesk support'), 'Blocked workspace UI must provide a generic recovery path.');
$assert(!str_contains($rootView, 'billingActions'), 'Blocked workspace UI must not expose billing actions.');
$assert(!str_contains($rootView, 'access.message'), 'Blocked workspace UI must not display raw backend billing messages.');
$assert(str_contains($session, 'tenantStateError = error.localizedDescription'), 'Tenant-state failures must remain observable by the access gate.');
$assert(str_contains($session, 'tenantState?.access.allowed ?? true'), 'Resolved tenant access must continue to use the backend access decision.');
$assert(str_contains($session, 'statusCode == 402'), 'A payment-required API response must refresh the workspace access state.');
$assert(str_contains($session, 'await refreshTenantState()'), 'The session must re-read tenant access after a payment-required response.');

echo "iOS workspace access gate contract OK\n";
