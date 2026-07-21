<?php
// Render partial. Variables are provided by report_render_admin_page().
?>
            <?php if (empty($by_week)): ?>
                <div class="card card-body p-8 text-center">
                    <div class="text-4xl mb-3 text-theme-muted">📅</div>
                    <div class="font-semibold mb-1 text-theme-primary"><?php echo e(t('No time entries found')); ?></div>
                    <div class="text-sm text-theme-muted"><?php echo e(t('Try adjusting the time range or filters above.')); ?></div>
                </div>
            <?php else: ?>
            <?php
            $weekly_max_minutes = $weekly_model['max_minutes'];
            $weekly_agent_ids = $weekly_model['agent_ids'];
            $weekly_agent_tone_map = $weekly_model['agent_tone_map'];
            $weekly_col_count = 3 + ($show_money ? 1 : 0) + ($show_money && $has_cost_data ? 1 : 0);
            ?>
            <div class="card overflow-hidden">
                <div class="card-header flex items-center justify-between">
                    <h3 class="font-semibold text-theme-primary"><?php echo e(t('Weekly')); ?></h3>
                    <?php if (count($weekly_agent_ids) > 1): ?>
                    <div class="flex flex-wrap items-center gap-3">
                        <?php foreach ($weekly_agent_ids as $aid): ?>
                            <?php $aname = ''; foreach ($by_week as $w) { if (isset($w['agents'][$aid])) { $aname = $w['agents'][$aid]['name']; break; } } ?>
                            <div class="flex items-center gap-1.5 text-xs text-theme-secondary">
                                <span class="report-agent-dot report-agent-dot--legend <?php echo e(report_tone_class($weekly_agent_tone_map[$aid] ?? 0)); ?>"></span>
                                <?php echo e($aname); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-theme-secondary">
                            <tr>
                                <th class="px-3 py-2 text-left th-label">
                                    <?php echo e(t('Week')); ?></th>
                                <th class="px-3 py-2 text-left th-label report-week-time-col">
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
                            <?php $wi = 0; foreach ($by_week as $wk => $week): $wi++; ?>
                                <tr class="cursor-pointer hover:bg-opacity-50 report-week-row" data-report-toggle-target="week-agents-<?php echo $wi; ?>">
                                    <td class="px-6 py-3">
                                        <div class="text-sm font-medium text-theme-primary"><?php echo e($week['label_formatted']); ?></div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <div class="text-sm text-theme-secondary">
                                            <?php echo e(format_duration_minutes($week['minutes'])); ?>
                                        </div>
                                        <?php if ($weekly_max_minutes > 0): ?>
                                        <div class="report-week-stack" title="<?php
                                            $parts = [];
                                            // Sort agents by minutes desc for this week
                                            $wa_sorted = $week['agents'];
                                            uasort($wa_sorted, fn($a, $b) => $b['minutes'] <=> $a['minutes']);
                                            foreach ($wa_sorted as $aid => $ag) {
                                                $parts[] = e($ag['name']) . ': ' . format_duration_minutes($ag['minutes']);
                                            }
                                            echo implode(' | ', $parts);
                                        ?>">
                                            <?php foreach ($wa_sorted as $aid => $ag):
                                                $seg_pct = $weekly_max_minutes > 0 ? ($ag['minutes'] / $weekly_max_minutes) * 100 : 0;
                                            ?>
                                            <div class="report-week-segment <?php echo e(report_width_class($seg_pct)); ?> <?php echo e(report_tone_class($weekly_agent_tone_map[$aid] ?? 0)); ?>"></div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-1.5 text-xs text-theme-secondary">
                                        <?php echo e(format_duration_minutes($week['billable_minutes'])); ?></td>
                                    <?php if ($show_money): ?>
                                        <td class="px-3 py-1.5 text-xs text-theme-secondary">
                                            <?php echo e(format_money($week['billable_amount'])); ?></td>
                                    <?php endif; ?>
                                    <?php if ($show_money && $has_cost_data): ?>
                                        <td class="px-3 py-1.5 text-xs text-theme-secondary"><?php echo e(format_money($week['profit'])); ?></td>
                                    <?php endif; ?>
                                </tr>
                                <?php if (count($week['agents']) > 0): ?>
                                <tr id="week-agents-<?php echo $wi; ?>" class="hidden">
                                    <td colspan="<?php echo $weekly_col_count; ?>" class="px-0 py-0">
                                        <div class="px-6 py-3 bg-theme-secondary">
                                            <div class="report-week-agent-grid">
                                                <?php
                                                $wa_sorted2 = $week['agents'];
                                                uasort($wa_sorted2, fn($a, $b) => $b['minutes'] <=> $a['minutes']);
                                                foreach ($wa_sorted2 as $aid => $ag):
                                                    $ag_pct = $week['minutes'] > 0 ? round(($ag['minutes'] / $week['minutes']) * 100) : 0;
                                                ?>
                                                <div class="flex items-center gap-2 px-3 py-2 fd-rounded-card bg-theme-primary">
                                                    <span class="report-agent-dot report-agent-dot--small <?php echo e(report_tone_class($weekly_agent_tone_map[$aid] ?? 0)); ?>"></span>
                                                    <div class="min-w-0 flex-1">
                                                        <div class="text-xs font-medium truncate text-theme-primary"><?php echo e($ag['name']); ?></div>
                                                        <div class="text-xs text-theme-muted">
                                                            <?php echo e(format_duration_minutes($ag['minutes'])); ?>
                                                            <span class="ml-1">(<?php echo $ag_pct; ?>%)</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
