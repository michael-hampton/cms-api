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

        /* Badges Carousel */
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
        }

        .carousel-badge:hover {
            transform: translateY(-4px);
        }

        .carousel-badge.locked {
            opacity: 0.6;
        }

        .badge-tier-indicator {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            border-radius: 1rem 1rem 0 0;
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
            color: #666666;
        }

        /* Filter Tabs */
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
            border-radius: 9999px; /* Make them round pills */
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

        /* Badges Grid */
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

        /* Show More Button */
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

        /*** No results ***/
        .no-results-message {
            text-align: center;
            padding: 3rem 2rem;
            background: white;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            min-height: 400px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
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
    </style>
</head>
<body>

@include('member._header')

<main class="container">
    <div class="page-header">
        <h1 class="page-title">🏆 My Badges</h1>
        <p id="badge-stats-summary" style="color: var(--text-secondary); margin-top: 0.5rem;">
            Loading badges...
        </p>
    </div>

    <div class="carousel-section">
        <h2 class="section-title">All Badges</h2>
        <div class="carousel-container">
            <div class="badges-carousel" id="badgesCarousel">
            </div>
        </div>

        <div class="carousel-controls">
            <button class="carousel-btn" id="prevBtn" onclick="moveCarousel(-1)">←</button>
            <button class="carousel-btn" id="nextBtn" onclick="moveCarousel(1)">→</button>
        </div>
        <div class="carousel-dots" id="carouselDots"></div>
    </div>

    <div class="section" id="earnedSection" style="display: none;">
        <h2 class="section-title">My Badges</h2>
        <div class="filter-tabs" id="earnedFilterTabs">
            <button class="filter-tab active" onclick="filterToggle(this, 'all')">All</button>
        </div>
        <div class="badges-grid" id="earnedBadgesGrid"></div>
    </div>

    <div class="section" id="unearnedSection" style="display: none;">
        <h2 class="section-title">Earn These Badges</h2>
        <div class="filter-tabs" id="unearnedFilterTabs">
            <button class="filter-tab active" onclick="filterToggle(this, 'all')">All</button>
        </div>
        <div class="badges-grid" id="unearnedBadgesGrid"></div>
        <div class="show-more-container" id="showMoreWrapper" style="display: none;">
            <button class="show-more-btn" onclick="showMoreBadges()">Show More</button>
        </div>
    </div>

    <div id="empty-state" class="empty-state" style="display: none;">
        <div class="empty-state-icon">🏆</div>
        <h2>No Badges Available</h2>
        <p style="color: var(--text-secondary); margin-top: 0.5rem;">Check back soon for new badges!</p>
    </div>
</main>

<script>
    // State
    let earnedBadges = [];
    let unearnedBadges = [];
    let currentPage = 1;
    const itemsPerPage = 6;
    let currentCarouselIndex = 0;
    const SITE_SLUG = '<?= \App\Framework\Support\SiteContext::slug() ?>';

    async function init() {
        try {
            const response = await fetch('/api/' + SITE_SLUG + '/member/badges');
            const res = await response.json();

            if (res.success) {
                earnedBadges = res.data.earned_badges;
                unearnedBadges = res.data.unearned_badges;

                renderSummary(earnedBadges.length, unearnedBadges.length);
                renderCarousel();
                renderFilters(res.data.categories);
                renderEarned('all');
                renderUnearned('all');

                if (earnedBadges.length === 0 && unearnedBadges.length === 0) {
                    document.getElementById('empty-state').style.display = 'block';
                }
            }
        } catch (e) {
            console.error("Failed to load badges", e);
        }
    }

    function renderSummary(earned, unearned) {
        document.getElementById('badge-stats-summary').textContent =
            `${earned} earned • ${earned + unearned} total available`;
    }

    function renderCarousel() {
        const container = document.getElementById('badgesCarousel');

        // Map earned
        const earnedHtml = earnedBadges.map(b => `
            <div class="carousel-badge">
                <div class="badge-tier-indicator ${b.tier}"></div>
                <div class="carousel-badge-icon">${b.icon || '🏆'}</div>
                <div class="carousel-badge-name">${b.name}</div>
                <div class="carousel-badge-tier ${b.tier}">${b.tier}</div>
            </div>
        `).join('');

        // Map unearned
        const unearnedHtml = unearnedBadges.map(b => `
            <div class="carousel-badge locked">
                <div class="badge-tier-indicator ${b.tier}"></div>
                <div class="carousel-badge-icon">🔒</div>
                <div class="carousel-badge-name">${b.name}</div>
                <div class="carousel-badge-tier ${b.tier}">${b.tier}</div>
            </div>
        `).join('');

        container.innerHTML = earnedHtml + unearnedHtml;
        setupCarouselLogic();
    }

    function renderFilters(categories) {
        const earnedTabs = document.getElementById('earnedFilterTabs');
        const unearnedTabs = document.getElementById('unearnedFilterTabs');

        // Create the HTML for the dynamic categories
        const html = categories.map(cat => `
        <button class="filter-tab" onclick="filterToggle(this, '${cat}')">
            ${cat.charAt(0).toUpperCase() + cat.slice(1)}
        </button>
    `).join('');

        // Append to the existing "All" buttons
        earnedTabs.innerHTML += html;
        unearnedTabs.innerHTML += html;
    }

    function renderEarned(category) {
        const grid = document.getElementById('earnedBadgesGrid');

        // CRITICAL: Ensure 'all' shows everything
        const filtered = (category === 'all')
            ? earnedBadges
            : earnedBadges.filter(b => b.category && b.category.toLowerCase() === category);

        if (earnedBadges.length > 0) document.getElementById('earnedSection').style.display = 'block';

        if (filtered.length === 0) {
            grid.innerHTML = `<div class="no-results-message" style="display: flex;">
                            <div class="no-results-icon">🔍</div>
                            <h3>No Badges Found</h3>
                            <p>You haven't earned any badges in this category yet.</p>
                          </div>`;
            return;
        }

        grid.innerHTML = filtered.map(b => `
        <div class="badge-card">
            <div class="badge-tier-indicator ${b.tier}"></div>
            <div class="badge-icon">${b.icon || '🏆'}</div>
            <div class="badge-tier ${b.tier}">${b.tier}</div>
            <div class="badge-name">${b.name}</div>
            <div class="badge-description">${b.description || ''}</div>
            ${b.points > 0 ? `<div class="badge-points">+${b.points} points</div>` : ''}
            <div class="badge-earned-date">Earned ${new Date().toLocaleDateString()}</div>
        </div>
    `).join('');
    }

    function renderUnearned(category) {
        const grid = document.getElementById('unearnedBadgesGrid');

        // CRITICAL: Ensure 'all' shows everything
        const filtered = (category === 'all')
            ? unearnedBadges
            : unearnedBadges.filter(b => b.category && b.category.toLowerCase() === category);

        if (unearnedBadges.length > 0) document.getElementById('unearnedSection').style.display = 'block';

        const toShow = filtered.slice(0, currentPage * itemsPerPage);

        if (toShow.length === 0) {
            grid.innerHTML = `<div class="no-results-message" style="display: flex;">
                            <div class="no-results-icon">🔍</div>
                            <h3>No Badges Found</h3>
                            <p>There are no badges available in this category.</p>
                          </div>`;
        } else {
            grid.innerHTML = toShow.map(b => `
            <div class="badge-card locked">
                <div class="badge-tier-indicator ${b.tier}"></div>
                <div class="badge-icon">🔒</div>
                <div class="badge-tier ${b.tier}">${b.tier}</div>
                <div class="badge-name">${b.name}</div>
                <div class="badge-description">${b.description || ''}</div>
                ${b.points > 0 ? `<div class="badge-points">+${b.points} points</div>` : ''}
            </div>
        `).join('');
        }

        const wrapper = document.getElementById('showMoreWrapper');
        wrapper.style.display = filtered.length > toShow.length ? 'block' : 'none';
    }

    // --- Interaction Helpers ---

    function filterToggle(btn, cat) {
        const parent = btn.parentElement;

        // Update active UI state
        parent.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
        btn.classList.add('active');

        // Normalize category to lowercase for comparison
        const targetCat = cat.toLowerCase();

        if (parent.id === 'earnedFilterTabs') {
            renderEarned(targetCat);
        } else {
            currentPage = 1; // Reset pagination when filtering
            renderUnearned(targetCat);
        }
    }

    function showMoreBadges() {
        currentPage++;
        const activeTab = document.querySelector('#unearnedFilterTabs .filter-tab.active');
        // Simple way to get category: textContent is usually enough or store in data-cat
        const cat = activeTab.textContent.trim().toLowerCase() === 'all' ? 'all' : activeTab.textContent.trim().toLowerCase();
        renderUnearned(cat);
    }

    // Re-use your original carousel logic
    function setupCarouselLogic() {
        const carousel = document.getElementById('badgesCarousel');
        const badges = carousel.querySelectorAll('.carousel-badge');
        const itemsPerView = window.innerWidth < 768 ? 2 : window.innerWidth < 1024 ? 3 : 5;
        const totalPages = Math.ceil(badges.length / itemsPerView);

        window.moveCarousel = (direction) => {
            currentCarouselIndex = Math.max(0, Math.min(totalPages - 1, currentCarouselIndex + direction));
            const badgeWidth = 216;
            carousel.style.transform = `translateX(${-currentCarouselIndex * badgeWidth * itemsPerView}px)`;

            document.getElementById('prevBtn').disabled = currentCarouselIndex === 0;
            document.getElementById('nextBtn').disabled = currentCarouselIndex >= totalPages - 1;
        };

        // Trigger initial state
        moveCarousel(0);
    }

    document.addEventListener('DOMContentLoaded', init);
</script>
</body>
</html>