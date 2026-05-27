<?php
/**
 * Public SaaS signup: creates a new FoxDesk workspace and its first admin.
 */

if (is_logged_in()) {
    header('Location: index.php?page=' . (is_platform_admin() ? 'platform' : 'dashboard'));
    exit;
}

$page_title = 'Create your FoxDesk';
$error = '';
$values = [
    'workspace_name' => trim((string) ($_POST['workspace_name'] ?? '')),
    'admin_first_name' => trim((string) ($_POST['admin_first_name'] ?? '')),
    'admin_last_name' => trim((string) ($_POST['admin_last_name'] ?? '')),
    'admin_email' => trim((string) ($_POST['admin_email'] ?? '')),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid()) {
        $error = 'Security check failed. Please try again.';
    } else {
        $password = (string) ($_POST['password'] ?? '');
        $password_confirm = (string) ($_POST['password_confirm'] ?? '');

        if ($password !== $password_confirm) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $workspace = create_tenant_workspace([
                    'workspace_name' => $values['workspace_name'],
                    'admin_email' => $values['admin_email'],
                    'admin_first_name' => $values['admin_first_name'],
                    'admin_last_name' => $values['admin_last_name'],
                    'password' => $password,
                    'status' => 'trialing',
                    'subscription_status' => 'trialing',
                    'plan' => billing_plan_code(),
                ]);

                if (function_exists('billing_send_trial_email_for_tenant')) {
                    billing_send_trial_email_for_tenant((int) $workspace['tenant_id'], 'trial_started');
                }

                if (login($values['admin_email'], $password)) {
                    flash('Your 14-day FoxDesk trial is ready. No payment is needed until the trial ends.', 'success');
                    header('Location: index.php?page=dashboard&signup=trial');
                    exit;
                }

                header('Location: index.php?page=login&created=1');
                exit;
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
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
    <link rel="stylesheet" href="tailwind.min.css">
    <link rel="stylesheet" href="theme.css?v=<?php echo APP_VERSION; ?>">
    <style>
        body { min-height: 100vh; background: var(--bg-primary); color: var(--text-primary); }
        .signup-shell { min-height: 100vh; display: grid; grid-template-columns: 1fr; }
        .signup-brand { display: none; background: #0f172a; color: white; padding: 4rem; align-items: center; }
        .signup-panel { display: flex; align-items: center; justify-content: center; padding: 2rem; }
        .signup-card { width: 100%; max-width: 440px; }
        .signup-logo { width: 3.5rem; height: 3.5rem; border-radius: 1rem; background: #4f63f1; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; margin-bottom: 2rem; }
        .signup-input { width: 100%; border: 1px solid var(--border-light); border-radius: .65rem; padding: .7rem .9rem; background: var(--surface-primary); color: var(--text-primary); }
        @media (min-width: 960px) { .signup-shell { grid-template-columns: 1fr 1fr; } .signup-brand { display: flex; } }
    </style>
</head>
<body>
    <main class="signup-shell">
        <section class="signup-brand">
            <div class="max-w-md">
                <div class="signup-logo">F</div>
                <h1 class="text-4xl font-bold mb-4">Start a new FoxDesk</h1>
                <p class="text-slate-300 text-lg">Create your own helpdesk workspace, invite your team, and keep every customer environment isolated.</p>
            </div>
        </section>
        <section class="signup-panel">
            <div class="signup-card">
                <div class="mb-7">
                    <h2 class="text-3xl font-bold mb-2">Create workspace</h2>
                    <p style="color: var(--text-muted);">Start a 14-day trial. No card required.</p>
                </div>

                <?php if ($error): ?>
                    <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"><?php echo e($error); ?></div>
                <?php endif; ?>

                <form method="post" class="space-y-4">
                    <?php echo csrf_field(); ?>
                    <div>
                        <label class="block text-sm font-medium mb-1.5">Workspace name</label>
                        <input class="signup-input" name="workspace_name" value="<?php echo e($values['workspace_name']); ?>" required>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium mb-1.5">First name</label>
                            <input class="signup-input" name="admin_first_name" value="<?php echo e($values['admin_first_name']); ?>" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1.5">Last name</label>
                            <input class="signup-input" name="admin_last_name" value="<?php echo e($values['admin_last_name']); ?>">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1.5">Admin email</label>
                        <input class="signup-input" type="email" name="admin_email" value="<?php echo e($values['admin_email']); ?>" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1.5">Password</label>
                        <input class="signup-input" type="password" name="password" minlength="12" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1.5">Confirm password</label>
                        <input class="signup-input" type="password" name="password_confirm" minlength="12" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-full py-2.5">Start 14-day free trial</button>
                    <p class="text-xs leading-5" style="color: var(--text-muted);">
                        By creating a workspace you agree to the
                        <a href="<?php echo e(url('legal', ['type' => 'terms'])); ?>" target="_blank" rel="noopener" style="color: var(--primary);">Terms</a>
                        and
                        <a href="<?php echo e(url('legal', ['type' => 'privacy'])); ?>" target="_blank" rel="noopener" style="color: var(--primary);">Privacy Policy</a>.
                        Billing is covered by the
                        <a href="<?php echo e(url('legal', ['type' => 'refunds'])); ?>" target="_blank" rel="noopener" style="color: var(--primary);">Refund and Cancellation Policy</a>.
                    </p>
                </form>

                <p class="text-center text-sm mt-6" style="color: var(--text-muted);">
                    Already have an account?
                    <a href="<?php echo url('login'); ?>" style="color: var(--primary);" class="font-medium">Sign in</a>
                </p>
            </div>
        </section>
    </main>
</body>
</html>
