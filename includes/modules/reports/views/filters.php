<?php
// Render partial. Variables are provided by report_render_admin_page().
?>
            <?php if ($has_active_filters): ?>
            <div class="report-filter-pills" id="report-filter-pills">
                <span class="report-filter-pills__label"><?php echo e(t('Filters')); ?>:</span>
                <?php foreach ($active_filters as $af): ?>
                    <?php
                    $remove_params = $_GET;
                    if ($af['type'] === 'time_range') {
                        $remove_params['time_range'] = 'this_month';
                        unset($remove_params['from_date'], $remove_params['to_date']);
                    } elseif ($af['type'] === 'org') {
                        $remove_params['organizations'] = array_values(array_diff($selected_orgs, [$af['id']]));
                        if (empty($remove_params['organizations'])) unset($remove_params['organizations']);
                    } elseif ($af['type'] === 'agent') {
                        $remove_params['agents'] = array_values(array_diff($selected_agents, [$af['id']]));
                        if (empty($remove_params['agents'])) unset($remove_params['agents']);
                    } elseif ($af['type'] === 'tag') {
                        $remaining_tags = array_filter($selected_tags, fn($t) => $t !== $af['value']);
                        if (!empty($remaining_tags)) {
                            $remove_params['tags'] = implode(', ', $remaining_tags);
                        } else {
                            unset($remove_params['tags']);
                        }
                    }
                    $remove_url = 'index.php?' . http_build_query($remove_params);
                    ?>
                    <?php if ($af['type'] === 'my_entries'): ?>
                    <span class="report-filter-pill">
                        <?php echo get_icon('user', 'w-3 h-3'); ?>
                        <?php echo e(t('My entries')); ?>: <?php echo e($af['label']); ?>
                    </span>
                    <?php else: ?>
                    <a href="<?php echo e($remove_url); ?>"
                       class="report-filter-pill"
                       title="<?php echo e(t('Remove filter')); ?>">
                        <?php echo e($af['label']); ?>
                        <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <details class="card mb-2" id="report-filters" <?php echo !$filter_collapsed ? 'open' : ''; ?>>
                <summary class="card-header report-filter-summary">
                    <div class="report-filter-summary__main">
                        <?php echo get_icon('sliders-horizontal', 'w-3.5 h-3.5'); ?>
                        <span class="report-filter-summary__title"><?php echo e(t('Filters')); ?></span>
                        <span class="report-filter-summary__text"><?php echo e($filter_summary_text); ?></span>
                    </div>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="rpt-chevron report-filter-summary__chevron"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </summary>
                <div class="report-filter-body">
                <form method="get">
                    <input type="hidden" name="page" value="admin">
                    <input type="hidden" name="section" value="reports">
                    <input type="hidden" name="tab" value="<?php echo e(is_admin() ? 'billing' : 'time'); ?>">

                    <!-- Row 1: All filter fields on one horizontal line -->
                    <div class="report-filter-grid">
                        <div class="report-filter-field">
                            <label class="block text-xs mb-1 font-medium text-theme-secondary"><?php echo e(t('Clients')); ?></label>
                            <div class="chip-select" id="cs-orgs">
                                <div class="chip-select__wrap" id="cs-orgs-wrap">
                                    <div class="chip-select__chips" id="cs-orgs-chips"></div>
                                    <input type="text" class="chip-select__input" id="cs-orgs-input"
                                           placeholder="<?php echo e(t('Type to filter...')); ?>" autocomplete="off">
                                </div>
                                <div class="chip-select__dropdown hidden" id="cs-orgs-dropdown"></div>
                                <div id="cs-orgs-hidden"></div>
                            </div>
                        </div>

                        <?php if (is_admin()): ?>
                        <div class="report-filter-field">
                            <label class="block text-xs mb-1 font-medium text-theme-secondary"><?php echo e(t('Agents')); ?></label>
                            <div class="chip-select" id="cs-agents">
                                <div class="chip-select__wrap" id="cs-agents-wrap">
                                    <div class="chip-select__chips" id="cs-agents-chips"></div>
                                    <input type="text" class="chip-select__input" id="cs-agents-input"
                                           placeholder="<?php echo e(t('Type to filter...')); ?>" autocomplete="off">
                                </div>
                                <div class="chip-select__dropdown hidden" id="cs-agents-dropdown"></div>
                                <div id="cs-agents-hidden"></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($tags_supported): ?>
                        <div class="report-filter-field">
                            <label class="block text-xs mb-1 font-medium text-theme-secondary">
                                <?php echo e(t('Tags')); ?>
                                <span class="report-filter-field__hint"><?php echo e(t('OR matching')); ?></span>
                            </label>
                            <input type="hidden" name="tags" id="rpt-tags-value" value="<?php echo e($selected_tags_csv); ?>">
                            <div class="chip-select" id="cs-tags">
                                <div class="chip-select__wrap" id="cs-tags-wrap">
                                    <div class="chip-select__chips" id="cs-tags-chips"></div>
                                    <input type="text" class="chip-select__input" id="cs-tags-input"
                                           placeholder="<?php echo e(t('Type to filter...')); ?>" autocomplete="off">
                                </div>
                                <div class="chip-select__dropdown hidden" id="cs-tags-dropdown"></div>
                                <div id="cs-tags-hidden"></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="report-filter-field">
                            <label class="block text-xs mb-1 font-medium text-theme-secondary"><?php echo e(t('Time range')); ?></label>
                            <select name="time_range" id="report-time-range" class="form-select w-full">
                                <option value="all" <?php echo $time_range === 'all' ? 'selected' : ''; ?>>
                                    <?php echo e(t('All time')); ?></option>
                                <option value="today" <?php echo $time_range === 'today' ? 'selected' : ''; ?>>
                                    <?php echo e(t('Today')); ?></option>
                                <option value="yesterday" <?php echo $time_range === 'yesterday' ? 'selected' : ''; ?>>
                                    <?php echo e(t('Yesterday')); ?></option>
                                <option value="last_7_days" <?php echo $time_range === 'last_7_days' ? 'selected' : ''; ?>>
                                    <?php echo e(t('Last 7 days')); ?></option>
                                <option value="last_30_days" <?php echo $time_range === 'last_30_days' ? 'selected' : ''; ?>>
                                    <?php echo e(t('Last 30 days')); ?></option>
                                <option value="this_week" <?php echo $time_range === 'this_week' ? 'selected' : ''; ?>>
                                    <?php echo e(t('This week')); ?></option>
                                <option value="last_week" <?php echo $time_range === 'last_week' ? 'selected' : ''; ?>>
                                    <?php echo e(t('Last week')); ?></option>
                                <option value="this_month" <?php echo $time_range === 'this_month' ? 'selected' : ''; ?>>
                                    <?php echo e(t('This month')); ?></option>
                                <option value="last_month" <?php echo $time_range === 'last_month' ? 'selected' : ''; ?>>
                                    <?php echo e(t('Last month')); ?></option>
                                <option value="this_quarter" <?php echo $time_range === 'this_quarter' ? 'selected' : ''; ?>>
                                    <?php echo e(t('This quarter')); ?></option>
                                <option value="last_quarter" <?php echo $time_range === 'last_quarter' ? 'selected' : ''; ?>>
                                    <?php echo e(t('Last quarter')); ?></option>
                                <option value="this_year" <?php echo $time_range === 'this_year' ? 'selected' : ''; ?>>
                                    <?php echo e(t('This year')); ?></option>
                                <option value="last_year" <?php echo $time_range === 'last_year' ? 'selected' : ''; ?>>
                                    <?php echo e(t('Last year')); ?></option>
                                <option value="custom" <?php echo $time_range === 'custom' ? 'selected' : ''; ?>>
                                    <?php echo e(t('Custom range')); ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- Row 2: Date hint, presets, show amounts, apply -->
                    <div class="report-filter-actions">
                        <?php if ($range_start && $range_end && $time_range !== 'custom' && $time_range !== 'all'): ?>
                        <span id="report-range-hint" class="report-range-hint">
                            <?php echo get_icon('calendar', 'w-3 h-3 inline-block'); ?>
                            <?php echo date('M j', strtotime($range_start)); ?> – <?php echo date('M j, Y', strtotime($range_end)); ?>
                        </span>
                        <?php endif; ?>

                        <?php if (is_admin()): ?>
                        <label class="report-toggle-label">
                            <input type="checkbox" name="show_money" value="1" class="fd-rounded-control" <?php echo $show_money ? 'checked' : ''; ?>>
                            <?php echo e(t('Show amounts')); ?>
                        </label>
                        <?php endif; ?>

                        <!-- Quick range presets -->
                        <div class="report-preset-list">
                            <?php
                            $quick_presets = [
                                'today' => t('Today'),
                                'this_week' => t('This week'),
                                'this_month' => t('This month'),
                                'last_month' => t('Last month'),
                                'this_quarter' => t('Q' . ceil(date('n') / 3)),
                            ];
                            foreach ($quick_presets as $preset_val => $preset_label): ?>
                            <button type="button"
                                class="range-preset-btn <?php echo $time_range === $preset_val ? 'is-active' : ''; ?>"
                                data-range="<?php echo e($preset_val); ?>"
                                data-report-range="<?php echo e($preset_val); ?>">
                                <?php echo e($preset_label); ?>
                            </button>
                            <?php endforeach; ?>
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm"><?php echo e(t('Update preview')); ?></button>
                    </div>

                    <!-- Custom date range (shown only when "Custom range" selected) -->
                    <div id="report-custom-range" class="report-custom-range <?php echo $time_range === 'custom' ? 'is-open' : ''; ?>">
                        <div>
                            <label class="block text-xs mb-1 font-medium text-theme-secondary"><?php echo e(t('From date')); ?></label>
                            <input type="date" name="from_date" value="<?php echo e($from_date); ?>" class="form-input">
                        </div>
                        <div>
                            <label class="block text-xs mb-1 font-medium text-theme-secondary"><?php echo e(t('To date')); ?></label>
                            <input type="date" name="to_date" value="<?php echo e($to_date); ?>" class="form-input">
                        </div>
                    </div>

                </form>
                </div>
            </details>
