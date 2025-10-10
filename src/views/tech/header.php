<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page->meta_title ?? $page->title ?? 'TechWeekly') ?></title>
    <meta name="description" content="<?= htmlspecialchars($page->meta_description ?? '') ?>">

    <?php
    use App\Framework\Support\SiteContext;

    $cssFile = asset(SiteContext::css(), 'css');

    ?>

    <link rel="stylesheet" href="<?= $cssFile ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
<header class="tech-header">
    <div class="header-announcement-bar">
        <div class="header-container">
            <p class="announcement-text">🚀 Breaking: New AI breakthroughs announced - <a href="/breaking-news">Read more →</a></p>
        </div>
    </div>

    <div class="header-main">
        <div class="header-container">
            <a href="/" class="tech-logo">
                <span class="logo-icon">⚡</span>
                <span class="logo-text">TechWeekly</span>
            </a>

            <nav class="header-nav desktop-nav">
                <ul class="nav-menu">
                    <?php if (isset($menu) && !empty($menu)): ?>
                        <?php foreach ($menu->items as $item): ?>
                            <li class="nav-item">
                                <a href="<?= htmlspecialchars($item->url) ?>" class="nav-link">
                                    <?= htmlspecialchars($item->label) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="nav-item"><a href="/" class="nav-link">Home</a></li>
                        <li class="nav-item"><a href="/ai" class="nav-link">AI</a></li>
                        <li class="nav-item"><a href="/security" class="nav-link">Security</a></li>
                        <li class="nav-item"><a href="/development" class="nav-link">Development</a></li>
                        <li class="nav-item"><a href="/reviews" class="nav-link">Reviews</a></li>
                        <li class="nav-item"><a href="/about" class="nav-link">About</a></li>
                    <?php endif; ?>
                </ul>
            </nav>

            <div class="header-actions">
                <button class="search-btn" onclick="toggleSearch()" aria-label="Search">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                </button>
                <a href="/subscribe" class="subscribe-btn">Subscribe</a>
                <button class="mobile-menu-toggle" onclick="toggleMobileMenu()" aria-label="Menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </div>
    </div>

    <nav class="mobile-nav" id="mobileNav">
        <ul class="mobile-menu">
            <?php if (isset($menu) && !empty($menu)): ?>
                <?php foreach ($menu as $item): ?>
                    <li class="mobile-nav-item">
                        <a href="<?= htmlspecialchars($item['url']) ?>" class="mobile-nav-link">
                            <?= htmlspecialchars($item['title']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li class="mobile-nav-item"><a href="/" class="mobile-nav-link">Home</a></li>
                <li class="mobile-nav-item"><a href="/ai" class="mobile-nav-link">AI</a></li>
                <li class="mobile-nav-item"><a href="/security" class="mobile-nav-link">Security</a></li>
                <li class="mobile-nav-item"><a href="/development" class="mobile-nav-link">Development</a></li>
                <li class="mobile-nav-item"><a href="/reviews" class="mobile-nav-link">Reviews</a></li>
                <li class="mobile-nav-item"><a href="/about" class="mobile-nav-link">About</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <div class="search-overlay" id="searchOverlay">
        <div class="search-container">
            <button class="search-close" onclick="toggleSearch()">&times;</button>
            <form class="search-form" action="/search" method="GET">
                <input type="search" name="q" placeholder="Search articles, tutorials, reviews..." class="search-input" autofocus>
                <button type="submit" class="search-submit">Search</button>
            </form>
            <div class="search-suggestions">
                <h4>Popular Searches</h4>
                <div class="suggestion-tags">
                    <a href="/search?q=AI">AI</a>
                    <a href="/search?q=Python">Python</a>
                    <a href="/search?q=Kubernetes">Kubernetes</a>
                    <a href="/search?q=Security">Security</a>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
    function toggleSearch() {
        document.getElementById('searchOverlay').classList.toggle('active');
    }

    function toggleMobileMenu() {
        document.getElementById('mobileNav').classList.toggle('active');
    }
</script>