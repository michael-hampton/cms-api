<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tag->name) ?> - Tag Archive</title>
    <meta name="description" content="<?= htmlspecialchars($tag->description ?? "Pages tagged with {$tag->name}") ?>">
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

        .tag-archive {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .tag-header {
            text-align: center;
            padding: 60px 32px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 16px;
            margin-bottom: 48px;
            box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
        }

        .tag-header h1 {
            margin-bottom: 16px;
            font-size: 3rem;
            font-weight: 800;
        }

        .tag-badge {
            display: inline-block;
            padding: 12px 24px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 24px;
            font-size: 1.1rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }

        .tag-description {
            margin-top: 16px;
            opacity: 0.95;
            font-size: 1.125rem;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }

        .tag-stats {
            margin-top: 24px;
            font-size: 1rem;
            opacity: 0.9;
        }

        .pages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .page-card {
            padding: 28px;
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            transition: all 0.3s;
        }

        .page-card:hover {
            border-color: #667eea;
            box-shadow: 0 12px 28px rgba(102, 126, 234, 0.15);
            transform: translateY(-4px);
        }

        .page-card h3 {
            margin-bottom: 12px;
            font-size: 1.375rem;
            line-height: 1.4;
        }

        .page-card a {
            color: #111827;
            text-decoration: none;
            font-weight: 700;
        }

        .page-card a:hover {
            color: #667eea;
        }

        .page-excerpt {
            color: #6b7280;
            line-height: 1.7;
            margin-bottom: 16px;
        }

        .page-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #9ca3af;
            font-size: 0.9rem;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
        }

        .page-meta time {
            font-weight: 500;
        }

        .page-meta span {
            font-weight: 500;
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #6b7280;
            background: white;
            border-radius: 12px;
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
        }

        .empty-state p {
            font-size: 1.125rem;
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
            background: white;
        }

        .pagination-link:hover {
            background: #f3f4f6;
            border-color: #667eea;
            color: #667eea;
        }

        .pagination-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
        }

        .pagination-dots {
            color: #9ca3af;
            padding: 0 8px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .tag-header h1 {
                font-size: 2rem;
            }

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
<main class="tag-archive">
    <header class="tag-header">
        <h1>
            <span class="tag-badge">#<?= htmlspecialchars($tag->name) ?></span>
        </h1>
        <?php if ($tag->description): ?>
            <p class="tag-description"><?= htmlspecialchars($tag->description) ?></p>
        <?php endif; ?>
        <p class="tag-stats">
            <?= isset($pagination) ? $pagination['total'] : count($pages) ?> article<?= (isset($pagination) ? $pagination['total'] : count($pages)) !== 1 ? 's' : '' ?>
        </p>
    </header>

    <?php if (!empty($pages)): ?>
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
                            <?= htmlspecialchars(substr($page->meta_description, 0, 150)) ?>
                            <?= strlen($page->meta_description) > 150 ? '...' : '' ?>
                        </p>
                    <?php endif; ?>

                    <div class="page-meta">
                        <time datetime="<?= $page->published_at ?>">
                            <?= $page->published_at?->format('F j, Y') ?>
                        </time>
                        <?php if ($page->author): ?>
                            <span>by <?= htmlspecialchars($page->author->name) ?></span>
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
    <?php else: ?>
        <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                <polyline points="14 2 14 8 20 8"/>
                <line x1="12" y1="18" x2="12" y2="12"/>
                <line x1="12" y1="12" x2="12" y2="12"/>
            </svg>
            <h3>No Articles Found</h3>
            <p>No articles with this tag yet.</p>
        </div>
    <?php endif; ?>
</main>
</body>
</html>