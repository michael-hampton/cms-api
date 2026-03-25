<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Today's Deals - Best Offers & Discounts</title>
    @css('deals.css')
    @css('productModal.css')
    @css('deals-carousel.css')
</head>
<body>
<div class="page-wrapper">
    <header class="site-header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <a href="/">YourStore</a>
                </div>
                <nav class="main-nav">
                    <a href="/">Home</a>
                    <a href="/shop">Shop</a>
                    <a href="/cart">Cart</a>
                    <a href="/contact">Contact</a>
                </nav>
                <div class="header-actions">
                    <button class="icon-btn" onclick="window.location.href='/wishlist'">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                        </svg>
                        <span class="badge" id="wishlist-count">0</span>
                    </button>
                    <button class="icon-btn" onclick="window.location.href='/cart'">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                        <span class="badge" id="cart-count"><?= $count ?? 0 ?></span>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Header with navigation -->

    <main class="deals-page">
        <div class="container">

            <!-- Hero Section with Carousel -->
            <section class="deals-hero">
                 @include ('components/deals-carousel')
            </section>

            @include('components/offers-carousel', ['offers' => $offers ?? []])
            @include('components/bundles-carousel', ['bundles' => $bundles ?? []])

            @include ('components/deal-alert-subscribe')

            <!-- Deals Tabs -->
            <div class="deals-tabs">
                <button class="tab-btn active" data-tab="all" onclick="switchTab('all')">All Deals</button>

                <!-- Category Tabs (Dynamic) -->
                <?php
                $filteredCategories = array_filter($categories ?? [], fn($c) => $c->product_count > 0);
                ?>

                <?php foreach (array_slice($filteredCategories, 0, 8) as $category): ?>
                    <button class="tab-btn" data-tab="cat-<?= $category->id ?>"
                            onclick="switchTab('cat-<?= $category->id ?>')">
                        <?= htmlspecialchars($category->name) ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="suggested-filters" id="suggested-filters" style="display: none;">
                <div class="suggested-filters-header">
                    <h3>Quick Filters:</h3>
                </div>
                <div class="suggested-filters-list" id="suggested-filters-list">
                    <!-- Dynamically populated -->
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="shop-layout">
                <!-- Sidebar -->
                <aside class="shop-sidebar">
                    <div class="sidebar-section collapsible" data-section="search">
                        <button type="button" class="section-toggle" onclick="toggleSection('search')">
                            <h3 class="sidebar-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <path d="m21 21-4.35-4.35"></path>
                                </svg>
                                Search
                            </h3>
                            <svg class="chevron" width="20" height="20" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>

                        <div class="section-content open">
                            <div class="search-box">
                                <input type="text" id="search-input" placeholder="Search products...">
                                <button type="button" id="search-btn">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <circle cx="11" cy="11" r="8"></circle>
                                        <path d="m21 21-4.35-4.35"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Star Rating Filter -->
                    <div class="sidebar-section collapsible" data-section="ratings">
                        <button type="button" class="section-toggle" onclick="toggleSection('ratings')">
                            <h3 class="sidebar-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2">
                                    <polygon
                                            points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                                </svg>
                                Customer Rating
                            </h3>
                            <svg class="chevron" width="20" height="20" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>

                        <div class="section-content open">
                            <div class="filter-list" id="rating-filter-list">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <label class="filter-checkbox-label">
                                        <input type="radio" name="min_rating" value="<?= $i ?>" class="filter-checkbox">
                                        <span class="rating-stars">
                                        <?php for ($s = 1; $s <= 5; $s++): ?>
                                            <svg width="14" height="14" viewBox="0 0 24 24"
                                                 fill="<?= $s <= $i ? '#f59e0b' : 'none' ?>"
                                                 stroke="<?= $s <= $i ? '#f59e0b' : '#cbd5e1' ?>"
                                                 stroke-width="2" style="display:inline-block;vertical-align:middle;">
                                                <polygon
                                                        points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                                            </svg>
                                        <?php endfor; ?>
                                    </span>
                                        <span class="rating-text">&amp; Up</span>
                                    </label>
                                <?php endfor; ?>
                                <label class="filter-option">
                                    <input type="radio" name="min_rating" value="">
                                    <span class="rating-text">Any rating</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="sidebar-section collapsible" data-section="categories">
                        <button type="button" class="section-toggle" onclick="toggleSection('categories')">
                            <h3 class="sidebar-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2">
                                    <path d="M3 3h7v7H3V3zm11 0h7v7h-7V3zM3 14h7v7H3v-7zm11 0h7v7h-7v-7z"></path>
                                </svg>
                                Categories
                            </h3>
                            <svg class="chevron" width="20" height="20" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>
                        <div class="section-content open">
                            <div class="filter-list" id="category-list">
                                <?php foreach (array_slice($categories, 0, 5) as $category): ?>
                                    <label class="filter-checkbox-label">
                                        <input type="checkbox" class="filter-checkbox" name="category[]"
                                               value="<?= $category->id ?>">
                                        <span class="filter-name"><?= htmlspecialchars($category->name) ?></span>
                                        <span class="filter-count"><?= $category->product_count ?? 0 ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($categories) > 5): ?>
                                <button type="button" class="show-more-btn" data-filter="category">
                                    Show More
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="sidebar-section collapsible" data-section="brands">
                        <button type="button" class="section-toggle" onclick="toggleSection('brands')">
                            <h3 class="sidebar-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2">
                                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
                                </svg>
                                Brands
                            </h3>
                            <svg class="chevron" width="20" height="20" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>
                        <div class="section-content open">
                            <div class="filter-list" id="brand-list">
                                <?php foreach (array_slice($brands, 0, 5) as $brand): ?>
                                    <label class="filter-checkbox-label">
                                        <input type="checkbox" class="filter-checkbox" name="brand[]"
                                               value="<?= $brand->id ?>">
                                        <span class="filter-name"><?= htmlspecialchars($brand->name) ?></span>
                                        <span class="filter-count"><?= $brand->product_count ?? 0 ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($brands) > 5): ?>
                                <button type="button" class="show-more-btn" data-filter="brand">
                                    Show More
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Specification Filters -->
                    <?php if (!empty($specificationGroups)): ?>
                        <?php foreach ($specificationGroups as $specGroup): ?>
                            <div class="sidebar-section collapsible" data-section="spec-<?= $specGroup['slug'] ?>">
                                <button type="button" class="section-toggle"
                                        onclick="toggleSection('spec-<?= $specGroup['slug'] ?>')">
                                    <h3 class="sidebar-title">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                                             stroke="currentColor" stroke-width="2">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                            <polyline points="14 2 14 8 20 8"></polyline>
                                            <line x1="16" y1="13" x2="8" y2="13"></line>
                                            <line x1="16" y1="17" x2="8" y2="17"></line>
                                            <polyline points="10 9 9 9 8 9"></polyline>
                                        </svg>
                                        <?= htmlspecialchars($specGroup['name']) ?>
                                    </h3>
                                    <svg class="chevron" width="20" height="20" viewBox="0 0 24 24" fill="none"
                                         stroke="currentColor" stroke-width="2">
                                        <polyline points="6 9 12 15 18 9"></polyline>
                                    </svg>
                                </button>
                                <div class="section-content">
                                    <div class="filter-list" id="spec-<?= $specGroup['slug'] ?>-list">
                                        <?php foreach (array_slice($specGroup['specifications'], 0, 5) as $spec): ?>
                                            <?php foreach ($spec['values'] as $value): ?>
                                                <label class="filter-checkbox-label">
                                                    <input type="checkbox"
                                                           class="filter-checkbox"
                                                           name="spec_<?= $specGroup['id'] ?>[]"
                                                           value="<?= htmlspecialchars($value) ?>"
                                                           data-group="<?= $specGroup['id'] ?>"
                                                           data-key="<?= htmlspecialchars($spec['key']) ?>">
                                                    <span class="filter-name"><?= htmlspecialchars($spec['key']) ?>: <?= htmlspecialchars($value) ?></span>
                                                    <span class="filter-count"><?= $spec['count'] ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if (count($specGroup['specifications']) > 5): ?>
                                        <button type="button" class="show-more-btn"
                                                data-filter="spec-<?= $specGroup['slug'] ?>">
                                            Show More
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div class="sidebar-section collapsible" data-section="price-range">
                        <button type="button" class="section-toggle" onclick="toggleSection('price-range')">
                            <h3 class="sidebar-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2">
                                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                </svg>
                                Price Range
                            </h3>
                            <svg class="chevron" width="20" height="20" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>
                        <div class="section-content open">
                            <div class="price-range">
                                <input type="number" id="min-price" placeholder="Min" class="price-input">
                                <span class="price-separator">-</span>
                                <input type="number" id="max-price" placeholder="Max" class="price-input">
                            </div>
                        </div>
                    </div>

                    <div class="sidebar-section">
                        <label class="checkbox-label">
                            <input type="checkbox" id="on-sale-filter">
                            <span>On Sale Only</span>
                        </label>
                    </div>

                    <button class="btn btn-primary btn-block" id="apply-filters">Apply Filters</button>
                    <button class="btn btn-secondary btn-block" id="reset-filters">Reset</button>

                    <script id="all-categories" type="application/json">
                        <?= json_encode($categories) ?>

                    </script>
                    <script id="all-brands" type="application/json">
                        <?= json_encode($brands) ?>

                    </script>
                </aside>

                <!-- Deals Grid -->
                <div class="deals-content">
                    <!-- Toolbar -->
                    <div class="shop-toolbar">
                        <div class="results-info">
                            <span id="results-count">0 products</span>
                        </div>
                        <div class="toolbar-actions">
                            <select id="sort-select" class="sort-select">
                                <option value="created_at:desc">Newest First</option>
                                <option value="created_at:asc">Oldest First</option>
                                <option value="price:asc">Price: Low to High</option>
                                <option value="price:desc">Price: High to Low</option>
                                <option value="name:asc">Name: A to Z</option>
                                <option value="name:desc">Name: Z to A</option>
                            </select>
                            <select id="per-page-select" class="per-page-select">
                                <option value="12">12 per page</option>
                                <option value="24">24 per page</option>
                                <option value="48">48 per page</option>
                            </select>
                        </div>
                    </div>

                    <!-- Deals Grid -->
                    <div id="deals-grid" class="deals-grid">
                        <!-- Deals will be loaded here -->
                    </div>

                    <!-- Loading State -->
                    <div id="loading-state" class="loading-state" style="display: none;">
                        <div class="spinner"></div>
                        <p>Loading deals...</p>
                    </div>

                    <!-- Empty State -->
                    <div id="empty-state" class="empty-state" style="display: none;">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <h3>No deals found</h3>
                        <p>Try adjusting your filters or search terms</p>
                    </div>

                    <!-- Pagination -->
                    <div id="pagination" class="pagination">
                        <!-- Pagination will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </main>

    @include('footer', ['footerMenu' => $footerMenu ?? null])
</div>

<div id="toast" class="toast"></div>

<div id="comparison-bar">
    <div class="comparison-count">0 products selected</div>
    <button onclick="compareProducts()" class="btn btn-primary">Compare Products</button>
</div>

@include('components/share-modal')

<!-- Hidden data for JavaScript -->
<script>
    site = '<?= \App\Framework\Support\SiteContext::slug() ?? '' ?>';
    categories = <?= json_encode($categories ?? []) ?>;
    brands = <?= json_encode($brands ?? []) ?>;
    SITE = '<?= \App\Framework\Support\SiteContext::slug()?>'
    CURRENCY_SYMBOL = '<?= $currencySymbol ?>';

</script>

@js('deals-carousel.js')
@js('deals.js')
@js('productModal.js')
@js('price-alert.js')
@js('recommendations.js')

</body>
</html>