<?php
// api/admin.php: token-gated JSON admin API for agents.
// Client: the `rbh` MCP server on THE-ONE (vault 06-Reference/scripts/rbh-admin-mcp.py).
//
// Auth: header `X-RBH-Key: <token>` (or `Authorization: Bearer <token>`).
// The server keeps only the SHA-256 of the token, in rbh-private/admin-api-key.php
// one folder above the web root:   <?php return '<64 hex chars>';
// Never in git, never in public_html.
//
// Reads  (GET  ?action=...): stats, businesses, business, jobs, log
// Writes (POST, JSON body {"action": ..., "id": ...}): approve_business, reject_business,
//   verify_business, unverify_business, edit_business, approve_job, reject_job, close_job
// No deletes. No contact details out (emails, phones, edit tokens). No applicant data exists.

ini_set('session.use_cookies', '0');
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/jobs-lib.php';
require_once dirname(__DIR__) . '/admin-lib.php';

function api_out(int $code, array $body): void
{
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
    exit;
}

// ---------------------------------------------------------------- auth
$key_file = rbh_private_dir() . '/admin-api-key.php';
$stored = is_file($key_file) ? (require $key_file) : '';
if (!is_string($stored) || !preg_match('/^[a-f0-9]{64}$/', $stored)) {
    api_out(503, ['ok' => false, 'error' => 'api not configured']);
}
$given = $_SERVER['HTTP_X_RBH_KEY'] ?? '';
if ($given === '') {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (stripos($auth, 'Bearer ') === 0) {
        $given = trim(substr($auth, 7));
    }
}
if ($given === '' || !hash_equals($stored, hash('sha256', $given))) {
    api_out(401, ['ok' => false, 'error' => 'unauthorized']);
}

// ---------------------------------------------------------------- input
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$in = $_GET;
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $json = $raw !== '' ? json_decode($raw, true) : [];
    if (!is_array($json)) {
        api_out(400, ['ok' => false, 'error' => 'body must be a JSON object']);
    }
    $in = $json + $_POST;
}
$action = (string)($in['action'] ?? '');
$id = (int)($in['id'] ?? 0);

const API_READS  = ['stats', 'businesses', 'business', 'jobs', 'log'];
const API_WRITES = ['approve_business', 'reject_business', 'verify_business', 'unverify_business',
                    'edit_business', 'approve_job', 'reject_job', 'close_job'];

if (in_array($action, API_WRITES, true) && $method !== 'POST') {
    api_out(405, ['ok' => false, 'error' => "$action needs POST"]);
}
if (!in_array($action, API_READS, true) && !in_array($action, API_WRITES, true)) {
    api_out(400, ['ok' => false, 'error' => 'unknown action', 'reads' => API_READS, 'writes' => API_WRITES]);
}

// ---------------------------------------------------------------- shapes (public-safe fields only)
function api_business(array $b): array
{
    $cats = json_decode((string)$b['category'], true);
    return [
        'id'            => (int)$b['id'],
        'name'          => $b['name'],
        'slug'          => $b['slug'],
        'status'        => $b['status'],
        'is_verified'   => (bool)$b['is_verified'],
        'categories'    => is_array($cats) ? $cats : [],
        'address'       => $b['address'],
        'city'          => $b['city'],
        'state'         => $b['state'],
        'website'       => $b['website'],
        'description'   => $b['description'],
        'founder_story' => $b['founder_story'],
        'has_logo'      => trim((string)$b['logo']) !== '',
        'has_phone'     => trim((string)$b['phone']) !== '',
        'has_email'     => filter_var($b['email'], FILTER_VALIDATE_EMAIL) !== false,
        'owner_name'    => (string)($b['owner_name'] ?? ''),                 // private, for review only
        'owner_confirmed_at' => $b['owner_confirmed_at'] ?? null,            // ticked "I own it and I'm in recovery"
        'created_at'    => $b['created_at'],
        'public_url'    => RBH_SITE_URL . '/business/' . $b['slug'] . '/',
    ];
}

function api_job(array $j): array
{
    return [
        'id'            => (int)$j['id'],
        'slug'          => $j['slug'],
        'title'         => $j['title'],
        'status'        => $j['status'],
        'employer_name' => $j['employer_name'],
        'business_id'   => $j['business_id'] === null ? null : (int)$j['business_id'],
        'area'          => rbh_area_label((string)$j['area']),
        'town'          => $j['town'],
        'kind'          => $j['kind'],
        'pay'           => rbh_pay_label($j),
        'shift'         => $j['shift'],
        'policy'        => rbh_policy_label((string)$j['policy']),
        'policy_note'   => $j['policy_note'],
        'description'   => $j['description'],
        'has_email'     => filter_var($j['employer_email'], FILTER_VALIDATE_EMAIL) !== false,
        'created_at'    => $j['created_at'],
        'approved_at'   => $j['approved_at'],
        'expires_at'    => $j['expires_at'],
        'public_url'    => RBH_SITE_URL . '/jobs/' . $j['slug'] . '/',
    ];
}

function api_get_business(mysqli $conn, int $id): ?array
{
    $s = $conn->prepare('SELECT * FROM businesses WHERE id = ?');
    $s->bind_param('i', $id);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();
    return $row ?: null;
}

function api_get_job(mysqli $conn, int $id): ?array
{
    $s = $conn->prepare('SELECT * FROM jobs WHERE id = ?');
    $s->bind_param('i', $id);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();
    return $row ?: null;
}

