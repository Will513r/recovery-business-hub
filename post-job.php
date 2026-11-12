<?php
// post-job.php: employer form. Free. Every post is checked by hand in admin.php before it goes up.
// Look: art-direction.md (Key tag).
require_once 'config.php';
require_once 'jobs-lib.php';
require_once 'cards-lib.php';

$result = ['ok' => false, 'errors' => [], 'values' => []];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = rbh_handle_post_job($conn);
}
$errors = $result['errors'];
$v = $result['values'] + [
    'employer_name' => '', 'employer_email' => '', 'employer_phone' => '', 'title' => '',
    'area' => '', 'town' => '', 'kind' => '', 'pay_min' => '', 'pay_max' => '', 'pay_unit' => 'hour',
    'shift' => '', 'policy' => '', 'policy_note' => '', 'description' => '', 'agree' => false,
];
$posted = $_SERVER['REQUEST_METHOD'] === 'POST' && $result['ok'];

if (!$posted) {
    rbh_form_stamp('post_job');
}

$policy_help = [
    'no_check'     => "You don't run a background check on this job.",
    'case_by_case' => 'You run one, and you look at what it shows and when it happened before deciding.',
];

$page_title       = 'Post a job';
$page_description = 'Post a job on Recovery Business Hub. Free. Tell people your background check policy up front. Every post is checked by hand before it goes up.';
include 'header.php';
?>

