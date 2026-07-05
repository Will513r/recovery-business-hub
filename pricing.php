<?php
// pricing.php
require_once 'config.php';

$page_title       = 'Pricing & Plans';
$page_description = 'Choose the right plan to feature your recovery-owned business. Free, Featured, and Premium options available to support your growth.';
include 'header.php';
?>

<div class="pricing-hero">
    <div style="max-width: 800px; margin: 0 auto; text-align: center;">
        <h1 style="font-size: 3rem; margin-bottom: 1rem;">Invest in Your Comeback</h1>
        <p style="font-size: 1.25rem; opacity: 0.9;">Join the directory and connect with customers who intentionally support recovery-owned businesses. Choose a plan below.</p>
    </div>
</div>

<main class="pricing-container">

    <div class="pricing-grid">

        <!-- FREE TIER -->
        <div class="pricing-card">
            <h2 class="pricing-card__title">Basic Listing</h2>
            <div class="pricing-card__price">Free</div>
            <p class="pricing-card__desc">A solid foundation to get your business found in our directory.</p>

            <ul class="pricing-card__features">
                <li><span style="color:var(--accent-color);">✔</span> Business Name & Category</li>
                <li><span style="color:var(--accent-color);">✔</span> Address & Phone Number</li>
                <li><span style="color:var(--accent-color);">✔</span> Standard Search Visibility</li>
                <li class="disabled"><span>✖</span> Website Link</li>
                <li class="disabled"><span>✖</span> Spotlight Badge</li>
            </ul>

            <a href="add-business.php?plan=free" class="btn-submit" style="text-decoration:none; display:block; text-align:center;">Start for Free</a>
        </div>

        <!-- PAID TIER -->
        <div class="pricing-card pricing-card--featured">
            <div class="pricing-card__badge">Most Popular</div>
            <h2 class="pricing-card__title">Featured Listing</h2>
            <div class="pricing-card__price">$9<span>/mo</span></div>
            <p class="pricing-card__desc">Stand out from the crowd and drive traffic directly to your website.</p>

            <ul class="pricing-card__features">
                <li><span style="color:var(--accent-color);">✔</span> All Basic Features</li>
                <li><span style="color:var(--accent-color);">✔</span> Direct Website Link</li>
                <li><span style="color:var(--accent-color);">✔</span> Email Contact Button</li>
                <li><span style="color:var(--accent-color);">✔</span> Ranked Above Free Listings</li>
                <li><span style="color:var(--accent-color);">✔</span> "Featured" Profile Badge</li>
            </ul>

            <a href="add-business.php?plan=paid" class="btn-submit" style="text-decoration:none; display:block; text-align:center; background-color: var(--button-color);">Choose Featured</a>
        </div>

        <!-- PREMIUM TIER -->
        <div class="pricing-card pricing-card--premium">
            <h2 class="pricing-card__title" style="color: #10B981;">Premium Partner</h2>
            <div class="pricing-card__price" style="color: #10B981;">$29<span>/mo</span></div>
            <p class="pricing-card__desc">Maximum visibility and storytelling for businesses scaling their impact.</p>

            <ul class="pricing-card__features">
                <li><span style="color:var(--accent-color);">✔</span> All Featured Benefits</li>
                <li><span style="color:var(--accent-color);">✔</span> Interactive Google Maps Embed</li>
                <li><span style="color:var(--accent-color);">✔</span> Top Ranked in Searches</li>
                <li><span style="color:var(--accent-color);">✔</span> "Premium Partner" Badge</li>
                <li><span style="color:var(--accent-color);">✔</span> Prioritized Admin Support</li>
            </ul>

            <a href="add-business.php?plan=premium" class="btn-submit" style="text-decoration:none; display:block; text-align:center; background-color: var(--accent-color);">Become a Partner</a>
        </div>

    </div>

    <div style="text-align: center; margin-top: 4rem; padding: 2rem; background: var(--bg-light); border-radius: var(--radius-modern);">
        <h3 style="color: var(--primary-color);">Have questions?</h3>
        <p style="margin-bottom: 1rem;">Need help determining which plan is right for your business?</p>
        <a href="mailto:recoverybusinesshub@gmail.com" class="btn-secondary" style="text-decoration:none;">Contact Support</a>
    </div>

</main>

<?php include 'footer.php'; ?>