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

function seed_demo_avatar(string $filename): string {
    return 'public/demo-avatars/' . basename($filename);
}

function ensure_seed_demo_avatars(): void {
    $source_dir = BASE_PATH . '/assets/demo/avatars';
    $upload_dir = trim((defined('UPLOAD_DIR') ? UPLOAD_DIR : 'uploads/'), "/\\\\");
    $target_dir = BASE_PATH . '/' . ($upload_dir !== '' ? $upload_dir : 'uploads') . '/public/demo-avatars';

    if (!is_dir($source_dir)) {
        return;
    }
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0775, true);
    }

    foreach (glob($source_dir . '/*.png') ?: [] as $source) {
        $target = $target_dir . '/' . basename($source);
        if (!is_file($target) || hash_file('sha256', $target) !== hash_file('sha256', $source)) {
            copy($source, $target);
        }
    }
}

function ensure_seed_workflow_baseline(): void {
    $statuses = [
        ['Open', 'open', '#2563eb', 10, 1, 0],
        ['In progress', 'in-progress', '#f59e0b', 20, 0, 0],
        ['Waiting', 'waiting', '#7c3aed', 30, 0, 0],
        ['Closed', 'closed', '#059669', 90, 0, 1],
    ];
    foreach ($statuses as [$name, $slug, $color, $sort, $default, $closed]) {
        $existing = db_fetch_one('SELECT id FROM statuses WHERE slug = ? LIMIT 1', [$slug]);
        $payload = [
            'name' => $name,
            'slug' => $slug,
            'color' => $color,
            'sort_order' => $sort,
            'is_default' => $default,
            'is_closed' => $closed,
        ];
        if ($existing) {
            db_update('statuses', $payload, 'id = ?', [(int) $existing['id']]);
        } else {
            db_insert('statuses', $payload);
        }
    }

    $priorities = [
        ['Urgent', 'urgent', '#dc2626', 'alert-triangle', 5, 0],
        ['High', 'high', '#ea580c', 'arrow-up', 10, 0],
        ['Medium', 'medium', '#2563eb', 'minus', 20, 1],
        ['Low', 'low', '#64748b', 'arrow-down', 30, 0],
    ];
    foreach ($priorities as [$name, $slug, $color, $icon, $sort, $default]) {
        $existing = db_fetch_one('SELECT id FROM priorities WHERE slug = ? LIMIT 1', [$slug]);
        $payload = [
            'name' => $name,
            'slug' => $slug,
            'color' => $color,
            'icon' => $icon,
            'sort_order' => $sort,
            'is_default' => $default,
        ];
        if ($existing) {
            db_update('priorities', $payload, 'id = ?', [(int) $existing['id']]);
        } else {
            db_insert('priorities', $payload);
        }
    }

    if (table_exists('ticket_types')) {
        $types = [
            ['Incident', 'incident', 'alert-circle', '#dc2626', 10, 0, 1],
            ['Task', 'task', 'check-square', '#2563eb', 20, 1, 1],
            ['Question', 'question', 'help-circle', '#7c3aed', 30, 0, 1],
        ];
        foreach ($types as [$name, $slug, $icon, $color, $sort, $default, $active]) {
            $existing = db_fetch_one('SELECT id FROM ticket_types WHERE slug = ? LIMIT 1', [$slug]);
            $payload = [
                'name' => $name,
                'slug' => $slug,
                'icon' => $icon,
                'color' => $color,
                'sort_order' => $sort,
                'is_default' => $default,
                'is_active' => $active,
            ];
            if ($existing) {
                db_update('ticket_types', $payload, 'id = ?', [(int) $existing['id']]);
            } else {
                db_insert('ticket_types', $payload);
            }
        }
    }
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
    if (column_exists('users', 'avatar') && !empty($extra['avatar'])) {
        $payload['avatar'] = $extra['avatar'];
    }
    if ($existing) {
        db_update('users', $payload, 'id = ?', [(int) $existing['id']]);
        return (int) $existing['id'];
    }
    $payload['email'] = $email;
    $payload['created_at'] = date('Y-m-d H:i:s');
    return (int) db_insert('users', $payload);
}

