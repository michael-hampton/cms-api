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
        <p style="color: var(--text-secondary); margin-top: 0.5rem;">
            <?= $earnedBadges->count() ?> earned • <?= $earnedBadges->count() + $unearnedBadges->count() ?> total
            available
        </p>
    </div>

    <!-- Badges Carousel (Earned First, Then Unearned) -->
    <div class="carousel-section">
        <h2 class="section-title">All Badges</h2>
        <div class="carousel-container">
            <div class="badges-carousel" id="badgesCarousel">
                <?php
                // Show earned badges first
                foreach ($earnedBadges as $badge):
                    $memberBadge = $member->badges->firstWhere('id', $badge->id);
                    ?>
                    <div class="carousel-badge">
                        <div class="badge-tier-indicator <?= htmlspecialchars($badge->tier) ?>"></div>
                        <div class="carousel-badge-icon"><?= $badge->icon ?></div>
                        <div class="carousel-badge-name"><?= htmlspecialchars($badge->name) ?></div>
                        <div class="carousel-badge-tier <?= htmlspecialchars($badge->tier) ?>">
                            <?= htmlspecialchars($badge->tier) ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php
                // Then show unearned badges
                foreach ($unearnedBadges as $badge):
                    ?>
                    <div class="carousel-badge locked">
                        <div class="badge-tier-indicator <?= htmlspecialchars($badge->tier) ?>"></div>
                        <div class="carousel-badge-icon">🔒</div>
                        <div class="carousel-badge-name"><?= htmlspecialchars($badge->name) ?></div>
                        <div class="carousel-badge-tier <?= htmlspecialchars($badge->tier) ?>">
                            <?= htmlspecialchars($badge->tier) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="carousel-controls">
            <button class="carousel-btn" id="prevBtn" onclick="moveCarousel(-1)">←</button>
            <button class="carousel-btn" id="nextBtn" onclick="moveCarousel(1)">→</button>
        </div>
        <div class="carousel-dots" id="carouselDots"></div>
    </div>

    <!-- My Badges Section (Earned Badges with Category Filter) -->
    <?php if ($earnedBadges->count() > 0): ?>
        <div class="section">
            <h2 class="section-title">My Badges</h2>

            <div class="filter-tabs">
                <button class="filter-tab active" onclick="filterEarnedBadges('all')">All</button>
                <?php foreach ($categories as $category): ?>
                    <button class="filter-tab" onclick="filterEarnedBadges('<?= htmlspecialchars($category) ?>')">
                        <?= htmlspecialchars(ucfirst($category)) ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="badges-grid" id="earnedBadgesGrid">
                <?php foreach ($earnedBadges as $badge): ?>
                    <?php $memberBadge = $member->badges->where('id', $badge->id)->first(); ?>
                    <div class="badge-card" data-category="<?= htmlspecialchars($badge->category) ?>">
                        <div class="badge-tier-indicator <?= htmlspecialchars($badge->tier) ?>"></div>
                        <div class="badge-icon"><?= $badge->icon ?></div>
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
                        <div class="badge-earned-date">
                            Earned <?= $memberBadge->earned_at?->format('M j, Y') ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Earn Badges Section (Unearned with Category Filter & Pagination) -->
    <?php if ($unearnedBadges->count() > 0): ?>
        <div class="section">
            <h2 class="section-title">Earn These Badges</h2>

            <div class="filter-tabs">
                <button class="filter-tab active" onclick="filterUnearnedBadges('all')">All</button>
                <?php foreach ($categories as $category): ?>
                    <button class="filter-tab" onclick="filterUnearnedBadges('<?= htmlspecialchars($category) ?>')">
                        <?= htmlspecialchars(ucfirst($category)) ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="badges-grid" id="unearnedBadgesGrid">
                <?php
                $initialShow = 6;
                $count = 0;
                foreach ($unearnedBadges as $badge):
                    $count++;
                    $hidden = $count > $initialShow ? 'style="display: none;"' : '';
                    ?>
                    <div class="badge-card locked"
                         data-category="<?= htmlspecialchars($badge->category) ?>" <?= $hidden ?>>
                        <div class="badge-tier-indicator <?= htmlspecialchars($badge->tier) ?>"></div>
                        <div class="badge-icon">🔒</div>
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
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($unearnedBadges->count() > $initialShow): ?>
                <div class="show-more-container">
                    <button class="show-more-btn" onclick="showMoreBadges()">
                        Show More
                    </button>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($earnedBadges->count() === 0 && $unearnedBadges->count() === 0): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🏆</div>
            <h2>No Badges Available</h2>
            <p style="color: var(--text-secondary); margin-top: 0.5rem;">
                Check back soon for new badges to earn!
            </p>
        </div>
    <?php endif; ?>
