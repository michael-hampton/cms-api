<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tag->name) ?> - Tag</title>
    <meta name="description" content="<?= htmlspecialchars($tag->description ?? "Pages tagged with {$tag->name}") ?>">
    @css('landing-page.css')
</head>
<body>

@include('header', ['menu' => $menu, 'title' => $tag->name])

<header class="header">
    <div class="header-content">
        <h1>
            <span class="tag-hash">#</span><?= htmlspecialchars($tag->name) ?>
        </h1> <?php if ($tag->description): ?>
            <p><?= nl2br(htmlspecialchars($tag->description)) ?></p>
        <?php endif; ?>
        <div class="tag-stats-header">
            <div class="stat-item-header">
                <span class="stat-value-header"><?= isset($pagination) ? $pagination['total'] : count($pages) ?></span>
                <span class="stat-label-header"><?= (isset($pagination) ? $pagination['total'] : count($pages)) === 1 ? 'Article' : 'Articles' ?></span>
            </div>
        </div>
    </div>
</header>
<div class="container">
    <?php if ($pages && count($pages) > 0): ?>
        @include('partials.filters', ['pages' => $pages])
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
                                    <div class="page-authors">
            <span class="page-author">
                By
                <?php
                $authorNames = array_map(function ($author) {
                    return '<a href="/' . \App\Framework\Support\SiteContext::slug() . '/authors/' . $author['slug'] . '">' . htmlspecialchars($author['name']) . '</a>';
                }, $page->authors->take(3)->toArray());

                if (count($page->authors) > 3) {
                    echo implode(', ', $authorNames) . ' +' . (count($page->authors) - 3);
                } else {
                    echo implode(', ', $authorNames);
                }
                ?>
            </span>
                                    </div>
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
                                <?php foreach ($page->tags->take(3) as $tag): ?>
                                    <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/tags/<?= htmlspecialchars($tag->slug) ?>"
                                       class="tag-mini">
                                        #<?= htmlspecialchars($tag->name) ?>
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
            <h2>No Articles Found</h2>
            <p>No articles with this tag yet.</p>
        </div>
    <?php endif; ?>
</div>
</body>
</html>