<?php

$root = dirname(__DIR__);
$sourceRoots = [
    $root . '/ios/FoxDesk/FoxDesk/Sources',
    $root . '/ios/FoxDesk/FoxDeskKit/Sources',
];
$swiftFiles = [];
foreach ($sourceRoots as $sourceRoot) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'swift') {
            $swiftFiles[] = $file->getPathname();
        }
    }
}
sort($swiftFiles);
$swift = '';
foreach ($swiftFiles as $file) {
    $swift .= "\n" . file_get_contents($file);
}

$submission = file_get_contents($root . '/docs/IOS_APP_STORE_SUBMISSION.md');
$metadata = file_get_contents($root . '/docs/IOS_APP_STORE_CONNECT_METADATA.md');
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

foreach ([
    'import StoreKit',
    'Product.purchase(',
    'Transaction.currentEntitlements',
    'showCheckout',
    'showPortal',
    'billingActions',
    'TenantBillingActions',
    'subscriptionStatus',
    'billingEmail',
    'billingOverrideReason',
    'trialEndsAt',
    'manageBilling',
] as $forbidden) {
    $assert(!str_contains($swift, $forbidden), "Native source must not contain a purchase surface: {$forbidden}");
}

$purchaseCopyPattern = '/Text\s*\(\s*"[^"]*(buy|purchase|subscribe|upgrade|pricing|checkout|billing portal)[^"]*"\s*\)/i';
$assert(!preg_match($purchaseCopyPattern, $swift), 'Native visible copy must not invite users to purchase or manage web billing.');
$assert(str_contains($swift, 'Workspace access is paused'), 'Native UI must provide a generic workspace-access state.');
$assert(str_contains($swift, 'Contact your workspace administrator or FoxDesk support'), 'Native UI must direct blocked users to an administrator or support without exposing billing details.');

foreach ([$submission, $metadata] as $document) {
    $assert(str_contains($document, '3.1.3(f)'), 'App Store documentation must identify the free companion-app business model under guideline 3.1.3(f).');
    $assert((bool) preg_match('/no purchasing inside the\s+app/i', $document), 'App Store documentation must explicitly state that purchasing is absent.');
    $assert((bool) preg_match('/no calls to action to purchase outside the\s+app/i', $document), 'App Store documentation must explicitly prohibit external purchase calls to action.');
}

if ($failures) {
    fwrite(STDERR, "iOS companion business-model contract failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "iOS companion business-model contract OK\n";
