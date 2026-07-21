<?php

$root = dirname(__DIR__);
$scriptPath = $root . '/bin/ios-companion-readiness.sh';
$packagePath = $root . '/package.json';

$script = is_file($scriptPath) ? file_get_contents($scriptPath) : false;
$package = is_file($packagePath) ? file_get_contents($packagePath) : false;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$assert($script !== false, 'Missing iOS companion readiness gate.');
$assert($package !== false, 'Missing package.json.');

foreach ([
    'tests/ios-companion-business-model-contract-test.php',
    'tests/api-workspace-access-contract-test.php',
    'tests/ios-workspace-access-gate-contract-test.php',
    'IOS_COMPANION_MODEL_REVIEWED',
] as $needle) {
    $assert(str_contains($script, $needle), "Companion gate is missing required evidence or contract: {$needle}");
}

foreach ([
    'STRIPE_HOSTED_CHECKOUT_EVIDENCE_PATH',
    'PRODUCTION_STRIPE_BILLING_SMOKE_EVIDENCE_PATH',
    'PRODUCTION_STRIPE_WEBHOOK_SMOKE_EVIDENCE_PATH',
    'verify-stripe-hosted-checkout-evidence.js',
] as $forbidden) {
    $assert(!str_contains($script, $forbidden), "App Store companion gate must not depend on web commerce evidence: {$forbidden}");
}

$assert(str_contains($package, '"ios:companion:gate": "./bin/ios-companion-readiness.sh"'), 'package.json must expose the iOS companion gate.');

echo "iOS companion readiness contract OK\n";
