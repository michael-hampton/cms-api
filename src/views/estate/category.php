<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($category->name) ?> - Category</title>
    <meta name="description" content="<?= htmlspecialchars($category->description ?? "Browse {$category->name} content") ?>">
    @css('landing-page.css')
    @js('base.js')
</head>
<body>

@include('header', ['menu' => $menu, 'title' => $category->name])

<!-- Header -->
<header class="header">
    <div class="header-content">
        <h1>
            <?php if ($category->icon): ?>
                <span class="category-icon"><?= $category->icon ?></span>
            <?php endif; ?>
            <?= htmlspecialchars($category->name) ?>
        </h1>

        <?php if ($category->description): ?>
            <p><?= htmlspecialchars($category->description) ?></p>
        <?php endif; ?>

        <div class="category-stats-header">
            <div class="stat-item-header">
                <span class="stat-value-header"><?= $pagination['total'] ?? count($pages) ?></span>
                <span class="stat-label-header"><?= ($pagination['total'] ?? count($pages)) === 1 ? 'Article' : 'Articles' ?></span>
            </div>
            <?php if ($childCategories && count($childCategories) > 0): ?>
                <div class="stat-item-header">
                    <span class="stat-value-header"><?= count($childCategories) ?></span>
                    <span class="stat-label-header"><?= count($childCategories) === 1 ? 'Subcategory' : 'Subcategories' ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- Main Content -->
<div class="container">
    <?php if ($childCategories && count($childCategories) > 0): ?>
        <!-- Subcategories -->
        <section class="subcategories-section">
            <h2 class="subcategories-title">Subcategories</h2>
            <div class="subcategories-grid">
                <?php foreach ($childCategories as $child): ?>
                    <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/category/<?= htmlspecialchars($child->slug) ?>"
                       class="subcategory-card">
                        <?php if ($child->icon): ?>
                            <span class="subcategory-icon"><?= $child->icon ?></span>
                        <?php endif; ?>
                        <h3><?= htmlspecialchars($child->name) ?></h3>
                        <?php if ($child->description): ?>
                            <p><?= htmlspecialchars(substr($child->description, 0, 100)) ?><?= strlen($child->description) > 100 ? '...' : '' ?></p>
                        <?php endif; ?>
                        <span class="page-count"><?= $child->pages_count ?? 0 ?> articles</span>
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
            <h2>No Articles Yet</h2>
            <p>No articles in this category yet. Check back soon!</p>
        </div>
    <?php endif; ?>
</div>

@include('components/modals')

</body>
</html>