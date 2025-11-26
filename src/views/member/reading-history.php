<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reading History - <?= htmlspecialchars($site->name ?? 'Site') ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #667eea;
            --primary-dark: #5568d3;
            --secondary-color: #764ba2;
            --success-color: #10b981;
            --border-color: #e5e7eb;
            --bg-light: #f5f7fa;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg-light);
            min-height: 100vh;
            color: var(--text-primary);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
        }

        .stats-bar {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .stat-icon {
            font-size: 2rem;
        }

        .stat-info h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .stat-info p {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .timeline {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: var(--shadow);
        }

        .timeline-item {
            position: relative;
            padding-left: 2rem;
            padding-bottom: 2rem;
            border-left: 2px solid var(--border-color);
        }

        .timeline-item:last-child {
            padding-bottom: 0;
            border-left-color: transparent;
        }

        .timeline-marker {
            position: absolute;
            left: -0.625rem;
            top: 0;
            width: 1.25rem;
            height: 1.25rem;
            border-radius: 50%;
            background: var(--primary-color);
            border: 3px solid white;
            box-shadow: var(--shadow);
        }

        .timeline-content {
            background: var(--bg-light);
            padding: 1.5rem;
            border-radius: 0.75rem;
            transition: all 0.2s;
        }

        .timeline-content:hover {
            background: #f0f0f5;
        }

        .timeline-date {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .timeline-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .timeline-title a {
            color: var(--text-primary);
            text-decoration: none;
            transition: color 0.2s;
        }

        .timeline-title a:hover {
            color: var(--primary-color);
        }

        .timeline-excerpt {
            font-size: 0.875rem;
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .timeline-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.8125rem;
            color: var(--text-secondary);
        }

        .meta-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            background: white;
            border-radius: 0.375rem;
            font-weight: 500;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 1rem;
            box-shadow: var(--shadow);
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h2 {
            font-size: 1.5rem;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        .btn-primary {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            text-decoration: none;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: transform 0.2s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .header-content {
                padding: 1rem;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .stats-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .timeline {
                padding: 1rem;
            }

            .timeline-item {
                padding-left: 1.5rem;
            }

            .nav-links {
                gap: 1rem;
            }
        }
    </style>
</head>
<body>

@include('member._header')

<div class="container">
    <div class="page-header">
        <h1 class="page-title">📚 Reading History</h1>
        <p class="page-subtitle">Track your reading journey and revisit pages you've explored</p>
    </div>

    <div class="stats-bar">
        <div class="stat-item">
            <span class="stat-icon">📖</span>
            <div class="stat-info">
                <h3><?= $totalPagesRead ?></h3>
                <p>Unique Pages Read</p>
            </div>
        </div>
    </div>

    <?php if ($recentlyViewed->isEmpty()): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📚</div>
            <h2>No Reading History Yet</h2>
            <p>Start exploring content to build your reading history.</p>
            <a href="/" class="btn-primary">Start Reading</a>
        </div>
    <?php else: ?>
        <div class="timeline">
            <?php foreach ($recentlyViewed as $view): ?>
                <?php $page = $view->page; ?>
                <?php if ($page): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <div class="timeline-date">
                                <span>🕐</span>
                                <?php
                                $viewedDate = $view->viewed_at->getTimestamp();
                                $now = time();
                                $diff = $now - $viewedDate;

                                if ($diff < 3600) {
                                    echo floor($diff / 60) . ' minutes ago';
                                } elseif ($diff < 86400) {
                                    echo floor($diff / 3600) . ' hours ago';
                                } elseif ($diff < 604800) {
                                    echo floor($diff / 86400) . ' days ago';
                                } else {
                                    echo $view->viewed_at->format('M j, Y \a\t g:i A');
                                }
                                ?>
                            </div>
                            <h3 class="timeline-title">
                                <a href="/<?= htmlspecialchars($page->slug) ?>">
                                    <?= htmlspecialchars($page->title) ?>
                                </a>
                            </h3>
                            <?php if ($page->listing_synopsis): ?>
                                <p class="timeline-excerpt"><?= htmlspecialchars($page->listing_synopsis) ?></p>
                            <?php endif; ?>
                            <div class="timeline-meta">
                                <?php if (!empty($page->categories)): ?>
                                    <span class="meta-badge">
                                        📁 <?= htmlspecialchars($page->categories->first()->name) ?>
                                    </span>
                                <?php endif; ?>
                                <span class="meta-badge">
                                    📅 <?= $page->created_at->format('M j, Y') ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>