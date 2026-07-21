<?php

$root = dirname(__DIR__);
$scanRoots = [$root . '/includes', $root . '/pages'];
$allowed = [
    realpath($root . '/includes/update-functions.php'),
    realpath($root . '/includes/schema-migration-runner.php'),
];
$pattern = '/\b(?:CREATE\s+TABLE|ALTER\s+TABLE|CREATE\s+(?:UNIQUE\s+)?INDEX|DROP\s+TABLE|DROP\s+COLUMN|MODIFY\s+COLUMN|ADD\s+COLUMN)\b/i';
$violations = [];

foreach ($scanRoots as $scanRoot) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scanRoot));
    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        $path = $file->getRealPath();
        if (in_array($path, $allowed, true)) {
            continue;
        }
        if (preg_match($pattern, (string) file_get_contents($path))) {
            $violations[] = str_replace($root . '/', '', $path);
        }
    }
}

if ($violations) {
    fwrite(STDERR, "Runtime schema mutations found outside install/update migrations:\n - " . implode("\n - ", $violations) . "\n");
    exit(1);
}

foreach ([
    'migrations/2026072001_runtime_schema_to_migration.php',
    'migrations/2026072002_runtime_feature_tables.sql',
    'migrations/2026072003_runtime_feature_columns.php',
] as $relative) {
    if (!is_file($root . '/' . $relative)) {
        fwrite(STDERR, "Missing versioned migration: {$relative}\n");
        exit(1);
    }
}

echo "Runtime schema mutation contract passed.\n";
