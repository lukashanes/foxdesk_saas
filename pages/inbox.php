<?php
/**
 * Legacy Inbox route.
 *
 * Inbox used to be a separate customer-facing workspace page. The product flow
 * now keeps new, unassigned, waiting, and personal queues together in Work.
 */

$queue_map = [
    'customer_replies' => 'waiting',
    'email_imports' => 'unassigned',
    'triage' => 'unassigned',
];

$legacy_queue = trim((string) ($_GET['queue'] ?? 'triage'));
$work_queue = $queue_map[$legacy_queue] ?? 'unassigned';

redirect('work', ['queue' => $work_queue]);
