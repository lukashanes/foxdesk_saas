<?php

$root = dirname(__DIR__);

$plan = file_get_contents($root . '/docs/TECHNICAL_DEBT_PLAN.md');
$readme = file_get_contents($root . '/README.md');
$release = file_get_contents($root . '/docs/RELEASE_CHANNELS.md');
$monolith_inventory = file_get_contents($root . '/docs/MONOLITH_EXIT_INVENTORY.md');
$edition_matrix = file_get_contents($root . '/docs/EDITION_PARITY_MATRIX.md');

if ($plan === false || $readme === false || $release === false || $monolith_inventory === false || $edition_matrix === false) {
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
$assert(str_contains($plan, 'docs/MONOLITH_EXIT_INVENTORY.md'), 'Plan must link the monolith exit inventory.');
$assert(str_contains($plan, 'docs/EDITION_PARITY_MATRIX.md'), 'Plan must link the edition parity matrix.');

$assert(str_contains($readme, 'This repository is the **SaaS/managed deployment** repository.'), 'README must keep the SaaS release boundary.');
$assert(str_contains($release, 'FoxDesk SaaS / Cloud'), 'Release channel docs must keep SaaS channel.');
$assert(str_contains($release, 'must not include'), 'Release channel docs must keep public update exclusions.');
$assert(str_contains($edition_matrix, '| Billing | saas |'), 'Edition matrix must classify SaaS billing.');
$assert(str_contains($edition_matrix, '| Migration source | self-hosted |'), 'Edition matrix must classify self-hosted migration source.');

$assert(str_contains($monolith_inventory, 'pages/ticket-detail.php'), 'Inventory must include ticket-detail.');
$assert(str_contains($monolith_inventory, 'pages/admin/reports.php'), 'Inventory must include admin reports.');
$assert(str_contains($monolith_inventory, 'pages/admin/settings.php'), 'Inventory must include admin settings.');

echo "Technical debt plan contract OK\n";
