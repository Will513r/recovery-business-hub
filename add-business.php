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
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL) ? trim($_POST['email']) : '';
    $website = filter_var($_POST['website'] ?? '', FILTER_VALIDATE_URL) ? $_POST['website'] : '';

    $description = trim($_POST['description'] ?? '');
    $founder_story = trim($_POST['founder_story'] ?? '');

    // Owner details (2026-11): private, never shown on the site.
    $owner_name = trim(preg_replace('/\s+/', ' ', (string)($_POST['owner_name'] ?? '')));
    $owner_confirm = ($_POST['owner_confirm'] ?? '') === '1';

    // Business Hours Processing
    $hours_json = parse_hours_post();

    // Everything is free now. No plans.
    $tier = 'free';
    $status = 'pending';

    // SERVER-SIDE VALIDATION: check every required field, show all problems at once
    $name_len = mb_strlen(trim($name));
    $description_len = mb_strlen($description);
    if ($name_len < 3 || $name_len > 100) {
        $field_errors['name'] = "Business name needs to be 3 to 100 characters.";
    }
    if ($description_len < 30 || $description_len > 3000) {
        $field_errors['description'] = "Tell us a little more. At least 30 characters.";
    }
    if (mb_strlen($owner_name) < 2 || mb_strlen($owner_name) > 100) {
        $field_errors['owner_name'] = "Add your name.";
    }
    if ($email === '') {
        $field_errors['email'] = "Add an email we can reach you at.";
    }
    if (!$owner_confirm) {
        $field_errors['owner_confirm'] = "Tick this to send your listing. The list is only for owners in recovery.";
    }

    if (!$field_errors) {
        // Logo only uploads once everything else passed, so a failed try leaves no stray file
        $uploaded = handle_logo_upload();
        $logo_path = $uploaded !== false ? $uploaded : '';

        if (!in_array($state, $states_list, true)) {
            $state = '';
        }
        $phone = substr($phone, 0, 30);
        $city = substr($city, 0, 100);
        $address = substr($address, 0, 100);

        $stmt = $conn->prepare("INSERT INTO businesses (name, slug, category, description, tier, status, logo, address, city, state, phone, email, owner_name, owner_confirmed_at, website, founder_story, hours) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)");

        if ($stmt) {
            $stmt->bind_param("ssssssssssssssss", $name, $slug, $category_json, $description, $tier, $status, $logo_path, $address, $city, $state, $phone, $email, $owner_name, $website, $founder_story, $hours_json);

            if ($stmt->execute()) {
                // SECURITY: Strip newlines to prevent Email Header Injection
                $safe_name_for_email = str_replace(["\r", "\n", "%0a", "%0d"], '', $name);
                $safe_owner_for_email = str_replace(["\r", "\n", "%0a", "%0d"], '', $owner_name);

                // Send email notification through authenticated SMTP (raw mail()
                // silently fails on Hostinger). Failure is logged, not fatal:
                // the application is already saved and visible in admin.php.
                $to = 'recoverybusinesshub@gmail.com';
                $subject = 'New Business Application: ' . $safe_name_for_email;
                $email_body = "A new business applied.\n\nBusiness: $safe_name_for_email\nOwner: $safe_owner_for_email\nOwner ticked \"I own this business and I'm in recovery\": yes\n\nLogin to approve: https://www.recoverybusinesshub.com/admin.php";
                rbh_send_email($to, $subject, $email_body, $email);

                $submitted = true;
                $message = "<p class='form-note form-note--success'>Got it. We read every listing before it goes up, and we'll email you when yours is live.</p>";
            } else {
                $message = "<p class='form-note form-note--error'>That didn't save. Try again.</p>";
            }
            $stmt->close();
        } else {
            $message = "<p class='form-note form-note--error'>Something broke on our end. Try again in a minute.</p>";
        }
    } else {
        $message = "<p class='form-note form-note--error'>A few things need fixing. They're marked below.</p>";
    }
}

// Refill the form after an error so nobody types it twice
$old = function (string $key): string {
    return htmlspecialchars(trim((string)($_POST[$key] ?? '')), ENT_QUOTES);
};
$old_cats = (isset($_POST['categories']) && is_array($_POST['categories'])) ? $_POST['categories'] : [];
$more_open = false;
foreach (['address', 'city', 'state', 'phone', 'website', 'founder_story'] as $k) {
    if (trim((string)($_POST[$k] ?? '')) !== '') {
        $more_open = true;
    }
}
if ($old_cats) {
    $more_open = true;
}

$page_title       = 'List your business';
$page_description = 'List your recovery-owned business on Recovery Business Hub. It is free, and you choose how much of your story to share.';
include 'header.php';
?>

<script src="https://www.google.com/recaptcha/api.js?render=<?php echo htmlspecialchars($env_vars['RECAPTCHA_SITE_KEY'] ?? ''); ?>"></script>

