<?php
define('BASE_PATH', dirname(__DIR__));

function assert_legal_copy($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

$legal = file_get_contents(BASE_PATH . '/pages/legal.php');

foreach (['Privacy Policy', 'Terms of Service', 'Data Processing Addendum', 'Refund and Cancellation Policy', "'Security'"] as $required) {
    assert_legal_copy(strpos($legal, $required) !== false, "Missing legal document: {$required}");
}

foreach (['tenant-aware', 'CSRF', 'Cloudflare', 'Stripe', 'secrets from source code', 'service containers'] as $internal_term) {
    assert_legal_copy(stripos($legal, $internal_term) === false, "Legal copy should not expose internal implementation detail: {$internal_term}");
}

foreach (['This page explains', 'how we approach security'] as $fluff_term) {
    assert_legal_copy(stripos($legal, $fluff_term) === false, "Legal copy should avoid explainer filler: {$fluff_term}");
}

assert_legal_copy(strpos($legal, 'Aenze s.r.o.') !== false, 'Operator name is missing.');
assert_legal_copy(strpos($legal, 'processes personal data for FoxDesk Cloud as described below') !== false, 'Privacy intro should be direct and operator-specific.');
assert_legal_copy(strpos($legal, 'FoxDesk Cloud is supplied for business and professional use') !== false, 'Terms must be B2B-first.');
assert_legal_copy(strpos($legal, 'To the maximum extent permitted by law, we disclaim all warranties') !== false, 'Operator-favourable warranty wording is missing.');
assert_legal_copy(strpos($legal, 'If a serious service issue is confirmed') !== false, 'Refund remedy wording is missing.');
assert_legal_copy(strpos($legal, "if (\$type === 'subprocessors')") !== false, 'Public subprocessors page must remain disabled.');

echo "Legal copy contract OK\n";
