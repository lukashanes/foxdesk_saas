const { execFileSync } = require('child_process');

function dockerExec(args) {
  return execFileSync('docker', ['exec', 'foxdesk-saas-local-app', ...args], {
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe']
  });
}

function php(code) {
  return dockerExec(['php', '-r', code]);
}

const script = `
define('BASE_PATH', '/var/www/html');
require_once BASE_PATH . '/config.php';
require_once BASE_PATH . '/includes/database.php';
require_once BASE_PATH . '/includes/tenant-functions.php';
ensure_tenant_baseline();
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/ticket-functions.php';
require_once BASE_PATH . '/includes/ticket-time-functions.php';
require_once BASE_PATH . '/includes/report-functions.php';

function seed_hash(string $value): string {
    return substr(preg_replace('/[^a-z0-9]/', '', strtolower(sha1($value))), 0, 16);
}

function seed_uuid(string $value): string {
    $hash = md5($value);
    return substr($hash, 0, 8) . '-' . substr($hash, 8, 4) . '-4' . substr($hash, 13, 3) . '-8' . substr($hash, 17, 3) . '-' . substr($hash, 20, 12);
}

function seed_status_id(string $slug, bool $closed = false): int {
    $row = db_fetch_one('SELECT id FROM statuses WHERE slug = ? LIMIT 1', [$slug]);
    if (!$row) {
        $row = db_fetch_one('SELECT id FROM statuses WHERE is_closed = ? ORDER BY sort_order, id LIMIT 1', [$closed ? 1 : 0]);
    }
    if (!$row) {
        $row = db_fetch_one('SELECT id FROM statuses ORDER BY sort_order, id LIMIT 1');
    }
    return (int) ($row['id'] ?? 1);
}

function seed_priority_id(string $slug): int {
    $row = db_fetch_one('SELECT id FROM priorities WHERE slug = ? LIMIT 1', [$slug]);
    if (!$row) {
        $row = db_fetch_one('SELECT id FROM priorities WHERE is_default = 1 ORDER BY sort_order, id LIMIT 1');
    }
    if (!$row) {
        $row = db_fetch_one('SELECT id FROM priorities ORDER BY sort_order, id LIMIT 1');
    }
    return (int) ($row['id'] ?? 1);
}

function seed_ticket_type_id(string $slug): ?int {
    if (!table_exists('ticket_types')) {
        return null;
    }
    $row = db_fetch_one('SELECT id FROM ticket_types WHERE slug = ? LIMIT 1', [$slug]);
    if (!$row) {
        $row = db_fetch_one('SELECT id FROM ticket_types WHERE is_default = 1 ORDER BY sort_order, id LIMIT 1');
    }
    return $row ? (int) $row['id'] : null;
}

function upsert_tenant(array $data): int {
    $existing = db_fetch_one('SELECT id FROM tenants WHERE slug = ? LIMIT 1', [$data['slug']]);
    $payload = [
        'uuid' => $data['uuid'] ?? seed_uuid('tenant:' . $data['slug']),
        'name' => $data['name'],
        'slug' => $data['slug'],
        'primary_domain' => $data['primary_domain'] ?? null,
        'plan' => 'cloud',
        'status' => $data['status'],
        'billing_email' => $data['billing_email'] ?? null,
        'stripe_customer_id' => $data['stripe_customer_id'] ?? null,
        'stripe_subscription_id' => $data['stripe_subscription_id'] ?? null,
        'subscription_status' => $data['subscription_status'] ?? 'manual',
        'max_users' => 1000000,
        'max_agents' => 1000000,
        'trial_ends_at' => $data['trial_ends_at'] ?? null,
    ];
    if ($existing) {
        db_update('tenants', $payload, 'id = ?', [(int) $existing['id']]);
        return (int) $existing['id'];
    }
    $payload['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
    return (int) db_insert('tenants', $payload);
}

function upsert_org(int $tenant_id, array $data): int {
    $existing = db_fetch_one('SELECT id FROM organizations WHERE tenant_id = ? AND name = ? LIMIT 1', [$tenant_id, $data['name']]);
    $payload = [
        'tenant_id' => $tenant_id,
        'name' => $data['name'],
        'ico' => $data['ico'] ?? null,
        'address' => $data['address'] ?? null,
        'contact_email' => $data['contact_email'] ?? null,
        'contact_phone' => $data['contact_phone'] ?? null,
        'notes' => $data['notes'] ?? null,
        'billable_rate' => $data['billable_rate'] ?? 0,
        'is_active' => $data['is_active'] ?? 1,
    ];
    if ($existing) {
        db_update('organizations', $payload, 'id = ?', [(int) $existing['id']]);
        return (int) $existing['id'];
    }
    $payload['created_at'] = date('Y-m-d H:i:s');
    return (int) db_insert('organizations', $payload);
}

function upsert_user(int $tenant_id, string $email, string $password, string $first, string $last, string $role, ?int $org_id = null, ?array $permissions = null, array $extra = []): int {
    $existing = db_fetch_one('SELECT id FROM users WHERE email = ? LIMIT 1', [$email]);
    $payload = [
        'tenant_id' => $tenant_id,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'first_name' => $first,
        'last_name' => $last,
        'role' => $role,
        'organization_id' => $org_id,
        'permissions' => $permissions ? json_encode($permissions) : null,
        'cost_rate' => $extra['cost_rate'] ?? 0,
        'is_platform_admin' => $extra['is_platform_admin'] ?? 0,
        'language' => $extra['language'] ?? 'en',
        'email_notifications_enabled' => 1,
        'in_app_notifications_enabled' => 1,
        'is_active' => 1,
        'deleted_at' => null,
    ];
    if ($existing) {
        db_update('users', $payload, 'id = ?', [(int) $existing['id']]);
        return (int) $existing['id'];
    }
    $payload['email'] = $email;
    $payload['created_at'] = date('Y-m-d H:i:s');
    return (int) db_insert('users', $payload);
}

function clean_ticket_children(int $ticket_id): void {
    foreach (['attachments', 'ticket_time_entries', 'comments', 'activity_log', 'notifications'] as $table) {
        if (table_exists($table)) {
            db_delete($table, 'ticket_id = ?', [$ticket_id]);
        }
    }
}

function upsert_demo_ticket(int $tenant_id, array $data): int {
    $existing = db_fetch_one('SELECT id FROM tickets WHERE tenant_id = ? AND title = ? LIMIT 1', [$tenant_id, $data['title']]);
    $payload = [
        'tenant_id' => $tenant_id,
        'hash' => seed_hash($tenant_id . ':' . $data['title']),
        'title' => $data['title'],
        'description' => $data['description'],
        'type' => $data['type'] ?? 'general',
        'priority_id' => $data['priority_id'],
        'user_id' => $data['user_id'],
        'organization_id' => $data['organization_id'],
        'status_id' => $data['status_id'],
        'ticket_type_id' => $data['ticket_type_id'] ?? null,
        'source' => $data['source'] ?? 'web',
        'is_archived' => $data['is_archived'] ?? 0,
        'assignee_id' => $data['assignee_id'] ?? null,
        'due_date' => $data['due_date'] ?? null,
        'custom_billable_rate' => $data['custom_billable_rate'] ?? null,
        'tags' => $data['tags'] ?? '',
        'created_at' => $data['created_at'],
        'updated_at' => $data['updated_at'] ?? date('Y-m-d H:i:s'),
    ];
    if ($existing) {
        $ticket_id = (int) $existing['id'];
        clean_ticket_children($ticket_id);
        db_update('tickets', $payload, 'id = ?', [$ticket_id]);
        return $ticket_id;
    }
    return (int) db_insert('tickets', $payload);
}

function add_seed_comment(int $tenant_id, int $ticket_id, int $user_id, string $content, bool $internal, string $created_at): int {
    return (int) db_insert('comments', [
        'tenant_id' => $tenant_id,
        'ticket_id' => $ticket_id,
        'user_id' => $user_id,
        'content' => $content,
        'is_internal' => $internal ? 1 : 0,
        'created_at' => $created_at,
    ]);
}

function add_seed_time(int $tenant_id, int $ticket_id, int $user_id, string $started_at, int $minutes, string $summary, float $billable_rate, float $cost_rate, ?int $comment_id = null): void {
    db_insert('ticket_time_entries', [
        'tenant_id' => $tenant_id,
        'ticket_id' => $ticket_id,
        'user_id' => $user_id,
        'comment_id' => $comment_id,
        'started_at' => $started_at,
        'ended_at' => date('Y-m-d H:i:s', strtotime($started_at . ' +' . $minutes . ' minutes')),
        'duration_minutes' => $minutes,
        'is_billable' => 1,
        'billable_rate' => $billable_rate,
        'cost_rate' => $cost_rate,
        'is_manual' => 1,
        'summary' => $summary,
        'created_at' => date('Y-m-d H:i:s', strtotime($started_at . ' +' . ($minutes + 3) . ' minutes')),
    ]);
}

function add_seed_attachment(int $tenant_id, int $ticket_id, int $user_id, string $name, int $bytes, ?int $comment_id = null): void {
    db_insert('attachments', [
        'tenant_id' => $tenant_id,
        'ticket_id' => $ticket_id,
        'comment_id' => $comment_id,
        'filename' => 'seed/' . seed_hash($tenant_id . ':' . $ticket_id . ':' . $name) . '-' . $name,
        'original_name' => $name,
        'mime_type' => 'application/octet-stream',
        'file_size' => $bytes,
        'storage_driver' => 'local',
        'storage_key' => 'seed/' . $name,
        'uploaded_by' => $user_id,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}

function add_seed_activity(int $tenant_id, int $ticket_id, int $user_id, string $action, string $details, string $created_at): void {
    db_insert('activity_log', [
        'tenant_id' => $tenant_id,
        'ticket_id' => $ticket_id,
        'user_id' => $user_id,
        'action' => $action,
        'details' => $details,
        'created_at' => $created_at,
    ]);
}

function upsert_report_template(int $tenant_id, int $org_id, int $user_id, string $title): void {
    if (!table_exists('report_templates')) {
        return;
    }
    $existing = db_fetch_one('SELECT id FROM report_templates WHERE tenant_id = ? AND title = ? LIMIT 1', [$tenant_id, $title]);
    $payload = [
        'tenant_id' => $tenant_id,
        'uuid' => seed_uuid('report:' . $tenant_id . ':' . $title),
        'organization_id' => $org_id,
        'created_by_user_id' => $user_id,
        'title' => $title,
        'report_language' => 'en',
        'date_from' => date('Y-m-01'),
        'date_to' => date('Y-m-t'),
        'executive_summary' => 'Seeded monthly support summary with response work, incident handling, and onboarding activity.',
        'show_financials' => 1,
        'show_team_attribution' => 1,
        'show_cost_breakdown' => 1,
        'group_by' => 'ticket',
        'rounding_minutes' => 15,
        'theme_color' => '#2563eb',
        'is_draft' => 0,
        'last_generated_at' => date('Y-m-d H:i:s'),
    ];
    if ($existing) {
        db_update('report_templates', $payload, 'id = ?', [(int) $existing['id']]);
        return;
    }
    $payload['created_at'] = date('Y-m-d H:i:s');
    db_insert('report_templates', $payload);
}

$default_tenant = default_tenant_id();
$tenants = [
    [
        'name' => 'Acme Local Demo',
        'slug' => 'default',
        'primary_domain' => 'acme.foxdesk.test',
        'status' => 'active',
        'subscription_status' => 'active',
        'billing_email' => 'admin@example.test',
        'stripe_customer_id' => 'cus_seed_acme',
        'stripe_subscription_id' => 'sub_seed_acme',
        'created_at' => date('Y-m-d H:i:s', strtotime('-45 days')),
    ],
    [
        'name' => 'Northline Support',
        'slug' => 'northline-support',
        'primary_domain' => 'northline.foxdesk.test',
        'status' => 'active',
        'subscription_status' => 'active',
        'billing_email' => 'eva@northline.example',
        'stripe_customer_id' => 'cus_seed_northline',
        'stripe_subscription_id' => 'sub_seed_northline',
        'created_at' => date('Y-m-d H:i:s', strtotime('-28 days')),
    ],
    [
        'name' => 'Metro IT Desk',
        'slug' => 'metro-it-desk',
        'primary_domain' => 'metro.foxdesk.test',
        'status' => 'past_due',
        'subscription_status' => 'past_due',
        'billing_email' => 'billing@metro.example',
        'stripe_customer_id' => 'cus_seed_metro',
        'stripe_subscription_id' => 'sub_seed_metro',
        'created_at' => date('Y-m-d H:i:s', strtotime('-62 days')),
    ],
    [
        'name' => 'Studio Care',
        'slug' => 'studio-care',
        'primary_domain' => 'studio-care.foxdesk.test',
        'status' => 'trialing',
        'subscription_status' => 'trialing',
        'billing_email' => 'marek@studio.example',
        'trial_ends_at' => date('Y-m-d H:i:s', strtotime('+9 days')),
        'created_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
    ],
];

$summary = [];
$open = seed_status_id('open');
$progress = seed_status_id('in-progress');
$waiting = seed_status_id('waiting');
$closed = seed_status_id('closed', true);
$urgent = seed_priority_id('urgent');
$high = seed_priority_id('high');
$medium = seed_priority_id('medium');
$low = seed_priority_id('low');
$incident = seed_ticket_type_id('incident');
$task = seed_ticket_type_id('task');
$question = seed_ticket_type_id('question');

foreach ($tenants as $tenant_data) {
    $tenant_id = $tenant_data['slug'] === 'default' ? $default_tenant : upsert_tenant($tenant_data);
    if ($tenant_data['slug'] === 'default') {
        db_update('tenants', [
            'name' => $tenant_data['name'],
            'primary_domain' => $tenant_data['primary_domain'],
            'status' => $tenant_data['status'],
            'subscription_status' => $tenant_data['subscription_status'],
            'billing_email' => $tenant_data['billing_email'],
            'stripe_customer_id' => $tenant_data['stripe_customer_id'],
            'stripe_subscription_id' => $tenant_data['stripe_subscription_id'],
        ], 'id = ?', [$tenant_id]);
    }

    $org_main = upsert_org($tenant_id, [
        'name' => $tenant_data['name'],
        'contact_email' => $tenant_data['billing_email'],
        'contact_phone' => '+420 222 000 ' . str_pad((string) $tenant_id, 3, '0', STR_PAD_LEFT),
        'billable_rate' => 95,
        'notes' => 'Seeded primary customer account for local SaaS testing.',
    ]);
    $org_secondary = upsert_org($tenant_id, [
        'name' => $tenant_data['name'] . ' Field Team',
        'contact_email' => 'field-' . $tenant_data['slug'] . '@example.test',
        'billable_rate' => 125,
        'notes' => 'Secondary organization used to exercise filters and scoped permissions.',
    ]);

    $owner_email = $tenant_data['slug'] === 'default' ? 'admin@example.test' : 'owner+' . $tenant_data['slug'] . '@example.test';
    $owner_first = $tenant_data['slug'] === 'northline-support' ? 'Eva' : ($tenant_data['slug'] === 'metro-it-desk' ? 'Lukas' : ($tenant_data['slug'] === 'studio-care' ? 'Marek' : 'Admin'));
    $owner_last = $tenant_data['slug'] === 'northline-support' ? 'Novak' : ($tenant_data['slug'] === 'metro-it-desk' ? 'Hanes' : ($tenant_data['slug'] === 'studio-care' ? 'Svoboda' : 'Local'));
    $owner_id = upsert_user($tenant_id, $owner_email, 'AdminPass123!', $owner_first, $owner_last, 'admin', $org_main, null, [
        'is_platform_admin' => $tenant_data['slug'] === 'default' ? 1 : 0,
        'cost_rate' => 42,
    ]);
    db_update('tenants', ['owner_user_id' => $owner_id], 'id = ?', [$tenant_id]);

    $agent_id = upsert_user($tenant_id, 'agent+' . $tenant_data['slug'] . '@example.test', 'AgentPass123!', 'Agent', ucwords(str_replace('-', ' ', $tenant_data['slug'])), 'agent', $org_main, [
        'ticket_scope' => 'organization',
        'organization_ids' => [$org_main, $org_secondary],
        'can_view_time' => true,
        'can_view_timeline' => true,
    ], ['cost_rate' => 35]);
    $client_id = upsert_user($tenant_id, 'client+' . $tenant_data['slug'] . '@example.test', 'ClientPass123!', 'Client', ucwords(str_replace('-', ' ', $tenant_data['slug'])), 'user', $org_main, [
        'ticket_scope' => 'organization',
        'organization_ids' => [$org_main],
    ]);
    $field_client_id = upsert_user($tenant_id, 'field+' . $tenant_data['slug'] . '@example.test', 'ClientPass123!', 'Field', 'Requester', 'user', $org_secondary, [
        'ticket_scope' => 'organization',
        'organization_ids' => [$org_secondary],
    ]);

    $ticket_specs = [
        [
            'title' => 'Demo urgent login outage - ' . $tenant_data['name'],
            'description' => '<p>Users cannot sign in after an SSO certificate rotation. This seeded ticket checks urgent queues, email source labels, comments, and time tracking.</p>',
            'priority_id' => $urgent,
            'status_id' => $open,
            'ticket_type_id' => $incident,
            'source' => 'email',
            'user_id' => $client_id,
            'organization_id' => $org_main,
            'assignee_id' => $agent_id,
            'tags' => 'demo,urgent,sso,email',
            'created_at' => date('Y-m-d H:i:s', strtotime('-3 days 09:10')),
            'updated_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
            'due_date' => date('Y-m-d H:i:s', strtotime('+1 day 12:00')),
            'times' => [45, 30],
            'storage' => 42 * 1024 * 1024,
        ],
        [
            'title' => 'Demo onboarding checklist - ' . $tenant_data['name'],
            'description' => '<p>New team onboarding: configure departments, canned replies, reports, and first client workspace.</p>',
            'priority_id' => $medium,
            'status_id' => $progress,
            'ticket_type_id' => $task,
            'source' => 'web',
            'user_id' => $owner_id,
            'organization_id' => $org_main,
            'assignee_id' => $agent_id,
            'tags' => 'demo,onboarding,setup',
            'created_at' => date('Y-m-d H:i:s', strtotime('-8 days 14:20')),
            'updated_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'times' => [60, 35],
            'storage' => 180 * 1024 * 1024,
        ],
        [
            'title' => 'Demo storage and backup review - ' . $tenant_data['name'],
            'description' => '<p>Review attachment growth, backup status, and R2 migration readiness.</p>',
            'priority_id' => $high,
            'status_id' => $waiting,
            'ticket_type_id' => $question,
            'source' => 'web',
            'user_id' => $field_client_id,
            'organization_id' => $org_secondary,
            'assignee_id' => $agent_id,
            'tags' => 'demo,storage,backup',
            'created_at' => date('Y-m-d H:i:s', strtotime('-15 days 11:05')),
            'updated_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
            'times' => [25],
            'storage' => $tenant_data['slug'] === 'metro-it-desk' ? 1700 * 1024 * 1024 : ($tenant_data['slug'] === 'northline-support' ? 620 * 1024 * 1024 : 120 * 1024 * 1024),
        ],
        [
            'title' => 'Demo resolved SLA report - ' . $tenant_data['name'],
            'description' => '<p>Closed sample ticket used for reports, timeline, and dashboard completed-work widgets.</p>',
            'priority_id' => $low,
            'status_id' => $closed,
            'ticket_type_id' => $task,
            'source' => 'agent',
            'user_id' => $client_id,
            'organization_id' => $org_main,
            'assignee_id' => $agent_id,
            'tags' => 'demo,resolved,reporting',
            'created_at' => date('Y-m-d H:i:s', strtotime('-24 days 10:00')),
            'updated_at' => date('Y-m-d H:i:s', strtotime('-18 days')),
            'times' => [40, 20],
            'storage' => 55 * 1024 * 1024,
        ],
    ];

    foreach ($ticket_specs as $index => $spec) {
        $ticket_id = upsert_demo_ticket($tenant_id, $spec);
        $public_comment = add_seed_comment($tenant_id, $ticket_id, $spec['user_id'], '<p>Seeded customer update for local SaaS validation.</p><p>Second paragraph verifies rich text rendering and email-style line breaks.</p>', false, date('Y-m-d H:i:s', strtotime($spec['created_at'] . ' +20 minutes')));
        $internal_comment = add_seed_comment($tenant_id, $ticket_id, $agent_id, '<p>Internal note: validate owner, company, time, and attachment displays.</p>', true, date('Y-m-d H:i:s', strtotime($spec['created_at'] . ' +55 minutes')));
        foreach ($spec['times'] as $time_index => $minutes) {
            add_seed_time(
                $tenant_id,
                $ticket_id,
                $agent_id,
                date('Y-m-d H:i:s', strtotime($spec['created_at'] . ' +' . (90 + ($time_index * 80)) . ' minutes')),
                $minutes,
                $time_index === 0 ? 'Initial analysis and customer response' : 'Follow-up implementation work',
                $index === 2 ? 125 : 95,
                35,
                $time_index === 0 ? $internal_comment : null
            );
        }
        add_seed_attachment($tenant_id, $ticket_id, $agent_id, 'seed-' . $tenant_data['slug'] . '-' . ($index + 1) . '.bin', (int) $spec['storage'], $public_comment);
        add_seed_activity($tenant_id, $ticket_id, $spec['user_id'], 'created', 'Seed ticket created', $spec['created_at']);
        add_seed_activity($tenant_id, $ticket_id, $agent_id, 'commented', 'Seed comments and time entries added', date('Y-m-d H:i:s', strtotime($spec['created_at'] . ' +1 hour')));
    }

    upsert_report_template($tenant_id, $org_main, $owner_id, 'Demo monthly service report - ' . $tenant_data['name']);

    if (table_exists('page_views')) {
        db_delete('page_views', 'tenant_id = ? AND user_id IN (?, ?)', [$tenant_id, $owner_id, $agent_id]);
        foreach (['dashboard', 'tickets', 'reports', 'admin'] as $offset => $page) {
            db_insert('page_views', [
                'tenant_id' => $tenant_id,
                'user_id' => $offset % 2 === 0 ? $owner_id : $agent_id,
                'page' => $page,
                'section' => $page === 'admin' ? 'settings' : null,
                'created_at' => date('Y-m-d H:i:s', strtotime('-' . (6 - $offset) . ' hours')),
            ]);
        }
    }

    $counts = db_fetch_one(
        'SELECT
            (SELECT COUNT(*) FROM users WHERE tenant_id = ?) AS users,
            (SELECT COUNT(*) FROM organizations WHERE tenant_id = ?) AS orgs,
            (SELECT COUNT(*) FROM tickets WHERE tenant_id = ?) AS tickets,
            (SELECT COALESCE(SUM(file_size), 0) FROM attachments WHERE tenant_id = ?) AS storage',
        [$tenant_id, $tenant_id, $tenant_id, $tenant_id]
    );
    $summary[] = $tenant_data['name'] . ': ' . (int) $counts['users'] . ' users, ' . (int) $counts['orgs'] . ' orgs, ' . (int) $counts['tickets'] . ' tickets, ' . round(((int) $counts['storage']) / 1073741824, 2) . ' GB';
}

echo "Seed complete.\\n";
echo "Platform/admin: admin@example.test / AdminPass123!\\n";
echo "Default agent: agent+default@example.test / AgentPass123!\\n";
echo "Default client: client+default@example.test / ClientPass123!\\n";
foreach ($summary as $line) {
    echo "- " . $line . "\\n";
}
`;

process.stdout.write(php(script));
