<?php
// sitemap.php
require_once 'config.php';

// Tell the browser and search engines that this is an XML file, not HTML
header("Content-Type: application/xml; charset=utf-8");

// Define your base website URL
$base_url = "https://www.recoverybusinesshub.com";

// Start the XML output
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// 1. Array of your static core pages
$static_pages = [
    '/',
    '/about',
    '/pricing',
    '/add-business'
];

$current_date = date('c'); // W3C formatted date for today

// Output static pages into the XML
foreach ($static_pages as $page) {
    echo "  <url>\n";
    echo "    <loc>" . $base_url . $page . "</loc>\n";
    echo "    <lastmod>" . $current_date . "</lastmod>\n";
    echo "    <changefreq>weekly</changefreq>\n";
    echo "    <priority>1.0</priority>\n";
    echo "  </url>\n";
}

// 2. Fetch all APPROVED businesses from the database dynamically
$sql = "SELECT slug, created_at FROM businesses WHERE status = 'approved' ORDER BY created_at DESC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Ensure the slug is safe for URLs
        $slug = htmlspecialchars($row['slug']);
        // Format the database created_at timestamp into W3C Datetime format
        $date = date('c', strtotime($row['created_at']));

        echo "  <url>\n";
        echo "    <loc>" . $base_url . "/business/" . $slug . "/</loc>\n";
        echo "    <lastmod>" . $date . "</lastmod>\n";
        echo "    <changefreq>monthly</changefreq>\n";
        echo "    <priority>0.8</priority>\n";
        echo "  </url>\n";
    }
}

// Close the XML output
echo '</urlset>';
