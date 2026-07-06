<?php
// edit-business.php
require_once 'config.php'; // starts the session

// 1. SECURITY CHECK: Kick out anyone who isn't logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: admin.php");
    exit;
}

$message = "";
$business_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// 2. FETCH CURRENT DATA: Get the business info to pre-fill the form (and get the old logo path)
$stmt = $conn->prepare("SELECT * FROM businesses WHERE id = ?");
$stmt->bind_param("i", $business_id);
$stmt->execute();
$result = $stmt->get_result();
$business = $result->fetch_assoc();
$stmt->close();

// If someone types a random ID in the URL that doesn't exist
if (!$business) {
    die("<h2 style='text-align:center; padding: 2rem;'>Business not found. <a href='admin.php'>Go back</a></h2>");
}

// 3. IF THE FORM IS SUBMITTED: Update the database
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_verify();
    $name = $_POST['name'] ?? '';

    $selected_categories = $_POST['categories'] ?? [];
    if (!is_array($selected_categories)) {
        $selected_categories = [];
    }
    // Drop any submitted category that isn't in our known list
    $selected_categories = array_values(array_intersect($selected_categories, $categories));
    $category_json = json_encode($selected_categories);

    $address = $_POST['address'] ?? '';
    $city = $_POST['city'] ?? '';
    $state = $_POST['state'] ?? '';
    if (!in_array($state, $states_list, true)) {
        $state = '';
    }
    $phone = $_POST['phone'] ?? '';

    // SERVER-SIDE VALIDATION: Email and Website
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) ? $_POST['email'] : '';
    $website = filter_var($_POST['website'] ?? '', FILTER_VALIDATE_URL) ? $_POST['website'] : '';

    $description = trim($_POST['description'] ?? '');
    $founder_story = trim($_POST['founder_story'] ?? '');

    $allowed_tiers    = ['free', 'paid', 'premium'];
    $allowed_statuses = ['pending', 'approved', 'rejected'];
    $tier   = in_array($_POST['tier']   ?? '', $allowed_tiers)    ? $_POST['tier']   : 'free';
    $status = in_array($_POST['status'] ?? '', $allowed_statuses) ? $_POST['status'] : 'pending';

    $uploaded = handle_logo_upload();
    if ($uploaded !== false) {
        if (!empty($business['logo']) && strpos($business['logo'], 'uploads/') === 0 && file_exists($business['logo'])) {
            unlink($business['logo']);
        }
        $logo_path = $uploaded;
    } else {
        $logo_path = $business['logo']; // keep existing
    }

    // Business Hours Processing
    $hours_json = parse_hours_post();

    // Prepare the UPDATE statement
    $stmt = $conn->prepare("UPDATE businesses SET name=?, category=?, address=?, city=?, state=?, phone=?, email=?, logo=?, website=?, description=?, founder_story=?, hours=?, tier=?, status=? WHERE id=?");

    if ($stmt) {
        $stmt->bind_param("ssssssssssssssi", $name, $category_json, $address, $city, $state, $phone, $email, $logo_path, $website, $description, $founder_story, $hours_json, $tier, $status, $business_id);
        if ($stmt->execute()) {
            $message = "<p style='color: var(--accent-color); text-align: center; font-weight: bold;'>Business updated successfully!</p>";
            $business['logo'] = $logo_path;
            $business['hours'] = $hours_json;
            $business['category'] = $category_json;
            $business['founder_story'] = $founder_story;
        } else {
            $message = "<p style='color: red; text-align: center;'>Error updating business.</p>";
        }
        $stmt->close();
    }
}

include 'header.php';
?>

