<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liked Pages - <?= htmlspecialchars($site->name ?? 'Site') ?></title>
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
            --danger-color: #ef4444;
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

        .header {
            background: white;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }

        .nav-link {
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav-link:hover {
            color: var(--primary-color);
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

        .pages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }

        .page-card {
            background: white;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .page-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .page-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: linear-gradient(135deg, var(--primary-color)20 0%, var(--secondary-color)20 100%);
        }

        .page-content {
            padding: 1.5rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .page-title-text {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            line-height: 1.4;
        }

        .page-excerpt {
            font-size: 0.875rem;
            color: var(--text-secondary);
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            flex: 1;
        }

        .page-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
            font-size: 0.8125rem;
            color: var(--text-secondary);
        }

        .like-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--danger-color);
            color: white;
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 1rem;
            box-shadow: var(--shadow);
        }

        .unlike-btn {
            background: var(--bg-light);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            cursor: pointer;
            font-size: 0.875rem;
            color: var(--text-secondary);
            transition: all 0.2s;
        }

        .unlike-btn:hover {
            background: var(--danger-color);
            color: white;
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

            .pages-grid {
                grid-template-columns: 1fr;
            }

            .nav-links {
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
<header class="header">
    <div class="header-content">
        <a href="/" class="logo"><?= htmlspecialchars($site->name ?? 'Site') ?></a>
        <div class="nav-links">
            <a href="/member/dashboard" class="nav-link">Dashboard</a>
            <a href="/member/reading-history" class="nav-link">Reading History</a>
            <form method="POST" action="/member/logout" style="display: inline;">
                <button type="submit" class="nav-link" style="background: none; border: none; cursor: pointer;">Logout</button>
            </form>
        </div>
    </div>
</header>

<div class="container">
    <div class="page-header">
        <h1 class="page-title">❤️ Liked Pages</h1>
        <p class="page-subtitle">Your collection of favorite pages and content</p>
    </div>

    <div class="stats-bar">
        <div class="stat-item">
            <span class="stat-icon">❤️</span>
            <div class="stat-info">
                <h3><?= $totalLikes ?></h3>
                <p>Total Likes</p>
            </div>
        </div>
    </div>

    <?php if ($likedPages->isEmpty()): ?>
        <div class="empty-state">
            <div class="empty-state-icon">💔</div>
            <h2>No Liked Pages Yet</h2>
            <p>Start exploring and like pages to build your collection of favorites.</p>
            <a href="/" class="btn-primary">Explore Content</a>
        </div>
    <?php else: ?>
        <div class="pages-grid">
            <?php foreach ($likedPages as $like): ?>
                <?php $page = $like->page; ?>
                <?php if ($page): ?>
                    <div class="page-card">
                        <span class="like-badge">❤️</span>
                        <a href="/<?= htmlspecialchars($page->slug) ?>" style="text-decoration: none; color: inherit;">
                            <?php if ($page->listing_image_id): ?>
                                <img src="/images/<?= $page->listing_image_id ?>" alt="<?= htmlspecialchars($page->title) ?>" class="page-image">
                            <?php else: ?>
                                <div class="page-image"></div>
                            <?php endif; ?>
                            <div class="page-content">
                                <h3 class="page-title-text"><?= htmlspecialchars($page->title) ?></h3>
                                <?php if ($page->listing_synopsis): ?>
                                    <p class="page-excerpt"><?= htmlspecialchars($page->listing_synopsis) ?></p>
                                <?php endif; ?>
                                <div class="page-meta">
                                    <span>❤️ Liked on <?= $like->liked_at->format('M j, Y') ?></span>
                                    <button class="unlike-btn" onclick="unlikePage(event, <?= $page->id ?>)">Unlike</button>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    async function unlikePage(event, pageId) {
        event.preventDefault();
        event.stopPropagation();

        if (!confirm('Remove this page from your liked pages?')) {
            return;
        }

        try {
            const response = await fetch(`/api/pages/${pageId}/like`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (data.success) {
                // Reload page to show updated list
                window.location.reload();
            } else {
                alert('Failed to unlike page');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        }
    }
</script>
</body>
</html>