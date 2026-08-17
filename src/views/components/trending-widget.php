<?php
/**
 * trending-widget.php
 *
 * Expected variables injected by the public content composer:
 * $trendingPages – Collection of Page models with counts and relations
 * $siteSlug      – current site slug
 */

if (empty($trendingPages) || $trendingPages->count() === 0) {
    return;
}

$trendingTitle = $trendingTitle ?? 'Trending Now';
?>
<section class="trending-widget" aria-label="<?= htmlspecialchars((string) $trendingTitle, ENT_QUOTES, 'UTF-8') ?>">
    <div class="trending-widget__header">
        <div class="trending-widget__heading">
            <span class="trending-widget__flame" aria-hidden="true">🔥</span>
            <h2 class="trending-widget__title"><?= htmlspecialchars((string) $trendingTitle, ENT_QUOTES, 'UTF-8') ?></h2>
        </div>
        <a href="/<?= htmlspecialchars($siteSlug) ?>/pages"
           class="trending-widget__view-all">
            View all →
        </a>
    </div>

    <div class="trending-widget__grid">
        <?php foreach ($trendingPages as $index => $page): ?>
            <?php
            $url = '/' . htmlspecialchars($siteSlug) . '/' . htmlspecialchars($page->slug);

            // 👉 FIXED: Prioritize custom override values from the database fields
            $displayTitle = !empty($page->listing_title) ? $page->listing_title : $page->title;
            $image = ($page->relationLoaded('listingImage') && $page->listingImage)
                ? (string) ($page->listingImage->url ?? '')
                : (!empty($page->listing_image_url) ? $page->listing_image_url : ($page->metadata->featured_image ?? null));
            $badgeText = !empty($page->listing_label) ? $page->listing_label : ($page->categories->first()?->name ?? null);

            $likeCount = number_format($page->like_count_24h ?? 0);
            $commentCount = number_format($page->comment_count_24h ?? 0);
            $isFirst = $index === 0;
            $isFeatured = $isFirst && $trendingPages->count() > 1;
            ?>
            <article class="trending-card<?= $isFeatured ? ' trending-card--featured' : '' ?>">
                <a href="<?= $url ?>" class="trending-card__image-link" tabindex="-1" aria-hidden="true">
                    <div class="trending-card__image-wrap">
                        <?php if ($image): ?>
                            <img src="<?= htmlspecialchars($image) ?>"
                                 alt="<?= htmlspecialchars($displayTitle) ?>"
                                 loading="<?= $isFirst ? 'eager' : 'lazy' ?>"
                                 class="trending-card__image">
                        <?php else: ?>
                            <div class="trending-card__image trending-card__image--placeholder"></div>
                        <?php endif; ?>

                        <span class="trending-card__rank"><?= $index + 1 ?></span>

                        <?php if ($badgeText): ?>
                            <span class="trending-card__category">
                                <?= htmlspecialchars($badgeText) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </a>

                <div class="trending-card__body">
                    <h3 class="trending-card__title">
                        <a href="<?= $url ?>">
                            <?= htmlspecialchars($displayTitle) ?>
                        </a>
                    </h3>

                    <div class="trending-card__stats">
                        <span class="trending-card__stat" title="Likes in the last 24 h">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                            </svg>
                            <?= $likeCount ?>
                        </span>
                        <span class="trending-card__stat" title="Comments in the last 24 h">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2" aria-hidden="true">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                            </svg>
                            <?= $commentCount ?>
                        </span>

                        <button class="trending-card__gift-btn"
                                onclick="event.preventDefault(); openGiftModal('<?= htmlspecialchars($page->slug) ?>', '<?= htmlspecialchars(addslashes($page->title)) ?>')"
                                title="Gift this article">
                            🎁
                        </button>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<style>
    /* CSS code left completely untouched to safeguard layout properties */
    .trending-widget {
        margin-bottom: 3rem;
        margin-top: 3rem;
    }

    .trending-widget__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.25rem;
    }

    .trending-widget__heading {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .trending-widget__flame {
        font-size: 1.5rem;
        line-height: 1;
    }

    .trending-widget__title {
        font-size: 1.375rem;
        font-weight: 700;
        margin: 0;
        color: var(--text-primary, #111827);
    }

    .trending-widget__view-all {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--primary-color, #2563eb);
        text-decoration: none;
        transition: opacity 0.15s;
    }

    .trending-widget__view-all:hover {
        opacity: 0.75;
    }

    .trending-widget__grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.25rem;
    }

    .trending-card {
        background: #fff;
        border: 1px solid var(--border-color, #e5e7eb);
        border-radius: 0.875rem;
        overflow: hidden;
        transition: box-shadow 0.2s, transform 0.2s;
    }

    .trending-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.09);
    }

    .trending-card--featured {
        grid-column: span 2;
    }

    .trending-card__image-link {
        display: block;
    }

    .trending-card__image-wrap {
        position: relative;
        aspect-ratio: 16 / 9;
        overflow: hidden;
    }

    .trending-card__image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.35s;
    }

    .trending-card:hover .trending-card__image {
        transform: scale(1.04);
    }

    .trending-card__image--placeholder {
        background: var(--bg-light, #f3f4f6);
    }

    .trending-card__rank {
        position: absolute;
        top: 0.625rem;
        left: 0.625rem;
        width: 2rem;
        height: 2rem;
        background: var(--primary-color, #2563eb);
        color: #fff;
        border-radius: 50%;
        font-size: 0.8125rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .trending-card__category {
        position: absolute;
        bottom: 0.625rem;
        left: 0.625rem;
        background: rgba(0, 0, 0, 0.55);
        color: #fff;
        font-size: 0.725rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 0.2rem 0.55rem;
        border-radius: 0.2rem;
    }

    .trending-card__body {
        padding: 1rem;
    }

    .trending-card__title {
        font-size: 0.9375rem;
        font-weight: 600;
        margin: 0 0 0.625rem;
        line-height: 1.45;
    }

    .trending-card__title a {
        color: var(--text-primary, #111827);
        text-decoration: none;
    }

    .trending-card__title a:hover {
        color: var(--primary-color, #2563eb);
    }

    .trending-card__stats {
        display: flex;
        align-items: center;
        gap: 0.875rem;
    }

    .trending-card__stat {
        display: flex;
        align-items: center;
        gap: 0.3rem;
        font-size: 0.8125rem;
        color: var(--text-secondary, #6b7280);
    }

    .trending-card__gift-btn {
        margin-left: auto;
        background: none;
        border: none;
        cursor: pointer;
        font-size: 1rem;
        padding: 0;
        line-height: 1;
        transition: transform 0.15s;
    }

    .trending-card__gift-btn:hover {
        transform: scale(1.25);
    }

    @media (max-width: 640px) {
        .trending-card--featured {
            grid-column: span 1;
        }
    }
</style>