<?php

$root = dirname(__DIR__);
$source = file_get_contents($root . '/includes/update-functions.php');
$e2e = file_get_contents($root . '/tests/e2e/03-update-report.spec.js');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$assert($source !== false && $e2e !== false, 'Rollback contract sources are missing.');
$assert(str_contains($source, 'function update_release_managed_root_files'), 'Release root-file allowlist is missing.');
$assert(str_contains($source, "return ['assets', 'includes', 'pages'];"), 'Release directory allowlist is not explicit.');
$assert(str_contains($source, 'copy_release_manifest_files($temp_dir, BASE_PATH, $backup_files)'), 'Rollback does not restore from the managed manifest.');
$assert(str_contains($source, 'update_release_path_is_managed($name)'), 'ZIP manifest is not restricted to release-managed paths.');

$rootAllowlist = '';
if (preg_match('/function update_release_managed_root_files\(\): array\s*\{(.*?)\n\}/s', $source, $match)) {
    $rootAllowlist = $match[1];
}
$assert($rootAllowlist !== '', 'Unable to inspect the release root-file allowlist.');
foreach (['config.php', '.env.production', 'secrets', 'node_modules', 'tests', 'docs', 'ios'] as $sensitive) {
    $assert(!str_contains($rootAllowlist, "'{$sensitive}'"), 'Sensitive or non-runtime content leaked into the root allowlist.');
}

$assert(str_contains($e2e, "BASE_PATH . '/pages/rollback-marker-e2e.php'"), 'Rollback E2E must validate removal inside a managed release directory.');

echo "Update backup scope contract OK\n";
