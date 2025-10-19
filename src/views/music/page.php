@include('header', ['menu' => $menu])

<?php
use App\Framework\Support\SiteContext;

// Prepare page data
$title = $page->title ?? SiteContext::name();
$description = $page->meta_description ?? '';

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

<main class="page-main" id="main-content">
    <div class="container">
        <?php if ($page): ?>
            <div class="page-layout <?= $hasSidebar ? 'has-sidebar' : 'full-width' ?>">

                <!-- Main Content Area -->
                <article class="main-content">

                    <!-- Page Header -->
                    <?php if ($page->page_type !== 'custom'): ?>
                        <header class="page-header">
                            <h1 class="page-title"><?= htmlspecialchars($page->title) ?></h1>

                            <div class="page-meta">
                                <!-- Categories -->
                                <?php if (!empty($page->categories)): ?>
                                    <div class="page-categories">
                                        <?php foreach ($page->categories as $index => $category): ?>
                                            <a href="/category/<?= urlencode($category->slug) ?>" class="category-badge">
                                                <?= htmlspecialchars($category->name) ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Tags -->
                                <?php if (!empty($page->tags)): ?>
                                    <div class="page-tags">
                                        <?php foreach ($page->tags as $tag): ?>
                                            <a href="/tags/<?= urlencode($tag->slug) ?>" class="tag-badge">
                                                #<?= htmlspecialchars($tag->name) ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </header>
                    <?php endif; ?>

                    <!-- Main Content Blocks -->
                    <div class="page-content">
                        <?php foreach ($mainBlocks as $block): ?>
                            <?= $blockParserService->buildBlock(
                                $page->id,
                                $block->data + ['type' => $block->type],
                                $block->order
                            ) ?>
                        <?php endforeach; ?>
                    </div>

                    <!-- Blog Comments -->
                    @include('comments')

                    <!-- Social Sharing -->
                    @include('links)
                </article>

                <!-- Sidebar -->
                <?php if ($hasSidebar): ?>
                    <aside class="page-sidebar">
                        <?php foreach ($sidebarBlocks as $block): ?>
                            <?= $blockParserService->buildBlock(
                                $page->id,
                                $block->data + ['type' => $block->type],
                                $block->order
                            ) ?>
                        <?php endforeach; ?>
                    </aside>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="page-not-found">
                <h1>404 - Page Not Found</h1>
                <p>The page you're looking for doesn't exist.</p>
                <a href="<?= SiteContext::getUrl() ?>" class="btn btn-primary">
                    Go Home
                </a>
            </div>
        <?php endif; ?>

        @include('authors')
    </div>
</main>

@js('carousel.js');

<?php if (isset($footerMenu) && $footerMenu) {
    $footerRenderer = new \App\Services\FooterRenderer();
    echo $footerRenderer->renderFooter($footerMenu);
} ?>