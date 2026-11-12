<?php
// job.php: one open job at /jobs/{slug}/ (rewritten to ?slug=). Apply form emails the employer.
// Nothing the applicant types is stored. Look: art-direction.md (Key tag).
require_once 'config.php';
require_once 'jobs-lib.php';
require_once 'cards-lib.php';

$slug = isset($_GET['slug']) && is_string($_GET['slug']) ? $_GET['slug'] : '';
$job = $slug !== '' ? rbh_open_job($conn, $slug) : null;
if ($job === null) {
    include '404.php';
    exit;
}

$result = ['ok' => false, 'errors' => [], 'values' => []];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = rbh_handle_apply($conn, $job);
}
$errors  = $result['errors'];
$values  = $result['values'] + ['name' => '', 'phone' => '', 'email' => '', 'message' => ''];
$applied = $_SERVER['REQUEST_METHOD'] === 'POST' && $result['ok'];

// Stamp after the POST check so the time trap measures the render the visitor filled in.
if (!$applied) {
    rbh_form_stamp('apply_' . (int)$job['id']);
}

$stub        = $job['policy'] === 'no_check' ? 'green' : 'blue';
$area_label  = rbh_area_label((string)$job['area']);
$town        = trim((string)$job['town']);
$where       = $town !== '' ? $town . ', ' . $area_label : $area_label;
$policy_text = rbh_policy_label((string)$job['policy']);
$pay_text    = rbh_pay_label($job);
$canonical   = RBH_SITE_URL . '/jobs/' . $job['slug'] . '/';

// JobPosting schema
$posting = [
    '@context'           => 'https://schema.org',
    '@type'              => 'JobPosting',
    'title'              => $job['title'],
    'description'        => nl2br(htmlspecialchars((string)$job['description'], ENT_QUOTES, 'UTF-8')),
    'datePosted'         => date('c', strtotime((string)$job['approved_at'])),
    'validThrough'       => date('c', strtotime((string)$job['expires_at'])),
    'hiringOrganization' => ['@type' => 'Organization', 'name' => $job['employer_name']],
    'jobLocation'        => [
        '@type'   => 'Place',
        'address' => [
            '@type'           => 'PostalAddress',
            'addressLocality' => $town !== '' ? $town : $area_label,
            'addressRegion'   => $job['area'] === 'nky' ? 'KY' : 'OH',
            'addressCountry'  => 'US',
        ],
    ],
    'url' => $canonical,
];
$value = ['@type' => 'QuantitativeValue', 'minValue' => (float)$job['pay_min'], 'unitText' => $job['pay_unit'] === 'year' ? 'YEAR' : 'HOUR'];
if (!empty($job['pay_max']) && (float)$job['pay_max'] > (float)$job['pay_min']) {
    $value['maxValue'] = (float)$job['pay_max'];
}
$posting['baseSalary'] = ['@type' => 'MonetaryAmount', 'currency' => 'USD', 'value' => $value];
$schema_json = json_encode($posting, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);

$page_title       = $job['title'] . ' at ' . $job['employer_name'] . ($where !== '' ? ', ' . $where : '');
$page_description = $job['title'] . ' at ' . $job['employer_name'] . ', ' . $pay_text . '. ' . $policy_text . '. Apply by email, nothing to disclose.';
include 'header.php';
?>

<main class="profile-wrap job-page">
    <a href="/jobs" class="back-link">&larr; All jobs</a>

    <article class="tag-card tag-card--<?php echo $stub; ?> job-detail">
        <div class="tag-card__stub"><span class="tag-card__hole"></span></div>
        <div class="tag-card__face">
            <h1 class="job-detail__title"><?php echo rbh_h((string)$job['title']); ?></h1>
            <p class="job-detail__employer"><?php echo rbh_h((string)$job['employer_name']); ?></p>
            <p class="job-card__pay"><?php echo rbh_h($pay_text); ?></p>

            <dl class="job-facts">
                <?php if ($where !== ''): ?>
                    <div><dt>Where</dt><dd><?php echo rbh_h($where); ?></dd></div>
                <?php endif; ?>
                <div><dt>Kind of work</dt><dd><?php echo rbh_h((string)$job['kind']); ?></dd></div>
                <?php if (trim((string)$job['shift']) !== ''): ?>
                    <div><dt>Shift</dt><dd><?php echo rbh_h((string)$job['shift']); ?></dd></div>
                <?php endif; ?>
            </dl>

            <p class="job-card__policy"><?php echo rbh_h($policy_text); ?></p>
            <?php if (trim((string)$job['policy_note']) !== ''): ?>
                <p class="job-detail__note"><?php echo rbh_h((string)$job['policy_note']); ?></p>
            <?php endif; ?>
        </div>
    </article>

    <section class="job-body">
        <h2>About the job</h2>
        <p><?php echo nl2br(rbh_h((string)$job['description'])); ?></p>
    </section>

    <section class="job-apply" id="apply">
        <h2>Apply</h2>

        <?php if ($applied): ?>
            <p class="form-note form-note--success">Sent. The employer has your info and will reach out directly.</p>
            <p class="job-apply__after">Not heard back in a week? A short follow-up call or text is fine.</p>
            <p><a href="/jobs" class="btn-secondary">See more jobs</a></p>
        <?php else: ?>
            <p class="job-apply__lead">Give a phone number or an email so they can reach you. That's all they need.</p>

            <?php if (isset($errors['form'])): ?>
                <p class="form-note form-note--error" role="alert"><?php echo rbh_h($errors['form']); ?></p>
            <?php endif; ?>

            <form action="/jobs/<?php echo rbh_h((string)$job['slug']); ?>/#apply" method="POST" class="form-card apply-form">
                <?php echo rbh_spam_fields(); ?>

                <div class="form-group<?php echo isset($errors['name']) ? ' has-error' : ''; ?>">
                    <label for="apply-name">Your name</label>
                    <input type="text" id="apply-name" name="name" required maxlength="100" autocomplete="name" value="<?php echo rbh_h($values['name']); ?>">
                    <?php if (isset($errors['name'])): ?><span class="field-error"><?php echo rbh_h($errors['name']); ?></span><?php endif; ?>
                </div>

                <div class="form-group<?php echo isset($errors['phone']) ? ' has-error' : ''; ?>">
                    <label for="apply-phone">Phone</label>
                    <input type="tel" id="apply-phone" name="phone" maxlength="30" autocomplete="tel" value="<?php echo rbh_h($values['phone']); ?>">
                    <?php if (isset($errors['phone'])): ?><span class="field-error"><?php echo rbh_h($errors['phone']); ?></span><?php endif; ?>
                </div>

                <div class="form-group<?php echo isset($errors['email']) ? ' has-error' : ''; ?>">
                    <label for="apply-email">Email</label>
                    <input type="email" id="apply-email" name="email" maxlength="190" autocomplete="email" value="<?php echo rbh_h($values['email']); ?>">
                    <small>Phone or email. One is enough.</small>
                    <?php if (isset($errors['email'])): ?><span class="field-error"><?php echo rbh_h($errors['email']); ?></span><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="apply-message">A line about you (optional)</label>
                    <textarea id="apply-message" name="message" rows="3" maxlength="1000"><?php echo rbh_h($values['message']); ?></textarea>
                </div>

                <p class="apply-form__privacy">Your info goes by email to the employer, with a copy to Recovery Business Hub. Nothing is saved on the site.</p>
                <button type="submit" class="btn-primary btn-submit">Apply</button>
            </form>
        <?php endif; ?>
    </section>
</main>

<?php include 'footer.php'; ?>
