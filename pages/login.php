<?php
/**
 * Login Page — with optional TOTP 2FA
 */

// Redirect if already logged in
if (is_logged_in()) {
    $redirect_page = function_exists('foxdesk_authenticated_home_page') ? foxdesk_authenticated_home_page() : 'dashboard';
    header('Location: index.php?page=' . $redirect_page);
    exit;
}

// If the PHP session was lost (for example after an app/container restart),
// restore the user from the persistent remember-me cookie before showing login.
$remember_cookie_name = function_exists('foxdesk_remember_cookie_name')
    ? foxdesk_remember_cookie_name()
    : 'foxdesk_remember';
if (empty($_SESSION['2fa_pending']) && !empty($_COOKIE[$remember_cookie_name]) && validate_remember_token()) {
    $redirect_page = function_exists('foxdesk_authenticated_home_page') ? foxdesk_authenticated_home_page() : 'dashboard';
    header('Location: index.php?page=' . $redirect_page);
    exit;
}

$settings = get_settings();
$app_name = $settings['app_name'] ?? (defined('APP_NAME') ? APP_NAME : 'FoxDesk');
$login_brand_name = function_exists('foxdesk_is_platform_host') && foxdesk_is_platform_host()
    ? 'FoxDesk Platform'
    : (foxdesk_is_app_host() ? 'FoxDesk Cloud' : $app_name);

$error = '';
$info_message = '';
$show_2fa_form = !empty($_SESSION['2fa_pending']);
$workspace_login_url = function_exists('foxdesk_workspace_url')
    ? foxdesk_workspace_url('index.php?page=login')
    : 'index.php?page=login';
if (isset($_GET['platform_login_rejected']) && function_exists('foxdesk_is_platform_host') && foxdesk_is_platform_host()) {
    $error = 'This address is for FoxDesk platform administrators. Use the workspace login instead: ' . $workspace_login_url;
}
$current_lang = get_app_language();
$lang_options = [
    'en' => t('English'),
    'cs' => t('Czech'),
    'de' => t('German'),
    'it' => t('Italian'),
    'es' => t('Spanish')
];
$lang_params = ['page' => 'login'];
if (isset($_GET['reset'])) {
    $lang_params['reset'] = $_GET['reset'];
}
if (isset($_GET['sent'])) {
    $lang_params['sent'] = $_GET['sent'];
}

// Cancel 2FA — user clicked "Back to sign in"
if (isset($_GET['cancel2fa'])) {
    unset($_SESSION['2fa_pending']);
    header('Location: index.php?page=login');
    exit;
}

// ─── Phase 2: TOTP code verification ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_2fa'])) {
    if (!csrf_is_valid()) {
        $error = t('Security check failed. Please try again.');
        $show_2fa_form = true;
    } elseif (empty($_SESSION['2fa_pending'])) {
        $error = t('Session expired. Please sign in again.');
    } else {
        $pending = $_SESSION['2fa_pending'];

        // Check if 2FA session expired (15 min max)
        if (time() - $pending['timestamp'] > 900) {
            unset($_SESSION['2fa_pending']);
            $error = t('Verification expired. Please sign in again.');
        } else {
            $rate_key_2fa = '2fa_verify_' . $pending['user_id'];

            if (rate_limit_is_blocked($rate_key_2fa, 5, 300)) {
                unset($_SESSION['2fa_pending']);
                log_security_event('2fa_brute_force_blocked', $pending['user_id']);
                $error = t('Too many attempts. Please sign in again.');
            } else {
                require_once BASE_PATH . '/includes/totp.php';
                $submitted_code = trim($_POST['code'] ?? '');

                $user_row = db_fetch_one("SELECT totp_secret, totp_backup_codes FROM users WHERE id = ?", [$pending['user_id']]);

                $valid = false;
                if ($user_row && !empty($user_row['totp_secret'])) {
                    $valid = totp_verify($user_row['totp_secret'], $submitted_code)
                          || verify_backup_code($pending['user_id'], $submitted_code);
                }

                if ($valid) {
                    // 2FA passed — complete login
                    rate_limit_clear($rate_key_2fa);
                    rate_limit_clear(function_exists('rate_limit_key') ? rate_limit_key('login', $pending['user_email'] ?? '') : 'login');
                    session_regenerate_id(true);
                    $_SESSION = [];
                    $_SESSION['user_id']    = $pending['user_id'];
                    $_SESSION['user_email'] = $pending['user_email'];
                    $_SESSION['user_name']  = $pending['user_name'];
                    $_SESSION['user_role']  = $pending['user_role'];
                    $_SESSION['lang']       = $pending['lang'];

                    if ($pending['remember_me']) {
                        set_remember_token($pending['user_id']);
                    }
                    log_security_event('2fa_verified', $pending['user_id']);
                    $redirect_page = function_exists('foxdesk_authenticated_home_page') ? foxdesk_authenticated_home_page() : 'dashboard';
                    header('Location: index.php?page=' . $redirect_page);
                    exit;
                } else {
                    rate_limit_record($rate_key_2fa, 300);
                    log_security_event('2fa_failed', $pending['user_id']);
                    $error = t('Invalid or expired code. Please try again.');
                    $show_2fa_form = true;
                }
            }
        }
    }
}

