<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews - Expert Recommendations & Analysis</title>
    @css('landing-page.css')
</head>
<body>
@include('header', ['menu' => $menu, 'hasTitle' => false])

<header>
    <div class="header-content">
        <h1>Expert Reviews</h1>
        <p>In-depth analysis and honest recommendations from our team of experts</p>
    </div>
</header>

<div class="container">
    <?php if ($pages && count($pages) > 0): ?>
        @include('partials.filters', ['pages' => $pages])

        <div class="reviews-grid">
            <?php foreach ($pages as $page): ?>
                <article class="review-card">
                    <?php if ($page->featured_image): ?>
                        <div class="review-card-image">
                            <img src="<?= htmlspecialchars($page->featured_image) ?>"
                                 alt="<?= htmlspecialchars($page->title) ?>">
                        </div>
                    <?php else: ?>
                        <div class="review-card-image">📋</div>
                    <?php endif; ?>

                    <div class="review-card-content">
                        <div class="review-meta">
                            <?php if ($page->categories && count($page->categories) > 0): ?>
                                <?php foreach ($page->categories->take(2) as $category): ?>
                                    <span class="review-badge"><?= htmlspecialchars($category->name) ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <h2 class="review-card-title">
                            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/<?= htmlspecialchars($page->slug) ?>">
                                <?= htmlspecialchars($page->title) ?>
                            </a>
                        </h2>

                        <?php if ($page->excerpt): ?>
                            <p class="review-excerpt">
                                <?= htmlspecialchars(substr($page->excerpt, 0, 150)) ?>
                                <?= strlen($page->excerpt) > 150 ? '...' : '' ?>
                            </p>
                        <?php endif; ?>

                        <div class="review-footer">
                            <div class="review-author-date">
                                <?php if ($page->authors && count($page->authors) > 0): ?>
                                    <span class="review-author">
                                        By <?= htmlspecialchars($page->authors->first()->name) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($page->published_at): ?>
                                    <span class="review-date">
                                        <?= date('M j, Y', strtotime($page->published_at)) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/<?= htmlspecialchars($page->slug) ?>"
                               class="review-link">
                                Read Review
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
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
            <h2>No Reviews Yet</h2>
            <p>Check back soon for expert reviews and recommendations.</p>
        </div>
    <?php endif; ?>
</div>
</body>
</html>