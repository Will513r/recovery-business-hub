<?php
// jobs-lib.php: RBH v2 second-chance jobs.
// Data lists, queries, and the post-a-job / apply / admin handlers.
// Pages require config.php first (DB, session, csrf, rbh_send_email), then this file.
//
// Hard rules (rbh-v2-idea.md, plan-2026-11-12-rbh-site-rebuild.md):
// - Applying is an email to the employer plus a copy to RBH. No applicant data
//   is stored on the server. The log above webroot holds delivery status only.
// - Applicants never disclose anything. The employer's policy is the public part.
// - Every job post waits for a manual OK in admin.php.

if (!isset($conn)) {
    die('jobs-lib.php needs config.php first.');
}

const RBH_AREAS = [
    'cincinnati' => 'Cincinnati',
    'dayton'     => 'Dayton',
    'nky'        => 'Northern Kentucky',
];

const RBH_JOB_KINDS = [
    'Construction and trades',
    'Restaurant and kitchen',
    'Warehouse and production',
    'Driving and delivery',
    'Landscaping and outdoor',
    'Cleaning',
    'Auto and mechanic',
    'Retail and customer service',
    'Office and admin',
    'Other',
];

const RBH_POLICIES = [
    'no_check'     => 'No background check',
    'case_by_case' => 'Background check, case by case',
];

const RBH_JOB_DAYS    = 30;                               // a post stays up this long after approval
const RBH_COPY_TO     = 'recoverybusinesshub@gmail.com';   // Will's copy of every post and application
const RBH_SITE_URL    = 'https://www.recoverybusinesshub.com';
const RBH_MIN_FORM_SECONDS = 3;                           // faster than this is a bot

// ---------------------------------------------------------------------------
// Small helpers
// ---------------------------------------------------------------------------

function rbh_clean_line(string $s, int $max): string
{
    $s = trim(str_replace(["\r", "\n", "\t"], ' ', $s));
    return mb_substr($s, 0, $max);
}

function rbh_clean_text(string $s, int $max): string
{
    $s = trim(str_replace("\r\n", "\n", $s));
    return mb_substr($s, 0, $max);
}

function rbh_slugify(string $s): string
{
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');
    return $s === '' ? 'job' : substr($s, 0, 120);
}

function rbh_pay_label(array $job): string
{
    $fmt = function ($n) use ($job): string {
        $n = (float)$n;
        if ($job['pay_unit'] === 'year') {
            return '$' . number_format($n / 1000, $n % 1000 ? 1 : 0) . 'k';
        }
        return '$' . (floor($n) == $n ? number_format($n, 0) : number_format($n, 2));
    };
    $unit = $job['pay_unit'] === 'year' ? '/yr' : '/hr';
    $label = $fmt($job['pay_min']);
    if (!empty($job['pay_max']) && (float)$job['pay_max'] > (float)$job['pay_min']) {
        $label .= '-' . $fmt($job['pay_max']);
    }
    return $label . $unit;
}

function rbh_area_label(string $area): string
{
    return RBH_AREAS[$area] ?? '';
}

function rbh_policy_label(string $policy): string
{
    return RBH_POLICIES[$policy] ?? '';
}

// Where private files live: one folder above the web root. Override with
// PRIVATE_DIR in .env for local testing.
function rbh_private_dir(): string
{
    global $env_vars;
    $dir = $env_vars['PRIVATE_DIR'] ?? (dirname(__DIR__) . '/rbh-private');
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    return $dir;
}

// Delivery log only. Never applicant names, phones, emails or messages.
function rbh_log_event(string $type, array $data): void
{
    $line = json_encode(['t' => date('c'), 'type' => $type] + $data) . "\n";
    @file_put_contents(rbh_private_dir() . '/jobs-log.jsonl', $line, FILE_APPEND | LOCK_EX);
}

// Spam gate shared by both public forms: CSRF, honeypot, time trap, optional reCAPTCHA.
// Pages call rbh_form_stamp('post_job') when rendering, and put rbh_spam_fields() in the form.
function rbh_form_stamp(string $form): void
{
    $_SESSION['rbh_form_t'][$form] = time();
}

function rbh_spam_fields(): string
{
    // The honeypot is hidden with inline CSS so it works even if style.css fails to load.
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">'
        . '<div style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden" aria-hidden="true">'
        . '<label>Leave this empty<input type="text" name="company_site" tabindex="-1" autocomplete="off"></label></div>';
}

