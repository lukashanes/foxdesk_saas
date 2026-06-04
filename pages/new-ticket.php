<?php
/**
 * New Ticket Page
 */

$page_title = t('New ticket');
$page = 'new-ticket';
$user = current_user();
$priorities = get_priorities();
$statuses = get_statuses();
$ticket_types = get_ticket_types();
$tags_supported = function_exists('ticket_tags_column_exists') && ticket_tags_column_exists();
$organizations = [];
$allowed_organization_ids = [];

try {
    if (is_admin()) {
        $organizations = get_organizations();
        $allowed_organization_ids = array_map(static function ($org) {
            return (int) ($org['id'] ?? 0);
        }, $organizations);
    } else {
        $allowed_organization_ids = get_user_organization_ids($user['id']);
        if (!empty($allowed_organization_ids)) {
            $lookup = array_flip($allowed_organization_ids);
            $organizations = array_values(array_filter(get_organizations(), static function ($org) use ($lookup) {
                return isset($lookup[(int) ($org['id'] ?? 0)]);
            }));
        }
    }
    $allowed_organization_ids = normalize_organization_ids($allowed_organization_ids);
} catch (Throwable $e) {
    $organizations = [];
    $allowed_organization_ids = [];
}

// Load staff users for "Assign to" and all users for "On behalf of" (admin/agent only)
$staff_users = [];
$all_users_list = [];
if (is_admin() || is_agent()) {
    $all_users_raw = get_all_users();
    foreach ($all_users_raw as $u) {
        if (in_array($u['role'], ['admin', 'agent'])) {
            $staff_users[] = $u;
        }
        $all_users_list[] = $u;
    }
}

