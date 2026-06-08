<?php

$root = dirname(__DIR__);
$doc_path = $root . '/docs/PRODUCT_VOICE_AND_VISUAL_RESTYLE.md';
$assert = function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
};

$assert(is_file($doc_path), 'Product voice and visual restyle plan must exist.');
$doc = file_get_contents($doc_path);

foreach ([
    'FoxDesk Voice',
    'Do not expose internal implementation details',
    'Fixed typography scale',
    'letter-spacing: 0',
    'Milestone 1: Voice Baseline',
    'Milestone 7: Visual QA',
] as $needle) {
    $assert(str_contains($doc, $needle), 'Restyle plan is missing: ' . $needle);
}

echo "Product voice restyle contract passed.\n";
