<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tag->name) ?> - Brand</title>
    <meta name="description" content="<?= htmlspecialchars($tag->description ?? "Browse {$tag->name} content") ?>">
    @css('landing-page.css')
</head>
<body>

@include('header', ['menu' => $menu, 'title' => $tag->name])

<!-- Header -->
<header class="header">
    <div class="header-content">
        <?php if ($tag->icon): ?>
            <span class="tag-hash" style="font-size: 3rem;"><?= $tag->icon ?></span>
        <?php else: ?>
            <span class="tag-hash">#</span>
        <?php endif; ?>
        <h1><?= htmlspecialchars($tag->name) ?></h1>

        <?php if ($tag->description): ?>
            <p><?= htmlspecialchars($tag->description) ?></p>
        <?php endif; ?>

        <div class="tag-stats-header">
            <div class="stat-item-header">
                <span class="stat-value-header"><?= $pagination['total'] ?? count($pages) ?></span>
                <span class="stat-label-header"><?= ($pagination['total'] ?? count($pages)) === 1 ? 'Article' : 'Articles' ?></span>
            </div>
            <?php if (!empty($tag->categories)): ?>
                <div class="stat-item-header">
                    <span class="stat-value-header"><?= count($tag->categories) ?></span>
                    <span class="stat-label-header"><?= count($tag->categories) === 1 ? 'Category' : 'Categories' ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- Main Content -->
<div class="container">
    <?php if (!empty($tag->categories)): ?>
        <!-- Related Categories -->
        <section class="subcategories-section">
            <h2 class="subcategories-title">Related Categories</h2>
            <div class="subcategories-grid">
                <?php foreach ($tag->categories as $category): ?>
                    <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/category/<?= htmlspecialchars($category->slug) ?>"
                       class="subcategory-card">
                        <?php if ($category->icon): ?>
                            <span class="subcategory-icon"><?= $category->icon ?></span>
                        <?php endif; ?>
                        <h3><?= htmlspecialchars($category->name) ?></h3>
                        <?php if ($category->description): ?>
                            <p><?= htmlspecialchars(substr($category->description, 0, 100)) ?><?= strlen($category->description) > 100 ? '...' : '' ?></p>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($pages && count($pages) > 0): ?>
        <!-- Filters -->
        @include('partials.filters', ['pages' => $pages])

        <!-- Pages Grid -->
        <div class="pages-grid">
            <?php foreach ($pages as $page): ?>
                <article class="page-card">
                    <?php
                    $imageUrl = '';
                    $cropOverrides = $page->crop_overrides ?? null;
                    $resolvedImages = $page->resolved_images ?? null;
                    $useAsHero = ($page->listing_use_as_hero === true || $page->listing_use_as_hero === 1);

                    if ($useAsHero) {
                        if (isset($cropOverrides['hero-banner']['imageUrl'])) {
                            $imageUrl = $cropOverrides['hero-banner']['imageUrl'];
                        } elseif (isset($resolvedImages['hero-banner']['image_url'])) {
                            $imageUrl = $resolvedImages['hero-banner']['image_url'];
                        }
                    } else {
                        if (isset($cropOverrides['listing-card']['imageUrl'])) {
                            $imageUrl = $cropOverrides['listing-card']['imageUrl'];
                        } elseif (isset($resolvedImages['listing-card']['image_url'])) {
                            $imageUrl = $resolvedImages['listing-card']['image_url'];
                        }
                    }

                    if (!$imageUrl && isset($page->image->url)) {
                        $imageUrl = $page->image->url;
                    }
                    ?>

                    <?php if ($imageUrl): ?>
                        <div class="page-card-image">
                            <img src="<?= htmlspecialchars($imageUrl) ?>"
                                 alt="<?= htmlspecialchars($page->title) ?>">
                        </div>
                    <?php else: ?>
                        <div class="page-card-image">📄</div>
                    <?php endif; ?>

                    <div class="page-card-content">
                        <?php if ($page->categories && count($page->categories) > 0): ?>
                            <div class="page-meta">
                                <?php foreach ($page->categories->take(2) as $category): ?>
                                    <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/category/<?= htmlspecialchars($category->slug) ?>"
                                       class="tags-badge">
                                        <?= htmlspecialchars($category->name) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <h3 class="page-card-title">
                            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?><?= htmlspecialchars($page->getUrlAttribute()) ?>">
                                <?= htmlspecialchars($page->title) ?>
                            </a>
                        </h3>

                        <?php if ($page->meta_description): ?>
                            <p class="page-excerpt">
                                <?= htmlspecialchars(substr($page->meta_description, 0, 150)) ?>
                                <?= strlen($page->meta_description) > 150 ? '...' : '' ?>
                            </p>
                        <?php endif; ?>

                        <div class="page-footer">
                            <div class="page-author-date">
                                <?php if ($page->authors && count($page->authors) > 0): ?>
                                    <span class="page-author">
                                        By <?= htmlspecialchars($page->authors->first()->name) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($page->published_at): ?>
                                    <span class="page-date">
                                        <?= $page->published_at->format('M j, Y') ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?><?= htmlspecialchars($page->getUrlAttribute()) ?>"
                               class="page-link">
                                Read More
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>

                        <?php if ($page->tags && count($page->tags) > 0): ?>
                            <div class="page-tags">
                                <?php foreach ($page->tags->take(3) as $pageTag): ?>
                                    <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/tags/<?= htmlspecialchars($pageTag->slug) ?>"
                                       class="tag-mini">
                                        #<?= htmlspecialchars($pageTag->name) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        @include('partials.pagination', ['pages' => $pages])
    <?php else: ?>
        <div class="empty-state">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <h2>No Articles Yet</h2>
            <p>No articles with this brand yet.</p>
        </div>
    <?php endif; ?>
</div>

</body>
</html>