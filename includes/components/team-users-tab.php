<?php
defined('BASE_PATH') || exit;
?>
<!-- ============================================= -->
        <!-- USERS TAB (existing) -->
        <!-- ============================================= -->

        <div class="admin-two-column">
            <!-- Users List -->
            <div class="admin-main-column">
                <div class="admin-list-card">
                    <div class="card-header">
                        <h3 class="font-semibold text-theme-primary"><?php echo e(t('Users')); ?>
                            (<?php echo count($users); ?>)</h3>
                    </div>

                    <div class="admin-filter-bar">
                        <form method="get" class="flex flex-wrap items-end gap-3">
                            <input type="hidden" name="page" value="admin">
                            <input type="hidden" name="section" value="users">
                            <div>
                                <label class="block text-xs mb-1 text-theme-muted"><?php echo e(t('Time range')); ?></label>
                                <select name="time_range" id="users-time-range" class="form-select">
                                    <option value="all" <?php echo $time_range === 'all' ? 'selected' : ''; ?>>
                                        <?php echo e(t('All time')); ?>
                                    </option>
                                    <option value="this_week" <?php echo $time_range === 'this_week' ? 'selected' : ''; ?>>
                                        <?php echo e(t('This week')); ?>
                                    </option>
                                    <option value="this_month" <?php echo $time_range === 'this_month' ? 'selected' : ''; ?>>
                                        <?php echo e(t('This month')); ?>
                                    </option>
                                    <option value="last_month" <?php echo $time_range === 'last_month' ? 'selected' : ''; ?>>
                                        <?php echo e(t('Last month')); ?>
                                    </option>
                                    <option value="custom" <?php echo $time_range === 'custom' ? 'selected' : ''; ?>>
                                        <?php echo e(t('Custom range')); ?>
                                    </option>
                                </select>
                            </div>
                            <div id="users-custom-range"
                                class="flex flex-wrap items-end gap-3 <?php echo $time_range === 'custom' ? '' : 'hidden'; ?>">
                                <div>
                                    <label class="block text-xs mb-1 text-theme-muted"><?php echo e(t('From date')); ?></label>
                                    <input type="date" name="from_date" value="<?php echo e($from_date); ?>" class="form-input">
                                </div>
                                <div>
                                    <label class="block text-xs mb-1 text-theme-muted"><?php echo e(t('To date')); ?></label>
                                    <input type="date" name="to_date" value="<?php echo e($to_date); ?>" class="form-input">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-secondary btn-sm"><?php echo e(t('Apply')); ?></button>
                        </form>
                    </div>

                    <div class="admin-responsive-table-wrap">
                        <form method="get" id="user-filter-form" class="hidden">
                            <input type="hidden" name="page" value="admin">
                            <input type="hidden" name="section" value="users">
                            <?php if ($time_range !== 'all'): ?>
                                    <input type="hidden" name="time_range" value="<?php echo e($time_range); ?>">
                            <?php endif; ?>
                            <?php if ($from_date !== ''): ?>
                                    <input type="hidden" name="from_date" value="<?php echo e($from_date); ?>">
                            <?php endif; ?>
                            <?php if ($to_date !== ''): ?>
                                    <input type="hidden" name="to_date" value="<?php echo e($to_date); ?>">
                            <?php endif; ?>
                        </form>
                        <table class="admin-responsive-table admin-users-table tickets-table">
                            <thead class="bg-theme-secondary">
                                <tr class="border-b">
                                    <th class="px-4 py-2 text-left th-label">
                                        <?php echo e(t('Name')); ?>
                                    </th>
                                    <th class="px-4 py-2 text-left th-label w-32">
                                        <?php echo e(t('Company')); ?>
                                    </th>
                                    <th class="px-4 py-2 text-left th-label w-24">
                                        <?php echo e(t('Role')); ?>
                                    </th>
                                    <th class="px-4 py-2 text-left th-label w-28">
                                        <?php echo e(t('Logged time')); ?>
                                    </th>
                                    <th class="px-4 py-2 text-left th-label w-20">
                                        <?php echo e(t('Status')); ?>
                                    </th>
                                    <th class="px-4 py-2 text-right th-label w-40">
                                        <?php echo e(t('Actions')); ?>
                                    </th>
                                </tr>
                                <!-- Inline Filter Row -->
                                <tr class="border-b bg-theme-secondary">
                                    <th class="px-3 py-1.5">
                                        <input type="text" name="search" value="<?php echo e($filter_search); ?>"
                                            form="user-filter-form" placeholder="<?php echo e(t('Name or email...')); ?>"
                                            class="form-input form-input-sm w-full text-xs">
                                    </th>
                                    <th class="px-3 py-1.5">
                                        <!-- No filter for company -->
                                    </th>
                                    <th class="px-3 py-1.5">
                                        <select name="role" form="user-filter-form"
                                            class="form-select form-select-sm w-full text-xs"
                                            onchange="document.getElementById('user-filter-form').submit()">
                                            <option value=""><?php echo e(t('All')); ?></option>
                                            <option value="user" <?php echo $filter_role === 'user' ? 'selected' : ''; ?>>
                                                <?php echo e(t('User')); ?>
                                            </option>
                                            <option value="agent" <?php echo $filter_role === 'agent' ? 'selected' : ''; ?>>
                                                <?php echo e(t('Agent')); ?>
                                            </option>
                                            <option value="admin" <?php echo $filter_role === 'admin' ? 'selected' : ''; ?>>
                                                <?php echo e(t('Admin')); ?>
                                            </option>
                                        </select>
                                    </th>
                                    <th class="px-3 py-1.5">
                                        <!-- No filter for logged time -->
                                    </th>
                                    <th class="px-3 py-1.5">
                                        <select name="status" form="user-filter-form"
                                            class="form-select form-select-sm w-full text-xs"
                                            onchange="document.getElementById('user-filter-form').submit()">
                                            <option value=""><?php echo e(t('All')); ?></option>
                                            <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>
                                                <?php echo e(t('Active')); ?>
                                            </option>
                                            <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>><?php echo e(t('Archived')); ?></option>
                                        </select>
                                    </th>
                                    <th class="px-3 py-1.5 text-right">
                                        <button type="submit" form="user-filter-form" class="btn btn-primary btn-xs"
                                            title="<?php echo e(t('Apply')); ?>">
                                            <?php echo get_icon('search', 'w-3 h-3'); ?>
                                        </button>
                                        <?php if ($filter_search !== '' || $filter_role !== '' || $filter_status !== ''): ?>
                                                <a href="<?php echo url('admin', ['section' => 'users']); ?>"
                                                    class="btn btn-secondary btn-xs ml-1" title="<?php echo e(t('Clear')); ?>">
                                                    <?php echo get_icon('x', 'w-3 h-3'); ?>
                                                </a>
                                        <?php endif; ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <?php foreach ($users as $u): ?>
                                        <tr class="tr-hover">
                                            <td class="px-4 py-2.5 admin-responsive-primary" data-label="<?php echo e(t('Name')); ?>">
                                                <div class="flex items-center space-x-2">
                                                    <?php echo render_user_avatar($u, 'sm'); ?>
                                                    <div class="admin-cell-main">
                                                        <span class="admin-cell-title text-sm"><?php echo e($u['first_name'] . ' ' . $u['last_name']); ?></span>
                                                        <div class="admin-cell-subtitle text-xs">
                                                            <?php echo e($u['email']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-2.5 text-xs text-theme-secondary" data-label="<?php echo e(t('Company')); ?>">
                                                <?php if (!empty($u['organization_name'])): ?>
                                                        <span class="admin-cell-muted"><?php echo e($u['organization_name']); ?></span>
                                                <?php else: ?>
                                                        <span class="text-theme-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-2.5" data-label="<?php echo e(t('Role')); ?>">
                                                <?php
                                                $role_labels = ['user' => t('User'), 'agent' => t('Agent'), 'admin' => t('Admin')];
                                                $role_badge = [
                                                    'user' => 'team-user-badge--muted',
                                                    'agent' => 'bg-blue-100 text-blue-600',
                                                    'admin' => 'bg-purple-100 text-purple-600'
                                                ];
                                                $badge_class = $role_badge[$u['role']] ?? $role_badge['user'];
                                                ?>
                                                <span class="badge text-xs <?php echo e($badge_class); ?>">
                                                    <?php echo e($role_labels[$u['role']] ?? $u['role']); ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-2.5 text-xs text-theme-secondary" data-label="<?php echo e(t('Logged time')); ?>">
                                                <?php
                                                $total_minutes = $time_totals[$u['id']] ?? 0;
                                                echo $total_minutes > 0 ? e(format_duration_minutes($total_minutes)) : '<span class="text-theme-muted">-</span>';
                                                ?>
                                            </td>
                                            <td class="px-4 py-2.5" data-label="<?php echo e(t('Status')); ?>">
                                                <?php if ($u['is_active']): ?>
                                                        <span
                                                            class="text-xs px-2 py-0.5 fd-rounded-control bg-green-100 text-green-600"><?php echo e(t('Active')); ?></span>
                                                <?php else: ?>
                                                        <span class="text-xs px-2 py-1 fd-rounded-control bg-theme-tertiary text-theme-secondary"><?php echo e(t('Archived')); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-2.5 text-right admin-responsive-actions" data-label="<?php echo e(t('Actions')); ?>">
                                                <div class="flex items-center justify-end gap-1 relative z-10">
                                                    <?php if ($u['id'] != $_SESSION['user_id'] && (int) ($u['is_active'] ?? 0) === 1): ?>
                                                            <form method="post" action="index.php?page=impersonate" class="inline">
                                                                <?php echo csrf_field(); ?>
                                                                <input type="hidden" name="page" value="impersonate">
                                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                                <button type="submit"
                                                                    class="p-1.5 fd-rounded-control hover:bg-purple-50 text-purple-500 hover:text-purple-700 transition-colors"
                                                                    title="<?php echo e(t('Log in as user')); ?>">
                                                                    <?php echo get_icon('user-check', 'w-4 h-4'); ?>
                                                                </button>
                                                            </form>
                                                    <?php endif; ?>
                                                    <a href="<?php echo url('user-profile', ['id' => $u['id']]); ?>"
                                                        class="p-1.5 fd-rounded-control transition-colors text-theme-muted hover:text-theme-secondary"
                                                        title="<?php echo e(t('Ticket history')); ?>">
                                                        <?php echo get_icon('clock', 'w-4 h-4'); ?>
                                                    </a>
                                                    <button type="button"
                                                        onclick='editUser(<?php echo json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'
                                                        class="p-1.5 fd-rounded-control hover:bg-blue-50 dark:bg-blue-900/20 text-blue-500 hover:text-blue-700 transition-colors"
                                                        title="<?php echo e(t('Edit')); ?>">
                                                        <?php echo get_icon('edit', 'w-4 h-4'); ?>
                                                    </button>
                                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                                            <form method="post" class="inline"
                                                                onsubmit="return confirm('<?php echo e($u['is_active']
                                                                    ? t('Are you sure you want to archive this user?')
                                                                    : t('Are you sure you want to permanently delete this archived user? Ticket ownership, comments, and time entries will be transferred to your account.')); ?>');">
                                                                <?php echo csrf_field(); ?>
                                                                <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                                                <input type="hidden" name="purge"
                                                                    value="<?php echo $u['is_active'] ? '0' : '1'; ?>">
                                                                <button type="submit" name="delete_user"
                                                                    class="p-1.5 fd-rounded-control <?php echo $u['is_active']
                                                                        ? 'hover:bg-orange-50 text-orange-500 hover:text-orange-700 dark:text-orange-400 dark:hover:text-orange-300 dark:hover:bg-orange-900/30'
                                                                        : 'hover:bg-red-50 text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 dark:hover:bg-red-900/30'; ?> transition-colors"
                                                                    title="<?php echo e($u['is_active'] ? t('Archive user') : t('Delete permanently')); ?>">
                                                                    <?php echo get_icon($u['is_active'] ? 'archive' : 'trash', 'w-4 h-4'); ?>
                                                                </button>
                                                            </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Add New User -->
            <div class="admin-side-column">
                <div class="card card-body">
                    <h3 class="font-semibold mb-4 text-theme-primary"><?php echo e(t('Add user')); ?></h3>

                    <form method="post" class="space-y-4" id="addUserForm">
                        <?php echo csrf_field(); ?>
                        <div>
                            <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Email')); ?> *</label>
                            <input type="email" name="email" required class="form-input">
                        </div>

                        <?php if ($contact_phone_column_exists): ?>
                                <div>
                                    <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Phone')); ?></label>
                                    <input type="text" name="contact_phone" class="form-input">
                                </div>
                        <?php endif; ?>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('First name')); ?>
                                    *</label>
                                <input type="text" name="first_name" required class="form-input">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Last name')); ?></label>
                                <input type="text" name="last_name" class="form-input">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Password')); ?>
                                *</label>
                            <input type="password" name="password" required minlength="6" class="form-input">
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Cost rate (per hour)')); ?></label>
                            <input type="number" name="cost_rate" step="0.01" min="0" class="form-input">
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Role')); ?></label>
                            <select name="role" id="add_role" onchange="togglePermissions('add')" class="form-select">
                                <option value="user"><?php echo e(t('User')); ?></option>
                                <option value="agent"><?php echo e(t('Agent')); ?></option>
                                <option value="admin"><?php echo e(t('Admin')); ?></option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Language')); ?></label>
                            <select name="language" class="form-select">
                                <option value="en"><?php echo e(t('English')); ?></option>
                                <option value="cs"><?php echo e(t('Czech')); ?></option>
                                <option value="de"><?php echo e(t('German')); ?></option>
                                <option value="it"><?php echo e(t('Italian')); ?></option>
                                <option value="es"><?php echo e(t('Spanish')); ?></option>
                            </select>
                        </div>

                        <?php if (!empty($organizations)): ?>
                                <div>
                                    <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Company')); ?></label>
                                    <select name="organization_id" id="add_organization_id" class="form-select">
                                        <option value=""><?php echo e(t('-- No organization --')); ?></option>
                                        <?php foreach ($organizations as $org): ?>
                                                <option value="<?php echo $org['id']; ?>"><?php echo e($org['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Organizations')); ?></label>
                                    <select name="organization_membership_ids[]" id="add_organization_membership_ids" multiple size="5"
                                        class="form-select text-sm">
                                        <?php foreach ($organizations as $org): ?>
                                                <option value="<?php echo $org['id']; ?>"><?php echo e($org['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="text-xs mt-1 text-theme-muted">
                                        <?php echo e(t('Ctrl+click to select multiple organizations.')); ?>
                                    </p>
                                </div>
                        <?php endif; ?>

                        <?php if ($notes_column_exists): ?>
                                <div>
                                    <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Notes')); ?></label>
                                    <textarea name="notes" rows="3" class="form-textarea"></textarea>
                                </div>
                        <?php endif; ?>

                        <?php if ($notification_preferences_available): ?>
                                <div class="border-t pt-4 space-y-2">
                                    <h4 class="text-sm font-semibold text-theme-secondary">
                                        <?php echo e(t('Notification settings')); ?>
                                    </h4>
                                    <label id="add_email_notifications_wrap" class="flex items-center text-sm">
                                        <input type="checkbox" name="email_notifications_enabled" id="add_email_notifications_enabled"
                                            class="mr-2" checked>
                                        <?php echo e(t('Enable email notifications')); ?>
                                    </label>
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="in_app_notifications_enabled" id="add_in_app_notifications_enabled"
                                            class="mr-2" checked>
                                        <?php echo e(t('Enable in-app notifications')); ?>
                                    </label>
                                    <label class="flex items-center text-sm ml-6">
                                        <input type="checkbox" name="in_app_sound_enabled" id="add_in_app_sound_enabled" class="mr-2">
                                        <?php echo e(t('Play notification sound')); ?>
                                    </label>
                                </div>
                        <?php endif; ?>

                        <!-- Permissions (show for agents and users) -->
                        <div id="add_permissions" class="hidden border-t pt-4">
                            <h4 class="text-sm font-semibold mb-3 text-theme-secondary">
                                <?php echo e(t('Permissions')); ?>
                            </h4>

                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs mb-2 text-theme-secondary"><?php echo e(t('Ticket scope:')); ?></label>
                                    <div class="space-y-2">
                                        <label class="flex items-center text-sm">
                                            <input type="radio" name="ticket_scope" value="all" class="mr-2">
                                            <?php echo e(t('All tickets')); ?>
                                        </label>
                                        <label class="flex items-center text-sm">
                                            <input type="radio" name="ticket_scope" value="assigned" class="mr-2">
                                            <?php echo e(t('Assigned tickets only')); ?>
                                        </label>
                                        <label class="flex items-center text-sm">
                                            <input type="radio" name="ticket_scope" value="organization" class="mr-2"
                                                id="add_scope_org">
                                            <?php echo e(t('Tickets from selected organizations')); ?>
                                        </label>
                                        <label class="flex items-center text-sm">
                                            <input type="radio" name="ticket_scope" value="own" checked class="mr-2">
                                            <?php echo e(t('Own tickets only')); ?>
                                        </label>
                                    </div>

                                    <?php if (!empty($organizations)): ?>
                                            <div id="add_org_select" class="mt-2 hidden">
                                                <label class="block text-xs mb-1 text-theme-muted"><?php echo e(t('Select organizations (multiple allowed)')); ?></label>
                                                <select name="scope_organization_ids[]" multiple size="5" class="form-select text-sm">
                                                    <?php foreach ($organizations as $org): ?>
                                                            <option value="<?php echo $org['id']; ?>"><?php echo e($org['name']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <p class="text-xs mt-1 text-theme-muted">
                                                    <?php echo e(t('Ctrl+click to select multiple organizations.')); ?>
                                                </p>
                                            </div>
                                    <?php endif; ?>
                                </div>

                                <div id="add_can_archive_wrap">
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="can_archive" class="mr-2">
                                        <?php echo e(t('Can archive tickets')); ?>
                                    </label>
                                </div>
                                <div id="add_can_delete_tickets_permanently_wrap">
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="can_delete_tickets_permanently" class="mr-2">
                                        <?php echo e(t('Can permanently delete tickets')); ?>
                                    </label>
                                    <p class="text-xs mt-1 text-theme-muted">
                                        <?php echo e(t('Allows irreversible deletion of a ticket and all related data.')); ?>
                                    </p>
                                </div>
                                <div>
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="can_view_edit_history" class="mr-2">
                                        <?php echo e(t('Can view edit history')); ?>
                                    </label>
                                </div>
                                <div id="add_can_import_md_wrap">
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="can_import_md" class="mr-2">
                                        <?php echo e(t('Can import .md')); ?>
                                    </label>
                                </div>
                                <div>
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="can_view_time" class="mr-2">
                                        <?php echo e(t('Can view time entries')); ?>
                                    </label>
                                </div>
                                <div>
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="can_view_timeline" class="mr-2">
                                        <?php echo e(t('Can view activity timeline')); ?>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-theme-light pt-4 mt-4">
                            <label class="flex items-center text-sm cursor-pointer">
                                <input type="checkbox" name="send_welcome_email" class="mr-2">
                                <?php echo e(t('Send login credentials via email')); ?>
                            </label>
                        </div>

                        <button type="submit" name="add_user" class="btn btn-primary w-full">
                            <?php echo e(t('Add user')); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Permissions Matrix -->
        <div class="mt-3">
            <div class="card overflow-hidden">
                <div class="card-header flex items-center justify-between">
                    <div>
                        <h3 class="font-semibold text-theme-primary"><?php echo e(t('Permissions Matrix')); ?>
                        </h3>
                        <p class="text-sm mt-1 text-theme-muted">
                            <?php echo e(t('Overview of user access and permissions')); ?>
                        </p>
                    </div>
                    <button type="button" onclick="toggleMatrix()"
                        class="text-sm text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors">
                        <span id="matrix-toggle-text"><?php echo e(t('Show')); ?></span>
                    </button>
                </div>

                <div id="permissions-matrix" class="hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-theme-secondary">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium text-theme-secondary">
                                        <?php echo e(t('User')); ?>
                                    </th>
                                    <th class="px-4 py-3 text-left font-medium text-theme-secondary">
                                        <?php echo e(t('Role')); ?>
                                    </th>
                                    <th class="px-4 py-3 text-left font-medium text-theme-secondary">
                                        <?php echo e(t('Ticket Scope')); ?>
                                    </th>
                                    <th class="px-4 py-3 text-left font-medium text-theme-secondary">
                                        <?php echo e(t('Organizations')); ?>
                                    </th>
                                    <th class="px-4 py-3 text-center font-medium text-theme-secondary">
                                        <?php echo e(t('Can Archive')); ?>
                                    </th>
                                    <th class="px-4 py-3 text-center font-medium text-theme-secondary">
                                        <?php echo e(t('Can view edit history')); ?>
                                    </th>
                                    <th class="px-4 py-3 text-center font-medium text-theme-secondary">
                                        <?php echo e(t('Can import .md')); ?>
                                    </th>
                                    <th class="px-4 py-3 text-center font-medium text-theme-secondary">
                                        <?php echo e(t('Status')); ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <?php foreach ($users as $u):
                                    $perms = [];
                                    if (!empty($u['permissions'])) {
                                        $perms = json_decode($u['permissions'], true) ?? [];
                                    }
                                    $ticket_scope = $perms['ticket_scope'] ?? ($u['role'] === 'admin' ? 'all' : 'own');
                                    $org_ids = $perms['organization_ids'] ?? [];
                                    $can_archive = !empty($perms['can_archive']);
                                    $can_view_edit_history = !empty($perms['can_view_edit_history']);
                                    $can_import_md = !empty($perms['can_import_md']);

                                    // Get organization names for display
                                    $org_names = [];
                                    if (!empty($org_ids)) {
                                        foreach ($organizations as $org) {
                                            if (in_array($org['id'], $org_ids)) {
                                                $org_names[] = $org['name'];
                                            }
                                        }
                                    }
                                    // Also include primary organization if set
                                    if (!empty($u['organization_name']) && !in_array($u['organization_name'], $org_names)) {
                                        array_unshift($org_names, $u['organization_name'] . ' ★');
                                    }

                                    // Scope labels
                                    $scope_labels = [
                                        'all' => t('All tickets'),
                                        'assigned' => t('Assigned only'),
                                        'organization' => t('Organization'),
                                        'own' => t('Own tickets')
                                    ];
                                    $scope_label = $scope_labels[$ticket_scope] ?? $ticket_scope;

                                    // Scope colors (class + style pairs)
                                    $scope_colors = [
                                        'all' => 'bg-green-100 text-green-700',
                                        'assigned' => 'bg-yellow-100 text-yellow-700',
                                        'organization' => 'bg-blue-100 text-blue-700',
                                        'own' => 'team-user-badge--muted'
                                    ];
                                    $scope_color_class = $scope_colors[$ticket_scope] ?? 'team-user-badge--muted';

                                    // Role colors (class + style pairs)
                                    $role_colors = [
                                        'admin' => 'bg-purple-100 text-purple-700',
                                        'agent' => 'bg-blue-100 text-blue-700',
                                        'user' => 'team-user-badge--muted'
                                    ];
                                    $role_color_class = $role_colors[$u['role']] ?? 'team-user-badge--muted';
                                    ?>
                                        <tr class="tr-hover <?php echo $u['is_active'] ? '' : 'opacity-50'; ?>">
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-2">
                                                    <?php echo render_user_avatar($u, 'xs'); ?>
                                                    <div>
                                                        <div class="font-medium text-theme-primary">
                                                            <?php echo e($u['first_name'] . ' ' . $u['last_name']); ?>
                                                        </div>
                                                        <div class="text-xs text-theme-muted">
                                                            <?php echo e($u['email']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="text-xs px-2 py-0.5 fd-rounded-control <?php echo e($role_color_class); ?>">
                                                    <?php echo e(ucfirst($u['role'])); ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="text-xs px-2 py-0.5 fd-rounded-control <?php echo e($scope_color_class); ?>">
                                                    <?php echo e($scope_label); ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <?php if ($u['role'] === 'admin'): ?>
                                                        <span class="text-xs italic text-theme-muted"><?php echo e(t('All organizations')); ?></span>
                                                <?php elseif (!empty($org_names)): ?>
                                                        <div class="flex flex-wrap gap-1">
                                                            <?php foreach (array_slice($org_names, 0, 3) as $name): ?>
                                                                    <span class="text-xs px-1.5 py-0.5 fd-rounded-control bg-theme-secondary text-theme-secondary"><?php echo e($name); ?></span>
                                                            <?php endforeach; ?>
                                                            <?php if (count($org_names) > 3): ?>
                                                                    <span class="text-xs text-theme-muted">+<?php echo count($org_names) - 3; ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                <?php else: ?>
                                                        <span class="text-xs text-theme-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <?php if ($u['role'] === 'admin'): ?>
                                                        <?php echo get_icon('check', 'w-4 h-4 text-green-500 mx-auto'); ?>
                                                <?php elseif ($can_archive): ?>
                                                        <?php echo get_icon('check', 'w-4 h-4 text-green-500 mx-auto'); ?>
                                                <?php else: ?>
                                                        <?php echo get_icon('x', 'w-4 h-4 text-gray-400 mx-auto'); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <?php if ($u['role'] === 'admin'): ?>
                                                        <?php echo get_icon('check', 'w-4 h-4 text-green-500 mx-auto'); ?>
                                                <?php elseif ($can_view_edit_history): ?>
                                                        <?php echo get_icon('check', 'w-4 h-4 text-green-500 mx-auto'); ?>
                                                <?php else: ?>
                                                        <?php echo get_icon('x', 'w-4 h-4 text-gray-400 mx-auto'); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <?php if ($u['role'] === 'admin'): ?>
                                                        <?php echo get_icon('check', 'w-4 h-4 text-green-500 mx-auto'); ?>
                                                <?php elseif ($can_import_md): ?>
                                                        <?php echo get_icon('check', 'w-4 h-4 text-green-500 mx-auto'); ?>
                                                <?php else: ?>
                                                        <?php echo get_icon('x', 'w-4 h-4 text-gray-400 mx-auto'); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <?php if ($u['is_active']): ?>
                                                        <span
                                                            class="text-xs px-2 py-0.5 fd-rounded-control bg-green-100 text-green-600"><?php echo e(t('Active')); ?></span>
                                                <?php else: ?>
                                                        <span
                                                            class="text-xs px-2 py-0.5 fd-rounded-control bg-red-100 text-red-600"><?php echo e(t('Inactive')); ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Legend -->
                    <div class="px-6 py-3 border-t bg-theme-secondary border-theme-light">
                        <div class="flex flex-wrap gap-3 text-xs text-theme-secondary">
                            <div>
                                <span class="font-medium"><?php echo e(t('Ticket Scope:')); ?></span>
                                <span
                                    class="ml-2 px-1.5 py-0.5 fd-rounded-control bg-green-100 text-green-700"><?php echo e(t('All')); ?></span>
                                <span
                                    class="ml-1 px-1.5 py-0.5 fd-rounded-control bg-yellow-100 text-yellow-700"><?php echo e(t('Assigned')); ?></span>
                                <span
                                    class="ml-1 px-1.5 py-0.5 fd-rounded-control bg-blue-100 text-blue-700"><?php echo e(t('Org')); ?></span>
                                <span class="ml-1 px-1.5 py-0.5 fd-rounded-control bg-theme-secondary text-theme-secondary"><?php echo e(t('Own')); ?></span>
                            </div>
                            <div>
                                <span class="font-medium"><?php echo e(t('Organizations:')); ?></span>
                                <span class="ml-2">★ = <?php echo e(t('Primary organization')); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            function toggleMatrix() {
                const matrix = document.getElementById('permissions-matrix');
                const toggleText = document.getElementById('matrix-toggle-text');
                if (matrix.classList.contains('hidden')) {
                    matrix.classList.remove('hidden');
                    toggleText.textContent = '<?php echo e(t('Hide')); ?>';
                } else {
                    matrix.classList.add('hidden');
                    toggleText.textContent = '<?php echo e(t('Show')); ?>';
                }
            }
        </script>

        <!-- Edit User Modal -->
        <div id="editModal"
            class="fixed inset-0 bg-black bg-opacity-50 hidden items-start sm:items-center justify-center z-50 overflow-y-auto p-2 sm:p-3">
            <div class="fd-rounded-card shadow-xl w-full max-w-2xl max-h-[calc(100vh-1rem)] sm:max-h-[calc(100vh-1.5rem)] overflow-hidden flex flex-col bg-theme-primary">
                <div class="px-4 sm:px-6 py-3.5 border-b border-theme-light flex items-center justify-between sticky top-0 z-10 bg-theme-primary">
                    <h3 class="font-semibold text-theme-primary"><?php echo e(t('Edit user')); ?></h3>
                    <button type="button" onclick="closeModal()" class="p-1 text-theme-muted"
                        aria-label="<?php echo e(t('Cancel')); ?>">
                        <?php echo get_icon('x', 'w-5 h-5'); ?>
                    </button>
                </div>

                <div
                    class="p-4 sm:p-5 overflow-y-auto overscroll-contain max-h-[calc(100vh-8.5rem)] sm:max-h-[calc(100vh-9.5rem)]">

                    <!-- Avatar upload (separate form) -->
                    <div class="flex items-center gap-4 pb-3.5 mb-3.5 border-b border-theme-light">
                        <div id="edit_avatar_preview">
                            <div class="w-14 h-14 fd-rounded-pill flex items-center justify-center bg-theme-tertiary text-theme-secondary">
                                <span class="text-lg font-bold" id="edit_avatar_initial"></span>
                            </div>
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium mb-1.5 text-theme-secondary"><?php echo e(t('Avatar')); ?></label>
                            <form method="post" enctype="multipart/form-data" class="mb-1">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="user_id" id="edit_avatar_user_id">
                                <input type="hidden" name="upload_user_avatar" value="1">
                                <div id="user-avatar-edit-zone" class="fd-rounded-card p-2 cursor-pointer border-2 border-dashed hover:border-blue-300 transition-colors border-theme-light">
                                    <input type="file" name="user_avatar" id="user-avatar-edit-input" accept="image/jpeg,image/png,image/gif,image/webp"
                                        class="hidden" onchange="this.form.submit()">
                                    <div class="flex items-center gap-2 text-xs">
                                        <span class="text-theme-muted"><?php echo get_icon('cloud-upload-alt', 'text-base flex-shrink-0'); ?></span>
                                        <span><span class="text-blue-500 font-medium"><?php echo e(t('Click')); ?></span> <?php echo e(t('or drag file')); ?></span>
                                    </div>
                                </div>
                                <p id="user-avatar-edit-filename" class="text-xs mt-1 hidden text-theme-secondary"></p>
                            </form>
                            <div class="flex items-center gap-2">
                                <form method="post" id="remove_avatar_form" class="inline hidden">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="user_id" id="remove_avatar_user_id">
                                    <button type="submit" name="remove_user_avatar"
                                        class="btn btn-secondary btn-sm text-red-500 hover:text-red-700">
                                        <?php echo get_icon('trash', 'w-3.5 h-3.5'); ?> <?php echo e(t('Remove')); ?>
                                    </button>
                                </form>
                            </div>
                            <p class="text-xs mt-1 text-theme-muted">
                                <?php echo e(t('Square image recommended. Max 2 MB.')); ?>
                            </p>
                        </div>
                    </div>

                    <form method="post" id="editForm" class="space-y-3.5">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="id" id="edit_id">

                        <div>
                            <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Email')); ?> *</label>
                            <input type="email" name="email" id="edit_email" required class="form-input">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('First name')); ?></label>
                                <input type="text" name="first_name" id="edit_first_name" class="form-input">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Last name')); ?></label>
                                <input type="text" name="last_name" id="edit_last_name" class="form-input">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Role')); ?></label>
                            <select name="role" id="edit_role" onchange="togglePermissions('edit')" class="form-select">
                                <option value="user"><?php echo e(t('User')); ?></option>
                                <option value="agent"><?php echo e(t('Agent')); ?></option>
                                <option value="admin"><?php echo e(t('Admin')); ?></option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Language')); ?></label>
                            <select name="language" id="edit_language" class="form-select">
                                <option value="en"><?php echo e(t('English')); ?></option>
                                <option value="cs"><?php echo e(t('Czech')); ?></option>
                                <option value="de"><?php echo e(t('German')); ?></option>
                                <option value="it"><?php echo e(t('Italian')); ?></option>
                                <option value="es"><?php echo e(t('Spanish')); ?></option>
                            </select>
                        </div>

                        <?php if ($contact_phone_column_exists): ?>
                                <div>
                                    <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Phone')); ?></label>
                                    <input type="text" name="contact_phone" id="edit_contact_phone" class="form-input">
                                </div>
                        <?php endif; ?>

                        <div>
                            <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Cost rate (per hour)')); ?></label>
                            <input type="number" name="cost_rate" id="edit_cost_rate" step="0.01" min="0" class="form-input">
                        </div>

                        <?php if (!empty($organizations)): ?>
                                <div>
                                    <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Company')); ?></label>
                                    <select name="organization_id" id="edit_organization_id" class="form-select">
                                        <option value=""><?php echo e(t('-- No organization --')); ?></option>
                                        <?php foreach ($organizations as $org): ?>
                                                <option value="<?php echo $org['id']; ?>"><?php echo e($org['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Organizations')); ?></label>
                                    <select name="organization_membership_ids[]" id="edit_organization_membership_ids" multiple size="5"
                                        class="form-select text-sm">
                                        <?php foreach ($organizations as $org): ?>
                                                <option value="<?php echo $org['id']; ?>"><?php echo e($org['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="text-xs mt-1 text-theme-muted">
                                        <?php echo e(t('Ctrl+click to select multiple organizations.')); ?>
                                    </p>
                                </div>
                        <?php endif; ?>

                        <?php if ($notes_column_exists): ?>
                                <div>
                                    <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Notes')); ?></label>
                                    <textarea name="notes" id="edit_notes" rows="3" class="form-textarea"></textarea>
                                </div>
                        <?php endif; ?>

                        <?php if ($notification_preferences_available): ?>
                                <div class="border-t pt-4 space-y-2">
                                    <h4 class="text-sm font-semibold text-theme-secondary">
                                        <?php echo e(t('Notification settings')); ?>
                                    </h4>
                                    <label id="edit_email_notifications_wrap" class="flex items-center text-sm">
                                        <input type="checkbox" name="email_notifications_enabled" id="edit_email_notifications_enabled"
                                            class="mr-2">
                                        <?php echo e(t('Enable email notifications')); ?>
                                    </label>
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="in_app_notifications_enabled"
                                            id="edit_in_app_notifications_enabled" class="mr-2">
                                        <?php echo e(t('Enable in-app notifications')); ?>
                                    </label>
                                    <label class="flex items-center text-sm ml-6">
                                        <input type="checkbox" name="in_app_sound_enabled" id="edit_in_app_sound_enabled" class="mr-2">
                                        <?php echo e(t('Play notification sound')); ?>
                                    </label>
                                </div>
                        <?php endif; ?>

                        <!-- Permissions (show for agents and users) -->
                        <div id="edit_permissions" class="hidden border-t pt-4">
                            <h4 class="text-sm font-semibold mb-3 text-theme-secondary">
                                <?php echo e(t('Permissions')); ?>
                            </h4>

                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs mb-2 text-theme-secondary"><?php echo e(t('Ticket scope:')); ?></label>
                                    <div class="space-y-2">
                                        <label class="flex items-center text-sm">
                                            <input type="radio" name="ticket_scope" value="all" id="edit_scope_all"
                                                class="mr-2">
                                            <?php echo e(t('All tickets')); ?>
                                        </label>
                                        <label class="flex items-center text-sm">
                                            <input type="radio" name="ticket_scope" value="assigned" id="edit_scope_assigned"
                                                class="mr-2">
                                            <?php echo e(t('Assigned tickets only')); ?>
                                        </label>
                                        <label class="flex items-center text-sm">
                                            <input type="radio" name="ticket_scope" value="organization" id="edit_scope_org"
                                                class="mr-2">
                                            <?php echo e(t('Tickets from selected organizations')); ?>
                                        </label>
                                        <label class="flex items-center text-sm">
                                            <input type="radio" name="ticket_scope" value="own" id="edit_scope_own"
                                                class="mr-2">
                                            <?php echo e(t('Own tickets only')); ?>
                                        </label>
                                    </div>

                                    <?php if (!empty($organizations)): ?>
                                            <div id="edit_org_select" class="mt-2 hidden">
                                                <label class="block text-xs mb-1 text-theme-muted"><?php echo e(t('Select organizations (multiple allowed)')); ?></label>
                                                <select name="scope_organization_ids[]" id="edit_scope_organization_ids" multiple
                                                    size="5" class="form-select text-sm">
                                                    <?php foreach ($organizations as $org): ?>
                                                            <option value="<?php echo $org['id']; ?>"><?php echo e($org['name']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <p class="text-xs mt-1 text-theme-muted">
                                                    <?php echo e(t('Ctrl+click to select multiple organizations.')); ?>
                                                </p>
                                            </div>
                                    <?php endif; ?>
                                </div>

                                <div id="edit_can_archive_wrap">
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="can_archive" id="edit_can_archive" class="mr-2">
                                        <?php echo e(t('Can archive tickets')); ?>
                                    </label>
                                </div>
                                <div id="edit_can_delete_tickets_permanently_wrap">
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="can_delete_tickets_permanently"
                                            id="edit_can_delete_tickets_permanently" class="mr-2">
                                        <?php echo e(t('Can permanently delete tickets')); ?>
                                    </label>
                                    <p class="text-xs mt-1 text-theme-muted">
                                        <?php echo e(t('Allows irreversible deletion of a ticket and all related data.')); ?>
                                    </p>
                                </div>
                                <div>
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="can_view_edit_history" id="edit_can_view_edit_history"
                                            class="mr-2">
                                        <?php echo e(t('Can view edit history')); ?>
                                    </label>
                                </div>
                                <div id="edit_can_import_md_wrap">
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="can_import_md" id="edit_can_import_md" class="mr-2">
                                        <?php echo e(t('Can import .md')); ?>
                                    </label>
                                </div>
                                <div>
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="can_view_time" id="edit_can_view_time" class="mr-2">
                                        <?php echo e(t('Can view time entries')); ?>
                                    </label>
                                </div>
                                <div>
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="can_view_timeline" id="edit_can_view_timeline" class="mr-2">
                                        <?php echo e(t('Can view activity timeline')); ?>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" name="is_active" id="edit_is_active" class="mr-2 fd-rounded-control">
                                <span class="text-sm text-theme-secondary"><?php echo e(t('Active account')); ?></span>
                            </label>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-3">
                            <button type="button" onclick="closeModal()" class="btn btn-secondary flex-1">
                                <?php echo e(t('Cancel')); ?>
                            </button>
                            <button type="submit" name="update_user" class="btn btn-primary flex-1">
                                <?php echo e(t('Save')); ?>
                            </button>
                        </div>
                    </form>

                    <hr class="my-4">

                    <form method="post" class="flex flex-col sm:flex-row sm:items-end gap-3">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="id" id="reset_id">
                        <div class="flex-1">
                            <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('New password')); ?></label>
                            <input type="password" name="new_password" minlength="6" class="form-input">
                        </div>
                        <button type="submit" name="reset_password" class="btn btn-warning w-full sm:w-auto">
                            <?php echo e(t('Change password')); ?>
                        </button>
                    </form>

                    <form method="post" class="mt-3">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="id" id="reset_email_id">
                        <button type="submit" name="send_reset_email" class="btn btn-secondary w-full sm:w-auto">
                            <?php echo e(t('Send password reset email')); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <script>
            function togglePermissions(prefix) {
                const role = document.getElementById(prefix + '_role').value;
                const permissionsDiv = document.getElementById(prefix + '_permissions');
                const form = permissionsDiv ? permissionsDiv.closest('form') : null;
                const emailWrap = document.getElementById(prefix + '_email_notifications_wrap');
                const emailCheckbox = document.getElementById(prefix + '_email_notifications_enabled');
                const canArchiveWrap = document.getElementById(prefix + '_can_archive_wrap');
                const canArchiveInput = form ? form.querySelector('input[name="can_archive"]') : null;
                const canImportMdWrap = document.getElementById(prefix + '_can_import_md_wrap');
                const canImportMdInput = form ? form.querySelector('input[name="can_import_md"]') : null;

                // Show permissions for both agents and users (not admins)
                if ((role === 'agent' || role === 'user') && permissionsDiv) {
                    permissionsDiv.classList.remove('hidden');
                } else if (permissionsDiv) {
                    permissionsDiv.classList.add('hidden');
                }

                if (emailWrap) {
                    const allowEmailToggle = (role === 'agent' || role === 'user');
                    emailWrap.classList.toggle('hidden', !allowEmailToggle);
                    if (!allowEmailToggle && emailCheckbox) {
                        emailCheckbox.checked = true;
                    }
                }

                const isAgent = role === 'agent';
                if (canArchiveWrap) {
                    canArchiveWrap.classList.toggle('hidden', !isAgent);
                }
                if (!isAgent && canArchiveInput) {
                    canArchiveInput.checked = false;
                }
                if (canImportMdWrap) {
                    canImportMdWrap.classList.toggle('hidden', !isAgent);
                }
                if (!isAgent && canImportMdInput) {
                    canImportMdInput.checked = false;
                }
            }

            function toggleInAppSound(prefix) {
                const inAppCheckbox = document.getElementById(prefix + '_in_app_notifications_enabled');
                const soundCheckbox = document.getElementById(prefix + '_in_app_sound_enabled');
                if (!inAppCheckbox || !soundCheckbox) {
                    return;
                }
                const enabled = inAppCheckbox.checked;
                soundCheckbox.disabled = !enabled;
                if (!enabled) {
                    soundCheckbox.checked = false;
                }
            }

            // Toggle organization select based on ticket scope
            document.addEventListener('DOMContentLoaded', function () {
                const bindScopeToggle = (formId, orgContainerId) => {
                    const form = document.getElementById(formId);
                    const orgSelect = document.getElementById(orgContainerId);
                    if (!form || !orgSelect) {
                        return;
                    }
                    const sync = () => {
                        const checked = form.querySelector('input[name="ticket_scope"]:checked');
                        const isOrgScope = checked && checked.value === 'organization';
                        orgSelect.classList.toggle('hidden', !isOrgScope);
                    };
                    form.querySelectorAll('input[name="ticket_scope"]').forEach((radio) => {
                        radio.addEventListener('change', sync);
                    });
                    sync();
                };
                const bindPrimaryOrganizationSync = (primarySelectId, membershipsSelectId) => {
                    const primarySelect = document.getElementById(primarySelectId);
                    const membershipsSelect = document.getElementById(membershipsSelectId);
                    if (!primarySelect || !membershipsSelect) {
                        return;
                    }

                    primarySelect.addEventListener('change', () => {
                        const value = primarySelect.value;
                        if (!value) {
                            return;
                        }
                        for (const option of membershipsSelect.options) {
                            if (option.value === value) {
                                option.selected = true;
                            }
                        }
                    });

                    membershipsSelect.addEventListener('change', () => {
                        if (primarySelect.value) {
                            return;
                        }
                        const firstSelected = Array.from(membershipsSelect.options).find((option) => option.selected);
                        if (firstSelected) {
                            primarySelect.value = firstSelected.value;
                        }
                    });
                };

                bindScopeToggle('editForm', 'edit_org_select');
                bindScopeToggle('addUserForm', 'add_org_select');
                bindPrimaryOrganizationSync('add_organization_id', 'add_organization_membership_ids');
                bindPrimaryOrganizationSync('edit_organization_id', 'edit_organization_membership_ids');

                const addInAppCheckbox = document.getElementById('add_in_app_notifications_enabled');
                if (addInAppCheckbox) {
                    addInAppCheckbox.addEventListener('change', function () {
                        toggleInAppSound('add');
                    });
                    toggleInAppSound('add');
                }

                const editInAppCheckbox = document.getElementById('edit_in_app_notifications_enabled');
                if (editInAppCheckbox) {
                    editInAppCheckbox.addEventListener('change', function () {
                        toggleInAppSound('edit');
                    });
                    toggleInAppSound('edit');
                }

                const timeRangeSelect = document.getElementById('users-time-range');
                const customRange = document.getElementById('users-custom-range');
                if (timeRangeSelect && customRange) {
                    const toggleCustom = () => {
                        customRange.classList.toggle('hidden', timeRangeSelect.value !== 'custom');
                    };
                    timeRangeSelect.addEventListener('change', toggleCustom);
                    toggleCustom();
                }

                const editModal = document.getElementById('editModal');
                if (editModal) {
                    editModal.addEventListener('click', function (event) {
                        if (event.target === editModal) {
                            closeModal();
                        }
                    });
                }

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && editModal && !editModal.classList.contains('hidden')) {
                        closeModal();
                    }
                });

                togglePermissions('add');
                togglePermissions('edit');
                toggleInAppSound('add');
                toggleInAppSound('edit');
            });

            function editUser(user) {
                document.getElementById('edit_id').value = user.id;
                document.getElementById('reset_id').value = user.id;
                document.getElementById('reset_email_id').value = user.id;
                document.getElementById('edit_email').value = user.email || '';
                document.getElementById('edit_first_name').value = user.first_name;
                document.getElementById('edit_last_name').value = user.last_name || '';
                document.getElementById('edit_role').value = user.role;
                document.getElementById('edit_language').value = user.language || 'en';
                document.getElementById('edit_is_active').checked = user.is_active == 1;

                // Avatar preview
                document.getElementById('edit_avatar_user_id').value = user.id;
                document.getElementById('remove_avatar_user_id').value = user.id;
                const avatarPreview = document.getElementById('edit_avatar_preview');
                const removeAvatarForm = document.getElementById('remove_avatar_form');
                while (avatarPreview.firstChild) avatarPreview.removeChild(avatarPreview.firstChild);
                const avatarInitials = ((user.first_name || user.email || '?').charAt(0)).toUpperCase();
                const avatarWrapper = document.createElement('span');
                avatarWrapper.className = 'user-avatar user-avatar--edit';
                const avatarFallback = document.createElement('span');
                avatarFallback.className = 'user-avatar__initials';
                avatarFallback.textContent = avatarInitials;
                avatarWrapper.appendChild(avatarFallback);
                if (user.avatar) {
                    const img = document.createElement('img');
                    img.src = user.avatar;
                    img.alt = '';
                    img.className = 'user-avatar__image';
                    img.onerror = function () {
                        this.classList.add('is-hidden');
                        this.removeAttribute('src');
                    };
                    avatarWrapper.appendChild(img);
                    avatarPreview.appendChild(avatarWrapper);
                    removeAvatarForm.classList.remove('hidden');
                } else {
                    avatarPreview.appendChild(avatarWrapper);
                    removeAvatarForm.classList.add('hidden');
                }

                const contactPhoneInput = document.getElementById('edit_contact_phone');
                if (contactPhoneInput) {
                    contactPhoneInput.value = user.contact_phone || '';
                }
                const notesInput = document.getElementById('edit_notes');
                if (notesInput) {
                    notesInput.value = user.notes || '';
                }

                const emailCheckbox = document.getElementById('edit_email_notifications_enabled');
                if (emailCheckbox) {
                    emailCheckbox.checked = parseInt(user.email_notifications_enabled ?? 1, 10) === 1;
                }
                const inAppCheckbox = document.getElementById('edit_in_app_notifications_enabled');
                if (inAppCheckbox) {
                    inAppCheckbox.checked = parseInt(user.in_app_notifications_enabled ?? 1, 10) === 1;
                }
                const inAppSoundCheckbox = document.getElementById('edit_in_app_sound_enabled');
                if (inAppSoundCheckbox) {
                    inAppSoundCheckbox.checked = parseInt(user.in_app_sound_enabled ?? 0, 10) === 1;
                }
                const costRateInput = document.getElementById('edit_cost_rate');
                if (costRateInput) {
                    costRateInput.value = user.cost_rate || '';
                }

                const orgSelect = document.getElementById('edit_organization_id');
                if (orgSelect) {
                    orgSelect.value = user.organization_id || '';
                }

                const canArchive = document.getElementById('edit_can_archive');
                if (canArchive) {
                    canArchive.checked = false;
                }
                const canDeleteTicketsPermanently = document.getElementById('edit_can_delete_tickets_permanently');
                if (canDeleteTicketsPermanently) {
                    canDeleteTicketsPermanently.checked = false;
                }
                const canViewEditHistory = document.getElementById('edit_can_view_edit_history');
                if (canViewEditHistory) {
                    canViewEditHistory.checked = false;
                }
                const canImportMd = document.getElementById('edit_can_import_md');
                if (canImportMd) {
                    canImportMd.checked = false;
                }
                const canViewTime = document.getElementById('edit_can_view_time');
                if (canViewTime) {
                    canViewTime.checked = false;
                }
                const canViewTimeline = document.getElementById('edit_can_view_timeline');
                if (canViewTimeline) {
                    canViewTimeline.checked = false;
                }

                const defaultScopeRadios = document.querySelectorAll('#editForm input[name="ticket_scope"]');
                defaultScopeRadios.forEach((radio) => {
                    radio.checked = radio.value === 'all';
                });

                const defaultScopeOrgSelect = document.getElementById('edit_scope_organization_ids');
                if (defaultScopeOrgSelect) {
                    for (const option of defaultScopeOrgSelect.options) {
                        option.selected = false;
                    }
                }

                const membershipSelect = document.getElementById('edit_organization_membership_ids');
                if (membershipSelect) {
                    for (const option of membershipSelect.options) {
                        option.selected = false;
                    }
                }

                let permissions = {};
                if ((user.role === 'agent' || user.role === 'user') && user.permissions) {
                    try {
                        permissions = typeof user.permissions === 'string' ? JSON.parse(user.permissions) : user.permissions;
                    } catch (e) {
                        permissions = {};
                    }
                }
                if (typeof permissions !== 'object' || permissions === null) {
                    permissions = {};
                }

                if (user.role === 'user' && !permissions.ticket_scope) {
                    permissions.ticket_scope = user.organization_id ? 'organization' : 'own';
                }

                const scopeValue = permissions.ticket_scope || 'all';
                defaultScopeRadios.forEach((radio) => {
                    radio.checked = radio.value === scopeValue;
                });

                const permissionOrgIds = Array.isArray(permissions.organization_ids)
                    ? permissions.organization_ids
                    : (permissions.organization_ids ? [permissions.organization_ids] : []);

                if (scopeValue === 'organization' && defaultScopeOrgSelect) {
                    const selected = new Set(permissionOrgIds.map((id) => parseInt(id, 10)).filter((id) => !Number.isNaN(id) && id > 0));
                    for (const option of defaultScopeOrgSelect.options) {
                        option.selected = selected.has(parseInt(option.value, 10));
                    }
                    const editOrgSelect = document.getElementById('edit_org_select');
                    if (editOrgSelect) {
                        editOrgSelect.classList.remove('hidden');
                    }
                } else {
                    const editOrgSelect = document.getElementById('edit_org_select');
                    if (editOrgSelect) {
                        editOrgSelect.classList.add('hidden');
                    }
                }

                if (canArchive) {
                    canArchive.checked = permissions.can_archive === true;
                }
                if (canDeleteTicketsPermanently) {
                    canDeleteTicketsPermanently.checked = permissions.can_delete_tickets_permanently === true;
                }
                if (canViewEditHistory) {
                    canViewEditHistory.checked = permissions.can_view_edit_history === true;
                }
                if (canImportMd) {
                    canImportMd.checked = permissions.can_import_md === true;
                }
                if (canViewTime) {
                    canViewTime.checked = permissions.can_view_time === true;
                }
                if (canViewTimeline) {
                    canViewTimeline.checked = permissions.can_view_timeline === true;
                }

                if (membershipSelect) {
                    const membershipIds = new Set();
                    if (user.organization_id) {
                        membershipIds.add(parseInt(user.organization_id, 10));
                    }
                    permissionOrgIds.forEach((id) => {
                        const parsed = parseInt(id, 10);
                        if (!Number.isNaN(parsed) && parsed > 0) {
                            membershipIds.add(parsed);
                        }
                    });
                    for (const option of membershipSelect.options) {
                        option.selected = membershipIds.has(parseInt(option.value, 10));
                    }
                }

                togglePermissions('edit');
                toggleInAppSound('edit');

                document.getElementById('editModal').classList.remove('hidden');
                document.getElementById('editModal').classList.add('flex');
                document.body.classList.add('overflow-hidden');

                const editModalBody = document.querySelector('#editModal .overflow-y-auto');
                if (editModalBody) {
                    editModalBody.scrollTop = 0;
                }

                const emailInput = document.getElementById('edit_email');
                if (emailInput) {
                    emailInput.focus();
                    emailInput.select();
                }
            }

            function closeModal() {
                document.getElementById('editModal').classList.add('hidden');
                document.getElementById('editModal').classList.remove('flex');
                document.body.classList.remove('overflow-hidden');
            }

            // User avatar edit – drag & drop (must wait for DOMContentLoaded so app-footer.js defer script is loaded)
            function initUserAvatarDropzone() {
                if (!window.initFileDropzone) return;
                window.initFileDropzone({
                    zoneId: 'user-avatar-edit-zone',
                    inputId: 'user-avatar-edit-input',
                    acceptedExtensions: ['.jpg', '.jpeg', '.png', '.gif', '.webp'],
                    invalidTypeMessage: '<?php echo e(t('Invalid file type.')); ?>',
                    onFilesChanged: function(files) {
                        var label = document.getElementById('user-avatar-edit-filename');
                        if (label && files.length > 0) {
                            label.textContent = files[0].name;
                            label.classList.remove('hidden');
                            var form = document.getElementById('user-avatar-edit-input')?.closest('form');
                            if (form) form.submit();
                        }
                    }
                });
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initUserAvatarDropzone);
            } else {
                initUserAvatarDropzone();
            }
        </script>
