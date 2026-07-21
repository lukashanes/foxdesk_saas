<?php
define('BASE_PATH', dirname(__DIR__));

function assert_stripe_legal_consent($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

$billing = file_get_contents(BASE_PATH . '/includes/billing-functions.php');

assert_stripe_legal_consent($billing !== false, 'Unable to read billing functions.');
assert_stripe_legal_consent(str_contains($billing, "'consent_collection[terms_of_service]' => 'required'"), 'Stripe Checkout must require Terms acceptance.');
assert_stripe_legal_consent(str_contains($billing, 'business or professional purchase'), 'Checkout must record the B2B purchase confirmation.');
assert_stripe_legal_consent(str_contains($billing, 'authority to bind the customer'), 'Checkout must record signatory authority.');
assert_stripe_legal_consent(str_contains($billing, 'cancellation takes effect at the end of the current paid period'), 'Checkout must disclose period-end cancellation.');
assert_stripe_legal_consent(str_contains($billing, 'Paid periods are non-refundable'), 'Checkout must disclose the refund rule before purchase.');

echo "Stripe legal consent contract OK\n";
