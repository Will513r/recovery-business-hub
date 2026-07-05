<?php
// about.php
require_once 'config.php';
$page_title       = 'About Us';
$page_description = 'The Recovery Business Hub connects communities with businesses built by people in recovery. Learn about our mission and the grit behind every listing.';
include 'header.php';
?>

<main class="about-page-wrapper">
    <article class="about-card">

        <div class="about-hero-strip">
            <h1>Pain is Fuel.</h1>
            <div class="accent-bar"></div>
        </div>

        <div class="about-content">

            <div class="mission-statement">
                <p class="lead-text">
                    Let's be completely honest: when you're in recovery, the world doesn't exactly line up to hand you second chances. The deck is stacked, the odds are low, and most people expect you to fail.
                </p>
                <p>
                    <strong>But they underestimate the fire that comes from hitting rock bottom.</strong>
                </p>
            </div>

            <div class="main-body">
                <p>
                    We built the <em>Recovery Business Hub</em> because we know that individuals in recovery make some of the hardest-working, most resilient business owners on the planet. When you've fought your way back from the edge, running a business isn't just about making a buck—it's about survival and proving you can build something real.
                </p>

                <h3>What We Do</h3>
                <p>
                    This isn't just a directory; it's a community built on grit. We connect consumers who want to support second chances with the incredible businesses built by people in recovery.
                </p>
            </div>

            <div class="about-footer">
                <a href="add-business.php" class="btn-primary">Join the Directory</a>
            </div>

        </div>
    </article>
</main>

<?php
include 'footer.php';
?>