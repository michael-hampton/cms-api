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

        /* Toast */
        .toast-container {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            pointer-events: none;
        }

        .toast {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.25rem;
            border-radius: 0.75rem;
            font-size: 0.9375rem;
            font-weight: 500;
            box-shadow: var(--shadow-lg);
            pointer-events: all;
            animation: slideIn 0.3s ease;
            max-width: 360px;
        }

        .toast.success {
            background: #ecfdf5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .toast.error {
            background: #fef2f2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .toast.info {
            background: #eff6ff;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }

        .toast-close {
            margin-left: auto;
            background: none;
            border: none;
            cursor: pointer;
            color: inherit;
            opacity: 0.6;
            font-size: 1.1rem;
        }

        .toast-close:hover {
            opacity: 1;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideOut {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }

        /* Layout */
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

        /* Stats */
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

        /* Badges showcase */
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

        /* Progress section — next badges */
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
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }

        .progress-header-left {
            flex: 1;
            margin-right: 1rem;
        }

        .progress-header-left strong {
            font-size: 0.9375rem;
        }

        .progress-header-left span {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-left: 0.5rem;
        }

        .progress-pct {
            font-weight: 600;
            white-space: nowrap;
        }

        .progress-bar-container {
            height: 0.75rem;
            background: var(--bg-light);
            border-radius: 0.5rem;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border-radius: 0.5rem;
            transition: width 0.6s ease;
        }

        .progress-meta {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            margin-top: 0.4rem;
        }

        /* Chart */
        .chart-container {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .chart-wrapper {
            position: relative;
            height: 300px;
        }

        canvas {
            display: block;
        }

        /* Activity feed */
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
            margin-left: 0.5rem;
        }

        /* Skeleton */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: shimmer 1.4s infinite;
            border-radius: 0.5rem;
        }

        @keyframes shimmer {
            0% {
                background-position: 200% 0;
            }
            100% {
                background-position: -200% 0;
            }
        }

        .skeleton-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: var(--shadow);
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
</head>
<body>

@include('member._header')

<div class="toast-container" id="toastContainer"></div>

<main class="container">
    <div class="page-header">
        <h1 class="page-title">Activity & Achievements</h1>
        <p class="page-subtitle">Track your engagement and earn badges</p>
    </div>

    <!-- Stats -->
    <div class="stats-grid" id="stats-container">
        <?php for ($i = 0; $i < 6; $i++): ?>
            <div class="skeleton-card">
                <div class="skeleton" style="height:2rem;width:40%;margin-bottom:0.75rem;"></div>
                <div class="skeleton" style="height:1rem;width:60%;"></div>
            </div>
        <?php endfor; ?>
    </div>

    <!-- Badges showcase injected here -->
    <div id="badges-showcase-container"></div>

    <!-- Next badges injected here -->
    <div id="next-badges-container"></div>

    <!-- Chart -->
    <div class="chart-container">
        <h2 class="section-title">Activity Trends (Last 30 Days)</h2>
        <div class="chart-wrapper">
            <canvas id="activityChart"></canvas>
        </div>
    </div>

    <!-- Activity feed -->
    <div class="activity-feed">
        <h2 class="section-title">Recent Activity</h2>
        <div id="recent-activity-list">
            <div style="text-align:center;padding:2rem;color:var(--text-secondary);">Loading activity…</div>
        </div>
    </div>
</main>

