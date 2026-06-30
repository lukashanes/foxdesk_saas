<?php
/**
 * Admin settings tab navigation.
 */

function admin_settings_tabs(): array
{
    $email_description = (function_exists('settings_email_is_managed_surface') && settings_email_is_managed_surface())
        ? t('Support address and delivery status.')
        : t('Outgoing and incoming email.');

    return [
        'general' => ['label' => t('Workspace'), 'description' => t('Name, language, time and invoice defaults.'), 'icon' => 'cog'],
        'team' => ['label' => t('Team & access'), 'description' => t('Users, roles, and permissions.'), 'icon' => 'users', 'url' => url('admin', ['section' => 'users'])],
        'api' => ['label' => t('API & agents'), 'description' => t('API keys, AI agents, and test requests.'), 'icon' => 'key'],
        'clients' => ['label' => t('Clients'), 'description' => t('Contacts, companies, and access.'), 'icon' => 'user', 'url' => url('admin', ['section' => 'clients'])],
        'email' => ['label' => t('Email'), 'description' => $email_description, 'icon' => 'envelope'],
        'templates' => ['label' => t('Templates'), 'description' => t('Customer-facing notification text.'), 'icon' => 'file-alt'],
        'workflow' => ['label' => t('Workflow'), 'description' => t('Statuses, priorities, and ticket rules.'), 'icon' => 'tasks'],
        'reports' => ['label' => t('Reports & rates'), 'description' => t('Time, billing review, and rates.'), 'icon' => 'chart-bar', 'url' => url('admin', ['section' => 'reports', 'tab' => 'time'])],
        'security' => ['label' => t('Security'), 'description' => t('Access, sessions, and protection settings.'), 'icon' => 'shield'],
        'system' => ['label' => t('System'), 'description' => t('Updates, backups, tasks, and upload limits.'), 'icon' => 'desktop'],
        'logs' => ['label' => t('Activity'), 'description' => t('Operational history and diagnostics.'), 'icon' => 'list-alt'],
    ];
}

function render_admin_settings_tabs(string $active_tab): void
{
    ?>
    <div class="settings-section-nav" aria-label="<?php echo e(t('Settings sections')); ?>">
        <?php foreach (admin_settings_tabs() as $tab_key => $tab_meta): ?>
            <?php $is_active_tab = $active_tab === $tab_key; ?>
            <a href="<?php echo e($tab_meta['url'] ?? url('admin', ['section' => 'settings', 'tab' => $tab_key])); ?>"
                class="settings-section-card <?php echo $is_active_tab ? 'is-active' : ''; ?>"
                <?php echo $is_active_tab ? 'aria-current="page"' : ''; ?>>
                <span class="settings-section-card__icon"><?php echo get_icon($tab_meta['icon'], 'w-4 h-4'); ?></span>
                <span class="settings-section-card__body">
                    <strong><?php echo e($tab_meta['label']); ?></strong>
                    <span><?php echo e($tab_meta['description'] ?? ''); ?></span>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php
}
