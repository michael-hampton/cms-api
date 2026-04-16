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
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
        }

        /* Stats bar */
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

        /* Timeline */
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
            flex-wrap: wrap;
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

        /* Empty state */
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

        @media (max-width: 768px) {
            .container {
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
        }
    </style>
</head>
<body>

@include('member._header')

<div class="toast-container" id="toastContainer"></div>

<div class="container">
    <div class="page-header">
        <h1 class="page-title">📚 Reading History</h1>
        <p class="page-subtitle">Track your reading journey and revisit pages you've explored</p>
    </div>

    <div id="reading-history-root">
        <!-- Skeleton loader -->
        <div class="stats-bar">
            <div class="stat-item">
                <span class="stat-icon">📖</span>
                <div class="stat-info">
                    <div class="skeleton" style="height:1.75rem;width:3rem;margin-bottom:.25rem;"></div>
                    <div class="skeleton" style="height:.875rem;width:7rem;"></div>
                </div>
            </div>
        </div>
        <div class="timeline">
            <?php for ($i = 0; $i < 4; $i++): ?>
                <div class="timeline-item" style="opacity:<?= 1 - $i * 0.15 ?>;">
                    <div class="timeline-marker"></div>
                    <div class="timeline-content">
                        <div class="skeleton" style="height:.75rem;width:25%;margin-bottom:.75rem;"></div>
                        <div class="skeleton" style="height:1.25rem;width:60%;margin-bottom:.5rem;"></div>
                        <div class="skeleton" style="height:.875rem;width:80%;margin-bottom:.5rem;"></div>
                        <div class="skeleton" style="height:.875rem;width:40%;"></div>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<script>
    const SITE_SLUG = '<?= htmlspecialchars(\App\Framework\Support\SiteContext::slug()) ?>';

    /* ─── Toast ──────────────────────────────────────────── */
    function showToast(message, type = 'info', duration = 5000) {
        const icons = {error: '✕', info: 'ℹ'};
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <span>${icons[type] || 'ℹ'}</span>
            <span style="flex:1;">${message}</span>
            <button class="toast-close" onclick="this.parentElement.remove()">×</button>`;
        container.appendChild(toast);
        setTimeout(() => {
            if (!toast.parentElement) return;
            toast.style.animation = 'slideOut 0.3s ease forwards';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }

    /* ─── Load ───────────────────────────────────────────── */
    async function loadReadingHistory() {
        try {
            const res = await fetch(`/api/${SITE_SLUG}/member/reading-history`);
            if (!res.ok) throw new Error('Server error ' + res.status);
            const json = await res.json();
            if (!json.success) throw new Error('Failed to load');
            renderHistory(json.data.recently_viewed, json.data.total_pages_read);
        } catch (e) {
            console.error(e);
            showToast('Failed to load reading history. Please refresh.', 'error');
            document.getElementById('reading-history-root').innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">⚠️</div>
                    <h2>Failed to Load</h2>
                    <p>Please try refreshing the page.</p>
                    <button class="btn-primary" style="border:none;cursor:pointer;" onclick="loadReadingHistory()">Retry</button>
                </div>`;
        }
    }

    /* ─── Render ─────────────────────────────────────────── */
    function renderHistory(views, totalPagesRead) {
        const root = document.getElementById('reading-history-root');

        const statsBar = `
            <div class="stats-bar">
                <div class="stat-item">
                    <span class="stat-icon">📖</span>
                    <div class="stat-info">
                        <h3>${totalPagesRead}</h3>
                        <p>Unique Pages Read</p>
                    </div>
                </div>
            </div>`;

        if (!views || views.length === 0) {
            root.innerHTML = statsBar + `
                <div class="empty-state">
                    <div class="empty-state-icon">📚</div>
                    <h2>No Reading History Yet</h2>
                    <p>Start exploring content to build your reading history.</p>
                    <a href="/" class="btn-primary">Start Reading</a>
                </div>`;
            return;
        }

        const items = views.map(view => {
            const page = view.page;
            if (!page) return '';

            /* Category badge — restored from original */
            const categoryBadge = page.category_name
                ? `<span class="meta-badge">📁 ${esc(page.category_name)}</span>`
                : '';

            return `
                <div class="timeline-item">
                    <div class="timeline-marker"></div>
                    <div class="timeline-content">
                        <div class="timeline-date"><span>🕐</span>${timeAgo(view.viewed_at)}</div>
                        <h3 class="timeline-title">
                            <a href="/${esc(page.slug)}">${esc(page.title)}</a>
                        </h3>
                        ${page.listing_synopsis ? `<p class="timeline-excerpt">${esc(page.listing_synopsis)}</p>` : ''}
                        <div class="timeline-meta">
                            ${categoryBadge}
                            <span class="meta-badge">📅 ${formatDate(page.created_at)}</span>
                        </div>
                    </div>
                </div>`;
        }).filter(Boolean).join('');

        root.innerHTML = statsBar + `<div class="timeline">${items}</div>`;
    }

    /* ─── Helpers ────────────────────────────────────────── */
    function timeAgo(str) {
        if (!str) return '';
        const dateStr = (str.date || str).replace(' ', 'T');
        const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
        if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
        if (diff < 604800) return Math.floor(diff / 86400) + ' days ago';
        return formatDate(str);
    }

    function formatDate(str) {
        if (!str) return '';
        const d = str.date || str;
        return new Date(d).toLocaleDateString('en-GB', {year: 'numeric', month: 'short', day: 'numeric'});
    }

    function esc(str) {
        if (str == null) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    document.addEventListener('DOMContentLoaded', loadReadingHistory);
</script>
</body>
</html>