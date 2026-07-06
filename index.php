<?php
require_once 'config.php';
include 'performance.php'; // performance helpers

// 1. Get the search term, selected categories, and current page
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$selected_categories = isset($_GET['category']) ? $_GET['category'] : [];
$selected_state = isset($_GET['state']) ? $_GET['state'] : '';
$per_page = 9;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $per_page;

// 2. Start building the SQL Query
$where_clauses = ["b.status = 'approved'"];
$params = [];
$types = "";

// Add State Filter Logic
if (!empty($selected_state)) {
    $where_clauses[] = "b.state = ?";
    $params[] = $selected_state;
    $types .= "s";
}

// 3. Add Search Logic if a search was entered
if (!empty($search)) {
    $where_clauses[] = "(b.name LIKE ? OR b.category LIKE ? OR b.description LIKE ? OR b.city LIKE ?)";
    $searchTerm = "%$search%";
    array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    $types .= "ssss";
}

// 4. Add Category Filter Logic if boxes were checked
if (!empty($selected_categories) && is_array($selected_categories)) {
    $cat_conditions = [];
    foreach ($selected_categories as $cat) {
        $cat_conditions[] = "(CASE WHEN JSON_VALID(b.category) THEN JSON_CONTAINS(b.category, ?) ELSE b.category = ? END)";
        $params[] = '"' . $cat . '"';
        $params[] = $cat;
        $types .= "ss";
    }
    $where_clauses[] = "(" . implode(" OR ", $cat_conditions) . ")";
}

// Combine all the rules together
$where_sql = implode(" AND ", $where_clauses);

// Count total matching results for pagination
$count_sql = "SELECT COUNT(*) as total FROM businesses b WHERE $where_sql";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_results = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_results / $per_page);
$count_stmt->close();

$sql = "SELECT b.*, AVG(r.rating) as avg_rating, COUNT(r.rating) as review_count
        FROM businesses b 
        LEFT JOIN reviews r ON b.id = r.business_id
        WHERE $where_sql 
        GROUP BY b.id
        ORDER BY 
            CASE b.tier 
                WHEN 'premium' THEN 1 
                WHEN 'paid' THEN 2 
                WHEN 'free' THEN 3
                ELSE 4
            END ASC,
            b.created_at DESC 
        LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;
$types .= "ii";

// Prepare and run the query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// --- 5. DYNAMIC SEO META TITLE & DESCRIPTION ---
$meta_desc_parts = [];
$meta_title = "Business Directory";

if (!empty($selected_categories)) {
    $cat_string = implode(' and ', $selected_categories);
    $meta_desc_parts[] = $cat_string . " businesses run by people in recovery";
    $meta_title = $cat_string . " Directory";
} else {
    $meta_desc_parts[] = "recovery-owned businesses";
}

if (!empty($selected_state)) {
    $meta_desc_parts[] = "in " . $selected_state;
    if (empty($selected_categories)) {
        $meta_title = "Businesses in " . $selected_state;
    } else {
        $meta_title .= " in " . $selected_state;
    }
}

if (!empty($search)) {
    $meta_desc_parts[] = "matching '" . $search . "'";
}

if (empty($selected_categories) && empty($selected_state) && empty($search)) {
    $page_title       = 'Business Directory';
    $page_description = 'Browse recovery-owned businesses across all categories. Find services built by people in recovery who out-work the competition.';
} else {
    $page_title       = $meta_title;
    $page_description = "Find " . implode(' ', $meta_desc_parts) . " on Recovery Business Hub. Support second chances and real comebacks.";
}

include 'header.php';
?>

<section class="hero">
    <h1>Real Businesses. Real Comebacks. Fueled by Grit.</h1>
    <p>Find services run by people in recovery who out-work the competition.</p>

    <form class="search-bar" action="index.php" method="GET">
        <div class="search-input-wrapper">
            <input type="text" name="search" placeholder="Search for a business..." value="<?php echo htmlspecialchars($search); ?>">
            <?php if (!empty($search)): ?>
                <a href="index.php" class="clear-search" aria-label="Clear search">✖</a>
            <?php endif; ?>
        </div>
        <button type="submit">Find Businesses</button>
    </form>
