<?php

$source = (string) file_get_contents(dirname(__DIR__) . '/bin/repair-mojibake.php');
$assertions = [
    'Dry-run is the default mode' => str_contains($source, "\$apply = array_key_exists('apply', \$options);")
        && str_contains($source, "'mode' => \$apply ? 'apply' : 'dry-run'"),
    'Apply requires explicit confirmation' => str_contains($source, "--confirm=REPAIR-MOJIBAKE"),
    'Sensitive columns are excluded' => str_contains($source, 'password|token|secret|signature|cipher|hash'),
    'Only single-primary-key tables are mutable' => str_contains($source, 'count($primaryKeys) !== 1'),
    'Apply changes are transactional per table' => str_contains($source, '$pdo->beginTransaction();')
        && str_contains($source, '$pdo->rollBack();'),
];
$failed = array_keys(array_filter($assertions, static fn(bool $passed): bool => !$passed));
if ($failed !== []) {
    fwrite(STDERR, "Mojibake repair contract failed:\n - " . implode("\n - ", $failed) . "\n");
    exit(1);
}
echo "Mojibake repair contract passed.\n";
