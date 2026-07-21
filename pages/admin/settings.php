<?php
/**
 * Admin settings route.
 *
 * Request handling, section state, and rendering are intentionally split so
 * this route remains a small, edition-specific entrypoint.
 */

$page_title = t('Settings');
$page = 'admin';
$settings = get_settings();

require_once BASE_PATH . '/includes/modules/settings/settings-page-controller.php';
require_once BASE_PATH . '/includes/modules/settings/settings-page-view-model.php';

require_once BASE_PATH . '/includes/header.php';

$page_header_title = $page_title;
$page_header_subtitle = t('Configure system-wide preferences.');
include BASE_PATH . '/includes/components/page-header.php';

include BASE_PATH . '/includes/modules/settings/settings-page-render.php';

require_once BASE_PATH . '/includes/footer.php';