// Mail errors can quote an address back. Nothing with an @ leaves the API.
function api_scrub($v)
{
    return is_string($v) ? preg_replace('/[^\s<>"\']+@[^\s<>"\']+/', '[email]', $v) : $v;
}

// ---------------------------------------------------------------- reads
if ($action === 'stats') {
    $out = ['businesses' => [], 'jobs' => []];
    foreach (['businesses', 'jobs'] as $t) {
        $r = $conn->query("SELECT status, COUNT(*) AS c FROM $t GROUP BY status");
        while ($r && ($row = $r->fetch_assoc())) {
            $out[$t][$row['status']] = (int)$row['c'];
        }
    }
    api_out(200, ['ok' => true] + $out);
}

if ($action === 'businesses') {
    $status = (string)($in['status'] ?? 'pending');
    if ($status === 'all') {
        $r = $conn->query('SELECT * FROM businesses ORDER BY created_at DESC LIMIT 500');
        $rows = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
    } elseif (in_array($status, ['pending', 'approved', 'rejected'], true)) {
        $s = $conn->prepare('SELECT * FROM businesses WHERE status = ? ORDER BY created_at DESC LIMIT 500');
        $s->bind_param('s', $status);
        $s->execute();
        $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
        $s->close();
    } else {
        api_out(400, ['ok' => false, 'error' => 'status must be pending, approved, rejected or all']);
    }
    api_out(200, ['ok' => true, 'status' => $status, 'count' => count($rows), 'businesses' => array_map('api_business', $rows)]);
}

if ($action === 'business') {
    $b = api_get_business($conn, $id);
    $b ? api_out(200, ['ok' => true, 'business' => api_business($b)]) : api_out(404, ['ok' => false, 'error' => 'no business with that id']);
}

if ($action === 'jobs') {
    $status = (string)($in['status'] ?? 'pending');
    if (!in_array($status, ['pending', 'approved', 'rejected', 'closed'], true)) {
        api_out(400, ['ok' => false, 'error' => 'status must be pending, approved, rejected or closed']);
    }
    $rows = rbh_jobs_by_status($conn, $status);
    api_out(200, ['ok' => true, 'status' => $status, 'count' => count($rows), 'jobs' => array_map('api_job', $rows)]);
}

if ($action === 'log') {
    $n = max(1, min(100, (int)($in['n'] ?? 30)));
    $rows = array_map(fn($r) => array_map('api_scrub', $r), rbh_recent_log($n));
    api_out(200, ['ok' => true, 'count' => count($rows), 'events' => $rows]);
}

// ---------------------------------------------------------------- writes
if (str_ends_with($action, '_business')) {
    $b = api_get_business($conn, $id);
    if (!$b) {
        api_out(404, ['ok' => false, 'error' => 'no business with that id']);
    }

    if ($action === 'edit_business') {
        $fields = $in['fields'] ?? null;
        if (!is_array($fields) || !$fields) {
            api_out(400, ['ok' => false, 'error' => 'fields must be a non-empty object']);
        }
        $res = rbh_admin_clean_edit($fields);
        if ($res['errors']) {
            api_out(422, ['ok' => false, 'error' => 'invalid fields, nothing saved', 'fields' => $res['errors']]);
        }
        rbh_admin_apply_edit($conn, $id, $res['clean']);
        $changed = array_keys($fields);
        rbh_log_event('api', ['action' => $action, 'business_id' => $id, 'fields' => implode(',', $changed)]);
        api_out(200, ['ok' => true, 'action' => $action, 'changed' => $changed, 'business' => api_business(api_get_business($conn, $id))]);
    }

    $map = [
        'approve_business'  => ['approve', 'status', 'approved'],
        'reject_business'   => ['reject', 'status', 'rejected'],
        'verify_business'   => ['verify', 'is_verified', '1'],
        'unverify_business' => ['unverify', 'is_verified', '0'],
    ];
    [$verb, $col, $target] = $map[$action];
    if ((string)$b[$col] === $target) {
        api_out(409, ['ok' => false, 'error' => "already $target, nothing changed", 'business' => api_business($b)]);
    }
    $res = rbh_admin_business_action($conn, $verb, $id);
    rbh_log_event('api', ['action' => $action, 'business_id' => $id] + ($res['mail_sent'] === null ? [] : ['owner_sent' => $res['mail_sent']]));
    api_out(200, ['ok' => true, 'action' => $action, 'owner_email_sent' => $res['mail_sent'], 'business' => api_business(api_get_business($conn, $id))]);
}

if (str_ends_with($action, '_job')) {
    $j = api_get_job($conn, $id);
    if (!$j) {
        api_out(404, ['ok' => false, 'error' => 'no job with that id']);
    }
    $allowed_from = [
        'approve_job' => ['pending', 'rejected'],
        'reject_job'  => ['pending'],
        'close_job'   => ['approved'],
    ];
    if (!in_array($j['status'], $allowed_from[$action], true)) {
        api_out(409, ['ok' => false, 'error' => "job is {$j['status']}, $action works on: " . implode(', ', $allowed_from[$action]), 'job' => api_job($j)]);
    }
    $res = rbh_admin_job_action($conn, $action, $id);
    rbh_log_event('api', ['action' => $action, 'job_id' => $id]);
    api_out(200, ['ok' => true, 'action' => $action, 'employer_email_sent' => $res['mail_sent'] ?? null, 'job' => api_job(api_get_job($conn, $id))]);
}

api_out(400, ['ok' => false, 'error' => 'unknown action']);
