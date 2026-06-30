<?php
defined('BASE_PATH') || exit;
$ai_agent_form_action = isset($ai_agent_form_action) ? (string) $ai_agent_form_action : '';
$ai_agent_form_action_attr = $ai_agent_form_action !== '' ? ' action="' . e($ai_agent_form_action) . '"' : '';
$ai_agent_hide_create_form = !empty($ai_agent_hide_create_form);
?>
<!-- ============================================= -->
        <!-- AI AGENTS TAB -->
        <!-- ============================================= -->

        <?php if ($new_ai_token): ?>
                <div class="mb-3 p-4 bg-green-50 border border-green-200 fd-rounded-card">
                    <p class="text-sm font-medium text-green-800 mb-1">
                        <?php echo e(t('Copy this agent key now. It will not be shown again.')); ?>
                    </p>
                    <code class="block p-2 border fd-rounded-control text-sm font-mono break-all select-all bg-theme-app"><?php echo e($new_ai_token); ?></code>
                    <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-2 text-xs text-green-900">
                        <div class="fd-rounded-control bg-white/80 border border-green-200 p-2">
                            <div class="font-semibold mb-1"><?php echo e(t('Give it only to')); ?></div>
                            <p><?php echo e(t('The assistant or automation that should work as this agent.')); ?></p>
                        </div>
                        <div class="fd-rounded-control bg-white/80 border border-green-200 p-2">
                            <div class="font-semibold mb-1"><?php echo e(t('Store it as')); ?></div>
                            <p><?php echo e(t('FOXDESK_API_TOKEN in a private environment file or secret manager.')); ?></p>
                        </div>
                        <div class="fd-rounded-control bg-white/80 border border-green-200 p-2">
                            <div class="font-semibold mb-1"><?php echo e(t('Keep it safe')); ?></div>
                            <p><?php echo e(t('Never paste it into chat, screenshots, tickets, or shared documents.')); ?></p>
                        </div>
                    </div>
                    <?php if ($new_ai_agent_id): ?>
                            <a href="<?php echo url('admin', ['section' => 'agent-connect', 'id' => $new_ai_agent_id]); ?>"
                                class="inline-flex items-center gap-1 mt-3 text-sm font-medium text-green-800 hover:text-green-950">
                                <?php echo get_icon('link', 'w-4 h-4'); ?>
                                <?php echo e(t('Open setup guide')); ?>
                            </a>
                    <?php endif; ?>
                </div>
        <?php endif; ?>

        <div class="space-y-3">
            <?php if (!$ai_agent_hide_create_form): ?>
            <div class="card card-body" data-ai-agent-create>
                <div class="fd-section-header">
                    <div class="fd-section-main">
                        <h3 class="fd-section-title"><?php echo e(t('Create AI agent access')); ?></h3>
                        <p class="text-sm text-theme-muted">
                            <?php echo e(t('Use this when the assistant should appear as its own worker with its own rate, access, and audit trail.')); ?>
                        </p>
                    </div>
                </div>
                <form method="post" id="aiAddAgentForm" class="space-y-4"<?php echo $ai_agent_form_action_attr; ?>>
                    <?php echo csrf_field(); ?>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Agent name')); ?> *</label>
                            <input type="text" name="agent_name" required class="form-input" placeholder="<?php echo e(t('e.g. Codex')); ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Model')); ?></label>
                            <input type="text" name="ai_model" class="form-input"
                                placeholder="<?php echo e(t('Optional, for your records')); ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Rate/h')); ?></label>
                            <input type="number" name="cost_rate" step="0.01" min="0" class="form-input" placeholder="0.00">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)] gap-3">
                        <div class="fd-rounded-card border border-theme-light p-3">
                            <h4 class="text-sm font-semibold mb-2 text-theme-primary">
                                <?php echo e(t('Ticket access')); ?>
                            </h4>
                            <div class="space-y-2">
                                <label class="flex items-center text-sm">
                                    <input type="radio" name="ticket_scope" value="assigned" class="mr-2" checked>
                                    <?php echo e(t('Assigned tickets only')); ?>
                                </label>
                                <label class="flex items-center text-sm">
                                    <input type="radio" name="ticket_scope" value="organization" class="mr-2">
                                    <?php echo e(t('Tickets from selected organizations')); ?>
                                </label>
                                <label class="flex items-center text-sm">
                                    <input type="radio" name="ticket_scope" value="all" class="mr-2">
                                    <?php echo e(t('All tickets')); ?>
                                </label>
                            </div>
                            <?php if (!empty($organizations)): ?>
                                    <div id="ai_add_org_select" class="mt-2 hidden">
                                        <label class="block text-xs mb-1 text-theme-muted">
                                            <?php echo e(t('Select clients')); ?>
                                        </label>
                                        <select name="scope_organization_ids[]" multiple size="4" class="form-select text-sm">
                                            <?php foreach ($organizations as $org): ?>
                                                    <option value="<?php echo $org['id']; ?>"><?php echo e($org['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                            <?php endif; ?>
                            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <label class="flex items-center text-sm">
                                    <input type="checkbox" name="can_view_time" class="mr-2" checked>
                                    <?php echo e(t('Can view time entries')); ?>
                                </label>
                                <label class="flex items-center text-sm">
                                    <input type="checkbox" name="can_view_timeline" class="mr-2" checked>
                                    <?php echo e(t('Can view activity timeline')); ?>
                                </label>
                            </div>
                        </div>

                        <?php if (!empty($ai_agent_token_scope_groups)): ?>
                                <div class="fd-rounded-card border border-theme-light p-3">
                                    <h4 class="text-sm font-semibold mb-1 text-theme-primary">
                                        <?php echo e(t('Allowed actions')); ?>
                                    </h4>
                                    <p class="text-xs mb-2 text-theme-muted">
                                        <?php echo e(t('Start with read access, then add only the actions this agent really needs.')); ?>
                                    </p>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                        <?php foreach ($ai_agent_token_scope_groups as $group_key => $group): ?>
                                                <label class="flex items-start gap-2 text-sm fd-rounded-control border border-theme-light p-2 cursor-pointer">
                                                    <input type="checkbox" name="api_token_scope_groups[]"
                                                        value="<?php echo e($group_key); ?>" class="mt-0.5 fd-rounded-control"
                                                        <?php echo in_array($group_key, $ai_agent_token_default_scope_groups, true) ? 'checked' : ''; ?>>
                                                    <span>
                                                        <span class="font-medium text-theme-primary">
                                                            <?php echo e(t($group['label'])); ?>
                                                        </span>
                                                        <span class="block text-xs text-theme-muted">
                                                            <?php echo e(t($group['description'])); ?>
                                                        </span>
                                                    </span>
                                                </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                        <?php endif; ?>
                    </div>

                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs text-theme-muted">
                            <?php echo e(t('After creating the agent, copy the key once and store it as a secret.')); ?>
                        </p>
                        <button type="submit" name="add_ai_agent" class="btn btn-primary">
                            <?php echo e(t('Create agent and key')); ?>
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- AI Agents List -->
            <div class="admin-list-card">
                <div class="card-header">
                    <h3 class="font-semibold text-theme-primary"><?php echo e(t('AI agents')); ?>
                        (<?php echo count($ai_agents); ?>)</h3>
                </div>
                <div class="admin-responsive-table-wrap">
                    <table class="admin-responsive-table admin-ai-agents-table tickets-table">
                            <thead class="bg-theme-secondary">
                                <tr class="border-b">
                                    <th class="px-4 py-2 text-left th-label whitespace-nowrap"><?php echo e(t('Name')); ?></th>
                                    <th class="px-4 py-2 text-left th-label whitespace-nowrap w-28"><?php echo e(t('Model')); ?></th>
                                    <th class="px-4 py-2 text-left th-label whitespace-nowrap w-20"><?php echo e(t('Rate/h')); ?></th>
                                    <th class="px-4 py-2 text-left th-label whitespace-nowrap w-32"><?php echo e(t('API token')); ?></th>
                                    <th class="px-4 py-2 text-left th-label whitespace-nowrap w-44"><?php echo e(t('Access')); ?></th>
                                    <th class="px-4 py-2 text-left th-label whitespace-nowrap w-20"><?php echo e(t('Status')); ?></th>
                                    <th class="px-4 py-2 text-right th-label whitespace-nowrap w-28"><?php echo e(t('Actions')); ?></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <?php if (empty($ai_agents)): ?>
                                        <tr>
                                            <td colspan="7" class="px-4 py-6 text-center text-sm text-theme-muted">
                                                <?php echo e(t('No AI agents yet.')); ?>
                                            </td>
                                        </tr>
                                <?php endif; ?>
                                <?php foreach ($ai_agents as $agent):
                                    // Find tokens for this agent
                                    $agent_tks = array_filter($ai_agent_tokens, fn($tk) => (int) $tk['user_id'] === (int) $agent['id']);
                                    $active_token = null;
                                    foreach ($agent_tks as $tk) {
                                        if (!empty($tk['is_active'])) {
                                            $active_token = $tk;
                                            break;
                                        }
                                    }
                                    $agent_permissions = [];
                                    if (!empty($agent['permissions'])) {
                                        $decoded_permissions = json_decode((string) $agent['permissions'], true);
                                        if (is_array($decoded_permissions)) {
                                            $agent_permissions = $decoded_permissions;
                                        }
                                    }
                                    $agent_scope = $agent_permissions['ticket_scope'] ?? 'assigned';
                                    $agent_org_ids = normalize_organization_ids($agent_permissions['organization_ids'] ?? []);
                                    if (empty($agent_org_ids) && !empty($agent['organization_id'])) {
                                        $agent_org_ids = [(int) $agent['organization_id']];
                                    }
                                    $agent_org_names = [];
                                    foreach ($agent_org_ids as $org_id) {
                                        if (!empty($organization_names_by_id[$org_id])) {
                                            $agent_org_names[] = $organization_names_by_id[$org_id];
                                        }
                                    }
                                    $scope_labels = [
                                        'all' => t('All tickets'),
                                        'assigned' => t('Assigned tickets only'),
                                        'organization' => t('Tickets from selected organizations'),
                                        'own' => t('Own tickets only'),
                                    ];
                                    $access_label = $scope_labels[$agent_scope] ?? $agent_scope;
                                    ?>
                                        <tr class="tr-hover <?php echo $agent['is_active'] ? '' : 'opacity-50'; ?>">
                                            <td class="px-4 py-2.5 admin-responsive-primary" data-label="<?php echo e(t('Name')); ?>">
                                                <div class="flex items-center space-x-2">
                                                    <div
                                                        class="w-7 h-7 bg-purple-100 fd-rounded-pill flex items-center justify-center flex-shrink-0">
                                                        <?php echo get_icon('bot', 'w-4 h-4 text-purple-600'); ?>
                                                    </div>
                                                    <span class="admin-cell-title text-sm"><?php echo e($agent['first_name']); ?></span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-2.5" data-label="<?php echo e(t('Model')); ?>">
                                                <?php if (!empty($agent['ai_model'])): ?>
                                                        <span
                                                            class="text-xs bg-purple-100 text-purple-700 px-1.5 py-0.5 fd-rounded-control"><?php echo e($agent['ai_model']); ?></span>
                                                <?php else: ?>
<span class="text-theme-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-2.5 text-sm text-theme-secondary" data-label="<?php echo e(t('Rate/h')); ?>">
                                                <?php echo (float) $agent['cost_rate'] > 0 ? e(format_money($agent['cost_rate'])) . '/h' : '<span class="text-theme-muted">-</span>'; ?>
                                            </td>
                                            <td class="px-4 py-2.5 text-xs" data-label="<?php echo e(t('API token')); ?>">
                                                <?php if ($active_token): ?>
                                                        <code
                                                            class="text-theme-muted"><?php echo e($active_token['token_prefix'] ?? '???'); ?>...</code>
                                                <?php else: ?>
                                                        <span class="text-orange-500"><?php echo e(t('No token')); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-2.5 text-xs text-theme-secondary" data-label="<?php echo e(t('Access')); ?>">
                                                <div class="font-medium text-theme-primary"><?php echo e($access_label); ?></div>
                                                <?php if ($agent_scope === 'organization'): ?>
                                                        <div class="text-theme-muted">
                                                            <?php echo !empty($agent_org_names) ? e(implode(', ', $agent_org_names)) : e(t('No clients selected')); ?>
                                                        </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-2.5" data-label="<?php echo e(t('Status')); ?>">
                                                <?php if ($agent['is_active']): ?>
                                                        <span
                                                            class="text-xs px-2 py-0.5 fd-rounded-control bg-green-100 text-green-600"><?php echo e(t('Active')); ?></span>
                                                <?php else: ?>
                                                        <span class="text-xs px-2 py-0.5 fd-rounded-control bg-theme-tertiary text-theme-secondary"><?php echo e(t('Inactive')); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-2.5 text-right admin-responsive-actions" data-label="<?php echo e(t('Actions')); ?>">
                                                <div class="flex items-center justify-end gap-1 relative z-10">
                                                    <a href="<?php echo url('admin', ['section' => 'agent-connect', 'id' => $agent['id']]); ?>"
                                                        class="p-1.5 fd-rounded-control hover:bg-purple-50 text-purple-500 hover:text-purple-700 transition-colors"
                                                        title="<?php echo e(t('Connect')); ?>">
                                                        <?php echo get_icon('link', 'w-4 h-4'); ?>
                                                    </a>
                                                    <?php if (!$active_token): ?>
                                                            <button type="button"
                                                                onclick='editAiAgent(<?php echo json_encode($agent, JSON_HEX_APOS | JSON_HEX_QUOT); ?>, null)'
                                                                class="p-1.5 fd-rounded-control hover:bg-green-50 text-green-500 hover:text-green-700 transition-colors"
                                                                title="<?php echo e(t('Create token with access')); ?>">
                                                                <?php echo get_icon('key', 'w-4 h-4'); ?>
                                                            </button>
                                                    <?php endif; ?>
                                                    <button type="button"
                                                        onclick='editAiAgent(<?php echo json_encode($agent, JSON_HEX_APOS | JSON_HEX_QUOT); ?>, <?php echo json_encode($active_token, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'
                                                        class="p-1.5 fd-rounded-control hover:bg-blue-50 dark:bg-blue-900/20 text-blue-500 hover:text-blue-700 transition-colors"
                                                        title="<?php echo e(t('Edit')); ?>">
                                                        <?php echo get_icon('edit', 'w-4 h-4'); ?>
                                                    </button>
                                                    <form method="post" class="inline"<?php echo $ai_agent_form_action_attr; ?>
                                                        onsubmit="return confirmDeleteAgent('<?php echo addslashes(e($agent['first_name'])); ?>')">
                                                        <?php echo csrf_field(); ?>
                                                        <input type="hidden" name="id" value="<?php echo $agent['id']; ?>">
                                                        <button type="submit" name="delete_ai_agent"
                                                            class="p-1.5 fd-rounded-control hover:bg-red-50 text-red-400 hover:text-red-600 transition-colors"
                                                            title="<?php echo e(t('Delete agent')); ?>">
                                                            <?php echo get_icon('trash', 'w-4 h-4'); ?>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

        <!-- Edit AI Agent Modal -->
        <div id="editAiAgentModal"
            class="fixed inset-0 bg-black bg-opacity-50 hidden items-start sm:items-center justify-center z-50 overflow-y-auto p-2 sm:p-3"
            role="dialog" aria-modal="true" aria-labelledby="edit-ai-agent-title">
            <div class="fd-rounded-card shadow-xl w-full max-w-lg max-h-[calc(100vh-1rem)] overflow-hidden flex flex-col bg-theme-primary">
                <div class="px-4 sm:px-6 py-3.5 border-b border-theme-light flex items-center justify-between bg-theme-primary">
                    <h3 id="edit-ai-agent-title" class="font-semibold text-theme-primary">
                        <?php echo e(t('Edit AI agent')); ?>
                    </h3>
                    <button type="button" onclick="closeAiAgentModal()" class="p-1 text-theme-muted">
                        <?php echo get_icon('x', 'w-5 h-5'); ?>
                    </button>
                </div>
                <div class="p-4 sm:p-5 overflow-y-auto space-y-4">
                    <form method="post" id="editAiAgentForm" class="space-y-3.5"<?php echo $ai_agent_form_action_attr; ?>>
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="id" id="ai_edit_id">
                        <div>
                            <label for="ai_edit_name" class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Name')); ?> *</label>
                            <input type="text" name="agent_name" id="ai_edit_name" required aria-required="true"
                                class="form-input">
                        </div>
                        <div>
                            <label for="ai_edit_model" class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Model')); ?></label>
                            <input type="text" name="ai_model" id="ai_edit_model" class="form-input"
                                placeholder="<?php echo e(t('e.g. claude-sonnet-4-5, gpt-4o')); ?>">
                        </div>
                        <div>
                            <label for="ai_edit_cost_rate" class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Rate/h')); ?></label>
                            <input type="number" name="cost_rate" id="ai_edit_cost_rate" step="0.01" min="0" class="form-input">
                        </div>
                        <div class="border-t border-theme-light pt-3">
                            <h4 class="text-sm font-semibold mb-2 text-theme-secondary">
                                <?php echo e(t('Access')); ?>
                            </h4>
                            <div class="space-y-2">
                                <label class="flex items-center text-sm">
                                    <input type="radio" name="ticket_scope" value="assigned" id="ai_edit_scope_assigned" class="mr-2">
                                    <?php echo e(t('Assigned tickets only')); ?>
                                </label>
                                <label class="flex items-center text-sm">
                                    <input type="radio" name="ticket_scope" value="organization" id="ai_edit_scope_org" class="mr-2">
                                    <?php echo e(t('Tickets from selected organizations')); ?>
                                </label>
                                <label class="flex items-center text-sm">
                                    <input type="radio" name="ticket_scope" value="all" id="ai_edit_scope_all" class="mr-2">
                                    <?php echo e(t('All tickets')); ?>
                                </label>
                            </div>
                            <?php if (!empty($organizations)): ?>
                                    <div id="ai_edit_org_select" class="mt-2 hidden">
                                        <label class="block text-xs mb-1 text-theme-muted">
                                            <?php echo e(t('Select organizations (multiple allowed)')); ?>
                                        </label>
                                        <select name="scope_organization_ids[]" id="ai_edit_scope_organization_ids" multiple
                                            size="5" class="form-select text-sm">
                                            <?php foreach ($organizations as $org): ?>
                                                    <option value="<?php echo $org['id']; ?>"><?php echo e($org['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                            <?php endif; ?>
                            <div class="mt-3 space-y-2">
                                <label class="flex items-center text-sm">
                                    <input type="checkbox" name="can_view_time" id="ai_edit_can_view_time" class="mr-2">
                                    <?php echo e(t('Can view time entries')); ?>
                                </label>
                                <label class="flex items-center text-sm">
                                    <input type="checkbox" name="can_view_timeline" id="ai_edit_can_view_timeline" class="mr-2">
                                    <?php echo e(t('Can view activity timeline')); ?>
                                </label>
                                <label class="flex items-center text-sm">
                                    <input type="checkbox" name="can_view_edit_history" id="ai_edit_can_view_edit_history" class="mr-2">
                                    <?php echo e(t('Can view edit history')); ?>
                                </label>
                            </div>
                        </div>
                        <?php if (!empty($ai_agent_token_scope_groups)): ?>
                                <div class="border-t border-theme-light pt-3">
                                    <h4 class="text-sm font-semibold mb-1 text-theme-secondary">
                                        <?php echo e(t('Token actions')); ?>
                                    </h4>
                                    <p class="text-xs mb-2 text-theme-muted">
                                        <?php echo e(t('Choose what this token can do.')); ?>
                                    </p>
                                    <div class="space-y-2">
                                        <?php foreach ($ai_agent_token_scope_groups as $group_key => $group): ?>
                                                <label class="flex items-start gap-2 text-sm fd-rounded-card border border-theme-light p-2 cursor-pointer">
                                                    <input type="checkbox" name="api_token_scope_groups[]"
                                                        value="<?php echo e($group_key); ?>" class="mt-0.5 fd-rounded-control ai-token-scope-group"
                                                        data-group="<?php echo e($group_key); ?>"
                                                        <?php echo in_array($group_key, $ai_agent_token_default_scope_groups, true) ? 'checked' : ''; ?>>
                                                    <span>
                                                        <span class="font-medium text-theme-primary">
                                                            <?php echo e(t($group['label'])); ?>
                                                        </span>
                                                        <span class="block text-xs text-theme-muted">
                                                            <?php echo e(t($group['description'])); ?>
                                                        </span>
                                                    </span>
                                                </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                        <?php endif; ?>
                        <div>
                            <label class="flex items-center space-x-2 text-sm">
                                <input type="checkbox" name="is_active" id="ai_edit_is_active" value="1">
                                <span><?php echo e(t('Active')); ?></span>
                            </label>
                        </div>
                        <div class="border-t border-theme-light pt-3">
                            <h4 class="text-sm font-semibold mb-1 text-theme-secondary">
                                <?php echo e(t('API token')); ?>
                            </h4>
                            <div id="ai_edit_token_status"></div>
                            <p class="text-xs mt-2 text-theme-muted">
                                <?php echo e(t('The new token uses the access and actions selected above. Creating a new token revokes older active tokens for this agent.')); ?>
                            </p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-3">
                                <button type="submit" name="update_ai_agent" class="btn btn-secondary w-full">
                                    <?php echo e(t('Save access')); ?>
                                </button>
                                <button type="submit" name="save_and_generate_agent_token" class="btn btn-primary w-full">
                                    <?php echo e(t('Save access and create token')); ?>
                                </button>
                            </div>
                        </div>
                    </form>

                    <div id="ai_edit_token_actions"></div>
                </div>
            </div>
        </div>

        <script>
            var _aiAgentReturnFocus = null;
            var _aiAgentTokenGroupScopes = <?php echo json_encode($ai_agent_token_group_scopes, JSON_UNESCAPED_SLASHES); ?>;
            var _aiAgentDefaultTokenGroups = <?php echo json_encode($ai_agent_token_default_scope_groups, JSON_UNESCAPED_SLASHES); ?>;
            var _aiAgentFormAction = <?php echo json_encode($ai_agent_form_action); ?>;

            /**
             * Double-confirmation for deleting an agent.
             * Returns true only if user confirms both prompts.
             */
            function confirmDeleteAgent(name) {
                var q1 = '<?php echo addslashes(e(t('Delete agent "{name}" and all their records (API tokens, time entries)?'))); ?>'.replace('{name}', name);
                if (!confirm(q1)) return false;
                return confirm('<?php echo addslashes(e(t('Are you sure? This cannot be undone.'))); ?>');
            }

            function editAiAgent(agent, token) {
                _aiAgentReturnFocus = document.activeElement;
                document.getElementById('ai_edit_id').value = agent.id;
                document.getElementById('ai_edit_name').value = agent.first_name || '';
                document.getElementById('ai_edit_model').value = agent.ai_model || '';
                document.getElementById('ai_edit_cost_rate').value = agent.cost_rate || '';
                document.getElementById('ai_edit_is_active').checked = agent.is_active == 1;
                setAiAgentAccess(agent);
                setAiAgentTokenScopeGroups(token);

                var statusEl = document.getElementById('ai_edit_token_status');
                var actionsEl = document.getElementById('ai_edit_token_actions');

                // Clear previous content safely
                while (statusEl.firstChild) statusEl.removeChild(statusEl.firstChild);
                while (actionsEl.firstChild) actionsEl.removeChild(actionsEl.firstChild);

                if (token && token.is_active == 1) {
                    var p = document.createElement('p');
                    p.className = 'text-xs mb-1';
                    p.style.color = 'var(--text-muted)';
                    p.textContent = '<?php echo e(t('Active token:')); ?> ';
                    var code = document.createElement('code');
                    code.textContent = (token.token_prefix || '???') + '...';
                    p.appendChild(code);
                    statusEl.appendChild(p);

                    // Revoke button stays separate; creating a new token is submitted through the main form
                    // so it always uses the access and actions selected above.
                    var csrf = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
                    var revokeConfirm = '<?php echo addslashes(e(t('Revoke this token? The agent will lose API access.'))); ?>';
                    var revokeConfirm2 = '<?php echo addslashes(e(t('Are you sure? This cannot be undone.'))); ?>';
                    var actionAttr = _aiAgentFormAction ? ' action="' + _aiAgentFormAction.replace(/"/g, '&quot;') + '"' : '';
                    actionsEl.insertAdjacentHTML('beforeend',
                        '<form method="post" class="mt-2"' + actionAttr + ' onsubmit="return confirm(\'' + revokeConfirm + '\') && confirm(\'' + revokeConfirm2 + '\')">' +
                        '<input type="hidden" name="csrf_token" value="' + csrf + '">' +
                        '<input type="hidden" name="id" value="' + agent.id + '">' +
                        '<input type="hidden" name="token_id" value="' + token.id + '">' +
                        '<button type="submit" name="revoke_agent_token" class="btn btn-warning btn-sm w-full"><?php echo e(t('Revoke token')); ?></button>' +
                        '</form>');
                } else {
                    var p = document.createElement('p');
                    p.className = 'text-xs text-orange-500 mb-1';
                    p.textContent = '<?php echo e(t('No active token')); ?>';
                    statusEl.appendChild(p);
                }

                var modal = document.getElementById('editAiAgentModal');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.body.classList.add('overflow-hidden');
                document.getElementById('ai_edit_name').focus();
                if (typeof trapFocus === 'function') trapFocus(modal);
            }

            function closeAiAgentModal() {
                var modal = document.getElementById('editAiAgentModal');
                if (typeof releaseFocus === 'function') releaseFocus(modal);
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.body.classList.remove('overflow-hidden');
                if (_aiAgentReturnFocus) { _aiAgentReturnFocus.focus(); _aiAgentReturnFocus = null; }
            }

            function parseAiAgentPermissions(agent) {
                var permissions = {};
                if (agent && agent.permissions) {
                    try {
                        permissions = typeof agent.permissions === 'string' ? JSON.parse(agent.permissions) : agent.permissions;
                    } catch (e) {
                        permissions = {};
                    }
                }
                if (!permissions || typeof permissions !== 'object') {
                    permissions = {};
                }
                return permissions;
            }

            function parseAiTokenScopes(token) {
                if (!token || !token.scopes_json) {
                    return [];
                }
                try {
                    var scopes = typeof token.scopes_json === 'string' ? JSON.parse(token.scopes_json) : token.scopes_json;
                    return Array.isArray(scopes) ? scopes : [];
                } catch (e) {
                    return [];
                }
            }

            function setAiAgentTokenScopeGroups(token) {
                var form = document.getElementById('editAiAgentForm');
                if (!form) {
                    return;
                }
                var tokenScopes = parseAiTokenScopes(token);
                var useDefaults = tokenScopes.length === 0;
                var hasWildcard = tokenScopes.indexOf('*') !== -1;
                var tokenScopeSet = new Set(tokenScopes);

                form.querySelectorAll('input[name="api_token_scope_groups[]"]').forEach(function (checkbox) {
                    var group = checkbox.getAttribute('data-group') || checkbox.value;
                    var groupScopes = _aiAgentTokenGroupScopes[group] || [];
                    checkbox.checked = useDefaults
                        ? _aiAgentDefaultTokenGroups.indexOf(group) !== -1
                        : (hasWildcard || groupScopes.some(function (scope) { return tokenScopeSet.has(scope); }));
                });
            }

            function setAiAgentAccess(agent) {
                var form = document.getElementById('editAiAgentForm');
                if (!form) {
                    return;
                }

                var permissions = parseAiAgentPermissions(agent);
                var scope = permissions.ticket_scope || 'assigned';
                if (!['assigned', 'organization', 'all'].includes(scope)) {
                    scope = 'assigned';
                }

                form.querySelectorAll('input[name="ticket_scope"]').forEach(function (radio) {
                    radio.checked = radio.value === scope;
                });

                var selectedIds = Array.isArray(permissions.organization_ids)
                    ? permissions.organization_ids
                    : (permissions.organization_ids ? [permissions.organization_ids] : []);
                if (selectedIds.length === 0 && agent && agent.organization_id) {
                    selectedIds = [agent.organization_id];
                }
                var selected = new Set(selectedIds.map(function (id) {
                    return parseInt(id, 10);
                }).filter(function (id) {
                    return !Number.isNaN(id) && id > 0;
                }));

                var orgSelect = document.getElementById('ai_edit_scope_organization_ids');
                if (orgSelect) {
                    for (var option of orgSelect.options) {
                        option.selected = selected.has(parseInt(option.value, 10));
                    }
                }

                var timeCheckbox = document.getElementById('ai_edit_can_view_time');
                if (timeCheckbox) {
                    timeCheckbox.checked = permissions.can_view_time !== false;
                }
                var timelineCheckbox = document.getElementById('ai_edit_can_view_timeline');
                if (timelineCheckbox) {
                    timelineCheckbox.checked = permissions.can_view_timeline !== false;
                }
                var historyCheckbox = document.getElementById('ai_edit_can_view_edit_history');
                if (historyCheckbox) {
                    historyCheckbox.checked = permissions.can_view_edit_history === true;
                }

                syncAiAgentScope('editAiAgentForm', 'ai_edit_org_select');
            }

            function syncAiAgentScope(formId, orgContainerId) {
                var form = document.getElementById(formId);
                var orgContainer = document.getElementById(orgContainerId);
                if (!form || !orgContainer) {
                    return;
                }
                var checked = form.querySelector('input[name="ticket_scope"]:checked');
                orgContainer.classList.toggle('hidden', !(checked && checked.value === 'organization'));
            }

            function bindAiAgentScope(formId, orgContainerId) {
                var form = document.getElementById(formId);
                if (!form) {
                    return;
                }
                form.querySelectorAll('input[name="ticket_scope"]').forEach(function (radio) {
                    radio.addEventListener('change', function () {
                        syncAiAgentScope(formId, orgContainerId);
                    });
                });
                syncAiAgentScope(formId, orgContainerId);
            }

            document.addEventListener('DOMContentLoaded', function () {
                bindAiAgentScope('aiAddAgentForm', 'ai_add_org_select');
                bindAiAgentScope('editAiAgentForm', 'ai_edit_org_select');

                var modal = document.getElementById('editAiAgentModal');
                if (modal) {
                    modal.addEventListener('click', function (e) {
                        if (e.target === modal) closeAiAgentModal();
                    });
                }
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                        closeAiAgentModal();
                    }
                });
            });
        </script>
