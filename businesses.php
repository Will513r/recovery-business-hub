<?php
require_once 'config.php';
require_once 'jobs-lib.php';
require_once 'cards-lib.php';
include 'performance.php'; // performance helpers

// 1. Get the search term, selected categories, and current page
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$selected_categories = isset($_GET['category']) ? $_GET['category'] : [];
if (!is_array($selected_categories)) {
    $selected_categories = [];
}
// Drop any URL-supplied category that isn't in our known list
$selected_categories = array_values(array_intersect($selected_categories, $categories));
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
if (!empty($selected_categories)) {
    $cat_conditions = [];
    foreach ($selected_categories as $cat) {
        $cat_conditions[] = "(CASE WHEN JSON_VALID(b.category) THEN JSON_CONTAINS(b.category, ?) ELSE b.category = ? END)";
        $params[] = json_encode($cat);
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
        ORDER BY b.is_verified DESC, b.created_at DESC
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
$meta_title = "Recovery-owned businesses";

if (!empty($selected_categories)) {
    $cat_string = implode(' and ', $selected_categories);
    $meta_desc_parts[] = $cat_string . " businesses run by people in recovery";
    $meta_title = $cat_string . " businesses";
} else {
    $meta_desc_parts[] = "recovery-owned businesses";
}

if (!empty($selected_state)) {
    $meta_desc_parts[] = "in " . $selected_state;
    if (empty($selected_categories)) {
        $meta_title = "Recovery-owned businesses in " . $selected_state;
    } else {
        $meta_title .= " in " . $selected_state;
    }
}

if (!empty($search)) {
    $meta_desc_parts[] = "matching '" . $search . "'";
}

if (empty($selected_categories) && empty($selected_state) && empty($search)) {
    $page_title       = 'Recovery-owned businesses';
    $page_description = 'Businesses owned by people in recovery. Search by name, trade or city. Cincinnati, Dayton and Northern Kentucky first.';
} else {
    $page_title       = $meta_title;
    $page_description = "Find " . implode(' ', $meta_desc_parts) . " on Recovery Business Hub. Spend your money with people who are good at their trade.";
}

include 'header.php';

$hiring_ids  = rbh_hiring_business_ids($conn);
$has_filters = !empty($selected_categories) || !empty($search) || !empty($selected_state);
?>

<section class="page-head">
    <div class="wrap">
        <h1>Recovery-owned businesses</h1>
        <p>Spend your money with someone who's good at their trade. Every business here is owned by a person in recovery.</p>

        <form class="search-bar" action="/businesses" method="GET">
            <div class="search-input-wrapper">
                <label class="visually-hidden" for="search">Search businesses</label>
                <input type="text" id="search" name="search" placeholder="Search by name, trade or city" value="<?php echo htmlspecialchars($search); ?>">
                <?php if (!empty($search)): ?>
                    <a href="/businesses" class="clear-search" aria-label="Clear search">&times;</a>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn-secondary">Search</button>
        </form>
    </div>
</section>

<main class="directory-container">
    <aside class="filters">
        <div class="filters__head">
            <h2>Filter</h2>
            <button id="mobile-filter-toggle" class="btn-secondary" type="button">Filters</button>
        </div>

        <div id="filter-collapse" class="filter-collapse">
            <form id="filter-form" action="/businesses" method="GET">

                <?php if (!empty($search)): ?>
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <?php endif; ?>

                <h3>Trade</h3>
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

                <h3>State</h3>
                <select name="state" class="filter-select" aria-label="State" onchange="document.getElementById('filter-form').submit();">
                    <option value="">All states</option>
                    <?php foreach ($states_list as $st): ?>
                        <option value="<?php echo htmlspecialchars($st); ?>" <?php if ($selected_state == $st) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($st); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn-secondary">Apply filters</button>

                <?php if ($has_filters): ?>
                    <a href="/businesses" class="btn-secondary">Clear all</a>
                <?php endif; ?>

            </form>
        </div>
    </aside>

    <section class="listings-container">
        <div class="listings-head">
            <h2><?php echo $has_filters ? 'Results' : 'All businesses'; ?></h2>
            <span><?php echo (int)$total_results; ?> business<?php echo $total_results != 1 ? 'es' : ''; ?></span>
        </div>

        <?php if ($result && $result->num_rows > 0): ?>
            <div class="card-grid">
                <?php while ($business = $result->fetch_assoc()): ?>
                    <?php echo rbh_business_card($business, $hiring_ids); ?>
                <?php endwhile; ?>
            </div>
        <?php elseif ($has_filters): ?>
            <div class="empty-state">
                <?php echo rbh_blank_tag(); ?>
                <h3>Nothing matches that yet</h3>
                <p>Try fewer filters, or a different spelling. The list is growing.</p>
                <a href="/businesses" class="btn-secondary">Clear all filters</a>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <?php echo rbh_blank_tag(); ?>
                <h3>The list is just getting started</h3>
                <p>No businesses are listed yet. If you own one, yours can be first. It's free.</p>
                <a href="/add-business" class="btn-secondary">List your business</a>
            </div>
        <?php endif; ?>

        <?php if ($total_results > 0): ?>
            <nav class="pager" aria-label="Pages">
                <?php
                $start_item = $offset + 1;
                $end_item = min($offset + $per_page, $total_results);
                $query_parts = [];
                if (!empty($search)) $query_parts[] = 'search=' . urlencode($search);
                if (!empty($selected_state)) $query_parts[] = 'state=' . urlencode($selected_state);
                foreach ($selected_categories as $cat) $query_parts[] = 'category[]=' . urlencode($cat);
                $base_query = implode('&', $query_parts);
                $join = $base_query !== '' ? '&' : '';
                ?>
                <div class="pager__count">Showing <?php echo $start_item; ?> to <?php echo $end_item; ?> of <?php echo (int)$total_results; ?></div>

                <div class="pager__links">
                    <?php if ($current_page > 1): ?>
                        <a href="/businesses?<?php echo $base_query . $join; ?>page=<?php echo $current_page - 1; ?>" class="btn-secondary">&larr; Previous</a>
                    <?php endif; ?>
                    <?php if ($current_page < $total_pages): ?>
                        <a href="/businesses?<?php echo $base_query . $join; ?>page=<?php echo $current_page + 1; ?>" class="btn-secondary">Next &rarr;</a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    </section>
</main>

<?php include 'footer.php'; ?>
