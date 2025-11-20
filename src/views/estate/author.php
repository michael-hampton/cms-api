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

        /* Author Header */
        .author-header {
            background: white;
            border-radius: 12px;
            padding: 40px;
            margin-bottom: 40px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #2563eb;
        }

        .author-header-content {
            display: flex;
            gap: 32px;
            align-items: flex-start;
        }

        .author-avatar-container {
            flex-shrink: 0;
        }

        .author-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #e5e7eb;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .author-info {
            flex: 1;
        }

        .author-info h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 16px;
            color: #111827;
        }

        .author-bio {
            line-height: 1.7;
            color: #6b7280;
            font-size: 1.05rem;
            margin-bottom: 20px;
        }

        .author-stats {
            display: flex;
            gap: 24px;
            padding: 16px 0;
            margin-bottom: 20px;
            border-top: 1px solid #e5e7eb;
            border-bottom: 1px solid #e5e7eb;
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

        .author-social {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .social-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #f3f4f6;
            color: #374151;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s;
            font-size: 0.9rem;
        }

        .social-link:hover {
            background: #2563eb;
            color: white;
            transform: translateY(-2px);
        }

        .social-link svg {
            width: 18px;
            height: 18px;
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

        .page-meta time {
            font-weight: 500;
        }

        .page-type {
            background: #dbeafe;
            color: #1e40af;
            padding: 4px 12px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.8rem;
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
            .author-header-content {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .author-info h1 {
                font-size: 2rem;
            }

            .author-stats {
                justify-content: center;
                flex-wrap: wrap;
            }

            .author-social {
                justify-content: center;
            }

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

            .author-header,
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
        <a href="/authors">Authors</a>
        <span class="breadcrumb-separator">›</span>
        <span class="current"><?= htmlspecialchars($author->name) ?></span>
    </nav>

    <!-- Author Header -->
    <header class="author-header">
        <div class="author-header-content">
            <?php if ($author->avatar): ?>
                <div class="author-avatar-container">
                    <img
                            src="<?= htmlspecialchars($author->avatar) ?>"
                            alt="<?= htmlspecialchars($author->name) ?>"
                            class="author-avatar"
                    >
                </div>
            <?php endif; ?>

            <div class="author-info">
                <h1><?= htmlspecialchars($author->name) ?></h1>

                <?php if ($author->bio): ?>
                    <div class="author-bio">
                        <?= nl2br(htmlspecialchars($author->bio)) ?>
                    </div>
                <?php endif; ?>

                <div class="author-stats">
                    <div class="stat-item">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                        </svg>
                        <span class="stat-value"><?= $pagination['total'] ?? count($pages) ?></span>
                        <span><?= ($pagination['total'] ?? count($pages)) === 1 ? 'Article' : 'Articles' ?></span>
                    </div>
                    <?php if ($author->created_at): ?>
                        <div class="stat-item">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                            <span>Joined <?= date('Y', strtotime($author->created_at)) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($author->email || $author->website || $author->twitter || $author->linkedin || $author->facebook): ?>
                    <div class="author-social">
                        <?php if ($author->email): ?>
                            <a href="mailto:<?= htmlspecialchars($author->email) ?>" class="social-link">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                    <polyline points="22,6 12,13 2,6"/>
                                </svg>
                                Email
                            </a>
                        <?php endif; ?>

                        <?php if ($author->website): ?>
                            <a href="<?= htmlspecialchars($author->website) ?>" target="_blank" rel="noopener"
                               class="social-link">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <line x1="2" y1="12" x2="22" y2="12"/>
                                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                                </svg>
                                Website
                            </a>
                        <?php endif; ?>

                        <?php if ($author->twitter): ?>
                            <a href="https://twitter.com/<?= htmlspecialchars($author->twitter) ?>" target="_blank"
                               rel="noopener" class="social-link">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"/>
                                </svg>
                                Twitter
                            </a>
                        <?php endif; ?>

                        <?php if ($author->linkedin): ?>
                            <a href="<?= htmlspecialchars($author->linkedin) ?>" target="_blank" rel="noopener"
                               class="social-link">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/>
                                    <rect x="2" y="9" width="4" height="12"/>
                                    <circle cx="4" cy="4" r="2"/>
                                </svg>
                                LinkedIn
                            </a>
                        <?php endif; ?>

                        <?php if ($author->facebook): ?>
                            <a href="<?= htmlspecialchars($author->facebook) ?>" target="_blank" rel="noopener"
                               class="social-link">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
                                </svg>
                                Facebook
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Articles Section -->
    <?php if (!empty($pages)): ?>
        <section class="content-section">
            <h2>Articles by <?= htmlspecialchars($author->name) ?></h2>

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
                        Showing <?= $pagination['from'] ?> to <?= $pagination['to'] ?> of <?= $pagination['total'] ?> articles
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
        </section>
    <?php else: ?>
        <section class="content-section">
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                    <polyline points="10 9 9 9 8 9"/>
                </svg>
                <h3>No Articles Yet</h3>
                <p><?= htmlspecialchars($author->name) ?> hasn't published any articles yet.</p>
            </div>
        </section>
    <?php endif; ?>
</main>
</body>
</html>