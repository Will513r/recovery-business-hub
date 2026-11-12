<?php
// cards-lib.php: the two key tag cards, shared by every page that lists things.
// Markup and classes: style.css section 6. Needs jobs-lib.php loaded first for the job card.
// Pure output helpers. No queries here.

function rbh_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// A business card. $hiring is the array from rbh_hiring_business_ids() (ids as keys).
// $b may carry avg_rating and review_count when the query joined reviews.
function rbh_business_card(array $b, array $hiring = []): string
{
    $name = (string)($b['name'] ?? '');
    $words = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = '';
    foreach ($words as $w) {
        $initials .= mb_substr($w, 0, 1);
    }
    $initials = mb_substr($initials, 0, 2);

    $cats = json_decode((string)($b['category'] ?? ''), true);
    if (!is_array($cats) || empty($cats)) {
        $cats = !empty($b['category']) ? [(string)$b['category']] : [];
    }

    $place = [];
    if (!empty($b['city'])) {
        $place[] = $b['city'];
    }
    if (!empty($b['state'])) {
        $place[] = $b['state'];
    }

    $is_hiring = isset($hiring[(int)($b['id'] ?? 0)]);

    ob_start();
    ?>
    <article class="tag-card tag-card--ink">
        <div class="tag-card__stub"><span class="tag-card__hole"></span></div>
        <div class="tag-card__face">
            <div class="tag-card__top">
                <?php if (!empty($b['logo'])): ?>
                    <img src="/<?php echo rbh_h(ltrim($b['logo'], '/')); ?>" alt="Logo for <?php echo rbh_h($name); ?>" class="biz-logo" loading="lazy">
                <?php else: ?>
                    <div class="logo-placeholder" aria-hidden="true"><?php echo rbh_h($initials); ?></div>
                <?php endif; ?>
                <div>
                    <h3 class="tag-card__title"><a class="tag-card__link" href="/business/<?php echo rbh_h((string)$b['slug']); ?>/"><?php echo rbh_h($name); ?></a></h3>
                    <?php if (!empty($b['is_verified'])): ?>
                        <span class="badge-verified">Verified</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($place): ?>
                <p class="tag-card__meta"><?php echo rbh_h(implode(', ', $place)); ?></p>
            <?php endif; ?>

            <?php if ($cats || $is_hiring): ?>
                <div class="chip-row">
                    <?php if ($is_hiring): ?><span class="flag-hiring">Hiring</span><?php endif; ?>
                    <?php foreach ($cats as $c): ?>
                        <span class="category-tag"><?php echo rbh_h((string)$c); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($b['description'])): ?>
                <p class="tag-card__text"><?php echo rbh_h(mb_strimwidth(strip_tags((string)$b['description']), 0, 140, '...')); ?></p>
            <?php endif; ?>
        </div>
    </article>
    <?php
    return (string)ob_get_clean();
}

// A job card. Stub color follows the employer's policy: green = no check, blue = case by case.
function rbh_job_card(array $job): string
{
    $policy = (string)($job['policy'] ?? '');
    $stub = $policy === 'no_check' ? 'green' : 'blue';
    $area = rbh_area_label((string)($job['area'] ?? ''));
    $town = trim((string)($job['town'] ?? ''));
    $where = $town !== '' ? $town . ', ' . $area : $area;
    $policy_label = rbh_policy_label($policy);

    ob_start();
    ?>
    <article class="tag-card tag-card--<?php echo $stub; ?> job-card">
        <div class="tag-card__stub"><span class="tag-card__hole"></span></div>
        <div class="tag-card__face">
            <h3 class="job-card__title"><a class="tag-card__link" href="/jobs/<?php echo rbh_h((string)$job['slug']); ?>/"><?php echo rbh_h((string)$job['title']); ?></a></h3>
            <p class="job-card__pay"><?php echo rbh_h(rbh_pay_label($job)); ?></p>
            <?php if (!empty($job['employer_name'])): ?>
                <p class="job-card__employer"><?php echo rbh_h((string)$job['employer_name']); ?></p>
            <?php endif; ?>
            <?php if ($where !== ''): ?>
                <p class="job-card__area"><?php echo rbh_h($where); ?></p>
            <?php endif; ?>
            <?php if ($policy_label !== ''): ?>
                <p class="job-card__policy"><?php echo rbh_h($policy_label); ?></p>
            <?php endif; ?>
        </div>
    </article>
    <?php
    return (string)ob_get_clean();
}

// A lone blank tag: empty states and the 404.
function rbh_blank_tag(): string
{
    return '<div class="tag-card tag-card--ink tag-card--blank" aria-hidden="true">'
        . '<div class="tag-card__stub"><span class="tag-card__hole"></span></div>'
        . '<div class="tag-card__face"><span class="tag-blank-line"></span><span class="tag-blank-line"></span></div>'
        . '</div>';
}
