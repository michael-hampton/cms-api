<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Badges - <?= htmlspecialchars($site->name) ?></title>
    <style>
        /* Same base styles as dashboard */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #667eea;
            --primary-dark: #5568d3;
            --secondary-color: #764ba2;
            --border-color: #e5e7eb;
            --bg-light: #f5f7fa;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-light);
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
            text-decoration: none;
        }

        .nav {
            display: flex;
            gap: 1.5rem;
        }

        .nav a {
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav a:hover {
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
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .badge-filters {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 0.5rem 1.25rem;
            border: 2px solid var(--border-color);
            background: white;
            border-radius: 2rem;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }

        .filter-btn:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .filter-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .badges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .badge-card {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            text-align: center;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .badge-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .badge-card.locked {
            opacity: 0.6;
        }

        .badge-card.locked::after {
            content: '🔒';
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.5rem;
        }

        .badge-tier-indicator {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .badge-tier-indicator.bronze {
            background: linear-gradient(90deg, #cd7f32 0%, #b87333 100%);
        }

        .badge-tier-indicator.silver {
            background: linear-gradient(90deg, #c0c0c0 0%, #a8a8a8 100%);
        }

        .badge-tier-indicator.gold {
            background: linear-gradient(90deg, #ffd700 0%, #ffed4e 100%);
        }

        .badge-tier-indicator.platinum {
            background: linear-gradient(90deg, #e5e4e2 0%, #b9b8b6 100%);
        }

        .badge-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .badge-name {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .badge-description {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        .badge-tier {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }

        .badge-tier.bronze {
            background: #cd7f3220;
            color: #cd7f32;
        }

        .badge-tier.silver {
            background: #c0c0c020;
            color: #808080;
        }

        .badge-tier.gold {
            background: #ffd70020;
            color: #b8860b;
        }

        .badge-tier.platinum {
            background: #e5e4e220;
            color: #666666;
        }

        .badge-points {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .badge-earned-date {
            font-size: 0.8125rem;
            color: var(--text-secondary);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 1rem;
            box-shadow: var(--shadow);
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .badges-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<header class="header">
    <div class="header-content">
        <a href="/" class="logo"><?= htmlspecialchars($site->name) ?></a>
        <nav class="nav">
            <a href="/member/dashboard">Dashboard</a>
            <a href="/member/activity">Activity</a>
            <a href="/member/activity/badges">Badges</a>
            <a href="/member/settings">Settings</a>
            <a href="/member/logout">Logout</a>
        </nav>
    </div>
</header>

<main class="container">
    <div class="page-header">
        <h1 class="page-title">🏆 My Badges</h1>
        <p style="color: var(--text-secondary); margin-top: 0.5rem;">
            <?= $member->badges->count() ?> earned
            • <?= \App\Models\Badge::where('site_id', $site->id)->where('is_active', true)->count() ?> available
        </p>
    </div>

    <div class="badge-filters">
        <button class="filter-btn active" onclick="filterBadges('all')">All Badges</button>
        <button class="filter-btn" onclick="filterBadges('earned')">Earned</button>
        <button class="filter-btn" onclick="filterBadges('locked')">Locked</button>
        <button class="filter-btn" onclick="filterBadges('engagement')">Engagement</button>
        <button class="filter-btn" onclick="filterBadges('loyalty')">Loyalty</button>
        <button class="filter-btn" onclick="filterBadges('content')">Content</button>
    </div>

    <div class="badges-grid" id="badgesGrid">
        <?php
        $allBadges = \App\Models\Badge::where('site_id', $site->id)
                ->where('is_active', true)
                ->orderBy('tier')
                ->orderBy('name')
                ->get();

        $earnedBadgeIds = $member->badges->pluck('id')->toArray();
        ?>

        <?php foreach ($allBadges as $badge): ?>
            <?php
            $isEarned = in_array($badge->id, $earnedBadgeIds);
            $memberBadge = $isEarned ? $member->badges->firstWhere('id', $badge->id) : null;
            ?>
            <div class="badge-card <?= !$isEarned ? 'locked' : '' ?>"
                 data-status="<?= $isEarned ? 'earned' : 'locked' ?>"
                 data-category="<?= htmlspecialchars($badge->category) ?>">
                <div class="badge-tier-indicator <?= htmlspecialchars($badge->tier) ?>"></div>

                <div class="badge-icon"><?= $isEarned ? $badge->icon : '🔒' ?></div>
                <div class="badge-tier <?= htmlspecialchars($badge->tier) ?>">
                    <?= htmlspecialchars($badge->tier) ?>
                </div>
                <div class="badge-name"><?= htmlspecialchars($badge->name) ?></div>
                <div class="badge-description">
                    <?= htmlspecialchars($badge->description) ?>
                </div>

                <?php if ($badge->points > 0): ?>
                    <div class="badge-points">+<?= $badge->points ?> points</div>
                <?php endif; ?>

                <?php if ($isEarned && $memberBadge): ?>
                    <div class="badge-earned-date">
                        Earned <?= $memberBadge->pivot['earned_at']->format('M j, Y') ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<script>
    function filterBadges(filter) {
        // Update button states
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        event.target.classList.add('active');

        // Filter badges
        const badges = document.querySelectorAll('.badge-card');
        badges.forEach(badge => {
            const status = badge.dataset.status;
            const category = badge.dataset.category;

            let show = false;
            if (filter === 'all') {
                show = true;
            } else if (filter === 'earned' || filter === 'locked') {
                show = status === filter;
            } else {
                show = category === filter;
            }

            badge.style.display = show ? 'block' : 'none';
        });
    }
</script>
</body>
</html>