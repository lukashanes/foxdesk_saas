<?php

$root = dirname(__DIR__);

$plan = file_get_contents($root . '/docs/TECHNICAL_DEBT_PLAN.md');
$readme = file_get_contents($root . '/README.md');
$release = file_get_contents($root . '/docs/RELEASE_CHANNELS.md');

if ($plan === false || $readme === false || $release === false) {
    fwrite(STDERR, "Unable to read technical debt planning files.\n");
    exit(1);
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
};

$assert(str_contains($plan, 'Primary product track: FoxDesk SaaS'), 'Plan must mark SaaS as the primary product track.');
$assert(str_contains($plan, 'Secondary track: public self-hosted PHP FoxDesk'), 'Plan must define the self-hosted maintenance boundary.');
$assert(str_contains($plan, 'Non-Negotiable Rules'), 'Plan must include non-negotiable engineering rules.');
$assert(str_contains($plan, 'Milestone 1 - Lock Product Boundary'), 'Plan must define milestone 1.');
$assert(str_contains($plan, 'Milestone 10 - Self-Hosted Final Maintenance Gate'), 'Plan must define self-hosted maintenance gate.');
$assert(str_contains($plan, 'Native App API Freeze'), 'Plan must include native app readiness.');
$assert(str_contains($plan, 'Billing State Matrix'), 'Plan must include SaaS billing state debt.');
$assert(str_contains($plan, 'CSP Inline Style Reduction'), 'Plan must include CSP/UI runtime debt.');
$assert(str_contains($plan, 'Storage Finalization For SaaS'), 'Plan must include SaaS storage debt.');
$assert(str_contains($plan, 'Email Event Unification'), 'Plan must include email and notification debt.');

$assert(str_contains($readme, 'This repository is the **SaaS/managed deployment** repository.'), 'README must keep the SaaS release boundary.');
$assert(str_contains($release, 'FoxDesk SaaS / Cloud'), 'Release channel docs must keep SaaS channel.');
$assert(str_contains($release, 'must not include'), 'Release channel docs must keep public update exclusions.');

echo "Technical debt plan contract OK\n";
