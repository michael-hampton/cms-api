<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page->meta_title ?? $page->title ?? 'VOGUE NOIR') ?></title>
    <meta name="description" content="<?= htmlspecialchars($page->meta_description ?? '') ?>">

    <?php
    use App\Framework\Support\SiteContext;

    $cssFile = asset(SiteContext::css(), 'css');

    ?>

    <link rel="stylesheet" href="<?= $cssFile ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
<header class="fashion-header">
    <div class="header-top">
        <div class="header-container">
            <div class="header-social">
                <a href="#" class="social-icon">Instagram</a>
                <a href="#" class="social-icon">Twitter</a>
                <a href="#" class="social-icon">Pinterest</a>
            </div>
            <div class="header-actions">
                <a href="/subscribe" class="header-subscribe">Subscribe</a>
                <button class="header-search-toggle" onclick="toggleSearch()">Search</button>
            </div>
        </div>
    </div>

    <div class="header-main">
        <div class="header-container">
            <a href="/" class="logo">
                <span class="logo-main">VOGUE</span>
                <span class="logo-sub">NOIR</span>
            </a>
        </div>
    </div>

    <nav class="header-nav">
        <div class="header-container">
            <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <ul class="nav-menu" id="mainNav">
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
                    <li class="nav-item"><a href="/fashion" class="nav-link">Fashion</a></li>
                    <li class="nav-item"><a href="/beauty" class="nav-link">Beauty</a></li>
                    <li class="nav-item"><a href="/designers" class="nav-link">Designers</a></li>
                    <li class="nav-item"><a href="/lifestyle" class="nav-link">Lifestyle</a></li>
                    <li class="nav-item"><a href="/about" class="nav-link">About</a></li>
                    <li class="nav-item"><a href="/contact" class="nav-link">Contact</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <div class="search-overlay" id="searchOverlay">
        <div class="search-container">
            <button class="search-close" onclick="toggleSearch()">&times;</button>
            <form class="search-form" action="/search" method="GET">
                <input type="search" name="q" placeholder="Search articles, trends, designers..." class="search-input" autofocus>
                <button type="submit" class="search-submit">Search</button>
            </form>
        </div>
    </div>
</header>

<script>
    function toggleSearch() {
        document.getElementById('searchOverlay').classList.toggle('active');
    }

    function toggleMobileMenu() {
        document.getElementById('mainNav').classList.toggle('active');
    }
</script>