<?php

$root = dirname(__DIR__);
$script = $root . '/bin/ios-external-gates.sh';

if (!is_file($script)) {
    fwrite(STDERR, "Missing iOS external gates script.\n");
    exit(1);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$releaseEnvBypass = sys_get_temp_dir() . '/foxdesk-ios-release-env-does-not-exist-' . getmypid();
$command = sprintf(
    'cd %s && FOXDESK_IOS_RELEASE_ENV_FILE=%s APPLE_BUSINESS_VERIFIED=1 APP_STORE_CONNECT_APP_RECORD_READY=0 APPLE_DEVELOPER_BUNDLE_READY=0 APP_STORE_PRIVACY_REVIEWED=0 bash %s 2>&1',
    escapeshellarg($root),
    escapeshellarg($releaseEnvBypass),
    escapeshellarg($script)
);

$output = shell_exec($command);
$assert(is_string($output) && $output !== '', 'iOS external gate command produced no output.');

$assert(
    str_contains($output, 'Apple Business organization verification: ready'),
    'Apple Business verification should be reported as ready when APPLE_BUSINESS_VERIFIED=1.'
);
$assert(
    str_contains($output, 'App Store Connect record: missing'),
    'Apple Business verification must not satisfy the App Store Connect app record gate.'
);
$assert(
    str_contains($output, 'Apple Developer bundle and push capability: missing'),
    'Apple Business verification must not satisfy the Apple Developer bundle/push gate.'
);
$assert(
    !str_contains($output, 'App Store Connect record: ready'),
    'App Store Connect must not be reported ready without APP_STORE_CONNECT_APP_RECORD_READY=1.'
);
$assert(
    !str_contains($output, 'Apple Developer bundle and push capability: ready'),
    'Apple Developer bundle/push must not be reported ready without APPLE_DEVELOPER_BUNDLE_READY=1.'
);

$report = $root . '/tmp/ios-external-gates/latest.md';
$assert(is_file($report), 'iOS external gates must write tmp/ios-external-gates/latest.md.');
$reportBody = file_get_contents($report);
$assert($reportBody !== false, 'Unable to read iOS external gates report.');
$assert(
    str_contains($reportBody, '| App Store Connect record | missing |'),
    'External gate report must keep App Store Connect missing independently from Apple Business.'
);
$assert(
    str_contains($reportBody, '| Apple Developer bundle and push capability | missing |'),
    'External gate report must keep Apple Developer bundle/push missing independently from Apple Business.'
);
$assert(
    str_contains($reportBody, '| Apple Business organization verification | ready |'),
    'External gate report must record Apple Business verification as ready.'
);
$assert(
    str_contains($reportBody, 'manual date/start/end'),
    'External gate report must require manual date/start/end for the demo reviewer write proof.'
);

echo "iOS external gates contract OK\n";
