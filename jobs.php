<?php
// jobs.php: open second-chance jobs. Filters are a plain GET form, no JS needed.
// Look: art-direction.md (Key tag).
require_once 'config.php';
require_once 'jobs-lib.php';
require_once 'cards-lib.php';

$area = isset($_GET['area']) && is_string($_GET['area']) ? $_GET['area'] : '';
$kind = isset($_GET['kind']) && is_string($_GET['kind']) ? $_GET['kind'] : '';
if (!isset(RBH_AREAS[$area])) {
    $area = '';
}
if (!in_array($kind, RBH_JOB_KINDS, true)) {
    $kind = '';
}
$has_filters = $area !== '' || $kind !== '';

$jobs = rbh_open_jobs($conn, $area, $kind);

// No open jobs means an empty page. Keep it out of Google until the first job is live (S6);
// it flips to indexable by itself the day a job is approved.
if (!$jobs) {
    $robots_directive = 'noindex, follow';
}

$page_title       = 'Second-chance jobs in Cincinnati, Dayton and Northern Kentucky';
$page_description = 'Jobs from employers who say up front whether they run a background check. Cincinnati, Dayton and Northern Kentucky. No account. Applying is an email to the employer.';
include 'header.php';
?>

<section class="page-head">
    <div class="wrap">
        <h1>Jobs from employers who said yes up front</h1>
        <p>Every job here states the employer's background check policy up front. Nothing to disclose. You know where you stand before you apply.</p>

        <form class="job-filters" action="/jobs" method="GET">
            <div class="job-filters__field">
                <label for="area">Area</label>
                <select id="area" name="area" class="filter-select">
                    <option value="">All areas</option>
                    <?php foreach (RBH_AREAS as $key => $label): ?>
                        <option value="<?php echo rbh_h($key); ?>"<?php echo $area === $key ? ' selected' : ''; ?>><?php echo rbh_h($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="job-filters__field">
                <label for="kind">Kind of work</label>
                <select id="kind" name="kind" class="filter-select">
                    <option value="">All kinds of work</option>
                    <?php foreach (RBH_JOB_KINDS as $k): ?>
                        <option value="<?php echo rbh_h($k); ?>"<?php echo $kind === $k ? ' selected' : ''; ?>><?php echo rbh_h($k); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="job-filters__actions">
                <button type="submit" class="btn-secondary">Show jobs</button>
                <?php if ($has_filters): ?>
                    <a href="/jobs" class="btn-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </form>

        <p class="job-legend">Green tag: no background check. Blue tag: background check, case by case.</p>
    </div>
</section>

<main class="wrap jobs-list">
    <?php if ($jobs): ?>
        <div class="listings-head">
            <h2><?php echo $has_filters ? 'Matching jobs' : 'Open jobs'; ?></h2>
            <span><?php echo count($jobs); ?> job<?php echo count($jobs) !== 1 ? 's' : ''; ?></span>
        </div>
        <div class="card-grid job-grid">
            <?php foreach ($jobs as $job): ?>
                <?php echo rbh_job_card($job); ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <?php echo rbh_blank_tag(); ?>
            <h2>No open jobs here yet</h2>
            <?php if ($has_filters): ?>
                <p>Try another area or kind of work. New jobs go up as employers post them.</p>
                <div class="about-footer">
                    <a href="/jobs" class="btn-secondary">Clear filters</a>
                    <a href="/post-job" class="btn-secondary">Hiring? Post a job</a>
                </div>
            <?php else: ?>
                <p>Hiring? Post a job and say how you handle background checks. It's free.</p>
                <a href="/post-job" class="btn-secondary">Post a job</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>

<?php include 'footer.php'; ?>
