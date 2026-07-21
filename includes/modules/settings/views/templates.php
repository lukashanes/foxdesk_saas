<?php
/** Focused settings section partial. Variables are supplied by settings-page-view-model.php. */
?>
        <!-- Email Templates -->
        <?php
        $template_info = settings_email_template_catalog();
        $display_templates = settings_email_template_display_rows($templates, $template_lang);
        ?>

        <div class="mb-2 flex justify-between items-center">
            <h3 class="font-semibold text-theme-primary"><?php echo e(t('Email Templates')); ?></h3>

            <form action="" method="get" class="flex items-center space-x-2">
                <input type="hidden" name="page" value="admin">
                <input type="hidden" name="section" value="settings">
                <input type="hidden" name="tab" value="templates">

                <label class="text-sm text-theme-secondary"><?php echo e(t('Language:')); ?></label>
                <select name="lang" onchange="this.form.submit()" class="form-select form-select-sm w-auto">
                    <option value="en" <?php echo $template_lang === 'en' ? 'selected' : ''; ?>><?php echo e(t('English')); ?>
                    </option>
                    <option value="cs" <?php echo $template_lang === 'cs' ? 'selected' : ''; ?>><?php echo e(t('Czech')); ?>
                    </option>
                    <option value="de" <?php echo $template_lang === 'de' ? 'selected' : ''; ?>><?php echo e(t('German')); ?>
                    </option>
                    <option value="it" <?php echo $template_lang === 'it' ? 'selected' : ''; ?>><?php echo e(t('Italian')); ?>
                    </option>
                    <option value="es" <?php echo $template_lang === 'es' ? 'selected' : ''; ?>><?php echo e(t('Spanish')); ?>
                    </option>
                </select>
            </form>
        </div>
        <div class="space-y-3">
            <?php foreach ($display_templates as $template):
                $info = $template_info[$template['template_key']] ?? null;
                $required_variables = function_exists('settings_email_template_required_variables')
                    ? (settings_email_template_required_variables()[$template['template_key']] ?? [])
                    : [];
                ?>
                <div class="admin-list-card">
                    <form method="post">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="template_key" value="<?php echo e($template['template_key']); ?>">
                        <input type="hidden" name="template_lang" value="<?php echo e($template_lang); ?>">

                        <div class="px-6 py-3 border-b bg-theme-secondary">
                            <div>
                                <h4 class="font-semibold text-theme-primary">
                                    <?php echo e($info['name'] ?? $template['template_key']); ?>
                                </h4>
                                <?php if ($info): ?>
                                    <p class="text-sm text-theme-muted"><?php echo e($info['description']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="p-6">
                            <?php if ($info && !empty($info['variables'])): ?>
                                <div class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 fd-rounded-card">
                                    <div class="text-sm font-medium text-blue-800 mb-2"><?php echo e(t('Available variables:')); ?>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <?php foreach ($info['variables'] as $var => $desc): ?>
                                            <span class="inline-flex items-center border border-blue-200 fd-rounded-control px-2 py-1 text-xs bg-theme-app"
                                                title="<?php echo e($desc); ?>">
                                                <code class="text-blue-600"><?php echo e($var); ?></code>
                                                <span class="ml-1 text-theme-muted">- <?php echo e($desc); ?></span>
                                                <?php if (in_array($var, $required_variables, true)): ?>
                                                    <span class="ml-2 fd-rounded-control bg-blue-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-blue-700">
                                                        <?php echo e(t('Required')); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Email subject')); ?></label>
                                    <input type="text" name="template_subject" value="<?php echo e($template['subject']); ?>"
                                        class="form-input">
                                    <p class="text-xs mt-1 text-theme-muted">
                                        <?php echo e(t('You can use variables in the subject, e.g. {ticket_title}.')); ?>
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Email body')); ?></label>
                                    <textarea name="template_body" rows="8"
                                        class="form-textarea font-mono text-sm"><?php echo e($template['body']); ?></textarea>
                                </div>
                            </div>
                            <div class="mt-4 flex justify-end">
                                <button type="submit" name="save_template" class="btn btn-primary btn-sm">
                                    <?php echo get_icon('save', 'mr-1'); ?>         <?php echo e(t('Save')); ?>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
