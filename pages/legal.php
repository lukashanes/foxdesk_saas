<?php
$type = strtolower((string) ($_GET['type'] ?? 'privacy'));
$updated = 'May 27, 2026';

$documents = [
    'privacy' => [
        'title' => 'Privacy Policy',
        'intro' => 'This page explains what FoxDesk Cloud collects, why it is needed, and how customer data is handled.',
        'sections' => [
            ['Data we process', 'Account details, workspace content, tickets, comments, attachments, billing records, email delivery metadata, security logs, and operational diagnostics needed to run FoxDesk Cloud.'],
            ['Purpose', 'We use data to provide the helpdesk service, authenticate users, deliver notifications, process billing, secure the platform, provide support, and improve reliability.'],
            ['Customer content', 'Workspace owners control their customer, ticket, report, and attachment content. FoxDesk Cloud does not sell customer content or use it for advertising.'],
            ['Retention', 'Workspace data is retained while the subscription or trial is active. Backups and logs may be retained for a limited operational period for recovery, security, and accounting.'],
            ['Subprocessors', 'FoxDesk Cloud uses subprocessors listed on the Subprocessors page for hosting, storage, email delivery, billing, security, and operational services.'],
            ['Contact', 'Privacy requests can be sent to support@foxdesk.net.'],
        ],
    ],
    'terms' => [
        'title' => 'Terms of Service',
        'intro' => 'These terms define the basic rules for using FoxDesk Cloud.',
        'sections' => [
            ['Service', 'FoxDesk Cloud provides hosted helpdesk workspaces with users, clients, tickets, reports, attachments, email notifications, and managed operations.'],
            ['Accounts', 'Workspace owners are responsible for invited users, accurate billing information, secure credentials, and lawful use of their workspace.'],
            ['Acceptable use', 'The service must not be used for illegal content, malware, spam, abuse, infringement, credential harvesting, or activity that harms the platform or other customers.'],
            ['Billing', 'Paid subscriptions renew monthly unless canceled. The customer is responsible for base subscription fees, metered storage overage, taxes, and failed payment recovery handled through Stripe.'],
            ['Refunds and cancellation', 'Monthly subscriptions can be canceled before the next renewal period. Refund handling is described in the Refund and Cancellation Policy.'],
            ['Availability', 'FoxDesk Cloud is operated with reasonable care, backups, monitoring, and maintenance windows. A formal SLA can be published separately when support commitments are finalized.'],
            ['Termination', 'Accounts may be suspended for non-payment, abuse, security risk, or breach of these terms. Customers should be able to export their data where technically possible.'],
        ],
    ],
    'dpa' => [
        'title' => 'Data Processing Addendum',
        'intro' => 'This draft describes the processor relationship for customer workspace data. It should be finalized with legal review.',
        'sections' => [
            ['Roles', 'The customer is the controller of workspace content and FoxDesk Cloud acts as processor for that content.'],
            ['Processing scope', 'Processing is limited to hosting, storing, transmitting, securing, backing up, and supporting customer workspace data.'],
            ['Security', 'FoxDesk Cloud should maintain access controls, TLS, password hashing, CSRF protection, backup controls, logging, and least-privilege operational access.'],
            ['Subprocessors', 'Subprocessors must be documented and used only where needed to provide hosting, storage, email, billing, monitoring, or support.'],
            ['Incidents', 'Confirmed personal data incidents should be investigated promptly and communicated to affected customers without undue delay.'],
            ['Deletion and export', 'At termination, customer data should be exportable and deletable according to retention and backup limits.'],
        ],
    ],
    'refunds' => [
        'title' => 'Refund and Cancellation Policy',
        'intro' => 'This page explains how monthly FoxDesk Cloud subscriptions can be canceled and when refunds may apply.',
        'sections' => [
            ['Monthly subscriptions', 'FoxDesk Cloud is billed monthly through Stripe. A subscription remains active until the end of the paid billing period unless access is suspended for abuse, security risk, or non-payment.'],
            ['Cancellation', 'Workspace owners can request cancellation before the next renewal. Cancellation stops future renewals, but the current paid period normally remains available.'],
            ['Refund requests', 'Refund requests are reviewed case by case. Refunds may be considered for duplicate charges, accidental renewal reported promptly, or confirmed service failure that prevented normal use.'],
            ['Storage overage', 'Metered storage overage is based on usage reported for the billing period. If usage data is incorrect, contact support so the invoice can be reviewed.'],
            ['Contact', 'Billing questions and refund requests can be sent to billing@foxdesk.net or support@foxdesk.net.'],
        ],
    ],
    'subprocessors' => [
        'title' => 'Subprocessors',
        'intro' => 'FoxDesk Cloud uses the following subprocessors to provide hosting, storage, security, email delivery, billing, and operations.',
        'sections' => [
            ['Hetzner', 'Infrastructure hosting for the FoxDesk Cloud application, database, and related server workloads.'],
            ['Cloudflare', 'DNS, TLS/proxy services, R2 object storage, email sending/routing, caching, and security controls.'],
            ['Stripe', 'Subscription billing, Checkout, Customer Portal, invoices, payment processing, and billing-related customer records.'],
            ['Email infrastructure', 'Transactional email metadata is processed as needed to deliver service messages, password resets, ticket notifications, and billing communication.'],
            ['Changes', 'This list should be updated when a new production subprocessor is added or when a provider role materially changes.'],
        ],
    ],
    'security' => [
        'title' => 'Security',
        'intro' => 'A public security page gives customers confidence and tells them how to report issues.',
        'sections' => [
            ['Application controls', 'FoxDesk Cloud uses authenticated sessions, CSRF tokens, role-aware permissions, optional 2FA support, upload restrictions, and tenant-aware data access.'],
            ['Infrastructure', 'Production is planned for Hetzner behind Cloudflare with TLS, health checks, backups, R2 storage, and environment-managed secrets.'],
            ['Billing security', 'Payments are processed by Stripe. FoxDesk Cloud stores Stripe customer and subscription identifiers, not raw card data.'],
            ['Vulnerability reports', 'Report suspected vulnerabilities to support@foxdesk.net with steps to reproduce and impact. Please avoid accessing or modifying data that is not yours.'],
            ['Responsible disclosure', 'We aim to acknowledge security reports, investigate impact, and deploy fixes before public detail is shared.'],
        ],
    ],
];

