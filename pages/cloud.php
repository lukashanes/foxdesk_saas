<?php
/**
 * Public FoxDesk Cloud website.
 *
 * This is the public FoxDesk Cloud surface. Operational/customer data is
 * intentionally kept out of this page; it belongs behind platform admin login.
 */

$page_title = 'FoxDesk Cloud';
$cloud_launch_price = billing_currency() === 'CZK' ? '249 Kc' : 'EUR 9.90';
$included_storage = billing_included_storage_bytes() === 1073741824
    ? '1 GB'
    : preg_replace('/\.00\s+/', ' ', format_file_size(billing_included_storage_bytes()));
$cloud_css_path = BASE_PATH . '/assets/public/cloud.css';
$cloud_css_version = (string) APP_VERSION . '-' . (file_exists($cloud_css_path) ? (string) filemtime($cloud_css_path) : '0');
$cloud_asset = static function (string $path): string {
    $absolute_path = BASE_PATH . '/' . ltrim($path, '/');
    $version = (string) APP_VERSION . '-' . (file_exists($absolute_path) ? (string) filemtime($absolute_path) : '0');

    return $path . '?v=' . rawurlencode($version);
};

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
    <title><?php echo e($page_title); ?> - Helpdesk & time tracking</title>
    <meta name="description" content="FoxDesk is helpdesk and time tracking for support teams and AI agents. Unlimited users, clients, organizations, agents, and tickets.">
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
                <img src="assets/public/logo.png" alt="" width="1024" height="1024" decoding="async">
                <span>FoxDesk</span>
            </a>
            <nav class="fd-nav" aria-label="Public navigation">
                <a href="#cloud">Cloud</a>
                <a href="#features">Features</a>
                <a href="#pricing">Pricing</a>
                <a href="<?php echo e(url('legal', ['type' => 'privacy'])); ?>">Privacy</a>
                <a href="https://foxdesk.org" target="_blank" rel="noopener">Open-source</a>
            </nav>
            <div class="fd-header-actions">
                <a href="<?php echo e(url('login')); ?>" class="fd-btn secondary">Client login</a>
                <a href="<?php echo e(url('signup')); ?>" class="fd-btn primary">Try FoxDesk</a>
                <button type="button" class="fd-theme-toggle" onclick="toggleCloudTheme()" aria-label="Toggle color mode">◐</button>
            </div>
        </div>
    </header>

    <main class="fd-main">
        <section class="fd-section fd-hero fd-grid-12 fd-grid-center" id="cloud">
            <div class="fd-hero-copy fd-span-5">
                <h1>Helpdesk & time tracking</h1>
                <p>Track support tickets and billable hours for your team and your AI agents. One app. No per-agent or client fees, ever.</p>
                <div class="fd-hero-actions">
                    <a href="<?php echo e(url('signup')); ?>" class="fd-btn primary">Try FoxDesk</a>
                    <a href="<?php echo e(url('login')); ?>" class="fd-btn secondary">Client login</a>
                </div>
                <div class="fd-hero-proof">
                    <div class="fd-proof-item"><strong>Unlimited</strong><span>Users, agents, clients, organizations, and tickets</span></div>
                    <div class="fd-proof-item"><strong>Time</strong><span>Billable hours tracked directly on tickets</span></div>
                    <div class="fd-proof-item"><strong>AI agents</strong><span>Human and AI support work in the same flow</span></div>
                </div>
            </div>

            <div class="fd-hero-product fd-span-7" aria-label="FoxDesk Cloud product preview">
                <div class="fd-product-frame">
                    <div class="fd-product-toolbar">
                        <div class="fd-window-dots"><span></span><span></span><span></span></div>
                        <div class="fd-product-url">app.foxdesk.net / dashboard</div>
                    </div>
                    <img class="fd-light-img" src="<?php echo e($cloud_asset('assets/public/dashboard-light.webp')); ?>" alt="FoxDesk dashboard preview" width="1200" height="675" fetchpriority="high" decoding="async">
                    <img class="fd-dark-img" src="<?php echo e($cloud_asset('assets/public/dashboard-dark.webp')); ?>" alt="FoxDesk dashboard preview in dark mode" width="1200" height="675" fetchpriority="high" decoding="async">
                </div>
            </div>
        </section>

        <section class="fd-section fd-band" id="features">
            <div class="fd-heading">
                <h2>Helpdesk & time tracking.</h2>
                <p>Tickets, billable hours, clients, and AI agents without per-seat pricing.</p>
            </div>
            <div class="fd-grid fd-grid-12">
                <article class="fd-card fd-span-4">
                    <h3>Tickets</h3>
                    <p>Manage requests, assignments, comments, attachments, priorities, and client visibility.</p>
                </article>
                <article class="fd-card fd-span-4">
                    <h3>Time tracking</h3>
                    <p>Track billable hours on tickets and turn work into clear client reports.</p>
                </article>
                <article class="fd-card fd-span-4">
                    <h3>AI agents</h3>
                    <p>Let AI agents create tickets, add updates, and log work beside your team.</p>
                </article>
            </div>
        </section>

        <section class="fd-section">
            <div class="fd-feature-section fd-grid-12 fd-grid-center">
                <div class="fd-feature-copy fd-span-5">
                    <div class="fd-feature-icon">TK</div>
                    <h3>Ticket lifecycle management.</h3>
                    <p>Run daily support from one workspace: statuses, priorities, assignments, comments, attachments, and client visibility.</p>
                    <ul class="fd-feature-list">
                        <li><span class="fd-check">✓</span><span><strong>Email piping:</strong> create and update tickets from support inboxes.</span></li>
                        <li><span class="fd-check">✓</span><span><strong>Granular notifications:</strong> keep teams and clients informed without noise.</span></li>
                        <li><span class="fd-check">✓</span><span><strong>Attachments:</strong> keep files connected to the ticket history.</span></li>
                    </ul>
                </div>
                <div class="fd-feature-media fd-span-7">
                    <img class="fd-light-img" src="<?php echo e($cloud_asset('assets/public/ticket-detail-light.webp')); ?>" alt="FoxDesk ticket detail" width="1200" height="675" loading="lazy" decoding="async">
                    <img class="fd-dark-img" src="<?php echo e($cloud_asset('assets/public/ticket-detail-dark.webp')); ?>" alt="FoxDesk ticket detail in dark mode" width="1200" height="675" loading="lazy" decoding="async">
                </div>
            </div>

            <div class="fd-feature-section reverse fd-grid-12 fd-grid-center">
                <div class="fd-feature-copy fd-span-5">
                    <div class="fd-feature-icon">TM</div>
                    <h3>Time tracking and client reports.</h3>
                    <p>Track billable work directly on tickets, review time by client, and prepare reports without exporting support data into a second tool.</p>
                    <ul class="fd-feature-list">
                        <li><span class="fd-check">✓</span><span><strong>Work logs:</strong> manual entries, timers, summaries, and internal notes.</span></li>
                        <li><span class="fd-check">✓</span><span><strong>Reports:</strong> client reports, time totals, snapshots, and shareable links.</span></li>
                        <li><span class="fd-check">✓</span><span><strong>Quick timer controls:</strong> start, pause, resume, and stop from the app.</span></li>
                    </ul>
                </div>
                <div class="fd-feature-media fd-span-7">
                    <img class="fd-light-img" src="<?php echo e($cloud_asset('assets/public/time-report-light.webp')); ?>" alt="FoxDesk time reporting" width="1200" height="675" loading="lazy" decoding="async">
                    <img class="fd-dark-img" src="<?php echo e($cloud_asset('assets/public/time-report-dark.webp')); ?>" alt="FoxDesk time reporting in dark mode" width="1200" height="675" loading="lazy" decoding="async">
                </div>
            </div>

            <div class="fd-heading">
                <h2>Built for support teams, agencies, and automation.</h2>
                <p>Everything your team needs to support clients, track work, and automate the routine parts.</p>
            </div>
            <div class="fd-capability-grid fd-grid-12">
                <article class="fd-capability fd-span-3"><span>AI</span><h4>AI Agent API</h4><p>Create tickets, post updates, and log work from AI agents or internal tools.</p></article>
                <article class="fd-capability fd-span-3"><span>EM</span><h4>Email tickets</h4><p>Turn support emails into tickets and keep replies in the thread.</p></article>
                <article class="fd-capability fd-span-3"><span>CL</span><h4>Organizations</h4><p>Group clients, tickets, time, reports, and permissions by account.</p></article>
                <article class="fd-capability fd-span-3"><span>AC</span><h4>Permissions</h4><p>Control admins, agents, client access, 2FA, and audit logs.</p></article>
                <article class="fd-capability fd-span-3"><span>LG</span><h4>Languages</h4><p>Use FoxDesk across localized app and customer-facing text.</p></article>
                <article class="fd-capability fd-span-3"><span>NT</span><h4>Notifications</h4><p>Keep teams and clients informed about ticket activity.</p></article>
                <article class="fd-capability fd-span-3"><span>FL</span><h4>Files</h4><p>Attach files, keep history, and share what clients need.</p></article>
                <article class="fd-capability fd-span-3"><span>AD</span><h4>Admin settings</h4><p>Customize statuses, priorities, ticket types, users, clients, and reports.</p></article>
            </div>
        </section>

        <section class="fd-section fd-band" id="pricing">
            <div class="fd-heading">
                <h2>One plan. No per-seat math.</h2>
                <p>Start free for 14 days. Add the whole team, every client, and every AI agent without changing plans.</p>
            </div>
            <div class="fd-pricing fd-grid-12">
                <div class="fd-price-card">
                    <div class="fd-price-top">
                        <div>
                            <h3 class="fd-plan-title">FoxDesk Cloud</h3>
                            <p class="fd-regular-price">Regular price EUR 19.90/month</p>
                            <p class="fd-offer-line">Introductory price EUR 9.90/month</p>
                        </div>
                    </div>
                    <div class="fd-price">
                        <strong><?php echo e($cloud_launch_price); ?></strong>
                        <span>/ month</span>
                    </div>
                    <p class="fd-price-note">Join during the introductory period and keep EUR 9.90/month.</p>
                    <p class="fd-price-note">One workspace. Unlimited seats, clients, tickets and team members.</p>
                    <ul class="fd-list">
                        <li><span class="fd-check">✓</span><span>Unlimited users, agents, clients, organizations, and tickets</span></li>
                        <li><span class="fd-check">✓</span><span>Helpdesk and billable time tracking in one app</span></li>
                        <li><span class="fd-check">✓</span><span><?php echo e($included_storage); ?> file storage included</span></li>
                    </ul>
                    <a href="<?php echo e(url('signup')); ?>" class="fd-btn primary fd-full-width">Try FoxDesk free for 14 days</a>
                </div>
            </div>
        </section>

        <section class="fd-section fd-band fd-preview-section" id="preview" aria-labelledby="preview-title">
            <div class="fd-heading">
                <h2 id="preview-title">The daily workspace.</h2>
                <p>Ticket work and time reporting stay close together, so support work turns into clean client records.</p>
            </div>
            <div class="fd-preview-stack fd-grid-12">
                <figure class="fd-preview-card fd-span-6">
                    <img class="fd-light-img" src="<?php echo e($cloud_asset('assets/public/dashboard-light.webp')); ?>" alt="FoxDesk dashboard" width="1200" height="675" loading="lazy" decoding="async">
                    <img class="fd-dark-img" src="<?php echo e($cloud_asset('assets/public/dashboard-dark.webp')); ?>" alt="FoxDesk dashboard in dark mode" width="1200" height="675" loading="lazy" decoding="async">
                    <figcaption>See what needs attention across tickets, clients, and work logs.</figcaption>
                </figure>
                <figure class="fd-preview-card fd-span-6">
                    <img class="fd-light-img" src="<?php echo e($cloud_asset('assets/public/ticket-detail-light.webp')); ?>" alt="FoxDesk ticket detail" width="1200" height="675" loading="lazy" decoding="async">
                    <img class="fd-dark-img" src="<?php echo e($cloud_asset('assets/public/ticket-detail-dark.webp')); ?>" alt="FoxDesk ticket detail in dark mode" width="1200" height="675" loading="lazy" decoding="async">
                    <figcaption>Reply, assign, track time, and keep the client history together.</figcaption>
                </figure>
            </div>
        </section>

    </main>

    <footer class="fd-footer" id="support">
        <div class="fd-footer-inner">
            <div class="fd-footer-brand">
                <img src="assets/public/logo.png" alt="" width="28" height="28" loading="lazy" decoding="async">
                <strong>FoxDesk</strong>
            </div>
            <div class="fd-footer-links">
                <a href="mailto:support@foxdesk.net">Support</a>
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
