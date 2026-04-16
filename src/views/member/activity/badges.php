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
    /* ─── State ─────────────────────────────────────────── */
    let earnedBadges = [];
    let unearnedBadges = [];
    let currentPage = 1;
    const ITEMS_PER_PAGE = 6;

    /* Carousel state */
    let currentCarouselIndex = 0;
    let carouselInterval = null;
    let itemsPerView = calcItemsPerView();

    function calcItemsPerView() {
        return window.innerWidth < 768 ? 2 : window.innerWidth < 1024 ? 3 : 5;
    }

    /* ─── Toast helper ───────────────────────────────────── */
    function showToast(message, type = 'info') {
        const icons = {success: '✓', error: '✕', info: 'ℹ'};
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `
            <span class="toast-icon">${icons[type]}</span>
            <span>${message}</span>
            <button class="toast-close" onclick="this.parentElement.remove()">×</button>`;
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease forwards';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    /* ─── Init ───────────────────────────────────────────── */
    async function init() {
        try {
            const response = await fetch('/api/' + SITE_SLUG + '/member/badges');
            if (!response.ok) throw new Error('Server error ' + response.status);
            const res = await response.json();

            if (!res.success) throw new Error(res.message || 'Failed to load badges');

            earnedBadges = res.data.earned_badges || [];
            unearnedBadges = res.data.unearned_badges || [];

            renderSummary(earnedBadges.length, unearnedBadges.length);
            renderCarousel();
            renderFilters(res.data.categories || []);
            renderEarned('all');
            renderUnearned('all');

            if (earnedBadges.length === 0 && unearnedBadges.length === 0) {
                document.getElementById('empty-state').style.display = 'block';
            }
        } catch (e) {
            console.error('Failed to load badges', e);
            showToast('Failed to load badges. Please refresh the page.', 'error');
            document.getElementById('badgesCarousel').innerHTML = '';
        }
    }

    /* ─── Summary line ───────────────────────────────────── */
    function renderSummary(earned, unearned) {
        document.getElementById('badge-stats-summary').textContent =
            `${earned} earned • ${earned + unearned} total available`;
    }

    /* ─── Carousel ───────────────────────────────────────── */
    function renderCarousel() {
        const container = document.getElementById('badgesCarousel');

        const earnedHtml = earnedBadges.map(b => `
            <div class="carousel-badge">
                <div class="badge-tier-indicator ${esc(b.tier)}"></div>
                <div class="carousel-badge-icon">${b.icon || '🏆'}</div>
                <div class="carousel-badge-name">${esc(b.name)}</div>
                <div class="carousel-badge-tier ${esc(b.tier)}">${esc(b.tier)}</div>
            </div>`).join('');

        const unearnedHtml = unearnedBadges.map(b => `
            <div class="carousel-badge locked">
                <div class="badge-tier-indicator ${esc(b.tier)}"></div>
                <div class="carousel-badge-icon">🔒</div>
                <div class="carousel-badge-name">${esc(b.name)}</div>
                <div class="carousel-badge-tier ${esc(b.tier)}">${esc(b.tier)}</div>
            </div>`).join('');

        container.innerHTML = earnedHtml + unearnedHtml;
        setupCarouselLogic();
    }

    function setupCarouselLogic() {
        itemsPerView = calcItemsPerView();
        const badges = document.getElementById('badgesCarousel').querySelectorAll('.carousel-badge');
        const totalPages = Math.ceil(badges.length / itemsPerView);

        /* Build dots */
        const dotsContainer = document.getElementById('carouselDots');
        dotsContainer.innerHTML = '';
        for (let i = 0; i < totalPages; i++) {
            const dot = document.createElement('button');
            dot.className = 'carousel-dot' + (i === 0 ? ' active' : '');
            dot.setAttribute('aria-label', 'Go to page ' + (i + 1));
            dot.addEventListener('click', () => goToCarouselPage(i));
            dotsContainer.appendChild(dot);
        }

        /* Clamp index in case items changed on resize */
        if (currentCarouselIndex >= totalPages) currentCarouselIndex = Math.max(0, totalPages - 1);
        applyCarouselTransform(totalPages);
        startAutoScroll(totalPages);
    }

    function applyCarouselTransform(totalPages) {
        const BADGE_WIDTH = 216; // 200px + 16px gap
        const carousel = document.getElementById('badgesCarousel');
        carousel.style.transform = `translateX(${-currentCarouselIndex * BADGE_WIDTH * itemsPerView}px)`;

        document.querySelectorAll('.carousel-dot').forEach((dot, i) =>
            dot.classList.toggle('active', i === currentCarouselIndex));

        document.getElementById('prevBtn').disabled = currentCarouselIndex === 0;
        document.getElementById('nextBtn').disabled = currentCarouselIndex >= (totalPages || 1) - 1;
    }

    function moveCarousel(direction) {
        const badges = document.getElementById('badgesCarousel').querySelectorAll('.carousel-badge');
        const totalPages = Math.ceil(badges.length / itemsPerView);
        currentCarouselIndex = Math.max(0, Math.min(totalPages - 1, currentCarouselIndex + direction));
        applyCarouselTransform(totalPages);
    }

    function goToCarouselPage(index) {
        const badges = document.getElementById('badgesCarousel').querySelectorAll('.carousel-badge');
        const totalPages = Math.ceil(badges.length / itemsPerView);
        currentCarouselIndex = Math.max(0, Math.min(totalPages - 1, index));
        applyCarouselTransform(totalPages);
    }

    function startAutoScroll(totalPages) {
        stopAutoScroll();
        if (totalPages <= 1) return;
        carouselInterval = setInterval(() => {
            currentCarouselIndex = (currentCarouselIndex + 1) % totalPages;
            applyCarouselTransform(totalPages);
        }, 5000);
    }

    function stopAutoScroll() {
        if (carouselInterval) {
            clearInterval(carouselInterval);
            carouselInterval = null;
        }
    }

    /* Pause on hover */
    document.addEventListener('DOMContentLoaded', () => {
        const carousel = document.getElementById('badgesCarousel');
        carousel.addEventListener('mouseenter', stopAutoScroll);
        carousel.addEventListener('mouseleave', () => {
            const badges = carousel.querySelectorAll('.carousel-badge');
            const totalPages = Math.ceil(badges.length / itemsPerView);
            startAutoScroll(totalPages);
        });
    });

    /* Re-initialise carousel on resize */
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(setupCarouselLogic, 150);
    });

    /* ─── Filter tabs ────────────────────────────────────── */
    function renderFilters(categories) {
        const earnedTabs = document.getElementById('earnedFilterTabs');
        const unearnedTabs = document.getElementById('unearnedFilterTabs');

        categories.forEach(cat => {
            const label = cat.charAt(0).toUpperCase() + cat.slice(1);

            const btn1 = document.createElement('button');
            btn1.className = 'filter-tab';
            btn1.dataset.cat = cat;
            btn1.textContent = label;
            btn1.addEventListener('click', () => filterToggle(btn1, cat, 'earned'));
            earnedTabs.appendChild(btn1);

            const btn2 = document.createElement('button');
            btn2.className = 'filter-tab';
            btn2.dataset.cat = cat;
            btn2.textContent = label;
            btn2.addEventListener('click', () => filterToggle(btn2, cat, 'unearned'));
            unearnedTabs.appendChild(btn2);
        });
    }

    function filterToggle(btn, cat, section) {
        const tabsId = section === 'earned' ? 'earnedFilterTabs' : 'unearnedFilterTabs';
        document.getElementById(tabsId).querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
        btn.classList.add('active');

        if (section === 'earned') {
            renderEarned(cat);
        } else {
            currentPage = 1;
            renderUnearned(cat);
        }
    }

    /* ─── Render earned badges ───────────────────────────── */
    function renderEarned(category) {
        const grid = document.getElementById('earnedBadgesGrid');
        const section = document.getElementById('earnedSection');

        if (earnedBadges.length > 0) section.style.display = 'block';

        const filtered = category === 'all'
            ? earnedBadges
            : earnedBadges.filter(b => (b.category || '').toLowerCase() === category);

        if (filtered.length === 0) {
            grid.innerHTML = `<div class="no-results-message">
                <div class="no-results-icon">🔍</div>
                <h3>No Badges Found</h3>
                <p>You haven't earned any badges in this category yet.</p>
            </div>`;
            return;
        }

        grid.innerHTML = filtered.map(b => `
            <div class="badge-card">
                <div class="badge-tier-indicator ${esc(b.tier)}"></div>
                <div class="badge-icon">${b.icon || '🏆'}</div>
                <div class="badge-tier ${esc(b.tier)}">${esc(b.tier)}</div>
                <div class="badge-name">${esc(b.name)}</div>
                <div class="badge-description">${esc(b.description || '')}</div>
                ${b.points > 0 ? `<div class="badge-points">+${b.points} points</div>` : ''}
                <div class="badge-earned-date">Earned ${formatEarnedDate(b.earned_at)}</div>
            </div>`).join('');
    }

    /* ─── Render unearned badges ─────────────────────────── */
    function renderUnearned(category) {
        const grid = document.getElementById('unearnedBadgesGrid');
        const section = document.getElementById('unearnedSection');

        if (unearnedBadges.length > 0) section.style.display = 'block';

        const filtered = category === 'all'
            ? unearnedBadges
            : unearnedBadges.filter(b => (b.category || '').toLowerCase() === category);

        const toShow = filtered.slice(0, currentPage * ITEMS_PER_PAGE);

        if (toShow.length === 0) {
            grid.innerHTML = `<div class="no-results-message">
                <div class="no-results-icon">🔍</div>
                <h3>No Badges Found</h3>
                <p>There are no badges available in this category.</p>
            </div>`;
            document.getElementById('showMoreWrapper').style.display = 'none';
            return;
        }

        grid.innerHTML = toShow.map(b => `
            <div class="badge-card locked">
                <div class="badge-tier-indicator ${esc(b.tier)}"></div>
                <div class="badge-icon">🔒</div>
                <div class="badge-tier ${esc(b.tier)}">${esc(b.tier)}</div>
                <div class="badge-name">${esc(b.name)}</div>
                <div class="badge-description">${esc(b.description || '')}</div>
                ${b.points > 0 ? `<div class="badge-points">+${b.points} points</div>` : ''}
            </div>`).join('');

        const wrapper = document.getElementById('showMoreWrapper');
        const btn = document.getElementById('showMoreBtn');
        if (filtered.length > toShow.length) {
            wrapper.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Show More';
        } else {
            wrapper.style.display = 'none';
        }
    }

    function showMoreBadges() {
        currentPage++;
        const activeTab = document.querySelector('#unearnedFilterTabs .filter-tab.active');
        const cat = activeTab ? activeTab.dataset.cat : 'all';
        renderUnearned(cat);
    }

    /* ─── Helpers ────────────────────────────────────────── */
    function formatEarnedDate(dateStr) {
        if (!dateStr) return 'Unknown';
        try {
            return new Date(dateStr).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'});
        } catch {
            return 'Unknown';
        }
    }

    function esc(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /* ─── Boot ───────────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', init);
</script>
</body>
</html>