<main class="form-container">
    <div style="margin-bottom: 1rem;">
        <a href="admin.php" style="color: var(--primary-color); font-weight: bold;">&larr; Back to Dashboard</a>
    </div>

    <h2>Edit Business: <?php echo htmlspecialchars($business['name']); ?></h2>

    <?php echo $message; ?>

    <form method="POST" class="business-form" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <div class="form-group">
            <label>Business Name</label>
            <input type="text" name="name" required value="<?php echo htmlspecialchars($business['name']); ?>">
        </div>

        <div class="form-group">
            <label>Categories *</label>
            <div class="category-checkbox-grid">
                <?php
                // FALLBACK: Safe JSON decoding
                $current_cat_decoded = json_decode($business['category'], true) ?? [];

                if (!is_array($current_cat_decoded) || empty($current_cat_decoded)) {
                    // Fallback for old single strings just in case they haven't run the SQL
                    $current_cat_decoded = [$business['category']];
                }

                foreach ($categories as $cat):
                    $is_checked = in_array($cat, $current_cat_decoded) ? 'checked' : '';
                ?>
                    <label class="category-checkbox-label">
                        <input type="checkbox" name="categories[]" value="<?php echo htmlspecialchars($cat); ?>" <?php echo $is_checked; ?>>
                        <span><?php echo htmlspecialchars($cat); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

        </div>

        <div class="form-group">
            <label>Street Address</label>
            <input type="text" name="address" value="<?php echo htmlspecialchars($business['address'] ?? ''); ?>">
        </div>

        <div style="display: flex; gap: 15px;">
            <div class="form-group" style="flex: 2;">
                <label>City</label>
                <input type="text" name="city" value="<?php echo htmlspecialchars($business['city'] ?? ''); ?>">
            </div>
            <div class="form-group" style="flex: 1;">
                <label>State</label>
                <select name="state">
                    <option value="">Select...</option>
                    <?php foreach ($states_list as $st): ?>
                        <option value="<?php echo htmlspecialchars($st); ?>" <?php if (($business['state'] ?? '') == $st) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($st); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Phone Number</label>
            <input type="text" name="phone" value="<?php echo htmlspecialchars($business['phone']); ?>">
        </div>

        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($business['email'] ?? ''); ?>" placeholder="hello@yourbusiness.com">
        </div>

        <div class="form-group" style="border: 1px dashed var(--border-color); padding: 1.5rem; background: var(--surface-bg);">
            <label for="logo_file">Business Logo / Image</label>
            <?php if (!empty($business['logo'])): ?>
                <div style="margin-bottom: 1rem;">
                    <img src="<?php echo htmlspecialchars($business['logo']); ?>" alt="Current Logo" style="max-height: 80px; border-radius: 8px;">
                    <p style="font-size: 0.85rem; color: var(--text-muted);">Current Image</p>
                </div>
            <?php endif; ?>
            <input type="file" id="logo_file" name="logo_file" accept=".jpg,.jpeg,.png,.webp">
            <small>Leave blank to keep the current image. Upload a new image (Max 2MB) to overwrite it.</small>
        </div>

        <div class="form-group">
            <label>Business Website</label>
            <input type="url" name="website" value="<?php echo htmlspecialchars($business['website'] ?? ''); ?>" placeholder="https://yourbusiness.com">
        </div>

        <div class="form-group">
            <label>Business Hours</label>
            <small style="margin-top: 0; margin-bottom: 0.5rem; display: block;">When is this business open?</small>
            <div class="schedule-builder">
                <?php
                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                $saved_hours = !empty($business['hours']) ? json_decode($business['hours'], true) : [];

                foreach ($days as $day):
                    $day_data = $saved_hours[$day] ?? ['is_closed' => false, 'open' => '', 'close' => ''];
                    $is_closed = $day_data['is_closed'] ? 'checked' : '';
                    $open = htmlspecialchars($day_data['open']);
                    $close = htmlspecialchars($day_data['close']);
                    $disabled = $day_data['is_closed'] ? 'disabled' : '';
                ?>
                    <div class="schedule-row">
                        <div class="schedule-day"><?php echo $day; ?></div>
                        <div class="schedule-inputs">
                            <label style="margin: 0; font-weight: normal; font-size: 0.9rem;">
                                <input type="checkbox" name="hours[<?php echo $day; ?>][is_closed]" value="1" <?php echo $is_closed; ?> onchange="toggleHours(this)"> Closed
                            </label>
                            <input type="time" name="hours[<?php echo $day; ?>][open]" class="time-input" value="<?php echo $open; ?>" <?php echo $disabled; ?>>
                            <span>to</span>
                            <input type="time" name="hours[<?php echo $day; ?>][close]" class="time-input" value="<?php echo $close; ?>" <?php echo $disabled; ?>>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <script>
            function toggleHours(checkbox) {
                const row = checkbox.closest('.schedule-row');
                const timeInputs = row.querySelectorAll('.time-input');
                timeInputs.forEach(input => {
                    input.disabled = checkbox.checked;
                    if (checkbox.checked) input.value = '';
                });
            }
        </script>

        <div class="form-group">
            <label>Business Description</label>
            <textarea name="description" rows="5" required><?php echo htmlspecialchars($business['description']); ?></textarea>
        </div>

        <div class="form-group">
            <label>Comeback Story (Optional)</label>
            <textarea name="founder_story" rows="3" placeholder="The owner's recovery story, shown as a featured quote on the profile."><?php echo htmlspecialchars($business['founder_story'] ?? ''); ?></textarea>
        </div>

        <div style="background-color: var(--accent-bg); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid var(--border-color);">
            <h3 style="margin-bottom: 1rem; color: var(--accent-color);">Admin Controls</h3>

            <div class="form-group">
                <label>Subscription Tier</label>
                <select name="tier">
                    <option value="free" <?php if ($business['tier'] == 'free') echo 'selected'; ?>>Free</option>
                    <option value="paid" <?php if ($business['tier'] == 'paid') echo 'selected'; ?>>Featured (Paid)</option>
                    <option value="premium" <?php if ($business['tier'] == 'premium') echo 'selected'; ?>>Premium Partner</option>
                </select>
            </div>

            <div class="form-group">
                <label>Listing Status</label>
                <select name="status">
                    <option value="pending" <?php if ($business['status'] == 'pending') echo 'selected'; ?>>Pending</option>
                    <option value="approved" <?php if ($business['status'] == 'approved') echo 'selected'; ?>>Approved</option>
                    <option value="rejected" <?php if ($business['status'] == 'rejected') echo 'selected'; ?>>Rejected</option>
                </select>
            </div>
        </div>

        <button type="submit" class="btn-submit">Save Changes</button>
    </form>
</main>

<?php include 'footer.php'; ?>