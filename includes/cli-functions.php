<?php
/**
 * Shared helpers for CLI entrypoints.
 */

function cli_scheduler_log($channel, $level, $message, $context = [])
{
    try {
        $has_table = (bool) db_fetch_one("SHOW TABLES LIKE 'debug_log'");
        if (!$has_table) {
            return;
        }

        if (!is_string($context)) {
            $context = json_encode($context, JSON_UNESCAPED_UNICODE);
        }

        db_insert('debug_log', [
            'channel' => (string) $channel,
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => (string) ($context ?: ''),
            'user_id' => null,
            'ip_address' => 'cli',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (Throwable $e) {
        // Logging must never break scheduled maintenance jobs.
    }
}