$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();

    $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $type = $_POST['type'] ?? 'general';
        $priority_id = !empty($_POST['priority_id']) ? (int) $_POST['priority_id'] : null;
        $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        $tags = $tags_supported ? trim($_POST['tags'] ?? '') : '';
        $organization_id = !empty($_POST['organization_id']) ? (int) $_POST['organization_id'] : null;
        $assignee_id = (is_admin() || is_agent()) && !empty($_POST['assignee_id']) ? (int) $_POST['assignee_id'] : null;
        $on_behalf_of = (is_admin() || is_agent()) && !empty($_POST['on_behalf_of']) ? (int) $_POST['on_behalf_of'] : null;
        $status_id = (is_admin() || is_agent()) && !empty($_POST['status_id']) ? (int) $_POST['status_id'] : null;
        // Manual time entry (quick minutes or exact start/end range)
        $manual_duration_input = trim((string) ($_POST['manual_duration_minutes'] ?? ''));
        $manual_duration_minutes = $manual_duration_input !== '' ? (int) $manual_duration_input : 0;
        $manual_date = trim($_POST['manual_date'] ?? '');
        $manual_start_time = trim($_POST['manual_start_time'] ?? '');
        $manual_end_time = trim($_POST['manual_end_time'] ?? '');

        // Resolve ticket owner: agent can create on behalf of another user
        $ticket_owner_id = $user['id'];
        if ($on_behalf_of) {
            $behalf_user = get_user($on_behalf_of);
            if ($behalf_user && can_user_create_ticket_for($behalf_user, $user)) {
                $ticket_owner_id = (int) $behalf_user['id'];
            } else {
                $error = t('Selected user is not available.');
            }
        }

        if ($assignee_id) {
            $assignee_user = get_user($assignee_id);
            if (!$assignee_user || !can_user_assign_to_staff($assignee_user, $user)) {
                $error = t('Invalid assignee.');
            }
        }

        if ($due_date) {
            $due_date = normalize_due_date_input($due_date);
            if ($due_date === false) {
                $error = t('Invalid due date.');
            }
        }

        if (!empty($error)) {
            // Keep the validation error raised above.
        } elseif (empty($title)) {
            $error = t('Enter a subject.');
        } elseif ($organization_id !== null && $organization_id > 0 && !in_array($organization_id, $allowed_organization_ids, true)) {
            $error = t('Selected organization is not available.');
        } elseif (
            is_agent()
            && function_exists('ticket_time_table_exists')
            && ticket_time_table_exists()
            && $manual_duration_input !== ''
            && ($manual_duration_minutes < 1 || $manual_duration_minutes > 1440)
        ) {
            $error = t('Duration must be between 1 and 1440 minutes.');
        } else {
            $upload_errors = [];
            $create_data = [
                'title' => $title,
                'description' => $description,
                'type' => $type,
                'priority_id' => $priority_id,
                'user_id' => $ticket_owner_id,
                'organization_id' => $organization_id,
                'due_date' => $due_date,
                'tags' => $tags,
                'assignee_id' => $assignee_id
            ];
            if ($status_id) {
                $create_data['status_id'] = $status_id;
            }
            $ticket_id = create_ticket($create_data);

            log_activity($ticket_id, $user['id'], 'created', 'Ticket created');

            // Save time entry — manual start/end takes priority over simple minutes, which takes priority over timer
            $timer_elapsed = (int) ($_POST['timer_elapsed_seconds'] ?? 0);
            if (is_agent() && function_exists('ticket_time_table_exists') && ticket_time_table_exists()) {
                $manual_time_logged = false;

                // Manual time entry with start/end times
                if ($manual_start_time !== '' && $manual_end_time !== '') {
                    $base_date = $manual_date !== '' ? $manual_date : date('Y-m-d');
                    $end_date = $base_date;
                    // Midnight overflow: if end time < start time, it's the next day
                    if ($manual_end_time < $manual_start_time) {
                        $end_date = date('Y-m-d', strtotime($base_date . ' +1 day'));
                    }
                    $start_dt = DateTime::createFromFormat('Y-m-d H:i', $base_date . ' ' . $manual_start_time);
                    $end_dt = DateTime::createFromFormat('Y-m-d H:i', $end_date . ' ' . $manual_end_time);
                    if ($start_dt && $end_dt && $end_dt > $start_dt) {
                        $duration = max(0, (int) floor(($end_dt->getTimestamp() - $start_dt->getTimestamp()) / 60));
                        if ($duration > 0 && function_exists('add_manual_time_entry')) {
                            add_manual_time_entry($ticket_id, $user['id'], [
                                'started_at' => $start_dt->format('Y-m-d H:i:s'),
                                'ended_at' => $end_dt->format('Y-m-d H:i:s'),
                                'duration_minutes' => $duration,
                                'summary' => t('Ticket creation'),
                                'is_billable' => 1,
                            ]);
                            $manual_time_logged = true;
                        }
                    }
                }

                if (!$manual_time_logged && $manual_duration_input !== '' && $manual_duration_minutes > 0 && function_exists('add_manual_time_entry')) {
                    $end_dt = new DateTime();
                    $start_dt = (clone $end_dt)->modify('-' . $manual_duration_minutes . ' minutes');
                    add_manual_time_entry($ticket_id, $user['id'], [
                        'started_at' => $start_dt->format('Y-m-d H:i:s'),
                        'ended_at' => $end_dt->format('Y-m-d H:i:s'),
                        'duration_minutes' => $manual_duration_minutes,
                        'summary' => t('Ticket creation'),
                        'is_billable' => 1,
                    ]);
                    $manual_time_logged = true;
                }

                if (!$manual_time_logged && $timer_elapsed > 0) {
                    // Timer was running — save as completed entry
                    $ticket_billable_rate = function_exists('get_ticket_effective_billable_rate')
                        ? get_ticket_effective_billable_rate($ticket_id)
                        : 0.0;
                    $user_cost_rate = (float)($user['cost_rate'] ?? 0);
                    $timer_started = date('Y-m-d H:i:s', time() - $timer_elapsed);
                    $timer_duration = max(1, (int) floor($timer_elapsed / 60));
                    $timer_entry = [
                        'ticket_id' => $ticket_id,
                        'user_id' => $user['id'],
                        'started_at' => $timer_started,
                        'ended_at' => date('Y-m-d H:i:s'),
                        'duration_minutes' => $timer_duration,
                        'is_billable' => 1,
                        'billable_rate' => $ticket_billable_rate,
                        'cost_rate' => $user_cost_rate,
                        'is_manual' => 0,
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    if (function_exists('time_entry_source_column_exists') && time_entry_source_column_exists()) {
                        $timer_entry['source'] = 'timer';
                    }
                    db_insert('ticket_time_entries', $timer_entry);
                    log_activity($ticket_id, $user['id'], 'time_stopped', "Timer stopped ({$timer_duration} min)");
                }
            }

            // Handle file uploads
            if (!empty($_FILES['attachments']['name'][0])) {
                $files = $_FILES['attachments'];

                for ($i = 0; $i < count($files['name']); $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }

                    if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                        $upload_errors[] = $files['name'][$i] . ': ' . get_upload_error_message((int) $files['error'][$i], get_max_upload_size());
                        continue;
                    }

                    try {
                        $file = [
                            'name' => $files['name'][$i],
                            'type' => $files['type'][$i],
                            'tmp_name' => $files['tmp_name'][$i],
                            'error' => $files['error'][$i],
                            'size' => $files['size'][$i]
                        ];

                        $result = upload_file($file);

                        // Save attachment record
                        db_insert('attachments', array_merge([
                            'ticket_id' => $ticket_id,
                            'filename' => $result['filename'],
                            'original_name' => $result['original_name'],
                            'mime_type' => $result['mime_type'],
                            'file_size' => $result['file_size'],
                            'uploaded_by' => $user['id'],
                            'created_at' => date('Y-m-d H:i:s')
                        ], attachment_storage_fields($result)));
                    } catch (Exception $e) {
                        $upload_errors[] = $files['name'][$i] . ': ' . $e->getMessage();
                    }
                }

                if (!empty($upload_errors)) {
                    flash(t('Ticket created, but some files could not be uploaded: {errors}', ['errors' => implode(', ', $upload_errors)]), 'error');
                }
            }

            // Send notifications
            require_once BASE_PATH . '/includes/mailer.php';
            $ticket = get_ticket($ticket_id);
            send_new_ticket_notification($ticket);
            send_ticket_confirmation_to_user($ticket); // Confirmation to user

            // Send assignment notification if ticket was assigned on creation
            if ($assignee_id && function_exists('send_ticket_assignment_notification')) {
                $assigned_agent = get_user($assignee_id);
                if ($assigned_agent) {
                    send_ticket_assignment_notification($ticket, $assigned_agent, $user, [
                        'created_with_ticket' => true,
                    ]);
                }
            }

            // In-app notifications
            if (function_exists('dispatch_ticket_notifications')) {
                $desc_preview = strip_tags($description);
                $desc_preview = mb_strlen($desc_preview) > 80 ? mb_substr($desc_preview, 0, 77) . '...' : $desc_preview;
                dispatch_ticket_notifications('new_ticket', $ticket_id, $user['id'], [
                    'comment_preview' => $desc_preview,
                ]);
                if ($assignee_id) {
                    dispatch_ticket_notifications('assigned_to_you', $ticket_id, $user['id'], [
                        'assignee_id' => $assignee_id,
                    ]);
                }
            }

            if (empty($upload_errors)) {
                flash(t('Ticket created successfully.'), 'success');
            }
            redirect('ticket', ['id' => $ticket_id]);
        }
}

