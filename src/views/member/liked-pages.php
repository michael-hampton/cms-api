<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liked Pages - <?= htmlspecialchars($site->name ?? 'Site') ?></title>
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
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
            --shadow: 0 1px 3px rgba(0, 0, 0, .1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, .1);
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
            margin-bottom: .5rem;
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
            gap: .75rem;
        }

        .stat-icon {
            font-size: 2rem;
        }

        .stat-info h3 {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .stat-info p {
            font-size: .875rem;
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
            transition: all .3s ease;
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
            display: block;
            background: linear-gradient(135deg, #667eea20, #764ba220);
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
            margin-bottom: .5rem;
            line-height: 1.4;
        }
        .page-excerpt {
            font-size: .875rem;
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
            font-size: .8125rem;
            color: var(--text-secondary);
        }

        .like-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--danger-color);
            color: white;
            padding: .5rem .75rem;
            border-radius: .5rem;
            font-size: 1rem;
            box-shadow: var(--shadow);
        }

        .unlike-btn {
            background: var(--bg-light);
            border: none;
            padding: .5rem 1rem;
            border-radius: .5rem;
            cursor: pointer;
            font-size: .875rem;
            color: var(--text-secondary);
            transition: all .2s;
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
            opacity: .5;
        }

        .empty-state h2 {
            font-size: 1.5rem;
            margin-bottom: .5rem;
        }

        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        .btn-primary {
            display: inline-block;
            padding: .75rem 1.5rem;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            text-decoration: none;
            border-radius: .5rem;
            font-weight: 500;
            transition: transform .2s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
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

            .pages-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
@include('member._header')

<div class="container">
    <div class="page-header">
        <h1 class="page-title">❤️ Liked Pages</h1>
        <p class="page-subtitle">Your collection of favourite pages and content</p>
    </div>
    <div id="liked-pages-root">
        <p style="text-align:center;color:var(--text-secondary);padding:2rem;">Loading liked pages…</p>
    </div>
</div>

<script>
    const SITE_SLUG = '<?= htmlspecialchars(\App\Framework\Support\SiteContext::slug()) ?>';

    async function loadLikedPages() {
        try {
            const res = await fetch(`/api/${SITE_SLUG}/member/liked-pages`);
            if (res.status === 401) {
                window.location.href = `/${SITE_SLUG}/member/login`;
                return;
            }
            const json = await res.json();
            if (!json.success) throw new Error('Failed to load');
            renderLikedPages(json.data.liked_pages, json.data.total_likes);
        } catch {
            document.getElementById('liked-pages-root').innerHTML =
                '<p style="color:var(--danger-color);text-align:center;">Failed to load liked pages. Please refresh.</p>';
        }
    }

    function renderLikedPages(likedPages, totalLikes) {
        const root = document.getElementById('liked-pages-root');

        const statsBar = `
            <div class="stats-bar">
                <div class="stat-item">
                    <span class="stat-icon">❤️</span>
                    <div class="stat-info"><h3>${totalLikes}</h3><p>Total Likes</p></div>
                </div>
            </div>`;

        if (!likedPages.length) {
            root.innerHTML = statsBar + `
                <div class="empty-state">
                    <div class="empty-state-icon">💔</div>
                    <h2>No Liked Pages Yet</h2>
                    <p>Start exploring and like pages to build your collection of favourites.</p>
                    <a href="/" class="btn-primary">Explore Content</a>
                </div>`;
            return;
        }

        const cards = likedPages.map(like => {
            const page = like.page;
            if (!page) return '';
            return `
                <div class="page-card" data-page-id="${page.id}">
                    <span class="like-badge">❤️</span>
                    <a href="/${escHtml(page.slug)}" style="text-decoration:none;color:inherit;display:contents;">
                        ${page.listing_image_id
                ? `<img src="/images/${page.listing_image_id}" alt="${escHtml(page.title)}" class="page-image">`
                : `<div class="page-image"></div>`}
                        <div class="page-content">
                            <h3 class="page-title-text">${escHtml(page.title)}</h3>
                            ${page.listing_synopsis
                ? `<p class="page-excerpt">${escHtml(page.listing_synopsis)}</p>`
                : ''}
                            <div class="page-meta">
                                <span>❤️ Liked on ${formatDate(like.liked_at)}</span>
                                <button class="unlike-btn"
                                    onclick="unlikePage(event, ${page.id})">Unlike</button>
                            </div>
                        </div>
                    </a>
                </div>`;
        }).join('');

        root.innerHTML = statsBar + `<div class="pages-grid">${cards}</div>`;
    }

    async function unlikePage(event, pageId) {
        event.preventDefault();
        event.stopPropagation();
        if (!confirm('Remove this page from your liked pages?')) return;
        try {
            // Uses SITE_SLUG constant — not a hardcoded PHP string inside JS
            const res = await fetch(`/api/${SITE_SLUG}/pages/like/${pageId}`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            });
            const data = await res.json();
            if (data.success) {
                // Animate card out before reloading
                const card = document.querySelector(`[data-page-id="${pageId}"]`);
                if (card) {
                    card.style.transition = 'opacity .3s,transform .3s';
                    card.style.opacity = '0';
                    card.style.transform = 'scale(.95)';
                    setTimeout(() => loadLikedPages(), 300);
                } else {
                    loadLikedPages();
                }
            } else {
                alert('Failed to unlike page');
            }
        } catch {
            alert('An error occurred. Please try again.');
        }
    }

    function formatDate(str) {
        return str ? new Date(str).toLocaleDateString('en-GB', {year: 'numeric', month: 'short', day: 'numeric'}) : '';
    }

    function escHtml(str) {
        if (str == null) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    document.addEventListener('DOMContentLoaded', loadLikedPages);
</script>
</body>
</html>