<?php

$root = dirname(__DIR__);
$auth = file_get_contents($root . '/includes/auth.php');
$apiHelpers = file_get_contents($root . '/includes/admin-crud-helper.php');
if ($auth === false || $apiHelpers === false) {
    fwrite(STDERR, "Unable to read API idempotency sources.\n");
    exit(1);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$start = strpos($auth, 'function api_idempotency_replay_if_available');
$end = strpos($auth, 'function api_token_resource_from_response', $start ?: 0);
$block = ($start !== false && $end !== false) ? substr($auth, $start, $end - $start) : '';
$reserve = strpos($block, "db_insert('api_idempotency_keys'");
$lookup = strpos($block, 'SELECT * FROM api_idempotency_keys');

$assert($block !== '', 'Idempotency implementation block is missing.');
$assert($reserve !== false && $lookup !== false && $reserve < $lookup, 'Idempotency key must be reserved atomically before replay lookup.');
$assert(str_contains($block, "['owns_reservation'] = true"), 'The request that reserves a key must be tracked as its owner.');
$assert(str_contains($block, 'already in progress.'), 'Concurrent retries must receive an explicit in-progress response.');
$assert(str_contains($block, "db_update(\n            'api_idempotency_keys'" ) || str_contains($block, "db_update('api_idempotency_keys'"), 'Successful responses must complete the reserved row instead of inserting after the side effect.');
$assert(str_contains($block, 'function api_idempotency_release_pending'), 'Failed writes must release their pending reservation.');
$assert(str_contains($apiHelpers, 'api_idempotency_release_pending()'), 'API errors must release reservations owned by the failed request.');

echo "API idempotency reservation contract OK\n";
