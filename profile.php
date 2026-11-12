<?php
// profile.php
require_once 'config.php'; // starts the session
require_once 'jobs-lib.php';
require_once 'cards-lib.php';

// 1. Grab either the slug (from SEO URL) or the ID
$slug = isset($_GET['slug']) ? $_GET['slug'] : '';
$business_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// 2. Query the database based on which one we have
if (!empty($slug)) {
    $stmt = $conn->prepare("SELECT * FROM businesses WHERE slug = ? AND status = 'approved'");
    $stmt->bind_param("s", $slug);
} else {
    $stmt = $conn->prepare("SELECT * FROM businesses WHERE id = ? AND status = 'approved'");
    $stmt->bind_param("i", $business_id);
}

$stmt->execute();
$result = $stmt->get_result();
$business = $result->fetch_assoc();
$stmt->close();

// 3. Ensure we have the $business_id set properly so the Review Form works!
if ($business) {
    $business_id = $business['id'];
}

// Initialize message variable to prevent undefined warnings
$review_message = '';

// --- SUBMIT NEW REVIEW LOGIC ---
if ($business && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    csrf_verify();
    $reviewer_name = trim($_POST['reviewer_name'] ?? '');
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if (empty($reviewer_name) || $rating < 1 || $rating > 5) {
        $review_message = "<p class='field-error'>Add your name and pick a star rating.</p>";
    } else {
        $stmt = $conn->prepare("INSERT INTO reviews (business_id, reviewer_name, rating, comment) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isis", $business_id, $reviewer_name, $rating, $comment);
            if ($stmt->execute()) {
                $review_message = "<p class='form-note form-note--success'>Thanks. Your review is in.</p>";
            } else {
                $review_message = "<p class='field-error'>That didn't go through. Try again.</p>";
            }
            $stmt->close();
        }
    }
}

// --- FETCH AGGREGATE STATS & FEED ---
$avg_rating = 0;
$review_count = 0;
$reviews = [];

