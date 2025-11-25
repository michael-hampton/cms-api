<?php
// views/estate/page.php (enhanced page template)
use App\Parsers\PageGridRenderer;

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
$pageGridAdded = false;
?>


    <main class="mt-20">
        <div class="container">
            <?php if ($page): ?>
                <div class="page-layout <?= $hasSidebar ? 'has-sidebar' : 'full-width' ?>">

                    <!-- Main Content Area -->
                    <div class="main-content <?= $hasSidebar ? 'with-sidebar' : 'full-width' ?>">

                        <!-- Page Header -->
                        <div class="page-header">
                            @include('page-title')

                            @if($page->page_type !== 'landing-page')

                            <!-- Categories -->
                            @include('categories', ['page' => $page])

                            <!-- Tags -->
                            @include('tags')

                            @include('page-actions')
                            @endif
                        </div>

                        <!-- Main Content Blocks -->
                        <?php foreach ($mainBlocks as $index => $block): ?>

                            <?php if (!empty($pageGrid) && $pageGrid->order === ($index + 1)): ?>
                                <?= (new PageGridRenderer())->render($pageGrid) ?>
                            <?php endif; ?>

                            <?= $blockParserService->buildBlock($page->id, $block->data + ['type' => $block->type], $block->order) ?>

                        <?php endforeach; ?>

                        <!-- After the page header section -->
                        <?php if ($page->page_type === 'landing-page' && !empty($categories)): ?>
                            @include('categories-widget', ['page' => $page])
                        <?php endif; ?>

                        @include('product-section')

                        <!-- Blog Comments Section -->
                        @if($page->page_type !== 'landing-page')
                        @include('comments')
                        @endif

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

            @if($page->page_type !== 'landing-page')
            @include('authors')
            @endif
        </div>
    </main>

<?php if (isset($footerMenu) && $footerMenu) {
    $footerRenderer = new \App\Services\FooterRenderer();
    echo $footerRenderer->renderFooter($footerMenu);
} ?>

@include('consent-banner', ['site' => $site])

@js('base.js')

<?php if (isset($subscriptionModalData)): ?>
    @include('components/subscription-modal', ['subscriptionModalData' => $subscriptionModalData])
<?php endif; ?>