// ─── Phase 1: Handle login form ─────────────────────────────────────────────
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['verify_2fa'])) {
    if (!csrf_is_valid()) {
        $error = t('Security check failed. Please try again.');
    } elseif (!require_turnstile_for_public_form('login')) {
        $error = t('Bot protection check failed. Please try again.');
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $rate_key = function_exists('rate_limit_key') ? rate_limit_key('login', $email) : 'login';

        if (rate_limit_is_blocked($rate_key, 5, 900)) {
            $error = t('Too many attempts. Please wait and try again.');
        } elseif (empty($email) || empty($password)) {
            $error = t('Enter email and password.');
        } elseif (login($email, $password)) {
            // Password correct — check if 2FA is needed
            require_once BASE_PATH . '/includes/totp.php';
            ensure_totp_columns();
            $logged_user = current_user();

            if (function_exists('foxdesk_is_platform_host') && foxdesk_is_platform_host() && !is_platform_admin($logged_user)) {
                logout();
                $error = 'This address is for FoxDesk platform administrators. Use the workspace login instead: ' . $workspace_login_url;
                log_security_event('platform_login_rejected', null, 'email=' . $email);
            } else {

                $user_has_2fa = is_2fa_enabled($logged_user);
                $role_requires_2fa = is_2fa_required_for_role($logged_user['role']);

                if ($user_has_2fa || $role_requires_2fa) {
                    // Park the session — user is NOT logged in yet
                    $pending = [
                        'user_id'      => $_SESSION['user_id'],
                        'user_email'   => $_SESSION['user_email'],
                        'user_name'    => $_SESSION['user_name'],
                        'user_role'    => $_SESSION['user_role'],
                        'lang'         => $_SESSION['lang'] ?? 'en',
                        'remember_me'  => !empty($_POST['remember_me']),
                        'totp_enabled' => $user_has_2fa,
                        'timestamp'    => time(),
                    ];

                    // Wipe the login session — is_logged_in() must return false
                    $_SESSION = [];
                    session_regenerate_id(true);
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    if ($user_has_2fa) {
                        // User has 2FA set up → show code entry form
                        $_SESSION['2fa_pending'] = $pending;
                        log_security_event('2fa_challenge', $pending['user_id']);
                        $show_2fa_form = true;
                    } else {
                        // 2FA required by role but user hasn't set it up → force setup
                        $_SESSION['user_id']    = $pending['user_id'];
                        $_SESSION['user_email'] = $pending['user_email'];
                        $_SESSION['user_name']  = $pending['user_name'];
                        $_SESSION['user_role']  = $pending['user_role'];
                        $_SESSION['lang']       = $pending['lang'];
                        $_SESSION['2fa_setup_required'] = true;

                        if (!empty($pending['remember_me'])) {
                            set_remember_token($pending['user_id']);
                        }
                        rate_limit_clear($rate_key);
                        header('Location: index.php?page=profile&setup2fa=1');
                        exit;
                    }
                } else {
                    // No 2FA needed — complete login normally
                    if (!empty($_POST['remember_me'])) {
                        set_remember_token($_SESSION['user_id']);
                    }
                    rate_limit_clear($rate_key);
                    $redirect_page = function_exists('foxdesk_authenticated_home_page') ? foxdesk_authenticated_home_page() : 'dashboard';
                    header('Location: index.php?page=' . $redirect_page);
                    exit;
                }
            }
        } else {
            rate_limit_record($rate_key, 900);
            log_security_event('login_failed', null, 'email=' . $email);
            $error = t('Invalid email or password.');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo e(get_app_language()); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e(t('Sign in')); ?> - <?php echo e($login_brand_name); ?></title>
    <link href="tailwind.min.css?v=<?php echo e((string) APP_VERSION); ?>" rel="stylesheet">
    <link href="assets/css/theme.min.css?v=<?php echo e((string) (@filemtime(BASE_PATH . '/assets/css/theme.min.css') ?: APP_VERSION)); ?>" rel="stylesheet">
    <?php echo turnstile_script_tag(); ?>
    <script>
        // Apply theme immediately to prevent flash
        (function () {
            const saved = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', saved);
        })();
    </script>
</head>

<body class="split-layout">
    <!-- Left Half: Dark Brand Area -->
    <div class="split-left login-brand-panel">
        <div class="login-brand-bg">
            <div class="login-brand-gradient"></div>
        </div>
        <div class="login-brand-content">
            <?php $app_logo = get_setting('app_logo', ''); ?>
            <?php if ($app_logo): ?>
                <img src="<?php echo e(upload_url($app_logo)); ?>" alt="<?php echo e($login_brand_name); ?>"
                    class="login-logo-image">
            <?php else: ?>
                <div class="login-logo-fallback">
                    <span>F</span>
                </div>
            <?php endif; ?>
            <h1 class="login-brand-title"><?php echo e(t('Welcome to {app}', ['app' => $login_brand_name])); ?></h1>
            <p class="login-brand-subtitle">
                <?php echo (function_exists('foxdesk_is_platform_host') && foxdesk_is_platform_host())
                    ? e('Sign in to operate hosted FoxDesk workspaces.')
                    : (foxdesk_is_app_host()
                    ? e('Sign in to your hosted FoxDesk workspace.')
                    : e($settings['login_welcome_text'] ?? t('Manage your tickets, track time, and support your customers with our corporate enterprise helpdesk.'))); ?>
            </p>
        </div>
    </div>

    <!-- Right Half: Form Area -->
    <div class="split-right login-form-panel">
        <!-- Theme Toggle -->
        <button onclick="toggleTheme()"
            class="theme-toggle login-theme-toggle"
            title="<?php echo e(t('Toggle theme')); ?>">
            <svg class="theme-toggle__icon theme-toggle__icon--light w-5 h-5 text-slate-500" fill="none"
                stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z">
                </path>
            </svg>
            <svg class="theme-toggle__icon theme-toggle__icon--dark w-5 h-5 text-slate-400" fill="none"
                stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
            </svg>
        </button>

        <div class="login-form-wrap animate-fade-in">

            <?php if (!empty($show_2fa_form) && !empty($_SESSION['2fa_pending'])): ?>
                <?php
                    // Mask email: show first 3 chars + *** + @domain
                    $pending_email = $_SESSION['2fa_pending']['user_email'];
                    $at_pos = strpos($pending_email, '@');
                    $masked_email = ($at_pos > 3
                        ? substr($pending_email, 0, 3) . '***'
                        : substr($pending_email, 0, 1) . '***')
                        . substr($pending_email, $at_pos);
                ?>
                <!-- ═══ 2FA Code Entry Form ═══ -->
                <div class="text-left mb-8">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="login-icon-shell">
                            <svg class="login-icon-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </div>
                        <div>
                            <h2 class="login-form-title login-form-title--compact">
                                <?php echo e(t('Two-factor authentication')); ?>
                            </h2>
                        </div>
                    </div>
                    <p class="login-form-copy text-sm">
                        <?php echo e(t('Enter the 6-digit code from your authenticator app.')); ?>
                    </p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error mb-6 animate-fade-in text-sm fd-rounded-card p-3 bg-red-50 text-red-600 border border-red-200 dark:bg-red-900/30 dark:border-red-800/50 dark:text-red-400">
                        <?php echo e($error); ?>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <?php echo csrf_field(); ?>
                    <div class="space-y-5">
                        <div>
                            <label class="login-label block text-sm font-medium mb-1.5">
                                <?php echo e(t('Verification code')); ?>
                            </label>
                            <input type="text" name="code" maxlength="9" pattern="[a-zA-Z0-9\-]{6,9}"
                                inputmode="numeric" autocomplete="one-time-code"
                                class="form-input login-field w-full fd-rounded-card border-slate-300 dark:border-slate-700 bg-transparent px-4 py-3 text-center text-2xl tracking-[0.3em] font-mono focus:border-[#3c50e0] focus:ring-1 focus:ring-[#3c50e0]"
                                placeholder="000000" required autofocus>
                            <p class="login-form-copy text-xs mt-1.5">
                                <?php echo e(t('Or enter a backup code (e.g. xxxx-xxxx)')); ?>
                            </p>
                        </div>
                    </div>

                    <button type="submit" name="verify_2fa" value="1"
                        class="btn btn-primary login-submit w-full mt-6 py-2.5 text-base fd-rounded-card transition-transform active:scale-[0.98]">
                        <?php echo e(t('Verify')); ?>
                    </button>
                </form>

                <div class="flex items-center justify-center mt-6">
                    <a href="<?php echo url('login', ['cancel2fa' => '1']); ?>"
                        class="login-muted-link text-sm transition-colors">
                        &larr; <?php echo e(t('Back to sign in')); ?>
                    </a>
                </div>

            <?php else: ?>
                <!-- ═══ Standard Login Form ═══ -->
                <div class="text-left mb-8">
                    <h2 class="login-form-title text-3xl font-bold mb-2"><?php echo e(t('Sign in')); ?>
                    </h2>
                    <p class="login-form-copy"><?php echo e((function_exists('foxdesk_is_platform_host') && foxdesk_is_platform_host()) ? 'Use your FoxDesk platform admin account.' : (foxdesk_is_app_host() ? 'Use the account from your FoxDesk workspace.' : t('Sign in to your account'))); ?></p>
                </div>

                <?php if ($error): ?>
                    <div
                        class="alert alert-error mb-6 animate-fade-in text-sm fd-rounded-card p-3 bg-red-50 text-red-600 border border-red-200 dark:bg-red-900/30 dark:border-red-800/50 dark:text-red-400">
                        <?php echo e($error); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['reset']) && $_GET['reset'] === 'success'): ?>
                    <div
                        class="alert alert-success mb-6 animate-fade-in text-sm fd-rounded-card p-3 bg-green-50 text-green-600 border border-green-200 dark:bg-green-900/30 dark:border-green-800/50 dark:text-green-400">
                        <?php echo e(t('Password updated successfully. You can sign in now.')); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['sent']) && $_GET['sent'] === '1'): ?>
                    <div
                        class="alert alert-info mb-6 animate-fade-in text-sm fd-rounded-card p-3 bg-blue-50 text-blue-600 border border-blue-200 dark:bg-blue-900/30 dark:border-blue-800/50 dark:text-blue-400">
                        <?php echo e(t('If an account exists for this email, a reset link has been sent.')); ?>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <?php echo csrf_field(); ?>
                    <div class="space-y-5">
                        <div>
                            <label class="login-label block text-sm font-medium mb-1.5"><?php echo e(t('Email')); ?></label>
                            <input type="email" name="email" value="<?php echo e($_POST['email'] ?? ''); ?>"
                                class="form-input login-field w-full fd-rounded-card border-slate-300 dark:border-slate-700 bg-transparent px-4 py-2.5 focus:border-[#3c50e0] focus:ring-1 focus:ring-[#3c50e0]"
                                autocomplete="username" inputmode="email" autocapitalize="none" required autofocus>
                        </div>
                        <div>
                            <label class="login-label block text-sm font-medium mb-1.5"><?php echo e(t('Password')); ?></label>
                            <input type="password" name="password"
                                class="form-input login-field w-full fd-rounded-card border-slate-300 dark:border-slate-700 bg-transparent px-4 py-2.5 focus:border-[#3c50e0] focus:ring-1 focus:ring-[#3c50e0]"
                                autocomplete="current-password" required>
                        </div>
                    </div>

                    <div class="flex items-center justify-between mt-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="remember_me" value="1" checked
                                class="form-checkbox login-checkbox fd-rounded-control">
                            <span class="login-form-secondary text-sm"><?php echo e(t('Remember me')); ?></span>
                        </label>
                        <a href="<?php echo url('forgot-password', ['lang' => $current_lang]); ?>"
                            class="login-link text-sm font-medium transition-colors hover:text-[#3243bd]">
                            <?php echo e(t('Forgot password?')); ?>
                        </a>
                    </div>

                    <?php echo turnstile_widget('login'); ?>

                    <button type="submit"
                        class="btn btn-primary login-submit w-full mt-6 py-2.5 text-base fd-rounded-card transition-transform active:scale-[0.98]">
                        <?php echo e(t('Sign in')); ?>
                    </button>
                </form>

                <div class="login-form-copy text-center text-sm mt-5">
                    New to FoxDesk?
                    <a href="<?php echo url('signup'); ?>" class="login-link font-medium">Create a new FoxDesk</a>
                </div>

                <form method="get" class="login-language-form flex flex-col items-center justify-center gap-2 mt-8">
                    <?php foreach ($lang_params as $key => $value): ?>
                        <input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e($value); ?>">
                    <?php endforeach; ?>
                    <div class="flex items-center justify-center gap-3 px-3 py-1.5 mt-4 mx-auto max-w-[fit-content]">
                        <div class="login-language-label text-[11px] uppercase tracking-wider font-semibold">
                            <?php echo e(t('Language')); ?></div>
                        <select id="lang-select" name="lang"
                            class="login-language-select bg-transparent text-sm border-none shadow-none focus:ring-0 cursor-pointer p-0 font-medium text-slate-700 dark:text-slate-300"
                            onchange="this.form.submit()">
                            <?php foreach ($lang_options as $code => $label): ?>
                                <option value="<?php echo e($code); ?>" <?php echo $code === $current_lang ? 'selected' : ''; ?>>
                                    <?php echo e($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            <?php endif; ?>

            <p class="login-footer text-center text-xs mt-8">
                &copy; <?php echo date('Y'); ?> <?php echo e($login_brand_name); ?>
            </p>
        </div>
    </div>

    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const current = html.getAttribute('data-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
        }
    </script>
</body>

</html>
