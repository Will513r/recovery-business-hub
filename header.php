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
<!-- deployed 2026-07-05 19:59 -->
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

    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?php echo htmlspecialchars($env_vars['ADSENSE_PUB_ID'] ?? 'ca-pub-6821162875854891'); ?>" crossorigin="anonymous"></script>

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
            <a href="/" class="logo" style="text-decoration: none; display: flex; align-items: center;">
                <img src="/Recovery_Business_Hub_Logo.webp" alt="Recovery Business Hub"
                    width="180" height="45" style="max-height: 45px; width: auto;">
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