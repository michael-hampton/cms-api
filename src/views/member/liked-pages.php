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
    class LikedPagesStore {
        constructor() {
            this.state = {
                pages: [],
                totalLikes: 0,
                loading: false,
                error: null,
                removingIds: new Set(),
            };
            this.listeners = [];
        }

        subscribe(listener) {
            this.listeners.push(listener);
            listener(this.state);
        }

        setState(patch) {
            this.state = {
                ...this.state,
                ...patch,
            };
            this.listeners.forEach(listener => listener(this.state));
        }

        setRemoving(pageId, removing) {
            const removingIds = new Set(this.state.removingIds);

            if (removing) {
                removingIds.add(pageId);
            } else {
                removingIds.delete(pageId);
            }

            this.setState({removingIds});
        }
    }

    class LikedPages {
        constructor() {
            this.root = document.getElementById('liked-pages-root');
            this.store = new LikedPagesStore();

            this.store.subscribe(state => this.render(state));
        }

        async load() {
            this.store.setState({loading: true, error: null});

            try {
                const json = await api(`/api/${SITE_SLUG}/member/liked-pages`);
                this.store.setState({
                    pages: json.data.liked_pages || [],
                    totalLikes: json.data.total_likes || 0,
                    loading: false,
                });
            } catch (_) {
                this.store.setState({
                    error: 'Failed to load liked pages. Please refresh.',
                    loading: false,
                });
            }
        }

        render(state) {
            if (state.loading) {
                UI.render(this.root, [UI.el('p', {
                    style: {textAlign: 'center', color: 'var(--text-secondary)', padding: '2rem'},
                }, ['Loading liked pages…'])]);
                return;
            }

            if (state.error) {
                UI.render(this.root, [UI.el('p', {
                    style: {color: 'var(--danger-color)', textAlign: 'center'},
                }, [state.error])]);
                return;
            }

            const statsBar = UI.el('div', {className: 'stats-bar'}, [
                UI.el('div', {className: 'stat-item'}, [
                    UI.el('span', {className: 'stat-icon'}, ['❤️']),
                    UI.el('div', {className: 'stat-info'}, [
                        UI.el('h3', {}, [String(state.totalLikes)]),
                        UI.el('p', {}, ['Total Likes']),
                    ]),
                ]),
            ]);

            if (!state.pages.length) {
                UI.render(this.root, [statsBar, UI.emptyState({
                    icon: '💔',
                    title: 'No Liked Pages Yet',
                    body: 'Start exploring and like pages to build your collection.',
                    action: UI.el('a', {href: '/', className: 'btn-primary'}, ['Explore Content']),
                })]);
                return;
            }

            const grid = UI.el('div', {className: 'pages-grid'});
            state.pages.forEach(like => {
                const page = like.page;
                if (!page) return;
                grid.appendChild(this._card(page, like.liked_at, state.removingIds.has(page.id)));
            });

            UI.render(this.root, [statsBar, grid]);
        }

        _card(page, likedAt, isRemoving = false) {
            const img = page.listing_image_id
                ? UI.el('img', {src: `/images/${page.listing_image_id}`, alt: page.title, className: 'page-image'})
                : UI.el('div', {className: 'page-image'});

            const unlikeBtn = UI.el('button', {
                className: 'unlike-btn',
                disabled: isRemoving,
            }, [isRemoving ? 'Removing…' : 'Unlike']);
            unlikeBtn.addEventListener('click', e => {
                e.preventDefault();
                e.stopPropagation();
                this._unlike(page.id);
            });

            const card = UI.el('div', {className: 'page-card', 'data-page-id': page.id}, [
                UI.el('span', {className: 'like-badge'}, ['❤️']),
                img,
                UI.el('div', {className: 'page-content'}, [
                    UI.el('h3', {className: 'page-title-text'}, [page.title]),
                    page.listing_synopsis
                        ? UI.el('p', {className: 'page-excerpt'}, [page.listing_synopsis])
                        : null,
                    UI.el('div', {className: 'page-meta'}, [
                        UI.el('span', {}, [`❤️ Liked on ${UI.formatDate(likedAt)}`]),
                        unlikeBtn,
                    ]),
                ]),
            ]);

            card.addEventListener('click', e => {
                if (!e.target.closest('.unlike-btn')) window.location.href = `/${page.slug}`;
            });
            card.style.cursor = 'pointer';
            return card;
        }

        async _unlike(pageId) {
            if (!confirm('Remove this page from your liked pages?')) return;

            this.store.setRemoving(pageId, true);

            try {
                await api(`/api/${SITE_SLUG}/member/pages/like/${pageId}`, {
                    method: 'POST',
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                });
                this.store.setState({
                    pages: this.store.state.pages.filter(like => like.page?.id !== pageId),
                    totalLikes: Math.max(0, this.store.state.totalLikes - 1),
                });
            } catch (_) {
                UI.toast('Failed to unlike page. Please try again.', 'error');
            } finally {
                this.store.setRemoving(pageId, false);
            }
        }
    }

    document.addEventListener('DOMContentLoaded', () => new LikedPages().load());
</script>
</body>
</html>
