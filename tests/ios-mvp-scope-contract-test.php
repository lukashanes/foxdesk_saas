<?php

$root = dirname(__DIR__);
$iosSources = $root . '/ios/FoxDesk/FoxDesk/Sources';
$kitSources = $root . '/ios/FoxDesk/FoxDeskKit/Sources';
$runbook = file_get_contents($root . '/docs/IOS_TESTFLIGHT_RUNBOOK.md');
$handoff = file_get_contents($root . '/docs/IOS_HANDOFF.md');
$project = file_get_contents($root . '/ios/FoxDesk/project.yml');

if (!is_dir($iosSources) || !is_dir($kitSources) || $runbook === false || $handoff === false || $project === false) {
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
$newTicketView = $readFile($iosSources . '/NewTicketView.swift');
$screenshotDemo = $readFile($iosSources . '/ScreenshotDemoRootView.swift');
$tenantModels = $readFile($kitSources . '/Models/TenantModels.swift');

$requiredTabs = [
    'Label("Dashboard", systemImage: "house")',
    'Label("Tickets", systemImage: "tray.full")',
    'Label("New ticket", systemImage: "plus.circle")',
    'Label("Search", systemImage: "magnifyingglass")',
    'Label("Account", systemImage: "person.crop.circle")',
    'case newTicket',
    '.tag(AppTab.newTicket)',
];

foreach ($requiredTabs as $needle) {
    $assert(str_contains($rootView, $needle), "iOS shell missing MVP tab contract: {$needle}");
}

foreach (['Label("Reports"', 'Label("Settings"', 'Label("Billing"', 'Label("Platform"', 'case reports', 'case settings', 'case billing', 'case platform'] as $needle) {
    $assert(!str_contains($rootView, $needle), "iOS shell must not expose out-of-scope tab: {$needle}");
}

$assert(
    str_contains($rootView, 'NewTicketView(')
        && str_contains($rootView, 'onCreated: { ticketID in')
        && str_contains($rootView, 'openTicket(ticketID)'),
    'iOS New ticket tab must open the created ticket after save.'
);
$assert(str_contains($rootView, 'private func openTicket(_ ticketID: Int)'), 'iOS shell must centralize created-ticket navigation.');
$assert(str_contains($newTicketView, 'private func resetForm()'), 'New ticket form must reset after successful create when used as a primary tab.');
$assert(str_contains($newTicketView, 'selectedPriorityID = createOptions?.defaults?.priorityId'), 'New ticket reset must preserve default priority for the next ticket.');
$assert(str_contains($newTicketView, 'selectedStatusID = createOptions?.defaults?.statusId'), 'New ticket reset must preserve default status for the next ticket.');
$assert(str_contains($rootView, 'WorkspaceAccessBlockedView'), 'iOS app must show a workspace-access state instead of billing or platform screens.');
$assert(str_contains($rootView, 'Workspace access is paused'), 'iOS app must use a generic workspace-access title.');
$assert(str_contains($rootView, 'Contact your workspace administrator or FoxDesk support'), 'iOS app must use a generic workspace-access recovery message.');
$assert(!str_contains($rootView, 'access.message'), 'iOS root UI must not render raw backend access or billing messages.');
$assert(!str_contains($rootView, 'billingActions'), 'iOS root UI must not decode or render billing actions.');
$assert(!str_contains($rootView, 'showCheckout'), 'iOS root UI must not branch into checkout.');
$assert(!str_contains($rootView, 'showPortal'), 'iOS root UI must not branch into a billing portal.');
$assert(str_contains($rootView, 'AccountView()'), 'iOS shell must use the lightweight Account view.');
$assert(!file_exists($iosSources . '/SettingsView.swift'), 'iOS must not keep a web-admin-style SettingsView surface.');
$assert(!str_contains($accountView, 'showCheckout'), 'iOS Account must not expose checkout actions.');
$assert(!str_contains($accountView, 'showPortal'), 'iOS Account must not expose billing portal actions.');
$assert(!str_contains($accountView, 'checkoutLabel'), 'iOS Account must not expose checkout labels.');
$assert(!str_contains($accountView, 'portalLabel'), 'iOS Account must not expose portal labels.');
$assert(!str_contains($accountView, 'trialEndsAt'), 'iOS Account must not expose trial state.');
$assert(!str_contains($accountView, 'billingActions'), 'iOS Account must not expose billing state.');
$assert(!str_contains($searchView, '"reports"'), 'iOS Search must not show report sections in the first agent/admin MVP.');
$assert(!str_contains($searchView, 'case "report"'), 'iOS Search must not render report result rows in the first agent/admin MVP.');
$assert(!str_contains(strtolower($screenshotDemo), 'billing'), 'iOS App Store screenshot fixture must not show billing copy in the agent/admin MVP.');
$assert(!str_contains(strtolower($screenshotDemo), 'report'), 'iOS App Store screenshot fixture must not show report copy in the agent/admin MVP.');

$assert(str_contains($accountView, 'Contact support'), 'iOS Account must keep support available.');
$assert(str_contains($accountView, 'Privacy Policy'), 'iOS Account must keep privacy policy available.');
$assert(str_contains($accountView, 'Request account deletion'), 'iOS Account must keep account deletion available.');
$assert(str_contains($accountView, '#if DEBUG'), 'Push diagnostics must stay debug-only.');
$assert(str_contains($accountView, 'Copy APNs token'), 'Debug/staging Account must still let testers copy the APNs token.');
$assert(str_contains($project, 'Staging: debug'), 'Staging must stay a debug config so APNs diagnostics are available for physical-device smoke testing.');
$assert(str_contains($project, 'Production: release'), 'Production must stay a release config for App Store builds.');

$assert(!str_contains($tenantModels, 'TenantBillingActions'), 'Native tenant models must not include billing action data.');
$assert(!str_contains($tenantModels, 'showCheckout'), 'Native tenant models must ignore checkout flags returned by the backend.');
$assert(!str_contains($tenantModels, 'subscriptionStatus'), 'Native tenant models must not include subscription state.');
$assert(!str_contains($tenantModels, 'trialEndsAt'), 'Native tenant models must not include trial state.');
$assert(str_contains($runbook, 'No billing or upgrade flow inside iOS'), 'Runbook must keep billing outside the iOS MVP.');
$assert(str_contains($handoff, 'Billing, pricing, Stripe, platform administration, reports, and full workspace'), 'Handoff must keep out-of-scope surfaces out of iOS.');

echo "iOS MVP scope contract OK\n";
