<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($author->name) ?> - Author</title>
    <meta name="description" content="<?= htmlspecialchars($author->bio ?? '') ?>">
    <?php if ($author->avatar): ?>
        <meta property="og:image" content="<?= htmlspecialchars($author->avatar) ?>">
    <?php endif; ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #ffffff;
            color: #111827;
            line-height: 1.6;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: white;
            padding: 60px 20px;
            text-align: center;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        .author-avatar-header {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            margin: 0 auto 24px;
        }

        .author-avatar-placeholder-header {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 700;
            border: 4px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            margin: 0 auto 24px;
        }

        .header-content h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .header-content p {
            font-size: 1.25rem;
            opacity: 0.95;
            max-width: 600px;
            margin: 0 auto 24px;
        }

        .author-stats-header {
            display: flex;
            justify-content: center;
            gap: 32px;
            margin-top: 24px;
            flex-wrap: wrap;
        }

        .stat-item-header {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .stat-value-header {
            font-size: 2rem;
            font-weight: 700;
        }

        .stat-label-header {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        /* Filters Section */
        .filters-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            border: 2px solid #e5e7eb;
        }

        .filters-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #111827;
        }

        .filters-header svg {
            color: #667eea;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
        }

        .filter-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 500;
            color: #495057;
            transition: all 0.3s ease;
        }

        .filter-group select:hover,
        .filter-group select:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.1);
        }

        /* Pages Grid */
        .pages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .page-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .page-card:hover {
            border-color: #667eea;
            box-shadow: 0 12px 32px rgba(102, 126, 234, 0.15);
            transform: translateY(-4px);
        }

        .page-card-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            overflow: hidden;
        }

        .page-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .page-card-content {
            padding: 24px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .page-card-title {
            font-size: 1.35rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: #111827;
            line-height: 1.4;
        }

        .page-card-title a {
            text-decoration: none;
            color: inherit;
            transition: color 0.3s ease;
        }

        .page-card-title a:hover {
            color: #667eea;
        }

        .page-subtitle {
            font-size: 1rem;
            color: #667eea;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .page-excerpt {
            color: #6b7280;
            font-size: 0.95rem;
            margin-bottom: 16px;
            flex-grow: 1;
            line-height: 1.6;
        }

        .page-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 16px;
            border-top: 2px solid #f3f4f6;
        }

        .page-author-date {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .page-date {
            font-size: 0.85rem;
            color: #9ca3af;
        }

        .page-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .page-link:hover {
            background: #5568d3;
            transform: translateX(2px);
        }

        .page-link svg {
            width: 16px;
            height: 16px;
            transition: transform 0.2s ease;
        }

        .page-link:hover svg {
            transform: translateX(4px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state svg {
            width: 80px;
            height: 80px;
            color: #d1d5db;
            margin-bottom: 20px;
        }

        .empty-state h2 {
            font-size: 1.5rem;
            margin-bottom: 12px;
            color: #6b7280;
        }

        .empty-state p {
            color: #9ca3af;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header-content h1 {
                font-size: 2rem;
            }

            .header-content p {
                font-size: 1rem;
            }

            .author-stats-header {
                gap: 16px;
            }

            .pages-grid {
                grid-template-columns: 1fr;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 20px;
            }
        }

        .page-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 12px;
        }

        .tag-mini {
            display: inline-block;
            background: #f3f4f6;
            color: #6b7280;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .tag-mini:hover {
            background: #667eea;
            color: white;
        }
    </style>
</head>
<body>

@include('header', ['menu' => $menu, 'title' => $author->name])

<!-- Header -->
<header class="header">
    <div class="header-content">
        <?php if ($author->avatar): ?>
            <img src="<?= htmlspecialchars($author->avatar) ?>"
                 alt="<?= htmlspecialchars($author->name) ?>"
                 class="author-avatar-header">
        <?php else: ?>
            <div class="author-avatar-placeholder-header">
                <?= strtoupper(substr($author->name, 0, 2)) ?>
            </div>
        <?php endif; ?>

        <h1><?= htmlspecialchars($author->name) ?></h1>

        <?php if ($author->bio): ?>
            <p><?= htmlspecialchars($author->bio) ?></p>
        <?php endif; ?>

        <div class="author-stats-header">
            <div class="stat-item-header">
                <span class="stat-value-header"><?= $pagination['total'] ?? count($pages) ?></span>
                <span class="stat-label-header"><?= ($pagination['total'] ?? count($pages)) === 1 ? 'Article' : 'Articles' ?></span>
            </div>
            <?php if ($author->created_at): ?>
                <div class="stat-item-header">
                    <span class="stat-value-header"><?= date('Y', strtotime($author->created_at)) ?></span>
                    <span class="stat-label-header">Joined</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- Main Content -->
<div class="container">
    <?php if ($pages && count($pages) > 0): ?>
        <!-- Filters -->
        @include('partials.filters', ['pages' => $pages])


        <!-- Pages Grid -->
        <div class="pages-grid">
            <?php foreach ($pages as $page): ?>
                <article class="page-card">
                    <?php if ($page->featured_image): ?>
                        <div class="page-card-image">
                            <img src="<?= htmlspecialchars($page->featured_image) ?>"
                                 alt="<?= htmlspecialchars($page->title) ?>">
                        </div>
                    <?php else: ?>
                        <div class="page-card-image">📄</div>
                    <?php endif; ?>

                    <div class="page-card-content">
                        <?php if ($page->subtitle): ?>
                            <p class="page-subtitle"><?= htmlspecialchars($page->subtitle) ?></p>
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
            <h2>No Articles Yet</h2>
            <p><?= htmlspecialchars($author->name) ?> hasn't published any articles yet.</p>
        </div>
    <?php endif; ?>
</div>

</body>
</html>