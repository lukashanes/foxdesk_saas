<?php
/** Focused settings section partial. Variables are supplied by settings-page-view-model.php. */
?>
        <div class="space-y-3" data-settings-api-access>
            <div class="card card-body">
                <div class="fd-section-header">
                    <div class="fd-section-main">
                        <h2 class="fd-section-title"><?php echo e(t('API & agents')); ?></h2>
                        <p class="text-sm text-theme-secondary">
                            <?php echo e(t('Create a key, choose what it can do, copy it once, and store it only in the tool that will use it.')); ?>
                        </p>
                    </div>
                </div>

                <div class="mb-5 space-y-4" data-api-access-builder>
                    <?php if ($new_profile_api_token): ?>
                        <?php
                        $settings_agent_handoff = "FOXDESK_BASE_URL=" . $settings_api_base_url . "\n"
                            . "FOXDESK_API_TOKEN=" . $new_profile_api_token . "\n\n"
                            . "curl -fsS \"\$FOXDESK_BASE_URL/index.php?page=api&action=agent-docs\" \\\n"
                            . "  -H \"Authorization: Bearer \$FOXDESK_API_TOKEN\"";
                        ?>
                        <div class="p-4 fd-rounded-card border border-green-200 bg-green-50 text-green-900" data-api-key-ready>
                            <div class="fd-section-header">
                                <div class="fd-section-main">
                                    <h3 class="fd-section-title text-green-950"><?php echo e(t('API token generated. Copy it now.')); ?></h3>
                                    <p class="text-sm text-green-900">
                                        <?php echo e(t('Copy this API key now. It will not be shown again.')); ?>
                                    </p>
                                </div>
                                <div class="fd-section-actions">
                                    <button type="button" class="btn btn-primary" data-api-key-copy
                                        onclick="copyGeneratedApiKey('settings-generated-api-token', this)">
                                        <?php echo get_icon('copy', 'w-4 h-4 mr-1 inline-block'); ?><?php echo e(t('Copy')); ?>
                                    </button>
                                </div>
                            </div>
                            <code id="settings-generated-api-token" class="block p-3 fd-rounded-control bg-white border text-xs font-mono break-all select-all"><?php echo e($new_profile_api_token); ?></code>
                            <?php if (!empty($new_profile_api_token_scopes)): ?>
                                <p class="text-xs mt-2"><?php echo e(t('Scopes')); ?>: <?php echo e(implode(', ', $new_profile_api_token_scopes)); ?></p>
                            <?php endif; ?>
                            <div class="mt-3 fd-rounded-card bg-white/90 border border-green-200 p-3" data-agent-api-handoff>
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <div class="font-semibold mb-1"><?php echo e(t('Give this to the agent')); ?></div>
                                        <p class="text-xs text-green-900">
                                            <?php echo e(t('The FoxDesk URL is the API host. The agent must not open the login page or wait for a browser session.')); ?>
                                        </p>
                                    </div>
                                    <button type="button" class="btn btn-secondary btn-sm"
                                        onclick="copyGeneratedApiKey('settings-agent-api-handoff', this)">
                                        <?php echo get_icon('copy', 'w-4 h-4 mr-1 inline-block'); ?><?php echo e(t('Copy instructions')); ?>
                                    </button>
                                </div>
                                <pre class="mt-2 p-3 fd-rounded-control bg-theme-app border text-xs font-mono overflow-auto"><code id="settings-agent-api-handoff"><?php echo e($settings_agent_handoff); ?></code></pre>
                                <p class="text-xs mt-2 text-green-900">
                                    <?php echo e(t('First call agent-docs, then use only the API actions returned for this token.')); ?>
                                </p>
                            </div>
                            <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
                                <div class="fd-rounded-control bg-white/80 border border-green-200 p-2">
                                    <div class="font-semibold mb-1"><?php echo e(t('Where to put it')); ?></div>
                                    <p><?php echo e(t('Add it to Codex, Claude, or your automation as a secret named FOXDESK_API_TOKEN.')); ?></p>
                                </div>
                                <div class="fd-rounded-control bg-white/80 border border-green-200 p-2">
                                    <div class="font-semibold mb-1"><?php echo e(t('How to use it safely')); ?></div>
                                    <p><?php echo e(t('Do not paste the key into chat, screenshots, tickets, or shared documents.')); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div>
                            <h3 class="font-semibold text-theme-primary"><?php echo e(t('Create access')); ?></h3>
                            <p class="text-sm text-theme-muted mt-1">
                                <?php echo e(t('Create one key for a trusted tool. Choose whether it acts as you or as a separate AI worker.')); ?>
                            </p>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3" role="radiogroup" aria-label="<?php echo e(t('Who will use this access?')); ?>">
                            <label class="fd-rounded-card border border-theme-light p-3 cursor-pointer bg-theme-app" data-api-access-choice>
                                <input type="radio" name="api_access_mode" value="user" class="mr-2" checked data-api-access-mode>
                                <span class="font-semibold text-theme-primary"><?php echo e(t('Use my account')); ?></span>
                                <span class="block text-sm text-theme-muted mt-1">
                                    <?php echo e(t('Best for Codex, Claude, scripts, or automations that should use your current permissions.')); ?>
                                </span>
                            </label>
                            <label class="fd-rounded-card border border-theme-light p-3 cursor-pointer bg-theme-app" data-api-access-choice>
                                <input type="radio" name="api_access_mode" value="agent" class="mr-2" data-api-access-mode>
                                <span class="font-semibold text-theme-primary"><?php echo e(t('Create a separate AI worker')); ?></span>
                                <span class="block text-sm text-theme-muted mt-1">
                                    <?php echo e(t('Use this when you want a separate name, hourly rate, and audit trail for the assistant.')); ?>
                                </span>
                            </label>
                        </div>

                        <form method="post" action="<?php echo e(url('admin', ['section' => 'settings', 'tab' => 'api'])); ?>" class="space-y-4" data-api-token-create-form data-api-access-panel="user">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="api_token_usage" value="automation">
                            <?php if (!empty($api_permission_presets)): ?>
                                <div data-api-permission-presets>
                                    <div class="flex items-center justify-between gap-3 mb-2">
                                        <div class="text-sm font-medium text-theme-secondary"><?php echo e(t('Permission level')); ?></div>
                                        <div class="text-xs text-theme-muted"><?php echo e(t('All is the only level that can delete records.')); ?></div>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2" role="radiogroup" aria-label="<?php echo e(t('Permission level')); ?>">
                                        <?php foreach ($api_permission_presets as $preset_key => $preset): ?>
                                            <label class="fd-rounded-card border border-theme-light p-3 cursor-pointer bg-theme-app" data-api-permission-preset>
                                                <input type="radio" name="api_permission_preset" value="<?php echo e($preset_key); ?>" class="mr-2"
                                                    <?php echo $preset_key === 'read_write' ? 'checked' : ''; ?>>
                                                <span class="font-semibold text-theme-primary"><?php echo e(t($preset['label'])); ?></span>
                                                <span class="block text-xs text-theme-muted mt-1"><?php echo e(t($preset['description'])); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div>
                                <label for="settings-api-token-name" class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Access name')); ?></label>
                                <input type="text" name="api_token_name" id="settings-api-token-name" class="form-input"
                                    placeholder="<?php echo e(t('Codex local assistant')); ?>" maxlength="120">
                            </div>
                            <p class="text-xs text-theme-muted"><?php echo e(t('The key stays active until you revoke it.')); ?></p>

                            <button type="submit" name="create_api_token" class="btn btn-primary">
                                <?php echo e(t('Create key')); ?>
                            </button>
                        </form>

                        <?php if ($api_agents_available): ?>
                            <form method="post" action="<?php echo e(url('admin', ['section' => 'users', 'tab' => 'ai_agents'])); ?>" id="aiAddAgentForm" class="space-y-4 hidden" data-api-access-panel="agent" data-ai-agent-create>
                                <?php echo csrf_field(); ?>
                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
                                    <div>
                                        <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Worker name')); ?> *</label>
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
                                        <?php if (!empty($api_organizations)): ?>
                                            <div id="ai_add_org_select" class="mt-2 hidden">
                                                <label class="block text-xs mb-1 text-theme-muted">
                                                    <?php echo e(t('Select clients')); ?>
                                                </label>
                                                <select name="scope_organization_ids[]" multiple size="4" class="form-select text-sm">
                                                    <?php foreach ($api_organizations as $org): ?>
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

                                    <?php if (!empty($api_permission_presets)): ?>
                                        <div class="fd-rounded-card border border-theme-light p-3">
                                            <h4 class="text-sm font-semibold mb-1 text-theme-primary">
                                                <?php echo e(t('Permission level')); ?>
                                            </h4>
                                            <p class="text-xs mb-2 text-theme-muted">
                                                <?php echo e(t('Choose the broad access level first. All is the only level that can delete records.')); ?>
                                            </p>
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-2" role="radiogroup" aria-label="<?php echo e(t('Permission level')); ?>">
                                                <?php foreach ($api_permission_presets as $preset_key => $preset): ?>
                                                    <label class="fd-rounded-control border border-theme-light p-2 cursor-pointer bg-theme-app">
                                                        <input type="radio" name="api_permission_preset" value="<?php echo e($preset_key); ?>" class="mr-2"
                                                            <?php echo $preset_key === 'read_write' ? 'checked' : ''; ?>>
                                                        <span class="font-medium text-theme-primary"><?php echo e(t($preset['label'])); ?></span>
                                                        <span class="block text-xs text-theme-muted mt-1"><?php echo e(t($preset['description'])); ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <p class="text-xs text-theme-muted">
                                        <?php echo e(t('After creating the worker, copy the key once and store it as a secret.')); ?>
                                    </p>
                                    <button type="submit" name="add_ai_agent" class="btn btn-primary">
                                        <?php echo e(t('Create worker and key')); ?>
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>

                        <?php $settings_agent_instructions = foxdesk_agent_operating_instructions_markdown(get_app_language(), current_user()); ?>
                        <div class="p-3 fd-rounded-card border border-theme-light bg-theme-secondary" data-agent-docs-instructions>
                            <div class="font-semibold text-theme-primary"><?php echo e(t('Agent instructions: FoxDesk tickets')); ?></div>
                            <p class="text-sm text-theme-muted mt-1">
                                <?php echo e(t('Every key can read live instructions before it acts. The response shows allowed actions, missing permissions, request fields, and safety rules.')); ?>
                            </p>
                            <code class="mt-2 block p-2 fd-rounded-control bg-theme-app border text-xs font-mono break-all">GET /index.php?page=api&amp;action=agent-docs&amp;instruction_language=<?php echo e(get_app_language()); ?></code>
                            <details class="mt-3">
                                <summary class="cursor-pointer text-sm font-semibold text-theme-primary"><?php echo e(t('Agent instructions')); ?></summary>
                                <pre class="mt-2 p-3 fd-rounded-control bg-theme-app border text-xs whitespace-pre-wrap overflow-auto"><code><?php echo e($settings_agent_instructions); ?></code></pre>
                            </details>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="admin-responsive-table-wrap">
                    <table class="admin-responsive-table tickets-table" data-api-token-table>
                        <thead>
                            <tr>
                                <th class="th-label text-left py-2"><?php echo e(t('Name')); ?></th>
                                <th class="th-label text-left py-2"><?php echo e(t('Prefix')); ?></th>
                                <th class="th-label text-left py-2"><?php echo e(t('Scopes')); ?></th>
                                <th class="th-label text-left py-2"><?php echo e(t('Last used')); ?></th>
                                <th class="th-label text-right py-2"><?php echo e(t('Actions')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($profile_api_tokens)): ?>
                                <tr>
                                    <td colspan="5" class="py-4 text-sm text-theme-muted"><?php echo e(t('No API keys yet.')); ?></td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($profile_api_tokens as $token): ?>
                                <?php
                                $token_scopes = !empty($token['scopes_json']) ? json_decode((string) $token['scopes_json'], true) : ['*'];
                                if (!is_array($token_scopes) || empty($token_scopes)) {
                                    $token_scopes = ['*'];
                                }
                                $is_active_token = !empty($token['is_active']) && empty($token['revoked_at']);
                                ?>
                                <tr class="<?php echo $is_active_token ? '' : 'opacity-60'; ?>">
                                    <td class="py-2 text-sm text-theme-primary" data-label="<?php echo e(t('Name')); ?>"><?php echo e($token['name']); ?></td>
                                    <td class="py-2 text-xs font-mono text-theme-muted" data-label="<?php echo e(t('Prefix')); ?>"><?php echo e($token['token_prefix']); ?>...</td>
                                    <td class="py-2 text-xs text-theme-muted" data-label="<?php echo e(t('Scopes')); ?>"><?php echo e(implode(', ', $token_scopes)); ?></td>
                                    <td class="py-2 text-xs text-theme-muted" data-label="<?php echo e(t('Last used')); ?>"><?php echo e(!empty($token['last_used_at']) ? format_date($token['last_used_at']) : t('Never')); ?></td>
                                    <td class="py-2 text-right" data-label="<?php echo e(t('Actions')); ?>">
                                        <?php if ($is_active_token): ?>
                                            <form method="post" action="<?php echo e(url('admin', ['section' => 'settings', 'tab' => 'api'])); ?>" class="inline">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="token_id" value="<?php echo (int) $token['id']; ?>">
                                                <button type="submit" name="revoke_api_token" class="btn btn-danger btn-sm"
                                                    onclick="return confirm('<?php echo e(t('Revoke this API key?')); ?>')">
                                                    <?php echo e(t('Revoke')); ?>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-xs text-theme-muted"><?php echo e(t('Revoked')); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card card-body" data-api-tester>
                <div class="fd-section-header">
                    <div class="fd-section-main">
                        <h2 class="fd-section-title"><?php echo e(t('Test API')); ?></h2>
                        <p class="text-sm text-theme-secondary">
                            <?php echo e(t('Paste a token, choose an action, and run a request without leaving Settings.')); ?>
                        </p>
                    </div>
                </div>
                <form class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_220px_140px] gap-3" data-api-test-form>
                    <div>
                        <label for="api-test-token" class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Bearer token')); ?></label>
                        <input type="password" id="api-test-token" class="form-input" autocomplete="off" placeholder="fdx_...">
                    </div>
                    <div>
                        <label for="api-test-action" class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Action')); ?></label>
                        <select id="api-test-action" class="form-select">
                            <option value="agent-me" data-method="GET">GET agent-me</option>
                            <option value="agent-list-tickets" data-method="GET">GET agent-list-tickets</option>
                            <option value="agent-create-ticket" data-method="POST">POST agent-create-ticket</option>
                            <option value="agent-log-time" data-method="POST">POST agent-log-time</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="btn btn-primary w-full"><?php echo e(t('Run test')); ?></button>
                    </div>
                    <div class="lg:col-span-3">
                        <label for="api-test-body" class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Request body')); ?></label>
                        <textarea id="api-test-body" class="form-input min-h-24 font-mono text-xs" spellcheck="false">{"title":"API test ticket","description":"Created from Settings API tester"}</textarea>
                    </div>
                </form>
                <pre class="mt-3 p-3 fd-rounded-card border border-theme-light bg-theme-secondary text-xs overflow-auto hidden" data-api-test-response></pre>
            </div>

            <?php if ($api_agents_available): ?>
                <?php
                $ai_agents = $api_ai_agents;
                $ai_agent_tokens = $api_ai_agent_tokens;
                $organizations = $api_organizations;
                $ai_agent_form_action = url('admin', ['section' => 'users', 'tab' => 'ai_agents']);
                $ai_agent_hide_create_form = true;
                ?>
                <?php include BASE_PATH . '/includes/components/team-ai-agents-tab.php'; ?>
            <?php endif; ?>
        </div>

    <script>
        function copyGeneratedApiKey(fieldId, button) {
            const field = document.getElementById(fieldId);
            if (!field) return;
            const value = (field.value || field.textContent || '').trim();
            if (!value) return;
            const original = button ? button.innerHTML : '';
            function markDone() {
                if (!button) return;
                button.textContent = <?php echo json_encode(t('Copied')); ?>;
                setTimeout(function () {
                    button.innerHTML = original || <?php echo json_encode(t('Copy')); ?>;
                }, 1400);
            }
            function fallbackCopy() {
                const temp = document.createElement('textarea');
                temp.value = value;
                temp.setAttribute('readonly', 'readonly');
                temp.className = 'sr-only';
                document.body.appendChild(temp);
                temp.select();
                try {
                    document.execCommand('copy');
                    markDone();
                } finally {
                    document.body.removeChild(temp);
                }
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value).then(markDone).catch(fallbackCopy);
                return;
            }
            fallbackCopy();
        }

        (function () {
            const modeInputs = document.querySelectorAll('[data-api-access-mode]');
            const accessPanels = document.querySelectorAll('[data-api-access-panel]');
            function syncAccessMode() {
                const checked = document.querySelector('[data-api-access-mode]:checked');
                const mode = checked ? checked.value : 'user';
                accessPanels.forEach(function (panel) {
                    panel.classList.toggle('hidden', panel.getAttribute('data-api-access-panel') !== mode);
                });
            }
            modeInputs.forEach(function (input) {
                input.addEventListener('change', syncAccessMode);
            });
            syncAccessMode();

            const form = document.querySelector('[data-api-test-form]');
            const output = document.querySelector('[data-api-test-response]');
            if (!form || !output) return;

            form.addEventListener('submit', async function (event) {
                event.preventDefault();
                const token = document.getElementById('api-test-token')?.value.trim() || '';
                const actionSelect = document.getElementById('api-test-action');
                const selected = actionSelect?.selectedOptions?.[0];
                const action = actionSelect?.value || '';
                const method = selected?.dataset?.method || 'GET';
                const bodyValue = document.getElementById('api-test-body')?.value.trim() || '';

                output.classList.remove('hidden');
                if (!token) {
                    output.textContent = <?php echo json_encode(t('Paste a bearer token first.')); ?>;
                    return;
                }

                const options = {
                    method,
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json'
                    }
                };
                if (method !== 'GET') {
                    options.headers['Content-Type'] = 'application/json';
                    options.body = bodyValue || '{}';
                }

                output.textContent = <?php echo json_encode(t('Running...')); ?>;
                try {
                    const response = await fetch('index.php?page=api&action=' + encodeURIComponent(action), options);
                    const text = await response.text();
                    let formatted = text;
                    try {
                        formatted = JSON.stringify(JSON.parse(text), null, 2);
                    } catch (ignored) {}
                    output.textContent = method + ' ' + action + '\nHTTP ' + response.status + '\n\n' + formatted;
                } catch (error) {
                    output.textContent = String(error && error.message ? error.message : error);
                }
            });
        })();
    </script>
