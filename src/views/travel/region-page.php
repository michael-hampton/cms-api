<?php
$title = $page->title ?? 'Global Wanderlust';
$description = $page->meta_description ?? 'Travel Magazine';

$sidebarBlocks = [];
$mainBlocks = [];

if ($page && $page->blocks) {
    foreach ($page->blocks as $block) {
        $blockData = $block->data ?? [];
        if (isset($blockData['context']) && $blockData['context'] === 'sidebar') {
            $sidebarBlocks[] = $block;
        } else {
            $mainBlocks[] = $block;
        }
    }
}

$hasSidebar = !empty($sidebarBlocks);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <meta name="description" content="<?= htmlspecialchars($description) ?>">
    @css('base-blocks.css')
    <?php

    use App\Framework\Support\SiteContext;

    $cssFile = asset(SiteContext::css(), 'css');

    ?>

    <link rel="stylesheet" href="<?= $cssFile ?>">
    <style>/* ==========================================================================
   Travel Magazine - Global Wanderlust Core Styles
   ========================================================================== */

        :root {
            --primary-color: #d97706;       /* Warm Terracotta / Sunset Orange */
            --text-main: #1e293b;           /* Deep Charcoal for readability */
            --text-muted: #64748b;          /* Soft Slate for secondary metadata */
            --bg-main: #ffffff;
            --bg-light: #f8fafc;           /* Light cool grey background cards */
            --border-color: #e2e8f0;       /* Crisp borders */
            --font-serif: "Playfair Display", Georgia, Cambria, "Times New Roman", Times, serif;
            --font-sans: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            --container-max-width: 1200px;
            --transition: all 0.3s ease;
        }

        /* Base Reset */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-sans);
            color: var(--text-main);
            background-color: var(--bg-main);
            line-height: 1.7;
            font-size: 1rem;
            -webkit-font-smoothing: antialiased;
        }

        a {
            color: inherit;
            text-decoration: none;
            transition: var(--transition);
        }

        ul, ol {
            list-style: none;
        }

        img {
            max-width: 100%;
            height: auto;
            display: block;
        }

        .container {
            width: 100%;
            max-width: var(--container-max-width);
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        /* ==========================================================================
           Header & Navigation
           ========================================================================== */

        .site-header {
            background: #fff;
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: var(--container-max-width);
            margin: 0 auto;
            padding: 1.25rem 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .site-logo {
            font-family: var(--font-serif);
            font-size: 1.75rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: var(--text-main);
        }

        .site-logo:hover {
            color: var(--primary-color);
        }

        .region-selector {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .region-selector select {
            padding: 0.4rem 1.5rem 0.4rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background-color: var(--bg-light);
            color: var(--text-main);
            font-weight: 600;
            cursor: pointer;
            outline: none;
        }

        .region-selector select:focus {
            border-color: var(--primary-color);
        }

        .main-nav ul {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }

        .main-nav a {
            font-weight: 500;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
        }

        .main-nav a:hover {
            color: var(--primary-color);
        }

        /* ==========================================================================
           Main Layout (Sidebar vs Full-Width)
           ========================================================================== */

        main {
            padding: 3rem 0;
        }

        .page-layout {
            display: grid;
            gap: 3rem;
        }

        .page-layout.has-sidebar {
            grid-template-columns: 1fr;
        }

        @media (min-width: 992px) {
            .page-layout.has-sidebar {
                grid-template-columns: minmax(0, 1fr) 320px;
            }
        }

        /* Page Header Elements */
        .page-header {
            margin-bottom: 2.5rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 2rem;
        }

        .page-title {
            font-family: var(--font-serif);
            font-size: 2.75rem;
            line-height: 1.2;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .page-categories {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 0.75rem;
        }

        .category-link {
            color: var(--primary-color);
            font-weight: 600;
        }

        .category-link:hover {
            text-decoration: underline;
        }

        .page-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .tag-badge {
            background: var(--bg-light);
            border: 1px solid var(--border-color);
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .tag-link {
            color: var(--text-muted);
        }

        .tag-link:hover {
            color: var(--primary-color);
        }

        /* Content Blocks Spacing */
        .block {
            margin-bottom: 2.5rem;
        }

        /* ==========================================================================
           Carousel Grid styling (Matches your JS logic)
           ========================================================================== */

        .region-page-grid {
            margin-top: 3.5rem;
        }

        /* Assumptions based on standard carousel markup used in your JS */
        .page-grid-carousel {
            position: relative;
            overflow: hidden;
            padding-bottom: 1rem;
        }

        .page-grid-track {
            display: flex;
            gap: 2rem; /* 32px matches JS gap parameter */
            overflow-x: auto;
            scroll-behavior: smooth;
            scrollbar-width: none; /* Hide scrollbar Firefox */
            scroll-snap-type: x mandatory;
        }

        .page-grid-track::-webkit-scrollbar {
            display: none; /* Hide scrollbar Chrome/Safari */
        }

        .page-card {
            flex-shrink: 0;
            width: calc(33.333% - 1.34rem); /* Default 3 columns */
            scroll-snap-align: start;
            background: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.5rem;
        }

        @media (max-width: 768px) {
            .page-card {
                width: calc(50% - 1rem); /* 2 columns on tablet */
            }
        }

        @media (max-width: 480px) {
            .page-card {
                width: 100%; /* 1 column on mobile */
            }
        }

        /* Carousel Control Elements */
        .page-grid-nav-btn {
            background: #fff;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }

        .page-grid-nav-btn:hover {
            background: var(--primary-color);
            color: #fff;
        }

        .page-grid-indicators {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }

        .page-grid-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--border-color);
            cursor: pointer;
            transition: var(--transition);
        }

        .page-grid-indicator.active {
            background: var(--primary-color);
            width: 20px;
            border-radius: 4px;
        }

        /* ==========================================================================
           Sidebar Custom Styles
           ========================================================================== */

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 2.5rem;
        }

        .sidebar .block {
            background: var(--bg-light);
            padding: 1.5rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        /* ==========================================================================
           Footer
           ========================================================================== */

        .site-footer {
            background: #0f172a; /* Sophisticated deep Navy/Black */
            color: #f8fafc;
            padding: 4rem 0 2rem 0;
            margin-top: 5rem;
        }

        .footer-container {
            max-width: var(--container-max-width);
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2.5rem;
            border-bottom: 1px solid #334155;
            padding-bottom: 3rem;
        }

        .footer-section h3 {
            font-family: var(--font-serif);
            font-size: 1.2rem;
            margin-bottom: 1.25rem;
            color: #fff;
            position: relative;
        }

        .footer-section h3::after {
            content: '';
            display: block;
            width: 30px;
            height: 2px;
            background: var(--primary-color);
            margin-top: 0.5rem;
        }

        .footer-section ul {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .footer-section a {
            color: #cbd5e1;
            font-size: 0.95rem;
        }

        .footer-section a:hover {
            color: var(--primary-color);
            padding-left: 4px;
        }

        .footer-bottom {
            padding-top: 2rem;
            text-align: center;
            font-size: 0.85rem;
            color: #94a3b8;
        }

        /* Mobile Responsive Tweaks for Header */
        @media (max-width: 640px) {
            .header-container {
                flex-direction: column;
                align-items: flex-start;
            }
            .main-nav ul {
                flex-wrap: wrap;
                gap: 1rem;
                margin-top: 0.5rem;
            }
        }</style>
</head>
<body>
<!-- Header -->
<header class="site-header">
    <div class="header-container">
        <a href="/" class="site-logo">Global Wanderlust</a>

        <div class="region-selector">
            <label for="region-select">Region:</label>
            <select id="region-select" onchange="switchRegion(this.value)">
                <?php foreach ($allTerritories as $t): ?>
                    <option value="<?= $t->slug ?>"
                        <?= $t->id === $territory->id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t->name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        here

        <?php if ($menu): ?>
            <nav class="main-nav">
                <ul>
                    <?php foreach ($menu->items as $item): ?>
                        <li>
                            <a href="<?= htmlspecialchars($item->url ?? '#') ?>">
                                <?= htmlspecialchars($item->label) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</header>

<main>
    <div class="container">
        <?php if ($page): ?>
            <div class="page-layout <?= $hasSidebar ? 'has-sidebar' : 'full-width' ?>">
                <!-- Main Content Area -->
                <div class="main-content <?= $hasSidebar ? 'with-sidebar' : 'full-width' ?>">
                    <!-- Page Header -->
                    <div class="page-header">
                        <h1 class="page-title"><?= htmlspecialchars($page->title) ?></h1>

                        <!-- Categories -->
                        <?php if (!empty($page->categories)): ?>
                            <div class="page-categories">
                                <span class="categories-label">Categories:</span>
                                <?php foreach ($page->categories as $index => $category): ?>
                                <a href="/category/<?= urlencode($category->slug) ?>" class="category-link">
                                    <?= htmlspecialchars($category->name) ?>
                                    </a><?= $index < count($page->categories) - 1 ? ', ' : '' ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Tags -->
                        <?php if ($page->tags): ?>
                            <div class="page-tags">
                                <?php foreach ($page->tags as $tag): ?>
                                    <span class="tag-badge">
                                            <a href="/tags/<?= urlencode($tag->slug) ?>" class="tag-link">
                                                <?= htmlspecialchars($tag->name) ?>
                                            </a>
                                        </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Main Content Blocks -->
                    <?php foreach ($mainBlocks as $block): ?>
                        <div class="block block-<?= htmlspecialchars($block->type) ?>">
                            <?= $blockParserService->buildBlock($page->id, $block->data + ['type' => $block->type], $block->order) ?>
                        </div>
                    <?php endforeach; ?>

                    <!-- Region Articles Grid -->
                    <?php if (!empty($pageGridHtml)): ?>
                        <div class="region-page-grid">
                            <?= $pageGridHtml ?>
                        </div>
                    <?php endif; ?>

                    <!-- Comments Section -->
                    <?php if (!empty($comments)): ?>
                        <div class="comments-section"
                             style="margin-top: 4rem; padding-top: 2rem; border-top: 2px solid var(--border-color);">
                            <h3 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 1.5rem;">Comments</h3>
                            <?php foreach ($comments as $comment): ?>
                                <div style="padding: 1rem; background: var(--bg-light); border-radius: 8px; margin-bottom: 1rem;">
                                    <p><?= htmlspecialchars($comment->content ?? '') ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <?php if ($hasSidebar): ?>
                    <aside class="sidebar">
                        <?php foreach ($sidebarBlocks as $block): ?>
                            <div class="block block-<?= htmlspecialchars($block->type) ?>">
                                <?= $blockParserService->buildBlock($page->id, $block->data + ['type' => $block->type], $block->order) ?>
                            </div>
                        <?php endforeach; ?>
                    </aside>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 4rem 0;">
                <h1>Page Not Found</h1>
                <p>The page you're looking for doesn't exist.</p>
                <a href="/"
                   style="display: inline-block; margin-top: 1rem; padding: 0.75rem 1.5rem; background: var(--primary-color); color: #fff; text-decoration: none; border-radius: 6px;">Go
                    Home</a>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Footer -->
<footer class="site-footer">
    <div class="footer-container">
        <div class="footer-content">
            <div class="footer-section">
                <h3>About Global Wanderlust</h3>
                <p style="color: rgba(255,255,255,0.8); line-height: 1.6;">
                    Your guide to exploring the world's most incredible destinations.
                </p>
            </div>

            <div class="footer-section">
                <h3>Regions</h3>
                <ul>
                    <?php foreach ($allTerritories as $t): ?>
                        <li>
                            <a href="/<?= htmlspecialchars($t->slug) ?>/<?= htmlspecialchars($t->slug) ?>">
                                <?= htmlspecialchars($t->name) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <?php if ($footerMenu && $footerMenu->items): ?>
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <ul>
                        <?php foreach ($footerMenu->items as $item): ?>
                            <li>
                                <a href="<?= htmlspecialchars($item->url ?? '#') ?>">
                                    <?= htmlspecialchars($item->label) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="footer-section">
                <h3>Connect</h3>
                <ul>
                    <li><a href="#">Newsletter</a></li>
                    <li><a href="#">Instagram</a></li>
                    <li><a href="#">Facebook</a></li>
                    <li><a href="#">Twitter</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> Global Wanderlust. All rights reserved.</p>
        </div>
    </div>
</footer>

<script>
    function switchRegion(regionSlug) {
        // Get current page slug from URL
        const pathParts = window.location.pathname.split('/').filter(p => p);

        const currentPageSlug = pathParts[pathParts.length - 1];
        const siteName = pathParts[0];

        // Navigate to the homepage of the selected region
        window.location.href = '/' + siteName + '/' + regionSlug + '/' + regionSlug;
    }

    function scrollPageGrid(button, direction) {
        const carousel = button.closest('.page-grid-carousel');
        const track = carousel.querySelector('.page-grid-track');
        const cardWidth = track.querySelector('.page-card').offsetWidth;
        const gap = 32; // 2rem gap
        const scrollAmount = cardWidth + gap;

        if (direction === 'prev') {
            track.scrollBy({left: -scrollAmount, behavior: 'smooth'});
        } else {
            track.scrollBy({left: scrollAmount, behavior: 'smooth'});
        }

        updateIndicators(carousel);
    }

    function scrollPageGridToIndex(button, index) {
        const carousel = button.closest('.page-grid-carousel');
        const track = carousel.querySelector('.page-grid-track');
        const cardWidth = track.querySelector('.page-card').offsetWidth;
        const gap = 32;
        const scrollAmount = (cardWidth + gap) * index;

        track.scrollTo({left: scrollAmount, behavior: 'smooth'});
        updateIndicators(carousel);
    }

    function updateIndicators(carousel) {
        const track = carousel.querySelector('.page-grid-track');
        const indicators = carousel.querySelectorAll('.page-grid-indicator');
        const cardWidth = track.querySelector('.page-card').offsetWidth;
        const gap = 32;
        const currentIndex = Math.round(track.scrollLeft / (cardWidth + gap));

        indicators.forEach((indicator, index) => {
            if (index === currentIndex) {
                indicator.classList.add('active');
            } else {
                indicator.classList.remove('active');
            }
        });
    }

    // Auto-update indicators on scroll
    document.addEventListener('DOMContentLoaded', function () {
        const carousels = document.querySelectorAll('.page-grid-carousel');

        carousels.forEach(carousel => {
            const track = carousel.querySelector('.page-grid-track');

            if (track) {
                track.addEventListener('scroll', () => {
                    updateIndicators(carousel);
                });
            }
        });
    });

</script>
</body>
</html>