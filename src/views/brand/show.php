<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tag->name) ?> - Brand</title>
    <meta name="description" content="<?= htmlspecialchars($tag->description ?? "Browse {$tag->name} content") ?>">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #111827;
            background: #f9fafb;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 32px;
            font-size: 0.9rem;
            color: #6b7280;
            padding: 16px 20px;
            background: white;
            border-radius: 8px;
        }

        .breadcrumb a {
            color: #2563eb;
            text-decoration: none;
            transition: color 0.2s;
        }

        .breadcrumb a:hover {
            color: #1d4ed8;
        }

        .breadcrumb-separator {
            margin: 0 8px;
            color: #d1d5db;
        }

        .breadcrumb .current {
            font-weight: 600;
            color: #111827;
        }

        /* Category Header */
        .category-header {
            text-align: center;
            padding: 48px 32px;
            background: white;
            border-radius: 12px;
            margin-bottom: 48px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .category-title {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 16px;
            color: #111827;
        }

        .category-description {
            font-size: 1.125rem;
            color: #6b7280;
            max-width: 700px;
            margin: 0 auto 24px;
            line-height: 1.7;
        }

        .category-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 24px;
            font-weight: 600;
            font-size: 0.95rem;
            color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .category-icon {
            font-size: 1.2rem;
        }

        /* Child Categories */
        .child-categories {
            margin-bottom: 48px;
        }

        .child-categories h2 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 24px;
            color: #111827;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .category-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 24px;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .category-card:hover {
            border-color: #2563eb;
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.12);
            transform: translateY(-4px);
        }

        .category-card-icon {
            font-size: 2rem;
            margin-bottom: 4px;
        }

        .category-card h3 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: #111827;
        }

        .category-card p {
            color: #6b7280;
            font-size: 0.9rem;
            line-height: 1.5;
            flex: 1;
        }

        .page-count {
            font-size: 0.85rem;
            color: #2563eb;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Category Pages */
        .category-pages {
            background: white;
            padding: 32px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .category-pages h2 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 24px;
            color: #111827;
        }

        .pages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .page-card {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 24px;
            transition: all 0.2s;
        }

        .page-card:hover {
            border-color: #2563eb;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);
            transform: translateY(-2px);
        }

        .page-card h3 {
            margin-bottom: 12px;
            font-size: 1.25rem;
            line-height: 1.4;
        }

        .page-card h3 a {
            color: #111827;
            text-decoration: none;
            font-weight: 600;
        }

        .page-card h3 a:hover {
            color: #2563eb;
        }

        .page-excerpt {
            color: #6b7280;
            margin-bottom: 16px;
            line-height: 1.6;
        }

        .page-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.875rem;
            color: #9ca3af;
            padding-top: 12px;
            border-top: 1px solid #e5e7eb;
        }

        .page-type {
            background: #e5e7eb;
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: 500;
            text-transform: capitalize;
            color: #374151;
        }

        .page-tags-mini {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .tag-mini {
            background: #dbeafe;
            color: #1e40af;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        /* Empty State */
        .no-pages {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }

        .no-pages svg {
            width: 64px;
            height: 64px;
            margin-bottom: 16px;
            color: #d1d5db;
        }

        .no-pages p {
            font-size: 1.125rem;
            margin-bottom: 24px;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
        }

        /* Pagination */
        .pagination-wrapper {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
        }

        .pagination-info {
            text-align: center;
            color: #6b7280;
            margin-bottom: 16px;
            font-size: 0.9rem;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            list-style: none;
        }

        .pagination li {
            display: inline-block;
        }

        .pagination-link {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            padding: 0 12px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            text-decoration: none;
            color: #374151;
            font-weight: 500;
            transition: all 0.2s;
        }

        .pagination-link:hover {
            background: #f3f4f6;
            border-color: #2563eb;
            color: #2563eb;
        }

        .pagination-link.active {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
        }

        .pagination-dots {
            color: #9ca3af;
            padding: 0 8px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .category-title {
                font-size: 2rem;
            }

            .categories-grid,
            .pages-grid {
                grid-template-columns: 1fr;
            }

            .page-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .pagination {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>

<main class="container">
    <!-- Breadcrumb -->
    <nav class="breadcrumb">
        <a href="/">Home</a>
        <span class="breadcrumb-separator">›</span>
        <a href="/categories">Categories</a>
        <?php foreach ($breadcrumb as $index => $crumb): ?>
            <span class="breadcrumb-separator">›</span>
            <?php if ($index < count($breadcrumb) - 1): ?>
                <a href="/category/<?= urlencode($crumb['slug']) ?>"><?= htmlspecialchars($crumb['name']) ?></a>
            <?php else: ?>
                <span class="current"><?= htmlspecialchars($crumb['name']) ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <!-- Brand Header -->
    <div class="category-header">
        <h1 class="category-title"><?= htmlspecialchars($tag->name) ?></h1>
        <?php if (!empty($tag->description)): ?>
            <p class="category-description"><?= nl2br(htmlspecialchars($tag->description)) ?></p>
        <?php endif; ?>

        <?php if ($tag->color): ?>
            <div class="category-badge" style="background-color: <?= htmlspecialchars($tag->color) ?>;">
                <?php if ($tag->icon): ?>
                    <span class="category-icon"><?= $tag->icon ?></span>
                <?php endif; ?>
                <?= htmlspecialchars($tag->name) ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Categories -->
    <?php foreach ($tag->categories as $category) { ?>
        <?= $category->name; ?>
    <?php }

    ?>

    <!-- Child Categories -->
    <?php if (!empty($childCategories)): ?>
        <section class="child-categories">
            <h2>Subcategories</h2>
            <div class="categories-grid">
                <?php foreach ($childCategories as $childCategory): ?>
                    <a href="/category/<?= urlencode($childCategory->slug) ?>" class="category-card">
                        <?php if ($childCategory->icon): ?>
                            <div class="category-card-icon"><?= $childCategory->icon ?></div>
                        <?php endif; ?>
                        <h3><?= htmlspecialchars($childCategory->name) ?></h3>
                        <?php if ($childCategory->description): ?>
                            <p><?= htmlspecialchars(substr($childCategory->description, 0, 100)) ?><?= strlen($childCategory->description) > 100 ? '...' : '' ?></p>
                        <?php endif; ?>
                        <span class="page-count"><?= $childCategory->getPageCount() ?> pages</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Pages in Category -->
    <section class="category-pages">
        <h2>Pages in <?= htmlspecialchars($tag->name) ?></h2>

        <?php if (!empty($pages)): ?>
            <div class="pages-grid">
                <?php foreach ($pages as $page): ?>
                    <article class="page-card">
                        <h3>
                            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?><?= htmlspecialchars($page->getUrlAttribute()) ?>"><?= htmlspecialchars($page->title) ?></a>
                        </h3>

                        <?php if (!empty($page->meta_description)): ?>
                            <p class="page-excerpt"><?= htmlspecialchars(substr($page->meta_description, 0, 150)) ?><?= strlen($page->meta_description) > 150 ? '...' : '' ?></p>
                        <?php endif; ?>

                        <div class="page-meta">
                            <?php if ($page->published_at): ?>
                                <time datetime="<?= date('Y-m-d', strtotime($page->published_at)) ?>">
                                    <?= date('M j, Y', strtotime($page->published_at)) ?>
                                </time>
                            <?php endif; ?>

                            <?php if ($page->page_type): ?>
                                <span class="page-type"><?= ucfirst(htmlspecialchars($page->page_type)) ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($page->tags)): ?>
                            <div class="page-tags-mini">
                                <?php foreach (array_slice($page->tags, 0, 3) as $tag): ?>
                                    <span class="tag-mini"><?= htmlspecialchars($tag->name) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if (isset($pagination) && $pagination['last_page'] > 1): ?>
                <nav class="pagination-wrapper">
                    <div class="pagination-info">
                        Showing <?= $pagination['from'] ?> to <?= $pagination['to'] ?> of <?= $pagination['total'] ?>
                        pages
                    </div>
                    <ul class="pagination">
                        <?php if ($pagination['current_page'] > 1): ?>
                            <li>
                                <a href="?page=<?= $pagination['current_page'] - 1 ?>" class="pagination-link">
                                    ‹ Previous
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $pagination['current_page'] - 2);
                        $end = min($pagination['last_page'], $pagination['current_page'] + 2);

                        if ($start > 1): ?>
                            <li><a href="?page=1" class="pagination-link">1</a></li>
                            <?php if ($start > 2): ?>
                                <li><span class="pagination-dots">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <li>
                                <a href="?page=<?= $i ?>"
                                   class="pagination-link <?= $i === $pagination['current_page'] ? 'active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($end < $pagination['last_page']): ?>
                            <?php if ($end < $pagination['last_page'] - 1): ?>
                                <li><span class="pagination-dots">...</span></li>
                            <?php endif; ?>
                            <li><a href="?page=<?= $pagination['last_page'] ?>"
                                   class="pagination-link"><?= $pagination['last_page'] ?></a></li>
                        <?php endif; ?>

                        <?php if ($pagination['current_page'] < $pagination['last_page']): ?>
                            <li>
                                <a href="?page=<?= $pagination['current_page'] + 1 ?>" class="pagination-link">
                                    Next ›
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="no-pages">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                </svg>
                <p>No pages found in this category yet.</p>
                <a href="/" class="btn">Explore Other Content</a>
            </div>
        <?php endif; ?>
    </section>
</main>

</body>
</html>