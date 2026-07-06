<?php
// edit-listing.php
// Owner-facing edit form, reached only through a magic link from
// manage-listing.php. Owners can update contact info, hours, logo, and
// descriptions. They can't touch name, tier, status, or verification —
// those stay admin-only in edit-business.php.
require_once 'config.php'; // starts the session

// 1. VALIDATE THE TOKEN
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$business = null;

if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $token_hash = hash('sha256', $token);
    $stmt = $conn->prepare("SELECT * FROM businesses WHERE edit_token_hash = ? AND edit_token_expires > NOW()");
    $stmt->bind_param("s", $token_hash);
    $stmt->execute();
    $business = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$business) {
    $page_title = 'Link Expired';
    include 'header.php';
    echo "<main class='form-container' style='text-align: center;'>
            <h2>This link isn't valid anymore</h2>
            <p>Edit links work for 48 hours. Request a fresh one and you're back in business.</p>
            <a href='/manage-listing.php' class='btn-primary'>Get a New Link</a>
          </main>";
    include 'footer.php';
    exit;
}

$message = "";

// 2. HANDLE THE UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $selected_categories = $_POST['categories'] ?? [];
    if (!is_array($selected_categories)) {
        $selected_categories = [];
    }
    // Drop any submitted category that isn't in our known list
    $selected_categories = array_values(array_intersect($selected_categories, $categories));
    $category_json = json_encode($selected_categories);

    $address = substr($_POST['address'] ?? '', 0, 100);
    $city    = substr($_POST['city'] ?? '', 0, 100);
    $state   = $_POST['state'] ?? '';
    if (!in_array($state, $states_list, true)) {
        $state = '';
    }
    $phone = substr($_POST['phone'] ?? '', 0, 30);

    $email   = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) ? $_POST['email'] : '';
    $website = filter_var($_POST['website'] ?? '', FILTER_VALIDATE_URL) ? $_POST['website'] : '';

    $description   = trim($_POST['description'] ?? '');
    $founder_story = trim($_POST['founder_story'] ?? '');

    $description_len = strlen($description);
    if ($description_len < 30 || $description_len > 3000) {
        $message = "<p style='color: red; text-align: center;'>Business description must be between 30 and 3000 characters.</p>";
    } else {
        $uploaded = handle_logo_upload();
        if ($uploaded !== false) {
            if (!empty($business['logo']) && strpos($business['logo'], 'uploads/') === 0 && file_exists($business['logo'])) {
                unlink($business['logo']);
            }
            $logo_path = $uploaded;
        } else {
            $logo_path = $business['logo']; // keep existing
        }

        $hours_json = parse_hours_post();

        $stmt = $conn->prepare("UPDATE businesses SET category=?, address=?, city=?, state=?, phone=?, email=?, logo=?, website=?, description=?, founder_story=?, hours=? WHERE id=?");
        $stmt->bind_param("sssssssssssi", $category_json, $address, $city, $state, $phone, $email, $logo_path, $website, $description, $founder_story, $hours_json, $business['id']);

        if ($stmt->execute()) {
            $message = "<p style='color: var(--color-success); text-align: center; font-weight: bold;'>Your listing is updated. Changes are live now.</p>";

            // Refresh local copy so the form shows the saved values
            $business = array_merge($business, [
                'category' => $category_json, 'address' => $address, 'city' => $city,
                'state' => $state, 'phone' => $phone, 'email' => $email,
                'logo' => $logo_path, 'website' => $website, 'description' => $description,
                'founder_story' => $founder_story, 'hours' => $hours_json,
            ]);

            // Heads-up to admin so edits don't go unnoticed
            $safe_name = str_replace(["\r", "\n"], '', $business['name']);
            rbh_send_email(
                'recoverybusinesshub@gmail.com',
                'Listing edited by owner: ' . $safe_name,
                "The owner of $safe_name just updated their listing through a magic link.\n\nReview it here: https://www.recoverybusinesshub.com/business/{$business['slug']}/"
            );
        } else {
            $message = "<p style='color: red; text-align: center;'>Error saving changes. Please try again.</p>";
        }
        $stmt->close();
    }
}

$page_title = 'Edit Your Listing';
include 'header.php';
?>

<main class="form-container">
    <h2>Edit: <?php echo htmlspecialchars($business['name']); ?></h2>
    <p>Update your listing below. Changes go live as soon as you save. Need your business name changed? Email recoverybusinesshub@gmail.com.</p>

    <?php echo $message; ?>

    <form method="POST" class="business-form" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

        <div class="form-group">
            <label>Categories * (Select all that apply)</label>
            <div class="category-checkbox-grid">
                <?php
                $current_cat_decoded = json_decode($business['category'], true) ?? [];
                if (!is_array($current_cat_decoded) || empty($current_cat_decoded)) {
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
            <input type="email" name="email" value="<?php echo htmlspecialchars($business['email'] ?? ''); ?>">
            <small>This is also the email that receives edit links, so keep it current.</small>
        </div>

        <div class="form-group" style="border: 1px dashed var(--border-color); padding: 1.5rem; background: var(--accent-bg);">
            <label for="logo_file">Business Logo / Image</label>
            <?php if (!empty($business['logo'])): ?>
                <div style="margin-bottom: 1rem;">
                    <img src="/<?php echo htmlspecialchars(ltrim($business['logo'], '/')); ?>" alt="Current Logo" style="max-height: 80px; border-radius: 8px;">
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
            <small style="margin-top: 0; margin-bottom: 0.5rem; display: block;">When is your business open?</small>
            <div class="schedule-builder">
                <?php
                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                $saved_hours = !empty($business['hours']) ? json_decode($business['hours'], true) : [];

                foreach ($days as $day):
                    $day_data = $saved_hours[$day] ?? ['is_closed' => false, 'open' => '', 'close' => ''];
                    $is_closed = !empty($day_data['is_closed']) ? 'checked' : '';
                    $open = htmlspecialchars($day_data['open']);
                    $close = htmlspecialchars($day_data['close']);
                    $disabled = !empty($day_data['is_closed']) ? 'disabled' : '';
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
            <label>Business Description *</label>
            <textarea name="description" rows="5" required minlength="30"><?php echo htmlspecialchars($business['description']); ?></textarea>
        </div>

        <div class="form-group">
            <label>Comeback Story (Optional)</label>
            <textarea name="founder_story" rows="3" placeholder="Your recovery story, shown as a featured quote on your profile."><?php echo htmlspecialchars($business['founder_story'] ?? ''); ?></textarea>
        </div>

        <button type="submit" class="btn-submit">Save Changes</button>
    </form>
</main>

<?php include 'footer.php'; ?>
