<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Badges - <?= htmlspecialchars($site->name) ?></title>
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
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Toast notifications */
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

        .toast-icon {
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .toast-close {
            margin-left: auto;
            background: none;
            border: none;
            cursor: pointer;
            color: inherit;
            opacity: 0.6;
            font-size: 1.1rem;
            padding: 0;
            line-height: 1;
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

        /* Page header */
        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        /* Loading skeleton */
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
            min-width: 200px;
        }

        /* Carousel */
        .carousel-section {
            margin-bottom: 3rem;
            position: relative;
        }

        .carousel-container {
            position: relative;
            overflow: hidden;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .badges-carousel {
            display: flex;
            gap: 1rem;
            padding: 1rem 0;
            transition: transform 0.5s ease;
        }

        .carousel-controls {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        .carousel-btn {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: white;
            border: 2px solid var(--border-color);
            color: var(--primary-color);
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .carousel-btn:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            transform: scale(1.1);
        }

        .carousel-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
            transform: none;
        }

        .carousel-dots {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            margin-top: 1rem;
        }
        .carousel-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--border-color);
            cursor: pointer;
            transition: all 0.3s;
            border: none;
        }

        .carousel-dot.active {
            background: var(--primary-color);
            width: 24px;
            border-radius: 4px;
        }

        .carousel-badge {
            min-width: 200px;
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            text-align: center;
            transition: transform 0.3s;
            position: relative;
            flex-shrink: 0;
        }

        .carousel-badge:hover {
            transform: translateY(-4px);
        }

        .carousel-badge.locked {
            opacity: 0.6;
        }

        /* Tier indicators */
        .badge-tier-indicator {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            border-radius: 1rem 1rem 0 0;
        }

        .badge-tier-indicator.bronze {
            background: linear-gradient(90deg, #cd7f32, #b87333);
        }

        .badge-tier-indicator.silver {
            background: linear-gradient(90deg, #c0c0c0, #a8a8a8);
        }

        .badge-tier-indicator.gold {
            background: linear-gradient(90deg, #ffd700, #ffed4e);
        }

        .badge-tier-indicator.platinum {
            background: linear-gradient(90deg, #e5e4e2, #b9b8b6);
        }

        .carousel-badge-icon {
            font-size: 3rem;
            margin-bottom: 0.5rem;
        }

        .carousel-badge-name {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .carousel-badge-tier {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 1rem;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .carousel-badge-tier.bronze {
            background: #cd7f3220;
            color: #cd7f32;
        }

        .carousel-badge-tier.silver {
            background: #c0c0c020;
            color: #808080;
        }

        .carousel-badge-tier.gold {
            background: #ffd70020;
            color: #b8860b;
        }

        .carousel-badge-tier.platinum {
            background: #e5e4e220;
            color: #666;
        }

        /* Filter tabs */
        .filter-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 0.5rem;
        }
        .filter-tab {
            padding: 0.5rem 1.25rem;
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 9999px;
            cursor: pointer;
            font-weight: 500;
            color: var(--text-secondary);
            transition: all 0.3s;
        }

        .filter-tab:hover {
            color: var(--primary-color);
            border-color: var(--primary-color);
            background: rgba(102, 126, 234, 0.05);
        }

        .filter-tab.active {
            color: white;
            background: var(--primary-color);
            border-color: var(--primary-color);
        }

        /* Badge grid */
        .badges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
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

        .badge-points {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .badge-earned-date {
            font-size: 0.8125rem;
            color: var(--text-secondary);
        }

        /* Show more */
        .show-more-container {
            text-align: center;
            margin-top: 2rem;
        }
        .show-more-btn {
            padding: 0.75rem 2rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .show-more-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .show-more-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        /* Empty / no-results */
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

        .no-results-message {
            text-align: center;
            padding: 3rem 2rem;
            background: white;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            min-height: 300px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            grid-column: 1 / -1;
        }

        .no-results-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .no-results-message h3 {
            font-size: 1.25rem;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .no-results-message p {
            color: var(--text-secondary);
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .badges-grid {
                grid-template-columns: 1fr;
            }

            .carousel-badge {
                min-width: 150px;
            }
        }
    </style>
</head>
<body>

@include('member._header')

<div class="toast-container" id="toastContainer"></div>

<main class="container">
    <div class="page-header">
        <h1 class="page-title">🏆 My Badges</h1>
        <p id="badge-stats-summary" style="color: var(--text-secondary); margin-top: 0.5rem;">
            Loading badges…
        </p>
    </div>

    <!-- Carousel -->
    <div class="carousel-section">
        <h2 class="section-title">All Badges</h2>
        <div class="carousel-container">
            <div class="badges-carousel" id="badgesCarousel">
                <!-- skeleton placeholders while loading -->
                <?php for ($i = 0; $i < 5; $i++): ?>
                    <div class="skeleton-card" style="min-width:200px;">
                        <div class="skeleton"
                             style="height:3rem;width:3rem;border-radius:50%;margin:0 auto 0.75rem;"></div>
                        <div class="skeleton" style="height:1rem;margin-bottom:0.5rem;"></div>
                        <div class="skeleton" style="height:0.75rem;width:60%;margin:0 auto;"></div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
        <div class="carousel-controls">
            <button class="carousel-btn" id="prevBtn" onclick="moveCarousel(-1)" disabled>←</button>
            <button class="carousel-btn" id="nextBtn" onclick="moveCarousel(1)">→</button>
        </div>
        <div class="carousel-dots" id="carouselDots"></div>
    </div>

    <!-- Earned badges -->
    <div class="section" id="earnedSection" style="display:none;">
        <h2 class="section-title">My Badges</h2>
        <div class="filter-tabs" id="earnedFilterTabs">
            <button class="filter-tab active" data-cat="all" onclick="filterToggle(this, 'all', 'earned')">All</button>
        </div>
        <div class="badges-grid" id="earnedBadgesGrid"></div>
    </div>

    <!-- Unearned badges -->
    <div class="section" id="unearnedSection" style="display:none;">
        <h2 class="section-title">Earn These Badges</h2>
        <div class="filter-tabs" id="unearnedFilterTabs">
            <button class="filter-tab active" data-cat="all" onclick="filterToggle(this, 'all', 'unearned')">All
            </button>
        </div>
        <div class="badges-grid" id="unearnedBadgesGrid"></div>
        <div class="show-more-container" id="showMoreWrapper" style="display:none;">
            <button class="show-more-btn" id="showMoreBtn" onclick="showMoreBadges()">Show More</button>
        </div>
    </div>

    <!-- Fully empty state -->
    <div id="empty-state" class="empty-state" style="display:none;">
        <div class="empty-state-icon">🏆</div>
        <h2>No Badges Available</h2>
        <p style="color: var(--text-secondary); margin-top: 0.5rem;">Check back soon for new badges to earn!</p>
    </div>
</main>

<script>
    const ITEMS_PER_PAGE = 6;

    class Carousel {
        constructor(containerId) {
            this.container = document.getElementById(containerId);
            this.index = 0;
            this.timer = null;
        }

        get items() {
            return this.container.querySelectorAll('.carousel-badge');
        }

        get perView() {
            return window.innerWidth < 768 ? 2 : window.innerWidth < 1024 ? 3 : 5;
        }

        get totalPages() {
            return Math.ceil(this.items.length / this.perView);
        }

        build(earned, unearned) {
            UI.render(this.container, [
                ...earned.map(b => this._card(b, false)),
                ...unearned.map(b => this._card(b, true)),
            ]);
            this._buildDots();
            this._apply();
            this._autoScroll();
            this.container.addEventListener('mouseenter', () => this._stopAuto());
            this.container.addEventListener('mouseleave', () => this._autoScroll());
        }

        _card(b, locked) {
            return UI.el('div', {className: `carousel-badge${locked ? ' locked' : ''}`}, [
                UI.el('div', {className: `badge-tier-indicator ${b.tier ?? ''}`}),
                UI.el('div', {className: 'carousel-badge-icon'}, [locked ? '🔒' : (b.icon ?? '🏆')]),
                UI.el('div', {className: 'carousel-badge-name'}, [b.name]),
                UI.el('div', {className: `carousel-badge-tier ${b.tier ?? ''}`}, [b.tier ?? '']),
            ]);
        }

        _buildDots() {
            const dots = document.getElementById('carouselDots');
            UI.render(dots, Array.from({length: this.totalPages}, (_, i) =>
                UI.el('button', {
                    className: `carousel-dot${i === 0 ? ' active' : ''}`,
                    'aria-label': `Go to page ${i + 1}`,
                    onclick: () => this.goTo(i),
                })
            ));
        }

        _apply() {
            const BADGE_WIDTH = 216;
            this.container.style.transform = `translateX(${-this.index * BADGE_WIDTH * this.perView}px)`;
            document.querySelectorAll('.carousel-dot').forEach((d, i) =>
                d.classList.toggle('active', i === this.index));
            document.getElementById('prevBtn').disabled = this.index === 0;
            document.getElementById('nextBtn').disabled = this.index >= this.totalPages - 1;
        }

        move(dir) {
            this.index = Math.max(0, Math.min(this.totalPages - 1, this.index + dir));
            this._apply();
        }

        goTo(i) {
            this.index = Math.max(0, Math.min(this.totalPages - 1, i));
            this._apply();
        }

        _autoScroll() {
            this._stopAuto();
            if (this.totalPages <= 1) return;
            this.timer = setInterval(() => {
                this.index = (this.index + 1) % this.totalPages;
                this._apply();
            }, 5000);
        }

        _stopAuto() {
            clearInterval(this.timer);
            this.timer = null;
        }

        onResize() {
            if (this.index >= this.totalPages) this.index = Math.max(0, this.totalPages - 1);
            this._buildDots();
            this._apply();
            this._autoScroll();
        }
    }

    class BadgeGrid {
        constructor(gridId, tabsId, badges, earned) {
            this.grid = document.getElementById(gridId);
            this.tabs = document.getElementById(tabsId);
            this.badges = badges;
            this.earned = earned;
            this.page = 1;
            this.category = 'all';
        }

        buildTabs(categories) {
            const allBtn = UI.el('button', {
                className: 'filter-tab active', 'data-cat': 'all',
                onclick: e => this._tabClick(e)
            }, ['All']);
            UI.render(this.tabs, [
                allBtn,
                ...categories.map(cat =>
                    UI.el('button', {
                            className: 'filter-tab', 'data-cat': cat,
                            onclick: e => this._tabClick(e)
                        },
                        [cat.charAt(0).toUpperCase() + cat.slice(1)])
                ),
            ]);
        }

        _tabClick(e) {
            this.tabs.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
            e.target.classList.add('active');
            this.page = 1;
            this.category = e.target.dataset.cat;
            this.render();
        }

        filtered() {
            return this.category === 'all'
                ? this.badges
                : this.badges.filter(b => (b.category ?? '').toLowerCase() === this.category);
        }

        render() {
            const list = this.filtered();
            const toShow = this.earned ? list : list.slice(0, this.page * ITEMS_PER_PAGE);

            if (!toShow.length) {
                UI.render(this.grid, [UI.el('div', {className: 'no-results-message'}, [
                    UI.el('div', {className: 'no-results-icon'}, ['🔍']),
                    UI.el('h3', {}, ['No Badges Found']),
                    UI.el('p', {}, [this.earned
                        ? "You haven't earned any badges in this category yet."
                        : 'There are no badges available in this category.']),
                ])]);
                this._toggleShowMore(false);
                return;
            }

            UI.render(this.grid, toShow.map(b => this._card(b)));

            if (!this.earned) {
                this._toggleShowMore(list.length > toShow.length);
            }
        }

        _card(b) {
            const card = UI.el('div', {className: `badge-card${this.earned ? '' : ' locked'}`}, [
                UI.el('div', {className: `badge-tier-indicator ${b.tier ?? ''}`}),
                UI.el('div', {className: 'badge-icon'}, [this.earned ? (b.icon ?? '🏆') : '🔒']),
                UI.el('div', {className: `badge-tier ${b.tier ?? ''}`}, [b.tier ?? '']),
                UI.el('div', {className: 'badge-name'}, [b.name]),
                UI.el('div', {className: 'badge-description'}, [b.description ?? '']),
                b.points > 0 ? UI.el('div', {className: 'badge-points'}, [`+${b.points} points`]) : null,
                this.earned && b.earned_at
                    ? UI.el('div', {className: 'badge-earned-date'}, [`Earned ${UI.formatDate(b.earned_at)}`])
                    : null,
            ]);
            return card;
        }

        _toggleShowMore(show) {
            const wrapper = document.getElementById('showMoreWrapper');
            if (wrapper) wrapper.style.display = show ? 'block' : 'none';
        }

        showMore() {
            this.page++;
            this.render();
        }
    }

    class BadgesPage {
        async init() {
            try {
                const json = await api(`/api/${SITE_SLUG}/member/badges`);
                const {earned_badges, unearned_badges, categories} = json.data;

                document.getElementById('badge-stats-summary').textContent =
                    `${earned_badges.length} earned • ${earned_badges.length + unearned_badges.length} total available`;

                // Carousel
                this.carousel = new Carousel('badgesCarousel');
                this.carousel.build(earned_badges, unearned_badges);
                document.getElementById('prevBtn').onclick = () => this.carousel.move(-1);
                document.getElementById('nextBtn').onclick = () => this.carousel.move(1);
                let resizeTimer;
                window.addEventListener('resize', () => {
                    clearTimeout(resizeTimer);
                    resizeTimer = setTimeout(() => this.carousel.onResize(), 150);
                });

                // Earned section
                if (earned_badges.length) {
                    document.getElementById('earnedSection').style.display = 'block';
                    this.earnedGrid = new BadgeGrid('earnedBadgesGrid', 'earnedFilterTabs', earned_badges, true);
                    this.earnedGrid.buildTabs(categories ?? []);
                    this.earnedGrid.render();
                }

                // Unearned section
                if (unearned_badges.length) {
                    document.getElementById('unearnedSection').style.display = 'block';
                    this.unearnedGrid = new BadgeGrid('unearnedBadgesGrid', 'unearnedFilterTabs', unearned_badges, false);
                    this.unearnedGrid.buildTabs(categories ?? []);
                    this.unearnedGrid.render();
                    document.getElementById('showMoreBtn').onclick = () => this.unearnedGrid.showMore();
                }

                if (!earned_badges.length && !unearned_badges.length) {
                    document.getElementById('empty-state').style.display = 'block';
                }
            } catch (e) {
                UI.toast('Failed to load badges. Please refresh.', 'error');
                document.getElementById('badgesCarousel').innerHTML = '';
            }
        }
    }

    document.addEventListener('DOMContentLoaded', () => new BadgesPage().init());
</script>
</body>
</html>