<?php
// edit-listing.php
// Owner-facing edit form, reached only through a magic link from
// manage-listing.php. Owners can update contact info, hours, logo, and
// descriptions. They can't touch name, status, or verification.
// Those stay admin-only in edit-business.php.
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
    $robots_directive = 'noindex, nofollow';
    $page_title = 'Link expired';
    include 'header.php';
    echo "<main class='form-container'>
            <h1>This link isn't valid anymore</h1>
            <p>Edit links work for 48 hours. Ask for a fresh one and you're back in.</p>
            <a href='/manage-listing' class='btn-secondary'>Get a new link</a>
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
        $message = "<p class='form-note form-note--error'>The description needs to be 30 to 3000 characters.</p>";
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
            $message = "<p class='form-note form-note--success'>Saved. Your changes are live now.</p>";

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
            $message = "<p class='form-note form-note--error'>That didn't save. Try again.</p>";
        }
        $stmt->close();
    }
}

$robots_directive = 'noindex, nofollow';
$page_title = 'Edit your listing';
include 'header.php';
?>

<main class="form-container">
    <h1>Edit <?php echo htmlspecialchars($business['name']); ?></h1>
    <p>Change what you want and save. Changes go live right away. To change your business name, write to recoverybusinesshub@gmail.com.</p>

    <?php echo $message; ?>

    <form method="POST" class="business-form" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

        <div class="form-group">
            <span class="form-label">Categories * (pick all that apply)</span>
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
            <label for="address">Street address</label>
            <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($business['address'] ?? ''); ?>">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="city">City</label>
                <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($business['city'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="state">State</label>
                <select id="state" name="state">
                    <option value="">Select</option>
                    <?php foreach ($states_list as $st): ?>
                        <option value="<?php echo htmlspecialchars($st); ?>" <?php if (($business['state'] ?? '') == $st) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($st); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="phone">Phone</label>
            <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($business['phone']); ?>">
        </div>

        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($business['email'] ?? ''); ?>">
            <small>Edit links go to this email, so keep it current.</small>
        </div>

        <div class="form-group form-logo-preview">
            <label for="logo_file">Logo or photo</label>
            <?php if (!empty($business['logo'])): ?>
                <div>
                    <img src="/<?php echo htmlspecialchars(ltrim($business['logo'], '/')); ?>" alt="Your current logo">
                    <small>Current image</small>
                </div>
            <?php endif; ?>
            <input type="file" id="logo_file" name="logo_file" accept=".jpg,.jpeg,.png,.webp">
            <small>Leave it blank to keep the current image. A new one (up to 2MB) replaces it.</small>
        </div>

        <div class="form-group">
            <label for="website">Website</label>
            <input type="url" id="website" name="website" value="<?php echo htmlspecialchars($business['website'] ?? ''); ?>" placeholder="https://yourbusiness.com">
        </div>

        <div class="form-group">
            <span class="form-label">Hours</span>
            <small>Tick Closed for any day you're shut.</small>
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
                            <label>
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
            <label for="description">About the business *</label>
            <textarea id="description" name="description" rows="5" required minlength="30"><?php echo htmlspecialchars($business['description']); ?></textarea>
        </div>

        <div class="form-group">
            <label for="founder_story">Your story (optional)</label>
            <textarea id="founder_story" name="founder_story" rows="3" placeholder="Share as much or as little as you want. Leave it blank and nothing about your recovery shows."><?php echo htmlspecialchars($business['founder_story'] ?? ''); ?></textarea>
        </div>

        <button type="submit" class="btn-submit">Save changes</button>
    </form>
</main>

<?php include 'footer.php'; ?>
