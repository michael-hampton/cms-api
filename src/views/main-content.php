<?php
// views/estate/page.php (enhanced page template)

$title = $page->title ?? 'Premier Properties';
$description = $page->meta_description ?? 'Premier Properties - Luxury Real Estate in London';

$pageGridAdded = false;
?>


    <main class="mt-20">
        <div class="container">
            <?php if ($page): ?>

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

                <div class="page-layout <?= $html['hasSidebar'] ? 'has-sidebar' : 'full-width' ?>">

                    <!-- Main Content Area -->
                    <div class="main-content <?= $html['hasSidebar'] ? 'with-sidebar' : 'full-width' ?>">

                        <?= $html['main'] ?>

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
                    <?php if ($html['hasSidebar']): ?>
                        <aside class="sidebar">
                            <?= $html['sidebar'] ?>
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

@include('components/newsletter-account-creation-modal')
@include('components/modals')

