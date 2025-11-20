<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($category->name) ?> - Category</title>
    <meta name="description" content="<?= htmlspecialchars($category->description ?? "Browse {$category->name} content") ?>">
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
            margin-bottom: 24px;
            font-size: 0.875rem;
            color: #6b7280;
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

        /* Page Header */
        .page-header {
            background: white;
            border-radius: 12px;
            padding: 40px;
            margin-bottom: 40px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #2563eb;
        }

        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 12px;
            color: #111827;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .category-icon {
            font-size: 2rem;
            color: #2563eb;
        }

        .page-description {
            font-size: 1.125rem;
            color: #6b7280;
            line-height: 1.7;
            margin-bottom: 20px;
        }

        .page-stats {
            display: flex;
            gap: 24px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #6b7280;
            font-size: 0.95rem;
        }

        .stat-value {
            font-weight: 700;
            color: #2563eb;
        }

        /* Child Categories */
        .child-categories {
            margin-bottom: 40px;
        }

        .child-categories h2 {
            font-size: 1.5rem;
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
            transition: all 0.25s ease;
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
            color: #2563eb;
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

        /* Content Section */
        .content-section {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .content-section h2 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 32px;
            color: #111827;
        }

        .pages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .page-card {
            background: #f9fafb;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 24px;
            transition: all 0.25s ease;
            display: flex;
            flex-direction: column;
        }

        .page-card:hover {
            border-color: #2563eb;
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.12);
            transform: translateY(-4px);
        }

        .page-card h3 {
            margin-bottom: 12px;
            font-size: 1.25rem;
            line-height: 1.4;
        }

        .page-card h3 a {
            color: #111827;
            text-decoration: none;
            font-weight: 700;
        }

        .page-card h3 a:hover {
            color: #2563eb;
        }

        .page-excerpt {
            color: #6b7280;
            margin-bottom: 16px;
            line-height: 1.6;
            flex: 1;
        }

        .page-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.875rem;
            color: #9ca3af;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
        }

        .page-type {
            background: #dbeafe;
            color: #1e40af;
            padding: 4px 12px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.8rem;
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
            font-size: 0.75rem;
            font-weight: 500;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #6b7280;
        }

        .empty-state svg {
            width: 80px;
            height: 80px;
            margin-bottom: 24px;
            color: #d1d5db;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 12px;
            color: #374151;
            font-weight: 700;
        }

        .empty-state p {
            font-size: 1.05rem;
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
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        /* Pagination */
        .pagination-wrapper {
            margin-top: 40px;
            padding-top: 32px;
            border-top: 2px solid #e5e7eb;
        }

        .pagination-info {
            text-align: center;
            color: #6b7280;
            margin-bottom: 20px;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            list-style: none;
            flex-wrap: wrap;
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
            padding: 0 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            text-decoration: none;
            color: #374151;
            font-weight: 600;
            transition: all 0.2s;
            background: white;
        }

        .pagination-link:hover {
            background: #f9fafb;
            border-color: #2563eb;
            color: #2563eb;
            transform: translateY(-2px);
        }

        .pagination-link.active {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
        }

        .pagination-dots {
            color: #9ca3af;
            padding: 0 8px;
            font-weight: 600;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 2rem;
                flex-direction: column;
                align-items: flex-start;
            }

            .page-stats {
                flex-direction: column;
                gap: 12px;
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

            .container {
                padding: 20px 16px;
            }

            .page-header,
            .content-section {
                padding: 24px;
            }
        }

        .page-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.25s ease;
            display: flex;
            flex-direction: column;
        }

        .page-card-image {
            width: 100%;
            height: 200px;
            overflow: hidden;
            background: #f3f4f6;
        }

        .page-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .page-card:hover .page-card-image img {
            transform: scale(1.05);
        }

        .page-card-content {
            padding: 24px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .page-subtitle {
            font-size: 1rem;
            color: #3b82f6;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .page-footer {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: auto;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
        }

        .page-author-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .author-avatar-small {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e5e7eb;
        }

        .author-avatar-placeholder {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .author-name {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
        }

        .page-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.875rem;
            color: #9ca3af;
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

    <!-- Page Header -->
    <header class="page-header">
        <h1>
            <?php if ($category->icon): ?>
                <span class="category-icon"><?= $category->icon ?></span>
            <?php endif; ?>
            <?= htmlspecialchars($category->name) ?>
        </h1>

        <?php if (!empty($category->description)): ?>
            <p class="page-description"><?= nl2br(htmlspecialchars($category->description)) ?></p>
        <?php endif; ?>

        <div class="page-stats">
            <div class="stat-item">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                </svg>
                <span class="stat-value"><?= isset($pagination) ? $pagination['total'] : count($pages) ?></span>
                <span>Pages</span>
            </div>
            <?php if (!empty($childCategories)): ?>
                <div class="stat-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                    </svg>
                    <span class="stat-value"><?= count($childCategories) ?></span>
                    <span><?= count($childCategories) === 1 ? 'Subcategory' : 'Subcategories' ?></span>
                </div>
            <?php endif; ?>
        </div>
    </header>

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

    <!-- Pages Section -->
    <section class="content-section">
        <h2>All Pages</h2>

        <?php if (!empty($pages)): ?>
            <div class="pages-grid">
                <?php foreach ($pages as $page): ?>
                    <article class="page-card">
                        <?php if (!empty($page->featured_image)): ?>
                            <div class="page-card-image">
                                <img src="<?= htmlspecialchars($page->featured_image) ?>"
                                     alt="<?= htmlspecialchars($page->title) ?>">
                            </div>
                        <?php endif; ?>

                        <div class="page-card-content">
                            <h3>
                                <a href="/<?= \App\Framework\Support\SiteContext::slug() ?><?= htmlspecialchars($page->getUrlAttribute()) ?>">
                                    <?= htmlspecialchars($page->title) ?>
                                </a>
                            </h3>

                            <?php if ($page->subtitle): ?>
                                <p class="page-subtitle">
                                    <?= htmlspecialchars($page->subtitle) ?>
                                </p>
                            <?php endif; ?>

                            <?php if ($page->meta_description): ?>
                                <p class="page-excerpt">
                                    <?= htmlspecialchars(substr($page->meta_description, 0, 120)) ?><?= strlen($page->meta_description) > 120 ? '...' : '' ?>
                                </p>
                            <?php endif; ?>

                            <div class="page-footer">
                                <div class="page-author-info">
                                    <?php if ($page->authors && $page->authors->count() > 0): ?>
                                        <?php $author = $page->authors->first(); ?>
                                        <?php if ($author->avatar): ?>
                                            <img src="<?= htmlspecialchars($author->avatar) ?>"
                                                 alt="<?= htmlspecialchars($author->name) ?>"
                                                 class="author-avatar-small">
                                        <?php else: ?>
                                            <div class="author-avatar-placeholder">
                                                <?= strtoupper(substr($author->name, 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <span class="author-name"><?= htmlspecialchars($author->name) ?></span>
                                    <?php endif; ?>
                                </div>

                                <div class="page-meta">
                                    <?php if ($page->published_at): ?>
                                        <time datetime="<?= $page->published_at->format('Y-m-d') ?>">
                                            <?= $page->published_at->format('M j, Y') ?>
                                        </time>
                                    <?php endif; ?>

                                    <?php if ($page->page_type): ?>
                                        <span class="page-type"><?= ucfirst(htmlspecialchars($page->page_type)) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!empty($page->tags) && count($page->tags) > 1): ?>
                                <div class="page-tags-mini">
                                    <?php foreach (array_slice($page->tags, 0, 3) as $pageTag): ?>
                                        <span class="tag-mini"><?= htmlspecialchars($pageTag->name) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if (isset($pagination) && $pagination['last_page'] > 1): ?>
                <nav class="pagination-wrapper">
                    <div class="pagination-info">
                        Showing <?= $pagination['from'] ?> to <?= $pagination['to'] ?> of <?= $pagination['total'] ?> pages
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
                            <li><a href="?page=<?= $pagination['last_page'] ?>" class="pagination-link"><?= $pagination['last_page'] ?></a></li>
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
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                </svg>
                <h3>No Pages Yet</h3>
                <p>No pages found in this category.</p>
                <a href="/" class="btn">Explore Other Content</a>
            </div>
        <?php endif; ?>
    </section>
</main>

</body>
</html>