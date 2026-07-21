<?php
// Render partial. Variables are provided by report_render_admin_page().
?>
            <?php if ($selected_flow_org !== null): ?>
            <section class="report-preview-card" data-report-preview>
                <div class="report-preview-header">
                    <div>
                        <p class="report-preview-kicker"><?php echo e(t('Client report preview')); ?></p>
                        <h3><?php echo e($selected_flow_org_name ?: t('Selected client')); ?></h3>
                        <p><?php echo e($report_period_label); ?> · <?php echo e(empty($entries) ? t('No work found for this selection yet.') : t('Open tickets below to review the work notes before publishing.')); ?></p>
                    </div>
                    <div class="report-preview-actions">
                        <?php if (!empty($entries)): ?>
                        <div class="relative" id="col-picker-wrap">
                            <button type="button" data-report-toggle-target="col-picker-dropdown"
                                class="report-mini-action"
                                title="<?php echo e(t('Columns')); ?>">
                                <?php echo get_icon('columns', 'w-3 h-3 inline-block'); ?><?php echo e(t('Columns')); ?>
                            </button>
                            <div id="col-picker-dropdown" class="report-col-picker-dropdown hidden absolute right-0 mt-1 w-44 fd-rounded-card shadow-lg border z-50 p-1.5">
                                <?php foreach ($billing_col_defs as $col_key => $col_label): ?>
                                <label class="flex items-center gap-2 px-2 py-1 text-xs fd-rounded-control cursor-pointer text-theme-primary">
                                    <input type="checkbox" class="fd-rounded-control col-toggle" data-col="<?php echo e($col_key); ?>" checked>
                                    <?php echo e($col_label); ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <a href="index.php?<?php echo http_build_query($report_export_params); ?>"
                            class="report-mini-action"
                            title="<?php echo e(t('Export CSV')); ?>">
                            <?php echo get_icon('download', 'w-3 h-3 inline-block'); ?><?php echo e(t('Export CSV')); ?>
                        </a>
                        <button type="button" data-report-print
                            class="report-mini-action"
                            title="<?php echo e(t('Print')); ?>">
                            <?php echo get_icon('print', 'w-3 h-3 inline-block'); ?><?php echo e(t('Print')); ?>
                        </button>
                        <a href="<?php echo e($report_builder_url); ?>"
                            class="btn btn-primary btn-sm"
                            data-report-create-link>
                            <?php echo get_icon('file-text', 'w-3.5 h-3.5'); ?><?php echo e(t('Create client report')); ?>
                        </a>
                        <?php else: ?>
                        <a href="<?php echo url('admin', ['section' => 'reports-list']); ?>"
                            class="btn btn-secondary btn-sm">
                            <?php echo get_icon('list', 'w-3.5 h-3.5'); ?><?php echo e(t('Report history')); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="report-preview-metrics">
                    <div class="report-preview-metric">
                        <span><?php echo e(t('Items')); ?></span>
                        <strong><?php echo e((string) count($entries)); ?></strong>
                    </div>
                    <div class="report-preview-metric">
                        <span><?php echo e(t('Total time')); ?></span>
                        <strong><?php echo e(format_duration_minutes($totals['minutes'])); ?></strong>
                    </div>
                    <div class="report-preview-metric">
                        <span><?php echo e(t('Billable time')); ?></span>
                        <strong><?php echo e(format_duration_minutes($totals['billable_minutes'])); ?></strong>
                    </div>
                    <?php if ($show_money): ?>
                    <div class="report-preview-metric">
                        <span><?php echo e(t('Billable amount')); ?></span>
                        <strong><?php echo e(format_money($totals['billable_amount'])); ?></strong>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($billing_ticket_details['tickets'])): ?>
                <div class="report-ticket-preview" data-report-ticket-preview>
                    <?php foreach ($billing_ticket_details['tickets'] as $ticket): ?>
                        <?php $preview_detail_id = 'billing-ticket-' . (int) $ticket['id']; ?>
                        <div class="report-ticket-card fd-report-ticket-card">
                            <button type="button"
                                class="report-ticket-summary fd-report-ticket-summary"
                                data-report-ticket-row
                                aria-expanded="false"
                                aria-controls="<?php echo e($preview_detail_id); ?>"
                                data-report-preview-target="<?php echo e($preview_detail_id); ?>">
                                <span class="report-ticket-summary__main fd-report-ticket-main">
                                    <span class="report-ticket-summary__icon fd-report-ticket-icon" aria-hidden="true"><?php echo get_icon('chevron-right'); ?></span>
                                    <span class="fd-report-ticket-copy">
                                        <span class="report-ticket-summary__title fd-report-ticket-title"><?php echo e($ticket['title']); ?></span>
                                        <span class="report-ticket-summary__meta fd-report-ticket-meta">
                                            <?php echo e($ticket['code']); ?> · <?php echo e((string) $ticket['entries_count']); ?> <?php echo e(t('work records')); ?>
                                        </span>
                                    </span>
                                </span>
                                <span class="report-ticket-summary__totals fd-report-ticket-totals">
                                    <strong><?php echo e(format_duration_minutes($ticket['minutes'])); ?></strong>
                                    <?php if ($show_money): ?>
                                        <span><?php echo e(format_money($ticket['amount'])); ?></span>
                                    <?php endif; ?>
                                </span>
                            </button>
                            <div id="<?php echo e($preview_detail_id); ?>" class="report-ticket-preview__details hidden" data-report-ticket-details>
                                <div class="overflow-x-auto">
                                    <table class="w-full">
                                        <thead class="bg-theme-secondary">
                                            <tr>
                                                <th class="px-3 py-2 text-left th-label"><?php echo e(t('Date')); ?></th>
                                                <th class="px-3 py-2 text-left th-label"><?php echo e(t('Work details')); ?></th>
                                                <th class="px-3 py-2 text-left th-label"><?php echo e(t('Time Range')); ?></th>
                                                <th class="px-3 py-2 text-right th-label"><?php echo e(t('Duration')); ?></th>
                                                <th class="px-3 py-2 text-left th-label"><?php echo e(t('Agent')); ?></th>
                                                <?php if ($show_money): ?>
                                                    <th class="px-3 py-2 text-right th-label"><?php echo e(t('Amount')); ?></th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y">
                                            <?php foreach ($ticket['entries'] as $entry): ?>
                                            <tr data-report-comment-row>
                                                <td class="px-3 py-2 text-xs text-theme-secondary"><?php echo e($entry['date'] !== '' ? format_date($entry['date'], 'd.m.Y') : '-'); ?></td>
                                                <td class="px-3 py-2 text-xs">
                                                    <div class="font-medium text-theme-primary"><?php echo e($entry['summary']); ?></div>
                                                    <?php if (!empty($entry['comment_is_internal'])): ?>
                                                        <div class="text-[11px] text-theme-muted"><?php echo e(t('Internal note')); ?></div>
                                                    <?php elseif (!$entry['has_public_comment']): ?>
                                                        <div class="text-[11px] text-theme-muted"><?php echo e(t('No public comment was added for this time entry.')); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-3 py-2 text-xs text-theme-secondary"><?php echo e($entry['time_range']); ?></td>
                                                <td class="px-3 py-2 text-xs text-right text-theme-primary"><?php echo e(format_duration_minutes($entry['duration_minutes'])); ?></td>
                                                <td class="px-3 py-2 text-xs text-theme-secondary"><?php echo e($entry['agent_name']); ?></td>
                                                <?php if ($show_money): ?>
                                                    <td class="px-3 py-2 text-xs text-right text-theme-primary"><?php echo e(format_money($entry['amount'])); ?></td>
                                                <?php endif; ?>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>
            <?php else: ?>
            <section class="report-preview-card report-preview-card--empty" data-report-preview-empty>
                <div class="report-preview-header">
                    <div>
                        <p class="report-preview-kicker"><?php echo e(t('Client report preview')); ?></p>
                        <h3><?php echo e(t('Choose a client')); ?></h3>
                        <p><?php echo e(t('Select one client and period to review ticket totals before publishing.')); ?></p>
                    </div>
                </div>
            </section>
            <?php endif; ?>
            <?php if ($selected_flow_org === null): ?>
            <?php elseif (empty($entries)): ?>
                <div class="card card-body p-8 text-center">
                    <div class="text-4xl mb-3 text-theme-muted">📋</div>
                    <div class="font-semibold mb-1 text-theme-primary"><?php echo e(t('No time entries found')); ?></div>
                    <div class="text-sm text-theme-muted"><?php echo e(t('Try adjusting the time range or filters above.')); ?></div>
                </div>
            <?php else: ?>
            <div class="reporting-review-surface"
                data-app-contract-surface="reporting-review"
                data-app-contract-action="app-reporting-review"
                data-report-time-range="<?php echo e($time_range); ?>"
                data-report-from-date="<?php echo e($from_date); ?>"
                data-report-to-date="<?php echo e($to_date); ?>"
                data-report-organization-ids="<?php echo e(implode(',', array_filter($selected_orgs, static fn ($id) => (int) $id > 0))); ?>"
                data-report-agent-ids="<?php echo e(implode(',', array_filter($selected_agents, static fn ($id) => (int) $id > 0))); ?>"
                data-report-tags="<?php echo e($selected_tags_csv); ?>"
                data-report-limit="250"
                data-report-currency="<?php echo e(function_exists('get_currency_label') ? get_currency_label() : 'CZK'); ?>">
            <div class="report-detail-totals" id="report-detail-totals">
                <div class="report-metric">
                    <div class="report-metric__label"><?php echo e(t('Total time')); ?></div>
                    <div id="detail-total-time" class="report-metric__value" data-report-total="minutes"><?php echo e(format_duration_minutes($totals['minutes'])); ?></div>
                </div>
                <div class="report-metric">
                    <div class="report-metric__label"><?php echo e(t('Billable time')); ?></div>
                    <div id="detail-billable-time" class="report-metric__value" data-report-total="billable_minutes"><?php echo e(format_duration_minutes($totals['billable_minutes'])); ?></div>
                </div>
                <?php if ($show_money): ?>
                <div class="report-metric">
                    <div class="report-metric__label"><?php echo e(t('Billable amount')); ?></div>
                    <div id="detail-billable-amount" class="report-metric__value" data-report-total="billable_amount"><?php echo e(format_money($totals['billable_amount'])); ?></div>
                </div>
                <?php if ($has_cost_data): ?>
                <div class="report-metric">
                    <div class="report-metric__label"><?php echo e(t('Profit')); ?></div>
                    <div id="detail-profit" class="report-metric__value" data-report-total="profit"><?php echo e(format_money($totals['profit'])); ?></div>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php if ($billable_time_notice): ?>
            <div class="report-billing-note report-billing-note--<?php echo e($billable_time_notice['tone']); ?>">
                <div class="report-billing-note__head">
                    <?php echo get_icon('info', 'w-3.5 h-3.5'); ?>
                    <strong><?php echo e($billable_time_notice['title']); ?></strong>
                </div>
                <div class="report-billing-note__body">
                    <span><?php echo e($billable_time_notice['text']); ?></span>
                    <?php if (!empty($billable_time_notice['delta'])): ?>
                        <span class="report-billing-note__delta"><?php echo e($billable_time_notice['delta']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <div class="card overflow-hidden">
                <div class="card-header">
                    <h3 class="font-semibold text-theme-primary"><?php echo e(t('Adjust billable items')); ?></h3>
                </div>
                <?php if (is_admin()): ?>
                <form id="bulk-billing-form" method="post" class="report-bulk-billing px-4 py-3 border-b">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="bulk_update_billable_entries" value="1">
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="min-w-[180px]">
                            <label class="block text-xs font-medium mb-1 text-theme-secondary"><?php echo e(t('Bulk billing adjustments')); ?></label>
                            <select name="bulk_action" class="form-select text-sm">
                                <?php foreach (billing_review_bulk_adjustment_actions() as $action_key => $action_label): ?>
                                    <option value="<?php echo e($action_key); ?>"><?php echo e($action_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="w-32">
                            <label class="block text-xs font-medium mb-1 text-theme-secondary"><?php echo e(t('Hourly rate')); ?></label>
                            <input type="number" step="0.01" min="0" name="bulk_rate" class="form-input text-sm" placeholder="1000">
                        </div>
                        <div class="w-32">
                            <label class="block text-xs font-medium mb-1 text-theme-secondary"><?php echo e(t('Discount (%)')); ?></label>
                            <input type="number" step="0.01" min="0" max="100" name="bulk_discount_percent" class="form-input text-sm" placeholder="10">
                        </div>
                        <div class="w-36">
                            <label class="block text-xs font-medium mb-1 text-theme-secondary"><?php echo e(t('Discount amount')); ?></label>
                            <input type="number" step="0.01" min="0" name="bulk_discount_amount" class="form-input text-sm" placeholder="500">
                        </div>
                        <div class="w-36">
                            <label class="block text-xs font-medium mb-1 text-theme-secondary"><?php echo e(t('Target total')); ?></label>
                            <input type="number" step="0.01" min="0" name="bulk_target_total" class="form-input text-sm" placeholder="15000">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <?php echo e(t('Apply to selected')); ?>
                        </button>
                    </div>
                </form>
                <?php endif; ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-theme-secondary">
                            <tr>
                                <?php if (is_admin()): ?>
                                <th class="px-3 py-2 text-left th-label">
                                    <input type="checkbox" id="bulk-select-all" class="fd-rounded-control" title="<?php echo e(t('Select all')); ?>">
                                </th>
                                <?php endif; ?>
                                <th class="px-3 py-2 text-left th-label" data-col="ticket">
                                    <?php echo e(t('Ticket')); ?></th>
                                <th class="px-3 py-2 text-left th-label" data-col="company">
                                    <?php echo e(t('Company')); ?></th>
                                <?php if ($tags_supported): ?>
                                    <th class="px-3 py-2 text-left th-label" data-col="tags">
                                        <?php echo e(t('Tags')); ?></th>
                                <?php endif; ?>
                                <th class="px-3 py-2 text-left th-label" data-col="duration">
                                    <?php echo e(t('Duration')); ?></th>
                                <th class="px-3 py-2 text-left th-label" data-col="billable">
                                    <?php echo e(t('Billable')); ?></th>
                                <th class="px-3 py-2 text-left th-label" data-col="agent">
                                    <?php echo e(t('Agent')); ?></th>
                                <th class="px-3 py-2 text-left th-label" data-col="source">
                                    <?php echo e(t('Source')); ?></th>
                                <th class="px-3 py-2 text-left th-label" data-col="start">
                                    <?php echo e(t('Start time')); ?></th>
                                <th class="px-3 py-2 text-left th-label" data-col="end">
                                    <?php echo e(t('End time')); ?></th>
                                <?php if ($show_money): ?>
                                    <th class="px-3 py-2 text-left th-label report-amount-col" data-col="amount">
                                        <?php echo e(t('Amount')); ?></th>
                                <?php endif; ?>
                                <?php if ($show_money && $has_cost_data): ?>
                                    <th class="px-3 py-2 text-left th-label" data-col="cost">
                                        <?php echo e(t('Cost')); ?></th>
                                    <th class="px-3 py-2 text-left th-label" data-col="profit">
                                        <?php echo e(t('Profit')); ?></th>
                                <?php endif; ?>
                                <?php if (is_admin()): ?>
                                <th class="px-6 py-3 text-right th-label">
                                    <?php echo e(t('Actions')); ?></th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php foreach ($entries as $entry): ?>
                                <tr class="report-detail-row"
                                    data-report-entry-row
                                    data-entry-id="<?php echo (int) $entry['id']; ?>"
                                    data-billable="<?php echo !empty($entry['is_billable']) ? '1' : '0'; ?>"
                                    data-actual-minutes="<?php echo (int) $entry['actual_minutes']; ?>"
                                    data-billable-minutes="<?php echo (int) $entry['billable_minutes']; ?>"
                                    data-original-rate="<?php echo e(number_format((float) $entry['billable_rate'], 2, '.', '')); ?>"
                                    data-original-amount="<?php echo e(number_format((float) $entry['billable_amount'], 2, '.', '')); ?>"
                                    data-cost-amount="<?php echo e(number_format((float) $entry['cost_amount'], 2, '.', '')); ?>">
                                    <?php if (is_admin()): ?>
                                    <td class="px-3 py-1.5 text-xs">
                                        <input type="checkbox" class="bulk-entry-check fd-rounded-control" name="entry_ids[]" value="<?php echo $entry['id']; ?>" form="bulk-billing-form" <?php echo !empty($entry['is_billable']) ? '' : 'disabled'; ?>>
                                    </td>
                                    <?php endif; ?>
                                    <td class="px-3 py-1.5 text-xs" data-col="ticket"><a href="<?php echo url('ticket', ['id' => $entry['ticket_id']]); ?>" class="text-blue-600 hover:text-blue-800 hover:underline" data-report-entry-field="ticket"><?php echo e($entry['ticket_title']); ?></a></td>
                                    <td class="px-3 py-1.5 text-xs text-theme-secondary" data-col="company" data-report-entry-field="client">
                                        <?php echo e($entry['organization_name'] ?: t('-- No organization --')); ?></td>
                                    <?php if ($tags_supported): ?>
                                        <td class="px-3 py-1.5 text-xs" data-col="tags">
                                            <?php $entry_tags = function_exists('get_ticket_tags_array') ? get_ticket_tags_array($entry['ticket_tags'] ?? '') : []; ?>
                                            <?php if (!empty($entry_tags)): ?>
                                                <div class="flex flex-wrap gap-1">
                                                    <?php foreach (array_slice($entry_tags, 0, 4) as $tag): ?>
                                                        <span class="inline-flex items-center px-1.5 py-0.5 fd-rounded-control tag-badge text-xs">#<?php echo e($tag); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-theme-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <td class="px-3 py-1.5 text-xs text-theme-secondary" data-col="duration" data-report-entry-field="minutes">
                                        <?php echo e(format_duration_minutes($entry['actual_minutes'])); ?></td>
                                    <td class="px-3 py-1.5 text-xs text-theme-secondary" data-col="billable">
                                        <?php if (is_admin()): ?>
                                        <form method="post">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="entry_id" value="<?php echo $entry['id']; ?>">
                                            <select name="is_billable" class="form-select text-xs" data-report-auto-submit>
                                                <option value="1" <?php echo !empty($entry['is_billable']) ? 'selected' : ''; ?>>
                                                    <?php echo e(t('Billable')); ?></option>
                                                <option value="0" <?php echo empty($entry['is_billable']) ? 'selected' : ''; ?>>
                                                    <?php echo e(t('Non-billable')); ?></option>
                                            </select>
                                            <input type="hidden" name="set_billable" value="1">
                                        </form>
                                        <?php else: ?>
                                            <span class="text-xs"><?php echo e(!empty($entry['is_billable']) ? t('Billable') : t('Non-billable')); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-1.5 text-xs text-theme-secondary" data-col="agent" data-report-entry-field="agent">
                                        <?php echo e(trim($entry['first_name'] . ' ' . $entry['last_name'])); ?></td>
                                    <td class="px-3 py-1.5 text-xs" data-col="source">
                                        <?php echo function_exists('render_source_badge') ? render_source_badge($entry['_source'] ?? get_time_entry_source($entry)) : ''; ?></td>
                                    <td class="px-3 py-1.5 text-xs text-theme-secondary" data-col="start"><?php echo e(format_date($entry['started_at'])); ?>
                                    </td>
                                    <td class="px-3 py-1.5 text-xs text-theme-secondary" data-col="end">
                                        <?php echo e($entry['ended_at'] ? format_date($entry['ended_at']) : '-'); ?></td>
                                    <?php if ($show_money): ?>
                                        <td class="px-3 py-1.5 text-xs report-amount-col" data-col="amount">
                                            <div data-entry-amount data-report-entry-field="amount"><?php echo e(format_money($entry['billable_amount'])); ?></div>
                                            <div class="text-[11px] text-theme-muted" data-entry-rate data-report-entry-field="rate"><?php echo e(format_money($entry['billable_rate'])); ?>/h</div>
                                        </td>
                                    <?php endif; ?>
                                    <?php if ($show_money && $has_cost_data): ?>
                                        <td class="px-3 py-1.5 text-xs text-theme-secondary" data-col="cost">
                                            <?php echo e(format_money($entry['cost_amount'])); ?></td>
                                        <td class="px-3 py-1.5 text-xs text-theme-secondary" data-col="profit"><?php echo e(format_money($entry['profit'])); ?>
                                        </td>
                                    <?php endif; ?>
                                    <?php if (is_admin()): ?>
                                    <td class="px-6 py-3 text-right">
                                        <?php
                                        $entry_data = [
                                            'id' => $entry['id'],
                                            'ticket_id' => $entry['ticket_id'],
                                            'ticket_code' => get_ticket_code($entry['ticket_id']),
                                            'ticket_title' => $entry['ticket_title'],
                                            'started_at' => date('Y-m-d\\TH:i', strtotime($entry['started_at'])),
                                            'ended_at' => $entry['ended_at'] ? date('Y-m-d\\TH:i', strtotime($entry['ended_at'])) : ''
                                        ];
                                        ?>
                                        <div class="flex items-center justify-end gap-2">
                                            <button type="button" class="text-blue-600 hover:text-blue-800"
                                                data-report-edit-entry="<?php echo e(json_encode($entry_data, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>"
                                                title="<?php echo e(t('Edit')); ?>">
                                                <?php echo get_icon('edit', 'w-4 h-4'); ?>
                                            </button>
                                            <button type="button" class="hover:text-red-600 text-theme-muted"
                                                data-report-delete-time data-entry-id="<?php echo (int) $entry['id']; ?>"
                                                title="<?php echo e(t('Delete')); ?>">
                                                <?php echo get_icon('trash', 'w-4 h-4'); ?>
                                            </button>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php if (is_admin() && $show_money && !empty($entry['is_billable'])): ?>
                                <tr class="report-entry-adjustment-row">
                                    <td colspan="14" class="px-3 py-2 bg-theme-secondary/40">
                                        <form method="post" class="entry-billing-form flex flex-col sm:flex-row sm:items-end gap-2" data-entry-id="<?php echo $entry['id']; ?>">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="entry_id" value="<?php echo $entry['id']; ?>">
                                            <div class="min-w-0 sm:w-44">
                                                <label class="block text-[11px] font-medium text-theme-muted mb-1"><?php echo e(t('Item adjustment')); ?></label>
                                                <select name="entry_adjust_action" class="form-select text-xs py-1 report-adjust-action w-full">
                                                    <?php foreach (billing_review_adjustment_actions() as $action_key => $action_label): ?>
                                                        <option value="<?php echo e($action_key); ?>"><?php echo e($action_label); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="min-w-0 sm:w-32">
                                                <label class="block text-[11px] font-medium text-theme-muted mb-1"><?php echo e(t('Value')); ?></label>
                                                <input type="number" name="entry_adjust_value" step="0.01" min="0" class="form-input text-xs py-1 report-adjust-value w-full" placeholder="<?php echo e(t('Value')); ?>">
                                            </div>
                                            <button type="submit" name="adjust_billable_entry" class="btn btn-ghost btn-sm shrink-0 w-full sm:w-auto" title="<?php echo e(t('Save billing')); ?>">
                                                <?php echo get_icon('check', 'w-3 h-3'); ?>
                                                <span><?php echo e(t('Save billing')); ?></span>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            </div>
            <?php endif; ?>