</section>

<main class="directory-container">
    <aside class="filters">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.8rem;">
            <h3 style="margin: 0; border: none; padding: 0;">Filter Directory</h3>
            <button id="mobile-filter-toggle" class="btn-primary" type="button" style="padding: 0.4rem 0.8rem; font-size: 0.9rem;">⚙️ Filters</button>
        </div>

        <div id="filter-collapse" class="filter-collapse">
            <h4 style="margin-bottom: 1rem; color: var(--primary-color);">By Category</h4>
            <form id="filter-form" action="index.php" method="GET">

                <?php if (!empty($search)): ?>
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <?php endif; ?>

                <ul class="filter-list">
                    <?php foreach ($categories as $cat): ?>
                        <li>
                            <label class="filter-check-label">
                                <input type="checkbox" name="category[]" value="<?php echo htmlspecialchars($cat); ?>"
                                    <?php if (in_array($cat, $selected_categories)) echo 'checked'; ?>
                                    onchange="document.getElementById('filter-form').submit();">
                                <?php echo htmlspecialchars($cat); ?>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <h3 style="margin-top: 1.5rem; margin-bottom: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; font-size: 1.17em;">Filter by State</h3>
                <select name="state" class="filter-select" onchange="document.getElementById('filter-form').submit();">
                    <option value="">All States</option>
                    <?php foreach ($states_list as $st): ?>
                        <option value="<?php echo htmlspecialchars($st); ?>" <?php if ($selected_state == $st) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($st); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn-primary" style="width: 100%; margin-top: 1rem;">Apply Filters</button>

                <?php if (!empty($selected_categories) || !empty($search) || !empty($selected_state)): ?>
                    <a href="index.php" class="btn-secondary" style="display: block; text-align: center; margin-top: 0.5rem; text-decoration: none;">Clear All</a>
                <?php endif ?>

            </form>
        </div>
    </aside>

    <section class="listings-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2 style="color: var(--text-main); margin: 0;">Featured Businesses</h2>
            <span style="color: var(--text-muted); font-size: 0.9rem;">
                <?php echo $total_results; ?> business<?php echo $total_results !== 1 ? 'es' : ''; ?> found
            </span>
        </div>

        <div class="listings">
            <?php
            if ($result && $result->num_rows > 0):
                while ($business = $result->fetch_assoc()):
            ?>
                    <article class="business-card tier-<?php echo htmlspecialchars($business['tier']); ?>">
                        <div class="card-content">
                            <?php if ($business['tier'] === 'premium'): ?>
                                <span class="badge badge-premium">Premium</span>
                            <?php elseif ($business['tier'] === 'paid'): ?>
                                <span class="badge badge-paid">Featured</span>
                            <?php endif; ?>

                            <div class="card-header">
                                <?php if (!empty($business['logo'])): ?>
                                    <img src="<?php echo htmlspecialchars($business['logo']); ?>" alt="Official business logo for <?php echo htmlspecialchars($business['name']); ?>" class="business-logo" loading="lazy">
                                <?php else: ?>
                                    <?php
                                    $words = explode(' ', trim($business['name']));
                                    $initials = '';
                                    foreach ($words as $word) {
                                        if (!empty($word)) {
                                            $initials .= substr($word, 0, 1);
                                        }
                                    }
                                    $initials = substr($initials, 0, 2);
                                    ?>
                                    <div class="logo-placeholder"><?php echo htmlspecialchars($initials); ?></div>
                                <?php endif; ?>

                                <div>
                                    <h3 style="margin: 0;"><?php echo htmlspecialchars($business['name']); ?></h3>
                                    <?php if (!empty($business['is_verified'])): ?>
                                        <span class="badge-verified" style="margin-top: 4px; display: inline-flex;">✔ Verified</span>
                                    <?php endif; ?>

                                    <?php if (isset($business['review_count']) && $business['review_count'] > 0): ?>
                                        <div style="display: flex; align-items: center; gap: 4px; margin-top: 8px;">
                                            <span style="color: #F59E0B; font-size: 0.95rem;">★</span>
                                            <span style="font-weight: 600; font-size: 0.95rem; color: var(--text-main);"><?php echo round($business['avg_rating'], 1); ?></span>
                                            <span style="color: var(--text-muted); font-size: 0.85rem;">(<?php echo $business['review_count']; ?>)</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="contact-info">
                                <?php if (!empty($business['address']) || !empty($business['city']) || !empty($business['state'])): ?>
                                    <p>📍 <?php
                                            $loc = [];
                                            if (!empty($business['address'])) $loc[] = $business['address'];
                                            if (!empty($business['city'])) $loc[] = $business['city'];
                                            if (!empty($business['state'])) $loc[] = $business['state'];
                                            echo htmlspecialchars(implode(', ', $loc));
                                            ?></p>
                                <?php endif; ?>
                                <p>📞 <?php echo htmlspecialchars($business['phone']); ?></p>
                            </div>

                            <?php
                            // FIXED: Safe JSON Parsing Fallback
                            $card_cats = json_decode($business['category'], true) ?? [];
                            if (empty($card_cats) && !empty($business['category'])) {
                                $card_cats = [$business['category']];
                            }
                            ?>
                            <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 0.5rem; margin-top: 1rem;">
                                <?php foreach ($card_cats as $cc): ?>
                                    <span class="category-tag">
                                        <?php echo htmlspecialchars($cc); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>

                            <p class="description" style="margin-top: 1rem;"><?php echo htmlspecialchars(mb_strimwidth($business['description'], 0, 100, "...")); ?></p>

                            <a href="/business/<?php echo htmlspecialchars($business['slug']); ?>/" class="btn-secondary" style="margin-top: 1rem;">View Profile</a>
                        </div>
                    </article>
                <?php
                endwhile;
            else:
                ?>
                <div class="empty-state">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">🔍</div>
                    <h3 style="color: var(--primary-color); margin-bottom: 0.5rem;">No results found</h3>
                    <p style="color: var(--text-muted); margin-bottom: 1.5rem;">We couldn't find any businesses matching your current search or filters.</p>
                    <a href="index.php" class="btn-primary">Clear All Filters</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($total_results > 0): ?>
            <nav style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-top: 3rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                <?php
                $start_item = $offset + 1;
                $end_item = min($offset + $per_page, $total_results);
                ?>
                <div style="color: var(--text-muted); font-size: 0.95rem;">
                    Showing <strong><?php echo $start_item; ?>–<?php echo $end_item; ?></strong> of <strong><?php echo $total_results; ?></strong> results
                </div>

                <div style="display: flex; gap: 0.5rem;">
                    <?php
                    $query_parts = [];
                    if (!empty($search)) $query_parts[] = 'search=' . urlencode($search);
                    if (!empty($selected_state)) $query_parts[] = 'state=' . urlencode($selected_state);
                    foreach ($selected_categories as $cat) $query_parts[] = 'category[]=' . urlencode($cat);
                    $base_query = implode('&', $query_parts);
                    ?>

                    <?php if ($current_page > 1): ?>
                        <a href="index.php?<?php echo $base_query; ?>&page=<?php echo $current_page - 1; ?>" class="btn-secondary" style="text-decoration: none;">&larr; Previous</a>
                    <?php endif; ?>

                    <?php if ($current_page < $total_pages): ?>
                        <a href="index.php?<?php echo $base_query; ?>&page=<?php echo $current_page + 1; ?>" class="btn-secondary" style="text-decoration: none;">Next &rarr;</a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    </section>
</main>

<?php include 'footer.php'; ?>