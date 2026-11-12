<?php
// 404.php: a lone blank tag and two ways back.
// To serve this for missing URLs, point ErrorDocument 404 at /404.php in .htaccess.
require_once 'config.php';
require_once 'cards-lib.php';
http_response_code(404);
$page_title       = 'Page not found';
$page_description = 'That page is not here.';
include 'header.php';
?>

<main class="about-page-wrapper">
    <div class="empty-state empty-state--center">
        <?php echo rbh_blank_tag(); ?>
        <h1>That page isn't here.</h1>
        <p>It may have moved, or the link may be off by a letter.</p>
        <div class="about-footer">
            <a href="/jobs" class="btn-secondary">Find work</a>
            <a href="/businesses" class="btn-secondary">Find a business</a>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
