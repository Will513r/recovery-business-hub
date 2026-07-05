<?php
// profile.php
session_start();
require_once 'config.php';

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
        $review_message = "<p style='color: red; margin-bottom: 1rem;'>Please provide a name and a valid star rating.</p>";
    } else {
        $stmt = $conn->prepare("INSERT INTO reviews (business_id, reviewer_name, rating, comment) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isis", $business_id, $reviewer_name, $rating, $comment);
            if ($stmt->execute()) {
                $review_message = "<p style='color: var(--button-color); font-weight: bold; margin-bottom: 1rem;'>Thank you for your review!</p>";
            } else {
                $review_message = "<p style='color: red; margin-bottom: 1rem;'>Error submitting review.</p>";
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
    $page_description = $cat_string . ' — ' . mb_strimwidth(strip_tags($business['description']), 0, 155, '...');

    // Set dynamic SEO Image to the Business Logo
    if (!empty($business['logo'])) {
        $seo_image = "https://www.recoverybusinesshub.com/" . ltrim($business['logo'], '/');
    }

    // --- BUILD SEO SCHEMA MARKUP ---
    $local_business_schema = [
        "@context" => "https://schema.org",
        "@type" => "LocalBusiness",
        "name" => $business['name'],
        "image" => !empty($business['logo']) ? "https://www.recoverybusinesshub.com/" . ltrim($business['logo'], '/') : "https://www.recoverybusinesshub.com/Recovery_Business_Hub_Logo.png",
        "description" => strip_tags($business['description']),
        "telephone" => $business['phone'],
        "url" => !empty($business['website']) ? $business['website'] : "https://www.recoverybusinesshub.com/profile.php?id=" . $business_id
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
    $page_title       = 'Business Not Found';
    $page_description = 'This listing may be pending review or has been removed from the Recovery Business Hub directory.';
}

// -------------------------------
include 'header.php';
?>

<main class="directory-container" style="display: block; max-width: 900px; margin: 2rem auto; padding: 0 1rem;">

    <?php if ($business): ?>
        <div style="margin-bottom: 1.5rem;">
            <a href="/index.php" style="color: var(--button-color); text-decoration: none; font-weight: bold;">← Back to Directory</a>
        </div>

        <article class="profile-card">

            <div class="profile-header-wrap">
                <div class="profile-img-col">
                    <?php if (!empty($business['logo'])): ?>
                        <?php $logo_src = '/' . ltrim($business['logo'], '/'); ?>
                        <img src="<?php echo htmlspecialchars($logo_src); ?>" alt="Official business logo for <?php echo htmlspecialchars($business['name']); ?>" loading="lazy">
                    <?php else: ?>
                        <div class="logo-placeholder" style="width: 100%; height: 200px; font-size: 3rem;">Logo</div>
                    <?php endif; ?>
                </div>

                <div class="profile-intro-col">
                    <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 0.5rem;">
                        <?php if ($business['tier'] === 'premium'): ?>
                            <span class="badge badge-premium">Premium Partner</span>
                        <?php elseif ($business['tier'] === 'paid'): ?>
                            <span class="badge badge-paid">Featured</span>
                        <?php endif; ?>

                        <?php if (!empty($business['is_verified'])): ?>
                            <span class="badge-verified badge-verified--large">✔ Verified</span>
                        <?php endif; ?>

                        <?php if (!empty($parsed_hours)): ?>
                            <?php if ($is_open_now): ?>
                                <span class="badge-open">Open Now</span>
                            <?php else: ?>
                                <span class="badge-closed">Closed</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <h1><?php echo htmlspecialchars($business['name']); ?></h1>

                    <?php if ($review_count > 0): ?>
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.5rem;">
                            <span class="stars" style="margin: 0; font-size: 1.25rem;">
                                <?php echo str_repeat('★', round($avg_rating)) . str_repeat('☆', 5 - round($avg_rating)); ?>
                            </span>
                            <span style="color: var(--text-main); font-weight: 600; font-size: 1.1rem;"><?php echo $avg_rating; ?></span>
                            <span style="color: var(--text-muted); font-size: 0.95rem;">(<?php echo $review_count; ?> reviews)</span>
                        </div>
                    <?php endif; ?>

                    <div class="profile-categories" style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 1.5rem;">
                        <?php foreach ($cat_list as $c): ?>
                            <span style="background: rgba(14, 165, 233, 0.1); color: var(--accent-color); padding: 4px 10px; border-radius: 12px; font-size: 0.85rem; font-weight: 600; border: 1px solid rgba(14, 165, 233, 0.2);">
                                <?php echo htmlspecialchars($c); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>

                    <div class="profile-actions">
                        <a href="tel:<?php echo htmlspecialchars($business['phone']); ?>"
                            aria-label="Call <?php echo htmlspecialchars($business['name']); ?>"
                            class="btn-primary" style="background-color: var(--button-color);">Call Now</a>

                        <?php if (!empty($business['website'])): ?>
                            <a href="<?php echo htmlspecialchars($business['website']); ?>" target="_blank" rel="noopener noreferrer" class="btn-secondary">Visit Website</a>
                        <?php endif; ?>
                    </div>

                    <?php
                    $share_url   = urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
                    $share_title = urlencode($business['name'] . ' — Recovery Business Hub');
                    ?>
                    <div class="share-row">
                        <p class="share-row__label">Share this business</p>
                        <div class="share-buttons">
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $share_url; ?>" target="_blank" rel="noopener noreferrer" class="share-btn share-btn--facebook">📘 Facebook</a>
                            <a href="https://twitter.com/intent/tweet?url=<?php echo $share_url; ?>&text=<?php echo $share_title; ?>" target="_blank" rel="noopener noreferrer" class="share-btn share-btn--x">𝕏 Share</a>
                            <button onclick="navigator.clipboard.writeText(window.location.href).then(()=>{ this.classList.add('copied-success'); this.textContent='✅ Copied!'; setTimeout(()=>{ this.classList.remove('copied-success'); this.textContent='🔗 Copy Link'; }, 2000); })" class="share-btn share-btn--copy">🔗 Copy Link</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="profile-body-wrap">

                <div class="about-section">
                    <?php if (!empty($business['founder_story'])): ?>
                        <div class="founder-story-block">
                            <span class="quote-mark">"</span>
                            <h4 style="color: var(--primary-color); margin-bottom: 0.5rem; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 1px;">The Comeback Story</h4>
                            <p><?php echo nl2br(htmlspecialchars($business['founder_story'])); ?></p>
                        </div>
                    <?php endif; ?>
                    <h3 style="color: var(--primary-color); margin-bottom: 1rem; border-bottom: 2px solid var(--accent-color); display: inline-block;">About Our Business</h3>
                    <div style="line-height: 1.8; color: var(--text-main); font-size: 1.1rem; white-space: pre-line;">
                        <?php echo htmlspecialchars($business['description']); ?>
                    </div>

                    <div class="reviews-section" id="reviews">
                        <h3 style="color: var(--primary-color); margin-bottom: 2rem;">Customer Reviews</h3>

                        <?php if (empty($reviews)): ?>
                            <p style="color: var(--text-muted); margin-bottom: 2rem;">No reviews yet. Be the first to share your experience!</p>
                        <?php else: ?>
                            <?php foreach ($reviews as $rev): ?>
                                <div class="review-card">
                                    <div class="review-header">
                                        <div class="reviewer-name"><?php echo htmlspecialchars($rev['reviewer_name']); ?></div>
                                        <div class="review-date"><?php echo date('M j, Y', strtotime($rev['created_at'])); ?></div>
                                    </div>
                                    <div class="stars">
                                        <?php echo str_repeat('★', $rev['rating']) . str_repeat('☆', 5 - $rev['rating']); ?>
                                    </div>
                                    <?php if (!empty($rev['comment'])): ?>
                                        <p class="review-comment"><?php echo nl2br(htmlspecialchars($rev['comment'])); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <div class="review-form-container">
                            <h3>Leave a Review</h3>
                            <?php echo $review_message; ?>
                            <form method="POST" action="#reviews" style="display: flex; flex-direction: column; gap: 1rem;">
                                <input type="hidden" name="submit_review" value="1">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

                                <div>
                                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Your Name *</label>
                                    <input type="text" name="reviewer_name" required style="width: 100%; padding: 0.8rem; border-radius: 8px; border: 1px solid #CBD5E1;">
                                </div>

                                <div>
                                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Rating *</label>
                                    <fieldset style="border: none; padding: 0; margin: 0;">
                                        <legend style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Rating *</legend>
                                        <div class="star-rating">
                                            <input type="radio" id="star5" name="rating" value="5" required><label for="star5" title="5 stars">★</label>
                                            <input type="radio" id="star4" name="rating" value="4"><label for="star4" title="4 stars">★</label>
                                            <input type="radio" id="star3" name="rating" value="3"><label for="star3" title="3 stars">★</label>
                                            <input type="radio" id="star2" name="rating" value="2"><label for="star2" title="2 stars">★</label>
                                            <input type="radio" id="star1" name="rating" value="1"><label for="star1" title="1 star">★</label>
                                        </div>
                                    </fieldset>
                                </div>

                                <div>
                                    <div style="display: flex; justify-content: space-between; align-items: baseline;">
                                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Comment (Optional)</label>
                                        <span id="char-count" style="font-size: 0.8rem; color: var(--text-muted);">0 / 500</span>
                                    </div>
                                    <textarea name="comment" id="review-comment" rows="4" maxlength="500" style="width: 100%; padding: 0.8rem; border-radius: 8px; border: 1px solid var(--border-color);"></textarea>
                                </div>

                                <script>
                                    const textarea = document.getElementById('review-comment');
                                    const charCount = document.getElementById('char-count');
                                    textarea.addEventListener('input', function() {
                                        charCount.textContent = this.value.length + ' / 500';
                                    });
                                </script>

                                <button type="submit" class="btn-primary" style="margin-top: 0.5rem;">Submit Review</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="contact-sidebar">
                    <div class="profile-contact-box">
                        <h4>Location & Contact</h4>
                        <p><strong>📍 Address:</strong><br><?php
                                                            $loc = [];
                                                            if (!empty($business['address'])) $loc[] = $business['address'];
                                                            if (!empty($business['city'])) $loc[] = $business['city'];
                                                            if (!empty($business['state'])) $loc[] = $business['state'];
                                                            echo !empty($loc) ? htmlspecialchars(implode(', ', $loc)) : 'None provided';
                                                            ?></p>
                        <p><strong>📞 Phone:</strong><br><?php echo htmlspecialchars($business['phone']); ?></p>
                        <?php if (!empty($business['email'])): ?>
                            <p><strong>✉️ Email:</strong><br><a href="mailto:<?php echo htmlspecialchars($business['email']); ?>" style="color: var(--button-color);"><?php echo htmlspecialchars($business['email']); ?></a></p>
                        <?php endif; ?>

                        <?php if (!empty($parsed_hours)): ?>
                            <hr style="margin: 1.5rem 0; border: 0; border-top: 1px solid #E2E8F0;">
                            <h4>Business Hours</h4>
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
                                        $display_time = "$open - $close";
                                    }
                                ?>
                                    <li class="<?php echo $is_today ? 'hours-today' : ''; ?>">
                                        <span><?php echo $day; ?></span>
                                        <span style="<?php echo $data['is_closed'] ? 'color: #EF4444; font-weight: 500;' : ''; ?>"><?php echo $display_time; ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <hr style="margin: 1.5rem 0; border: 0; border-top: 1px solid #E2E8F0;">
                        <p style="font-size: 0.9rem; color: var(--text-muted);">Support a recovery-owned business. Every purchase helps fuel a comeback.</p>
                    </div>

                    <?php
                    $mapQuery = [];
                    if (!empty($business['address'])) $mapQuery[] = $business['address'];
                    if (!empty($business['city'])) $mapQuery[] = $business['city'];
                    if (!empty($business['state'])) $mapQuery[] = $business['state'];
                    $mapStr = implode(', ', $mapQuery);
                    ?>
                    <?php if (!empty($mapStr)): ?>
                        <div class="profile-map-wrap">
                            <iframe
                                loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade"
                                src="https://maps.google.com/maps?q=<?php echo urlencode($mapStr); ?>&t=&z=13&ie=UTF8&iwloc=&output=embed">
                            </iframe>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </article>

    <?php else: ?>
        <div style="text-align: center; padding: 5rem;">
            <h2>Business Not Found</h2>
            <p>This listing may be pending or removed.</p>
            <a href="/">Back to Home</a>
        </div>
    <?php endif; ?>

</main>

<?php include 'footer.php'; ?>