// Get default values
$default_priority = get_default_priority();
$default_priority_id = $default_priority ? $default_priority['id'] : (!empty($priorities) ? $priorities[0]['id'] : null);

$default_type = null;
foreach ($ticket_types as $tt) {
    if (!empty($tt['is_default'])) {
        $default_type = $tt;
        break;
    }
}
if (!$default_type && !empty($ticket_types)) {
    $default_type = $ticket_types[0];
}
$default_type_slug = $default_type ? $default_type['slug'] : 'general';
$default_organization_id = null;
$default_assignee_id = null;
$is_postback = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($is_postback && !empty($_POST['import_organization_id'])) {
    $posted_org_id = (int) $_POST['import_organization_id'];
    if ($posted_org_id > 0 && in_array($posted_org_id, $allowed_organization_ids, true)) {
        $default_organization_id = $posted_org_id;
    }
} elseif ($is_postback && !empty($_POST['organization_id'])) {
    $posted_org_id = (int) $_POST['organization_id'];
    if ($posted_org_id > 0 && in_array($posted_org_id, $allowed_organization_ids, true)) {
        $default_organization_id = $posted_org_id;
    }
}
if ($is_postback && (is_admin() || is_agent()) && !empty($_POST['assignee_id'])) {
    $posted_assignee_id = (int) $_POST['assignee_id'];
    foreach ($staff_users as $staff_user) {
        if ((int) ($staff_user['id'] ?? 0) === $posted_assignee_id) {
            $default_assignee_id = $posted_assignee_id;
            break;
        }
    }
}

require_once BASE_PATH . '/includes/header.php';
?>

<?php
$page_header_title = t('New ticket');
$page_header_subtitle = '';
$page_header_breadcrumbs = [
    ['label' => t('Tickets'), 'url' => url('tickets')],
    ['label' => t('New ticket')]
];
include BASE_PATH . '/includes/components/page-header.php';
?>

