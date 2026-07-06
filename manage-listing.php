<?php
// manage-listing.php
// Owner self-service entry point. The owner enters the email on their listing
// and gets a magic edit link by email. No accounts, no passwords.
// The response is the same whether or not the email matched, so this page
// can't be used to fish for which emails are in the directory.
require_once 'config.php'; // starts the session

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);

    if ($email) {
        $stmt = $conn->prepare("SELECT id, name, slug FROM businesses WHERE email = ? AND status IN ('approved', 'pending')");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $matches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (!empty($matches)) {
            $links = [];
            foreach ($matches as $biz) {
                // Raw token goes in the email, only its hash is stored
                $token = bin2hex(random_bytes(32));
                $token_hash = hash('sha256', $token);
                $upd = $conn->prepare("UPDATE businesses SET edit_token_hash = ?, edit_token_expires = DATE_ADD(NOW(), INTERVAL 48 HOUR) WHERE id = ?");
                $upd->bind_param("si", $token_hash, $biz['id']);
                $upd->execute();
                $upd->close();

                $safe_name = str_replace(["\r", "\n"], '', $biz['name']);
                $links[] = $safe_name . ":\nhttps://www.recoverybusinesshub.com/edit-listing.php?token=" . $token;
            }

            $body = "You asked for a link to edit your listing on Recovery Business Hub.\n\n"
                . implode("\n\n", $links)
                . "\n\nThe link works for 48 hours. If you didn't request this, you can ignore this email; your listing is unchanged.";
            rbh_send_email($email, 'Edit your Recovery Business Hub listing', $body);
        }
    }

    // Same message either way, on purpose
    $message = "<p style='color: var(--color-success); font-weight: bold; text-align: center;'>If that email is on a listing, an edit link is on its way. Check your inbox (and spam folder).</p>";
}

$page_title       = 'Manage Your Listing';
$page_description = 'Update your Recovery Business Hub listing. Enter the email on your listing and we\'ll send you a secure edit link.';
include 'header.php';
?>

<main class="form-container">
    <h2>Manage Your Listing</h2>
    <p>Enter the email address on your listing and we'll send you a secure link to edit it. The link works for 48 hours.</p>

    <?php echo $message; ?>

    <form method="POST" class="business-form">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <div class="form-group">
            <label for="email">Email on your listing</label>
            <input type="email" id="email" name="email" required placeholder="hello@yourbusiness.com">
            <small>This has to match the email we have on file. Not sure which one that is? Reach out at recoverybusinesshub@gmail.com.</small>
        </div>
        <button type="submit" class="btn-submit">Send My Edit Link</button>
    </form>
</main>

<?php include 'footer.php'; ?>
