<?php
// Render partial. Variables are provided by report_render_admin_page().
?>
            <?php $agent_client_rates = function_exists('get_agent_client_billable_rates') ? get_agent_client_billable_rates() : []; ?>
            <?php $agent_default_rates = function_exists('get_agent_default_billable_rates') ? get_agent_default_billable_rates() : []; ?>
            <div class="admin-two-column">
                <div class="space-y-4">
                <div class="admin-list-card">
                    <div class="card-header">
                        <div>
                            <h3><?php echo e(t('Agent default rates')); ?></h3>
                            <p><?php echo e(t('Used when no ticket or client-specific rate is set.')); ?></p>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><?php echo e(t('Agent')); ?></th>
                                    <th><?php echo e(t('Default billable rate')); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($agent_default_rates as $rate_row): ?>
                                    <tr>
                                        <td><?php echo e(trim(($rate_row['first_name'] ?? '') . ' ' . ($rate_row['last_name'] ?? '')) ?: $rate_row['email']); ?></td>
                                        <td><?php echo (float) ($rate_row['billable_rate'] ?? 0) > 0 ? e(format_money($rate_row['billable_rate'])) . '/h' : '<span class="text-theme-muted">-</span>'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="admin-list-card">
                    <div class="card-header">
                        <div>
                            <h3><?php echo e(t('Agent client rates')); ?></h3>
                            <p><?php echo e(t('Override the client hourly rate for a specific agent or admin.')); ?></p>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><?php echo e(t('Client')); ?></th>
                                    <th><?php echo e(t('Agent')); ?></th>
                                    <th><?php echo e(t('Billable rate')); ?></th>
                                    <th><?php echo e(t('Notes')); ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($agent_client_rates)): ?>
                                    <tr>
                                        <td colspan="5" class="text-sm text-theme-muted"><?php echo e(t('No custom rates yet.')); ?></td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($agent_client_rates as $rate_row): ?>
                                    <tr>
                                        <td><?php echo e($rate_row['organization_name']); ?></td>
                                        <td><?php echo e(trim(($rate_row['first_name'] ?? '') . ' ' . ($rate_row['last_name'] ?? '')) ?: $rate_row['email']); ?></td>
                                        <td><?php echo e(format_money($rate_row['billable_rate'])); ?>/h</td>
                                        <td class="text-sm text-theme-muted"><?php echo e($rate_row['notes'] ?? ''); ?></td>
                                        <td class="text-right">
                                            <form method="post" class="inline">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="rate_id" value="<?php echo (int) $rate_row['id']; ?>">
                                                <button type="submit" name="delete_agent_client_rate" class="btn btn-ghost btn-xs"
                                                    data-report-confirm="<?php echo e(t('Delete this rate?')); ?>">
                                                    <?php echo e(t('Delete')); ?>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                </div>

                <div class="admin-panel">
                    <div class="admin-panel-header">
                        <div>
                            <h3><?php echo e(t('Add rate')); ?></h3>
                            <p><?php echo e(t('Specific client rates override agent defaults.')); ?></p>
                        </div>
                    </div>
                    <form method="post" class="admin-panel-body space-y-3 border-b border-theme">
                        <?php echo csrf_field(); ?>
                        <div>
                            <label class="block text-sm font-medium mb-1"><?php echo e(t('Agent')); ?></label>
                            <select name="user_id" class="form-select" required>
                                <option value=""><?php echo e(t('Select agent')); ?></option>
                                <?php foreach ($agents as $agent): ?>
                                    <option value="<?php echo (int) $agent['id']; ?>"><?php echo e(trim($agent['first_name'] . ' ' . $agent['last_name'])); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1"><?php echo e(t('Default billable rate')); ?></label>
                            <input type="number" name="billable_rate" step="0.01" min="0" class="form-input" placeholder="1000" required>
                        </div>
                        <button type="submit" name="save_agent_default_rate" class="btn btn-secondary w-full justify-center">
                            <?php echo e(t('Save default rate')); ?>
                        </button>
                    </form>
                    <form method="post" class="admin-panel-body space-y-3">
                        <?php echo csrf_field(); ?>
                        <div>
                            <label class="block text-sm font-medium mb-1"><?php echo e(t('Client')); ?></label>
                            <select name="organization_id" class="form-select" required>
                                <option value=""><?php echo e(t('Select client')); ?></option>
                                <?php foreach ($organizations as $org): ?>
                                    <option value="<?php echo (int) $org['id']; ?>"><?php echo e($org['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1"><?php echo e(t('Agent')); ?></label>
                            <select name="user_id" class="form-select" required>
                                <option value=""><?php echo e(t('Select agent')); ?></option>
                                <?php foreach ($agents as $agent): ?>
                                    <option value="<?php echo (int) $agent['id']; ?>"><?php echo e(trim($agent['first_name'] . ' ' . $agent['last_name'])); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1"><?php echo e(t('Billable rate (per hour)')); ?></label>
                            <input type="number" name="billable_rate" step="0.01" min="0" class="form-input" placeholder="750" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1"><?php echo e(t('Notes')); ?></label>
                            <textarea name="notes" rows="3" class="form-textarea" placeholder="<?php echo e(t('Optional')); ?>"></textarea>
                        </div>
                        <button type="submit" name="save_agent_client_rate" class="btn btn-primary w-full justify-center">
                            <?php echo e(t('Save rate')); ?>
                        </button>
                    </form>
                </div>
            </div>
