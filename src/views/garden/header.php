<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Haven & Hearth - Home & Garden</title>

    <?php

    use App\Framework\Support\SiteContext;

    $cssFile = asset(SiteContext::css(), 'css');

    ?>

    <link rel="stylesheet" href="<?= $cssFile ?>">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Crimson+Text:ital,wght@0,400;0,600;1,400&display=swap"
          rel="stylesheet">
</head>
<body>
<!-- ===================================
     HEADER
     =================================== -->
<header class="site-header">
    <div class="header-container">
        <!-- Logo -->
        <a href="/" class="site-logo">
            <div class="logo-icon">🏡</div>
            <div class="logo-text">
                <div class="logo-title">Haven & Hearth</div>
                <div class="logo-subtitle">Home & Garden</div>
            </div>
        </a>

        <!-- Desktop Navigation -->
        <nav class="main-nav">
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
                    <li><a href="/" class="active">Home</a></li>
                    <li><a href="/interior-design">Interior Design</a></li>
                    <li><a href="/garden">Garden</a></li>
                    <li><a href="/products">Products</a></li>
                    <li><a href="/diy">DIY Projects</a></li>
                    <li><a href="/about">About</a></li>
                    <li><a href="/contact">Contact</a></li>
                <?php endif; ?>
            </ul>
        </nav>

        <!-- Header Actions -->
        <div class="header-actions">
            <button class="search-toggle" aria-label="Toggle search">
                🔍
            </button>
            <button class="menu-toggle" aria-label="Toggle menu">
                ☰
            </button>
        </div>
    </div>

    <!-- Search Bar (Hidden by default) -->
    <div class="header-search" id="headerSearch">
        <form class="search-form" action="/search" method="GET">
            <input
                    type="search"
                    name="q"
                    class="search-input"
                    placeholder="Search for design ideas, products, tips..."
                    aria-label="Search"
            >
            <button type="submit" class="search-button">Search</button>
        </form>
    </div>

    <!-- Mobile Navigation (Hidden by default) -->
    <nav class="mobile-nav" id="mobileNav">
        <ul class="nav-menu">
            <li><a href="/">Home</a></li>
            <li><a href="/interior-design">Interior Design</a></li>
            <li><a href="/garden">Garden</a></li>
            <li><a href="/products">Products</a></li>
            <li><a href="/diy">DIY Projects</a></li>
            <li><a href="/about">About</a></li>
            <li><a href="/contact">Contact</a></li>
        </ul>
    </nav>
</header>