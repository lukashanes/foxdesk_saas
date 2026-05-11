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

$tenant_id = default_tenant_id();

function upsert_user($tenant_id, $email, $password, $first, $last, $role, $org_id = null, $permissions = null) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $existing = db_fetch_one('SELECT id FROM users WHERE email = ? LIMIT 1', [$email]);
    $data = [
        'tenant_id' => $tenant_id,
        'password' => $hash,
        'first_name' => $first,
        'last_name' => $last,
        'role' => $role,
        'organization_id' => $org_id,
        'permissions' => $permissions ? json_encode($permissions) : null,
        'is_active' => 1,
        'deleted_at' => null,
    ];
    if ($existing) {
        db_update('users', $data, 'id = ?', [(int) $existing['id']]);
        return (int) $existing['id'];
    }
    $data['email'] = $email;
    $data['created_at'] = date('Y-m-d H:i:s');
    return (int) db_insert('users', $data);
}

$org = db_fetch_one("SELECT id FROM organizations WHERE tenant_id = ? AND name = 'Acme Local Demo' LIMIT 1", [$tenant_id]);
if (!$org) {
    $org_id = (int) db_insert('organizations', [
        'tenant_id' => $tenant_id,
        'name' => 'Acme Local Demo',
        'contact_email' => 'client@example.test',
        'is_active' => 1,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
} else {
    $org_id = (int) $org['id'];
}

$agent_id = upsert_user($tenant_id, 'agent@example.test', 'AgentPass123!', 'Agent', 'Local', 'agent', $org_id, [
    'ticket_scope' => 'organization',
    'organization_ids' => [$org_id],
    'can_view_time' => true,
    'can_view_timeline' => true,
]);
$client_id = upsert_user($tenant_id, 'client@example.test', 'ClientPass123!', 'Client', 'Local', 'user', $org_id, [
    'ticket_scope' => 'organization',
    'organization_ids' => [$org_id],
]);

$existing_ticket = db_fetch_one("SELECT id FROM tickets WHERE tenant_id = ? AND title = 'Demo onboarding ticket' LIMIT 1", [$tenant_id]);
if (!$existing_ticket) {
    $ticket_id = create_ticket([
        'title' => 'Demo onboarding ticket',
        'description' => '<p>This ticket was created by the local seed script.</p>',
        'user_id' => $client_id,
        'organization_id' => $org_id,
        'assignee_id' => $agent_id,
        'tags' => 'demo,local',
    ]);
    log_activity($ticket_id, $agent_id, 'ticket_created', 'Seeded demo ticket');
}

echo "Seed complete.\\n";
echo "Admin: admin@example.test / AdminPass123!\\n";
echo "Agent: agent@example.test / AgentPass123!\\n";
echo "Client: client@example.test / ClientPass123!\\n";
`;

process.stdout.write(php(script));
