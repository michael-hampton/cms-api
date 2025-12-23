<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tag->name) ?> - Tag</title>
    <meta name="description" content="<?= htmlspecialchars($tag->description ?? "Pages tagged with {$tag->name}") ?>">
    @css('landing-page.css')
    @js('base.js')
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
                @include('components/page-card', ['page' => $page, 'showToolbar' => true])
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

@include('components/newsletter-modal')
@include('components/comment-modal')
</body>
</html>