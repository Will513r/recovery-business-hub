<?php
// add-business.php
session_start();
require_once 'config.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    csrf_verify();

    // --- RECAPTCHA VERIFICATION ---
    $recaptcha_secret = $env_vars['RECAPTCHA_SECRET_KEY'] ?? '';
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

    // If a secret is configured, a token is REQUIRED. Otherwise a bot can
    // skip verification entirely by just omitting g-recaptcha-response.
    if (!empty($recaptcha_secret)) {
        $recaptcha_ok = false;
        if (!empty($recaptcha_response)) {
            $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
            $response = file_get_contents($verify_url . '?secret=' . urlencode($recaptcha_secret) . '&response=' . urlencode($recaptcha_response));
            $response_data = json_decode($response);
            $recaptcha_ok = !empty($response_data->success) && ($response_data->score ?? 0) >= 0.5;
        }
        if (!$recaptcha_ok) {
            die("<p style='color:red; text-align:center;'>Bot behavior detected. Application rejected.</p>");
        }
    }

    $name = $_POST['name'] ?? '';

    // Generate the SEO-friendly slug
    $slug = strtolower(trim($name)); // Make lowercase and remove extra spaces
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug); // Replace special characters with hyphens
    $slug = preg_replace('/-+/', "-", $slug); // Remove multiple consecutive hyphens
    $slug = trim($slug, '-'); // Trim leading/trailing hyphens
    if ($slug === '' || preg_match('/^-+$/', $slug)) {
        $slug = 'business'; // Fallback if nothing usable was left
    }

    // Make sure the slug is unique in the database, appending -2, -3, etc. if taken
    $base_slug = $slug;
    $suffix = 2;
    $slug_check = $conn->prepare("SELECT id FROM businesses WHERE slug = ?");
    if ($slug_check) {
        while ($suffix <= 50) {
            $slug_check->bind_param("s", $slug);
            $slug_check->execute();
            $slug_check->store_result();
            $taken = $slug_check->num_rows > 0;
            $slug_check->free_result();
            if (!$taken) {
                break;
            }
            $slug = $base_slug . '-' . $suffix;
            $suffix++;
        }
        // Still taken after 50 numeric tries: fall back to a random suffix
        if ($suffix > 50) {
            $slug_check->bind_param("s", $slug);
            $slug_check->execute();
            $slug_check->store_result();
            $taken = $slug_check->num_rows > 0;
            $slug_check->free_result();
            if ($taken) {
                $slug = $base_slug . '-' . bin2hex(random_bytes(2));
            }
        }
        $slug_check->close();
    }

    // Process categories array
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
    $phone = $_POST['phone'] ?? '';

    // SERVER-SIDE VALIDATION: Email and Website
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) ? $_POST['email'] : '';
    $website = filter_var($_POST['website'] ?? '', FILTER_VALIDATE_URL) ? $_POST['website'] : '';

    $description = $_POST['description'] ?? '';
    $founder_story = $_POST['founder_story'] ?? '';

    // Secure Image Upload Handling
    $uploaded = handle_logo_upload();
    $logo_path = $uploaded !== false ? $uploaded : '';

    // Business Hours Processing
    $hours_json = parse_hours_post();

    // Grab the tier from the form, default to free
    $tier = $_POST['tier'] ?? 'free';
    if (!in_array($tier, ['free', 'paid', 'premium'], true)) {
        $tier = 'free';
    }
    $status = 'pending';

    // SERVER-SIDE VALIDATION: reject bad input and enforce field length limits
    $name_len = strlen(trim($name));
    $description_len = strlen(trim($description));
    if ($name_len < 3 || $name_len > 100) {
        $message = "<p style='color: red; text-align: center;'>Business name must be between 3 and 100 characters.</p>";
    } elseif ($description_len < 30 || $description_len > 3000) {
        $message = "<p style='color: red; text-align: center;'>Business description must be between 30 and 3000 characters.</p>";
    } else {
        if (!in_array($state, $states_list, true)) {
            $state = '';
        }
        $phone = substr($phone, 0, 30);
        $city = substr($city, 0, 100);
        $address = substr($address, 0, 100);

        $stmt = $conn->prepare("INSERT INTO businesses (name, slug, category, description, tier, status, logo, address, city, state, phone, email, website, founder_story, hours) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if ($stmt) {
            $stmt->bind_param("sssssssssssssss", $name, $slug, $category_json, $description, $tier, $status, $logo_path, $address, $city, $state, $phone, $email, $website, $founder_story, $hours_json);

            if ($stmt->execute()) {
                // SECURITY: Strip newlines to prevent Email Header Injection
                $safe_name_for_email = str_replace(["\r", "\n", "%0a", "%0d"], '', $name);

                // Send email notification
                $to = 'recoverybusinesshub@gmail.com';
                $subject = 'New Business Application: ' . $safe_name_for_email;
                $email_body = "Great news! A new business applied.\n\nName: $name\nTier: $tier\n\nNOTE: If tier is paid or premium, confirm the subscription exists in Stripe before approving.\n\nLogin to approve: https://www.recoverybusinesshub.com/admin.php";
                $headers = "From: admin@recoverybusinesshub.com";
                mail($to, $subject, $email_body, $headers);

                // --- BULLETPROOF STRIPE REDIRECT LOGIC ---
                if ($tier === 'paid') {
                    header("Location: " . STRIPE_PAID_URL);
                    exit;
                } elseif ($tier === 'premium') {
                    header("Location: " . STRIPE_PREMIUM_URL);
                    exit;
                } else {
                    // Free tier gets standard on-page success message
                    $message = "<p style='color: var(--accent-color); font-weight: bold; text-align: center;'>Application submitted successfully! It is pending review.</p>";
                }
            } else {
                $message = "<p style='color: red; text-align: center;'>Error saving application. Please try again.</p>";
            }
            $stmt->close();
        } else {
            $message = "<p style='color: red; text-align: center;'>Database error: Could not prepare statement.</p>";
        }
    }
}

