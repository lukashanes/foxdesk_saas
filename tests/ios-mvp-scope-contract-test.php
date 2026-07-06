<?php

$root = dirname(__DIR__);
$iosSources = $root . '/ios/FoxDesk/FoxDesk/Sources';
$kitSources = $root . '/ios/FoxDesk/FoxDeskKit/Sources';
$runbook = file_get_contents($root . '/docs/IOS_TESTFLIGHT_RUNBOOK.md');
$handoff = file_get_contents($root . '/docs/IOS_HANDOFF.md');

if (!is_dir($iosSources) || !is_dir($kitSources) || $runbook === false || $handoff === false) {
    fwrite(STDERR, "Unable to read iOS scope files.\n");
    exit(1);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$readFile = static function (string $path): string {
    $content = file_get_contents($path);
    if ($content === false) {
        fwrite(STDERR, "Unable to read {$path}.\n");
        exit(1);
    }
    return $content;
};

$swiftFiles = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($iosSources, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'swift') {
        $swiftFiles[] = $file->getPathname();
    }
}

sort($swiftFiles);
$assert($swiftFiles !== [], 'iOS app source files are missing.');

$extractSwiftStrings = static function (string $source): array {
    preg_match_all('/"((?:\\\\.|[^"\\\\])*)"/s', $source, $matches);
    return $matches[1] ?? [];
};

$forbiddenVisibleTerms = [
    'Stripe',
    'Checkout',
    'Customer Portal',
    'Pricing',
    'Upgrade',
    'Subscription',
    'Plan',
    'In-app purchase',
    'Platform admin',
    'Tenant lifecycle',
    'Self-hosted',
    'SMTP',
    'IMAP',
    'Cloudflare',
    'R2',
];

foreach ($swiftFiles as $path) {
    $relative = str_replace($root . '/', '', $path);
    $content = $readFile($path);
    foreach ($extractSwiftStrings($content) as $string) {
        foreach ($forbiddenVisibleTerms as $term) {
            $assert(
                stripos($string, $term) === false,
                "Forbidden iOS MVP visible term '{$term}' found in {$relative}: {$string}"
            );
        }
    }
}

$rootView = $readFile($iosSources . '/RootView.swift');
$accountView = $readFile($iosSources . '/AccountView.swift');
$searchView = $readFile($iosSources . '/SearchView.swift');
$tenantModels = $readFile($kitSources . '/Models/TenantModels.swift');

$assert(str_contains($rootView, 'WorkspaceAccessBlockedView'), 'iOS app must show a workspace-access state instead of billing or platform screens.');
$assert(str_contains($rootView, 'session.tenantState?.billingActions?.noticeTitle'), 'iOS app may read billing notice title only as a workspace access message.');
$assert(!str_contains($rootView, 'showCheckout'), 'iOS root UI must not branch into checkout.');
$assert(!str_contains($rootView, 'showPortal'), 'iOS root UI must not branch into a billing portal.');
$assert(str_contains($rootView, 'AccountView()'), 'iOS shell must use the lightweight Account view.');
$assert(!file_exists($iosSources . '/SettingsView.swift'), 'iOS must not keep a web-admin-style SettingsView surface.');
$assert(!str_contains($accountView, 'showCheckout'), 'iOS Account must not expose checkout actions.');
$assert(!str_contains($accountView, 'showPortal'), 'iOS Account must not expose billing portal actions.');
$assert(!str_contains($accountView, 'checkoutLabel'), 'iOS Account must not expose checkout labels.');
$assert(!str_contains($accountView, 'portalLabel'), 'iOS Account must not expose portal labels.');
$assert(!str_contains($searchView, '"reports"'), 'iOS Search must not show report sections in the first agent/admin MVP.');
$assert(!str_contains($searchView, 'case "report"'), 'iOS Search must not render report result rows in the first agent/admin MVP.');

$assert(str_contains($accountView, 'Contact support'), 'iOS Account must keep support available.');
$assert(str_contains($accountView, 'Privacy Policy'), 'iOS Account must keep privacy policy available.');
$assert(str_contains($accountView, 'Request account deletion'), 'iOS Account must keep account deletion available.');
$assert(str_contains($accountView, '#if DEBUG'), 'Push diagnostics must stay debug-only.');
$assert(str_contains($accountView, 'Copy APNs token'), 'Debug/staging Account must still let testers copy the APNs token.');

$assert(str_contains($tenantModels, 'TenantBillingActions'), 'Tenant state model must keep billing notice fields for access-state messages.');
$assert(str_contains($tenantModels, 'showCheckout'), 'Tenant state model must decode checkout flags from the backend even though iOS does not render them.');
$assert(str_contains($runbook, 'No billing or upgrade flow inside iOS'), 'Runbook must keep billing outside the iOS MVP.');
$assert(str_contains($handoff, 'Billing, pricing, Stripe, platform administration, reports, and full workspace'), 'Handoff must keep out-of-scope surfaces out of iOS.');

echo "iOS MVP scope contract OK\n";
