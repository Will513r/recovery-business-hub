<?php
// index.php: home. Two doors (jobs, businesses), newest jobs, a few listings.
// Look: art-direction.md (Key tag). The full directory lives in businesses.php.
require_once 'config.php';
require_once 'jobs-lib.php';
require_once 'cards-lib.php';
include 'performance.php';

$new_jobs   = rbh_open_jobs($conn, '', '', 3);
$hiring_ids = rbh_hiring_business_ids($conn);

$biz_rows = [];
$biz_res = $conn->query("SELECT * FROM businesses WHERE status = 'approved' ORDER BY is_verified DESC, created_at DESC LIMIT 12");
if ($biz_res) {
    while ($row = $biz_res->fetch_assoc()) {
        $biz_rows[] = $row;
    }
}

include 'header.php';
?>

<section class="hero">
    <div class="wrap">
        <div>
            <h1>Jobs from employers who already said yes.</h1>
            <p class="hero__sub">Second-chance jobs in Cincinnati, Dayton and Northern Kentucky, plus a list of businesses owned by people in recovery. Free to use.</p>
            <div class="doors">
                <a href="/jobs" class="btn-primary">Find work</a>
                <a href="/businesses" class="btn-secondary">Find a business</a>
            </div>
            <p class="hero__note">No account. No record on file. Applying is an email to the employer.</p>
        </div>

        <div class="tag-stack" aria-hidden="true">
            <div class="tag-card tag-card--green">
                <div class="tag-card__stub"><span class="tag-card__hole"></span></div>
                <div class="tag-card__face">
                    <p class="tag-stack__label">Open job</p>
                    <span class="tag-blank-line"></span>
                    <span class="tag-blank-line"></span>
                </div>
            </div>
            <div class="tag-card tag-card--ink">
                <div class="tag-card__stub"><span class="tag-card__hole"></span></div>
                <div class="tag-card__face">
                    <p class="tag-stack__label">Recovery-owned business</p>
                    <span class="tag-blank-line"></span>
                    <span class="tag-blank-line"></span>
                </div>
            </div>
            <div class="tag-card tag-card--open">
                <div class="tag-card__stub"><span class="tag-card__hole"></span></div>
                <div class="tag-card__face">
                    <p class="tag-stack__label">Yours here</p>
                    <span class="tag-blank-line"></span>
                    <span class="tag-blank-line"></span>
                </div>
            </div>
            <span class="tag-stack__ring"></span>
        </div>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <hr class="rule">
        <div class="section-head">
            <h2>Newest jobs</h2>
            <p>Every post says up front whether the employer runs a background check.</p>
        </div>

        <?php if ($new_jobs): ?>
            <div class="card-grid card-grid--3">
                <?php foreach ($new_jobs as $job): ?>
                    <?php echo rbh_job_card($job); ?>
                <?php endforeach; ?>
            </div>
            <p class="more-link"><a class="link" href="/jobs"><strong>See all jobs</strong></a></p>
        <?php else: ?>
            <div class="empty-state">
                <?php echo rbh_blank_tag(); ?>
                <h3>No jobs posted yet</h3>
                <p>Hiring in Cincinnati, Dayton or Northern Kentucky? Post the first one. It's free, and every post gets a look from us before it goes up.</p>
                <a href="/post-job" class="btn-secondary">Post a job</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <hr class="rule">
        <div class="section-head">
            <h2>Recovery-owned businesses</h2>
            <p>Spend your money with someone who's good at their trade.</p>
        </div>

        <?php if ($biz_rows): ?>
            <div class="card-grid card-grid--3">
                <?php foreach ($biz_rows as $biz): ?>
                    <?php echo rbh_business_card($biz, $hiring_ids); ?>
                <?php endforeach; ?>
            </div>
            <p class="more-link"><a class="link" href="/businesses"><strong>See the full list</strong></a></p>
        <?php else: ?>
            <div class="empty-state">
                <?php echo rbh_blank_tag(); ?>
                <h3>The list is just getting started</h3>
                <p>No businesses are listed yet. If you own one, yours can be first. It's free.</p>
                <a href="/add-business" class="btn-secondary">List your business</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <hr class="rule">
        <div class="section-head">
            <h2>How it works</h2>
        </div>

        <div class="how-grid">
            <div class="how-block">
                <h3>If you're hiring</h3>
                <ul>
                    <li>Post the job: pay, area, and whether you run a background check.</li>
                    <li>We read every post before it goes up.</li>
                    <li>Applications come to your inbox by email. We get a copy, and keep nothing on the site.</li>
                </ul>
                <a href="/post-job" class="btn-secondary">Post a job</a>
            </div>

            <div class="how-block">
                <h3>If you own a business</h3>
                <ul>
                    <li>List it free. It has to be owned by someone in recovery.</li>
                    <li>You choose how much of your story to share. Leave it blank if you'd rather.</li>
                    <li>We review every listing before it shows.</li>
                </ul>
                <a href="/add-business" class="btn-secondary">List your business</a>
            </div>
        </div>
        <p class="more-link"><a class="link" href="/about"><strong>Why we built this</strong></a></p>
    </div>
</section>

<?php include 'footer.php'; ?>
