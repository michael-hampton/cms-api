<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop - All Products</title>
    @css('products.css')
    @css('productModal.css')
</head>
<body>
<div class="page-wrapper">
    <!-- Header -->
    @include('header', ['menu' => $menu, 'menuRenderer' => $menuRenderer, 'title' => 'Shop'])]

    @include('components/member-badge')

    <!-- Main Content -->
    <main class="main-content">
        <div class="full-container" style="margin: 20px">
            <!-- Hero Section -->
            <section class="hero-section" style="margin-bottom: 20px">
                <div class="container">
                    <h1 class="hero-title">Discover Amazing Products</h1>
                    <p class="hero-subtitle">Quality items curated just for you</p>
                </div>
            </section>


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

                    <div class="sidebar-section collapsible" data-section="categories">
                        <button type="button" class="section-toggle" onclick="toggleSection('categories')">
                            <h3 class="sidebar-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 3h7v7H3V3zm11 0h7v7h-7V3zM3 14h7v7H3v-7zm11 0h7v7h-7v-7z"></path>
                                </svg>
                                Categories
                            </h3>
                            <svg class="chevron" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>
                        <div class="section-content open">
                            <div class="filter-list" id="category-list">
                                <?php foreach (array_slice($categories, 0, 5) as $category): ?>
                                    <label class="filter-checkbox-label">
                                        <input type="checkbox" class="filter-checkbox" name="category[]" value="<?= $category->id ?>">
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
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
                                </svg>
                                Brands
                            </h3>
                            <svg class="chevron" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>
                        <div class="section-content open">
                            <div class="filter-list" id="brand-list">
                                <?php foreach (array_slice($brands, 0, 5) as $brand): ?>
                                    <label class="filter-checkbox-label">
                                        <input type="checkbox" class="filter-checkbox" name="brand[]" value="<?= $brand->id ?>">
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

                <!-- Products Area -->
                <div class="shop-content">
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

                    <!-- Products Grid -->
                    <div id="products-grid" class="products-grid">
                        <!-- Products will be loaded here -->
                    </div>

                    <!-- Loading State -->
                    <div id="loading-state" class="loading-state" style="display: none;">
                        <div class="spinner"></div>
                        <p>Loading products...</p>
                    </div>

                    <!-- Empty State -->
                    <div id="empty-state" class="empty-state" style="display: none;">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                        <h3>No products found</h3>
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

    <!-- Footer -->
    <footer class="site-footer">
        <div class="container">
            <p>&copy; 2025 YourStore. All rights reserved.</p>
        </div>
    </footer>
</div>

<!-- Toast Notification -->
<div id="toast" class="toast"></div>

<div id="comparison-bar"
     style="display: none; position: fixed; bottom: 0; left: 0; right: 0; background: #000; color: white; padding: 1rem; justify-content: space-between; align-items: center; z-index: 1000;">
    <div class="comparison-count">0 products selected</div>
    <button onclick="compareProducts()" class="btn btn-primary">Compare Products</button>
</div>

<script id="all-specification-groups" type="application/json">
    <?= json_encode($specificationGroups ?? []) ?>

</script>

@include('components/share-modal')

<script>
    SITE = '<?= \App\Framework\Support\SiteContext::slug()?>'
</script>

@js('productModal.js')
@js('products.js')

</body>
</html>