<?php
// admin-lib.php: business admin actions shared by admin.php (password login)
// and api/admin.php (token, used by the `rbh` MCP server on THE-ONE).
// Callers check auth first. Needs config.php ($conn) and jobs-lib.php loaded.

if (!isset($conn)) {
    die('admin-lib.php needs config.php first.');
}

// Fields an agent may edit on a listing. Email, logo, hours, tier and status stay out.
const RBH_API_EDIT_FIELDS = [
    'name'          => 100,
    'categories'    => 0,      // array of known categories
    'address'       => 100,
    'city'          => 100,
    'state'         => 5,
    'phone'         => 30,
    'website'       => 255,
    'description'   => 5000,
    'founder_story' => 5000,
];

// approve | reject | verify | unverify on one business.
// Returns null when the business doesn't exist, else
// ['ok' => true, 'mail_sent' => bool|null] (null = no email for this action or no owner email).
function rbh_admin_business_action(mysqli $conn, string $action, int $business_id): ?array
{
    $stmt = $conn->prepare('SELECT id, name, slug, email FROM businesses WHERE id = ?');
    $stmt->bind_param('i', $business_id);
    $stmt->execute();
    $owner = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$owner) {
        return null;
    }

    $mail_sent = null;
    if ($action === 'approve' || $action === 'reject') {
        $new_status = $action === 'approve' ? 'approved' : 'rejected';
        $stmt = $conn->prepare('UPDATE businesses SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $new_status, $business_id);
        $stmt->execute();
        $stmt->close();

        // Let the owner know their listing status changed (skipped if no email on file)
        if (filter_var($owner['email'], FILTER_VALIDATE_EMAIL)) {
            $safe_name = str_replace(["\r", "\n"], '', $owner['name']);
            if ($new_status === 'approved') {
                $subject = 'Your listing is live on Recovery Business Hub';
                $body = "Good news! Your listing for $safe_name was approved and is now live.\n\nView it here: https://www.recoverybusinesshub.com/business/{$owner['slug']}/\n\nQuestions? Just reply to this email.";
            } else {
                $subject = 'Update on your Recovery Business Hub application';
                $body = "Thanks for applying to list $safe_name on Recovery Business Hub.\n\nWe weren't able to approve the listing as submitted. Reply to this email if you'd like details or want to resubmit.";
            }
            $mail_sent = rbh_send_email($owner['email'], $subject, $body);
        }
    } elseif ($action === 'verify' || $action === 'unverify') {
        $flag = $action === 'verify' ? 1 : 0;
        $stmt = $conn->prepare('UPDATE businesses SET is_verified = ? WHERE id = ?');
        $stmt->bind_param('ii', $flag, $business_id);
        $stmt->execute();
        $stmt->close();
    } else {
        return null;
    }
    return ['ok' => true, 'mail_sent' => $mail_sent];
}

// Validate an edit from the API. Returns ['errors' => [field => msg], 'clean' => [column => value]].
function rbh_admin_clean_edit(array $fields): array
{
    global $categories, $states_list;
    $errors = [];
    $clean = [];
    foreach ($fields as $k => $v) {
        if (!array_key_exists($k, RBH_API_EDIT_FIELDS)) {
            $errors[$k] = 'not an editable field';
            continue;
        }
        if ($k === 'categories') {
            if (!is_array($v) || !$v) {
                $errors[$k] = 'needs a non-empty list';
                continue;
            }
            $bad = array_values(array_diff($v, $categories));
            if ($bad) {
                $errors[$k] = 'unknown categories: ' . implode(', ', $bad);
                continue;
            }
            $clean['category'] = json_encode(array_values(array_unique($v)));
            continue;
        }
        if (!is_string($v)) {
            $errors[$k] = 'must be text';
            continue;
        }
        $v = trim(str_replace("\0", '', $v));
        if (in_array($k, ['description', 'founder_story'], true)) {
            $v = str_replace("\r\n", "\n", $v);
        } else {
            $v = preg_replace('/\s+/', ' ', $v);
        }
        if (mb_strlen($v) > RBH_API_EDIT_FIELDS[$k]) {
            $errors[$k] = 'longer than ' . RBH_API_EDIT_FIELDS[$k] . ' characters';
            continue;
        }
        if ($k === 'name' && $v === '') {
            $errors[$k] = 'cannot be empty';
            continue;
        }
        if ($k === 'state' && $v !== '' && !in_array($v, $states_list, true)) {
            $errors[$k] = 'not a US state code';
            continue;
        }
        if ($k === 'website' && $v !== '' && !filter_var($v, FILTER_VALIDATE_URL)) {
            $errors[$k] = 'not a valid URL';
            continue;
        }
        $clean[$k] = $v;
    }
    return ['errors' => $errors, 'clean' => $clean];
}

// Write cleaned columns. Column names come only from RBH_API_EDIT_FIELDS (plus `category`).
function rbh_admin_apply_edit(mysqli $conn, int $business_id, array $clean): void
{
    $sets = [];
    $vals = [];
    foreach ($clean as $col => $val) {
        $sets[] = "`$col` = ?";
        $vals[] = $val;
    }
    if (!$sets) {
        return;
    }
    $vals[] = $business_id;
    $stmt = $conn->prepare('UPDATE businesses SET ' . implode(', ', $sets) . ' WHERE id = ?');
    $stmt->bind_param(str_repeat('s', count($clean)) . 'i', ...$vals);
    $stmt->execute();
    $stmt->close();
}
