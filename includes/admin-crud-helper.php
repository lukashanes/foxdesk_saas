<?php
/**
 * Admin CRUD Helper Functions
 *
 * Common utility functions for admin CRUD operations.
 * Provides standardized patterns for create, update, delete, and reorder.
 */

/**
 * Reorder items in a table via sort_order column
 */
function reorder_items($table, $order_array) {
    validate_sql_identifier($table);
    if (!is_array($order_array) || empty($order_array)) {
        return false;
    }

    foreach ($order_array as $index => $id) {
        db_update($table, ['sort_order' => $index + 1], 'id = ?', [(int)$id]);
    }

    return true;
}

function admin_crud_slug_from_name(string $name, string $separator = '_'): string
{
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', $separator, $name));
    return trim($slug, $separator);
}

function admin_crud_unique_slug(string $table, string $name, string $separator = '_'): string
{
    validate_sql_identifier($table);
    $slug = admin_crud_slug_from_name($name, $separator);
    $existing = db_fetch_one("SELECT id FROM {$table} WHERE slug = ?", [$slug]);

    return $existing ? $slug . $separator . time() : $slug;
}

function admin_crud_next_sort_order(string $table): int
{
    validate_sql_identifier($table);
    $max = db_fetch_one("SELECT MAX(sort_order) as max_order FROM {$table}");

    return (int) (($max['max_order'] ?? 0) + 1);
}

function admin_crud_clear_default(string $table): void
{
    validate_sql_identifier($table);
    db_query("UPDATE {$table} SET is_default = 0");
}

function admin_crud_delete_if_unused(string $table, int $id, string $usage_sql, array $usage_params): bool
{
    validate_sql_identifier($table);
    $usage = db_fetch_one($usage_sql, $usage_params);
    if ($usage && (int) ($usage['count'] ?? $usage['c'] ?? 0) > 0) {
        return false;
    }

    db_delete($table, 'id = ?', [$id]);
    return true;
}

/**
 * Move an item up in sort order
 */
function move_item_up($table, $id) {
    validate_sql_identifier($table);
    $item = db_fetch_one("SELECT id, sort_order FROM {$table} WHERE id = ?", [$id]);

    if (!$item) {
        return ['success' => false, 'message' => 'Item not found'];
    }

    $above = db_fetch_one(
        "SELECT id, sort_order FROM {$table} WHERE sort_order < ? ORDER BY sort_order DESC LIMIT 1",
        [$item['sort_order']]
    );

    if (!$above) {
        return ['success' => false, 'message' => 'Already at top'];
    }

    // Swap sort orders
    db_update($table, ['sort_order' => $above['sort_order']], 'id = ?', [$item['id']]);
    db_update($table, ['sort_order' => $item['sort_order']], 'id = ?', [$above['id']]);

    return ['success' => true];
}

/**
 * Move an item down in sort order
 */
function move_item_down($table, $id) {
    validate_sql_identifier($table);
    $item = db_fetch_one("SELECT id, sort_order FROM {$table} WHERE id = ?", [$id]);

    if (!$item) {
        return ['success' => false, 'message' => 'Item not found'];
    }

    $below = db_fetch_one(
        "SELECT id, sort_order FROM {$table} WHERE sort_order > ? ORDER BY sort_order ASC LIMIT 1",
        [$item['sort_order']]
    );

    if (!$below) {
        return ['success' => false, 'message' => 'Already at bottom'];
    }

    // Swap sort orders
    db_update($table, ['sort_order' => $below['sort_order']], 'id = ?', [$item['id']]);
    db_update($table, ['sort_order' => $item['sort_order']], 'id = ?', [$below['id']]);

    return ['success' => true];
}

/**
 * Standard API response helpers
 */
function api_success($data = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

function api_error($message, $code = 400) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['error' => $message, 'success' => false]);
    exit;
}

/**
 * Check for admin permission and POST method
 */
function require_admin_post() {
    if (!is_admin()) {
        api_error('Forbidden', 403);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        api_error('Method not allowed', 405);
    }

    require_csrf_token(true);
}

/**
 * Get JSON input from request body
 */
function get_json_input() {
    $input = json_decode(file_get_contents('php://input'), true);
    return is_array($input) ? $input : [];
}