$page_title       = 'List Your Business';
$page_description = 'Apply to join the Recovery Business Hub directory. Submit your recovery-owned business and connect with customers who want to support second chances.';
include 'header.php';
?>

<script src="https://www.google.com/recaptcha/api.js?render=<?php echo htmlspecialchars($env_vars['RECAPTCHA_SITE_KEY'] ?? ''); ?>"></script>

<main class="form-container">
    <h2>Apply to Join the Directory</h2>
    <p>Submit your business details below. All submissions are reviewed before being published.</p>

    <?php echo $message; ?>

    <form action="add-business.php" method="POST" class="business-form" id="businessForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

        <input type="hidden" id="g-recaptcha-response" name="g-recaptcha-response">

        <div class="form-group">
            <label for="name">Business Name *</label>
            <input type="text" id="name" name="name" required minlength="3" placeholder="e.g. Second Chance Cafe">
        </div>
        <?php
        // Check if they came from the pricing page with a pre-selected plan
        $selected_plan = isset($_GET['plan']) ? $_GET['plan'] : 'free';
        ?>
        <div class="form-group" style="background: var(--accent-bg); padding: 1rem; border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 1.5rem;">
            <label for="tier" style="color: var(--primary-color); font-weight: bold;">Select Your Listing Plan *</label>
            <select id="tier" name="tier" required style="font-weight: bold;">
                <option value="free" <?php if ($selected_plan == 'free') echo 'selected'; ?>>Basic Listing (Free)</option>
                <option value="paid" <?php if ($selected_plan == 'paid') echo 'selected'; ?>>Featured Listing ($9/mo)</option>
                <option value="premium" <?php if ($selected_plan == 'premium') echo 'selected'; ?>>Premium Partner ($29/mo)</option>
            </select>
            <small>If you select a paid plan, you will be redirected to our secure Stripe checkout after clicking submit.</small>
        </div>
        <div class="form-group">
            <label>Categories * (Select all that apply)</label>
            <div class="category-checkbox-grid">
                <?php foreach ($categories as $cat): ?>
                    <label class="category-checkbox-label">
                        <input type="checkbox" name="categories[]" value="<?php echo htmlspecialchars($cat); ?>">
                        <span><?php echo htmlspecialchars($cat); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

        </div>

        <div class="form-group">
            <label for="address">Street Address</label>
            <input type="text" id="address" name="address" placeholder="e.g. 123 Main St">
        </div>

        <div style="display: flex; gap: 15px;">
            <div class="form-group" style="flex: 2;">
                <label for="city">City</label>
                <input type="text" id="city" name="city" placeholder="e.g. Richmond">
            </div>
            <div class="form-group" style="flex: 1;">
                <label for="state">State</label>
                <select id="state" name="state">
                    <option value="">Select...</option>
                    <?php foreach ($states_list as $st): ?>
                        <option value="<?php echo htmlspecialchars($st); ?>"><?php echo htmlspecialchars($st); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="phone">Phone Number</label>
            <input type="tel" id="phone" name="phone" pattern="[\d\s\-\(\)\+]{10,20}" title="Enter a valid 10-digit phone number (e.g. 555-123-4567)" placeholder="e.g. (765) 555-0101">
        </div>
        <div class="form-group">
            <label for="email">Email Address (Optional)</label>
            <input type="email" id="email" name="email" placeholder="e.g. hello@yourbusiness.com">
            <small>Customers can contact you directly. Will be shown on your profile.</small>
        </div>

        <div class="form-group">
            <label for="logo_file">Business Logo / Image (Optional)</label>
            <input type="file" id="logo_file" name="logo_file" accept=".jpg,.jpeg,.png,.webp">
            <small>Upload a high-quality logo (Max 2MB). JPG, PNG, or WEBP allowed.</small>
        </div>

        <div class="form-group">
            <label for="website">Business Website (Optional)</label>
            <input type="url" id="website" name="website" placeholder="https://yourbusiness.com">
            <small>Your website URL will appear as a clickable button on your profile.</small>
        </div>

        <div class="form-group">
            <label>Business Hours</label>
            <small style="margin-top: 0; margin-bottom: 0.5rem; display: block;">Let customers know when you are open.</small>
            <div class="schedule-builder">
                <?php
                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                foreach ($days as $day):
                ?>
                    <div class="schedule-row">
                        <div class="schedule-day"><?php echo $day; ?></div>
                        <div class="schedule-inputs">
                            <label style="margin: 0; font-weight: normal; font-size: 0.9rem;">
                                <input type="checkbox" name="hours[<?php echo $day; ?>][is_closed]" value="1" onchange="toggleHours(this)"> Closed
                            </label>
                            <input type="time" name="hours[<?php echo $day; ?>][open]" class="time-input">
                            <span>to</span>
                            <input type="time" name="hours[<?php echo $day; ?>][close]" class="time-input">
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

            // Execute reCAPTCHA before submitting the form
            document.getElementById('businessForm').addEventListener('submit', function(e) {
                e.preventDefault();
                grecaptcha.ready(function() {
                    grecaptcha.execute('<?php echo htmlspecialchars($env_vars['RECAPTCHA_SITE_KEY'] ?? ''); ?>', {
                        action: 'submit'
                    }).then(function(token) {
                        document.getElementById('g-recaptcha-response').value = token;
                        document.getElementById('businessForm').submit();
                    });
                });
            });
        </script>

        <div class="form-group">
            <label for="description">Business Description *</label>
            <textarea id="description" name="description" rows="4" required minlength="30" placeholder="Tell us about your business and your mission..."></textarea>
        </div>
        <div class="form-group">
            <label for="founder_story">Your Comeback Story (Optional)</label>
            <textarea id="founder_story" name="founder_story" rows="3" placeholder="Share a brief snippet of your recovery journey to connect with customers..."></textarea>
            <small>This will be featured as a special quote on your profile page.</small>
        </div>
        <button type="submit" class="btn-submit">Submit Application</button>
    </form>
</main>

<?php include 'footer.php'; ?>