</main>

<script>
    let currentPage = 1;
    const itemsPerPage = 6;
    let currentCarouselIndex = 0;
    const carousel = document.getElementById('badgesCarousel');
    const badges = carousel.querySelectorAll('.carousel-badge');
    const itemsPerView = window.innerWidth < 768 ? 2 : window.innerWidth < 1024 ? 3 : 5;
    const totalPages = Math.ceil(badges.length / itemsPerView);

    function setupCarousel() {
        // Create dots
        const dotsContainer = document.getElementById('carouselDots');
        dotsContainer.innerHTML = '';
        for (let i = 0; i < totalPages; i++) {
            const dot = document.createElement('div');
            dot.className = 'carousel-dot' + (i === 0 ? ' active' : '');
            dot.onclick = () => goToPage(i);
            dotsContainer.appendChild(dot);
        }

        updateCarousel();
    }

    function moveCarousel(direction) {
        currentCarouselIndex += direction;
        if (currentCarouselIndex < 0) currentCarouselIndex = 0;
        if (currentCarouselIndex >= totalPages) currentCarouselIndex = totalPages - 1;
        updateCarousel();
    }

    function goToPage(index) {
        currentCarouselIndex = index;
        updateCarousel();
    }

    function updateCarousel() {
        const badgeWidth = 216; // 200px + 16px gap
        const offset = -currentCarouselIndex * badgeWidth * itemsPerView;
        carousel.style.transform = `translateX(${offset}px)`;

        // Update dots
        document.querySelectorAll('.carousel-dot').forEach((dot, index) => {
            dot.classList.toggle('active', index === currentCarouselIndex);
        });

        // Update button states
        document.getElementById('prevBtn').disabled = currentCarouselIndex === 0;
        document.getElementById('nextBtn').disabled = currentCarouselIndex >= totalPages - 1;
    }

    // Auto-scroll carousel every 5 seconds
    let carouselInterval = setInterval(() => {
        if (currentCarouselIndex < totalPages - 1) {
            moveCarousel(1);
        } else {
            currentCarouselIndex = -1;
            moveCarousel(1);
        }
    }, 5000);

    // Pause auto-scroll on hover
    carousel.addEventListener('mouseenter', () => clearInterval(carouselInterval));
    carousel.addEventListener('mouseleave', () => {
        carouselInterval = setInterval(() => {
            if (currentCarouselIndex < totalPages - 1) {
                moveCarousel(1);
            } else {
                currentCarouselIndex = -1;
                moveCarousel(1);
            }
        }, 5000);
    });

    // Initialize carousel
    setupCarousel();

    // Recalculate on resize
    window.addEventListener('resize', setupCarousel);

    function filterEarnedBadges(category) {
        // Update active tab
        const tabs = document.querySelectorAll('.filter-tabs')[0].querySelectorAll('.filter-tab');
        tabs.forEach(tab => {
            tab.classList.remove('active');
            const tabText = tab.textContent.trim().toLowerCase();
            if (tabText === category || (category === 'all' && tabText === 'all')) {
                tab.classList.add('active');
            }
        });

        // Filter badges
        const grid = document.getElementById('earnedBadgesGrid');
        const badges = grid.querySelectorAll('.badge-card');
        let visibleCount = 0;

        badges.forEach(badge => {
            const badgeCategory = badge.dataset.category;
            if (category === 'all' || badgeCategory === category) {
                badge.style.display = 'block';
                visibleCount++;
            } else {
                badge.style.display = 'none';
            }
        });

        // Show/hide no results message
        let noResultsMsg = grid.querySelector('.no-results-message');
        if (visibleCount === 0) {
            if (!noResultsMsg) {
                noResultsMsg = document.createElement('div');
                noResultsMsg.className = 'no-results-message';
                noResultsMsg.innerHTML = `
                <div class="no-results-icon">🔍</div>
                <h3>No Badges Found</h3>
                <p>You haven't earned any badges in this category yet.</p>
            `;
                grid.appendChild(noResultsMsg);
            }
            noResultsMsg.style.display = 'flex';
            grid.style.minHeight = '400px';
        } else {
            if (noResultsMsg) {
                noResultsMsg.style.display = 'none';
            }
            grid.style.minHeight = 'auto';
        }
    }

    function filterUnearnedBadges(category) {
        // Update active tab
        const tabs = document.querySelectorAll('.filter-tabs')[1].querySelectorAll('.filter-tab');
        tabs.forEach(tab => {
            tab.classList.remove('active');
            const tabText = tab.textContent.trim().toLowerCase();
            if (tabText === category || (category === 'all' && tabText === 'all')) {
                tab.classList.add('active');
            }
        });

        // Reset pagination
        currentPage = 1;

        // Filter and show badges
        const grid = document.getElementById('unearnedBadgesGrid');
        const badges = grid.querySelectorAll('.badge-card');
        let visibleCount = 0;

        badges.forEach(badge => {
            const badgeCategory = badge.dataset.category;
            const shouldShow = category === 'all' || badgeCategory === category;

            if (shouldShow) {
                visibleCount++;
                badge.style.display = visibleCount <= itemsPerPage ? 'block' : 'none';
            } else {
                badge.style.display = 'none';
            }
        });

        // Show/hide no results message
        let noResultsMsg = grid.querySelector('.no-results-message');
        if (visibleCount === 0) {
            if (!noResultsMsg) {
                noResultsMsg = document.createElement('div');
                noResultsMsg.className = 'no-results-message';
                noResultsMsg.innerHTML = `
                <div class="no-results-icon">🔍</div>
                <h3>No Badges Found</h3>
                <p>There are no badges available in this category.</p>
            `;
                grid.appendChild(noResultsMsg);
            }
            noResultsMsg.style.display = 'flex';
            grid.style.minHeight = '400px';
        } else {
            if (noResultsMsg) {
                noResultsMsg.style.display = 'none';
            }
            grid.style.minHeight = 'auto';
        }

        // Update show more button
        updateShowMoreButton();
    }

    function showMoreBadges() {
        currentPage++;
        const badges = document.querySelectorAll('#unearnedBadgesGrid .badge-card');
        const maxToShow = currentPage * itemsPerPage;
        let visibleCount = 0;

        badges.forEach(badge => {
            if (badge.style.display !== 'none' || badge.style.display === '') {
                visibleCount++;
                if (visibleCount <= maxToShow) {
                    badge.style.display = 'block';
                }
            }
        });

        updateShowMoreButton();
    }

    function updateShowMoreButton() {
        const button = document.querySelector('.show-more-btn');
        if (!button) return;

        const badges = Array.from(document.querySelectorAll('#unearnedBadgesGrid .badge-card'));
        const visibleBadges = badges.filter(b => {
            const computedStyle = window.getComputedStyle(b);
            return computedStyle.display !== 'none';
        });

        const allShown = visibleBadges.length === 0 || visibleBadges.every(b => b.style.display === 'block');

        if (allShown) {
            button.disabled = true;
            button.textContent = 'All Badges Shown';
        } else {
            button.disabled = false;
            button.textContent = 'Show More';
        }
    }
</script>
</body>
</html>