<div class="w-full">
    <?php if ($error): ?>
        <div class="alert alert-error mb-4">
            <?php echo e($error); ?>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="card card-body" id="new-ticket-form" autocomplete="off"
        data-fresh-ticket="<?php echo $is_postback ? '0' : '1'; ?>"
        onsubmit="var b=this.querySelector('[type=submit]');if(b){b.disabled=true;b.style.opacity='0.6';}">
        <?php echo csrf_field(); ?>
        <div class="space-y-4">
            <!-- Title -->
            <div>
                <label for="ticket-title-input" class="block text-sm font-medium mb-1" style="color: var(--text-secondary);"><?php echo e(t('Subject')); ?> *</label>
                <input type="text" name="title" value="<?php echo e($_POST['title'] ?? ''); ?>" class="form-input"
                    required aria-required="true" autofocus id="ticket-title-input">
            </div>

            <!-- Description with Rich Text Editor -->
            <div>
                <label id="description-label" class="block text-sm font-medium mb-1" style="color: var(--text-secondary);"><?php echo e(t('Description')); ?></label>
                <div class="editor-wrapper" role="textbox" aria-labelledby="description-label" aria-multiline="true">
                    <div id="description-editor"></div>
                </div>
                <input type="hidden" name="description" id="description-input" value="<?php echo e($_POST['description'] ?? ''); ?>">
            </div>


            <!-- File Upload + Company row -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);"><?php echo e(t('Attachments')); ?></label>
                    <div id="upload-zone" class="rounded-lg p-2 flex items-center gap-2 cursor-pointer border-2 border-dashed hover:border-blue-300 transition-colors" style="border-color: var(--border-light);">
                        <input type="file" name="attachments[]" id="file-input" multiple class="hidden"
                            accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,.rar">
                        <?php echo get_icon('cloud-upload-alt', 'text-lg flex-shrink-0'); ?>
                        <div class="flex-1 text-left min-w-0">
                            <p class="text-xs" style="color: var(--text-secondary);">
                                <span class="text-blue-500 font-medium"><?php echo e(t('Click')); ?></span>
                                <?php echo e(t('or drag files')); ?>
                            </p>
                        </div>
                    </div>
                    <p class="text-xs mt-1" style="color: var(--text-muted);">
                        <?php echo e(t('Max {size}MB. Types: JPG, PNG, GIF, PDF, DOC, XLS, TXT, ZIP', ['size' => get_max_upload_size_mb()])); ?>
                    </p>
                    <?php if (get_request_upload_limit() > 0): ?>
                    <p class="text-xs mt-0.5" style="color: var(--text-muted);">
                        <?php echo e(t('Total upload per request is limited to {size}.', ['size' => format_file_size(get_request_upload_limit())])); ?>
                    </p>
                    <?php endif; ?>
                    <div id="file-upload-errors" class="mt-2 hidden rounded-lg border px-3 py-2 text-xs"
                        style="border-color: color-mix(in srgb, #ef4444 28%, var(--border-light)); background: color-mix(in srgb, #ef4444 10%, var(--surface-primary)); color: #b91c1c;"
                        aria-live="polite"></div>
                    <!-- File preview -->
                    <div id="file-preview" class="mt-1.5 space-y-1 hidden"></div>
                </div>

                <?php if (!empty($organizations)): ?>
                <div>
                    <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);"><?php echo e(t('Company')); ?></label>
                    <select name="organization_id" class="form-select" autocomplete="off" data-reset-on-fresh-ticket="1">
                        <option value=""><?php echo e(t('-- No organization --')); ?></option>
                        <?php foreach ($organizations as $org): ?>
                            <option value="<?php echo (int) $org['id']; ?>" <?php echo $default_organization_id === (int) $org['id'] ? 'selected' : ''; ?>>
                                <?php echo e($org['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>

            <!-- Status (admin/agent only) — visible immediately -->
            <?php if (is_admin() || is_agent()): ?>
            <div>
                <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);"><?php echo e(t('Status')); ?></label>
                <input type="hidden" name="status_id" id="status_id" value="<?php echo (int) ($statuses[0]['id'] ?? 0); ?>">
                <div class="flex flex-wrap gap-1.5 items-center" id="status-selector">
                    <?php foreach ($statuses as $i => $status): ?>
                        <button type="button" class="option-pill <?php echo $i === 0 ? 'selected' : ''; ?>"
                            data-value="<?php echo (int) $status['id']; ?>" data-group="status"
                            onclick="selectOption(this, 'status_id')"
                            style="--pill-color: <?php echo e($status['color'] ?? '#6b7280'); ?>">
                            <span><?php echo e($status['name']); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Advanced Settings (collapsible) -->
            <details class="group">
                <summary class="flex items-center gap-2 cursor-pointer py-2 text-sm font-medium" style="color: var(--text-secondary);">
                    <svg class="w-4 h-4 transition-transform group-open:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    <?php echo e(t('Advanced')); ?>
                </summary>
                <div class="pt-2 space-y-4">
                    <!-- Priority, Ticket Type, Due Date, Assign To, On Behalf Of -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        <!-- Priority -->
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);"><?php echo e(t('Priority')); ?></label>
                            <input type="hidden" name="priority_id" id="priority_id"
                                value="<?php echo $default_priority_id; ?>">
                            <div class="flex flex-wrap gap-1.5 items-center" id="priority-selector">
                                <?php foreach ($priorities as $priority):
                                    $is_selected = ($default_priority_id == $priority['id']);
                                    ?>
                                    <button type="button" class="option-pill <?php echo $is_selected ? 'selected' : ''; ?>"
                                        data-value="<?php echo $priority['id']; ?>" data-group="priority"
                                        onclick="selectOption(this, 'priority_id')"
                                        style="--pill-color: <?php echo e($priority['color']); ?>">
                                        <span class="pill-icon"><?php echo get_icon($priority['icon'] ?? 'flag', 'w-3.5 h-3.5'); ?></span>
                                        <span><?php echo e($priority['name']); ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Ticket Type -->
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);"><?php echo e(t('Ticket type')); ?></label>
                            <input type="hidden" name="type" id="type" value="<?php echo e($default_type_slug); ?>">
                            <div class="flex flex-wrap gap-1.5 items-center" id="type-selector">
                                <?php foreach ($ticket_types as $tt):
                                    $is_selected = ($default_type_slug === $tt['slug']);
                                    ?>
                                    <button type="button" class="option-pill <?php echo $is_selected ? 'selected' : ''; ?>"
                                        data-value="<?php echo e($tt['slug']); ?>" data-group="type"
                                        onclick="selectOption(this, 'type')"
                                        style="--pill-color: <?php echo e($tt['color']); ?>">
                                        <span class="pill-icon"><?php echo get_icon($tt['icon'], 'w-3.5 h-3.5'); ?></span>
                                        <span><?php echo e($tt['name']); ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Due Date -->
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);"><?php echo e(t('Due date')); ?></label>
                            <input type="datetime-local" name="due_date" value="<?php echo e($_POST['due_date'] ?? ''); ?>"
                                class="form-input">
                        </div>

                        <!-- Assign To (admin/agent only) -->
                        <?php if (is_admin() || is_agent()): ?>
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);"><?php echo e(t('Assign to')); ?></label>
                            <select name="assignee_id" class="form-select" autocomplete="off" data-reset-on-fresh-ticket="1">
                                <option value=""><?php echo e(t('-- Unassigned --')); ?></option>
                                <?php foreach ($staff_users as $su): ?>
                                    <option value="<?php echo (int) $su['id']; ?>" <?php echo ($default_assignee_id !== null && (int) ($su['id'] ?? 0) === $default_assignee_id) ? 'selected' : ''; ?>>
                                        <?php echo e($su['first_name'] . ' ' . $su['last_name']); ?>
                                        <?php if ((int) $su['id'] === $user['id']): ?>(<?php echo e(t('me')); ?>)<?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- On Behalf Of (admin/agent only) -->
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);"><?php echo e(t('On behalf of')); ?></label>
                            <select name="on_behalf_of" class="form-select">
                                <option value=""><?php echo e(t('-- Myself --')); ?></option>
                                <?php foreach ($all_users_list as $au): ?>
                                    <?php if ((int) $au['id'] === $user['id']) continue; ?>
                                    <option value="<?php echo (int) $au['id']; ?>">
                                        <?php echo e($au['first_name'] . ' ' . $au['last_name']); ?>
                                        (<?php echo e($au['email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <!-- Tags (next to On Behalf Of) -->
                        <?php if ($tags_supported): ?>
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color: var(--text-secondary);"><?php echo e(t('Tags')); ?></label>
                            <input type="hidden" name="tags" id="nt-tags-value" value="<?php echo e($_POST['tags'] ?? ''); ?>">
                            <div class="chip-select" id="cs-tags">
                                <div class="chip-select__wrap" id="cs-tags-wrap">
                                    <div class="chip-select__chips" id="cs-tags-chips"></div>
                                    <input type="text" class="chip-select__input" id="cs-tags-input"
                                           placeholder="<?php echo e(t('Type to add tags...')); ?>" autocomplete="off">
                                </div>
                                <div class="chip-select__dropdown hidden" id="cs-tags-dropdown"></div>
                                <div id="cs-tags-hidden"></div>
                            </div>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
            </details>
        </div>

        <?php if (is_agent() && function_exists('ticket_time_table_exists') && ticket_time_table_exists()): ?>
        <!-- Manual Time Entry (hidden by default) -->
        <div id="nt-manual-entry-row" class="hidden mt-3 pt-3 border-t" style="border-color: var(--border-light);">
            <div class="space-y-3">
                <div>
                    <label class="form-label-sm mb-1"><?php echo e(t('Time (min)')); ?></label>
                    <input
                        type="number"
                        name="manual_duration_minutes"
                        id="nt-manual-duration-minutes"
                        min="1"
                        max="1440"
                        step="1"
                        placeholder="15"
                        value="<?php echo e($_POST['manual_duration_minutes'] ?? ''); ?>"
                        class="form-input text-sm h-9 max-w-xs">
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button type="button" class="nt-manual-duration-chip btn btn-ghost px-2 py-1 text-xs" data-minutes="5">+5</button>
                        <button type="button" class="nt-manual-duration-chip btn btn-ghost px-2 py-1 text-xs" data-minutes="10">+10</button>
                        <button type="button" class="nt-manual-duration-chip btn btn-ghost px-2 py-1 text-xs" data-minutes="15">+15</button>
                        <button type="button" class="nt-manual-duration-chip btn btn-ghost px-2 py-1 text-xs" data-minutes="30">+30</button>
                        <button type="button" class="nt-manual-duration-chip btn btn-ghost px-2 py-1 text-xs" data-minutes="60">+60</button>
                    </div>
                    <p class="mt-2 text-xs" style="color: var(--text-muted);">
                        <?php echo e(t('Leave Start and End empty to log quick minutes ending now. If both are filled, the exact range is used instead.')); ?>
                    </p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                    <div>
                        <label class="form-label-sm mb-1"><?php echo e(t('Date')); ?></label>
                        <input type="date" name="manual_date" value="<?php echo e($_POST['manual_date'] ?? date('Y-m-d')); ?>" class="form-input text-sm h-9">
                    </div>
                    <div>
                        <label class="form-label-sm mb-1"><?php echo e(t('Start')); ?></label>
                        <input type="time" name="manual_start_time" value="<?php echo e($_POST['manual_start_time'] ?? ''); ?>" class="form-input text-sm h-9">
                    </div>
                    <div>
                        <label class="form-label-sm mb-1"><?php echo e(t('End')); ?></label>
                        <input type="time" name="manual_end_time" value="<?php echo e($_POST['manual_end_time'] ?? ''); ?>" class="form-input text-sm h-9">
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Buttons - aligned right with consistent height -->
        <div class="mt-4 pt-3 border-t flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <?php if (is_agent() && function_exists('ticket_time_table_exists') && ticket_time_table_exists()): ?>
                    <?php $auto_timer = isset($_GET['auto_timer']) && $_GET['auto_timer'] === '1'; ?>
                    <input type="hidden" name="timer_elapsed_seconds" id="timer_elapsed_seconds" value="0">
                    <div id="new-ticket-timer" class="flex items-center gap-2" data-auto-start="<?php echo $auto_timer ? '1' : '0'; ?>">
                        <button type="button" id="nt-timer-btn"
                            class="btn btn-success px-3 py-1.5 text-sm inline-flex items-center gap-1.5 transition-colors"
                            data-state="stopped"
                            title="<?php echo e(t('Start timer')); ?>">
                            <span class="nt-timer-icon"><?php echo get_icon('play', 'w-4 h-4'); ?></span>
                            <span class="nt-timer-text"><?php echo e(t('Start timer')); ?></span>
                        </button>
                        <button type="button" id="nt-timer-discard"
                            class="hidden btn btn-ghost px-2 py-1.5 hover:text-red-500 transition-colors" style="color: var(--text-muted);"
                            title="<?php echo e(t('Discard timer')); ?>">
                            <?php echo get_icon('trash', 'w-4 h-4'); ?>
                        </button>
                    </div>
                    <!-- Manual time entry toggle -->
                    <button type="button" id="nt-manual-toggle" class="btn btn-ghost px-2 py-1.5"
                        style="color: var(--text-muted);" title="<?php echo e(t('Manual entry')); ?>">
                        <?php echo get_icon('pen', 'w-4 h-4'); ?>
                    </button>

                <?php endif; ?>
            </div>
            <div class="flex items-center gap-3">
                <a href="<?php echo url(function_exists('foxdesk_authenticated_home_page') ? foxdesk_authenticated_home_page() : 'dashboard'); ?>" class="btn btn-ghost flex items-center">
                    <?php echo e(t('Cancel')); ?>
                </a>
                <button type="submit" class="btn btn-primary flex items-center">
                    <?php echo e(t('Save')); ?>
                </button>
            </div>
        </div>
    </form>

</div>


<script src="assets/js/upload-preview.js?v=<?php echo APP_VERSION; ?>"></script>
<script>
    const ICONS = {
        'times': '<line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>',
        'file': '<path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline>',
        'file-image': '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline>',
        'file-pdf': '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline>',
        'file-word': '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline>',
        'file-excel': '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline>',
        'file-archive': '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline>'
    };

    function getIcon(name, classes = '') {
        const path = ICONS[name] || ICONS['file'];
        return `<svg xmlns="http://www.w3.org/2000/svg" class="${classes}" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${path}</svg>`;
    }

    // Selection handler for option pills
    function selectOption(element, inputId) {
        const group = element.dataset.group;
        const value = element.dataset.value;

        document.querySelectorAll(`[data-group="${group}"]`).forEach(el => {
            el.classList.remove('selected');
        });
        element.classList.add('selected');
        document.getElementById(inputId).value = value;
    }

    const initTicketUploadZones = function () {
        window.FoxDeskUploadPreview && window.FoxDeskUploadPreview.init({
            zoneId: 'upload-zone',
            inputId: 'file-input',
            previewId: 'file-preview',
            errorsId: 'file-upload-errors',
            removeLabel: <?php echo json_encode(t('Remove')); ?>,
            sizeDecimals: 1,
            limit: {
                single: <?php echo json_encode((int) get_max_upload_size()); ?>,
                total: <?php echo json_encode((int) get_request_upload_limit()); ?>,
                singleTemplate: <?php echo json_encode(t('File "{name}" exceeds the maximum allowed size of {size}.')); ?>,
                totalTemplate: <?php echo json_encode(t('Selected attachments exceed the server request limit of {size}.')); ?>
            }
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTicketUploadZones);
    } else {
        initTicketUploadZones();
    }

    const newTicketForm = document.getElementById('new-ticket-form');
    if (newTicketForm) {
        const resetFreshTicketSelects = function() {
            if (newTicketForm.dataset.freshTicket !== '1') return;
            newTicketForm.querySelectorAll('[data-reset-on-fresh-ticket="1"]').forEach(function(select) {
                select.value = '';
                select.querySelectorAll('option').forEach(function(option) {
                    option.selected = option.value === '';
                });
            });
        };
        resetFreshTicketSelects();
        window.addEventListener('pageshow', resetFreshTicketSelects);

        newTicketForm.addEventListener('submit', function(event) {
            const hadRenderedUploadErrors = fileUploadErrors && !fileUploadErrors.classList.contains('hidden');
            const validation = enforceUploadLimits();
            const shouldKeepRenderedUploadErrors = hadRenderedUploadErrors && fileInput && fileInput.files.length === 0 && validation.messages.length === 0;
            if (!shouldKeepRenderedUploadErrors) {
                renderUploadErrors(validation.messages);
            }
            const hasBlockingUploadError = hadRenderedUploadErrors || (fileUploadErrors && !fileUploadErrors.classList.contains('hidden'));
            if ((validation.hadErrors && fileInput && fileInput.files.length === 0) || (hasBlockingUploadError && fileInput && fileInput.files.length === 0)) {
                const submitBtn = this.querySelector('[type=submit]');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = '';
                }
                if (fileUploadErrors) {
                    fileUploadErrors.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
                event.preventDefault();
                return;
            }
            localStorage.removeItem('foxdesk_draft_timer');
        });
    }
</script>

<?php if (is_agent() && function_exists('ticket_time_table_exists') && ticket_time_table_exists()): ?>
<script>
(function() {
    var STORAGE_KEY = 'foxdesk_draft_timer';
    var wrapper = document.getElementById('new-ticket-timer');
    var btn = document.getElementById('nt-timer-btn');
    var btnIcon = btn ? btn.querySelector('.nt-timer-icon') : null;
    var btnText = btn ? btn.querySelector('.nt-timer-text') : null;
    var discardBtn = document.getElementById('nt-timer-discard');
    var hiddenInput = document.getElementById('timer_elapsed_seconds');
    if (!wrapper || !btn || !btnIcon || !btnText || !discardBtn || !hiddenInput) return;

    // Server-rendered icon SVGs (safe — PHP-escaped, not user input) and translated strings
    var iconPlay = '<?php echo get_icon('play', 'w-4 h-4'); ?>';
    var iconPause = '<?php echo get_icon('pause', 'w-4 h-4'); ?>';
    var STR_START = '<?php echo e(t('Start timer')); ?>';
    var STR_PAUSE = '<?php echo e(t('Pause timer')); ?>';
    var STR_RESUME = '<?php echo e(t('Resume timer')); ?>';
    var STR_PAUSED = '<?php echo e(t('Paused')); ?>';
    var STR_DISCARD_CONFIRM = '<?php echo e(t('Discard this timer? The tracked time will be lost.')); ?>';

    var timerStart = null;
    var timerInterval = null;
    var pausedTotal = 0;
    var pausedAt = null;
    var state = 'stopped';

    // --- localStorage persistence ---
    function saveTimer() {
        if (state === 'stopped') {
            localStorage.removeItem(STORAGE_KEY);
            return;
        }
        localStorage.setItem(STORAGE_KEY, JSON.stringify({
            startedAt: timerStart,
            pausedTotal: pausedTotal,
            pausedAt: pausedAt,
            state: state
        }));
    }

    function restoreTimer() {
        var raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return false;
        try { var d = JSON.parse(raw); } catch(e) { return false; }
        if (!d.startedAt || !d.state) return false;

        timerStart = d.startedAt;
        if (d.state === 'paused' && d.pausedAt) {
            // Hibernation time counts as paused
            pausedTotal = d.pausedTotal + (Date.now() - d.pausedAt);
            pausedAt = Date.now();
        } else {
            pausedTotal = d.pausedTotal || 0;
            pausedAt = null;
        }
        setState(d.state);
        return true;
    }

    // --- Core timer logic ---
    function formatTime(seconds) {
        if (seconds < 0) seconds = 0;
        var h = Math.floor(seconds / 3600);
        var m = Math.floor((seconds % 3600) / 60);
        var s = seconds % 60;
        if (h > 0) return h + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        return m + ':' + String(s).padStart(2, '0');
    }

    function getElapsed() {
        if (!timerStart) return 0;
        return Math.floor((Date.now() - timerStart - pausedTotal) / 1000);
    }

    function tick() {
        if (state !== 'running') return;
        var elapsed = getElapsed();
        btnText.textContent = formatTime(elapsed);
        hiddenInput.value = elapsed;
    }

    // setIcon: safely swap SVG icon content (source is PHP get_icon(), not user input)
    function setIcon(el, svgHtml) { el.innerHTML = svgHtml; } // eslint-disable-line no-param-reassign

    function setState(newState) {
        state = newState;
        btn.dataset.state = newState;

        if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }

        if (newState === 'running') {
            btn.className = 'btn btn-warning px-3 py-1.5 text-sm inline-flex items-center gap-1.5 transition-colors';
            btn.title = STR_PAUSE;
            setIcon(btnIcon, iconPause);
            btnText.textContent = formatTime(getElapsed());
            discardBtn.classList.remove('hidden');
            timerInterval = setInterval(tick, 1000);

        } else if (newState === 'paused') {
            var elapsed = getElapsed();
            btn.className = 'btn btn-success px-3 py-1.5 text-sm inline-flex items-center gap-1.5 transition-colors';
            btn.title = STR_RESUME;
            setIcon(btnIcon, iconPlay);
            btnText.textContent = formatTime(elapsed);
            var pausedLabel = document.createElement('span');
            pausedLabel.className = 'text-xs uppercase ml-1';
            pausedLabel.textContent = STR_PAUSED;
            btnText.appendChild(pausedLabel);
            discardBtn.classList.remove('hidden');
            hiddenInput.value = elapsed;

        } else { // stopped
            btn.className = 'btn btn-success px-3 py-1.5 text-sm inline-flex items-center gap-1.5 transition-colors';
            btn.title = STR_START;
            setIcon(btnIcon, iconPlay);
            btnText.textContent = STR_START;
            discardBtn.classList.add('hidden');
            timerStart = null;
            pausedTotal = 0;
            pausedAt = null;
            hiddenInput.value = '0';
        }
        saveTimer();
    }

    btn.addEventListener('click', function() {
        if (state === 'stopped') {
            timerStart = Date.now();
            pausedTotal = 0;
            pausedAt = null;
            setState('running');
        } else if (state === 'running') {
            pausedAt = Date.now();
            setState('paused');
        } else if (state === 'paused') {
            pausedTotal += Date.now() - pausedAt;
            pausedAt = null;
            setState('running');
        }
    });

    discardBtn.addEventListener('click', function() {
        if (!confirm(STR_DISCARD_CONFIRM)) return;
        setState('stopped');
    });

    // Restore from localStorage, or auto-start if ?auto_timer=1
    if (!restoreTimer() && wrapper.dataset.autoStart === '1') {
        timerStart = Date.now();
        pausedTotal = 0;
        pausedAt = null;
        setState('running');
    }
})();

// Manual entry toggle
(function() {
    var toggle = document.getElementById('nt-manual-toggle');
    var row = document.getElementById('nt-manual-entry-row');
    var durationInput = document.getElementById('nt-manual-duration-minutes');
    var durationButtons = document.querySelectorAll('.nt-manual-duration-chip');
    var dateInput = document.querySelector('input[name="manual_date"]');
    var startInput = document.querySelector('input[name="manual_start_time"]');
    var endInput = document.querySelector('input[name="manual_end_time"]');
    if (!toggle || !row) return;

    function setManualVisible(visible) {
        row.classList.toggle('hidden', !visible);
        toggle.style.color = visible ? 'var(--accent-primary)' : 'var(--text-muted)';
    }

    function activateQuickMinutes(minutes) {
        if (!durationInput) return;
        durationInput.value = minutes;
        if (startInput) startInput.value = '';
        if (endInput) endInput.value = '';
        setManualVisible(true);
        durationInput.focus();
    }

    if (
        (durationInput && durationInput.value !== '') ||
        (startInput && startInput.value !== '') ||
        (endInput && endInput.value !== '')
    ) {
        setManualVisible(true);
    }

    toggle.addEventListener('click', function() {
        setManualVisible(row.classList.contains('hidden'));
    });

    if (durationInput) {
        durationInput.addEventListener('input', function() {
            if (this.value !== '') {
                if (startInput) startInput.value = '';
                if (endInput) endInput.value = '';
                if (dateInput && dateInput.value === '') {
                    dateInput.value = '<?php echo e(date('Y-m-d')); ?>';
                }
                setManualVisible(true);
            }
        });
    }

    durationButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            activateQuickMinutes(this.dataset.minutes);
        });
    });

    [startInput, endInput].forEach(function(input) {
        if (!input) return;
        input.addEventListener('input', function() {
            if (durationInput && this.value !== '') {
                durationInput.value = '';
            }
            setManualVisible(true);
        });
    });
})();
</script>
<?php endif; ?>

