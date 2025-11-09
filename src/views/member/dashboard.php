<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard - <?= htmlspecialchars($site->name ?? 'Site') ?></title>
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
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
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
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .avatar {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1rem;
        }

        .user-name {
            font-weight: 500;
            color: var(--text-primary);
        }

        .btn-logout {
            padding: 0.5rem 1.25rem;
            background: var(--bg-light);
            color: var(--text-secondary);
            border: none;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-logout:hover {
            background: #e5e7eb;
            color: var(--text-primary);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .welcome-section {
            background: white;
            border-radius: 1rem;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .welcome-section h1 {
            font-size: 2rem;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .welcome-section p {
            color: var(--text-secondary);
            font-size: 1rem;
        }

        .message {
            padding: 1rem 1.25rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            font-size: 0.9375rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .message.success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid var(--success-color);
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title::before {
            content: '';
            width: 4px;
            height: 1.5rem;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border-radius: 2px;
        }

        /* Dashboard Cards Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .dashboard-card {
            background: white;
            border-radius: 1rem;
            padding: 1.75rem;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            border: 2px solid transparent;
        }

        .dashboard-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-color);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.25rem;
        }

        .card-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .card-icon.orders {
            background: linear-gradient(135deg, #667eea20 0%, #764ba220 100%);
        }

        .card-icon.newsletters {
            background: linear-gradient(135deg, #10b98120 0%, #059f6920 100%);
        }

        .card-icon.subscriptions {
            background: linear-gradient(135deg, #f59e0b20 0%, #d9770620 100%);
        }

        .card-icon.addresses {
            background: linear-gradient(135deg, #3b82f620 0%, #2563eb20 100%);
        }

        .card-icon.comments {
            background: linear-gradient(135deg, #8b5cf620 0%, #7c3aed20 100%);
        }

        .card-icon.settings {
            background: linear-gradient(135deg, #6b728020 0%, #4b556320 100%);
        }

        .card-content h3 {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .card-content p {
            font-size: 0.875rem;
            color: var(--text-secondary);
            line-height: 1.5;
            margin-top: 0.5rem;
        }

        .card-arrow {
            color: var(--primary-color);
            font-size: 1.25rem;
            transition: transform 0.2s ease;
        }

        .dashboard-card:hover .card-arrow {
            transform: translateX(4px);
        }

        /* Stats Cards Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            text-align: center;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Recommended Pages Grid */
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
            display: block;
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
        }

        .page-title {
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
        }

        .page-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
            font-size: 0.8125rem;
            color: var(--text-secondary);
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            background: white;
            border-radius: 1rem;
            box-shadow: var(--shadow);
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.25rem;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .header-content {
                padding: 1rem;
            }

            .welcome-section {
                padding: 1.5rem;
            }

            .welcome-section h1 {
                font-size: 1.5rem;
            }

            .dashboard-grid,
            .stats-grid,
            .pages-grid {
                grid-template-columns: 1fr;
            }

            .user-name {
                display: none;
            }
        }
    </style>
</head>

<?php
$site = \App\Framework\Support\SiteContext::slug();
?>
<body>
<header class="header">
    <div class="header-content">
        <div class="logo"><?= htmlspecialchars($site->name ?? 'Site') ?></div>
        <div class="user-menu">
            <div class="user-info">
                <div class="avatar">
                    <?= strtoupper(substr($member->first_name ?? 'M', 0, 1)) ?>
                </div>
                <span class="user-name"><?= htmlspecialchars($member->getDisplayName()) ?></span>
            </div>
            <form method="POST" action="/member/logout" style="display: inline;">
                <button type="submit" class="btn-logout">Logout</button>
            </form>
        </div>
    </div>
</header>

<div class="container">
    <?php if ($msg = message()): ?>
        <div class="message success">
            <span>✓</span>
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <div class="welcome-section">
        <h1>Welcome back, <?= htmlspecialchars($member->first_name ?? 'Member') ?>!</h1>
        <p>Manage your account, track your orders, and explore exclusive content.</p>
    </div>

    <h2 class="section-title">Quick Access</h2>

    <div class="dashboard-grid">
        <a href="/<?= $site ?>/member/orders" class="dashboard-card">
            <div class="card-header">
                <div class="card-icon orders">🛍️</div>
                <div class="card-arrow">→</div>
            </div>
            <div class="card-content">
                <h3>My Orders</h3>
                <p>View and track your order history and current shipments.</p>
            </div>
        </a>

        <a href="/<?= $site ?>/member/newsletters" class="dashboard-card">
            <div class="card-header">
                <div class="card-icon newsletters">📧</div>
                <div class="card-arrow">→</div>
            </div>
            <div class="card-content">
                <h3>Newsletters</h3>
                <p>Manage your newsletter subscriptions and preferences.</p>
            </div>
        </a>

        <a href="/<?= $site ?>/member/subscriptions" class="dashboard-card">
            <div class="card-header">
                <div class="card-icon subscriptions">⭐</div>
                <div class="card-arrow">→</div>
            </div>
            <div class="card-content">
                <h3>Subscriptions</h3>
                <p>View and manage your active subscriptions and membership plans.</p>
            </div>
        </a>

        <a href="/<?= $site ?>/member/addresses" class="dashboard-card">
            <div class="card-header">
                <div class="card-icon addresses">📍</div>
                <div class="card-arrow">→</div>
            </div>
            <div class="card-content">
                <h3>Addresses</h3>
                <p>Manage your shipping and billing addresses.</p>
            </div>
        </a>

        <a href="/<?= $site ?>/member/comments" class="dashboard-card">
            <div class="card-header">
                <div class="card-icon comments">💬</div>
                <div class="card-arrow">→</div>
            </div>
            <div class="card-content">
                <h3>Comments</h3>
                <p>View and manage your comments across the site.</p>
            </div>
        </a>

        <a href="/<?= $site ?>/member/settings" class="dashboard-card">
            <div class="card-header">
                <div class="card-icon settings">⚙️</div>
                <div class="card-arrow">→</div>
            </div>
            <div class="card-content">
                <h3>Account Settings</h3>
                <p>Update your password and account preferences.</p>
            </div>
        </a>

        <a href="/<?= $site ?>/member/reading-history" class="dashboard-card">
            <div class="card-header">
                <div class="card-icon" style="background: linear-gradient(135deg, #ec489920 0%, #f5717620 100%);">📚</div>
                <div class="card-arrow">→</div>
            </div>
            <div class="card-content">
                <h3>Reading History</h3>
                <p>View pages you've read and track your reading progress.</p>
            </div>
        </a>

        <a href="/<?= $site ?>/member/liked-pages" class="dashboard-card">
            <div class="card-header">
                <div class="card-icon" style="background: linear-gradient(135deg, #ef444420 0%, #dc262620 100%);">❤️</div>
                <div class="card-arrow">→</div>
            </div>
            <div class="card-content">
                <h3>Liked Pages</h3>
                <p>Access your collection of liked pages and content.</p>
            </div>
        </a>
    </div>

    <h2 class="section-title">Your Activity</h2>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?= $stats['orders'] ?></div>
            <div class="stat-label">Total Orders</div>
        </div>

        <div class="stat-card">
            <div class="stat-number"><?= $stats['newsletters'] ?></div>
            <div class="stat-label">Newsletters</div>
        </div>

        <div class="stat-card">
            <div class="stat-number"><?= $stats['subscriptions'] ?></div>
            <div class="stat-label">Active Subscriptions</div>
        </div>

<!--        <div class="stat-card">-->
<!--            <div class="stat-number">--><?php //= $stats['addresses'] ?><!--</div>-->
<!--            <div class="stat-label">Saved Addresses</div>-->
<!--        </div>-->

        <div class="stat-card">
            <div class="stat-number"><?= $stats['comments'] ?></div>
            <div class="stat-label">Comments Posted</div>
        </div>

        <div class="stat-card">
            <div class="stat-number"><?= $stats['pages_read'] ?></div>
            <div class="stat-label">Pages Read</div>
        </div>

        <div class="stat-card">
            <div class="stat-number"><?= $stats['likes'] ?></div>
            <div class="stat-label">Pages Liked</div>
        </div>
    </div>

    <h2 class="section-title">Recommended For You</h2>

    <?php if ($recommendedPages->isEmpty()): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📄</div>
            <h3>No Recommendations Yet</h3>
            <p>Check back soon for personalized content recommendations.</p>
        </div>
    <?php else: ?>
        <div class="pages-grid">
            <?php foreach ($recommendedPages as $page): ?>
                <a href="/<?= htmlspecialchars($page->slug) ?>" class="page-card">
                    <?php if ($page->listing_image_id): ?>
                        <img src="/images/<?= $page->listing_image_id ?>" alt="<?= htmlspecialchars($page->title) ?>" class="page-image">
                    <?php else: ?>
                        <div class="page-image"></div>
                    <?php endif; ?>
                    <div class="page-content">
                        <h3 class="page-title"><?= htmlspecialchars($page->title) ?></h3>
                        <?php if ($page->listing_synopsis): ?>
                            <p class="page-excerpt"><?= htmlspecialchars($page->listing_synopsis) ?></p>
                        <?php endif; ?>
                        <div class="page-meta">
                            <span>📅 <?= date('M j, Y', strtotime($page->created_at)) ?></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>