<main class="form-container">
    <h1>Post a job</h1>
    <p>Free. Say up front how you handle background checks, and people who've been burned by pulled offers will know where you stand. Every post is checked by hand before it goes up.</p>

    <?php if ($posted): ?>
        <div class="empty-state">
            <?php echo rbh_blank_tag(); ?>
            <p class="form-note form-note--success">Got it. We check every post by hand.</p>
            <p>You'll get an email when it's live. Applications come straight to the email address you gave.</p>
            <div class="about-footer">
                <a href="/jobs" class="btn-secondary">See the jobs board</a>
                <a href="/post-job" class="btn-secondary">Post another</a>
            </div>
        </div>
    <?php else: ?>

        <?php if (isset($errors['form'])): ?>
            <p class="form-note form-note--error" role="alert"><?php echo rbh_h($errors['form']); ?></p>
        <?php endif; ?>

        <form action="/post-job" method="POST" class="business-form post-job-form">
            <?php echo rbh_spam_fields(); ?>

            <div class="form-group<?php echo isset($errors['employer_name']) ? ' has-error' : ''; ?>">
                <label for="employer_name">Business name</label>
                <input type="text" id="employer_name" name="employer_name" required maxlength="100" autocomplete="organization" value="<?php echo rbh_h($v['employer_name']); ?>">
                <?php if (isset($errors['employer_name'])): ?><span class="field-error"><?php echo rbh_h($errors['employer_name']); ?></span><?php endif; ?>
            </div>

            <div class="form-group<?php echo isset($errors['employer_email']) ? ' has-error' : ''; ?>">
                <label for="employer_email">Email for applications</label>
                <input type="email" id="employer_email" name="employer_email" required maxlength="190" autocomplete="email" value="<?php echo rbh_h($v['employer_email']); ?>">
                <small>Applicants' info lands here. It's not shown on the site.</small>
                <?php if (isset($errors['employer_email'])): ?><span class="field-error"><?php echo rbh_h($errors['employer_email']); ?></span><?php endif; ?>
            </div>

            <div class="form-group<?php echo isset($errors['employer_phone']) ? ' has-error' : ''; ?>">
                <label for="employer_phone">Phone (optional)</label>
                <input type="tel" id="employer_phone" name="employer_phone" maxlength="30" autocomplete="tel" value="<?php echo rbh_h($v['employer_phone']); ?>">
                <small>For us only, in case we need to check something. Not shown on the site.</small>
            </div>

            <div class="form-group<?php echo isset($errors['title']) ? ' has-error' : ''; ?>">
                <label for="title">Job title</label>
                <input type="text" id="title" name="title" required maxlength="100" placeholder="e.g. Line cook" value="<?php echo rbh_h($v['title']); ?>">
                <?php if (isset($errors['title'])): ?><span class="field-error"><?php echo rbh_h($errors['title']); ?></span><?php endif; ?>
            </div>

            <div class="form-row form-row--even">
                <div class="form-group<?php echo isset($errors['area']) ? ' has-error' : ''; ?>">
                    <label for="area">Area</label>
                    <select id="area" name="area" required>
                        <option value="">Pick one</option>
                        <?php foreach (RBH_AREAS as $key => $label): ?>
                            <option value="<?php echo rbh_h($key); ?>"<?php echo $v['area'] === $key ? ' selected' : ''; ?>><?php echo rbh_h($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['area'])): ?><span class="field-error"><?php echo rbh_h($errors['area']); ?></span><?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="town">Town (optional)</label>
                    <input type="text" id="town" name="town" maxlength="100" placeholder="e.g. Hamilton" value="<?php echo rbh_h($v['town']); ?>">
                </div>
            </div>

            <div class="form-group<?php echo isset($errors['kind']) ? ' has-error' : ''; ?>">
                <label for="kind">Kind of work</label>
                <select id="kind" name="kind" required>
                    <option value="">Pick one</option>
                    <?php foreach (RBH_JOB_KINDS as $k): ?>
                        <option value="<?php echo rbh_h($k); ?>"<?php echo $v['kind'] === $k ? ' selected' : ''; ?>><?php echo rbh_h($k); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['kind'])): ?><span class="field-error"><?php echo rbh_h($errors['kind']); ?></span><?php endif; ?>
            </div>

            <div class="form-row form-row--even form-row--pay">
                <div class="form-group<?php echo isset($errors['pay_min']) ? ' has-error' : ''; ?>">
                    <label for="pay_min">Starting pay</label>
                    <input type="number" id="pay_min" name="pay_min" required min="0" step="0.01" inputmode="decimal" value="<?php echo rbh_h((string)$v['pay_min']); ?>">
                    <?php if (isset($errors['pay_min'])): ?><span class="field-error"><?php echo rbh_h($errors['pay_min']); ?></span><?php endif; ?>
                </div>
                <div class="form-group<?php echo isset($errors['pay_max']) ? ' has-error' : ''; ?>">
                    <label for="pay_max">Top pay (optional)</label>
                    <input type="number" id="pay_max" name="pay_max" min="0" step="0.01" inputmode="decimal" value="<?php echo rbh_h((string)$v['pay_max']); ?>">
                    <?php if (isset($errors['pay_max'])): ?><span class="field-error"><?php echo rbh_h($errors['pay_max']); ?></span><?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="pay_unit">Paid per</label>
                    <select id="pay_unit" name="pay_unit">
                        <option value="hour"<?php echo $v['pay_unit'] !== 'year' ? ' selected' : ''; ?>>Hour</option>
                        <option value="year"<?php echo $v['pay_unit'] === 'year' ? ' selected' : ''; ?>>Year</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="shift">Shift (optional)</label>
                <input type="text" id="shift" name="shift" maxlength="100" placeholder="e.g. Days, Mon to Fri" value="<?php echo rbh_h($v['shift']); ?>">
            </div>

            <div class="form-group<?php echo isset($errors['policy']) ? ' has-error' : ''; ?>">
                <span class="form-label" id="policy-label">Your background check policy</span>
                <div class="category-checkbox-grid" role="radiogroup" aria-labelledby="policy-label">
                    <?php foreach (RBH_POLICIES as $key => $label): ?>
                        <label class="category-checkbox-label policy-option">
                            <input type="radio" name="policy" value="<?php echo rbh_h($key); ?>" required<?php echo $v['policy'] === $key ? ' checked' : ''; ?>>
                            <span><strong><?php echo rbh_h($label); ?></strong><br><?php echo rbh_h($policy_help[$key] ?? ''); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php if (isset($errors['policy'])): ?><span class="field-error"><?php echo rbh_h($errors['policy']); ?></span><?php endif; ?>
            </div>

            <div class="form-group">
                <label for="policy_note">Anything they should know (optional)</label>
                <input type="text" id="policy_note" name="policy_note" maxlength="300" placeholder="e.g. We check after the offer and talk it through first" value="<?php echo rbh_h($v['policy_note']); ?>">
                <small>Shows on the job under your policy. Be straight about when the check happens.</small>
            </div>

            <div class="form-group<?php echo isset($errors['description']) ? ' has-error' : ''; ?>">
                <label for="description">About the job</label>
                <textarea id="description" name="description" rows="6" required minlength="30" maxlength="3000"><?php echo rbh_h($v['description']); ?></textarea>
                <small>What the work is, what a good week looks like, how to get started.</small>
                <?php if (isset($errors['description'])): ?><span class="field-error"><?php echo rbh_h($errors['description']); ?></span><?php endif; ?>
            </div>

            <div class="form-group<?php echo isset($errors['agree']) ? ' has-error' : ''; ?>">
                <label class="category-checkbox-label">
                    <input type="checkbox" name="agree" value="1"<?php echo $v['agree'] ? ' checked' : ''; ?>>
                    <span>I'll consider applicants as stated in my policy.</span>
                </label>
                <?php if (isset($errors['agree'])): ?><span class="field-error"><?php echo rbh_h($errors['agree']); ?></span><?php endif; ?>
            </div>

            <button type="submit" class="btn-primary btn-submit">Post the job</button>
        </form>
    <?php endif; ?>
</main>

<?php include 'footer.php'; ?>
