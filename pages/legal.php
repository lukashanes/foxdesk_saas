<?php
$type = strtolower((string) ($_GET['type'] ?? 'privacy'));
$updated = 'May 27, 2026';

$operator = [
    'name' => 'Aenze s.r.o.',
    'company_id' => '28534395',
    'vat_id' => 'CZ28534395',
    'address' => 'Moskevska 1842, 272 04 Kladno, Czech Republic',
    'support' => 'support@foxdesk.net',
    'billing' => 'billing@foxdesk.net',
];

if ($type === 'subprocessors') {
    http_response_code(404);
    echo 'Not found.';
    exit;
}

$documents = [
    'privacy' => [
        'title' => 'Privacy Policy',
        'intro' => 'This Privacy Policy explains how ' . $operator['name'] . ' processes personal data in connection with FoxDesk Cloud.',
        'sections' => [
            ['Controller and operator', $operator['name'] . ', Company ID ' . $operator['company_id'] . ', VAT ID ' . $operator['vat_id'] . ', registered office ' . $operator['address'] . ', operates FoxDesk Cloud. For account, billing, website, and service administration data, Aenze s.r.o. acts as the controller. For customer workspace content entered by customers, Aenze s.r.o. generally acts as processor on behalf of the workspace owner.'],
            ['Data we process', 'We process account identifiers, names, email addresses, authentication and security data, workspace configuration, customer and organization records, tickets, comments, attachments, time logs, reports, notification metadata, billing identifiers, invoices, payment status, audit records, technical logs, and support communications.'],
            ['Why we process data', 'We process data to provide and secure FoxDesk Cloud, authenticate users, deliver support workflows, send service notifications, maintain backups, process subscriptions and invoices, prevent abuse, comply with legal obligations, and communicate with customers about the service.'],
            ['Legal bases', 'Depending on the context, processing is based on performance of a contract, legitimate interests in operating and securing the service, compliance with legal obligations, consent where required, and the customer instructions documented in the Data Processing Addendum for workspace content.'],
            ['Customer workspace content', 'Workspace owners decide what personal data is placed into tickets, clients, attachments, reports, and comments. Customers must ensure they have a lawful basis for the data they submit and must configure user access appropriately. Aenze s.r.o. does not sell workspace content or use it for advertising.'],
            ['Sharing and providers', 'We share data only where needed to operate the service, including hosting, storage, email delivery, security, billing, and support. Payment data is handled by Stripe; FoxDesk Cloud stores Stripe customer and subscription identifiers but not raw card numbers.'],
            ['Retention', 'Workspace data is retained while an account, trial, or subscription is active. After termination, data may be retained for a limited period for export, recovery, security, tax, accounting, dispute handling, and backup integrity. Operational logs are kept only as long as reasonably needed.'],
            ['International transfers', 'Where a provider processes data outside the EEA, we rely on appropriate safeguards such as EU Standard Contractual Clauses, adequacy decisions, or equivalent contractual and technical protections where applicable.'],
            ['Your rights', 'Where GDPR applies, individuals may request access, correction, deletion, restriction, portability, or objection. Some requests relating to workspace content must be handled by the customer as controller. Requests can be sent to ' . $operator['support'] . '.'],
            ['Security', 'We use access controls, tenant separation, TLS, password hashing, CSRF protection, role-based permissions, audit/security logging, backup controls, and operational monitoring. No online service can guarantee absolute security, but we maintain reasonable technical and organizational measures.'],
        ],
    ],
    'terms' => [
        'title' => 'Terms of Service',
        'intro' => 'These Terms govern access to and use of FoxDesk Cloud.',
        'sections' => [
            ['Service provider', 'FoxDesk Cloud is provided by ' . $operator['name'] . ', Company ID ' . $operator['company_id'] . ', VAT ID ' . $operator['vat_id'] . ', registered office ' . $operator['address'] . '.'],
            ['The service', 'FoxDesk Cloud provides hosted helpdesk workspaces with users, clients, organizations, tickets, attachments, time tracking, reports, notifications, billing administration, and managed operational components. The open-source/self-hosted FoxDesk edition remains separate from the paid hosted service.'],
            ['Accounts and authority', 'A person creating or administering a workspace confirms that they are authorized to bind the relevant organization. Workspace owners are responsible for user invitations, role assignments, account security, billing details, and all activity under their workspace.'],
            ['Acceptable use', 'The service must not be used for illegal activity, malware, spam, harassment, infringement, credential harvesting, unauthorized scanning, excessive automated traffic, or activity that disrupts FoxDesk Cloud, its providers, or other customers.'],
            ['Customer data', 'Customers keep ownership of their workspace data. Customers grant Aenze s.r.o. the limited right to host, store, transmit, back up, and process the data only as needed to provide and support the service. Customers are responsible for ensuring that submitted data is lawful and appropriate for a helpdesk system.'],
            ['Billing', 'Subscriptions are billed monthly through Stripe. Fees may include a base subscription, metered storage overage, applicable taxes, and payment recovery charges where permitted. Failed payments may result in restricted access, suspension, or cancellation.'],
            ['Cancellation', 'A workspace owner may cancel future renewals before the next billing period. Unless stated otherwise, cancellation stops future charges but does not automatically refund the current paid period. Refund handling is described in the Refund and Cancellation Policy.'],
            ['Availability and changes', 'We aim to operate FoxDesk Cloud with reasonable care, backups, monitoring, and maintenance practices. We may update, improve, or modify features where needed for reliability, security, compliance, or product development.'],
            ['Suspension and termination', 'Access may be suspended or terminated for non-payment, abuse, legal risk, security risk, breach of these Terms, or operation that threatens the platform. Where reasonably possible, customers will be given an opportunity to export data before deletion.'],
            ['Liability', 'To the maximum extent permitted by law, FoxDesk Cloud is provided without guarantees of uninterrupted or error-free operation. Aenze s.r.o. is not liable for indirect loss, lost profit, lost business, or damage caused by customer configuration, third-party services, or unlawful customer content.'],
            ['Governing law', 'These Terms are governed by the laws of the Czech Republic, unless mandatory consumer or data protection law requires otherwise. Business disputes should first be attempted to be resolved in good faith.'],
        ],
    ],
    'dpa' => [
        'title' => 'Data Processing Addendum',
        'intro' => 'This Data Processing Addendum describes how Aenze s.r.o. processes customer workspace personal data for FoxDesk Cloud.',
        'sections' => [
            ['Parties and roles', 'For customer workspace content, the customer is the controller and Aenze s.r.o. is the processor. If the customer processes data on behalf of another controller, the customer remains responsible for having the required authority to instruct Aenze s.r.o.'],
            ['Subject matter', 'Processing covers the operation of a hosted FoxDesk workspace, including ticket management, customer records, attachments, time tracking, reporting, notifications, user administration, backups, security, and support.'],
            ['Categories of data', 'Workspace data may include names, email addresses, organization details, ticket content, comments, uploaded files, communication metadata, internal notes, work logs, reports, user roles, technical identifiers, and audit/security logs.'],
            ['Categories of data subjects', 'Data subjects may include customer employees, agents, client contacts, end users, support requesters, suppliers, contractors, and other people whose data is entered into the workspace by the customer or its users.'],
            ['Customer instructions', 'Aenze s.r.o. processes workspace personal data only on documented customer instructions, including this DPA, the Terms, product settings, support requests, and lawful written instructions. Aenze s.r.o. will inform the customer if an instruction appears to violate applicable data protection law.'],
            ['Confidentiality', 'Personnel with access to workspace data are required to keep such data confidential and may access it only where needed for operation, support, security, or legal compliance.'],
            ['Security measures', 'Aenze s.r.o. maintains reasonable technical and organizational measures, including tenant-aware access controls, TLS, password hashing, CSRF protection, upload controls, backup controls, logging, restricted operational access, and separation of production secrets from source code.'],
            ['Approved providers', 'Aenze s.r.o. may use carefully selected third-party providers where needed to provide FoxDesk Cloud, including hosting, storage, email delivery, security, billing, and operational services. Aenze s.r.o. remains responsible for provider use under applicable data protection law and requires appropriate contractual or equivalent safeguards. Provider details can be supplied to customers where legally required.'],
            ['Assistance', 'Taking into account the nature of processing, Aenze s.r.o. will reasonably assist the customer with data subject requests, security obligations, incident handling, and data protection impact assessments where required and technically possible.'],
            ['Personal data breach', 'Aenze s.r.o. will investigate confirmed security incidents involving customer workspace personal data and notify affected customers without undue delay after becoming aware of a personal data breach.'],
            ['Return and deletion', 'On termination, customer data may be exportable where technically possible. Data will be deleted or anonymized after the retention period, subject to backups, legal obligations, accounting, security, dispute handling, and legitimate operational needs.'],
            ['Audits', 'Aenze s.r.o. will provide reasonable information needed to demonstrate compliance with this DPA. Audits must be proportionate, protect other customers and platform security, and be agreed in advance.'],
        ],
    ],
    'refunds' => [
        'title' => 'Refund and Cancellation Policy',
        'intro' => 'This policy explains how FoxDesk Cloud monthly subscription cancellation and refund requests are handled.',
        'sections' => [
            ['Monthly billing', 'FoxDesk Cloud is billed monthly through Stripe. A subscription covers access to a hosted workspace for the current billing period and may include metered storage overage.'],
            ['Cancellation', 'Workspace owners can request cancellation before the next renewal. Cancellation stops future renewals but normally does not end access immediately for the already paid period.'],
            ['Refund eligibility', 'Refunds are not automatic. Aenze s.r.o. may approve a refund for duplicate charges, billing mistakes, accidental renewal reported promptly, or a confirmed service failure that materially prevented use of the paid service.'],
            ['Non-refundable cases', 'Refunds are generally not provided for unused time after ordinary cancellation, customer misconfiguration, lack of usage, blocked access caused by breach of the Terms, or issues caused by third-party systems outside FoxDesk Cloud control.'],
            ['Storage overage', 'Metered storage overage is based on recorded usage for the billing period. If a customer believes usage was measured incorrectly, Aenze s.r.o. will review the records and correct the invoice where appropriate.'],
            ['How to request a refund', 'Send refund or billing requests to ' . $operator['billing'] . ' and include workspace name, billing email, invoice number, and a short explanation. We may request additional verification before changing billing records.'],
        ],
    ],
    'security' => [
        'title' => 'Security',
        'intro' => 'This page summarizes the security practices used for FoxDesk Cloud.',
        'sections' => [
            ['Application security', 'FoxDesk Cloud uses authenticated sessions, CSRF tokens, role-based permissions, tenant-aware data access, optional 2FA support, upload restrictions, security logs, and administrative controls for sensitive actions.'],
            ['Infrastructure security', 'Production is operated behind Cloudflare with TLS and proxy controls. The application runs on managed server infrastructure with separate environment-managed secrets, health checks, backups, and monitored service containers.'],
            ['Data separation', 'Customer workspaces are separated by tenant-aware database records and permission checks. Platform administration is separated from customer workspace administration.'],
            ['Payments', 'Payments are processed by Stripe. FoxDesk Cloud stores billing status and Stripe identifiers, but does not store raw card numbers or card security codes.'],
            ['Backups and recovery', 'Backups and storage controls are used to support recovery from operational failure. Backup access should be limited to authorized operational personnel and retained only as long as needed.'],
            ['Responsible disclosure', 'Report suspected vulnerabilities to ' . $operator['support'] . ' with affected URL, steps to reproduce, expected impact, and any relevant screenshots or logs. Do not access, modify, delete, or disclose data that is not yours.'],
            ['Limitations', 'Security is a shared responsibility. Customers must manage user access, remove users who no longer need access, use strong passwords and 2FA where available, and avoid uploading unnecessary sensitive data.'],
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
        :root { color-scheme: light; --ink:#111827; --muted:#667085; --line:#e5e7eb; --bg:#f7f8fb; --panel:#fff; --blue:#2557d6; }
        * { box-sizing: border-box; }
        body { margin:0; font-family:Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background:var(--bg); color:var(--ink); }
        a { color:inherit; }
        .legal-shell { max-width:1040px; margin:0 auto; padding:28px 18px 56px; }
        .legal-top { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; margin-bottom:22px; }
        .legal-brand { font-weight:820; font-size:17px; text-decoration:none; }
        .legal-nav { display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
        .legal-nav a { border:1px solid var(--line); border-radius:999px; padding:7px 11px; color:var(--muted); text-decoration:none; font-size:13px; font-weight:700; background:var(--panel); }
        .legal-nav a.active { color:var(--ink); border-color:#111827; }
        .legal-card { background:var(--panel); border:1px solid var(--line); border-radius:14px; padding:30px; box-shadow:0 16px 45px rgba(15,23,42,.06); }
        h1 { margin:0; font-size:36px; line-height:1.05; letter-spacing:0; }
        .intro { color:var(--muted); margin:12px 0 0; max-width:760px; line-height:1.65; }
        .updated { margin-top:12px; color:var(--muted); font-size:13px; }
        .section { border-top:1px solid var(--line); padding-top:18px; margin-top:18px; }
        h2 { margin:0 0 7px; font-size:17px; }
        p { margin:0; color:var(--muted); line-height:1.68; }
        .notice { margin-top:22px; font-size:13px; color:var(--muted); }
        .contact { margin-top:22px; padding:14px 16px; border:1px solid #dbe4ff; border-radius:12px; background:#f7f9ff; color:#1f2f5f; font-size:14px; line-height:1.55; }
        .contact a { color:var(--blue); font-weight:700; }
        @media (max-width:640px) { .legal-top { flex-direction:column; } .legal-nav { justify-content:flex-start; } .legal-card { padding:20px; } h1 { font-size:29px; } }
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
            <div class="contact">
                Operator: <?php echo e($operator['name']); ?>, Company ID <?php echo e($operator['company_id']); ?>, registered office <?php echo e($operator['address']); ?>.
                Support: <a href="mailto:<?php echo e($operator['support']); ?>"><?php echo e($operator['support']); ?></a>.
                Billing: <a href="mailto:<?php echo e($operator['billing']); ?>"><?php echo e($operator['billing']); ?></a>.
            </div>
            <p class="notice">This page is operational contract copy for FoxDesk Cloud and should still be reviewed by counsel before final paid public launch.</p>
        </article>
    </main>
</body>
</html>
