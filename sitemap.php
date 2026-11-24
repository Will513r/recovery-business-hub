<?php
// sitemap.php
require_once 'config.php';
require_once 'jobs-lib.php';

// Tell the browser and search engines that this is an XML file, not HTML
header("Content-Type: application/xml; charset=utf-8");

// Define your base website URL
$base_url = "https://www.recoverybusinesshub.com";

// Start the XML output
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// 1. Static core pages
$static_pages = [
    '/',
    '/jobs',
    '/businesses',
    '/post-job',
    '/about',
    '/privacy',
    '/add-business'
];

// Real dates. Static pages take the date their own file last changed in git
// (tools/gen-sitemap-lastmod.php). This used to stamp the current time on all 7 pages,
// so every crawl said they changed that minute (B15). A page with no entry gets no
// <lastmod>. Listings use their own database dates, never today.
$file_dates = is_file(__DIR__ . '/sitemap-lastmod.php') ? (require __DIR__ . '/sitemap-lastmod.php') : [];

// Approved businesses and open jobs, read first so the list pages can take the date
// of the newest listing they show.
$businesses = [];
$sql = "SELECT slug, created_at FROM businesses WHERE status = 'approved' ORDER BY created_at DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $businesses[] = $row;
    }
}
$jobs = rbh_open_jobs($conn, '', '', 500);

$newest_business = null;
foreach ($businesses as $row) {
    $t = strtotime((string) $row['created_at']);
    if ($t && ($newest_business === null || $t > $newest_business)) $newest_business = $t;
}
$newest_job = null;
foreach ($jobs as $job) {
    $t = strtotime((string) ($job['approved_at'] ?? ''));
    if ($t && ($newest_job === null || $t > $newest_job)) $newest_job = $t;
}

// Newest of the page's own file date and the newest listing it shows, as Y-m-d.
$page_lastmod = function (string $path) use ($file_dates, $newest_business, $newest_job): ?string {
    $dates = [];
    if (!empty($file_dates[$path])) $dates[] = $file_dates[$path];
    if (($path === '/' || $path === '/businesses') && $newest_business) $dates[] = date('Y-m-d', $newest_business);
    if (($path === '/' || $path === '/jobs') && $newest_job) $dates[] = date('Y-m-d', $newest_job);
    return $dates ? max($dates) : null;
};

// Output static pages into the XML
foreach ($static_pages as $page) {
    $lastmod = $page_lastmod($page);
    echo "  <url>\n";
    echo "    <loc>" . $base_url . $page . "</loc>\n";
    if ($lastmod) echo "    <lastmod>" . $lastmod . "</lastmod>\n";
    echo "    <changefreq>weekly</changefreq>\n";
    echo "    <priority>1.0</priority>\n";
    echo "  </url>\n";
}

// 2. Approved businesses, dated by created_at
foreach ($businesses as $row) {
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

// 3. Open jobs
foreach ($jobs as $job) {
    echo "  <url>
";
    echo "    <loc>" . $base_url . "/jobs/" . htmlspecialchars($job['slug']) . "/</loc>
";
    echo "    <lastmod>" . date('c', strtotime($job['approved_at'])) . "</lastmod>
";
    echo "    <changefreq>weekly</changefreq>
";
    echo "    <priority>0.7</priority>
";
    echo "  </url>
";
}

// Close the XML output
echo '</urlset>';
