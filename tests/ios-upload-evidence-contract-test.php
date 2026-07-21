<?php

$root = dirname(__DIR__);
$fingerprintPath = $root . '/bin/ios-source-fingerprint.sh';
$evidencePath = $root . '/bin/ios-upload-evidence.sh';
$packagePath = $root . '/package.json';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$fingerprint = is_file($fingerprintPath) ? file_get_contents($fingerprintPath) : false;
$evidence = is_file($evidencePath) ? file_get_contents($evidencePath) : false;
$package = is_file($packagePath) ? json_decode((string) file_get_contents($packagePath), true) : null;

$assert($fingerprint !== false, 'Missing iOS source fingerprint script.');
$assert($evidence !== false, 'Missing iOS upload evidence script.');
$assert(is_array($package), 'Unable to read package.json.');
$assert(is_executable($fingerprintPath), 'iOS source fingerprint script must be executable.');
$assert(is_executable($evidencePath), 'iOS upload evidence script must be executable.');

$assert(str_contains($fingerprint, "! -path '*/DerivedData/*'"), 'Fingerprint must exclude generated DerivedData.');
$assert(str_contains($fingerprint, "! -path '*/DeviceDerivedData/*'"), 'Fingerprint must exclude generated device DerivedData.');
$assert(str_contains($evidence, 'uploadDestination === "App Store"'), 'Upload evidence must require an App Store upload event.');
$assert(str_contains($evidence, 'uploadEvent.state'), 'Upload evidence must inspect the upload result state.');
$assert(str_contains($evidence, 'source_fingerprint'), 'Upload evidence must record the iOS source fingerprint.');
$assert(str_contains($evidence, 'archive_binary_sha256'), 'Upload evidence must record the archived binary hash.');
$assert(str_contains($evidence, 'iOS source changed after upload'), 'Upload evidence must reject source changes after upload.');
$assert(str_contains($evidence, 'Archived binary changed after upload evidence was recorded'), 'Upload evidence must reject archive mutation.');

$scripts = $package['scripts'] ?? [];
$assert(($scripts['ios:upload:record'] ?? '') === './bin/ios-upload-evidence.sh --record', 'package.json must expose ios:upload:record.');
$assert(($scripts['ios:upload:evidence'] ?? '') === './bin/ios-upload-evidence.sh --check', 'package.json must expose ios:upload:evidence.');

echo "iOS upload evidence contract OK\n";