// reCAPTCHA v3, browser side. Echo once after a form whose class includes $form_class.
// Prints nothing when RECAPTCHA_SITE_KEY is unset (local), so the form posts as normal.
// When the live .env has the secret, rbh_spam_check() REQUIRES the token, so every
// public form must include this or its posts get silently dropped as bots.
function rbh_recaptcha_js(string $form_class): string
{
    global $env_vars;
    $key = $env_vars['RECAPTCHA_SITE_KEY'] ?? '';
    if ($key === '') {
        return '';
    }
    $k = htmlspecialchars($key, ENT_QUOTES);
    $sel = json_encode('form.' . $form_class);
    $kjs = json_encode($key);
    return '<script src="https://www.google.com/recaptcha/api.js?render=' . $k . '"></script>' . "\n"
        . "<script>(function(){var f=document.querySelector($sel);if(!f)return;var go=false;"
        . "f.addEventListener('submit',function(e){if(go)return;e.preventDefault();"
        . "var i=f.querySelector('input[name=\"g-recaptcha-response\"]');"
        . "if(!i){i=document.createElement('input');i.type='hidden';i.name='g-recaptcha-response';f.appendChild(i);}"
        . "grecaptcha.ready(function(){grecaptcha.execute($kjs,{action:'submit'}).then(function(t){i.value=t;go=true;HTMLFormElement.prototype.submit.call(f);});});"
        . "});})();</script>\n";
}

// Returns '' when the request looks human, else a reason (logged, never shown).
function rbh_spam_check(string $form): string
{
    global $env_vars;
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        return 'csrf';
    }
    if (trim($_POST['company_site'] ?? '') !== '') {
        return 'honeypot';
    }
    $t = $_SESSION['rbh_form_t'][$form] ?? 0;
    if ($t === 0 || time() - $t < RBH_MIN_FORM_SECONDS) {
        return 'too_fast';
    }
    $secret = $env_vars['RECAPTCHA_SECRET_KEY'] ?? '';
    if ($secret !== '') {
        $token = $_POST['g-recaptcha-response'] ?? '';
        if ($token === '') {
            return 'recaptcha_missing';
        }
        $resp = @file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret='
            . urlencode($secret) . '&response=' . urlencode($token));
        $data = $resp ? json_decode($resp) : null;
        if (empty($data->success) || ($data->score ?? 0) < 0.5) {
            return 'recaptcha_low';
        }
    }
    return '';
}

// ---------------------------------------------------------------------------
// Queries
// ---------------------------------------------------------------------------

