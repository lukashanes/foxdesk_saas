<?php
/** Render the active SaaS settings section from the allowlisted registry. */

$settings_section_partial = settings_section_partial($tab);
?>

<div class="admin-shell">
    <?php render_admin_settings_tabs($tab); ?>
    <?php include $settings_section_partial; ?>
</div>
