<?php
$type = strtolower((string) ($_GET['type'] ?? 'privacy'));
$updated = 'July 17, 2026';

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
        'intro' => $operator['name'] . ' processes personal data for FoxDesk Cloud as described below.',
        'sections' => [
            ['Controller and processor roles', $operator['name'] . ', Company ID ' . $operator['company_id'] . ', VAT ID ' . $operator['vat_id'] . ', registered office at ' . $operator['address'] . ', operates FoxDesk Cloud. We are the controller for website visits, accounts, billing, security, support, and our own service operations. For ticket, client, file, report, and workspace content entered by a customer, we normally act as processor and process that content for the customer.'],
            ['Business service only', 'FoxDesk Cloud is intended for business helpdesk and time tracking data. Customers must not intentionally upload payment card numbers, special categories of personal data, criminal offence data, national identifiers, medical data, or other sensitive regulated data unless we have expressly agreed to that in writing.'],
            ['Personal data processed', 'We may process names, email addresses, login and role details, company details, workspace settings, client and contact records, tickets, comments, internal notes, attachments, time entries, reports, notification records, billing and invoice details, IP addresses, device and browser data, security logs, and messages sent to support or billing contacts.'],
            ['Purposes', 'We process personal data to provide, secure, maintain, bill, support, improve, and protect FoxDesk Cloud; to create and manage accounts and workspaces; to send necessary service communications; to prevent abuse; to keep accounting and legal records; and to establish, exercise, or defend legal claims.'],
            ['Legal bases', 'Where GDPR applies, our legal bases are contract performance, legal obligation, legitimate interests in operating and protecting a paid business service, and consent where consent is legally required. Workspace content is processed on the customer\'s documented instructions.'],
            ['Customer responsibility', 'The customer determines the purposes and means of processing workspace content and controls what its users enter into the service. The customer is responsible for having a valid legal basis, giving required notices, handling data subject requests, configuring permissions, keeping account access secure, and deleting data that should no longer be stored. The customer must not instruct us to process data unlawfully. We do not sell workspace content and do not use it for advertising.'],
            ['Customer instructions and content', 'We process workspace content only to provide, secure, support, and administer the service, comply with documented customer instructions, or meet a legal obligation. We do not determine whether customer content is necessary, accurate, lawful, or suitable for the customer\'s business. We may reject, restrict, preserve, or remove content or instructions where reasonably necessary for security, legal compliance, abuse prevention, or protection of the service and third parties.'],
            ['Service providers', 'We may use professional service providers for hosting, storage, email delivery, payment processing, monitoring, security, support, accounting, and other operations needed to run FoxDesk Cloud. They may process personal data only for those services and under appropriate obligations. Provider details are supplied where required by law or customer agreement.'],
            ['Transfers outside the EEA', 'If personal data is transferred outside the European Economic Area, we rely on a lawful transfer mechanism where required, such as an adequacy decision, Standard Contractual Clauses, or another mechanism permitted by data protection law.'],
            ['Retention', 'Workspace data is kept while the workspace is active. After cancellation, expiry, suspension, or termination, we may retain data for export, recovery, security, backup expiry, billing, accounting, tax, legal compliance, dispute handling, and claim defence. We may delete or anonymise inactive or unpaid workspace data after a reasonable period.'],
            ['Data subject rights', 'Where GDPR applies, individuals may request access, correction, deletion, restriction, portability, or objection. Requests concerning customer workspace content may need to be handled by the customer as controller. Contact us at ' . $operator['support'] . '. Complaints may be made to the Czech data protection authority: ' . $operator['supervisory_authority'] . '.'],
            ['Security', 'We use reasonable technical and organisational measures for a hosted business service. No online service is risk-free. Customers remain responsible for their own users, devices, passwords, permissions, and the data they choose to store.'],
        ],
    ],
    'terms' => [
        'title' => 'Terms of Service',
        'intro' => 'FoxDesk Cloud is a business SaaS provided by ' . $operator['name'] . '. These Terms apply to all workspaces and users.',
        'sections' => [
            ['Provider', 'FoxDesk Cloud is provided by ' . $operator['name'] . ', Company ID ' . $operator['company_id'] . ', VAT ID ' . $operator['vat_id'] . ', with registered office at ' . $operator['address'] . '.'],
            ['Business customers only', 'FoxDesk Cloud is offered only for use in a trade, business, craft, or profession. A person creating, paying for, or administering a workspace confirms that the customer is acting for business or professional purposes and that the person has authority to bind that customer. We do not knowingly contract with consumers. If mandatory law nevertheless treats a customer as a consumer, rights that cannot lawfully be excluded remain unaffected.'],
            ['Service scope', 'The service provides hosted workspaces for tickets, clients, users, time tracking, files, reports, notifications, and administration. Features may be changed, added, removed, limited, or replaced where this is reasonable for security, reliability, legal, commercial, or product reasons.'],
            ['Contract formation', 'A contract is formed when the customer accepts these Terms and a workspace or trial is activated, or when a paid subscription is submitted through checkout. The person accepting these Terms confirms the business purpose and authority stated above. A separate written agreement signed by us prevails over these Terms only to the extent of a direct conflict.'],
            ['Customer obligations', 'The customer is responsible for all use of its workspace, including users, roles, permissions, passwords, devices, billing details, client data, ticket content, files, exports, integrations, legal notices, backups or exports needed for its own continuity, and compliance with laws applicable to the customer. Actions taken through a customer account, API token, integration, or authorised user are treated as customer actions.'],
            ['Prohibited use', 'The service must not be used for unlawful content, spam, malware, phishing, harassment, infringement, excessive automation, scraping, security testing without permission, bypassing access controls, resale without agreement, or activity that may harm the service, us, other customers, or third parties.'],
            ['Customer data and content rights', 'Customers retain their rights in workspace data. For the duration of the contract and any lawful retention period, the customer grants us a non-exclusive, worldwide, royalty-free right to host, copy, store, transmit, back up, process, display to authorised users, access, disclose, delete, or preserve that data solely as needed to provide and protect the service, comply with law and customer instructions, enforce these Terms, support customers, handle billing, investigate abuse, and defend legal claims. The customer represents and warrants that it has all rights, permissions, notices, and legal bases required for customer content, including personal data, attachments, trademarks, copyrighted material, and other third-party content.'],
            ['No monitoring duty', 'We do not undertake to pre-screen or continuously monitor customer content and are not responsible for its accuracy, legality, completeness, ownership, or business use. We may investigate reported content and may restrict, remove, preserve, or disclose content where reasonably necessary to comply with law, enforce these Terms, protect the service, or respond to a credible rights or security complaint.'],
            ['Plans, prices, and taxes', 'Prices, included limits, and usage-based fees are shown on the pricing page, checkout, invoice, or customer account. Prices are exclusive of VAT and other taxes unless stated otherwise. We may change future prices, storage fees, limits, and plan conditions by notice, publication, checkout update, or renewal terms.'],
            ['Payment', 'Paid subscriptions are charged in advance and renew automatically unless cancelled before renewal. The fee for each billing period becomes due when that period starts and is charged in exchange for making the service available during that period, whether or not the customer uses it. The customer authorises recurring charges for subscription fees, usage, taxes, and agreed charges. If payment fails or billing details are invalid, we may retry payment, contact the customer, restrict access, suspend the workspace, cancel the subscription, and delete data after a reasonable period.'],
            ['Trial and evaluation', 'A trial is a free, temporary evaluation of the service and may be limited. It gives the customer an opportunity to test suitability before purchasing and does not itself create a charge. We may refuse, shorten, suspend, or end a trial for abuse, duplicate accounts, payment risk, excessive use, operational reasons, or breach of these Terms. If a paid subscription is not started before the trial or grace period ends, access may be restricted or stopped and trial data may later be deleted.'],
            ['Cancellation', 'The workspace owner may cancel at any time through the available billing controls. Cancellation prevents the next renewal and takes effect at the end of the current paid billing period. Unless we suspend or terminate access under these Terms, the workspace remains available until that date. Cancellation does not reverse charges already incurred and does not create a pro-rated refund, credit, or right to recover unused time.'],
            ['Availability and changes', 'FoxDesk Cloud is provided on an as-is and as-available basis unless a separate written SLA states otherwise. We do not guarantee uninterrupted, error-free, or loss-free operation. Maintenance, outages, security work, third-party failures, network issues, or force majeure may affect availability.'],
            ['Suspension and termination', 'We may suspend, restrict, or terminate a workspace immediately where we reasonably believe there is non-payment, breach, abuse, legal risk, security risk, excessive load, harmful content, or risk to the service. We may also end a workspace on reasonable notice for commercial or operational reasons.'],
            ['Data export and deletion', 'Where technically available and lawful, the customer may export data before termination. We are not required to keep unpaid, suspended, expired, or terminated workspaces indefinitely. Deleted data may remain in backups until backup expiry.'],
            ['Remedies', 'If a serious paid-service failure caused by us is confirmed, we may repair the service, provide a service credit, refund the affected paid period, or terminate the affected subscription. To the maximum extent permitted by law, that is the customer\'s exclusive contractual remedy for the issue.'],
            ['No warranties', 'To the maximum extent permitted by law, we disclaim all warranties not expressly stated in these Terms, including implied warranties of merchantability, fitness for a particular purpose, non-infringement, uninterrupted operation, data loss prevention, and suitability for the customer\'s specific legal or business requirements.'],
            ['Limitation of liability', 'To the maximum extent permitted by law, we are not liable for indirect, incidental, special, punitive, or consequential loss; lost profit, revenue, savings, goodwill, business, contracts, or opportunities; loss or corruption of data; customer configuration; customer content; third-party services; or unauthorised use caused by the customer. To the maximum extent permitted by law, our aggregate liability arising from the service or these Terms is limited to the fees paid for the affected workspace during the three months before the event giving rise to the claim, or EUR 100 if no fees were paid. Nothing in these Terms excludes or limits liability, statutory duties, or remedies that applicable law does not permit the parties to exclude or limit.'],
            ['Indemnity', 'The customer must indemnify us against claims, losses, costs, and expenses arising from customer content, customer instructions, unlawful use of the service, breach of these Terms, or a dispute between the customer and its own users, clients, employees, contractors, or third parties.'],
            ['Changes to Terms', 'We may update these Terms for legal, security, operational, commercial, or product reasons. Updated Terms apply from the stated effective date, renewal, checkout, or continued use after notice. If a customer does not accept the update, its remedy is to stop using the service and cancel before the next renewal.'],
            ['Governing law and courts', 'These Terms are governed by the laws of the Czech Republic, excluding conflict-of-law rules. The courts of the Czech Republic have jurisdiction, unless mandatory law requires another forum.'],
        ],
    ],
    'dpa' => [
        'title' => 'Data Processing Addendum',
        'intro' => 'This Data Processing Addendum applies when ' . $operator['name'] . ' processes personal data as processor for a FoxDesk Cloud customer.',
        'sections' => [
            ['Roles', 'The customer is the controller of personal data entered into its workspace and remains responsible for the lawfulness, necessity, accuracy, retention, and use of that data. Aenze s.r.o. is the processor for that workspace data. If the customer processes data for another controller, the customer confirms that it has authority to instruct us and to appoint us as a sub-processor.'],
            ['Subject matter and duration', 'We process workspace data to provide FoxDesk Cloud. Processing lasts while the workspace is active and for any limited period needed for export, deletion, backup expiry, legal compliance, accounting, security, or dispute handling.'],
            ['Purpose of processing', 'We process workspace data so the customer can operate helpdesk tickets, client records, user access, time tracking, reports, notifications, files, and related administration.'],
            ['Types of personal data', 'Workspace data may include names, emails, organisations, contact details, ticket content, comments, files, internal notes, time entries, reports, user roles, technical identifiers, logs, and communication metadata.'],
            ['Data subjects', 'Data subjects may include the customer\'s employees, agents, client contacts, requesters, suppliers, contractors, and other people whose data is entered into a workspace.'],
            ['Customer instructions', 'We process workspace data only on documented customer instructions, including these Terms, this DPA, product settings, support requests, and lawful written instructions. The customer warrants that its instructions and disclosure of personal data comply with applicable law. We may refuse or suspend instructions that appear unlawful, unsafe, technically unreasonable, or outside the service scope.'],
            ['Confidentiality', 'People authorised to process workspace data for us must keep it confidential and may access it only as needed for the service, security, support, legal compliance, or claim defence.'],
            ['Security', 'We use technical and organisational measures appropriate to a hosted business SaaS, the processing, and the risk. The customer is responsible for choosing what data to upload, assigning users correctly, and using available security controls.'],
            ['Assistance', 'We will provide reasonable assistance with data subject requests, security incidents, deletion or export requests, and GDPR obligations where this is available through the service or reasonable support. Extraordinary assistance may require a separate agreement or fee.'],
            ['Sub-processors', 'The customer gives general authorisation for sub-processors needed to operate FoxDesk Cloud. We may appoint, replace, or remove sub-processors. We remain responsible for imposing appropriate data protection obligations on them as required by GDPR. Provider information is supplied where required by law or customer agreement.'],
            ['International transfers', 'Where processing involves transfers outside the European Economic Area, we will use a legally available transfer mechanism where required.'],
            ['Personal data breach', 'If we become aware of a personal data breach affecting customer workspace data, we will investigate and notify affected customers without undue delay after we have enough information to make a meaningful notice.'],
            ['Return and deletion', 'After termination, workspace data may be exported where technically available. We may delete or anonymise workspace data after the applicable retention period, subject to backups, legal duties, accounting, security, abuse prevention, and dispute handling.'],
            ['Audit information', 'The customer may request reasonable information needed to verify compliance with this DPA. Any review must be proportionate, scheduled in advance, limited to relevant information, and must not compromise other customers, confidential information, security, or service availability.'],
        ],
    ],
    'refunds' => [
        'title' => 'Refund and Cancellation Policy',
        'intro' => 'FoxDesk Cloud fees are paid for service access. Refunds are limited as set out below.',
        'sections' => [
            ['Business purchases', 'FoxDesk Cloud is offered only for business or professional use. The customer confirms this status at signup and checkout. The consumer right to withdraw from a distance contract does not apply to a genuine business purchase. If mandatory law nevertheless gives a customer a non-waivable right, that right remains unaffected.'],
            ['Billing periods', 'Paid subscriptions are normally billed monthly in advance. The full fee is due when each billing period starts in exchange for service availability during that period. Usage-based fees, taxes, and agreed charges may be billed according to the applicable plan, checkout, invoice, or customer account.'],
            ['Trial', 'A free trial, if offered, is a no-charge evaluation for the stated trial period. It is provided so the customer can assess the service before purchasing. The customer must add valid billing details and explicitly start a paid subscription to continue after the trial or grace period. We may limit or end trials for abuse, duplicate accounts, excessive use, or operational reasons.'],
            ['Cancellation', 'The workspace owner may cancel at any time. Cancellation prevents the next renewal and takes effect at the end of the current paid billing period. Unless access is suspended or terminated under the Terms, the workspace remains available until that date.'],
            ['No ordinary refunds', 'Except where mandatory law requires otherwise, fees are final and non-refundable once a paid billing period starts. Cancellation does not create a pro-rated refund or credit. No refund is owed for unused time, forgotten cancellation, lack of use, customer setup choices, customer content, user error, business decision changes, or access limited because of breach or non-payment.'],
            ['Discretionary credits', 'We may choose to issue a refund or service credit for a duplicate charge, clear billing error, measured usage error, accidental renewal reported promptly, or confirmed serious service failure caused by us that prevented normal paid use. This is discretionary unless mandatory law says otherwise.'],
            ['Exclusive remedy', 'If a serious service issue is confirmed, we may repair the service, provide a service credit, refund the affected paid period, or end the subscription. To the maximum extent permitted by law, once we provide one of those remedies, no further contractual remedy is owed for that issue.'],
            ['Request process', 'Send requests to ' . $operator['billing'] . ' with the workspace name, billing email, invoice number if available, and a short explanation. We may require account verification and may reject incomplete, late, abusive, or unsupported requests. Approved refunds are normally returned through the original payment method.'],
        ],
    ],
    'security' => [
        'title' => 'Security',
        'intro' => 'FoxDesk Cloud uses reasonable security measures for a hosted business service. Customers remain responsible for their own access and content.',
        'sections' => [
            ['Measures', 'We use access controls, secure connections, backups, logging, monitoring, operational controls, and separation of customer workspaces appropriate to a hosted business SaaS. Measures may change as the service develops.'],
            ['Customer access', 'Customers control their users, roles, permissions, passwords, devices, and workspace settings. Administrative access should be limited to trusted people. Users who no longer need access should be removed promptly.'],
            ['Data handling', 'FoxDesk Cloud is intended for ordinary business support data. Customers should not store unnecessary sensitive data, credentials, payment card numbers, medical data, or regulated identifiers unless expressly agreed in writing.'],
            ['Backups and recovery', 'We maintain backup and recovery processes designed to support continuity and recovery where reasonably possible. Backups are not a guarantee against every form of loss and may expire after a limited period.'],
            ['Incident response', 'We investigate suspected security issues that may affect the service. Where legally required, we notify affected customers or authorities using information reasonably available to us.'],
            ['Reporting', 'Report suspected vulnerabilities to ' . $operator['support'] . ' with the affected URL, steps to reproduce, expected impact, and relevant evidence. Do not access, modify, delete, download, or disclose data that is not yours.'],
            ['No guarantee', 'No online service is completely secure or always available. We do not guarantee that every attack, error, outage, or data loss can be prevented. Customer backups, exports, internal procedures, and user management remain the customer\'s responsibility.'],
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
    <link href="assets/css/theme.min.css?v=<?php echo e((string) APP_VERSION); ?>" rel="stylesheet">
    <link href="assets/css/legal.css?v=<?php echo e((string) APP_VERSION); ?>" rel="stylesheet">
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