if ($business) {
    // Get average and count
    $stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM reviews WHERE business_id = ?");
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $stat_result = $stmt->get_result()->fetch_assoc();
    if ($stat_result && $stat_result['review_count'] > 0) {
        $avg_rating = round($stat_result['avg_rating'], 1);
        $review_count = $stat_result['review_count'];
    }
    $stmt->close();

    // Get the reviews feed
    $stmt = $conn->prepare("SELECT * FROM reviews WHERE business_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->bind_param("i", $business_id);
    $stmt->execute();
    $reviews_result = $stmt->get_result();
    while ($r = $reviews_result->fetch_assoc()) {
        $reviews[] = $r;
    }
    $stmt->close();
}

// --- PARSE BUSINESS HOURS ---
$is_open_now = false;
$parsed_hours = [];

if ($business && !empty($business['hours'])) {
    // Added ?? [] fallback for safe decoding
    $parsed_hours = json_decode($business['hours'], true) ?? [];
    if (!empty($parsed_hours) && is_array($parsed_hours)) {
        $current_day = date('l'); // e.g., 'Monday'
        $current_time = time();

        if (isset($parsed_hours[$current_day]) && !$parsed_hours[$current_day]['is_closed']) {
            // Convert open/close times (e.g., '09:00') into UNIX timestamps for today
            $open_ts = strtotime('today ' . $parsed_hours[$current_day]['open']);
            $close_ts = strtotime('today ' . $parsed_hours[$current_day]['close']);

            if ($current_time >= $open_ts && $current_time <= $close_ts) {
                $is_open_now = true;
            }
        }
    }
}

// Initialize default Schema JSON empty string so header.php doesn't crash if business is missing
$schema_json = '';

if ($business) {
    // Safely parse category JSON with fallback
    $cat_list = json_decode($business['category'], true) ?? [];
    if (empty($cat_list) && !empty($business['category'])) {
        $cat_list = [$business['category']]; // Fallback for old simple strings
    }

    // Set dynamic SEO Title and Description
    $page_title       = $business['name'];
    $cat_string       = isset($cat_list[0]) ? $cat_list[0] : 'Business';
    $page_description = $cat_string . ': ' . mb_strimwidth(strip_tags($business['description']), 0, 155, '...');

    // Set dynamic SEO Image to the Business Logo
    if (!empty($business['logo'])) {
        $seo_image = "https://www.recoverybusinesshub.com/" . ltrim($business['logo'], '/');
    }

    // --- BUILD SEO SCHEMA MARKUP ---
    $local_business_schema = [
        "@context" => "https://schema.org",
        "@type" => "LocalBusiness",
        "name" => $business['name'],
        "image" => !empty($business['logo']) ? "https://www.recoverybusinesshub.com/" . ltrim($business['logo'], '/') : "https://www.recoverybusinesshub.com/logo-keytag.png",
        "description" => strip_tags($business['description']),
        "telephone" => $business['phone'],
        "url" => !empty($business['website']) ? $business['website'] : "https://www.recoverybusinesshub.com/business/" . $business['slug'] . "/"
    ];

    // Add Address to Schema if available
    if (!empty($business['address']) || !empty($business['city']) || !empty($business['state'])) {
        $local_business_schema["address"] = [
            "@type" => "PostalAddress",
            "streetAddress" => $business['address'] ?? "",
            "addressLocality" => $business['city'] ?? "",
            "addressRegion" => $business['state'] ?? "",
            "addressCountry" => "US"
        ];
    }

    // Add Aggregate Rating to Schema if reviews exist
    if ($review_count > 0) {
        $local_business_schema["aggregateRating"] = [
            "@type" => "AggregateRating",
            "ratingValue" => $avg_rating,
            "reviewCount" => $review_count
        ];
    }

    // Build the BreadcrumbList Schema
    $breadcrumb_schema = [
        "@context" => "https://schema.org",
        "@type" => "BreadcrumbList",
        "itemListElement" => [
            [
                "@type" => "ListItem",
                "position" => 1,
                "name" => "Home",
                "item" => "https://www.recoverybusinesshub.com/"
            ],
            [
                "@type" => "ListItem",
                "position" => 2,
                "name" => $business['name'],
                "item" => "https://www.recoverybusinesshub.com/business/" . $business['slug'] . "/"
            ]
        ]
    ];

    // Combine both schemas into an array and convert to JSON string for the header
    $schema_json = json_encode([$local_business_schema, $breadcrumb_schema], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} else {
    $page_title       = 'Business not found';
    $page_description = 'This listing may be pending review or has been taken down.';
}

// Open jobs for this business (shown on the profile)
$open_jobs = $business ? rbh_business_jobs($conn, (int)$business_id) : [];

// -------------------------------
include 'header.php';
?>

<main class="profile-wrap">

    <?php if ($business): ?>
        <a href="/businesses" class="back-link">&larr; All businesses</a>

        <article class="profile-card">
            <div class="profile-stub" aria-hidden="true"><span class="tag-card__hole"></span></div>

            <div class="profile-main">
                <div class="profile-header-wrap">
                    <div class="profile-img-col">
                        <?php if (!empty($business['logo'])): ?>
                            <?php $logo_src = '/' . ltrim($business['logo'], '/'); ?>
                            <img src="<?php echo htmlspecialchars($logo_src); ?>" alt="Logo for <?php echo htmlspecialchars($business['name']); ?>" loading="lazy">
                        <?php else: ?>
                            <?php
                            $pw = preg_split('/\s+/', trim($business['name']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                            $pi = '';
                            foreach ($pw as $w) { $pi .= mb_substr($w, 0, 1); }
                            ?>
                            <div class="logo-placeholder" aria-hidden="true"><?php echo htmlspecialchars(mb_substr($pi, 0, 2)); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="profile-intro-col">
                        <div class="profile-badges">
                            <?php if (!empty($open_jobs)): ?>
                                <span class="flag-hiring">Hiring</span>
                            <?php endif; ?>

                            <?php if (!empty($business['is_verified'])): ?>
                                <span class="badge-verified">Verified</span>
                            <?php endif; ?>

                            <?php if (!empty($parsed_hours)): ?>
                                <?php if ($is_open_now): ?>
                                    <span class="badge-open">Open now</span>
                                <?php else: ?>
                                    <span class="badge-closed">Closed</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <h1><?php echo htmlspecialchars($business['name']); ?></h1>

                        <?php if ($review_count > 0): ?>
                            <div class="rating-line">
                                <span class="stars" aria-hidden="true"><?php echo str_repeat('★', round($avg_rating)) . str_repeat('☆', 5 - round($avg_rating)); ?></span>
                                <strong><?php echo $avg_rating; ?></strong>
                                <span class="tag-card__meta">(<?php echo $review_count; ?> review<?php echo $review_count != 1 ? 's' : ''; ?>)</span>
                            </div>
                        <?php endif; ?>

                        <div class="chip-row profile-categories">
                            <?php foreach ($cat_list as $c): ?>
                                <span class="category-tag"><?php echo htmlspecialchars($c); ?></span>
                            <?php endforeach; ?>
                        </div>

                        <div class="profile-actions">
                            <?php if (!empty($business['phone'])): ?>
                                <a href="tel:<?php echo htmlspecialchars($business['phone']); ?>"
                                    aria-label="Call <?php echo htmlspecialchars($business['name']); ?>"
                                    class="btn-primary">Call now</a>
                            <?php endif; ?>

                            <?php if (!empty($business['website'])): ?>
                                <a href="<?php echo htmlspecialchars($business['website']); ?>" target="_blank" rel="noopener noreferrer" class="btn-secondary">Visit website</a>
                            <?php endif; ?>
                        </div>

                        <?php
                        $share_url   = urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
                        $share_title = urlencode($business['name'] . ' | Recovery Business Hub');
                        ?>
                        <div class="share-row">
                            <p class="share-row__label">Pass it on</p>
                            <div class="share-buttons">
                                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $share_url; ?>" target="_blank" rel="noopener noreferrer" class="share-btn">Facebook</a>
                                <a href="https://twitter.com/intent/tweet?url=<?php echo $share_url; ?>&text=<?php echo $share_title; ?>" target="_blank" rel="noopener noreferrer" class="share-btn">X</a>
                                <button type="button" onclick="navigator.clipboard.writeText(window.location.href).then(()=>{ this.classList.add('copied-success'); this.textContent='Copied'; setTimeout(()=>{ this.classList.remove('copied-success'); this.textContent='Copy link'; }, 2000); })" class="share-btn share-btn--copy">Copy link</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="profile-body-wrap">

                    <div class="about-section">
                        <?php if (!empty($business['founder_story'])): ?>
                            <div class="founder-story-block">
                                <h4>Their story</h4>
                                <p><?php echo nl2br(htmlspecialchars($business['founder_story'])); ?></p>
                            </div>
                        <?php endif; ?>

                        <h3>About</h3>
                        <div class="profile-description"><?php echo htmlspecialchars($business['description']); ?></div>

                        <?php if (!empty($open_jobs)): ?>
                            <div class="profile-jobs" id="jobs">
                                <h3>Open jobs</h3>
                                <div class="card-grid">
                                    <?php foreach ($open_jobs as $oj): ?>
                                        <?php echo rbh_job_card($oj); ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="reviews-section" id="reviews">
                            <h3>Reviews</h3>

                            <?php if (empty($reviews)): ?>
                                <p class="tag-card__meta">No reviews yet. Been a customer? Leave the first one.</p>
                            <?php else: ?>
                                <?php foreach ($reviews as $rev): ?>
                                    <div class="review-card">
                                        <div class="review-header">
                                            <div class="reviewer-name"><?php echo htmlspecialchars($rev['reviewer_name']); ?></div>
                                            <div class="review-date"><?php echo date('M j, Y', strtotime($rev['created_at'])); ?></div>
                                        </div>
                                        <div class="stars" aria-label="<?php echo (int)$rev['rating']; ?> out of 5 stars">
                                            <?php echo str_repeat('★', $rev['rating']) . str_repeat('☆', 5 - $rev['rating']); ?>
                                        </div>
                                        <?php if (!empty($rev['comment'])): ?>
                                            <p class="review-comment"><?php echo nl2br(htmlspecialchars($rev['comment'])); ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>

                            <div class="review-form-container">
                                <h3>Leave a review</h3>
                                <?php echo $review_message; ?>
                                <form method="POST" action="#reviews">
                                    <input type="hidden" name="submit_review" value="1">
                                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

                                    <div class="form-group">
                                        <label for="reviewer_name">Your name</label>
                                        <input type="text" id="reviewer_name" name="reviewer_name" required>
                                    </div>

                                    <div class="form-group">
                                        <fieldset>
                                            <legend>Rating</legend>
                                            <div class="star-rating">
                                                <input type="radio" id="star5" name="rating" value="5" required><label for="star5" title="5 stars">★</label>
                                                <input type="radio" id="star4" name="rating" value="4"><label for="star4" title="4 stars">★</label>
                                                <input type="radio" id="star3" name="rating" value="3"><label for="star3" title="3 stars">★</label>
                                                <input type="radio" id="star2" name="rating" value="2"><label for="star2" title="2 stars">★</label>
                                                <input type="radio" id="star1" name="rating" value="1"><label for="star1" title="1 star">★</label>
                                            </div>
                                        </fieldset>
                                    </div>

                                    <div class="form-group">
                                        <div class="label-row">
                                            <label for="review-comment">Comment (optional)</label>
                                            <span id="char-count" class="char-count">0 / 500</span>
                                        </div>
                                        <textarea name="comment" id="review-comment" rows="4" maxlength="500"></textarea>
                                    </div>

                                    <script>
                                        const textarea = document.getElementById('review-comment');
                                        const charCount = document.getElementById('char-count');
                                        function updateCharCount() {
                                            charCount.textContent = textarea.value.length + ' / 500';
                                        }
                                        updateCharCount();
                                        textarea.addEventListener('input', updateCharCount);
                                    </script>

                                    <button type="submit" class="btn-secondary">Submit review</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="contact-sidebar">
                        <div class="profile-contact-box">
                            <h4>Location and contact</h4>
                            <p><strong>Address</strong><br><?php
                                                            $loc = [];
                                                            if (!empty($business['address'])) $loc[] = $business['address'];
                                                            if (!empty($business['city'])) $loc[] = $business['city'];
                                                            if (!empty($business['state'])) $loc[] = $business['state'];
                                                            echo !empty($loc) ? htmlspecialchars(implode(', ', $loc)) : 'Not listed';
                                                            ?></p>
                            <?php if (!empty($business['phone'])): ?>
                                <p><strong>Phone</strong><br><?php echo htmlspecialchars($business['phone']); ?></p>
                            <?php endif; ?>

                            <?php if (!empty($parsed_hours)): ?>
                                <hr>
                                <h4>Hours</h4>
                                <ul class="hours-list">
                                    <?php
                                    $days_order = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                                    $current_day = date('l');
                                    foreach ($days_order as $day):
                                        if (!isset($parsed_hours[$day])) continue;

                                        $data = $parsed_hours[$day];
                                        $is_today = ($day === $current_day);

                                        if ($data['is_closed']) {
                                            $display_time = 'Closed';
                                        } else {
                                            $open = date("g:i A", strtotime($data['open']));
                                            $close = date("g:i A", strtotime($data['close']));
                                            $display_time = "$open to $close";
                                        }
                                    ?>
                                        <li class="<?php echo $is_today ? 'hours-today' : ''; ?>">
                                            <span><?php echo $day; ?></span>
                                            <span class="<?php echo $data['is_closed'] ? 'hours-closed' : ''; ?>"><?php echo $display_time; ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
        </article>

    <?php else: ?>
        <div class="empty-state empty-state--center">
            <?php echo rbh_blank_tag(); ?>
            <h1>Business not found</h1>
            <p>This listing may be pending review, or it's been taken down.</p>
            <a href="/businesses" class="btn-secondary">See all businesses</a>
        </div>
    <?php endif; ?>

</main>

<?php include 'footer.php'; ?>
