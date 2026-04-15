<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Dashboard - <?= htmlspecialchars($site->name) ?></title>
    <style>
        /* All original styles remain 1:1 */
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

        .container {
            max-width: 1200px;
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
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .stat-footer {
            margin-top: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .main-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .card {
            background: white;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
        }

        .chart-container {
            padding: 1.5rem;
            height: 300px;
            position: relative;
        }

        canvas {
            width: 100% !important;
            height: 100% !important;
        }

        .activity-list {
            list-style: none;
        }

        .activity-item {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: background-color 0.2s;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .activity-icon.points {
            background: #ecfdf5;
            color: #059669;
        }

        .activity-icon.badge {
            background: #eef2ff;
            color: #4f46e5;
        }

        .activity-info {
            flex: 1;
        }

        .activity-text {
            font-size: 0.9375rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .activity-date {
            font-size: 0.8125rem;
            color: var(--text-secondary);
        }

        .activity-points {
            font-weight: 600;
            font-size: 0.9375rem;
        }

        .activity-points.positive {
            color: var(--success-color);
        }

        .activity-points.negative {
            color: var(--danger-color);
        }

        .loader {
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
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

    </style>
</head>
<body>

@include('member.partials.nav')

<div class="container">

    <div class="page-header">
        <h1 class="page-title">Activity & Achievements</h1>
        <p class="page-subtitle">Track your engagement and earn badges</p>
    </div>

    <div class="stats-grid" id="stats-container">
        <div class="stat-card">
            <div class="loader">Loading stats...</div>
        </div>
    </div>

    <div id="badges-showcase-container"></div>
    <div id="next-badges-container"></div>

    <div class="main-grid">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Activity Overview</h2>
            </div>
            <div class="chart-container">
                <canvas id="activityChart"></canvas>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Recent Activity</h2>
                <a href="/member/activity"
                   style="font-size: 0.875rem; color: var(--primary-color); text-decoration: none;">View All</a>
            </div>
            <div class="activity-list" id="recent-activity-list">
                <div class="loader">Loading activity...</div>
            </div>
        </div>
    </div>
</div>

<script>
    const SITE_SLUG = '<?= \App\Framework\Support\SiteContext::slug()?>'

    async function initDashboard() {
        try {
            const SITE_SLUG = '<?= \App\Framework\Support\SiteContext::slug() ?>'
            const response = await fetch('/api/' + SITE_SLUG + '/member/activity');
            const result = await response.json();

            if (result.success) {
                const {progress, recent_activities, activity_trends, member_badges, next_badges} = result.data;

                // 1. FIX: Extract labels and counts from the array of objects
                const labels = activity_trends.map(item => item.date);
                const counts = activity_trends.map(item => item.count); // or item.points depending on what you want to graph

                // 2. Render UI Components
                renderStats(progress);
                renderBadges(member_badges); // Use member_badges from your JSON
                renderActivity(recent_activities);
                renderNextBadges(next_badges);

                // 3. Pass the newly created arrays to the chart function
                drawActivityChart(labels, counts);
            }
        } catch (error) {
            console.error('Failed to load dashboard:', error);
        }
    }

    function renderStats(progress) {
        const container = document.getElementById('stats-container');
        const s = progress.stats; // Access nested stats (comments, pages_read, etc.)

        container.innerHTML = `
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea20 0%, #764ba220 100%);">
                    🏆
                </div>
            </div>
            <div class="stat-value">${progress.total_points.toLocaleString()}</div>
            <div class="stat-label">Total Points</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background: linear-gradient(135deg, #10b98120 0%, #059f6920 100%);">
                    🎖️
                </div>
            </div>
            <div class="stat-value">${progress.badges_earned} / ${progress.badges_available}</div>
            <div class="stat-label">Badges Earned</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b20 0%, #d9770620 100%);">
                    💬
                </div>
            </div>
            <div class="stat-value">${s.comments.toLocaleString()}</div>
            <div class="stat-label">Comments Posted</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background: linear-gradient(135deg, #3b82f620 0%, #2563eb20 100%);">
                    📚
                </div>
            </div>
            <div class="stat-value">${s.pages_read.toLocaleString()}</div>
            <div class="stat-label">Pages Read</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ef444420 0%, #dc262620 100%);">
                    ❤️
                </div>
            </div>
            <div class="stat-value">${s.likes.toLocaleString()}</div>
            <div class="stat-label">Likes Given</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background: linear-gradient(135deg, #8b5cf620 0%, #7c3aed20 100%);">
                    📅
                </div>
            </div>
            <div class="stat-value">${s.member_days.toLocaleString()}</div>
            <div class="stat-label">Days as Member</div>
        </div>
    `;
    }

    function renderBadges(memberBadges) {
        const container = document.getElementById('badges-showcase-container');
        if (!memberBadges || memberBadges.length === 0) {
            container.innerHTML = ''; // Matches PHP if (!$member->badges->isEmpty())
            return;
        }

        const badgeItems = memberBadges.slice(0, 8).map(badge => `
        <div class="badge-item">
            <div class="badge-icon">${badge.icon || '🏆'}</div>
            <div class="badge-name">${badge.name}</div>
            <div class="badge-tier">${badge.tier}</div>
        </div>
    `).join('');

        container.innerHTML = `
        <div class="badges-showcase">
            <h2 class="section-title">Recent Badges</h2>
            <div class="badges-grid">
                ${badgeItems}
            </div>
            <div style="text-align: center; margin-top: 1.5rem;">
                <a href="/${SITE_SLUG}/member/activity/badges"
                   style="color: var(--primary-color); text-decoration: none; font-weight: 500;">
                    View All Badges →
                </a>
            </div>
        </div>
    `;
    }

    function renderNextBadges(nextBadges) {
        const container = document.getElementById('next-badges-container');
        if (!nextBadges || nextBadges.length === 0) {
            container.innerHTML = ''; // Matches PHP if (!empty($progress['next_badges']))
            return;
        }

        const progressItems = nextBadges.map(item => `
        <div class="progress-item">
            <div class="progress-header">
                <div>
                    <strong>${item.badge.icon || '✨'} ${item.badge.name}</strong>
                    <span style="font-size: 0.875rem; color: var(--text-secondary); margin-left: 0.5rem;">
                        ${item.badge.description || ''}
                    </span>
                </div>
                <span style="font-weight: 600;">
                    ${Math.round(item.progress.percentage)}%
                </span>
            </div>
            <div class="progress-bar-container">
                <div class="progress-bar" style="width: ${item.progress.percentage}%"></div>
            </div>
            <div style="font-size: 0.8125rem; color: var(--text-secondary); margin-top: 0.5rem;">
                ${item.progress.met} of ${item.progress.total} requirements met
            </div>
        </div>
    `).join('');

        container.innerHTML = `
        <div class="progress-section">
            <h2 class="section-title">Next Badges to Earn</h2>
            ${progressItems}
        </div>
    `;
    }

    function renderActivity(activities) {
        const container = document.getElementById('recent-activity-list');

        // 1. Handle Empty State (Match original PHP logic)
        if (!activities || activities.length === 0) {
            container.innerHTML = `
            <p style="text-align: center; color: var(--text-secondary); padding: 2rem;">
                No recent activity yet. Start engaging to see your activity here!
            </p>`;
            return;
        }

        // 2. Define Original PHP Mappings
        const icons = {
            'comment': '💬',
            'like': '❤️',
            'read': '📖',
            'share': '↗️',
            'purchase': '🛍️'
        };

        const actions = {
            'comment': 'Posted a comment',
            'like': 'Liked a page',
            'read': 'Read a page',
            'share': 'Shared content',
            'purchase': 'Made a purchase'
        };

        // 3. Build the Feed
        let html = '';

        html += activities.map(activity => {
            const icon = icons[activity.activity_type] || '✨';
            const actionText = actions[activity.activity_type] || 'Activity';

            // Use the nested date from your JSON: activity.activity_date.date
            const timeAgo = formatDiffForHumans(activity.activity_date);

            return `
            <div class="activity-item">
                <div class="activity-icon">
                    ${icon}
                </div>
                <div class="activity-content">
                    <div class="activity-text">
                        ${actionText}
                    </div>
                    <div class="activity-time">
                        ${timeAgo}
                        ${activity.points > 0 ? `
                            <span class="activity-points">+${activity.points} points</span>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
        }).join('');

        container.innerHTML = html;
    }

    /**
     * JS Equivalent of PHP diffForHumans()
     */
    function formatDiffForHumans(dateString) {
        const now = new Date();
        // Replace space with T to make it ISO compliant for safari/older browsers
        const past = new Date(dateString.replace(' ', 'T'));
        const diffInSeconds = Math.floor((now - past) / 1000);

        if (diffInSeconds < 60) return 'Just now';

        const minutes = Math.floor(diffInSeconds / 60);
        if (minutes < 60) return `${minutes}m ago`;

        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `${hours}h ago`;

        const days = Math.floor(hours / 24);
        if (days < 7) return `${days}d ago`;

        return past.toLocaleDateString('en-US', {month: 'short', day: 'numeric'});
    }

    function drawActivityChart(labels, counts) {
        const canvas = document.getElementById('activityChart');
        const ctx = canvas.getContext('2d');
        const padding = 40;
        const width = canvas.offsetWidth;
        const height = canvas.offsetHeight;

        // Match high-DPI displays
        canvas.width = width * window.devicePixelRatio;
        canvas.height = height * window.devicePixelRatio;
        ctx.scale(window.devicePixelRatio, window.devicePixelRatio);

        const maxCount = Math.max(...counts, 5);
        const minCount = 0;
        const xStep = (width - padding * 2) / (labels.length - 1);
        const yScale = (height - padding * 2) / (maxCount - minCount);

        // Draw Line
        ctx.beginPath();
        ctx.strokeStyle = '#667eea';
        ctx.lineWidth = 3;
        ctx.lineJoin = 'round';

        counts.forEach((count, i) => {
            const x = padding + i * xStep;
            const y = height - padding - (count - minCount) * yScale;
            if (i === 0) ctx.moveTo(x, y);
            else ctx.lineTo(x, y);
        });
        ctx.stroke();

        // Draw Points & Labels (Original dashboard logic)
        ctx.fillStyle = '#667eea';
        counts.forEach((count, i) => {
            const x = padding + i * xStep;
            const y = height - padding - (count - minCount) * yScale;
            ctx.beginPath();
            ctx.arc(x, y, 4, 0, Math.PI * 2);
            ctx.fill();

            // Draw date label on X axis
            ctx.fillStyle = '#6b7280';
            ctx.font = '10px sans-serif';
            ctx.textAlign = 'center';
            if (i % 5 === 0 || i === labels.length - 1) {
                const date = new Date(labels[i]);
                ctx.fillText(date.toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric'
                }), x, height - padding + 20);
            }
            ctx.fillStyle = '#667eea';
        });
    }

    document.addEventListener('DOMContentLoaded', initDashboard);
</script>
</body>
</html>