// Open jobs: approved and not expired. $area and $kind are optional filters.
function rbh_open_jobs(mysqli $conn, string $area = '', string $kind = '', int $limit = 100): array
{
    $sql = "SELECT * FROM jobs WHERE status = 'approved' AND expires_at > NOW()";
    $types = '';
    $args = [];
    if (isset(RBH_AREAS[$area])) {
        $sql .= ' AND area = ?';
        $types .= 's';
        $args[] = $area;
    }
    if (in_array($kind, RBH_JOB_KINDS, true)) {
        $sql .= ' AND kind = ?';
        $types .= 's';
        $args[] = $kind;
    }
    $sql .= ' ORDER BY approved_at DESC LIMIT ' . max(1, min($limit, 500));
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log('[rbh jobs] list prepare failed: ' . $conn->error);
        return [];
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$args);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// One open job by slug, or null (pending, closed and expired jobs 404).
function rbh_open_job(mysqli $conn, string $slug): ?array
{
    $stmt = $conn->prepare("SELECT * FROM jobs WHERE slug = ? AND status = 'approved' AND expires_at > NOW()");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

// Open jobs for one listed business (profile page).
function rbh_business_jobs(mysqli $conn, int $business_id): array
{
    $stmt = $conn->prepare("SELECT * FROM jobs WHERE business_id = ? AND status = 'approved' AND expires_at > NOW() ORDER BY approved_at DESC");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $business_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// Set of business ids with at least one open job, for the "Hiring" tag on cards.
function rbh_hiring_business_ids(mysqli $conn): array
{
    $ids = [];
    $res = $conn->query("SELECT DISTINCT business_id FROM jobs WHERE business_id IS NOT NULL AND status = 'approved' AND expires_at > NOW()");
    if ($res) {
        while ($r = $res->fetch_row()) {
            $ids[(int)$r[0]] = true;
        }
    }
    return $ids;
}

// Admin: jobs by status, newest first.
function rbh_jobs_by_status(mysqli $conn, string $status): array
{
    if (!in_array($status, ['pending', 'approved', 'rejected', 'closed'], true)) {
        return [];
    }
    $stmt = $conn->prepare("SELECT * FROM jobs WHERE status = ? ORDER BY created_at DESC LIMIT 200");
    $stmt->bind_param('s', $status);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// ---------------------------------------------------------------------------
// Post a job (employer form). Returns ['ok' => bool, 'errors' => [field => msg], 'values' => [...]]
// ---------------------------------------------------------------------------

function rbh_handle_post_job(mysqli $conn): array
{
    $v = [
        'employer_name'  => rbh_clean_line($_POST['employer_name'] ?? '', 100),
        'employer_email' => rbh_clean_line($_POST['employer_email'] ?? '', 190),
        'employer_phone' => rbh_clean_line($_POST['employer_phone'] ?? '', 30),
        'title'          => rbh_clean_line($_POST['title'] ?? '', 100),
        'area'           => $_POST['area'] ?? '',
        'town'           => rbh_clean_line($_POST['town'] ?? '', 100),
        'kind'           => $_POST['kind'] ?? '',
        'pay_min'        => trim($_POST['pay_min'] ?? ''),
        'pay_max'        => trim($_POST['pay_max'] ?? ''),
        'pay_unit'       => ($_POST['pay_unit'] ?? 'hour') === 'year' ? 'year' : 'hour',
        'shift'          => rbh_clean_line($_POST['shift'] ?? '', 100),
        'policy'         => $_POST['policy'] ?? '',
        'policy_note'    => rbh_clean_line($_POST['policy_note'] ?? '', 300),
        'description'    => rbh_clean_text($_POST['description'] ?? '', 3000),
        'agree'          => isset($_POST['agree']),
    ];

    $spam = rbh_spam_check('post_job');
    if ($spam !== '') {
        rbh_log_event('post_job_blocked', ['reason' => $spam]);
        // Bots get a fake success so they don't retry. A failed CSRF is the one humans can hit (expired session).
        if ($spam === 'csrf') {
            return ['ok' => false, 'errors' => ['form' => 'Your session timed out. Check your info and send it again.'], 'values' => $v];
        }
        return ['ok' => true, 'errors' => [], 'values' => []];
    }

    $e = [];
    if (mb_strlen($v['employer_name']) < 2)  $e['employer_name'] = 'Enter your business name.';
    if (!filter_var($v['employer_email'], FILTER_VALIDATE_EMAIL)) $e['employer_email'] = 'Enter an email where applications can reach you.';
    if (mb_strlen($v['title']) < 3)          $e['title'] = 'Enter the job title.';
    if (!isset(RBH_AREAS[$v['area']]))       $e['area'] = 'Pick the area.';
    if (!in_array($v['kind'], RBH_JOB_KINDS, true)) $e['kind'] = 'Pick the kind of work.';
    if (!is_numeric($v['pay_min']) || (float)$v['pay_min'] <= 0) {
        $e['pay_min'] = 'Enter the starting pay.';
    } elseif ((float)$v['pay_min'] > 9999999) {
        $e['pay_min'] = 'That pay number looks off.';
    }
    if ($v['pay_max'] !== '' && (!is_numeric($v['pay_max']) || (float)$v['pay_max'] < (float)$v['pay_min'] || (float)$v['pay_max'] > 9999999)) {
        $e['pay_max'] = 'Top pay has to be at least the starting pay, or leave it blank.';
    }
    if (!isset(RBH_POLICIES[$v['policy']])) $e['policy'] = 'Pick your background check policy.';
    if (mb_strlen($v['description']) < 30)   $e['description'] = 'Tell people about the job in at least a couple of sentences.';
    if (!$v['agree'])                        $e['agree'] = 'Check the box to confirm your hiring policy.';

    if ($e) {
        return ['ok' => false, 'errors' => $e, 'values' => $v];
    }

    $slug = rbh_slugify($v['title'] . ' ' . ($v['town'] !== '' ? $v['town'] : rbh_area_label($v['area']))) . '-' . bin2hex(random_bytes(2));
    $pay_min = (float)$v['pay_min'];
    $pay_max = $v['pay_max'] === '' ? null : (float)$v['pay_max'];

    $stmt = $conn->prepare("INSERT INTO jobs (slug, employer_name, employer_email, employer_phone, title, area, town, kind, pay_min, pay_max, pay_unit, shift, policy, policy_note, description, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    if (!$stmt) {
        error_log('[rbh jobs] insert prepare failed: ' . $conn->error);
        return ['ok' => false, 'errors' => ['form' => "That didn't go through. Try again in a minute."], 'values' => $v];
    }
    $stmt->bind_param(
        'ssssssssddsssss',
        $slug, $v['employer_name'], $v['employer_email'], $v['employer_phone'], $v['title'], $v['area'], $v['town'],
        $v['kind'], $pay_min, $pay_max, $v['pay_unit'], $v['shift'], $v['policy'], $v['policy_note'], $v['description']
    );
    $ok = $stmt->execute();
    $job_id = $stmt->insert_id;
    $stmt->close();
    if (!$ok) {
        error_log('[rbh jobs] insert failed: ' . $conn->error);
        return ['ok' => false, 'errors' => ['form' => "That didn't go through. Try again in a minute."], 'values' => $v];
    }

    $job = $v + ['pay_min' => $pay_min, 'pay_max' => $pay_max];
    $summary = "Business: {$v['employer_name']}\nEmail: {$v['employer_email']}\nPhone: {$v['employer_phone']}\n"
        . "Job: {$v['title']}\nWhere: " . trim($v['town'] . ', ' . rbh_area_label($v['area']), ', ') . "\n"
        . "Kind: {$v['kind']}\nPay: " . rbh_pay_label($job) . "\nShift: {$v['shift']}\n"
        . 'Policy: ' . rbh_policy_label($v['policy']) . ($v['policy_note'] !== '' ? " ({$v['policy_note']})" : '') . "\n\n"
        . $v['description'] . "\n";

    $sent_will = rbh_send_email(RBH_COPY_TO, 'New job post to approve: ' . $v['title'], $summary . "\nApprove or reject: " . RBH_SITE_URL . "/admin.php?view=jobs\n");
    $sent_employer = rbh_send_email(
        $v['employer_email'],
        'Got your job post: ' . $v['title'],
        "Thanks for posting on Recovery Business Hub.\n\nEvery job gets checked by hand before it goes up. You'll get another email when it's live. Applications come straight to this email address.\n\nHere's what you sent:\n\n" . $summary
    );
    rbh_log_event('post_job', ['job_id' => $job_id, 'will_sent' => $sent_will, 'employer_sent' => $sent_employer]);
    unset($_SESSION['rbh_form_t']['post_job']);

    return ['ok' => true, 'errors' => [], 'values' => []];
}

// ---------------------------------------------------------------------------
// Apply to a job (job seeker form). Emails the employer, copies RBH, stores nothing.
// Returns ['ok' => bool, 'errors' => [field => msg], 'values' => [...]]
// ---------------------------------------------------------------------------

function rbh_handle_apply(mysqli $conn, array $job): array
{
    $v = [
        'name'    => rbh_clean_line($_POST['name'] ?? '', 100),
        'phone'   => rbh_clean_line($_POST['phone'] ?? '', 30),
        'email'   => rbh_clean_line($_POST['email'] ?? '', 190),
        'message' => rbh_clean_text($_POST['message'] ?? '', 1000),
    ];
    $form = 'apply_' . (int)$job['id'];

    $spam = rbh_spam_check($form);
    if ($spam !== '') {
        rbh_log_event('apply_blocked', ['job_id' => (int)$job['id'], 'reason' => $spam]);
        if ($spam === 'csrf') {
            return ['ok' => false, 'errors' => ['form' => 'Your session timed out. Check your info and send it again.'], 'values' => $v];
        }
        return ['ok' => true, 'errors' => [], 'values' => []];
    }

    // Throttle: one application per job per 10 minutes, 10 per hour per session.
    $now = time();
    $sent = array_filter($_SESSION['rbh_applied'] ?? [], fn($t) => $now - $t < 3600);
    if (isset($sent[$job['id']]) && $now - $sent[$job['id']] < 600) {
        return ['ok' => false, 'errors' => ['form' => 'You already applied to this one. The employer has it.'], 'values' => $v];
    }
    if (count($sent) >= 10) {
        return ['ok' => false, 'errors' => ['form' => "That's a lot of applications in one hour. Take a breather and try again later."], 'values' => $v];
    }

    $e = [];
    if (mb_strlen($v['name']) < 2) $e['name'] = 'Enter your name.';
    $phone_digits = preg_replace('/\D/', '', $v['phone']);
    $has_phone = strlen($phone_digits) >= 10;
    $has_email = filter_var($v['email'], FILTER_VALIDATE_EMAIL) !== false;
    if ($v['email'] !== '' && !$has_email) $e['email'] = "That email doesn't look right.";
    if (!$has_phone && !$has_email && !isset($e['email'])) $e['phone'] = 'Give a phone number or an email so they can reach you.';
    if ($e) {
        return ['ok' => false, 'errors' => $e, 'values' => $v];
    }

    $body = "Someone applied for your job on Recovery Business Hub.\n\n"
        . "Job: {$job['title']}\n\n"
        . "Name: {$v['name']}\n"
        . 'Phone: ' . ($v['phone'] !== '' ? $v['phone'] : '-') . "\n"
        . 'Email: ' . ($v['email'] !== '' ? $v['email'] : '-') . "\n\n"
        . ($v['message'] !== '' ? "What they said:\n{$v['message']}\n\n" : '')
        . "Reach out to them directly. Even a quick no helps. Nobody likes getting ghosted.\n";

    $reply_to = $has_email ? $v['email'] : null;
    $employer_sent = rbh_send_email($job['employer_email'], 'New applicant: ' . $job['title'], $body, $reply_to);
    $copy_sent = rbh_send_email(RBH_COPY_TO, 'Copy, applicant for ' . $job['title'] . ' at ' . $job['employer_name'], $body, $reply_to);
    rbh_log_event('apply', ['job_id' => (int)$job['id'], 'employer_sent' => $employer_sent, 'copy_sent' => $copy_sent]);

    if (!$employer_sent) {
        return ['ok' => false, 'errors' => ['form' => "That didn't go through. Try again in a minute."], 'values' => $v];
    }
    $_SESSION['rbh_applied'] = $sent + [$job['id'] => $now];
    unset($_SESSION['rbh_form_t'][$form]);
    return ['ok' => true, 'errors' => [], 'values' => []];
}

// ---------------------------------------------------------------------------
// Admin actions. Call only after the admin.php login check and csrf_verify().
// $action: approve_job | reject_job | close_job
// ---------------------------------------------------------------------------

function rbh_admin_job_action(mysqli $conn, string $action, int $job_id): void
{
    $stmt = $conn->prepare('SELECT * FROM jobs WHERE id = ?');
    $stmt->bind_param('i', $job_id);
    $stmt->execute();
    $job = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$job) {
        return;
    }

    if ($action === 'approve_job') {
        // Link to a listed business when the employer email matches one.
        $biz_id = null;
        $b = $conn->prepare("SELECT id FROM businesses WHERE email = ? AND status = 'approved' LIMIT 1");
        $b->bind_param('s', $job['employer_email']);
        $b->execute();
        if ($row = $b->get_result()->fetch_assoc()) {
            $biz_id = (int)$row['id'];
        }
        $b->close();

        $days = RBH_JOB_DAYS;
        $u = $conn->prepare("UPDATE jobs SET status = 'approved', business_id = ?, approved_at = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL ? DAY) WHERE id = ?");
        $u->bind_param('iii', $biz_id, $days, $job_id);
        $u->execute();
        $u->close();

        rbh_send_email(
            $job['employer_email'],
            'Your job is live: ' . $job['title'],
            "Your job post is up on Recovery Business Hub.\n\n" . RBH_SITE_URL . '/jobs/' . $job['slug'] . "/\n\n"
            . "It stays up for {$days} days. Applications come straight to this email. Filled it early? Reply to this email and we'll take it down.\n"
        );
    } elseif ($action === 'reject_job') {
        $u = $conn->prepare("UPDATE jobs SET status = 'rejected' WHERE id = ?");
        $u->bind_param('i', $job_id);
        $u->execute();
        $u->close();
        rbh_send_email(
            $job['employer_email'],
            'About your job post: ' . $job['title'],
            "Thanks for posting on Recovery Business Hub.\n\nWe couldn't put this one up as written. Reply to this email if you want to know why or send a new version.\n"
        );
    } elseif ($action === 'close_job') {
        $u = $conn->prepare("UPDATE jobs SET status = 'closed' WHERE id = ?");
        $u->bind_param('i', $job_id);
        $u->execute();
        $u->close();
    }
    rbh_log_event('admin_job', ['job_id' => $job_id, 'action' => $action]);
}