<main class="form-container">
    <h1>List your business</h1>
    <p>Free, and only for businesses owned by someone in recovery. The first part takes about a minute. Your name and email stay with us. Only your business details show on the site, and only what you give us.</p>

    <?php echo $message; ?>

    <?php if (!$submitted): ?>
    <form action="/add-business" method="POST" class="business-form" id="businessForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

        <input type="hidden" id="g-recaptcha-response" name="g-recaptcha-response">

        <div class="form-group<?php echo isset($field_errors['name']) ? ' has-error' : ''; ?>">
            <label for="name">Business name *</label>
            <input type="text" id="name" name="name" required minlength="3" maxlength="100" placeholder="e.g. Second Chance Cafe" value="<?php echo $old('name'); ?>">
            <?php if (isset($field_errors['name'])): ?><span class="field-error"><?php echo htmlspecialchars($field_errors['name']); ?></span><?php endif; ?>
        </div>

        <div class="form-group<?php echo isset($field_errors['description']) ? ' has-error' : ''; ?>">
            <label for="description">What you do *</label>
            <textarea id="description" name="description" rows="3" required minlength="30" maxlength="3000" placeholder="What you do, who you do it for, and where. A sentence or two is plenty."><?php echo $old('description'); ?></textarea>
            <?php if (isset($field_errors['description'])): ?><span class="field-error"><?php echo htmlspecialchars($field_errors['description']); ?></span><?php endif; ?>
        </div>

        <div class="form-group<?php echo isset($field_errors['owner_name']) ? ' has-error' : ''; ?>">
            <label for="owner_name">Your name *</label>
            <input type="text" id="owner_name" name="owner_name" required minlength="2" maxlength="100" autocomplete="name" placeholder="e.g. Jamie Carter" value="<?php echo $old('owner_name'); ?>">
            <small>Private. It never shows on your listing.</small>
            <?php if (isset($field_errors['owner_name'])): ?><span class="field-error"><?php echo htmlspecialchars($field_errors['owner_name']); ?></span><?php endif; ?>
        </div>

        <div class="form-group<?php echo isset($field_errors['email']) ? ' has-error' : ''; ?>">
            <label for="email">Your email *</label>
            <input type="email" id="email" name="email" required autocomplete="email" placeholder="e.g. hello@yourbusiness.com" value="<?php echo $old('email'); ?>">
            <small>Private. We email you when your listing is live, and it's how you get a link to edit it later.</small>
            <?php if (isset($field_errors['email'])): ?><span class="field-error"><?php echo htmlspecialchars($field_errors['email']); ?></span><?php endif; ?>
        </div>

        <div class="form-group form-confirm<?php echo isset($field_errors['owner_confirm']) ? ' has-error' : ''; ?>">
            <label class="form-confirm__label">
                <input type="checkbox" name="owner_confirm" value="1" required<?php echo ($_POST['owner_confirm'] ?? '') === '1' ? ' checked' : ''; ?>>
                <span>I own this business and I'm in recovery. *</span>
            </label>
            <small>Private. Nothing about your recovery shows unless you write it in your story below.</small>
            <?php if (isset($field_errors['owner_confirm'])): ?><span class="field-error"><?php echo htmlspecialchars($field_errors['owner_confirm']); ?></span><?php endif; ?>
        </div>

        <details class="form-more"<?php echo $more_open ? ' open' : ''; ?>>
            <summary>Want a fuller listing? Add more (optional)</summary>
            <p class="form-more__note">Skip any of it. You can add it later from the edit link we email you.</p>

            <div class="form-group">
                <span class="form-label">Categories (pick all that apply)</span>
                <div class="category-checkbox-grid">
                    <?php foreach ($categories as $cat): ?>
                        <label class="category-checkbox-label">
                            <input type="checkbox" name="categories[]" value="<?php echo htmlspecialchars($cat); ?>"<?php echo in_array($cat, $old_cats, true) ? ' checked' : ''; ?>>
                            <span><?php echo htmlspecialchars($cat); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="city">City</label>
                    <input type="text" id="city" name="city" maxlength="100" placeholder="e.g. Cincinnati" value="<?php echo $old('city'); ?>">
                </div>
                <div class="form-group">
                    <label for="state">State</label>
                    <select id="state" name="state">
                        <option value="">Select</option>
                        <?php foreach ($states_list as $st): ?>
                            <option value="<?php echo htmlspecialchars($st); ?>"<?php echo ($_POST['state'] ?? '') === $st ? ' selected' : ''; ?>><?php echo htmlspecialchars($st); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="address">Street address</label>
                <input type="text" id="address" name="address" maxlength="100" placeholder="e.g. 123 Main St" value="<?php echo $old('address'); ?>">
                <small>Working from home or on the road? Leave this off. City and state are enough.</small>
            </div>

            <div class="form-group">
                <label for="phone">Business phone</label>
                <input type="tel" id="phone" name="phone" maxlength="30" placeholder="e.g. (513) 555-0101" value="<?php echo $old('phone'); ?>">
                <small>It shows on your listing so customers can call.</small>
            </div>

            <div class="form-group">
                <label for="website">Website</label>
                <input type="url" id="website" name="website" placeholder="https://yourbusiness.com" value="<?php echo $old('website'); ?>">
                <small>It shows as a button on your listing.</small>
            </div>

            <div class="form-group">
                <label for="logo_file">Logo or photo</label>
                <input type="file" id="logo_file" name="logo_file" accept=".jpg,.jpeg,.png,.webp">
                <small>JPG, PNG or WEBP, up to 2MB.</small>
            </div>

            <div class="form-group">
                <span class="form-label">Hours</span>
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

            <div class="form-group">
                <label for="founder_story">Your story</label>
                <textarea id="founder_story" name="founder_story" rows="3" maxlength="5000" placeholder="Share as much or as little as you want. Leave it blank and nothing about your recovery shows."><?php echo $old('founder_story'); ?></textarea>
                <small>It shows on your listing as a short note. It's yours to share or skip.</small>
            </div>
        </details>

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

        <p class="form-more__note">We read every listing before it goes up and email you when yours is live.</p>
        <button type="submit" class="btn-submit">Send for review</button>
    </form>
    <?php endif; ?>
</main>

<?php include 'footer.php'; ?>
