<?php

$root = dirname(__DIR__);
$router = file_get_contents($root . '/includes/api/router.php');
$handler = file_get_contents($root . '/includes/api/mobile-handler.php');
$schema = file_get_contents($root . '/includes/schema.sql');
$apiClient = file_get_contents($root . '/ios/FoxDesk/FoxDeskKit/Sources/API/FoxDeskAPIClient.swift');
$tests = file_get_contents($root . '/ios/FoxDesk/FoxDeskKitTests/FoxDeskAPIClientTests.swift');

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(str_contains($schema, 'CREATE TABLE IF NOT EXISTS mobile_idempotency_keys'), 'Mobile idempotency table is missing from the schema.');
$assert(str_contains($handler, 'function mobile_api_idempotency_replay_if_available'), 'Mobile idempotency replay handler is missing.');
$assert(str_contains($handler, 'function mobile_api_idempotency_store_success'), 'Mobile idempotency response persistence is missing.');
$assert(str_contains($schema, 'UNIQUE KEY uniq_mobile_idempotency_session_key (mobile_session_id, action, idempotency_key)'), 'Mobile idempotency keys must be unique per session and action.');
$assert(str_contains($router, 'mobile_api_idempotency_replay_if_available($action)'), 'Authenticated mobile writes do not enter the idempotency gate.');
$assert(str_contains($apiClient, 'forHTTPHeaderField: "Idempotency-Key"'), 'The iOS API client does not send an idempotency key.');
$assert(str_contains($apiClient, 'performIdempotentDataRequest'), 'The iOS API client does not retry a lost write response with the same request.');
$assert(str_contains($tests, 'testMobileWriteRetriesOnceWithTheSameIdempotencyKey'), 'The iOS idempotent retry regression test is missing.');

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

echo "Mobile idempotency contract OK\n";
