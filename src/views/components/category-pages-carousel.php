<?php
/**
 * category-pages-carousel.php
 *
 * Displays categories with their pages.
 * Layout can be toggled between "carousel" (default) and "grid" via radio pills.
 *
 * Expected variables:
 *   $categoriesWithPages  – array keyed by category id: ['category' => Category, 'pages' => Collection]
 *   $siteSlug             – string  e.g. "estate"
 */

/** @var array $categoriesWithPages */
/** @var string $siteSlug */

if (empty($categoriesWithPages)) {
    return;
}

$widgetId = 'cat-pages-' . substr(md5(uniqid()), 0, 6);
$siteSlug = \App\Framework\Support\SiteContext::slug();
?>
<section class="cat-pages-widget" id="<?= $widgetId ?>" aria-label="Category pages">

    <div class="cat-pages-widget__toolbar">
        <h2 class="cat-pages-widget__heading">Browse by Category</h2>

        <div class="cat-pages-widget__layout-pills" role="group" aria-label="Layout">
            <label class="layout-pill layout-pill--active">
                <input type="radio" name="<?= $widgetId ?>-layout" value="carousel" checked hidden>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     aria-hidden="true">
                    <rect x="2" y="7" width="20" height="14" rx="2"/>
                    <polyline points="16 2 12 7 8 2"/>
                </svg>
                Carousel
            </label>
            <label class="layout-pill">
                <input type="radio" name="<?= $widgetId ?>-layout" value="grid" hidden>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     aria-hidden="true">
                    <rect x="3" y="3" width="7" height="7"/>
                    <rect x="14" y="3" width="7" height="7"/>
                    <rect x="3" y="14" width="7" height="7"/>
                    <rect x="14" y="14" width="7" height="7"/>
                </svg>
                Grid
            </label>
        </div>
    </div>

    <?php foreach ($categoriesWithPages as $block): ?>
        <?php
        $category = $block['category'];
        $pages = $block['pages'];
        $catId = 'cat-' . $category->id . '-' . substr(md5($widgetId), 0, 4);
        ?>
        <div class="cat-block" data-cat-block>
            <div class="cat-block__header">
                <h3 class="cat-block__title">
                    <a href="/<?= htmlspecialchars($siteSlug) ?>/category/<?= htmlspecialchars($category->slug) ?>">
                        <?php if (!empty($category->icon)): ?>
                            <span aria-hidden="true"><?= $category->icon ?></span>
                        <?php endif; ?>
                        <?= htmlspecialchars($category->name) ?>
                    </a>
                </h3>
                <?php if (!empty($category->description)): ?>
                    <p class="cat-block__desc"><?= htmlspecialchars($category->description) ?></p>
                <?php endif; ?>
                <a href="/<?= htmlspecialchars($siteSlug) ?>/category/<?= htmlspecialchars($category->slug) ?>"
                   class="cat-block__view-all">View all →</a>
            </div>

            <!-- Carousel layout -->
            <div class="cat-block__carousel layout-view layout-view--carousel" id="<?= $catId ?>">
                <button class="carousel-nav carousel-nav--prev"
                        onclick="catCarouselScroll(this,'prev')" aria-label="Previous">‹
                </button>
                <button class="carousel-nav carousel-nav--next"
                        onclick="catCarouselScroll(this,'next')" aria-label="Next">›
                </button>

                <div class="carousel-track" data-track>
                    <?php foreach ($pages as $page): ?>
                        <?= catPageCard($page, $siteSlug) ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Grid layout -->
            <div class="cat-block__grid layout-view layout-view--grid" hidden>
                <?php foreach ($pages as $page): ?>
                    <?= catPageCard($page, $siteSlug) ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</section>

<?php
/**
 * Render a single page card (shared between carousel and grid views).
 */
function catPageCard($page, string $siteSlug): string
{
    $url = '/' . htmlspecialchars($siteSlug) . '/' . htmlspecialchars($page->slug);
    $image = $page->metadata->featured_image ?? null;
    $excerpt = $page->metadata->excerpt ?? $page->meta_description ?? '';
    $excerpt = htmlspecialchars(mb_strimwidth($excerpt, 0, 120, '…'));

    $html = '<article class="cat-page-card">';
    $html .= '<a href="' . $url . '" class="cat-page-card__image-link" tabindex="-1" aria-hidden="true">';
    $html .= '<div class="cat-page-card__image-wrap">';
    if ($image) {
        $html .= '<img src="' . htmlspecialchars($image) . '" alt="' . htmlspecialchars($page->title) . '" loading="lazy" class="cat-page-card__image">';
    } else {
        $html .= '<div class="cat-page-card__image cat-page-card__image--placeholder"></div>';
    }
    $html .= '</div></a>';
    $html .= '<div class="cat-page-card__body">';
    $html .= '<h4 class="cat-page-card__title"><a href="' . $url . '">' . htmlspecialchars($page->title) . '</a></h4>';
    if ($excerpt) {
        $html .= '<p class="cat-page-card__excerpt">' . $excerpt . '</p>';
    }
    $html .= '</div></article>';

    return $html;
}

?>

