<?php
$seo_title       = isset($page_title)       ? htmlspecialchars($page_title) . ' | Recovery Business Hub' : 'Recovery Business Hub — Real Businesses. Real Comebacks.';
$seo_description = isset($page_description) ? htmlspecialchars($page_description) : 'Find services run by people in recovery. Browse our directory of recovery-owned businesses and support second chances.';

// 1. Define your default site logo (Absolute URL)
$default_image_url = "https://www.recoverybusinesshub.com/Recovery_Business_Hub_Logo.webp";

// 2. If a specific page (like profile.php) provided an image, use it. Otherwise, use default.
$og_image_url = isset($seo_image) ? $seo_image : $default_image_url;

// 3. Define the current URL for canonical and og:url tags
$current_url = "https://www.recoverybusinesshub.com" . htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '?'));
?>
<!DOCTYPE html>
<!-- deployed 2026-07-05 20:48 -->
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $seo_title; ?></title>
    <meta name="description" content="<?php echo $seo_description; ?>">

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

    <link rel="stylesheet" href="/style.css">
    <link rel="canonical" href="<?php echo $current_url; ?>">

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
            "logo" => "https://www.recoverybusinesshub.com/Recovery_Business_Hub_Logo.webp",
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
        <nav class="navbar">
            <a href="/" class="logo" aria-label="Recovery Business Hub — Home">
                <!-- Inline SVG so it renders in the site's Montserrat font -->
                <svg width="230" height="46" viewBox="0 0 320 64" role="img" aria-hidden="true" focusable="false">
                    <rect x="3.5" y="7.5" width="49" height="49" rx="12" fill="#FFFFFF" stroke="#0C1B33" stroke-width="3"/>
                    <text x="28" y="45" text-anchor="middle" font-family="Montserrat, Arial, sans-serif" font-weight="800" font-size="32" fill="#1766C2">R</text>
                    <text x="68" y="30" font-family="Montserrat, Arial, sans-serif" font-weight="800" font-size="20" fill="#0C1B33">RECOVERY</text>
                    <text x="68" y="50" font-family="Montserrat, Arial, sans-serif" font-weight="600" font-size="14" letter-spacing="3" fill="#1766C2">BUSINESS HUB</text>
                </svg>
            </a>

            <button class="menu-toggle" aria-label="Open Navigation Menu">☰</button>

            <div class="nav-overlay"></div>

            <ul class="nav-links">
                <li class="close-menu-wrapper">
                    <button class="close-menu" aria-label="Close Menu">&times;</button>
                </li>
                <li><a href="/">Home</a></li>
                <li><a href="/about.php">About</a></li>
                <li><a href="/pricing.php">Pricing</a></li>
                <li><a href="/add-business.php" class="btn-primary">Add a Business</a></li>
            </ul>
        </nav>
    </header>