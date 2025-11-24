<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Dashboard - <?= htmlspecialchars($site->name) ?></title>
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
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
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

        .page-subtitle {
            color: var(--text-secondary);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .badges-showcase {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .badges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 1rem;
        }

        .badge-item {
            text-align: center;
            padding: 1rem;
            border: 2px solid var(--border-color);
            border-radius: 0.75rem;
            transition: all 0.3s;
        }

        .badge-item:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }

        .badge-icon {
            font-size: 3rem;
            margin-bottom: 0.5rem;
        }

        .badge-name {
            font-weight: 600;
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }

        .badge-tier {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
        }

        .progress-section {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .progress-item {
            margin-bottom: 1.5rem;
        }

        .progress-item:last-child {
            margin-bottom: 0;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .progress-bar-container {
            height: 0.75rem
            background: var(--bg-light);
            border-radius: 0.5rem;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            transition: width 0.3s;
        }

        .activity-feed {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
        }

        .activity-item {
            display: flex;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            background: var(--bg-light);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .activity-content {
            flex: 1;
        }

        .activity-text {
            font-size: 0.9375rem;
            margin-bottom: 0.25rem;
        }

        .activity-time {
            font-size: 0.8125rem;
            color: var(--text-secondary);
        }

        .activity-points {
            color: var(--success-color);
            font-weight: 600;
        }

        .chart-container {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .chart {
            height: 300px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .badges-grid {
                grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        <h1 class="page-title">Activity & Achievements</h1>
        <p class="page-subtitle">Track your engagement and earn badges</p>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea20 0%, #764ba220 100%);">
                    🏆
                </div>
            </div>
            <div class="stat-value"><?= number_format($progress['total_points']) ?></div>
            <div class="stat-label">Total Points</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background: linear-gradient(135deg, #10b98120 0%, #059f6920 100%);">
                    🎖️
                </div>
            </div>
            <div class="stat-value"><?= $progress['badges_earned'] ?> / <?= $progress['badges_available'] ?></div>
            <div class="stat-label">Badges Earned</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b20 0%, #d9770620 100%);">
                    💬
                </div>
            </div>
            <div class="stat-value"><?= number_format($progress['stats']['comments']) ?></div>
            <div class="stat-label">Comments Posted</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f620 0%, #2563eb20 100%);">
                    📚
                </div>
            </div>
            <div class="stat-value"><?= number_format($progress['stats']['pages_read']) ?></div>
            <div class="stat-label">Pages Read</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ef444420 0%, #dc262620 100%);">
                    ❤️
                </div>
            </div>
            <div class="stat-value"><?= number_format($progress['stats']['likes']) ?></div>
            <div class="stat-label">Likes Given</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background: linear-gradient(135deg, #8b5cf620 0%, #7c3aed20 100%);">
                    📅
                </div>
            </div>
            <div class="stat-value"><?= number_format($progress['stats']['member_days']) ?></div>
            <div class="stat-label">Days as Member</div>
        </div>
    </div>

    <!-- Recent Badges -->
    <?php if (!$member->badges->isEmpty()): ?>
        <div class="badges-showcase">
            <h2 class="section-title">Recent Badges</h2>
            <div class="badges-grid">
                <?php foreach ($member->badges->take(8) as $badge): ?>
                    <div class="badge-item">
                        <div class="badge-icon"><?= $badge->icon ?></div>
                        <div class="badge-name"><?= htmlspecialchars($badge->name) ?></div>
                        <div class="badge-tier"><?= htmlspecialchars($badge->tier) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div style="text-align: center; margin-top: 1.5rem;">
                <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/activity/badges"
                   style="color: var(--primary-color); text-decoration: none; font-weight: 500;">
                    View All Badges →
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Progress Towards Next Badges -->
    <?php if (!empty($progress['next_badges'])): ?>
        <div class="progress-section">
            <h2 class="section-title">Next Badges to Earn</h2>
            <?php foreach ($progress['next_badges'] as $badgeProgress): ?>
                <div class="progress-item">
                    <div class="progress-header">
                        <div>
                            <strong><?= $badgeProgress['badge']->icon ?> <?= htmlspecialchars($badgeProgress['badge']->name) ?></strong>
                            <span style="font-size: 0.875rem; color: var(--text-secondary); margin-left: 0.5rem;">
                        <?= htmlspecialchars($badgeProgress['badge']->description) ?>
                    </span>
                        </div>
                        <span style="font-weight: 600;">
                    <?= number_format($badgeProgress['progress']['percentage']) ?>%
                </span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar" style="width: <?= $badgeProgress['progress']['percentage'] ?>%"></div>
                    </div>
                    <div style="font-size: 0.8125rem; color: var(--text-secondary); margin-top: 0.5rem;">
                        <?= $badgeProgress['progress']['met'] ?> of <?= $badgeProgress['progress']['total'] ?>
                        requirements met
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Activity Trends Chart -->
    <div class="chart-container">
        <h2 class="section-title">Activity Trends (Last 30 Days)</h2>
        <canvas id="activityChart" class="chart"></canvas>
    </div>

    <!-- Recent Activity Feed -->
    <div class="activity-feed">
        <h2 class="section-title">Recent Activity</h2>
        <?php if ($recent_activities->isEmpty()): ?>
            <p style="text-align: center; color: var(--text-secondary); padding: 2rem;">
                No recent activity yet. Start engaging to see your activity here!
            </p>
        <?php else: ?>
            <?php foreach ($recent_activities as $activity): ?>
                <div class="activity-item">
                    <div class="activity-icon">
                        <?php
                        $icons = [
                                'comment' => '💬',
                                'like' => '❤️',
                                'read' => '📖',
                                'share' => '↗️',
                                'purchase' => '🛍️'
                        ];
                        echo $icons[$activity->activity_type] ?? '✨';
                        ?>
                    </div>
                    <div class="activity-content">
                        <div class="activity-text">
                            <?php
                            $actions = [
                                    'comment' => 'Posted a comment',
                                    'like' => 'Liked a page',
                                    'read' => 'Read a page',
                                    'share' => 'Shared content',
                                    'purchase' => 'Made a purchase'
                            ];
                            echo $actions[$activity->activity_type] ?? 'Activity';
                            ?>
                        </div>
                        <div class="activity-time">
                            <?= diffForHumans($activity->activity_date) ?>
                            <?php if ($activity->points > 0): ?>
                                <span class="activity-points">+<?= $activity->points ?> points</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<script>
    // Activity Trends Chart
    //const activityData = <?php //= json_encode($activity_trends) ?>//;
    //
    //const ctx = document.getElementById('activityChart').getContext('2d');
    //new Chart(ctx, {
    //    type: 'line',
    //    data: {
    //        labels: activityData.map(d => {
    //            const date = new Date(d.date);
    //            return date.toLocaleDateString('en-US', {month: 'short', day: 'numeric'});
    //        }),
    //        datasets: [{
    //            label: 'Activities',
    //            data: activityData.map(d => d.count),
    //            borderColor: '#667eea',
    //            backgroundColor: 'rgba(102, 126, 234, 0.1)',
    //            tension: 0.4,
    //            fill: true
    //        }]
    //    },
    //    options: {
    //        responsive: true,
    //        maintainAspectRatio: false,
    //        plugins: {
    //            legend: {
    //                display: false
    //            }
    //        },
    //        scales: {
    //            y: {
    //                beginAtZero: true,
    //                ticks: {
    //                    precision: 0
    //                }
    //            }
    //        }
    //    }
    //});
</script>
</body>
</html>