<script>
    class StatsSection {
        render(progress) {
            const s = progress.stats;
            const cards = [
                {icon: '🏆', value: progress.total_points.toLocaleString(), label: 'Total Points'},
                {icon: '🎖️', value: `${progress.badges_earned} / ${progress.badges_available}`, label: 'Badges Earned'},
                {icon: '💬', value: s.comments.toLocaleString(), label: 'Comments Posted'},
                {icon: '📚', value: s.pages_read.toLocaleString(), label: 'Pages Read'},
                {icon: '❤️', value: s.likes.toLocaleString(), label: 'Likes Given'},
                {icon: '📅', value: s.member_days.toLocaleString(), label: 'Days as Member'},
            ];
            UI.render(document.getElementById('stats-container'), cards.map(c =>
                UI.el('div', {className: 'stat-card'}, [
                    UI.el('div', {className: 'stat-header'}, [
                        UI.el('div', {className: 'stat-icon'}, [c.icon]),
                    ]),
                    UI.el('div', {className: 'stat-value'}, [c.value]),
                    UI.el('div', {className: 'stat-label'}, [c.label]),
                ])
            ));
        }
    }

    class BadgesShowcase {
        render(memberBadges) {
            const container = document.getElementById('badges-showcase-container');
            if (!memberBadges?.length) {
                container.innerHTML = '';
                return;
            }

            const grid = UI.el('div', {className: 'badges-grid'});
            memberBadges.slice(0, 8).forEach(badge => {
                grid.appendChild(UI.el('div', {className: 'badge-item'}, [
                    UI.el('div', {className: 'badge-icon'}, [badge.icon ?? '🏆']),
                    UI.el('div', {className: 'badge-name'}, [badge.name]),
                    UI.el('div', {className: 'badge-tier'}, [badge.tier]),
                ]));
            });

            const link = UI.el('a', {
                href: `/${SITE_SLUG}/member/activity/badges`,
                style: {color: 'var(--primary-color)', textDecoration: 'none', fontWeight: '500'},
            }, ['View All Badges →']);

            UI.render(container, [
                UI.el('div', {className: 'badges-showcase'}, [
                    UI.el('h2', {className: 'section-title'}, ['Recent Badges']),
                    grid,
                    UI.el('div', {style: {textAlign: 'center', marginTop: '1.5rem'}}, [link]),
                ]),
            ]);
        }
    }

    class NextBadges {
        render(nextBadges) {
            const container = document.getElementById('next-badges-container');
            if (!nextBadges?.length) {
                container.innerHTML = '';
                return;
            }

            const items = nextBadges.map(item => {
                const pct = Math.min(100, Math.round(item.progress.percentage));
                const bar = UI.el('div', {className: 'progress-bar', style: {width: `${pct}%`}});
                return UI.el('div', {className: 'progress-item'}, [
                    UI.el('div', {className: 'progress-header'}, [
                        UI.el('div', {className: 'progress-header-left'}, [
                            UI.el('strong', {}, [`${item.badge.icon ?? '✨'} ${item.badge.name}`]),
                            UI.el('span', {}, [` ${item.badge.description ?? ''}`]),
                        ]),
                        UI.el('span', {className: 'progress-pct'}, [`${pct}%`]),
                    ]),
                    UI.el('div', {className: 'progress-bar-container'}, [bar]),
                    UI.el('div', {className: 'progress-meta'},
                        [`${item.progress.met} of ${item.progress.total} requirements met`]),
                ]);
            });

            UI.render(container, [
                UI.el('div', {className: 'progress-section'}, [
                    UI.el('h2', {className: 'section-title'}, ['Next Badges to Earn']),
                    ...items,
                ]),
            ]);
        }
    }

    class ActivityFeed {
        render(activities) {
            const container = document.getElementById('recent-activity-list');
            if (!activities?.length) {
                UI.render(container, [UI.el('p', {
                    style: {textAlign: 'center', color: 'var(--text-secondary)', padding: '2rem'},
                }, ['No recent activity yet.'])]);
                return;
            }

            const icons = {comment: '💬', like: '❤️', read: '📖', share: '↗️', purchase: '🛍️'};
            const labels = {
                comment: 'Posted a comment', like: 'Liked a page', read: 'Read a page',
                share: 'Shared content', purchase: 'Made a purchase'
            };

            UI.render(container, activities.map(a => {
                const pts = a.points > 0
                    ? UI.el('span', {className: 'activity-points'}, [`+${a.points} points`])
                    : null;
                return UI.el('div', {className: 'activity-item'}, [
                    UI.el('div', {className: 'activity-icon'}, [icons[a.activity_type] ?? '✨']),
                    UI.el('div', {className: 'activity-content'}, [
                        UI.el('div', {className: 'activity-text'}, [labels[a.activity_type] ?? 'Activity']),
                        UI.el('div', {className: 'activity-time'}, [UI.timeAgo(a.activity_date), pts]),
                    ]),
                ]);
            }));
        }
    }

    class ActivityChart {
        draw(labels, counts) {
            const canvas = document.getElementById('activityChart');
            const wrapper = canvas.parentElement;
            const W = wrapper.offsetWidth || 800;
            const H = wrapper.offsetHeight || 300;
            const DPR = window.devicePixelRatio || 1;
            canvas.width = W * DPR;
            canvas.height = H * DPR;
            canvas.style.width = W + 'px';
            canvas.style.height = H + 'px';
            const ctx = canvas.getContext('2d');
            ctx.scale(DPR, DPR);
            if (!labels.length) return;

            const pad = {top: 24, right: 24, bottom: 48, left: 48};
            const plotW = W - pad.left - pad.right;
            const plotH = H - pad.top - pad.bottom;
            const maxCount = Math.max(...counts, 5);
            const xStep = plotW / Math.max(counts.length - 1, 1);
            const yScale = plotH / maxCount;

            // Grid
            ctx.strokeStyle = '#f0f0f0';
            ctx.lineWidth = 1;
            for (let i = 1; i <= 5; i++) {
                const y = pad.top + plotH - (i / 5) * plotH;
                ctx.beginPath();
                ctx.moveTo(pad.left, y);
                ctx.lineTo(pad.left + plotW, y);
                ctx.stroke();
            }

            // Y labels
            ctx.fillStyle = '#6b7280';
            ctx.font = '11px sans-serif';
            ctx.textAlign = 'right';
            ctx.textBaseline = 'middle';
            for (let i = 0; i <= 5; i++) {
                const y = pad.top + plotH - (i / 5) * plotH;
                ctx.fillText(Math.round((i / 5) * maxCount), pad.left - 8, y);
            }

            // Axes
            ctx.strokeStyle = '#d1d5db';
            ctx.lineWidth = 1.5;
            ctx.beginPath();
            ctx.moveTo(pad.left, pad.top);
            ctx.lineTo(pad.left, pad.top + plotH);
            ctx.lineTo(pad.left + plotW, pad.top + plotH);
            ctx.stroke();

            // Fill
            const grad = ctx.createLinearGradient(0, pad.top, 0, pad.top + plotH);
            grad.addColorStop(0, 'rgba(102,126,234,0.25)');
            grad.addColorStop(1, 'rgba(102,126,234,0.02)');
            ctx.beginPath();
            counts.forEach((c, i) => {
                const x = pad.left + i * xStep, y = pad.top + plotH - c * yScale;
                i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
            });
            ctx.lineTo(pad.left + (counts.length - 1) * xStep, pad.top + plotH);
            ctx.lineTo(pad.left, pad.top + plotH);
            ctx.closePath();
            ctx.fillStyle = grad;
            ctx.fill();

            // Line
            ctx.beginPath();
            ctx.strokeStyle = '#667eea';
            ctx.lineWidth = 2.5;
            ctx.lineJoin = 'round';
            counts.forEach((c, i) => {
                const x = pad.left + i * xStep, y = pad.top + plotH - c * yScale;
                i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
            });
            ctx.stroke();

            // Points + labels
            counts.forEach((c, i) => {
                const x = pad.left + i * xStep, y = pad.top + plotH - c * yScale;
                ctx.beginPath();
                ctx.arc(x, y, 4, 0, Math.PI * 2);
                ctx.fillStyle = 'white';
                ctx.strokeStyle = '#667eea';
                ctx.lineWidth = 2;
                ctx.fill();
                ctx.stroke();
                if (c > 0) {
                    ctx.fillStyle = '#374151';
                    ctx.font = '11px sans-serif';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'bottom';
                    ctx.fillText(c, x, y - 6);
                }
            });

            // X labels
            const step = Math.max(1, Math.ceil(labels.length / 8));
            ctx.fillStyle = '#6b7280';
            ctx.font = '11px sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'top';
            labels.forEach((label, i) => {
                if (i % step === 0 || i === labels.length - 1) {
                    const x = pad.left + i * xStep;
                    ctx.fillText(new Date(label).toLocaleDateString('en-US', {month: 'short', day: 'numeric'}),
                        x, pad.top + plotH + 10);
                }
            });
        }
    }

    class ActivityDashboard {
        constructor() {
            this.stats = new StatsSection();
            this.badges = new BadgesShowcase();
            this.next = new NextBadges();
            this.feed = new ActivityFeed();
            this.chart = new ActivityChart();
        }

        async init() {
            try {
                const json = await api(`/api/${SITE_SLUG}/member/activity`);
                const {progress, recent_activities, activity_trends, member_badges, next_badges} = json.data;

                this.stats.render(progress);
                this.badges.render(member_badges);
                this.next.render(next_badges);
                this.feed.render(recent_activities);

                requestAnimationFrame(() => {
                    this.chart.draw(
                        (activity_trends ?? []).map(d => d.date),
                        (activity_trends ?? []).map(d => d.count),
                    );
                });
            } catch {
                UI.toast('Failed to load activity data. Please refresh.', 'error');
                UI.render(document.getElementById('stats-container'), [
                    UI.el('p', {style: {color: 'var(--danger-color)', padding: '1rem'}},
                        ['Could not load stats.']),
                ]);
            }
        }
    }

    document.addEventListener('DOMContentLoaded', () => new ActivityDashboard().init());

</script>
</body>
</html>