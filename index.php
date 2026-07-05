<?php
// index.php — directory listing with working search + category filter.
require_once __DIR__ . '/includes/init.php';

$search   = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
if ($category !== '' && !in_array($category, $categories, true)) {
    $category = '';
}

$sql    = "SELECT * FROM businesses WHERE status = 'approved'";
$types  = '';
$params = [];

if ($search !== '') {
    $sql .= " AND (name LIKE ? OR category LIKE ? OR description LIKE ?)";
    $like = '%' . $search . '%';
    $types .= 'sss';
    array_push($params, $like, $like, $like);
}
if ($category !== '') {
    $sql .= " AND category = ?";
    $types .= 's';
    $params[] = $category;
}

$sql .= " ORDER BY
            CASE tier
                WHEN 'premium' THEN 1
                WHEN 'paid' THEN 2
                WHEN 'free' THEN 3
                ELSE 4
            END ASC,
            name ASC";

$stmt = $conn->prepare($sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$page_title = 'Recovery Business Hub | Directory';
require __DIR__ . '/includes/header.php';
?>

    <section class="hero">
        <h1>Support Businesses in Recovery</h1>
        <p>Find, highlight, and support small businesses owned and operated by individuals in recovery.</p>
        <form class="search-bar" action="index.php" method="GET">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search for a business or service...">
            <?php if ($category !== ''): ?>
                <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
            <?php endif; ?>
            <button type="submit">Search</button>
        </form>
    </section>

    <main class="directory-container">
        <aside class="filters">
            <h3>Filter by Category</h3>
            <ul>
                <li><a href="index.php<?php echo $search !== '' ? '?search=' . urlencode($search) : ''; ?>" class="<?php echo $category === '' ? 'active' : ''; ?>">All Categories</a></li>
                <?php foreach ($categories as $cat): ?>
                    <li>
                        <a href="index.php?category=<?php echo urlencode($cat); ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>"
                           class="<?php echo $category === $cat ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($cat); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </aside>

        <section class="listings">
            <h2><?php echo ($search !== '' || $category !== '') ? 'Search Results' : 'Featured Businesses'; ?></h2>

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
                                    <img src="<?php echo htmlspecialchars($business['logo']); ?>" alt="<?php echo htmlspecialchars($business['name']); ?> Logo" class="business-logo">
                                <?php else: ?>
                                    <span class="business-logo logo-fallback"><?php echo htmlspecialchars(strtoupper(mb_substr($business['name'], 0, 1))); ?></span>
                                <?php endif; ?>
                                <h3><?php echo htmlspecialchars($business['name']); ?></h3>
                            </div>

                            <p class="category"><strong>Category:</strong> <?php echo htmlspecialchars($business['category']); ?></p>

                            <div class="contact-info">
                                <p>📍 <?php echo htmlspecialchars($business['address']); ?></p>
                                <p>📞 <?php echo htmlspecialchars($business['phone']); ?></p>
                            </div>

                            <p class="description"><?php echo htmlspecialchars($business['description']); ?></p>
                        </div>
                    </article>

                <?php
                endwhile;
            elseif ($search !== '' || $category !== ''):
                ?>
                <p>No businesses match your search. <a href="index.php">See all listings</a>.</p>
            <?php else: ?>
                <p>No businesses found at the moment. Be the first to apply!</p>
            <?php endif; ?>

        </section>
    </main>

<?php require __DIR__ . '/includes/footer.php'; ?>
