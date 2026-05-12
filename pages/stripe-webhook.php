<?php
/**
 * Stripe webhook endpoint.
 */

header('Content-Type: application/json');

$payload = file_get_contents('php://input');
$payload = is_string($payload) ? $payload : '';
$secret = billing_webhook_secret();

if ($secret === '') {
    http_response_code(503);
    echo json_encode(['error' => 'Stripe webhook secret is not configured.']);
    exit;
}

$signature = (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
if (!billing_verify_stripe_signature($payload, $signature, $secret)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid Stripe signature.']);
    exit;
}

$event = json_decode($payload, true);
if (!is_array($event)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload.']);
    exit;
}

try {
    $result = billing_handle_webhook_event($event);
    echo json_encode(['received' => true] + $result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Webhook handling failed.']);
}