<?php if ($tags_supported): ?>
<script src="assets/js/chip-select.js"></script>
<script>
(function () {
    var hiddenInput = document.getElementById('nt-tags-value');
    if (!hiddenInput) return;

    // Fetch existing tag suggestions
    fetch('index.php?page=api&action=get-tags')
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) return;
            initTagChips(data.tags || []);
        })
        .catch(function () { initTagChips([]); });

    function initTagChips(tagItems) {
        // Pre-selected tags from POST (on validation failure)
        var preSelected = [];
        var existing = hiddenInput.value.trim();
        if (existing) {
            preSelected = existing.split(',').map(function (t) { return t.trim(); }).filter(Boolean);
        }

        var csTags = new ChipSelect({
            wrapId:     'cs-tags-wrap',
            chipsId:    'cs-tags-chips',
            inputId:    'cs-tags-input',
            dropdownId: 'cs-tags-dropdown',
            hiddenId:   'cs-tags-hidden',
            items:      tagItems,
            selected:   preSelected,
            name:       'tag_chips[]',
            allowCreate: true,
            noMatchText: <?php echo json_encode(t('No matches')); ?>
        });

        // Sync chip values to hidden input before form submit
        var form = document.getElementById('new-ticket-form');
        if (form) {
            form.addEventListener('submit', function () {
                hiddenInput.value = csTags.getSelectedValues().join(', ');
            });
        }
    }
})();
</script>
<?php endif; ?>

