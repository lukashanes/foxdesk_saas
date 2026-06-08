<?php
$type = strtolower((string) ($_GET['type'] ?? 'privacy'));
$updated = 'June 8, 2026';

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
        'intro' => 'This Privacy Policy explains how ' . $operator['name'] . ' handles personal data when you visit our website, create a FoxDesk Cloud workspace, use the service, or contact us.',
        'sections' => [
            ['Who we are', $operator['name'] . ', Company ID ' . $operator['company_id'] . ', VAT ID ' . $operator['vat_id'] . ', with registered office at ' . $operator['address'] . ', operates FoxDesk Cloud. For our website, account administration, billing, support, and service operation, we act as the controller. For ticket and workspace content entered by a customer, we usually act as a processor for that customer.'],
            ['What FoxDesk Cloud is for', 'FoxDesk Cloud is a business helpdesk and time tracking service. It is designed for ordinary business support data. Customers should not intentionally store payment card numbers, health records, government identifiers, or other highly sensitive information unless this has been separately agreed in writing.'],
            ['Data we may process', 'We may process account details, names, email addresses, roles, workspace settings, client and contact records, tickets, comments, attachments, time entries, reports, notification details, billing details, invoice status, IP addresses, device and browser information, security records, and messages sent to our support or billing contacts.'],
            ['Why we use personal data', 'We use personal data to provide and secure FoxDesk Cloud, create and manage accounts, operate workspaces, send service notifications, provide support, process billing, keep legally required records, prevent abuse, diagnose problems, improve reliability, and communicate about the service.'],
            ['Legal bases', 'Where GDPR applies, we rely on performance of a contract, compliance with legal obligations, legitimate interests in operating and protecting the service, and consent where consent is required. If we process workspace content for a customer, we do so on that customer\'s instructions.'],
            ['Customer workspace content', 'Customers decide what they and their users enter into a workspace. Customers are responsible for having a lawful reason to collect and use that content, for inviting the right users, and for removing data that should not be stored in FoxDesk Cloud. We do not sell workspace content and we do not use it for advertising.'],
            ['Service providers', 'We use trusted service providers to help us host the service, store files, send email, process payments, monitor reliability, provide security, and support customers. They may process personal data only as needed to provide those services to us. Details are available to customers where required by law or contract.'],
            ['International transfers', 'If personal data is transferred outside the European Economic Area, we use a legally available safeguard where required, such as an adequacy decision or Standard Contractual Clauses.'],
            ['How long we keep data', 'Workspace data is kept while the workspace is active. After cancellation or termination, data may be kept for a limited period for export, recovery, security, dispute handling, accounting, tax, and backup purposes. Billing and accounting records are kept for the period required by law.'],
            ['Your rights', 'Where GDPR applies, you may request access, correction, deletion, restriction, portability, or objection. Some requests about workspace content may need to be handled by the customer that owns the workspace. You can contact us at ' . $operator['support'] . '. You may also contact the Czech data protection authority: ' . $operator['supervisory_authority'] . '.'],
            ['Security', 'We use reasonable technical and organisational measures to protect personal data. No online service can be guaranteed to be perfectly secure, but we work to protect the service, limit access, and respond to reported issues.'],
        ],
    ],
    'terms' => [
        'title' => 'Terms of Service',
        'intro' => 'These Terms govern the use of FoxDesk Cloud. They are written for business customers and workspace users.',
        'sections' => [
            ['Provider', 'FoxDesk Cloud is provided by ' . $operator['name'] . ', Company ID ' . $operator['company_id'] . ', VAT ID ' . $operator['vat_id'] . ', with registered office at ' . $operator['address'] . '.'],
            ['Business use', 'FoxDesk Cloud is intended for business and professional use. If you create or administer a workspace for an organisation, you confirm that you are authorised to do so. Mandatory consumer rights, if any apply, are not affected.'],
            ['The service', 'FoxDesk Cloud provides hosted helpdesk workspaces for tickets, clients, users, time tracking, files, reports, notifications, and workspace administration. The hosted service is separate from any free or self-hosted edition of FoxDesk.'],
            ['Starting a workspace', 'A contract is formed when a workspace is created, a subscription is started, or these Terms are otherwise accepted. If we sign a separate written agreement with a customer, that agreement will apply where it conflicts with these Terms.'],
            ['Customer responsibilities', 'The customer is responsible for its users, passwords, roles, permissions, workspace settings, billing information, customer content, and lawful use of the service. The customer must remove users who no longer need access.'],
            ['Acceptable use', 'You must not use FoxDesk Cloud for unlawful activity, spam, malware, harassment, infringement, misleading messages, attempts to bypass security, excessive automated use, or anything that may harm the service, other customers, or third parties.'],
            ['Customer data', 'Customers keep ownership of their workspace data. We may host, store, transmit, back up, process, and support that data only as needed to provide FoxDesk Cloud, comply with law, protect the service, resolve disputes, or handle billing and support.'],
            ['Plans, prices, and taxes', 'Current prices, included storage, and any usage-based fees are shown on the pricing page, in the checkout flow, or in the customer account. Prices are exclusive of taxes unless stated otherwise. We may change future prices by giving reasonable notice or by publishing the new price before it applies.'],
            ['Billing and payment', 'Subscriptions are billed in advance unless stated otherwise. The customer authorises recurring charges for the selected plan, usage-based fees, taxes, and other agreed charges. If payment fails, we may send reminders, limit access, suspend the workspace, cancel the subscription, or delete data after a reasonable period.'],
            ['Trial period', 'A trial may be offered for a limited time. Unless billing is added before the trial ends, access may be limited or stopped after the trial. Trial availability may be changed or withdrawn for abuse or operational reasons.'],
            ['Availability', 'We aim to keep FoxDesk Cloud reliable, but we do not promise uninterrupted availability unless a separate written SLA says otherwise. Maintenance, outages, security work, third-party issues, or events outside our reasonable control may affect the service.'],
            ['Suspension and termination', 'We may suspend or terminate access for non-payment, breach of these Terms, legal risk, security risk, abuse, or harm to the service. Where reasonably possible and lawful, we will allow the customer to export data before permanent deletion.'],
            ['What happens if something goes wrong', 'If FoxDesk Cloud has a serious service problem caused by us, the customer should contact us promptly. We may fix the issue, provide a service credit, refund the affected paid period, or end the affected subscription. To the maximum extent permitted by law, this is the customer\'s main remedy.'],
            ['Limitation of liability', 'To the maximum extent permitted by law, we are not liable for indirect loss, lost profit, lost revenue, lost business, loss of goodwill, customer configuration, customer content, third-party services, or data entered by the customer. Our total contractual liability is limited to the fees paid for the affected workspace during the three months before the event giving rise to the claim, or EUR 100 if no fees were paid. Nothing in these Terms limits liability that cannot be limited by law.'],
            ['Changes', 'We may update these Terms for legal, security, operational, or product reasons. Material changes will apply after reasonable notice, at renewal, or sooner if required by law or security needs. Continued use after the effective date means acceptance of the updated Terms.'],
            ['Governing law', 'These Terms are governed by the laws of the Czech Republic. The parties will first try to resolve disputes in good faith. Courts of the Czech Republic have jurisdiction unless mandatory law requires another forum.'],
        ],
    ],
    'dpa' => [
        'title' => 'Data Processing Addendum',
        'intro' => 'This Data Processing Addendum applies when a customer uses FoxDesk Cloud to process personal data and ' . $operator['name'] . ' acts as processor for that customer.',
        'sections' => [
            ['Roles', 'The customer is the controller of personal data entered into its workspace. Aenze s.r.o. is the processor for that workspace data. If the customer processes data for another controller, the customer confirms that it has authority to instruct us.'],
            ['Subject matter and duration', 'We process workspace data to provide FoxDesk Cloud. Processing lasts while the workspace is active and for any limited period needed for export, deletion, backup expiry, legal compliance, accounting, security, or dispute handling.'],
            ['Purpose of processing', 'We process workspace data so the customer can operate helpdesk tickets, client records, user access, time tracking, reports, notifications, files, and related administration.'],
            ['Types of personal data', 'Workspace data may include names, emails, organisations, contact details, ticket content, comments, files, internal notes, time entries, reports, user roles, technical identifiers, logs, and communication metadata.'],
            ['Data subjects', 'Data subjects may include the customer\'s employees, agents, client contacts, requesters, suppliers, contractors, and other people whose data is entered into a workspace.'],
            ['Customer instructions', 'We process workspace data only on documented customer instructions, including the Terms, this DPA, product settings, support requests, and lawful written instructions. We will tell the customer if an instruction appears to violate applicable data protection law.'],
            ['Confidentiality', 'We require people who may access workspace data for us to keep it confidential and to access it only as needed to provide or protect the service.'],
            ['Security', 'We use reasonable technical and organisational measures appropriate to the service, the data, and the risk. Measures are designed to protect confidentiality, integrity, availability, and resilience.'],
            ['Assistance', 'We will reasonably assist the customer with data subject requests, security incidents, deletion or export requests, and other GDPR obligations where this is possible through the service or reasonable support.'],
            ['Sub-processors', 'The customer authorises us to use service providers needed to run FoxDesk Cloud. We remain responsible for requiring appropriate data protection obligations from them. We will provide provider information where required by law or reasonably requested under a customer agreement.'],
            ['International transfers', 'Where processing involves transfers outside the European Economic Area, we will use a legally available transfer mechanism where required.'],
            ['Personal data breach', 'If we become aware of a personal data breach affecting customer workspace data, we will investigate and notify affected customers without undue delay, with information reasonably available to us.'],
            ['Return and deletion', 'After termination, workspace data may be exported where technically possible. We will delete or anonymise workspace data after the applicable retention period, subject to backups, legal duties, accounting, security, and dispute handling.'],
            ['Audit information', 'The customer may request reasonable information to verify compliance with this DPA. Any review must be proportionate, scheduled in advance, and protect other customers, confidential information, and service security.'],
        ],
    ],
    'refunds' => [
        'title' => 'Refund and Cancellation Policy',
        'intro' => 'This policy explains cancellation, credits, and refunds for FoxDesk Cloud.',
        'sections' => [
            ['Monthly subscriptions', 'FoxDesk Cloud is normally billed monthly. A paid period starts when payment is accepted and normally remains available until the end of that period.'],
            ['Trial period', 'If a free trial is offered, no subscription fee is charged for the trial itself. To continue after the trial, the customer must add billing details before access is limited or stopped.'],
            ['Cancellation', 'The workspace owner may cancel before the next renewal. Cancellation stops future renewals but does not automatically refund the current paid period.'],
            ['General rule', 'Fees for an active monthly SaaS period are generally non-refundable. This keeps pricing simple and avoids manual prorating for ordinary cancellation, lack of use, or forgotten cancellation.'],
            ['When we may refund or credit', 'We may, at our discretion, issue a full or partial refund or service credit for duplicate charges, clear billing errors, accidental renewal reported promptly, measured usage error, or a serious service failure caused by us that prevented normal use of the paid service.'],
            ['If something goes wrong', 'If a serious service issue is confirmed, we may refund or credit the affected paid period and may end the subscription. To the maximum extent permitted by law, once that refund or credit is provided, no further contractual remedy is owed for that issue.'],
            ['Non-refundable cases', 'Refunds are generally not provided for unused time, customer setup choices, customer content, lack of usage, access limited because of a Terms breach, third-party outages outside our reasonable control, or issues not reported within a reasonable time.'],
            ['How to request', 'Send requests to ' . $operator['billing'] . ' with the workspace name, billing email, invoice number if available, and a short explanation. We may ask for verification before changing billing records. Approved refunds are normally returned through the original payment method.'],
        ],
    ],
    'security' => [
        'title' => 'Security',
        'intro' => 'This page explains how we approach security for FoxDesk Cloud and what customers should do to protect their workspaces.',
        'sections' => [
            ['Our approach', 'We use reasonable technical and organisational measures to protect FoxDesk Cloud. Security is reviewed as the service changes, and we work to reduce risk without making the product unnecessarily difficult to use.'],
            ['Access control', 'Customer workspaces require user accounts. Customers can manage who has access and what role each user has. Administrative actions should be limited to trusted people.'],
            ['Data protection', 'We use secure connections, access controls, backups, logging, and operational controls appropriate to a hosted business service. We do not store raw payment card numbers.'],
            ['Reliability and recovery', 'We maintain backup and recovery processes designed to support service continuity and data recovery where reasonably possible. Backups may be kept for a limited period before expiry.'],
            ['Customer responsibilities', 'Customers should use strong passwords, enable available security features, invite only the right users, remove users who leave, protect their devices, avoid unnecessary sensitive data, and tell us promptly about suspected compromise.'],
            ['Reporting security issues', 'Report suspected vulnerabilities to ' . $operator['support'] . ' with the affected URL, steps to reproduce, expected impact, and relevant screenshots or logs. Please do not access, change, delete, or disclose data that is not yours.'],
            ['No absolute guarantee', 'No online service can be guaranteed to be completely secure. We will act reasonably to protect the service and respond to security reports.'],
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
