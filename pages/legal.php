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
    'supervisory_authority' => 'Office for Personal Data Protection of the Czech Republic (UOOU), https://www.uoou.cz',
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
            ['Controller and service provider', $operator['name'] . ', Company ID ' . $operator['company_id'] . ', VAT ID ' . $operator['vat_id'] . ', registered office ' . $operator['address'] . ', operates FoxDesk Cloud. For account administration, billing, website, security, and support data, Aenze s.r.o. acts as controller. For ticket, client, attachment, report, and other workspace content entered by a customer, Aenze s.r.o. normally acts as processor for the workspace owner.'],
            ['Service scope', 'FoxDesk Cloud is intended for business and professional use. It is not intended for storing special categories of personal data, health data, payment card numbers, government identifiers, or other highly sensitive data unless a separate written agreement allows it.'],
            ['Processing purposes and legal bases', 'Account registration, login, workspace administration, support, and billing are processed to perform the contract. Security logs, abuse prevention, service diagnostics, backups, and service improvement are processed for legitimate interests in operating and protecting the service. Accounting, tax, and legal records are processed to comply with legal obligations. Optional marketing or non-essential cookies are used only where a valid legal basis exists.'],
            ['Categories of personal data', 'We may process names, email addresses, account identifiers, authentication data, roles, workspace settings, client and organization records, tickets, comments, attachments, time logs, reports, notification metadata, billing identifiers, invoice and payment status, IP addresses, device/browser metadata, audit logs, security logs, and support communications.'],
            ['Customer workspace content', 'The customer controls what is entered into tickets, clients, comments, reports, and files. The customer is responsible for having a lawful basis for that content, limiting user access, and avoiding unnecessary sensitive data. Aenze s.r.o. does not sell workspace content and does not use it for advertising.'],
            ['Recipients and providers', 'Personal data may be processed by carefully selected providers necessary for hosting, storage, email delivery, billing, security, monitoring, backups, and support. Stripe processes payments and billing workflows; FoxDesk Cloud stores Stripe customer and subscription identifiers but not raw card numbers or card security codes. Provider details are supplied to customers where legally required.'],
            ['International transfers', 'If personal data is transferred outside the EEA, Aenze s.r.o. relies on an adequacy decision, EU Standard Contractual Clauses, or another legally available transfer safeguard where required.'],
            ['Retention', 'Workspace data is retained while the workspace is active and for a limited period afterwards for export, recovery, dispute handling, security, accounting, tax, and backup integrity. Billing and accounting records are retained as required by law. Security and operational logs are retained only as long as reasonably needed for security, reliability, and abuse prevention.'],
            ['Data subject rights', 'Where GDPR applies, individuals may request access, correction, deletion, restriction, portability, or objection. Requests concerning customer workspace content may need to be handled by the customer as controller. Requests can be sent to ' . $operator['support'] . '. Individuals may also contact the supervisory authority: ' . $operator['supervisory_authority'] . '.'],
            ['Security', 'Aenze s.r.o. uses reasonable technical and organizational measures, including TLS, password hashing, CSRF protection, role-based access, tenant-aware permissions, upload controls, backups, security logs, and restricted operational access. No online service can guarantee absolute security.'],
        ],
    ],
    'terms' => [
        'title' => 'Terms of Service',
        'intro' => 'These Terms govern access to and use of FoxDesk Cloud.',
        'sections' => [
            ['Provider', 'FoxDesk Cloud is provided by ' . $operator['name'] . ', Company ID ' . $operator['company_id'] . ', VAT ID ' . $operator['vat_id'] . ', registered office ' . $operator['address'] . '.'],
            ['Business use only', 'FoxDesk Cloud is offered for business and professional use. By creating or administering a workspace, the user confirms that they act for a business or organization and have authority to bind that organization. Consumer rights that cannot be excluded by law remain unaffected.'],
            ['Contract formation', 'A contract is formed when a workspace is created, a subscription is started, or the customer otherwise accepts these Terms. If a separate written agreement signed by Aenze s.r.o. conflicts with these Terms, the separate written agreement prevails for that customer.'],
            ['Service description', 'FoxDesk Cloud provides hosted helpdesk workspaces with users, clients, organizations, tickets, attachments, time tracking, reports, notifications, billing administration, and managed operational components. The open-source/self-hosted FoxDesk edition is separate from the hosted paid service.'],
            ['Customer responsibilities', 'The customer is responsible for account security, invited users, role assignments, workspace configuration, customer content, lawful use of the service, billing information, and all activity under its workspace. The customer must promptly remove users who no longer need access.'],
            ['Acceptable use', 'The service must not be used for unlawful content, malware, spam, harassment, infringement, credential harvesting, unauthorized scanning, excessive automated traffic, attempts to bypass security controls, or activity that may harm Aenze s.r.o., providers, the platform, or other customers.'],
            ['Customer data', 'Customers retain ownership of their workspace data. Customers grant Aenze s.r.o. the limited right to host, store, transmit, back up, process, and support that data only as needed to provide FoxDesk Cloud, comply with law, protect the platform, or handle billing and support.'],
            ['Plans, prices, and taxes', 'Subscription fees, included storage, and metered overage are shown on the public pricing page or in the customer account. Prices exclude taxes unless stated otherwise. Aenze s.r.o. may change prices for future billing periods by giving reasonable notice or by publishing updated pricing before the next renewal.'],
            ['Billing and payment', 'Subscriptions are billed through Stripe. The customer authorizes recurring charges for the selected plan, metered overage, taxes, and other agreed fees. Failed payment may result in reminder emails, restricted access, suspension, cancellation, or data export/deletion after a reasonable period.'],
            ['No guaranteed SLA', 'Unless a separate written SLA is agreed, FoxDesk Cloud is provided on a commercially reasonable efforts basis. Maintenance, outages, third-party provider issues, abuse prevention, security incidents, or emergency changes may affect availability.'],
            ['Suspension and termination', 'Aenze s.r.o. may suspend or terminate access for non-payment, breach of these Terms, legal risk, security risk, abuse, or platform harm. Where reasonably possible and lawful, the customer will be allowed to export data before permanent deletion.'],
            ['Exclusive remedy', 'If FoxDesk Cloud fails materially due to a fault attributable to Aenze s.r.o., the customer must notify Aenze s.r.o. promptly. Aenze s.r.o. may, at its choice, repair the issue, provide a service credit, refund the affected paid period, or terminate the affected subscription. This is the customer\'s primary and exclusive contractual remedy to the maximum extent permitted by law.'],
            ['Limitation of liability', 'To the maximum extent permitted by law, Aenze s.r.o. is not liable for indirect loss, lost profit, lost revenue, lost business, loss of goodwill, loss caused by customer configuration, unlawful customer content, third-party services, or data entered by the customer. Aenze s.r.o.\'s total contractual liability is capped at the fees paid by the customer for the affected workspace during the three months before the event giving rise to the claim, or EUR 100 if no fees were paid. Nothing in these Terms excludes liability that cannot be excluded under mandatory law.'],
            ['Changes to terms', 'Aenze s.r.o. may update these Terms for legal, security, operational, or product reasons. Material changes apply from the next renewal period or after reasonable notice, unless an immediate change is required by law or security needs. Continued use after the effective date means acceptance of the updated Terms.'],
            ['Governing law and disputes', 'These Terms are governed by the laws of the Czech Republic, excluding conflict-of-law rules. The parties will first try to resolve disputes in good faith. Courts of the Czech Republic have jurisdiction unless mandatory law requires otherwise.'],
        ],
    ],
    'dpa' => [
        'title' => 'Data Processing Addendum',
        'intro' => 'This Data Processing Addendum forms part of the FoxDesk Cloud Terms where the customer acts as controller and Aenze s.r.o. acts as processor for customer workspace personal data.',
        'sections' => [
            ['Parties and roles', 'The customer is controller of customer workspace personal data. Aenze s.r.o. is processor for that data. If the customer acts as processor for another controller, the customer confirms that it has authority to instruct Aenze s.r.o. and remains responsible for passing through required obligations.'],
            ['Subject matter and duration', 'Processing covers the provision of a hosted FoxDesk workspace. Processing lasts for the term of the customer subscription and any post-termination period needed for export, deletion, backup expiry, legal compliance, security, accounting, or dispute handling.'],
            ['Nature and purpose', 'Aenze s.r.o. hosts, stores, transmits, backs up, secures, monitors, and supports customer workspace data so the customer can operate helpdesk, ticketing, client management, time tracking, reporting, notifications, and administration workflows.'],
            ['Types of personal data', 'Workspace personal data may include names, emails, organizations, ticket content, comments, attachments, internal notes, time logs, reports, user roles, technical identifiers, IP addresses, audit logs, security logs, and communication metadata.'],
            ['Categories of data subjects', 'Data subjects may include customer employees, agents, client contacts, end users, requesters, suppliers, contractors, and other people whose data is entered into the workspace by the customer or its users.'],
            ['Customer instructions', 'Aenze s.r.o. processes workspace personal data only on documented instructions from the customer, including the Terms, this DPA, product configuration, support requests, and lawful written instructions. Aenze s.r.o. will inform the customer if an instruction appears to violate applicable data protection law.'],
            ['Processor obligations', 'Aenze s.r.o. will keep workspace personal data confidential, ensure that authorized personnel are bound by confidentiality, implement reasonable technical and organizational measures, assist with customer GDPR obligations where technically possible, and make reasonable compliance information available to the customer.'],
            ['Security measures', 'Measures include tenant-aware access controls, TLS, password hashing, CSRF protection, upload controls, backup controls, logging, restricted operational access, separation of production secrets from source code, and provider controls appropriate to the service.'],
            ['Sub-processors and providers', 'The customer gives general authorization for Aenze s.r.o. to use providers necessary for hosting, storage, email delivery, billing, security, monitoring, backups, and support. Aenze s.r.o. remains responsible for requiring appropriate data protection obligations from such providers. Provider details and material changes will be supplied to customers where legally required or reasonably requested.'],
            ['International transfers', 'Where provider processing involves transfers outside the EEA, Aenze s.r.o. will use legally available transfer mechanisms where required, such as adequacy decisions or EU Standard Contractual Clauses.'],
            ['Data subject requests', 'Aenze s.r.o. will reasonably assist the customer with data subject requests relating to workspace personal data. If Aenze s.r.o. receives a request directly and can identify the relevant customer, it may refer the requester to the customer unless legally required to respond directly.'],
            ['Personal data breach', 'Aenze s.r.o. will investigate confirmed incidents involving customer workspace personal data and notify affected customers without undue delay after becoming aware of a personal data breach, including available information reasonably needed for the customer to meet its own obligations.'],
            ['Return and deletion', 'On termination, customer data may be exported where technically possible. After the applicable retention period, Aenze s.r.o. will delete or anonymize customer workspace data, subject to backups, legal obligations, accounting, security, and dispute handling.'],
            ['Audits', 'The customer may request reasonable information to verify compliance with this DPA. Any audit must be proportionate, scheduled in advance, protect other customers and platform security, and avoid exposing secrets, infrastructure details, or confidential information.'],
        ],
    ],
    'refunds' => [
        'title' => 'Refund and Cancellation Policy',
        'intro' => 'This policy explains cancellation, credits, and refunds for FoxDesk Cloud.',
        'sections' => [
            ['Monthly subscriptions', 'FoxDesk Cloud is billed monthly through Stripe. A paid period starts when payment is accepted and normally remains available until the end of that period.'],
            ['Cancellation', 'The workspace owner may cancel before the next renewal. Cancellation stops future renewals but does not automatically refund the current paid period.'],
            ['General rule', 'Fees for an active monthly SaaS period are generally non-refundable. This keeps pricing simple and avoids manual prorating for ordinary cancellation or lack of use.'],
            ['When Aenze may refund or credit', 'Aenze s.r.o. may, at its discretion, issue a full or partial refund or service credit for duplicate charges, billing errors, accidental renewal reported promptly, measured storage error, or a material service failure attributable to Aenze s.r.o. that prevented normal use of the paid service.'],
            ['Exclusive practical remedy', 'Where a serious service issue is confirmed, Aenze s.r.o. may refund or credit the affected paid period and may terminate the subscription. After such refund or credit and termination, no further contractual remedy is owed to the maximum extent permitted by law. Mandatory legal rights remain unaffected.'],
            ['Non-refundable cases', 'Refunds are generally not provided for unused time, customer misconfiguration, forgotten cancellation, lack of usage, blocked access caused by breach of the Terms, third-party outages outside Aenze s.r.o. control, or unlawful customer content.'],
            ['How to request', 'Send requests to ' . $operator['billing'] . ' with workspace name, billing email, invoice number, and a short explanation. Aenze s.r.o. may request verification before changing billing records. Approved refunds are normally returned through the original Stripe payment method.'],
        ],
    ],
    'security' => [
        'title' => 'Security',
        'intro' => 'This page summarizes practical security measures and customer responsibilities for FoxDesk Cloud.',
        'sections' => [
            ['Application controls', 'FoxDesk Cloud uses authenticated sessions, CSRF tokens, role-based permissions, tenant-aware data access, upload controls, audit/security logs, and administrative controls for sensitive actions.'],
            ['Infrastructure controls', 'Production is operated behind Cloudflare with TLS/proxy controls and runs on server infrastructure with environment-managed secrets, health checks, backups, and monitored service containers.'],
            ['Data separation', 'Customer workspaces are separated by tenant-aware database records and permission checks. Platform administration is separate from customer workspace administration.'],
            ['Payments', 'Payments are processed by Stripe. FoxDesk Cloud stores billing status and Stripe identifiers, not raw card numbers or card security codes.'],
            ['Backups and recovery', 'Backups and storage controls support operational recovery. Backup access is limited to authorized operational personnel and backups may persist for a limited retention period before expiry.'],
            ['Customer responsibilities', 'Customers must manage user access, use strong passwords, enable 2FA where available, remove users who no longer need access, avoid unnecessary sensitive data, and promptly report suspected compromise.'],
            ['Responsible disclosure', 'Report suspected vulnerabilities to ' . $operator['support'] . ' with affected URL, steps to reproduce, expected impact, and relevant screenshots or logs. Do not access, modify, delete, or disclose data that is not yours.'],
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
    <link href="theme.css?v=<?php echo e((string) APP_VERSION); ?>" rel="stylesheet">
</head>
<body class="legal-page">
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
            <p class="notice">If a mandatory law gives a customer or data subject rights that cannot be limited by contract, those mandatory rights remain unaffected.</p>
        </article>
    </main>
</body>
</html>
