<?php
/**
 * Public SaaS signup: starts a trial from one verified email address.
 */

require_once BASE_PATH . '/includes/signup-functions.php';

if (is_logged_in()) {
    $redirect_page = function_exists('foxdesk_authenticated_home_page')
        ? foxdesk_authenticated_home_page()
        : (is_platform_admin() ? 'platform' : 'dashboard');
    header('Location: index.php?page=' . $redirect_page);
    exit;
}

$page_title = 'Start FoxDesk';
$error = '';
$success = false;
$values = [
    'email' => trim((string) ($_POST['email'] ?? '')),
];

if (isset($_GET['token'])) {
    try {
        $result = signup_complete_magic_link((string) $_GET['token']);
        if (($result['status'] ?? '') === 'created') {
            flash('Your 14-day FoxDesk trial is ready. No payment is needed until the trial ends.', 'success');
            $redirect_page = function_exists('foxdesk_authenticated_home_page') ? foxdesk_authenticated_home_page() : 'dashboard';
            header('Location: index.php?page=' . $redirect_page . '&signup=trial');
            exit;
        }
        if (($result['status'] ?? '') === 'created_not_signed_in') {
            header('Location: index.php?page=login&created=1');
            exit;
        }
        if (($result['status'] ?? '') === 'expired') {
            $values['email'] = (string) ($result['email'] ?? '');
            $error = 'That link expired. Enter your email and we will send a fresh one.';
        } elseif (($result['status'] ?? '') === 'used') {
            $error = 'That link has already been used. Sign in or request a new link.';
        } elseif (($result['status'] ?? '') === 'existing_user') {
            header('Location: index.php?page=login&sent=1');
            exit;
        } else {
            $error = 'That signup link is not valid. Enter your email and we will send a new one.';
        }
    } catch (Throwable $e) {
        $error = 'We could not finish signup. Please request a new link.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        $error = 'Security check failed. Please try again.';
    } elseif (!require_turnstile_for_public_form('signup')) {
        $error = 'Bot protection check failed. Please try again.';
    } else {
        try {
            $result = signup_request_magic_link($values['email']);
            if (!($result['sent'] ?? false)) {
                $error = 'We could not send the signup link. Please try again.';
            } else {
                $success = true;
            }
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        } catch (Throwable $e) {
            if (function_exists('log_security_event')) {
                log_security_event('signup_magic_request_failed', null, 'error=' . $e->getMessage());
            }
            $error = 'We could not send the signup link. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($page_title); ?></title>
    <link rel="stylesheet" href="tailwind.min.css?v=<?php echo e((string) APP_VERSION); ?>">
    <link rel="stylesheet" href="theme.css?v=<?php echo e((string) APP_VERSION); ?>">
    <?php echo turnstile_script_tag(); ?>
</head>
<body class="signup-page">
    <main class="signup-shell">
        <section class="signup-brand">
            <div class="max-w-md">
                <div class="signup-logo">F</div>
                <h1 class="text-4xl font-bold mb-4">Start your FoxDesk trial</h1>
                <p class="text-slate-300 text-lg">One secure link, 14 days free, no card needed.</p>
            </div>
        </section>
        <section class="signup-panel">
            <div class="signup-card">
                <?php if ($success): ?>
                    <div class="mb-7">
                        <h2 class="text-3xl font-bold mb-2">Check your email</h2>
                        <p class="text-theme-muted">We sent a secure link. Open it within 30 minutes to create your FoxDesk.</p>
                    </div>
                    <p class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        If the email does not arrive in a minute or two, check spam or try again.
                    </p>
                    <form method="post" class="mt-5">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="email" value="<?php echo e($values['email']); ?>">
                        <?php echo turnstile_widget('signup'); ?>
                        <button type="submit" class="btn btn-secondary w-full py-2.5 mt-4">Send link again</button>
                    </form>
                <?php else: ?>
                    <div class="mb-7">
                        <h2 class="text-3xl font-bold mb-2">Create a new FoxDesk</h2>
                        <p class="text-theme-muted">Start a 14-day trial. No card required.</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"><?php echo e($error); ?></div>
                    <?php endif; ?>

                    <form method="post" class="space-y-4">
                        <?php echo csrf_field(); ?>
                        <div>
                            <label class="block text-sm font-medium mb-1.5">Email</label>
                            <input class="signup-input" type="email" name="email" value="<?php echo e($values['email']); ?>" autocomplete="email" required autofocus>
                        </div>
                        <?php echo turnstile_widget('signup'); ?>
                        <button type="submit" class="btn btn-primary w-full py-2.5">Start free trial</button>
                        <p class="text-xs leading-5 text-theme-muted">
                            By continuing you agree to the
                            <a class="link-theme-primary" href="<?php echo e(url('legal', ['type' => 'terms'])); ?>" target="_blank" rel="noopener">Terms</a>
                            and
                            <a class="link-theme-primary" href="<?php echo e(url('legal', ['type' => 'privacy'])); ?>" target="_blank" rel="noopener">Privacy Policy</a>.
                            Billing is covered by the
                            <a class="link-theme-primary" href="<?php echo e(url('legal', ['type' => 'refunds'])); ?>" target="_blank" rel="noopener">Refund and Cancellation Policy</a>.
                        </p>
                    </form>
                <?php endif; ?>

                <p class="text-center text-sm mt-6 text-theme-muted">
                    Already have an account?
                    <a href="<?php echo url('login'); ?>" class="font-medium link-theme-primary">Sign in</a>
                </p>
            </div>
        </section>
    </main>
</body>
</html>
