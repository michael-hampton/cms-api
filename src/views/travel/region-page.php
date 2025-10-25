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
    <?php

    use App\Framework\Support\SiteContext;

    $cssFile = asset(SiteContext::css(), 'css');

    ?>

    <link rel="stylesheet" href="<?= $cssFile ?>">
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