<style>
    /* ── Category-pages widget ──────────────────────────────── */
    .cat-pages-widget {
        margin-bottom: 3rem;
    }

    .cat-pages-widget__toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-bottom: 2rem;
    }

    .cat-pages-widget__heading {
        font-size: 1.375rem;
        font-weight: 700;
        margin: 0;
        color: var(--text-primary, #111827);
    }

    /* Layout pills */
    .cat-pages-widget__layout-pills {
        display: flex;
        gap: 0.375rem;
    }

    .layout-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.375rem 0.875rem;
        border-radius: 2rem;
        border: 1.5px solid var(--border-color, #e5e7eb);
        font-size: 0.8125rem;
        font-weight: 600;
        cursor: pointer;
        color: var(--text-secondary, #6b7280);
        background: #fff;
        transition: all 0.15s;
        user-select: none;
    }

    .layout-pill:hover {
        border-color: var(--primary-color, #2563eb);
        color: var(--primary-color, #2563eb);
    }

    .layout-pill--active,
    .layout-pill:has(input:checked) {
        background: var(--primary-color, #2563eb);
        border-color: var(--primary-color, #2563eb);
        color: #fff;
    }

    /* Category block */
    .cat-block {
        margin-bottom: 3.5rem;
    }

    .cat-block__header {
        margin-bottom: 1.125rem;
        padding-bottom: 0.875rem;
        border-bottom: 2px solid var(--border-color, #e5e7eb);
    }

    .cat-block__title {
        font-size: 1.25rem;
        font-weight: 700;
        margin: 0 0 0.25rem;
    }

    .cat-block__title a {
        color: inherit;
        text-decoration: none;
    }

    .cat-block__title a:hover {
        color: var(--primary-color, #2563eb);
    }

    .cat-block__desc {
        font-size: 0.875rem;
        color: var(--text-secondary, #6b7280);
        margin: 0.25rem 0 0.5rem;
    }

    .cat-block__view-all {
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--primary-color, #2563eb);
        text-decoration: none;
    }

    /* Carousel */
    .cat-block__carousel {
        position: relative;
    }

    .carousel-track {
        display: flex;
        gap: 1.125rem;
        overflow-x: auto;
        scroll-snap-type: x mandatory;
        scrollbar-width: none;
        -ms-overflow-style: none;
        padding-bottom: 0.25rem;
    }

    .carousel-track::-webkit-scrollbar {
        display: none;
    }

    .carousel-nav {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        z-index: 2;
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 50%;
        border: 1.5px solid var(--border-color, #e5e7eb);
        background: #fff;
        font-size: 1.25rem;
        line-height: 1;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-primary, #111827);
        transition: all 0.15s;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }

    .carousel-nav--prev {
        left: -1.125rem;
    }

    .carousel-nav--next {
        right: -1.125rem;
    }

    .carousel-nav:hover {
        border-color: var(--primary-color, #2563eb);
        color: var(--primary-color, #2563eb);
    }

    /* Grid */
    .cat-block__grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 1.25rem;
    }

    /* Page card (shared) */
    .cat-page-card {
        background: #fff;
        border: 1px solid var(--border-color, #e5e7eb);
        border-radius: 0.75rem;
        overflow: hidden;
        flex: 0 0 260px;
        scroll-snap-align: start;
        transition: box-shadow 0.2s, transform 0.2s;
    }

    .cat-page-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
    }

    .cat-page-card__image-wrap {
        aspect-ratio: 16 / 9;
        overflow: hidden;
    }

    .cat-page-card__image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s;
    }

    .cat-page-card:hover .cat-page-card__image {
        transform: scale(1.04);
    }

    .cat-page-card__image--placeholder {
        background: var(--bg-light, #f3f4f6);
    }

    .cat-page-card__body {
        padding: 0.875rem;
    }

    .cat-page-card__title {
        font-size: 0.9375rem;
        font-weight: 600;
        margin: 0 0 0.375rem;
        line-height: 1.45;
    }

    .cat-page-card__title a {
        color: var(--text-primary, #111827);
        text-decoration: none;
    }

    .cat-page-card__title a:hover {
        color: var(--primary-color, #2563eb);
    }

    .cat-page-card__excerpt {
        font-size: 0.8125rem;
        color: var(--text-secondary, #6b7280);
        margin: 0;
        line-height: 1.5;
    }

    /* Hidden layout view */
    .layout-view[hidden] {
        display: none !important;
    }
</style>

<script>
    (function () {
        // Radio-pill layout toggle
        document.querySelectorAll('.cat-pages-widget__layout-pills').forEach(function (group) {
            group.addEventListener('change', function (e) {
                var pill = e.target.closest('.layout-pill');
                var widget = pill.closest('.cat-pages-widget');
                var layout = e.target.value;

                // Update pill active states
                group.querySelectorAll('.layout-pill').forEach(function (p) {
                    p.classList.toggle('layout-pill--active', p.contains(e.target));
                });

                // Toggle layout views inside every cat-block
                widget.querySelectorAll('[data-cat-block]').forEach(function (block) {
                    block.querySelectorAll('.layout-view').forEach(function (view) {
                        var isMatch = view.classList.contains('layout-view--' + layout);
                        if (isMatch) {
                            view.removeAttribute('hidden');
                        } else {
                            view.setAttribute('hidden', '');
                        }
                    });
                });
            });
        });
    })();

    function catCarouselScroll(btn, direction) {
        var track = btn.closest('.cat-block__carousel').querySelector('[data-track]');
        var cardWidth = track.querySelector('.cat-page-card')?.offsetWidth ?? 280;
        var gap = 18;
        var delta = (cardWidth + gap) * 2;
        track.scrollBy({left: direction === 'prev' ? -delta : delta, behavior: 'smooth'});
    }
</script>