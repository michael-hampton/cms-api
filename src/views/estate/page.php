@include('header', ['menu' => $menu])


<?php
// views/estate/page.php (enhanced page template)
$title = $page->title ?? 'Premier Properties';
$description = $page->meta_description ?? 'Premier Properties - Luxury Real Estate in London';

// Separate sidebar and main content blocks
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

<main class="mt-20">
    <div class="container" style="padding: 2rem;">
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
                        <?= $blockParserService->buildBlock($page->id, $block->data + ['type' => $block->type], $block->order) ?>
                    <?php endforeach; ?>

                    @include('comments')

                    <?php if ($page->gallery_slides): ?>
                        @include('gallery')
                    <?php endif; ?>

                    <!-- Social Media Links -->
                    <?php if ($page->social && $page->social->enable_sharing): ?>
                        <div class="social-sharing">
                            <h4>Share this page:</h4>
                            <div class="social-buttons">
                                <?php
                                $platforms = $page->social->platforms ?? [];
                                $shareText = urlencode($page->social->share_text ?? $page->title);
                                $currentUrl = urlencode("https://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}");
                                ?>

                                <?php if (in_array('facebook', $platforms)): ?>
                                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $currentUrl ?>"
                                       target="_blank" class="social-btn facebook">Facebook</a>
                                <?php endif; ?>

                                <?php if (in_array('twitter', $platforms)): ?>
                                    <a href="https://twitter.com/intent/tweet?text=<?= $shareText ?>&url=<?= $currentUrl ?>"
                                       target="_blank" class="social-btn twitter">Twitter</a>
                                <?php endif; ?>

                                <?php if (in_array('linkedin', $platforms)): ?>
                                    <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $currentUrl ?>"
                                       target="_blank" class="social-btn linkedin">LinkedIn</a>
                                <?php endif; ?>

                                <?php if (in_array('email', $platforms)): ?>
                                    <a href="mailto:?subject=<?= $shareText ?>&body=<?= $currentUrl ?>"
                                       class="social-btn email">Email</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <?php if ($hasSidebar): ?>
                    <aside class="sidebar">
                        <?php foreach ($sidebarBlocks as $block): ?>
                            <?= $blockParserService->buildBlock($page->id, $block->data + ['type' => $block->type], $block->order) ?>
                        <?php endforeach; ?>
                    </aside>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 4rem 0;">
                <h1>Page Not Found</h1>
                <p>The page you're looking for doesn't exist.</p>
                <a href="/" class="btn btn-primary">Go Home</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const site = '<?= \App\Framework\Support\SiteContext::slug() ?>';
    </script>

    <?php if (!empty($todaysDeals)): ?>
        <div class="page-deals-section">
            @include('components/deals-carousel')
        </div>

        @css('deals-carousel.css')
        @js('deals-carousel.js')
    <?php endif; ?>

  @include('authors')
</main>

<?php if (isset($footerMenu) && $footerMenu) {
    $footerRenderer = new \App\Services\FooterRenderer();
    echo $footerRenderer->renderFooter($footerMenu);
} ?>