<!-- Quill Editor -->
<!-- Quill 1.3.7 (stable version) -->
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script src="assets/js/quill-image-upload.js?v=<?php echo APP_VERSION; ?>"></script>
<script>
    // Initialize Quill Editor
    (function() {
        try {
            const editorEl = document.getElementById('description-editor');
            if (!editorEl) {
                console.error('Quill: editor element #description-editor not found');
                return;
            }

            if (typeof Quill === 'undefined') {
                console.error('Quill: library not loaded - check if CDN is accessible');
                return;
            }

            window.descriptionEditor = new Quill('#description-editor', {
                theme: 'snow',
                placeholder: '<?php echo e(t('Describe your request...')); ?>',
                modules: {
                    toolbar: [
                        [{ 'header': [1, 2, 3, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        ['link', 'image'],
                        ['clean']
                    ]
                }
            });

            // Enable image paste/drop upload
            if (window.initQuillImageUpload) {
                initQuillImageUpload(window.descriptionEditor, {
                    uploadUrl: 'index.php?page=api&action=upload',
                    csrfToken: window.csrfToken || ''
                });
            }

            // Load existing content if any
            const existingContent = document.getElementById('description-input').value;
            if (existingContent) {
                window.descriptionEditor.clipboard.dangerouslyPasteHTML(existingContent);
            }

            const descriptionInput = document.getElementById('description-input');
            const ticketForm = document.getElementById('new-ticket-form');
            if (!descriptionInput) {
                console.error('Quill: hidden input #description-input not found');
                return;
            }
            const syncDescriptionInput = function() {
                const html = window.descriptionEditor.root.innerHTML;
                if (html === '<p><br></p>' || html === '<p></p>') {
                    descriptionInput.value = '';
                } else {
                    descriptionInput.value = html;
                }
            };

            // Keep hidden input in sync continuously and also right before submit.
            window.descriptionEditor.on('text-change', syncDescriptionInput);
            if (ticketForm) {
                ticketForm.addEventListener('submit', syncDescriptionInput);
            }
            syncDescriptionInput();

        } catch (e) {
            console.error('Quill initialization error:', e);
        }
    })();
</script>

<!-- Autosave for new ticket form -->
<script src="assets/js/autosave.js"></script>
<script>
(function() {
    if (typeof FoxDeskAutosave === 'undefined') return;

    var draft = FoxDeskAutosave.create({
        key: 'foxdesk_draft_new_ticket',
        formSelector: '#new-ticket-form',
        quillEditors: {description: window.descriptionEditor},
        fields: [
            {name: 'title', selector: '#ticket-title-input', type: 'input'},
            {name: 'description', type: 'quill', editorKey: 'description', selector: '#description-input'},
            {name: 'priority_id', selector: '#priority_id', type: 'hidden'},
            {name: 'type', selector: '#type', type: 'hidden'}
        ],
        pillRestore: function(fieldName, value) {
            // Re-select pill UI for priority and type
            if (fieldName === 'priority_id') {
                var group = 'priority';
                document.querySelectorAll('[data-group="' + group + '"]').forEach(function(el) {
                    el.classList.remove('selected');
                    if (el.dataset.value === value) el.classList.add('selected');
                });
            } else if (fieldName === 'type') {
                var group = 'type';
                document.querySelectorAll('[data-group="' + group + '"]').forEach(function(el) {
                    el.classList.remove('selected');
                    if (el.dataset.value === value) el.classList.add('selected');
                });
            }
        },
        onRestore: function(relTime) {
            if (window.showAppToast) window.showAppToast('<?php echo e(t('Draft restored')); ?> (' + relTime + ')', 'info');
        }
    });
    draft.init();

    // Suppress beforeunload on cancel link
    var cancelLink = document.querySelector('a[href*="dashboard"]');
    if (cancelLink) {
        cancelLink.addEventListener('click', function() {
            draft.suppressBeforeUnload();
        });
    }
})();
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; 
