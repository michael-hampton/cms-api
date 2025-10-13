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
<header class="header">
    <div class="header-top">
        <div class="header-logo">
            <span>🍷</span>
            The Wine Chronicle
        </div>
        <div class="header-actions">
            <div class="search-bar">
                <input type="text" placeholder="Search wines, regions, producers...">
                <button>🔍</button>
            </div>
            <a href="#" class="btn-subscribe">Subscribe</a>
        </div>
    </div>
    <nav class="main-nav">
        <ul>
            <?php if (isset($menu) && !empty($menu)): ?>
                <?php foreach ($menu->items as $item): ?>
                    <li class="nav-item">
                        <a href="<?= htmlspecialchars($item->url) ?>" class="nav-link">
                            <?= htmlspecialchars($item->label) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
            <li><a href="/">Home</a></li>
            <li><a href="/reviews">Wine Reviews</a></li>
            <li><a href="/regions">Regions</a></li>
            <li><a href="/guides">Wine Knowledge</a></li>
            <li><a href="/events">Events</a></li>
            <li><a href="/about">About</a></li>
            <li><a href="/contact">Contact</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>
</body>