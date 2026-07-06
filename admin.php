<?php
// admin.php
// Connect to the database (config.php also starts the session)
require_once 'config.php';
// --- SECURE PASSWORD HASH ---
// Lives in .env (ADMIN_PASSWORD_HASH) so the hash never sits in a public repo.
// Generate one with: php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT);"
$admin_password_hash = $env_vars['ADMIN_PASSWORD_HASH'] ?? '';
if ($admin_password_hash === '') {
    die('Admin login is not configured. Add ADMIN_PASSWORD_HASH to .env.');
}
$error = '';

// Handle the Login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    csrf_verify();
    $entered_password = $_POST['password'] ?? '';

    // password_verify() safely compares what you typed to the hash
    // admin.php — replace lines 18-19:
    if (password_verify($entered_password, $admin_password_hash)) {
        session_regenerate_id(true); // ADD THIS LINE
        $_SESSION['logged_in'] = true;
    } else {
        $error = 'Incorrect password. Please try again.';
    }
}

// Handle the Logout action
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

// Handle Approve / Reject / Delete actions (only if logged in)
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_POST['action'])) {
    csrf_verify();
    $business_id = (int) $_POST['business_id']; // The ID of the business
    $action = $_POST['action']; // Will be 'approve', 'reject', or 'delete'

    if ($action === 'approve' || $action === 'reject') {
        $new_status = $action === 'approve' ? 'approved' : 'rejected';
        $stmt = $conn->prepare("UPDATE businesses SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $business_id);
        $stmt->execute();
        $stmt->close();

        // Let the owner know their listing status changed (skipped if no email on file)
        $stmt = $conn->prepare("SELECT name, slug, email FROM businesses WHERE id = ?");
        $stmt->bind_param("i", $business_id);
        $stmt->execute();
        $owner = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($owner && filter_var($owner['email'], FILTER_VALIDATE_EMAIL)) {
            $safe_name = str_replace(["\r", "\n"], '', $owner['name']);
            if ($new_status === 'approved') {
                $subject = 'Your listing is live on Recovery Business Hub';
                $body = "Good news! Your listing for $safe_name was approved and is now live.\n\nView it here: https://www.recoverybusinesshub.com/business/{$owner['slug']}/\n\nQuestions? Just reply to this email.";
            } else {
                $subject = 'Update on your Recovery Business Hub application';
                $body = "Thanks for applying to list $safe_name on Recovery Business Hub.\n\nWe weren't able to approve the listing as submitted. Reply to this email if you'd like details or want to resubmit.";
            }
            rbh_send_email($owner['email'], $subject, $body);
        }
    } elseif ($action === 'delete_review') {
        $review_id = (int) ($_POST['review_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM reviews WHERE id = ?");
        $stmt->bind_param("i", $review_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'delete') {
        $logo_to_delete = null;
        $logo_stmt = $conn->prepare("SELECT logo FROM businesses WHERE id = ?");
        $logo_stmt->bind_param("i", $business_id);
        $logo_stmt->execute();
        $logo_result = $logo_stmt->get_result();
        if ($logo_row = $logo_result->fetch_assoc()) {
            $logo_to_delete = $logo_row['logo'];
        }
        $logo_stmt->close();

        $stmt = $conn->prepare("DELETE FROM businesses WHERE id = ?");
        $stmt->bind_param("i", $business_id);
        $stmt->execute();
        $stmt->close();

        if (!empty($logo_to_delete) && strpos($logo_to_delete, 'uploads/') === 0 && file_exists($logo_to_delete)) {
            unlink($logo_to_delete);
        }
    } elseif ($action === 'verify') {
        $stmt = $conn->prepare("UPDATE businesses SET is_verified = 1 WHERE id = ?");
        $stmt->bind_param("i", $business_id);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'unverify') {
        $stmt = $conn->prepare("UPDATE businesses SET is_verified = 0 WHERE id = ?");
        $stmt->bind_param("i", $business_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Fetch dashboard stats
$stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'this_month' => 0];
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $s = $conn->query("SELECT status, COUNT(*) as c FROM businesses GROUP BY status");
    if ($s) {
        while ($r = $s->fetch_assoc()) {
            $stats[$r['status']] = $r['c'];
            $stats['total'] += $r['c'];
        }
    }
    $m = $conn->query("SELECT COUNT(*) as c FROM businesses WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())");
    if ($m) {
        $stats['this_month'] = $m->fetch_assoc()['c'];
    }
}

// Admin search & filter vars (needed before HTML renders the search bar)
$admin_search = isset($_GET['q']) ? trim($_GET['q']) : '';
$admin_status = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

$page_title = 'Admin Dashboard';
include 'header.php';
?>

<main class="form-container" style="max-width: 1100px; width: 100%;">

    <?php if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true): ?>
        <h2>Admin Login</h2>
        <p>Please enter the administrator password to manage listings.</p>

        <?php if ($error) echo "<p style='color: red; font-weight: bold;'>" . htmlspecialchars($error) . "</p>"; ?>

        <form method="POST" class="business-form">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">


            <div class="form-group">
                <input type="password" name="password" required placeholder="Enter Admin Password" autocomplete="current-password">
            </div>
            <button type="submit" name="login" class="btn-primary" style="width: 100%;">Login</button>
        </form>

    <?php else: ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2>Business Directory Management</h2>
            <a href="?logout=true" style="color: red; font-weight: bold;">Logout</a>
        </div>

        <!-- Dashboard Stats -->
        <div class="stats-grid">
            <div class="stat-card stat-card--total">
                <div class="stat-card__number"><?php echo $stats['total']; ?></div>
                <div class="stat-card__label">Total Listings</div>
            </div>
            <div class="stat-card stat-card--pending">
                <div class="stat-card__number"><?php echo $stats['pending']; ?></div>
                <div class="stat-card__label">Pending Review</div>
            </div>
            <div class="stat-card stat-card--approved">
                <div class="stat-card__number"><?php echo $stats['approved']; ?></div>
                <div class="stat-card__label">Approved</div>
            </div>
            <div class="stat-card stat-card--month">
                <div class="stat-card__number"><?php echo $stats['this_month']; ?></div>
                <div class="stat-card__label">New This Month</div>
            </div>
        </div>

        <!-- Admin Search & Filter Bar -->
        <form method="GET" style="display: flex; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 1.5rem; align-items: center;">
            <input type="text" name="q" placeholder="Search by name, category, or address..."
                value="<?php echo htmlspecialchars($admin_search ?? ''); ?>"
                style="flex: 1; min-width: 220px; padding: 0.6rem 1rem; border: 1px solid #ddd; border-radius: 8px; font-size: 0.95rem;">
            <select name="status_filter" style="padding: 0.6rem 1rem; border: 1px solid #ddd; border-radius: 8px; font-size: 0.95rem;">
                <option value="">All Statuses</option>
                <option value="pending" <?php if (($admin_status ?? '') === 'pending')  echo 'selected'; ?>>Pending</option>
                <option value="approved" <?php if (($admin_status ?? '') === 'approved') echo 'selected'; ?>>Approved</option>
                <option value="rejected" <?php if (($admin_status ?? '') === 'rejected') echo 'selected'; ?>>Rejected</option>
            </select>
            <button type="submit" class="btn-primary" style="padding: 0.6rem 1.2rem;">🔍 Search</button>
            <?php if (!empty($admin_search) || !empty($admin_status)): ?>
                <a href="admin.php" class="btn-secondary" style="text-decoration: none; padding: 0.6rem 1rem;">Clear</a>
            <?php endif; ?>
        </form>

        <?php

        $admin_where = [];
        $admin_params = [];
        $admin_types = '';

        if (!empty($admin_search)) {
            $admin_where[] = "(name LIKE ? OR category LIKE ? OR address LIKE ?)";
            $t = "%$admin_search%";
            array_push($admin_params, $t, $t, $t);
            $admin_types .= 'sss';
        }
        if (!empty($admin_status)) {
            $admin_where[] = "status = ?";
            $admin_params[] = $admin_status;
            $admin_types .= 's';
        }

        $admin_where_sql = !empty($admin_where) ? 'WHERE ' . implode(' AND ', $admin_where) : '';

        // --- PAGINATION LOGIC ---
        $limit = 50; // How many listings to show per page
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($page < 1) $page = 1;
        $offset = ($page - 1) * $limit; // Calculate where to start pulling data

        // Count total matches so we know whether a next page actually exists
        $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM businesses $admin_where_sql");
        if (!empty($admin_params)) {
            $count_stmt->bind_param($admin_types, ...$admin_params);
        }
        $count_stmt->execute();
        $admin_total = (int) $count_stmt->get_result()->fetch_assoc()['total'];
        $count_stmt->close();
        $admin_total_pages = (int) ceil($admin_total / $limit);

        // Add LIMIT and OFFSET to the SQL query
        $sql = "SELECT * FROM businesses $admin_where_sql ORDER BY CASE status WHEN 'pending' THEN 1 WHEN 'approved' THEN 2 WHEN 'rejected' THEN 3 ELSE 4 END, created_at DESC LIMIT ? OFFSET ?";

        // Securely bind the limit and offset variables to prevent SQL injection
        $admin_params[] = $limit;
        $admin_params[] = $offset;
        $admin_types .= 'ii'; // 'i' stands for integer

        $stmt_admin = $conn->prepare($sql);
        if (!empty($admin_params)) {
            $stmt_admin->bind_param($admin_types, ...$admin_params);
        }
        $stmt_admin->execute();
        $result = $stmt_admin->get_result();
        $stmt_admin->close();

        if ($result && $result->num_rows > 0):
            while ($row = $result->fetch_assoc()):
        ?>
                <div class="business-card" style="margin-bottom: 1.5rem; border: 2px solid var(--border-color);">

                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                        <h3 style="color: var(--primary-color); margin: 0;"><?php echo htmlspecialchars($row['name']); ?></h3>

                        <?php if ($row['status'] === 'pending'): ?>
                            <span style="background: #FFF3E0; color: #E65100; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold;">Pending</span>
                        <?php elseif ($row['status'] === 'approved'): ?>
                            <span style="background: #E8F5E9; color: #2E7D32; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold;">Approved</span>
                        <?php else: ?>
                            <span style="background: #FFEBEE; color: #C62828; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold;">Rejected</span>
                        <?php endif; ?>
                    </div>

                    <p><strong>Category:</strong> <?php echo htmlspecialchars($row['category']); ?></p>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($row['address']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($row['phone']); ?></p>
                    <p style="margin-top: 10px;"><strong>Description:</strong><br> <?php echo nl2br(htmlspecialchars($row['description'])); ?></p>

                    <?php
                    // Reviews for this listing, so spam can be removed without touching the database directly
                    $rev_stmt = $conn->prepare("SELECT id, reviewer_name, rating, comment, created_at FROM reviews WHERE business_id = ? ORDER BY created_at DESC LIMIT 20");
                    $rev_stmt->bind_param("i", $row['id']);
                    $rev_stmt->execute();
                    $biz_reviews = $rev_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $rev_stmt->close();
                    ?>
                    <?php if (!empty($biz_reviews)): ?>
                        <details style="margin-top: 1rem;">
                            <summary style="cursor: pointer; font-weight: bold; color: var(--text-muted);">Reviews (<?php echo count($biz_reviews); ?>)</summary>
                            <?php foreach ($biz_reviews as $rev): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 0.5rem 0; border-bottom: 1px solid var(--border-color);">
                                    <div style="font-size: 0.9rem;">
                                        <strong><?php echo htmlspecialchars($rev['reviewer_name']); ?></strong>
                                        <span style="color: #F59E0B;"><?php echo str_repeat('★', (int)$rev['rating']); ?></span>
                                        <span style="color: var(--text-muted);"><?php echo date('M j, Y', strtotime($rev['created_at'])); ?></span>
                                        <?php if (!empty($rev['comment'])): ?><br><?php echo htmlspecialchars($rev['comment']); ?><?php endif; ?>
                                    </div>
                                    <form method="POST" style="margin: 0;">
                                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                        <input type="hidden" name="business_id" value="<?php echo $row['id']; ?>">
                                        <input type="hidden" name="review_id" value="<?php echo $rev['id']; ?>">
                                        <button type="submit" name="action" value="delete_review" style="background: none; border: 1px solid #d32f2f; color: #d32f2f; border-radius: 5px; padding: 2px 8px; cursor: pointer; font-size: 0.8rem;" onclick="return confirm('Delete this review permanently?');">Delete</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </details>
                    <?php endif; ?>

                    <form method="POST" style="margin-top: 1.5rem; display: flex; gap: 15px; flex-wrap: wrap;">
                        <input type="hidden" name="business_id" value="<?php echo $row['id']; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

                        <?php if ($row['status'] === 'pending'): ?>
                            <button type="submit" name="action" value="approve" class="btn-primary" style="background-color: var(--accent-color); border: none; cursor: pointer;">✅ Approve</button>
                            <button type="submit" name="action" value="reject" class="btn-primary" style="background-color: #f57c00; border: none; cursor: pointer;">❌ Reject</button>
                        <?php endif; ?>

                        <?php if (empty($row['is_verified'])): ?>
                            <button type="submit" name="action" value="verify" class="btn-primary" style="background-color: #1565C0; border: none; cursor: pointer;">✔ Verify</button>
                        <?php else: ?>
                            <button type="submit" name="action" value="unverify" class="btn-primary" style="background-color: #78909C; border: none; cursor: pointer;">✖ Unverify</button>
                        <?php endif; ?>

                        <a href="edit-business.php?id=<?php echo $row['id']; ?>" class="btn-primary" style="background-color: #2196F3; border: none; text-decoration: none; padding: 0.6rem 1.2rem; border-radius: 5px; color: white;">✏️ Edit</a>

                        <button type="submit" name="action" value="delete" class="btn-primary" style="background-color: #d32f2f; border: none; cursor: pointer;" onclick="return confirm('Are you sure you want to permanently delete this application? This cannot be undone.');">🗑️ Delete</button>
                    </form>
                </div>

            <?php
            endwhile;
            ?>

            <div style="display: flex; justify-content: space-between; margin-top: 2rem; margin-bottom: 2rem;">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&q=<?php echo urlencode($admin_search); ?>&status_filter=<?php echo urlencode($admin_status); ?>" class="btn-primary" style="text-decoration: none; background-color: #555;">&larr; Previous Page</a>
                <?php else: ?>
                    <div></div> <?php endif; ?>

                <?php if ($page < $admin_total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&q=<?php echo urlencode($admin_search); ?>&status_filter=<?php echo urlencode($admin_status); ?>" class="btn-primary" style="text-decoration: none; background-color: #555;">Next Page &rarr;</a>
                <?php endif; ?>
            </div>

        <?php
        else:
        ?>
            <p style="text-align: center; color: var(--text-muted);">Your directory is currently empty.</p>
        <?php endif; ?>

    <?php endif; ?>

</main>

<?php include 'footer.php'; ?>