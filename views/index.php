<?php
// index.php
// 1. Include the database connection
require_once 'config.php';

// 2. Prepare the SQL query to fetch approved businesses, ordered by tier
$sql = "SELECT * FROM businesses 
        WHERE status = 'approved' 
        ORDER BY 
            CASE tier 
                WHEN 'premium' THEN 1 
                WHEN 'paid' THEN 2 
                WHEN 'free' THEN 3 
            END ASC";

// 3. Execute the query
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recovery Business Hub | Directory</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <header>
        <nav class="navbar">
            <div class="logo">Recovery Business Hub</div>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="add-business.php" class="btn-primary">Add a Business</a></li>
            </ul>
        </nav>
    </header>

    <section class="hero">
        <h1>Support Businesses in Recovery</h1>
        <p>Find, highlight, and support small businesses owned and operated by individuals in recovery.</p>
        <form class="search-bar" action="index.php" method="GET">
            <input type="text" name="search" placeholder="Search for a business or service...">
            <button type="submit">Search</button>
        </form>
    </section>

    <main class="directory-container">
        <aside class="filters">
            <h3>Filter by Category</h3>
            <ul>
                <li><label><input type="checkbox"> Food & Dining</label></li>
                <li>
                    <label><input type="checkbox"> Home Services</label>
                    <ul class="subcategories">
                        <li><label><input type="checkbox"> Landscaping</label></li>
                        <li><label><input type="checkbox"> HVAC</label></li>
                        <li><label><input type="checkbox"> Plumbing</label></li>
                        <li><label><input type="checkbox"> Electrical</label></li>
                    </ul>
                </li>
                <li><label><input type="checkbox"> Retail</label></li>
                <li><label><input type="checkbox"> Consulting</label></li>
            </ul>
        </aside>

        <section class="listings">
            <h2>Featured Businesses</h2>

            <?php
            // 4. Check if we have results, then loop through them
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
                                <img src="<?php echo htmlspecialchars($business['logo']); ?>" alt="<?php echo htmlspecialchars($business['name']); ?> Logo" class="business-logo">
                                <h3><?php echo htmlspecialchars($business['name']); ?></h3>
                            </div>

                            <p class="category"><strong>Category:</strong> <?php echo htmlspecialchars($business['category']); ?></p>

                            <div class="contact-info">
                                <p>📍 <?php echo htmlspecialchars($business['address']); ?></p>
                                <p>📞 <?php echo htmlspecialchars($business['phone']); ?></p>
                            </div>

                            <p class="description"><?php echo htmlspecialchars($business['description']); ?></p>
                            <a href="#" class="btn-secondary">View Profile</a>
                        </div>
                    </article>

                <?php
                endwhile;
            else:
                ?>
                <p>No businesses found at the moment. Be the first to apply!</p>
            <?php endif; ?>

        </section>
    </main>

    <footer>
        <p>&copy; 2026 Recovery Business Hub. All rights reserved.</p>
    </footer>

</body>

</html>