function clean_ticket_children(int $ticket_id, int $tenant_id): void {
    foreach (['attachments', 'ticket_time_entries', 'comments', 'activity_log', 'notifications'] as $table) {
        if (table_exists($table)) {
            validate_sql_identifier($table);
            if (column_exists($table, 'tenant_id')) {
                db_query("DELETE FROM {$table} WHERE ticket_id = ? AND (tenant_id = ? OR tenant_id IS NULL)", [$ticket_id, $tenant_id]);
            } else {
                db_query("DELETE FROM {$table} WHERE ticket_id = ?", [$ticket_id]);
            }
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
        clean_ticket_children($ticket_id, $tenant_id);
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

function add_seed_active_time(int $tenant_id, int $ticket_id, int $user_id, string $started_at, string $summary, float $billable_rate, float $cost_rate): void {
    db_insert('ticket_time_entries', [
        'tenant_id' => $tenant_id,
        'ticket_id' => $ticket_id,
        'user_id' => $user_id,
        'comment_id' => null,
        'started_at' => $started_at,
        'ended_at' => null,
        'duration_minutes' => 0,
        'is_billable' => 1,
        'billable_rate' => $billable_rate,
        'cost_rate' => $cost_rate,
        'is_manual' => 0,
        'summary' => $summary,
        'created_at' => $started_at,
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

ensure_seed_demo_avatars();
ensure_seed_workflow_baseline();
$default_tenant = default_tenant_id();
$tenants = [
    [
        'name' => 'Atlas Support',
        'slug' => 'default',
        'primary_domain' => 'atlas.foxdesk.test',
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

    $people = [
        'default' => [
            'owner' => ['Emma', 'Carter', 'emma-novak.png'],
            'agent' => ['Sarah', 'Mitchell', 'rachel-edwards.png'],
            'client' => ['Oliver', 'Reed', 'marcus-thompson.png'],
            'field' => ['Maya', 'Chen', 'nina-hartley.png'],
        ],
        'northline-support' => [
            'owner' => ['Ava', 'Collins', 'anna-kowalski.png'],
            'agent' => ['Daniel', 'Brooks', 'david-harrison.png'],
            'client' => ['Hannah', 'Miller', 'lisa-campbell.png'],
            'field' => ['Jonas', 'Blake', 'james-crawford.png'],
        ],
        'metro-it-desk' => [
            'owner' => ['Lucas', 'Harris', 'james-crawford.png'],
            'agent' => ['Thomas', 'Parker', 'michael-foster.png'],
            'client' => ['Patricia', 'Cole', 'sophie-bennett.png'],
            'field' => ['Martin', 'Brooks', 'david-harrison.png'],
        ],
        'studio-care' => [
            'owner' => ['Mark', 'Sullivan', 'marcus-thompson.png'],
            'agent' => ['Laura', 'Stone', 'lisa-campbell.png'],
            'client' => ['Nora', 'White', 'anna-kowalski.png'],
            'field' => ['Adam', 'Green', 'michael-foster.png'],
        ],
    ];
    $tenant_people = $people[$tenant_data['slug']] ?? $people['default'];

    $owner_email = $tenant_data['slug'] === 'default' ? 'admin@example.test' : 'owner+' . $tenant_data['slug'] . '@example.test';
    [$owner_first, $owner_last, $owner_avatar] = $tenant_people['owner'];
    $owner_id = upsert_user($tenant_id, $owner_email, 'AdminPass123!', $owner_first, $owner_last, 'admin', $org_main, null, [
        'is_platform_admin' => $tenant_data['slug'] === 'default' ? 1 : 0,
        'cost_rate' => 42,
        'avatar' => seed_demo_avatar($owner_avatar),
    ]);
    db_update('tenants', ['owner_user_id' => $owner_id], 'id = ?', [$tenant_id]);

    [$agent_first, $agent_last, $agent_avatar] = $tenant_people['agent'];
    $agent_id = upsert_user($tenant_id, 'agent+' . $tenant_data['slug'] . '@example.test', 'AgentPass123!', $agent_first, $agent_last, 'agent', $org_main, [
        'ticket_scope' => 'organization',
        'organization_ids' => [$org_main, $org_secondary],
        'can_view_time' => true,
        'can_view_timeline' => true,
    ], [
        'cost_rate' => 35,
        'avatar' => seed_demo_avatar($agent_avatar),
    ]);
    [$client_first, $client_last, $client_avatar] = $tenant_people['client'];
    $client_id = upsert_user($tenant_id, 'client+' . $tenant_data['slug'] . '@example.test', 'ClientPass123!', $client_first, $client_last, 'user', $org_main, [
        'ticket_scope' => 'organization',
        'organization_ids' => [$org_main],
    ], [
        'avatar' => seed_demo_avatar($client_avatar),
    ]);
    [$field_first, $field_last, $field_avatar] = $tenant_people['field'];
    $field_client_id = upsert_user($tenant_id, 'field+' . $tenant_data['slug'] . '@example.test', 'ClientPass123!', $field_first, $field_last, 'user', $org_secondary, [
        'ticket_scope' => 'organization',
        'organization_ids' => [$org_secondary],
    ], [
        'avatar' => seed_demo_avatar($field_avatar),
    ]);

    $ticket_specs = [
        [
            'title' => 'VPN access stopped working',
            'description' => '<p>The VPN client asks for MFA on every connection and rejects the code after the first attempt.</p><ul><li>Started after yesterday\\'s certificate rotation</li><li>Affects finance and operations users</li><li>Screenshot attached by the requester</li></ul>',
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
            'title' => 'Prepare onboarding checklist for finance team',
            'description' => '<p>Prepare a clean onboarding checklist for new finance users, including mailbox access, shared folders, VPN, and first login checks.</p>',
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
            'title' => 'Monthly storage and backup review',
            'description' => '<p>Review attachment growth, backup status, and restore evidence for this month.</p>',
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
            'title' => 'Closed SLA report for executive review',
            'description' => '<p>Closed sample ticket used for reports, timeline, and completed-work widgets.</p>',
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

    $seed_ticket_ids = [];
    foreach ($ticket_specs as $index => $spec) {
        $ticket_id = upsert_demo_ticket($tenant_id, $spec);
        $seed_ticket_ids[$index] = $ticket_id;
        $public_comment = add_seed_comment($tenant_id, $ticket_id, $spec['user_id'], '<p>We reproduced this on two laptops.</p><ul><li>Codes arrive by SMS</li><li>The first code fails immediately</li><li>Second attempt locks the user for 15 minutes</li></ul>', false, date('Y-m-d H:i:s', strtotime($spec['created_at'] . ' +20 minutes')));
        $internal_comment = add_seed_comment($tenant_id, $ticket_id, $agent_id, '<p>Checked the identity provider logs and found repeated challenge failures after the certificate rollover. Next step is to rotate the VPN profile and retry with one affected user.</p>', true, date('Y-m-d H:i:s', strtotime($spec['created_at'] . ' +55 minutes')));
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
        if ($tenant_data['slug'] === 'default' && $index === 0) {
            add_seed_time(
                $tenant_id,
                $ticket_id,
                $owner_id,
                date('Y-m-d H:i:s', strtotime('today 09:20')),
                38,
                'Reviewed customer impact and prepared the handoff notes',
                95,
                42
            );
        }
        if ($tenant_data['slug'] === 'default' && $index === 1) {
            add_seed_active_time(
                $tenant_id,
                $ticket_id,
                $owner_id,
                date('Y-m-d H:i:s', strtotime('-24 minutes')),
                'Updating the onboarding checklist for the finance team',
                95,
                42
            );
        }
        add_seed_attachment($tenant_id, $ticket_id, $agent_id, 'seed-' . $tenant_data['slug'] . '-' . ($index + 1) . '.bin', (int) $spec['storage'], $public_comment);
        add_seed_activity($tenant_id, $ticket_id, $spec['user_id'], 'created', 'Seed ticket created', $spec['created_at']);
        add_seed_activity($tenant_id, $ticket_id, $agent_id, 'commented', 'Seed comments and time entries added', date('Y-m-d H:i:s', strtotime($spec['created_at'] . ' +1 hour')));
    }

    if ($tenant_data['slug'] === 'default' && !empty($seed_ticket_ids)) {
        $weekly_time_entries = [
            [$seed_ticket_ids[0] ?? null, $agent_id, '-6 days 10:00', 72, 'VPN profile validation and customer follow-up', 95, 35],
            [$seed_ticket_ids[1] ?? null, $owner_id, '-6 days 14:10', 48, 'Finance onboarding checklist review', 95, 42],
            [$seed_ticket_ids[2] ?? null, $agent_id, '-5 days 09:30', 54, 'Storage review and restore evidence check', 125, 35],
            [$seed_ticket_ids[0] ?? null, $owner_id, '-4 days 11:15', 66, 'Customer impact review and escalation notes', 95, 42],
            [$seed_ticket_ids[1] ?? null, $agent_id, '-3 days 15:20', 85, 'Checklist implementation and ticket updates', 95, 35],
            [$seed_ticket_ids[2] ?? null, $owner_id, '-2 days 10:45', 42, 'Backup status review with client context', 125, 42],
            [$seed_ticket_ids[0] ?? null, $agent_id, '-1 day 13:05', 63, 'MFA retry testing and reply preparation', 95, 35],
            [$seed_ticket_ids[3] ?? null, $owner_id, '-1 day 16:20', 35, 'Executive report cleanup', 95, 42],
        ];

        foreach ($weekly_time_entries as [$week_ticket_id, $week_user_id, $week_start, $week_minutes, $week_summary, $week_rate, $week_cost]) {
            if (!$week_ticket_id) {
                continue;
            }
            add_seed_time(
                $tenant_id,
                (int) $week_ticket_id,
                (int) $week_user_id,
                date('Y-m-d H:i:s', strtotime($week_start)),
                (int) $week_minutes,
                $week_summary,
                (float) $week_rate,
                (float) $week_cost
            );
        }
    }

    upsert_report_template($tenant_id, $org_main, $owner_id, 'Monthly service report - ' . $tenant_data['name']);

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
