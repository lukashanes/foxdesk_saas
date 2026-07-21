<?php
/**
 * Render coordinator for the unified reports page.
 */

function report_render_partial(string $name, array $context): void
{
    $allowed = ['filters', 'time', 'weekly', 'billing', 'worklog', 'rates', 'published', 'entry-modal'];
    if (!in_array($name, $allowed, true)) {
        throw new InvalidArgumentException('Unknown report partial: ' . $name);
    }

    extract($context, EXTR_SKIP);
    require __DIR__ . '/views/' . $name . '.php';
}

function report_render_admin_page(array $context): void
{
    extract($context, EXTR_SKIP);
    require __DIR__ . '/views/page.php';
}
