<?php
$seo_title       = isset($page_title)       ? htmlspecialchars($page_title) . ' | Recovery Business Hub' : 'Recovery Business Hub | Second-chance jobs and recovery-owned businesses';
$seo_description = isset($page_description) ? htmlspecialchars($page_description) : 'Jobs from employers who hire people in recovery or with a record, and a list of businesses owned by people in recovery. Cincinnati, Dayton and Northern Kentucky first. Free.';

// 1. Default share image (absolute URL)
$default_image_url = "https://www.recoverybusinesshub.com/og-share.png";

// 2. If a specific page (like profile.php) provided an image, use it. Otherwise, use default.
$og_image_url = isset($seo_image) ? $seo_image : $default_image_url;

// 3. Canonical URL. One form per page: detail pages (/business/x/, /jobs/x/) end in a slash,
// every other page has none, and /index.php is the home page. Query strings never count.
$canon_path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if ($canon_path === '' || $canon_path === '/index.php') {
    $canon_path = '/';
} elseif (preg_match('#^/(business|jobs)/[^/]+/?$#', $canon_path)) {
    $canon_path = rtrim($canon_path, '/') . '/';
} else {
    $canon_path = rtrim($canon_path, '/');
}
$current_url = "https://www.recoverybusinesshub.com" . htmlspecialchars($canon_path);

// 4. Robots. A page sets $robots_directive before including this file (e.g. 'noindex, follow').
// Search and filter URLs are never worth indexing. Missing pages (404) and the admin and
// owner-only pages are kept out too. Do NOT also block these in robots.txt (B2).
$robots_directive = isset($robots_directive) ? (string) $robots_directive : '';
if ($robots_directive === '') {
    foreach (['search', 'category', 'state', 'area', 'kind', 'page'] as $rbh_k) {
        if (isset($_GET[$rbh_k])) {
            $robots_directive = 'noindex, follow';
            break;
        }
    }
}
if ($robots_directive === '' && http_response_code() === 404) {
    $robots_directive = 'noindex, follow';
}
if ($robots_directive !== '' && !headers_sent()) {
    header('X-Robots-Tag: ' . $robots_directive);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $seo_title; ?></title>
    <meta name="description" content="<?php echo $seo_description; ?>">
    <?php if ($robots_directive !== ''): ?>
    <meta name="robots" content="<?php echo htmlspecialchars($robots_directive); ?>">
    <?php endif; ?>

    <meta property="og:title" content="<?php echo $seo_title; ?>">
    <meta property="og:description" content="<?php echo $seo_description; ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Recovery Business Hub">

    <meta property="og:image" content="<?php echo $og_image_url; ?>">
    <meta property="og:url" content="<?php echo $current_url; ?>">

    <meta name="twitter:image" content="<?php echo $og_image_url; ?>">
    <meta name="twitter:card" content="summary_large_image">

    <?php if (!empty($env_vars['ADSENSE_PUB_ID'])): ?>
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?php echo htmlspecialchars($env_vars['ADSENSE_PUB_ID']); ?>" crossorigin="anonymous"></script>
    <?php endif; ?>

    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">

    <link rel="stylesheet" href="/style.css?v=<?php echo (int) @filemtime(__DIR__ . '/style.css'); ?>">
    <?php if ($robots_directive === '' || strpos($robots_directive, 'noindex') === false): ?>
    <link rel="canonical" href="<?php echo $current_url; ?>">
    <?php endif; ?>

    <script defer src="/script.js"></script>

    <?php
    // --- HOMEPAGE ORGANIZATION SCHEMA ---
    // This ensures the schema only loads when visitors are on the main index.php page
    if (basename($_SERVER['PHP_SELF']) === 'index.php'):
        $org_schema = [
            "@context" => "https://schema.org",
            "@type" => "Organization",
            "name" => "Recovery Business Hub",
            "url" => "https://www.recoverybusinesshub.com/",
            "logo" => "https://www.recoverybusinesshub.com/logo-keytag.png",
            "sameAs" => [
                "https://www.facebook.com/profile.php?id=61575303073968",
                "https://www.youtube.com/channel/UC9QdgPlsyMsmk37yRq_V98w"
            ]
        ];
    ?>
        <script type="application/ld+json">
            <?php echo json_encode($org_schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>
        </script>
    <?php endif; ?>

    <?php if (!empty($schema_json)): ?>
        <script type="application/ld+json">
            <?php echo $schema_json; ?>
        </script>
    <?php endif; ?>
</head>

<body>

    <header>
        <nav class="navbar" aria-label="Main">
            <a href="/" class="logo" aria-label="Recovery Business Hub, home">
                <svg viewBox="0 0 340 44" role="img" aria-hidden="true" focusable="false">
                    <path d="M22 4 H20 A16 16 0 0 0 4 20 V24 A16 16 0 0 0 20 40 H22 Z" fill="#1E8A4C" stroke="#141414" stroke-width="2.5" stroke-linejoin="round"/>
                    <circle cx="15" cy="22" r="4" fill="#ECECE8" stroke="#141414" stroke-width="2.5"/>
                    <path d="M22 4 H48 A4 4 0 0 1 52 8 V36 A4 4 0 0 1 48 40 H22 Z" fill="#FFFFFF" stroke="#141414" stroke-width="2.5" stroke-linejoin="round"/>
                    <path d="M29 16 H44 M29 25 H38" stroke="#141414" stroke-width="2.5" stroke-linecap="round"/>
                    <text x="64" y="30" font-family="'Bricolage Grotesque', Arial, sans-serif" font-weight="800" font-size="24" letter-spacing="-0.4" fill="#141414">recovery business hub</text>
                </svg>
            </a>

            <button class="menu-toggle" aria-label="Open navigation menu">
                <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6 H21 M3 12 H21 M3 18 H21" stroke="#141414" stroke-width="2.5" stroke-linecap="round" fill="none"/></svg>
            </button>

            <div class="nav-overlay"></div>

            <ul class="nav-links">
                <li class="close-menu-wrapper">
                    <button class="close-menu" aria-label="Close menu">&times;</button>
                </li>
                <li><a href="/jobs">Jobs</a></li>
                <li><a href="/businesses">Businesses</a></li>
                <li><a href="/add-business">List your business</a></li>
                <li><a href="/post-job" class="btn-secondary">Post a job</a></li>
            </ul>
        </nav>
    </header>
