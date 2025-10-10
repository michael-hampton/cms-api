<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page->meta_title ?? $page->title ?? 'ECHO BEAT') ?></title>
    <meta name="description" content="<?= htmlspecialchars($page->meta_description ?? '') ?>">

    <?php
    use App\Framework\Support\SiteContext;
    $cssFile = asset(SiteContext::css(), 'css');
    ?>

    <link rel="stylesheet" href="<?= $cssFile ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;700&family=Orbitron:wght@400;600;800&display=swap" rel="stylesheet">
</head>
<body>
<header class="music-header">
    <div class="header-container">
        <a href="/" class="logo">
            <span class="logo-main">ECHO</span>
            <span class="logo-sub">BEAT</span>
        </a>

        <nav class="header-nav">
            <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
                <span></span><span></span><span></span>
            </button>

            <ul id="mainNav">
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
        </nav>

        <div class="header-actions">
            <a href="https://instagram.com" class="social-icon">Instagram</a>
            <a href="https://twitter.com" class="social-icon">Twitter</a>
            <button class="header-search-toggle" onclick="toggleSearch()">Search</button>
        </div>
    </div>

    <div class="search-overlay" id="searchOverlay">
        <div class="search-container">
            <button class="search-close" onclick="toggleSearch()">&times;</button>
            <form class="search-form" action="/search" method="GET">
                <input type="search" name="q" placeholder="Search tracks, artists, albums..." class="search-input" autofocus>
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
</body>
</html>
