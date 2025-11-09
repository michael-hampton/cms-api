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

                        @include('page-title')

                        <!-- Categories -->
                        @include('categories')

                        <!-- Tags -->
                        @include('tags')

                        @include('page-actions')
                    </div>

                    <?php if(!empty($pageGridHtml)) {
                        echo $pageGridHtml;
                    }
                    ?>

                    <!-- Main Content Blocks -->
                    <?php foreach ($mainBlocks as $block): ?>
                        <?= $blockParserService->buildBlock($page->id, $block->data + ['type' => $block->type], $block->order) ?>
                    <?php endforeach; ?>

                    <!-- Blog Comments Section -->
                    @include('comments')

                    <!-- Social Media Links -->
                    @include('links')
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

        @include('authors')
    </div>
</main>

<?php if (isset($footerMenu) && $footerMenu) {
    $footerRenderer = new \App\Services\FooterRenderer();
    echo $footerRenderer->renderFooter($footerMenu);
} ?>