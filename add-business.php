<?php
// add-business.php — public submission form. New rows always land as
// status=pending, tier=free; approval and tier changes happen off-form.
require_once __DIR__ . '/includes/init.php';

$message_ok  = '';
$message_err = '';
$old = ['name' => '', 'category' => '', 'address' => '', 'phone' => '', 'logo' => '', 'description' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $field => $_) {
        $old[$field] = trim($_POST[$field] ?? '');
    }

    // Server-side validation. HTML `required` alone doesn't stop a direct POST.
    $errors = [];
    if ($old['name'] === '' || mb_strlen($old['name']) > 100) {
        $errors[] = 'Business name is required (100 characters max).';
    }
    if (!in_array($old['category'], $categories, true)) {
        $errors[] = 'Please pick a category from the list.';
    }
    if ($old['description'] === '' || mb_strlen($old['description']) > 2000) {
        $errors[] = 'Description is required (2000 characters max).';
    }
    if (mb_strlen($old['address']) > 200) {
        $errors[] = 'Address is too long (200 characters max).';
    }
    if (mb_strlen($old['phone']) > 30) {
        $errors[] = 'Phone number is too long.';
    }
    if ($old['logo'] !== '' && !filter_var($old['logo'], FILTER_VALIDATE_URL)) {
        $errors[] = 'Logo must be a valid URL (or leave it blank).';
    }

    if ($errors) {
        $message_err = implode(' ', $errors);
    } else {
        $status = 'pending';
        $tier   = 'free';

        $stmt = $conn->prepare("INSERT INTO businesses (name, category, description, tier, status, logo, address, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt && $stmt->bind_param('ssssssss', $old['name'], $old['category'], $old['description'], $tier, $status, $old['logo'], $old['address'], $old['phone']) && $stmt->execute()) {
            // Safety net: append every submission to an above-webroot log so
            // nothing is ever lost silently, whatever happens downstream.
            @file_put_contents(
                dirname(__DIR__, 2) . '/rbh-submissions.log',
                json_encode(['ts' => date('c'), 'name' => $old['name'], 'category' => $old['category'],
                    'phone' => $old['phone'], 'address' => $old['address']],
                    JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . "\n",
                FILE_APPEND | LOCK_EX
            );

            // Notify via the TDM lead hub if the site key exists above the
            // webroot. Missing key = no notification, but the submission is
            // already saved, so we don't surface an error to the visitor.
            $key_file = dirname(__DIR__, 2) . '/rbh-site-key.php';
            if (is_file($key_file)) {
                require_once $key_file;
            }
            if (defined('TDM_WEBHOOK_TOKEN') && TDM_WEBHOOK_TOKEN !== '' && function_exists('curl_init')) {
                $payload = json_encode([
                    'token'      => TDM_WEBHOOK_TOKEN,
                    'type'       => 'form',
                    'deliver'    => true,
                    'source'     => 'rbh-add-business',
                    'page_url'   => 'https://recoverybusinesshub.com/add-business.php',
                    'visitor_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'name'       => $old['name'],
                    'phone'      => $old['phone'],
                    'message'    => 'New RBH directory application: ' . $old['category'] . ' — ' . $old['description'],
                ], JSON_UNESCAPED_SLASHES);

                $ch = curl_init('https://dashboard.techdadmedia.com/api/track.php');
                curl_setopt_array($ch, [
                    CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 4,
                ]);
                $resp = curl_exec($ch);
                curl_close($ch);
                $data = is_string($resp) ? json_decode($resp, true) : null;
                if (!is_array($data) || empty($data['ok'])) {
                    error_log('[rbh] hub relay failed — ' . substr((string)$resp, 0, 200));
                }
            }

            if ($stmt) {
                $stmt->close();
            }
            $message_ok = 'Application submitted successfully! It is pending review.';
            $old = ['name' => '', 'category' => '', 'address' => '', 'phone' => '', 'logo' => '', 'description' => ''];
        } else {
            error_log('[rbh] insert failed: ' . $conn->error);
            $message_err = 'Error saving application. Please try again.';
        }
    }
}

$page_title = 'Add a Business | Recovery Business Hub';
require __DIR__ . '/includes/header.php';
?>

    <main class="form-container">
        <h2>Apply to Join the Directory</h2>
        <p>Submit your business details below. All submissions are reviewed before being published.</p>

        <?php if ($message_ok !== ''): ?>
            <p class="form-message form-success"><?php echo htmlspecialchars($message_ok); ?></p>
        <?php elseif ($message_err !== ''): ?>
            <p class="form-message form-error"><?php echo htmlspecialchars($message_err); ?></p>
        <?php endif; ?>

        <form action="add-business.php" method="POST" class="business-form">
            <div class="form-group">
                <label for="name">Business Name *</label>
                <input type="text" id="name" name="name" required maxlength="100" value="<?php echo htmlspecialchars($old['name']); ?>" placeholder="e.g. Second Chance Cafe">
            </div>

            <div class="form-group">
                <label for="category">Category *</label>
                <select id="category" name="category" required>
                    <option value="">Select a Category...</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $old['category'] === $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="address">Street Address</label>
                <input type="text" id="address" name="address" maxlength="200" value="<?php echo htmlspecialchars($old['address']); ?>" placeholder="e.g. 123 Main St, Richmond, IN">
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" maxlength="30" value="<?php echo htmlspecialchars($old['phone']); ?>" placeholder="e.g. (765) 555-0101">
            </div>

            <div class="form-group">
                <label for="logo">Logo Image URL</label>
                <input type="url" id="logo" name="logo" value="<?php echo htmlspecialchars($old['logo']); ?>" placeholder="https://example.com/logo.jpg">
                <small>For now, please provide a link to an image of your logo.</small>
            </div>

            <div class="form-group">
                <label for="description">Business Description *</label>
                <textarea id="description" name="description" rows="4" required maxlength="2000" placeholder="Tell us about your business and your mission..."><?php echo htmlspecialchars($old['description']); ?></textarea>
            </div>

            <button type="submit" class="btn-submit">Submit Application</button>
        </form>
    </main>

<?php require __DIR__ . '/includes/footer.php'; ?>