if (!isset($documents[$type])) {
    $type = 'privacy';
}

$doc = $documents[$type];
$nav = [
    'privacy' => 'Privacy',
    'terms' => 'Terms',
    'dpa' => 'DPA',
    'refunds' => 'Refunds',
    'subprocessors' => 'Subprocessors',
    'security' => 'Security',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($doc['title']); ?> - FoxDesk Cloud</title>
    <style>
        :root { color-scheme: light; --ink:#111827; --muted:#667085; --line:#e5e7eb; --bg:#f7f8fb; --panel:#fff; }
        body { margin:0; font-family:Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background:var(--bg); color:var(--ink); }
        .legal-shell { max-width:980px; margin:0 auto; padding:28px 18px 48px; }
        .legal-top { display:flex; justify-content:space-between; gap:16px; align-items:center; margin-bottom:22px; }
        .legal-brand { font-weight:820; font-size:17px; }
        .legal-nav { display:flex; gap:8px; flex-wrap:wrap; }
        .legal-nav a { border:1px solid var(--line); border-radius:999px; padding:7px 11px; color:var(--muted); text-decoration:none; font-size:13px; font-weight:700; background:var(--panel); }
        .legal-nav a.active { color:var(--ink); border-color:#111827; }
        .legal-card { background:var(--panel); border:1px solid var(--line); border-radius:16px; padding:26px; box-shadow:0 16px 45px rgba(15,23,42,.06); }
        h1 { margin:0; font-size:34px; line-height:1.05; letter-spacing:0; }
        .intro { color:var(--muted); margin:12px 0 0; max-width:700px; line-height:1.6; }
        .updated { margin-top:10px; color:var(--muted); font-size:13px; }
        .section { border-top:1px solid var(--line); padding-top:18px; margin-top:18px; }
        h2 { margin:0 0 7px; font-size:17px; }
        p { margin:0; color:var(--muted); line-height:1.65; }
        .notice { margin-top:22px; font-size:13px; color:var(--muted); }
        @media (max-width:640px) { .legal-top { align-items:flex-start; flex-direction:column; } .legal-card { padding:20px; } h1 { font-size:28px; } }
    </style>
</head>
<body>
    <main class="legal-shell">
        <header class="legal-top">
            <a class="legal-brand" href="<?php echo e(url('cloud')); ?>">FoxDesk Cloud</a>
            <nav class="legal-nav" aria-label="Legal navigation">
                <?php foreach ($nav as $key => $label): ?>
                    <a class="<?php echo $key === $type ? 'active' : ''; ?>" href="<?php echo e(url('legal', ['type' => $key])); ?>"><?php echo e($label); ?></a>
                <?php endforeach; ?>
            </nav>
        </header>
        <article class="legal-card">
            <h1><?php echo e($doc['title']); ?></h1>
            <p class="intro"><?php echo e($doc['intro']); ?></p>
            <div class="updated">Last updated: <?php echo e($updated); ?></div>
            <?php foreach ($doc['sections'] as $section): ?>
                <section class="section">
                    <h2><?php echo e($section[0]); ?></h2>
                    <p><?php echo e($section[1]); ?></p>
                </section>
            <?php endforeach; ?>
            <p class="notice">Review with counsel before relying on this page as final legal advice for paid public subscriptions.</p>
        </article>
    </main>
</body>
</html>
