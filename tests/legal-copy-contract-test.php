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

assert_legal_copy(strpos($legal, 'Aenze s.r.o.') !== false, 'Operator name is missing.');
assert_legal_copy(strpos($legal, 'This Privacy Policy explains') !== false, 'Privacy intro should be plain English.');
assert_legal_copy(strpos($legal, 'If a serious service issue is confirmed') !== false, 'Refund remedy wording is missing.');
assert_legal_copy(strpos($legal, "if (\$type === 'subprocessors')") !== false, 'Public subprocessors page must remain disabled.');

echo "Legal copy contract OK\n";
