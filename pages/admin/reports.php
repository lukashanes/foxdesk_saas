<?php
/**
 * Admin reports route.
 *
 * Request handling, read models, rendering and browser behavior live in the
 * reports module. Keep this route as the stable composition boundary.
 */

$report_context = report_admin_page_context($_GET, $_POST, $_SERVER);
$page_title = $report_context['page_title'];
$page = $report_context['page'];

require_once BASE_PATH . '/includes/header.php';
report_render_admin_page($report_context);
require_once BASE_PATH . '/includes/footer.php';
