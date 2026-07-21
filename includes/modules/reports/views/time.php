<?php
// Render partial. Variables are provided by report_render_admin_page().
?>
            <?php
            $work_log_rows = report_time_overview_work_log_rows($entries, 120);
            $work_log_total = count($entries);
            ?>
            <div class="report-summary-strip">
                <div class="report-metric">
                    <div class="report-metric__label"><?php echo e(is_admin() ? t('Total time') : t('My time')); ?></div>
                    <a class="report-metric__value report-metric__link" href="#report-work-log"><?php echo e(format_duration_minutes($totals['minutes'])); ?></a>
                </div>
                <div class="report-metric">
                    <div class="report-metric__label"><?php echo e(t('Entries')); ?></div>
                    <div class="report-metric__value"><?php echo e((string) count($entries)); ?></div>
                </div>
                <div class="report-metric">
                    <div class="report-metric__label"><?php echo e(t('Clients')); ?></div>
                    <div class="report-metric__value"><?php echo e((string) count($by_org)); ?></div>
                </div>
                <?php if (is_admin()): ?>
                <div class="report-metric">
                    <div class="report-metric__label"><?php echo e(t('Agents')); ?></div>
                    <div class="report-metric__value"><?php echo e((string) count($by_agent)); ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($work_log_rows)): ?>
            <section class="card report-worklog-card" id="report-work-log" data-report-time-overview-log>
                <div class="card-header report-worklog-card__header">
                    <div>
                        <p class="admin-eyebrow"><?php echo e(t('Work log')); ?></p>
                        <h3 class="report-section-title"><?php echo e(t('What was done')); ?></h3>
                    </div>
                    <?php if ($work_log_total > count($work_log_rows)): ?>
                    <span class="report-worklog-card__count">
                        <?php echo e(t('Showing latest {shown} of {total} entries.', ['shown' => count($work_log_rows), 'total' => $work_log_total])); ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table report-worklog-table">
                        <thead>
                            <tr>
                                <th><?php echo e(t('Started')); ?></th>
                                <th><?php echo e(t('Agent')); ?></th>
                                <th><?php echo e(t('Ticket')); ?></th>
                                <th><?php echo e(t('Client')); ?></th>
                                <th><?php echo e(t('Time')); ?></th>
                                <th><?php echo e(t('Note')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($work_log_rows as $row): ?>
                            <tr>
                                <td>
                                    <span class="report-worklog-table__date"><?php echo e($row['started_label']); ?></span>
                                    <?php if ($row['ended_label'] !== ''): ?>
                                    <span class="report-worklog-table__muted">– <?php echo e($row['ended_label']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e($row['agent']); ?></td>
                                <td>
                                    <a href="<?php echo url('ticket', ['id' => $row['ticket_id']]); ?>" class="report-worklog-table__ticket">
                                        <?php if ($row['ticket_code'] !== ''): ?>
                                            <span><?php echo e($row['ticket_code']); ?></span>
                                        <?php endif; ?>
                                        <?php echo e($row['ticket_title']); ?>
                                    </a>
                                </td>
                                <td><?php echo e($row['client']); ?></td>
                                <td>
                                    <span class="report-worklog-table__duration"><?php echo e(format_duration_minutes($row['minutes'])); ?></span>
                                </td>
                                <td class="report-worklog-table__note"><?php echo e($row['summary']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php endif; ?>
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

            <?php
            $human_min = $totals['human_minutes'] ?? 0;
            $ai_min = $totals['ai_minutes'] ?? 0;
            if ($human_min > 0 && $ai_min > 0):
            ?>
            <div class="report-source-strip">
                <div class="report-source-card report-source-card--human">
                    <div class="report-source-card__label">
                        <?php echo get_icon('user', 'w-3 h-3'); ?>
                        <?php echo e(t('Human')); ?>
                    </div>
                    <div class="report-source-card__value"><?php echo e(format_duration_minutes($human_min)); ?></div>
                    <?php if ($show_money): ?>
                        <div class="report-source-card__meta">
                            <?php echo e(t('Billable')); ?>: <?php echo e(format_money($totals['human_billable'] ?? 0)); ?>
                            <?php if ($has_cost_data): ?>
                            · <?php echo e(t('Cost')); ?>: <?php echo e(format_money($totals['human_cost'] ?? 0)); ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="report-source-card report-source-card--ai">
                    <div class="report-source-card__label">
                        <?php echo get_icon('bot', 'w-3 h-3'); ?>
                        <?php echo e(t('AI')); ?>
                    </div>
                    <div class="report-source-card__value"><?php echo e(format_duration_minutes($ai_min)); ?></div>
                    <?php if ($show_money): ?>
                        <div class="report-source-card__meta">
                            <?php echo e(t('Billable')); ?>: <?php echo e(format_money($totals['ai_billable'] ?? 0)); ?>
                            <?php if ($has_cost_data): ?>
                            · <?php echo e(t('Cost')); ?>: <?php echo e(format_money($totals['ai_cost'] ?? 0)); ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (empty($entries)): ?>
                <div class="card card-body p-8 text-center">
                    <div class="text-4xl mb-3 text-theme-muted">📊</div>
                    <div class="font-semibold mb-1 text-theme-primary"><?php echo e(t('No time entries found')); ?></div>
                    <div class="text-sm text-theme-muted"><?php echo e(t('Try adjusting the time range or filters above.')); ?></div>
                </div>
            <?php else: ?>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-2">
                <div class="card overflow-hidden">
                    <div class="card-header report-card-header--compact">
                        <h3 class="report-section-title"><?php echo e(t('Company')); ?></h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full data-table">
                            <thead>
                                <tr>
                                    <th><?php echo e(t('Company')); ?></th>
                                    <th><?php echo e(t('Time')); ?></th>
                                    <th><?php echo e(t('Billable time')); ?></th>
                                    <?php if ($show_money): ?>
                                        <th><?php echo e(t('Billable rate')); ?></th>
                                        <th><?php echo e(t('Amount')); ?></th>
                                    <?php endif; ?>
                                    <?php if ($show_money && $has_cost_data): ?>
                                        <th><?php echo e(t('Profit')); ?></th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <?php foreach ($by_org as $org):
                                    $org_pct = $totals['minutes'] > 0 ? round(($org['minutes'] / $totals['minutes']) * 100) : 0;
                                ?>
                                    <tr>
                                        <td class="px-3 py-1.5 text-xs text-theme-primary"><?php echo e($org['name']); ?></td>
                                        <td class="px-3 py-1.5 text-xs text-theme-secondary">
                                            <a href="<?php echo e($report_log_url(['organizations' => [(int) ($org['id'] ?? 0)]])); ?>" class="report-time-drilldown">
                                                <?php echo e(format_duration_minutes($org['minutes'])); ?>
                                            </a>
                                            <div class="flex items-center gap-1.5 mt-1">
                                                <div class="report-mini-progress">
                                                    <div class="report-mini-progress__bar report-mini-progress__bar--org <?php echo e(report_width_class($org_pct)); ?>"></div>
                                                </div>
                                                <span class="text-xs text-theme-muted"><?php echo $org_pct; ?>%</span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-1.5 text-xs text-theme-secondary">
                                            <?php echo e(format_duration_minutes($org['billable_minutes'])); ?></td>
                                        <?php if ($show_money): ?>
                                            <td class="px-3 py-1.5 text-xs text-theme-secondary"><?php echo e(format_money($org['rate'])); ?></td>
                                            <td class="px-3 py-1.5 text-xs text-theme-secondary">
                                                <?php echo e(format_money($org['billable_amount'])); ?></td>
                                        <?php endif; ?>
                                        <?php if ($show_money && $has_cost_data): ?>
                                            <td class="px-3 py-1.5 text-xs text-theme-secondary"><?php echo e(format_money($org['profit'])); ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if (is_admin()): ?>
                <div class="card overflow-hidden">
                    <div class="card-header report-card-header--compact">
                        <h3 class="report-section-title"><?php echo e(t('Agents')); ?></h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-theme-secondary">
                                <tr>
                                    <th class="px-3 py-2 text-left th-label">
                                        <?php echo e(t('Agent')); ?></th>
                                    <th class="px-3 py-2 text-left th-label">
                                        <?php echo e(t('Time')); ?></th>
                                    <th class="px-3 py-2 text-left th-label">
                                        <?php echo e(t('Billable time')); ?></th>
                                    <?php if ($show_money): ?>
                                        <th class="px-3 py-2 text-left th-label">
                                            <?php echo e(t('Amount')); ?></th>
                                    <?php endif; ?>
                                    <?php if ($show_money && $has_cost_data): ?>
                                        <th class="px-3 py-2 text-left th-label">
                                            <?php echo e(t('Profit')); ?></th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <?php foreach ($by_agent as $agent):
                                    $agent_pct = $totals['minutes'] > 0 ? round(($agent['minutes'] / $totals['minutes']) * 100) : 0;
                                ?>
                                    <tr>
                                        <td class="px-3 py-1.5 text-xs text-theme-primary"><?php echo e($agent['name']); ?></td>
                                        <td class="px-3 py-1.5 text-xs text-theme-secondary">
                                            <a href="<?php echo e($report_log_url(['agents' => [(int) ($agent['id'] ?? 0)]])); ?>" class="report-time-drilldown">
                                                <?php echo e(format_duration_minutes($agent['minutes'])); ?>
                                            </a>
                                            <div class="flex items-center gap-1.5 mt-1">
                                                <div class="report-mini-progress">
                                                    <div class="report-mini-progress__bar report-mini-progress__bar--agent <?php echo e(report_width_class($agent_pct)); ?>"></div>
                                                </div>
                                                <span class="text-xs text-theme-muted"><?php echo $agent_pct; ?>%</span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-1.5 text-xs text-theme-secondary">
                                            <?php echo e(format_duration_minutes($agent['billable_minutes'])); ?></td>
                                        <?php if ($show_money): ?>
                                            <td class="px-3 py-1.5 text-xs text-theme-secondary">
                                                <?php echo e(format_money($agent['billable_amount'])); ?></td>
                                        <?php endif; ?>
                                        <?php if ($show_money && $has_cost_data): ?>
                                            <td class="px-3 py-1.5 text-xs text-theme-secondary"><?php echo e(format_money($agent['profit'])); ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($by_source) && count($by_source) > 1): ?>
            <div class="card overflow-hidden">
                <div class="card-header border-theme-light">
                    <h3 class="font-semibold text-theme-primary"><?php echo e(t('Source')); ?></h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full data-table">
                        <thead>
                            <tr>
                                <th><?php echo e(t('Source')); ?></th>
                                <th><?php echo e(t('Entries')); ?></th>
                                <th><?php echo e(t('Time')); ?></th>
                                <th><?php echo e(t('Billable time')); ?></th>
                                <?php if ($show_money): ?>
                                    <th><?php echo e(t('Amount')); ?></th>
                                <?php endif; ?>
                                <?php if ($show_money && $has_cost_data): ?>
                                    <th><?php echo e(t('Profit')); ?></th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php foreach ($by_source as $src):
                                $src_pct = $totals['minutes'] > 0 ? round(($src['minutes'] / $totals['minutes']) * 100) : 0;
                            ?>
                                <tr>
                                    <td class="px-3 py-1.5 text-xs"><?php echo function_exists('render_source_badge') ? render_source_badge($src['source']) : e($src['label']); ?></td>
                                    <td class="px-3 py-1.5 text-xs text-theme-secondary"><?php echo (int) $src['count']; ?></td>
                                    <td class="px-3 py-1.5 text-xs text-theme-secondary">
                                        <?php echo e(format_duration_minutes($src['minutes'])); ?>
                                        <div class="flex items-center gap-1.5 mt-1">
                                            <div class="report-mini-progress">
                                                <div class="report-mini-progress__bar report-mini-progress__bar--source <?php echo e(report_width_class($src_pct)); ?>"></div>
                                            </div>
                                            <span class="text-xs text-theme-muted"><?php echo $src_pct; ?>%</span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-1.5 text-xs text-theme-secondary"><?php echo e(format_duration_minutes($src['billable_minutes'])); ?></td>
                                    <?php if ($show_money): ?>
                                        <td class="px-3 py-1.5 text-xs text-theme-secondary"><?php echo e(format_money($src['billable_amount'])); ?></td>
                                    <?php endif; ?>
                                    <?php if ($show_money && $has_cost_data): ?>
                                        <td class="px-3 py-1.5 text-xs text-theme-secondary"><?php echo e(format_money($src['profit'])); ?></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <?php
            // Separate open and closed tickets
            $open_tickets = array_filter($by_ticket, function ($t) { return empty($t['is_closed']); });
            $closed_tickets_report = array_filter($by_ticket, function ($t) { return !empty($t['is_closed']); });
            ?>
            <div class="card overflow-hidden">
                <div class="card-header">
                    <h3 class="font-semibold text-theme-primary"><?php echo e(t('Tickets')); ?></h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-theme-secondary">
                            <tr>
                                <th class="px-3 py-2 text-left th-label">
                                    <?php echo e(t('Ticket')); ?></th>
                                <th class="px-3 py-2 text-left th-label">
                                    <?php echo e(t('Company')); ?></th>
                                <?php if ($tags_supported): ?>
                                    <th class="px-3 py-2 text-left th-label">
                                        <?php echo e(t('Tags')); ?></th>
                                <?php endif; ?>
                                <th class="px-3 py-2 text-left th-label">
                                    <?php echo e(t('Time')); ?></th>
                                <?php if ($show_money): ?>
                                    <th class="px-3 py-2 text-left th-label">
                                        <?php echo e(t('Amount')); ?></th>
                                <?php endif; ?>
                                <?php if ($show_money && $has_cost_data): ?>
                                    <th class="px-3 py-2 text-left th-label">
                                        <?php echo e(t('Profit')); ?></th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php foreach ($open_tickets as $tid => $ticket): ?>
                                <tr>
                                    <td class="px-3 py-1.5 text-xs"><a href="<?php echo url('ticket', ['id' => $tid]); ?>" class="text-blue-600 hover:text-blue-800 hover:underline"><?php echo e($ticket['title']); ?></a></td>
                                    <td class="px-3 py-1.5 text-xs text-theme-secondary">
                                        <?php echo e($ticket['organization_name'] ?: t('-- No organization --')); ?></td>
                                    <?php if ($tags_supported): ?>
                                        <td class="px-3 py-1.5 text-xs">
                                            <?php $row_tags = function_exists('get_ticket_tags_array') ? get_ticket_tags_array($ticket['tags'] ?? '') : []; ?>
                                            <?php if (!empty($row_tags)): ?>
                                                <div class="flex flex-wrap gap-1">
                                                    <?php foreach (array_slice($row_tags, 0, 4) as $tag): ?>
                                                        <span class="inline-flex items-center px-1.5 py-0.5 fd-rounded-control tag-badge text-xs">#<?php echo e($tag); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-theme-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <td class="px-3 py-1.5 text-xs text-theme-secondary">
                                        <?php echo e(format_duration_minutes($ticket['minutes'])); ?></td>
                                    <?php if ($show_money): ?>
                                        <td class="px-3 py-1.5 text-xs text-theme-secondary">
                                            <?php echo e(format_money($ticket['billable_amount'])); ?></td>
                                    <?php endif; ?>
                                    <?php if ($show_money && $has_cost_data): ?>
                                        <td class="px-3 py-1.5 text-xs text-theme-secondary"><?php echo e(format_money($ticket['profit'])); ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php if (!empty($closed_tickets_report)): ?>
                        <tbody class="report-closed-ticket-toggle">
                            <tr class="cursor-pointer bg-theme-secondary" data-report-toggle-target="closed-tickets-report">
                                <?php $report_colspan = 3 + ($tags_supported ? 1 : 0) + ($show_money ? 1 : 0) + ($show_money && $has_cost_data ? 1 : 0); ?>
                                <td colspan="<?php echo $report_colspan; ?>" class="px-6 py-2 font-medium text-xs text-center text-gray-500 hover:text-gray-700">
                                    <?php echo e(t('Closed')); ?> (<?php echo count($closed_tickets_report); ?>)
                                </td>
                            </tr>
                        </tbody>
                        <tbody id="closed-tickets-report" class="hidden divide-y">
                            <?php foreach ($closed_tickets_report as $tid => $ticket): ?>
                                <tr class="report-muted-row">
                                    <td class="px-3 py-1.5 text-xs"><a href="<?php echo url('ticket', ['id' => $tid]); ?>" class="text-blue-600 hover:text-blue-800 hover:underline"><?php echo e($ticket['title']); ?></a></td>
                                    <td class="px-3 py-1.5 text-xs text-theme-secondary">
                                        <?php echo e($ticket['organization_name'] ?: t('-- No organization --')); ?></td>
                                    <?php if ($tags_supported): ?>
                                        <td class="px-3 py-1.5 text-xs">
                                            <?php $row_tags = function_exists('get_ticket_tags_array') ? get_ticket_tags_array($ticket['tags'] ?? '') : []; ?>
                                            <?php if (!empty($row_tags)): ?>
                                                <div class="flex flex-wrap gap-1">
                                                    <?php foreach (array_slice($row_tags, 0, 4) as $tag): ?>
                                                        <span class="inline-flex items-center px-1.5 py-0.5 fd-rounded-control tag-badge text-xs">#<?php echo e($tag); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-theme-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <td class="px-3 py-1.5 text-xs text-theme-secondary">
                                        <?php echo e(format_duration_minutes($ticket['minutes'])); ?></td>
                                    <?php if ($show_money): ?>
                                        <td class="px-3 py-1.5 text-xs text-theme-secondary">
                                            <?php echo e(format_money($ticket['billable_amount'])); ?></td>
                                    <?php endif; ?>
                                    <?php if ($show_money && $has_cost_data): ?>
                                        <td class="px-3 py-1.5 text-xs text-theme-secondary"><?php echo e(format_money($ticket['profit'])); ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
            <?php endif; ?>
