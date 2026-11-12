<?php
// add-business.php
require_once 'config.php'; // starts the session

$message = "";
$submitted = false;
$field_errors = [];

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
            die("<p style='color:#B91C1C; text-align:center;'>Bot behavior detected. Application rejected.</p>");
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

    $description = trim($_POST['description'] ?? '');
    $founder_story = trim($_POST['founder_story'] ?? '');

    // Secure Image Upload Handling
    $uploaded = handle_logo_upload();
    $logo_path = $uploaded !== false ? $uploaded : '';

    // Business Hours Processing
    $hours_json = parse_hours_post();

    // Everything is free now. No plans.
    $tier = 'free';
    $status = 'pending';

    // SERVER-SIDE VALIDATION: reject bad input and enforce field length limits
    $name_len = strlen(trim($name));
    $description_len = strlen(trim($description));
    if ($name_len < 3 || $name_len > 100) {
        $field_errors['name'] = "Business name needs to be 3 to 100 characters.";
    } elseif ($description_len < 30 || $description_len > 3000) {
        $field_errors['description'] = "The description needs to be 30 to 3000 characters.";
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

                // Send email notification through authenticated SMTP (raw mail()
                // silently fails on Hostinger). Failure is logged, not fatal:
                // the application is already saved and visible in admin.php.
                $to = 'recoverybusinesshub@gmail.com';
                $subject = 'New Business Application: ' . $safe_name_for_email;
                $email_body = "A new business applied.\n\nName: $safe_name_for_email\n\nLogin to approve: https://www.recoverybusinesshub.com/admin.php";
                rbh_send_email($to, $subject, $email_body);

                $submitted = true;
                $message = "<p class='form-note form-note--success'>Got it. We read every listing before it goes up.</p>";
            } else {
                $message = "<p class='form-note form-note--error'>That didn't save. Try again.</p>";
            }
            $stmt->close();
        } else {
            $message = "<p class='form-note form-note--error'>Something broke on our end. Try again in a minute.</p>";
        }
    }
}

$page_title       = 'List your business';
$page_description = 'List your recovery-owned business on Recovery Business Hub. It is free, and you choose how much of your story to share.';
include 'header.php';
?>

<script src="https://www.google.com/recaptcha/api.js?render=<?php echo htmlspecialchars($env_vars['RECAPTCHA_SITE_KEY'] ?? ''); ?>"></script>

<main class="form-container">
    <h1>List your business</h1>
    <p>Free, and only for businesses owned by someone in recovery. You choose how much of your story to share, and we only show what you give us. We read every listing before it goes up.</p>

    <?php echo $message; ?>

    <?php if (!$submitted): ?>
    <form action="/add-business" method="POST" class="business-form" id="businessForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

        <input type="hidden" id="g-recaptcha-response" name="g-recaptcha-response">

        <div class="form-group<?php echo isset($field_errors['name']) ? ' has-error' : ''; ?>">
            <label for="name">Business name *</label>
            <input type="text" id="name" name="name" required minlength="3" placeholder="e.g. Second Chance Cafe">
            <?php if (isset($field_errors['name'])): ?><span class="field-error"><?php echo htmlspecialchars($field_errors['name']); ?></span><?php endif; ?>
        </div>

        <div class="form-group">
            <span class="form-label">Categories * (pick all that apply)</span>
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
            <label for="address">Street address (optional)</label>
            <input type="text" id="address" name="address" placeholder="e.g. 123 Main St">
            <small>Working from home or on the road? Leave this off. City and state are enough.</small>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="city">City</label>
                <input type="text" id="city" name="city" placeholder="e.g. Cincinnati">
            </div>
            <div class="form-group">
                <label for="state">State</label>
                <select id="state" name="state">
                    <option value="">Select</option>
                    <?php foreach ($states_list as $st): ?>
                        <option value="<?php echo htmlspecialchars($st); ?>"><?php echo htmlspecialchars($st); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="phone">Phone (optional)</label>
            <input type="tel" id="phone" name="phone" pattern="[\d\s\-\(\)\+]{10,20}" title="Enter a 10-digit phone number, like 555-123-4567" placeholder="e.g. (513) 555-0101">
        </div>
        <div class="form-group">
            <label for="email">Email (optional)</label>
            <input type="email" id="email" name="email" placeholder="e.g. hello@yourbusiness.com">
            <small>It shows on your profile. It's also how you get a link to edit your listing later, so leave it off and you can't edit it yourself.</small>
        </div>

        <div class="form-group">
            <label for="logo_file">Logo or photo (optional)</label>
            <input type="file" id="logo_file" name="logo_file" accept=".jpg,.jpeg,.png,.webp">
            <small>JPG, PNG or WEBP, up to 2MB.</small>
        </div>

        <div class="form-group">
            <label for="website">Website (optional)</label>
            <input type="url" id="website" name="website" placeholder="https://yourbusiness.com">
            <small>It shows as a button on your profile.</small>
        </div>

        <div class="form-group">
            <span class="form-label">Hours (optional)</span>
            <small>Tick Closed for any day you're shut.</small>
            <div class="schedule-builder">
                <?php
                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                foreach ($days as $day):
                ?>
                    <div class="schedule-row">
                        <div class="schedule-day"><?php echo $day; ?></div>
                        <div class="schedule-inputs">
                            <label>
                                <input type="checkbox" name="hours[<?php echo $day; ?>][is_closed]" value="1" onchange="toggleHours(this)"> Closed
                            </label>
                            <input type="time" name="hours[<?php echo $day; ?>][open]" class="time-input" aria-label="<?php echo $day; ?> opens">
                            <span>to</span>
                            <input type="time" name="hours[<?php echo $day; ?>][close]" class="time-input" aria-label="<?php echo $day; ?> closes">
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

        <div class="form-group<?php echo isset($field_errors['description']) ? ' has-error' : ''; ?>">
            <label for="description">About the business *</label>
            <textarea id="description" name="description" rows="4" required minlength="30" placeholder="What you do, who you do it for, and where."></textarea>
            <?php if (isset($field_errors['description'])): ?><span class="field-error"><?php echo htmlspecialchars($field_errors['description']); ?></span><?php endif; ?>
        </div>
        <div class="form-group">
            <label for="founder_story">Your story (optional)</label>
            <textarea id="founder_story" name="founder_story" rows="3" placeholder="Share as much or as little as you want. Leave it blank and nothing about your recovery shows."></textarea>
            <small>It shows on your profile as a short note. It's yours to share or skip.</small>
        </div>
        <button type="submit" class="btn-submit">Send for review</button>
    </form>
    <?php endif; ?>
</main>

<?php include 'footer.php'; ?>
