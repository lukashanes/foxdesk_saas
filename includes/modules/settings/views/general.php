<?php
/** Focused settings section partial. Variables are supplied by settings-page-view-model.php. */
?>
        <!-- General Settings -->
        <div class="card card-body">
            <h3 class="text-xs font-semibold uppercase tracking-wide mb-2 text-theme-muted">
                <?php echo e(t('General settings')); ?>
            </h3>

            <form method="post" class="space-y-3">
                <?php echo csrf_field(); ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Application name')); ?></label>
                        <input type="text" name="app_name" value="<?php echo e($settings['app_name'] ?? 'FoxDesk'); ?>"
                            class="form-input">
                        <p class="text-xs mt-1 text-theme-muted">
                            <?php echo e(t('This name appears throughout the app.')); ?>
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Ticket ID prefix')); ?></label>
                        <input type="text" name="ticket_prefix" value="<?php echo e($settings['ticket_prefix'] ?? 'TK'); ?>"
                            maxlength="5" placeholder="TK" class="form-input">
                        <p class="text-xs mt-1 text-theme-muted">
                            <?php echo e(t('Example: TK-10001, REQ-10001 (letters only). Only affects new tickets — existing tickets keep their current prefix.')); ?></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3">
                    <div>
                        <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Login page welcome text')); ?></label>
                        <textarea name="login_welcome_text"
                            class="form-input h-20"><?php echo e($settings['login_welcome_text'] ?? 'Manage your tickets, track time, and support your customers with our corporate enterprise helpdesk.'); ?></textarea>
                        <p class="text-xs mt-1 text-theme-muted">
                            <?php echo e(t('This text appears on the login screen below the application name.')); ?></p>
                    </div>
                </div>

                <div class="max-w-sm">
                    <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Language')); ?></label>
                    <select name="app_language" class="form-select">
                        <option value="en" <?php echo ($settings['app_language'] ?? 'en') === 'en' ? 'selected' : ''; ?>>
                            <?php echo e(t('English')); ?>
                        </option>
                        <option value="cs" <?php echo ($settings['app_language'] ?? 'en') === 'cs' ? 'selected' : ''; ?>>
                            <?php echo e(t('Czech')); ?>
                        </option>
                        <option value="de" <?php echo ($settings['app_language'] ?? 'en') === 'de' ? 'selected' : ''; ?>>
                            <?php echo e(t('German')); ?>
                        </option>
                        <option value="it" <?php echo ($settings['app_language'] ?? 'en') === 'it' ? 'selected' : ''; ?>>
                            <?php echo e(t('Italian')); ?>
                        </option>
                        <option value="es" <?php echo ($settings['app_language'] ?? 'en') === 'es' ? 'selected' : ''; ?>>
                            <?php echo e(t('Spanish')); ?>
                        </option>
                    </select>
                    <p class="text-xs mt-1 text-theme-muted">
                        <?php echo e(t('Default interface language for all users. Users can override this in their profile.')); ?>
                    </p>
                </div>

                <div class="max-w-sm">
                    <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Time format')); ?></label>
                    <select name="time_format" class="form-select">
                        <option value="24" <?php echo ($settings['time_format'] ?? '24') === '24' ? 'selected' : ''; ?>>
                            <?php echo e(t('24-hour')); ?>
                        </option>
                        <option value="12" <?php echo ($settings['time_format'] ?? '24') === '12' ? 'selected' : ''; ?>>
                            <?php echo e(t('12-hour (AM/PM)')); ?>
                        </option>
                    </select>
                    <p class="text-xs mt-1 text-theme-muted">
                        <?php echo e(t('Applies to timestamps across the app.')); ?>
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
                    <div>
                        <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Currency')); ?></label>
                        <input type="text" name="currency" value="<?php echo e($settings['currency'] ?? 'CZK'); ?>"
                            class="form-input" maxlength="10">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Billing rounding (minutes)')); ?></label>
                        <select name="billing_rounding" class="form-select">
                            <?php
                            $rounding_value = (int) ($settings['billing_rounding'] ?? 1);
                            $rounding_options = [1, 5, 10, 15, 30, 60];
                            foreach ($rounding_options as $option):
                                ?>
                                <option value="<?php echo $option; ?>" <?php echo $rounding_value === $option ? 'selected' : ''; ?>>
                                    <?php echo $option; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs mt-1 text-theme-muted">
                            <?php echo e(t('Optional invoice rounding. Work and agent reports always show exact tracked minutes.')); ?>
                        </p>
                    </div>
                </div>

                <!-- Time Tracking Alerts Section -->
                <div class="border-t pt-3 mt-3">
                    <h4 class="font-semibold mb-4 text-theme-primary">
                        <?php echo e(t('Time tracking alerts')); ?>
                    </h4>

                    <div class="space-y-4">
                        <div>
                            <label class="flex items-center space-x-3">
                                <input type="checkbox" name="timer_alert_enabled" <?php echo ($settings['timer_alert_enabled'] ?? '0') === '1' ? 'checked' : ''; ?>
                                    class="w-5 h-5 fd-rounded-control text-blue-500 focus:ring-blue-500">
                                <span class="font-medium text-theme-primary"><?php echo e(t('Enable long timer alerts')); ?></span>
                            </label>
                            <p class="text-sm ml-8 text-theme-muted">
                                <?php echo e(t('Notify users when their timer has been running for too long.')); ?>
                            </p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-xl">
                            <div>
                                <label class="block text-sm font-medium mb-1 text-theme-secondary"><?php echo e(t('Alert threshold (hours)')); ?></label>
                                <input type="number" name="timer_alert_hours"
                                    value="<?php echo e($settings['timer_alert_hours'] ?? '3'); ?>" min="1" max="24"
                                    class="form-input">
                                <p class="text-xs mt-1 text-theme-muted">
                                    <?php echo e(t('Send alert when timer exceeds this duration.')); ?>
                                </p>
                            </div>
                        </div>

                        <div>
                            <label class="flex items-center space-x-3">
                                <input type="checkbox" name="timer_alert_email" <?php echo ($settings['timer_alert_email'] ?? '1') === '1' ? 'checked' : ''; ?>
                                    class="w-4 h-4 fd-rounded-control text-blue-500 focus:ring-blue-500">
                                <span
                                    class="text-theme-secondary"><?php echo e(t('Send email notification to user')); ?></span>
                            </label>
                            <p class="text-xs ml-7 text-theme-muted">
                                <?php echo e(t('User will receive an email reminder to stop their timer.')); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <button type="submit" name="save_general" class="btn btn-primary mt-3">
                    <?php echo e(t('Save settings')); ?>
                </button>
            </form>
        </div>

        <!-- Favicon Upload Section -->
        <div class="card card-body mt-3">
            <h3 class="font-semibold mb-4 text-theme-primary"><?php echo e(t('Favicon')); ?></h3>
            <?php $current_favicon = $settings['favicon'] ?? ''; ?>
            <?php if ($current_favicon): ?>
                <div class="flex items-center gap-3 p-3 fd-rounded-card mb-4 w-fit bg-theme-secondary">
                    <img src="<?php echo e($current_favicon); ?>" alt="Current favicon" class="w-8 h-8">
                    <span class="text-sm text-theme-secondary"><?php echo e(t('Current favicon')); ?></span>
                    <form method="post" class="inline ml-2">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="remove_favicon" value="1">
                        <button type="submit" name="save_favicon" class="text-red-500 hover:text-red-700 text-sm">
                            <?php echo get_icon('trash', 'w-4 h-4'); ?>
                        </button>
                    </form>
                </div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" id="favicon-form">
                <?php echo csrf_field(); ?>
                <div id="favicon-upload-zone"
                    class="fd-rounded-card p-4 cursor-pointer border-2 border-dashed hover:border-blue-300 transition-colors max-w-md border-theme-light">
                    <input type="file" name="favicon" id="favicon-file-input"
                        accept=".ico,.png,.gif,image/x-icon,image/png,image/gif" class="hidden">
                    <div class="flex items-center gap-3">
                        <span
                            class="text-theme-muted"><?php echo get_icon('cloud-upload-alt', 'text-2xl flex-shrink-0'); ?></span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-theme-secondary">
                                <span class="text-blue-500 font-medium"><?php echo e(t('Click')); ?></span>
                                <?php echo e(t('or drag file')); ?>
                            </p>
                            <p class="text-xs mt-0.5 text-theme-muted" id="favicon-file-name">
                                <?php echo e(t('No file selected')); ?>
                            </p>
                            <p class="text-xs text-theme-muted">
                                <?php echo e(t('Recommended: 32x32 or 16x16 pixels. Formats: ICO, PNG, GIF')); ?>
                            </p>
                        </div>
                        <button type="submit" name="save_favicon" class="btn btn-primary flex-shrink-0"
                            id="favicon-upload-btn" disabled>
                            <?php echo get_icon('upload', 'mr-1'); ?>     <?php echo e(t('Upload')); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- App Logo Upload Section -->
        <div class="card card-body mt-3">
            <h3 class="font-semibold mb-4 text-theme-primary"><?php echo e(t('App logo')); ?></h3>
            <?php $current_app_logo = get_setting('app_logo', ''); ?>
            <?php if ($current_app_logo): ?>
                <div class="flex items-center gap-3 p-3 fd-rounded-card mb-4 w-fit bg-theme-secondary">
                    <img src="<?php echo e(upload_url($current_app_logo)); ?>" alt="Current logo"
                        class="w-10 h-10 fd-rounded-pill object-cover">
                    <span class="text-sm text-theme-secondary"><?php echo e(t('Current logo')); ?></span>
                    <form method="post" class="inline ml-2">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="remove_app_logo" value="1">
                        <button type="submit" name="save_app_logo" class="text-red-500 hover:text-red-700 text-sm">
                            <?php echo get_icon('trash', 'w-4 h-4'); ?>
                        </button>
                    </form>
                </div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" id="app-logo-form">
                <?php echo csrf_field(); ?>
                <div id="app-logo-upload-zone"
                    class="fd-rounded-card p-4 cursor-pointer border-2 border-dashed hover:border-blue-300 transition-colors max-w-md border-theme-light">
                    <input type="file" name="app_logo" id="app-logo-file-input"
                        accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml" class="hidden">
                    <div class="flex items-center gap-3">
                        <span
                            class="text-theme-muted"><?php echo get_icon('cloud-upload-alt', 'text-2xl flex-shrink-0'); ?></span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-theme-secondary">
                                <span class="text-blue-500 font-medium"><?php echo e(t('Click')); ?></span>
                                <?php echo e(t('or drag file')); ?>
                            </p>
                            <p class="text-xs mt-0.5 text-theme-muted" id="app-logo-file-name">
                                <?php echo e(t('No file selected')); ?>
                            </p>
                            <p class="text-xs text-theme-muted">
                                <?php echo e(t('Square image recommended. Formats: JPG, PNG, GIF, WebP, SVG. Max 2 MB.')); ?>
                            </p>
                        </div>
                        <button type="submit" name="save_app_logo" class="btn btn-primary flex-shrink-0"
                            id="app-logo-upload-btn" disabled>
                            <?php echo get_icon('upload', 'mr-1'); ?>     <?php echo e(t('Upload')); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const uploadZone = document.getElementById('favicon-upload-zone');
            const fileInput = document.getElementById('favicon-file-input');
            const fileName = document.getElementById('favicon-file-name');
            const uploadBtn = document.getElementById('favicon-upload-btn');

            if (!uploadZone || !fileInput) return;

            uploadZone.addEventListener('click', function (e) {
                if (e.target !== uploadBtn && !uploadBtn.contains(e.target)) {
                    fileInput.click();
                }
            });

            fileInput.addEventListener('change', function () {
                if (this.files.length > 0) {
                    fileName.textContent = this.files[0].name;
                    uploadBtn.disabled = false;
                } else {
                    fileName.textContent = '<?php echo e(t('No file selected')); ?>';
                    uploadBtn.disabled = true;
                }
            });

            uploadZone.addEventListener('dragover', function (e) {
                e.preventDefault();
                uploadZone.classList.add('border-blue-400', 'bg-blue-50');
            });

            uploadZone.addEventListener('dragleave', function (e) {
                e.preventDefault();
                uploadZone.classList.remove('border-blue-400', 'bg-blue-50');
            });

            uploadZone.addEventListener('drop', function (e) {
                e.preventDefault();
                uploadZone.classList.remove('border-blue-400', 'bg-blue-50');

                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    const file = files[0];
                    const validTypes = ['.ico', '.png', '.gif'];
                    const ext = file.name.toLowerCase().slice(file.name.lastIndexOf('.'));
                    if (validTypes.includes(ext)) {
                        fileInput.files = files;
                        fileName.textContent = file.name;
                        uploadBtn.disabled = false;
                    } else {
                        alert('<?php echo e(t('Please select an ICO, PNG, or GIF file')); ?>');
                    }
                }
            });

            // App logo upload zone
            const logoZone = document.getElementById('app-logo-upload-zone');
            const logoInput = document.getElementById('app-logo-file-input');
            const logoFileName = document.getElementById('app-logo-file-name');
            const logoBtn = document.getElementById('app-logo-upload-btn');

            if (logoZone && logoInput) {
                logoZone.addEventListener('click', function (e) {
                    if (e.target !== logoBtn && !logoBtn.contains(e.target)) {
                        logoInput.click();
                    }
                });

                logoInput.addEventListener('change', function () {
                    if (this.files.length > 0) {
                        logoFileName.textContent = this.files[0].name;
                        logoBtn.disabled = false;
                    } else {
                        logoFileName.textContent = '<?php echo e(t('No file selected')); ?>';
                        logoBtn.disabled = true;
                    }
                });

                logoZone.addEventListener('dragover', function (e) {
                    e.preventDefault();
                    logoZone.classList.add('border-blue-400');
                });

                logoZone.addEventListener('dragleave', function (e) {
                    e.preventDefault();
                    logoZone.classList.remove('border-blue-400');
                });

                logoZone.addEventListener('drop', function (e) {
                    e.preventDefault();
                    logoZone.classList.remove('border-blue-400');
                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        const file = files[0];
                        const validTypes = ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.svg'];
                        const ext = file.name.toLowerCase().slice(file.name.lastIndexOf('.'));
                        if (validTypes.includes(ext)) {
                            logoInput.files = files;
                            logoFileName.textContent = file.name;
                            logoBtn.disabled = false;
                        } else {
                            alert('<?php echo e(t('Please select a JPG, PNG, GIF, WebP, or SVG file')); ?>');
                        }
                    }
                });
            }
        });
    </script>
