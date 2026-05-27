<?php
/**
 * Public FoxDesk Cloud website.
 *
 * This is the public FoxDesk Cloud surface. Operational/customer data is
 * intentionally kept out of this page; it belongs behind platform admin login.
 */

$page_title = 'FoxDesk Cloud';
$cloud_launch_price = billing_currency() === 'CZK' ? '249 Kc' : 'EUR 9.90';
$cloud_launch_until = 'May 31, 2026';
$included_storage = billing_included_storage_bytes() === 1073741824
    ? '1 GB'
    : preg_replace('/\.00\s+/', ' ', format_file_size(billing_included_storage_bytes()));
$cloud_css_path = BASE_PATH . '/assets/public/cloud.css';
$cloud_css_version = (string) APP_VERSION . '-' . (file_exists($cloud_css_path) ? (string) filemtime($cloud_css_path) : '0');

if (!headers_sent()) {
    header(
        "Content-Security-Policy: default-src 'self'; " .
        "script-src 'self' 'unsafe-inline'; " .
        "style-src 'self'; " .
        "font-src 'self'; " .
        "img-src 'self' data:; " .
        "connect-src 'self'; " .
        "frame-ancestors 'self'"
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($page_title); ?> - Managed helpdesk hosting</title>
    <meta name="description" content="FoxDesk Cloud is managed hosting for FoxDesk. Unlimited users, clients, agents, and tickets with simple storage-based scaling.">
    <link rel="icon" type="image/png" href="assets/public/logo.png">
    <link rel="stylesheet" href="assets/public/cloud.css?v=<?php echo rawurlencode($cloud_css_version); ?>">
    <script>
        (function () {
            var saved = localStorage.getItem('foxdesk-cloud-theme');
            var theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
</head>
<body>
    <div class="fd-glow-top"></div>

    <header class="fd-header">
        <div class="fd-header-inner">
            <a href="<?php echo e(url('cloud')); ?>" class="fd-brand">
                <picture>
                    <source srcset="assets/public/logo.webp" type="image/webp">
                    <img src="assets/public/logo.png" alt="FoxDesk" width="1024" height="1024" decoding="async">
                </picture>
                <span>FoxDesk</span>
            </a>
            <nav class="fd-nav" aria-label="Public navigation">
                <a href="#cloud">Cloud</a>
                <a href="#features">Features</a>
                <a href="#pricing">Pricing</a>
                <a href="#migration">Migration</a>
                <a href="<?php echo e(url('legal', ['type' => 'privacy'])); ?>">Privacy</a>
                <a href="https://foxdesk.org" target="_blank" rel="noopener">Open-source</a>
            </nav>
            <div class="fd-header-actions">
                <a href="<?php echo e(url('login')); ?>" class="fd-btn secondary">Client login</a>
                <a href="#pricing" class="fd-btn primary">View plan</a>
                <button type="button" class="fd-theme-toggle" onclick="toggleCloudTheme()" aria-label="Toggle color mode">◐</button>
            </div>
        </div>
    </header>

    <main class="fd-main">
        <section class="fd-section fd-hero" id="cloud">
            <div class="fd-hero-copy">
                <h1>Run customer support from one managed FoxDesk.</h1>
                <p>FoxDesk Cloud hosts your helpdesk, time tracking, clients, tickets, attachments, email delivery, and updates so your team can start working without managing PHP hosting.</p>
                <div class="fd-hero-actions">
                    <a href="#pricing" class="fd-btn primary">See Cloud plan</a>
                    <a href="<?php echo e(url('login')); ?>" class="fd-btn secondary">Sign in to app</a>
                </div>
                <div class="fd-hero-proof">
                    <div class="fd-proof-item"><strong>Unlimited</strong><span>Users, clients, agents, and tickets</span></div>
                    <div class="fd-proof-item"><strong><?php echo e($included_storage); ?></strong><span>Storage included at launch</span></div>
                    <div class="fd-proof-item"><strong>Managed</strong><span>Updates, storage, email, and backups</span></div>
                </div>
            </div>

            <div class="fd-hero-product" aria-label="FoxDesk Cloud product preview">
                <div class="fd-product-frame">
                    <div class="fd-product-toolbar">
                        <div class="fd-window-dots"><span></span><span></span><span></span></div>
                        <div class="fd-product-url">app.foxdesk.net / dashboard</div>
                    </div>
                    <img class="fd-light-img" src="assets/public/dashboard-light.webp" alt="FoxDesk dashboard preview" width="1200" height="675" fetchpriority="high" decoding="async">
                    <img class="fd-dark-img" src="assets/public/dashboard-dark.webp" alt="FoxDesk dashboard preview in dark mode" width="1200" height="675" fetchpriority="high" decoding="async">
                </div>
            </div>
        </section>

        <section class="fd-section fd-band" id="features">
            <div class="fd-heading">
                <h2>Everything FoxDesk can do, managed for you.</h2>
                <p>The SaaS version keeps the full FoxDesk feature set and removes the hosting work: app stack, email, storage, updates, backups, and monitoring.</p>
            </div>
            <div class="fd-grid">
                <article class="fd-card">
                    <h3>Ready workspace</h3>
                    <p>Each customer gets their own FoxDesk workspace with isolated users, clients, tickets, reports, and files.</p>
                </article>
                <article class="fd-card">
                    <h3>Unlimited team</h3>
                    <p>No per-agent pricing. Invite admins, agents, clients, and collaborators without fighting seat limits.</p>
                </article>
                <article class="fd-card">
                    <h3>Managed operations</h3>
                    <p>The hosted version is prepared for managed updates, email delivery, attachment storage, backups, and monitoring.</p>
                </article>
            </div>
        </section>

        <section class="fd-section">
            <div class="fd-feature-section">
                <div class="fd-feature-copy">
                    <div class="fd-feature-icon">T</div>
                    <h3>Ticket lifecycle management.</h3>
                    <p>Run daily support from one workspace: ticket statuses, priorities, assignments, comments, attachments, client visibility, and shared public links.</p>
                    <ul class="fd-feature-list">
                        <li><span class="fd-check">✓</span><span><strong>Email piping:</strong> create and update tickets from support inboxes.</span></li>
                        <li><span class="fd-check">✓</span><span><strong>Granular notifications:</strong> keep teams and clients informed without noise.</span></li>
                        <li><span class="fd-check">✓</span><span><strong>Attachments:</strong> store and download files without managing hosting disk space yourself.</span></li>
                    </ul>
                </div>
                <div class="fd-feature-media">
                    <img class="fd-light-img" src="assets/public/ticket-detail-light.webp" alt="FoxDesk ticket detail" width="1200" height="675" loading="lazy" decoding="async">
                    <img class="fd-dark-img" src="assets/public/ticket-detail-dark.webp" alt="FoxDesk ticket detail in dark mode" width="1200" height="675" loading="lazy" decoding="async">
                </div>
            </div>

            <div class="fd-feature-section reverse">
                <div class="fd-feature-copy">
                    <div class="fd-feature-icon">⏱</div>
                    <h3>Time tracking and client reports.</h3>
                    <p>Track billable work directly on tickets, review time by client, and prepare reports without exporting support data into a second tool.</p>
                    <ul class="fd-feature-list">
                        <li><span class="fd-check">✓</span><span><strong>Work logs:</strong> manual entries, timers, summaries, and internal notes.</span></li>
                        <li><span class="fd-check">✓</span><span><strong>Reports:</strong> client reports, time totals, snapshots, and shareable links.</span></li>
                        <li><span class="fd-check">✓</span><span><strong>Quick timer controls:</strong> start, pause, resume, and stop from the app.</span></li>
                    </ul>
                </div>
                <div class="fd-feature-media">
                    <img class="fd-light-img" src="assets/public/time-report-light.webp" alt="FoxDesk time reporting" width="1200" height="675" loading="lazy" decoding="async">
                    <img class="fd-dark-img" src="assets/public/time-report-dark.webp" alt="FoxDesk time reporting in dark mode" width="1200" height="675" loading="lazy" decoding="async">
                </div>
            </div>

            <div class="fd-heading">
                <h2>Built for support teams, agencies, and automation.</h2>
                <p>FoxDesk Cloud keeps the practical admin surface from the self-hosted edition and adds a managed SaaS operating layer.</p>
            </div>
            <div class="fd-capability-grid">
                <article class="fd-capability"><span>A</span><h4>AI Agent API</h4><p>Give AI agents or internal tools API access to create tickets, post updates, and log work.</p></article>
                <article class="fd-capability"><span>✉</span><h4>Custom client emails</h4><p>Send branded support notifications from your FoxDesk workspace without configuring SMTP yourself.</p></article>
                <article class="fd-capability"><span>O</span><h4>Organizations</h4><p>Group clients, tickets, time, reports, and permissions around real customer accounts.</p></article>
                <article class="fd-capability"><span>🔒</span><h4>Security controls</h4><p>Admin permissions, 2FA support, CSRF protection, audit/security logs, and impersonation controls.</p></article>
                <article class="fd-capability"><span>🌐</span><h4>Multilingual UI</h4><p>Use FoxDesk across languages with localized app labels and customer-facing text.</p></article>
                <article class="fd-capability"><span>🔔</span><h4>Notifications</h4><p>Email, in-app, and push notification building blocks for ticket activity and reminders.</p></article>
                <article class="fd-capability"><span>📎</span><h4>File storage</h4><p>Attachments are stored outside the app container so the workspace can grow safely.</p></article>
                <article class="fd-capability"><span>⚙</span><h4>Admin settings</h4><p>Branding, ticket statuses, priorities, types, users, clients, reports, and recurring tasks.</p></article>
            </div>
        </section>

        <section class="fd-section fd-band" id="pricing">
            <div class="fd-heading">
                <h2>One simple Cloud plan.</h2>
                <p>Create a workspace, start the Cloud subscription, and manage billing from your FoxDesk account.</p>
            </div>
            <div class="fd-pricing">
                <div class="fd-price-card">
                    <div class="fd-price-top">
                        <div>
                            <h3 class="fd-plan-title">FoxDesk Cloud</h3>
                            <p class="fd-offer-line">Launch price available until <?php echo e($cloud_launch_until); ?>.</p>
                        </div>
                    </div>
                    <div class="fd-price">
                        <strong><?php echo e($cloud_launch_price); ?></strong>
                        <span>/ month</span>
                    </div>
                    <ul class="fd-list">
                        <li><span class="fd-check">✓</span><span>Unlimited users, agents, clients, organizations, and tickets</span></li>
                        <li><span class="fd-check">✓</span><span><?php echo e($included_storage); ?> file storage included</span></li>
                        <li><span class="fd-check">✓</span><span>Hosted FoxDesk app on app.foxdesk.net</span></li>
                        <li><span class="fd-check">✓</span><span>Managed email sending and attachment storage</span></li>
                        <li><span class="fd-check">✓</span><span>Updates and production deployment prepared for managed hosting</span></li>
                    </ul>
                    <a href="<?php echo e(url('signup')); ?>" class="fd-btn primary fd-full-width">Create workspace</a>
                </div>
            </div>
        </section>

        <section class="fd-section fd-band fd-preview-section" id="preview">
            <div class="fd-heading">
                <h2>See the managed workspace.</h2>
                <p>Preview the dashboard and ticket detail your team will use every day.</p>
            </div>
            <div class="fd-preview-stack">
                <img class="fd-light-img" src="assets/public/dashboard-light.webp" alt="FoxDesk dashboard" width="1200" height="675" loading="lazy" decoding="async">
                <img class="fd-dark-img" src="assets/public/dashboard-dark.webp" alt="FoxDesk dashboard in dark mode" width="1200" height="675" loading="lazy" decoding="async">
                <img class="fd-light-img" src="assets/public/ticket-detail-light.webp" alt="FoxDesk ticket detail" width="1200" height="675" loading="lazy" decoding="async">
                <img class="fd-dark-img" src="assets/public/ticket-detail-dark.webp" alt="FoxDesk ticket detail in dark mode" width="1200" height="675" loading="lazy" decoding="async">
            </div>
        </section>

        <section class="fd-section fd-band" id="migration">
            <div class="fd-migration">
                <div>
                    <h2>Move your existing FoxDesk safely.</h2>
                    <p>If you already run FoxDesk on Vas-Hosting or another PHP hosting, the clean migration path is backup first, restore second, then DNS switch only after testing.</p>
                    <div class="fd-hero-actions">
                        <a href="<?php echo e(url('login')); ?>" class="fd-btn secondary">App login</a>
                        <a href="mailto:hanes.lukas@gmail.com?subject=FoxDesk%20migration" class="fd-btn primary">Plan migration</a>
                    </div>
                </div>
                <div class="fd-steps">
                    <div class="fd-step">
                        <div class="fd-step-number">1</div>
                        <div><strong>Export current installation</strong><span>Database dump, uploaded files, config, cron/email settings, and current FoxDesk version.</span></div>
                    </div>
                    <div class="fd-step">
                        <div class="fd-step-number">2</div>
                        <div><strong>Restore on the new server</strong><span>Import DB, copy files to the managed stack, configure email delivery and attachment storage.</span></div>
                    </div>
                    <div class="fd-step">
                        <div class="fd-step-number">3</div>
                        <div><strong>Test before switching DNS</strong><span>Login, tickets, attachments, outbound email, inbound email, cron, health endpoint, and admin permissions.</span></div>
                    </div>
                    <div class="fd-step">
                        <div class="fd-step-number">4</div>
                        <div><strong>Switch production traffic</strong><span>Lower DNS TTL, point the app domain to the new server, monitor logs, then keep the old host as rollback for a short period.</span></div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="fd-footer">
        <div class="fd-footer-inner">
            <div class="fd-footer-brand">
                <img src="assets/public/logo.webp" alt="" width="28" height="28" loading="lazy" decoding="async">
                <strong>FoxDesk</strong>
            </div>
            <div>Open-source FoxDesk remains available at <a href="https://foxdesk.org" class="fd-link-blue" target="_blank" rel="noopener">foxdesk.org</a>. FoxDesk Cloud runs at <strong>app.foxdesk.net</strong>.</div>
            <div class="fd-footer-links">
                <a href="<?php echo e(url('legal', ['type' => 'privacy'])); ?>">Privacy</a>
                <a href="<?php echo e(url('legal', ['type' => 'terms'])); ?>">Terms</a>
                <a href="<?php echo e(url('legal', ['type' => 'dpa'])); ?>">DPA</a>
                <a href="<?php echo e(url('legal', ['type' => 'refunds'])); ?>">Refunds</a>
                <a href="<?php echo e(url('legal', ['type' => 'security'])); ?>">Security</a>
            </div>
        </div>
    </footer>
    <script>
        function toggleCloudTheme() {
            var current = document.documentElement.getAttribute('data-theme') || 'light';
            var next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('foxdesk-cloud-theme', next);
        }
    </script>
</body>
</html>
