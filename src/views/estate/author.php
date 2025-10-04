<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($author->name) ?> - Author Profile</title>
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

        .author-profile {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .author-header {
            display: flex;
            gap: 32px;
            margin-bottom: 48px;
            padding: 32px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .author-avatar-container {
            flex-shrink: 0;
        }

        .author-avatar {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #e5e7eb;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .author-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .author-info h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 8px;
        }

        .author-stats {
            display: flex;
            gap: 24px;
            padding: 16px 0;
            border-top: 1px solid #e5e7eb;
            border-bottom: 1px solid #e5e7eb;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2563eb;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .author-bio {
            line-height: 1.7;
            color: #4b5563;
            font-size: 1.05rem;
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
            padding: 10px 16px;
            background: #f3f4f6;
            color: #374151;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s;
            font-size: 0.9rem;
        }

        .social-link:hover {
            background: #e5e7eb;
            transform: translateY(-2px);
        }

        .social-link svg {
            width: 18px;
            height: 18px;
        }

        .author-pages {
            background: white;
            padding: 32px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .author-pages h2 {
            font-size: 2rem;
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
            padding: 24px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
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

        .page-card a {
            color: #111827;
            text-decoration: none;
            font-weight: 600;
        }

        .page-card a:hover {
            color: #2563eb;
        }

        .page-excerpt {
            color: #6b7280;
            margin: 12px 0;
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

        /* Pagination Styles */
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

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }

        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 16px;
            color: #d1d5db;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 8px;
            color: #374151;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .author-header {
                flex-direction: column;
                text-align: center;
                align-items: center;
            }

            .author-info h1 {
                font-size: 2rem;
            }

            .author-stats {
                justify-content: center;
            }

            .author-social {
                justify-content: center;
            }

            .pages-grid {
                grid-template-columns: 1fr;
            }

            .pagination {
                flex-wrap: wrap;
            }
        }
    </style>
</head>

<body>
<main class="author-profile">
    <article class="author-header">
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
            <div>
                <h1><?= htmlspecialchars($author->name) ?></h1>

                <div class="author-stats">
                    <div class="stat-item">
                        <span class="stat-value"><?= $pagination['total'] ?? count($pages) ?></span>
                        <span class="stat-label">Articles</span>
                    </div>
                    <?php if ($author->created_at): ?>
                        <div class="stat-item">
                            <span class="stat-value"><?= date('Y', strtotime($author->created_at)) ?></span>
                            <span class="stat-label">Joined</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($author->bio): ?>
                <div class="author-bio">
                    <?= nl2br(htmlspecialchars($author->bio)) ?>
                </div>
            <?php endif; ?>

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
                        <a href="<?= htmlspecialchars($author->website) ?>" target="_blank" rel="noopener" class="social-link">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="2" y1="12" x2="22" y2="12"/>
                                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                            </svg>
                            Website
                        </a>
                    <?php endif; ?>

                    <?php if ($author->twitter): ?>
                        <a href="https://twitter.com/<?= htmlspecialchars($author->twitter) ?>" target="_blank" rel="noopener" class="social-link">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"/>
                            </svg>
                            Twitter
                        </a>
                    <?php endif; ?>

                    <?php if ($author->linkedin): ?>
                        <a href="<?= htmlspecialchars($author->linkedin) ?>" target="_blank" rel="noopener" class="social-link">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/>
                                <rect x="2" y="9" width="4" height="12"/>
                                <circle cx="4" cy="4" r="2"/>
                            </svg>
                            LinkedIn
                        </a>
                    <?php endif; ?>

                    <?php if ($author->facebook): ?>
                        <a href="<?= htmlspecialchars($author->facebook) ?>" target="_blank" rel="noopener" class="social-link">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
                            </svg>
                            Facebook
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </article>

    <?php if (!empty($pages)): ?>
        <section class="author-pages">
            <h2>Articles by <?= htmlspecialchars($author->name) ?></h2>

            <div class="pages-grid">
                <?php foreach ($pages as $page): ?>
                    <article class="page-card">
                        <h3>
                            <a href="<?= htmlspecialchars($page->getUrlAttribute()) ?>">
                                <?= htmlspecialchars($page->title) ?>
                            </a>
                        </h3>

                        <?php if ($page->meta_description): ?>
                            <p class="page-excerpt">
                                <?= htmlspecialchars($page->meta_description) ?>
                            </p>
                        <?php endif; ?>

                        <div class="page-meta">
                            <time datetime="<?= $page->published_at?->format('Y-m-d') ?>">
                                <?= $page->published_at?->format('F j, Y') ?>
                            </time>
                            <?php if ($page->content_type): ?>
                                <span><?= htmlspecialchars($page->content_type) ?></span>
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
                                    &laquo; Previous
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
                                    Next &raquo;
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </section>
    <?php else: ?>
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
    <?php endif; ?>
</main>
</body>
</html>