<?php
$pageType = (string) $page->page_type;
$pageTypeLabel = match ($pageType) {
    'article' => 'Article',
    'content' => 'Story',
    default => null,
};
$subtitle = trim((string) ($page->subtitle ?? ''));
$publishedAt = $page->published_at ?? null;
$publishedDate = null;
$publishedDateIso = null;
$reviewData = $reviewData ?? null;

if ($publishedAt) {
    if ($publishedAt instanceof \DateTimeInterface) {
        $publishedDate = $publishedAt->format('j F Y');
        $publishedDateIso = $publishedAt->format('Y-m-d');
    } else {
        $publishedTimestamp = strtotime((string) $publishedAt);

        if ($publishedTimestamp !== false) {
            $publishedDate = date('j F Y', $publishedTimestamp);
            $publishedDateIso = date('Y-m-d', $publishedTimestamp);
        }
    }
}
?>

<header class="public-page-heading">
    <div class="public-page-heading__content">
        <?php if ($pageTypeLabel): ?>
            <span class="public-page-heading__eyebrow"><?= htmlspecialchars($pageTypeLabel, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>

        <h1 class="public-page-heading__title"><?= htmlspecialchars((string) $page->title, ENT_QUOTES, 'UTF-8') ?></h1>

        <?php if ($subtitle !== ''): ?>
            <p class="public-page-heading__subtitle"><?= htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <?php if ($publishedDate !== null): ?>
            <p class="public-page-heading__published">
                Published
                <time datetime="<?= htmlspecialchars($publishedDateIso, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($publishedDate, ENT_QUOTES, 'UTF-8') ?>
                </time>
            </p>
        <?php endif; ?>

        <?php if ($reviewData): ?>
            <div class="public-page-heading__rating" aria-label="Rated <?= htmlspecialchars(number_format((float) $reviewData['rating'], 1), ENT_QUOTES, 'UTF-8') ?> out of <?= (int) $reviewData['maxRating'] ?>">
                <?php for ($i = 1; $i <= $reviewData['maxRating']; $i++): ?>
                    <?php $fill = max(0, min(1, $reviewData['rating'] - ($i - 1))) * 100; ?>
                    <span class="star" style="--fill: <?= (float) $fill ?>%"></span>
                <?php endfor; ?>
                <span class="public-page-heading__rating-value"><?= htmlspecialchars(number_format((float) $reviewData['rating'], 1), ENT_QUOTES, 'UTF-8') ?>/<?= (int) $reviewData['maxRating'] ?></span>
            </div>
        <?php endif; ?>
    </div>
</header>

<style>
    .public-page-heading {
        margin: 0 0 1.5rem;
        padding: clamp(1.5rem, 4vw, 2.5rem);
        border: 1px solid #e5e7eb;
        border-radius: 18px;
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        box-shadow: 0 16px 40px rgba(15, 23, 42, .06);
        overflow: hidden;
        position: relative;
    }

    .public-page-heading::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, #2563eb, #7c3aed);
    }

    .public-page-heading__content {
        min-width: 0;
    }

    .public-page-heading__eyebrow {
        display: inline-flex;
        margin-bottom: .75rem;
        color: #2563eb;
        font-size: .75rem;
        font-weight: 800;
        letter-spacing: .14em;
        text-transform: uppercase;
    }

    .public-page-heading__title {
        margin: 0;
        color: #0f172a;
        font-size: clamp(2rem, 5vw, 4rem);
        font-weight: 800;
        letter-spacing: -.035em;
        line-height: 1.05;
        text-wrap: balance;
    }

    .public-page-heading__subtitle {
        max-width: 52rem;
        margin: 1rem 0 0;
        color: #64748b;
        font-size: clamp(1rem, 2vw, 1.2rem);
        line-height: 1.65;
    }

    .public-page-heading__published {
        margin: 1rem 0 0;
        color: #64748b;
        font-size: .9rem;
        font-weight: 600;
    }

    .public-page-heading__published time {
        color: #334155;
    }

    .public-page-heading__rating {
        display: flex;
        align-items: center;
        gap: .35rem;
        margin: .75rem 0 0;
    }

    .star {
        --fill: 0%;
        position: relative;
        display: inline-block;
        width: 1.1rem;
        height: 1.1rem;
        font-size: 1.1rem;
        line-height: 1;
    }

    .star::before,
    .star::after {
        content: '★';
        position: absolute;
        inset: 0;
    }

    .star::before {
        color: #e2e8f0;
    }

    .star::after {
        color: #f59e0b;
        width: var(--fill);
        overflow: hidden;
        white-space: nowrap;
    }

    .public-page-heading__rating-value {
        font-size: .85rem;
        font-weight: 700;
        color: #334155;
    }

    .public-page-heading__review {
        margin-top: 1.25rem;
        padding-top: 1.25rem;
        border-top: 1px solid #e5e7eb;
    }

    .public-page-heading__verdict {
        color: #334155;
        font-size: 1rem;
        line-height: 1.6;
        margin: 0 0 1rem;
    }

    .public-page-heading__review-columns {
        display: grid;
        gap: 1.5rem;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    }

    .public-page-heading__pros h3,
    .public-page-heading__cons h3 {
        font-size: .8rem;
        text-transform: uppercase;
        letter-spacing: .06em;
        margin: 0 0 .5rem;
        color: #64748b;
    }

    .public-page-heading__pros ul,
    .public-page-heading__cons ul {
        margin: 0;
        padding-left: 1.1rem;
        color: #334155;
        font-size: .92rem;
        line-height: 1.55;
    }
</style>
