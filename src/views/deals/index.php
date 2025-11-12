<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Today's Deals - Best Offers & Discounts</title>
    @css('deals.css')
    @css('deals-carousel.css')
</head>
<body>
<div class="page-wrapper">
    <!-- Header with navigation -->

    <main class="deals-page">
        <div class="container">
            <!-- Hero Section with Carousel -->
            <section class="deals-hero">
                 @include ('components/deals-carousel')
            </section>

            @include ('components/deal-alert-subscribe')

            <!-- Deals Tabs -->
            <div class="deals-tabs">
                <button class="tab-btn active" data-tab="all" onclick="switchTab('all')">All Deals</button>
                <button class="tab-btn" data-tab="under25" onclick="switchTab('under25')">Under £25</button>
                <button class="tab-btn" data-tab="vouchers" onclick="switchTab('vouchers')">Vouchers</button>
                <button class="tab-btn" data-tab="over50" onclick="switchTab('over50')">Over 50% Off</button>

                <!-- Category Tabs (Dynamic) -->
                <?php foreach (array_slice($categories ?? [], 0, 5) as $category): ?>
                    <button class="tab-btn" data-tab="cat-<?= $category['id'] ?>" onclick="switchTab('cat-<?= $category['id'] ?>')">
                        <?= htmlspecialchars($category['name']) ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Main Content Area -->
            <div class="deals-layout">
                <!-- Filters Sidebar -->
                <aside class="deals-sidebar">
                    <div class="sidebar-header">
                        <h3>Filters</h3>
                        <button class="reset-filters" onclick="resetDealsFilters()">Clear All</button>
                    </div>

                    <!-- Star Ratings Filter -->
                    <div class="filter-section">
                        <h4 class="filter-title">Customer Rating</h4>
                        <div class="filter-options">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <label class="filter-option">
                                    <input type="checkbox" name="rating[]" value="<?= $i ?>" onchange="applyDealsFilters()">
                                    <span class="rating-stars" style="--rating: <?= $i ?>"></span>
                                    <span class="rating-text">& Up</span>
                                </label>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Product Type Filter -->
                    <div class="filter-section collapsible open">
                        <button class="filter-toggle" onclick="toggleFilterSection(this)">
                            <h4 class="filter-title">Categories</h4>
                            <svg class="chevron" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>
                        <div class="filter-options">
                            <?php foreach (array_slice($categories ?? [], 0, 10) as $category): ?>
                                <label class="filter-option">
                                    <input type="checkbox" name="category[]" value="<?= $category['id'] ?>" onchange="applyDealsFilters()">
                                    <span><?= htmlspecialchars($category['name']) ?></span>
                                    <span class="count">(<?= $category['product_count'] ?? 0 ?>)</span>
                                </label>
                            <?php endforeach; ?>
                            <?php if (count($categories ?? []) > 10): ?>
                                <button class="show-more" onclick="showMoreFilters('category')">Show More</button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Brand Filter -->
                    <div class="filter-section collapsible">
                        <button class="filter-toggle" onclick="toggleFilterSection(this)">
                            <h4 class="filter-title">Brand</h4>
                            <svg class="chevron" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>
                        <div class="filter-options">
                            <div class="search-box">
                                <input type="text" id="brand-search" placeholder="Search brands..." onkeyup="filterBrands()">
                            </div>
                            <div id="brand-list">
                                <?php foreach (array_slice($brands ?? [], 0, 10) as $brand): ?>
                                    <label class="filter-option">
                                        <input type="checkbox" name="brand[]" value="<?= $brand['id'] ?>" onchange="applyDealsFilters()">
                                        <span><?= htmlspecialchars($brand['name']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($brands ?? []) > 10): ?>
                                <button class="show-more" onclick="showMoreFilters('brand')">Show More</button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Price Range Filter -->
                    <div class="filter-section">
                        <h4 class="filter-title">Price</h4>
                        <div class="filter-options">
                            <div class="price-inputs">
                                <input type="number" id="min-price" placeholder="Min" min="0" onchange="applyDealsFilters()">
                                <span class="separator">to</span>
                                <input type="number" id="max-price" placeholder="Max" min="0" onchange="applyDealsFilters()">
                            </div>

                            <div class="price-slider">
                                <input type="range" id="price-range-min" min="0" max="1000" value="0" step="10" oninput="updatePriceRange()">
                                <input type="range" id="price-range-max" min="0" max="1000" value="1000" step="10" oninput="updatePriceRange()">
                            </div>

                            <button class="btn-apply-price" onclick="applyDealsFilters()">Go</button>
                        </div>
                    </div>

                    <!-- Discount Percentage Filter -->
                    <div class="filter-section">
                        <h4 class="filter-title">Discount</h4>
                        <div class="filter-options">
                            <label class="filter-option">
                                <input type="radio" name="discount" value="10" onchange="applyDealsFilters()">
                                <span>10% Off or More</span>
                            </label>
                            <label class="filter-option">
                                <input type="radio" name="discount" value="25" onchange="applyDealsFilters()">
                                <span>25% Off or More</span>
                            </label>
                            <label class="filter-option">
                                <input type="radio" name="discount" value="50" onchange="applyDealsFilters()">
                                <span>50% Off or More</span>
                            </label>
                            <label class="filter-option">
                                <input type="radio" name="discount" value="75" onchange="applyDealsFilters()">
                                <span>75% Off or More</span>
                            </label>

                            <div class="discount-custom">
                                <input type="number" id="custom-discount" placeholder="Custom %" min="0" max="100" onchange="applyCustomDiscount()">
                                <span>% or more</span>
                            </div>
                        </div>
                    </div>
                </aside>

                <!-- Deals Grid -->
                <div class="deals-content">
                    <!-- Toolbar -->
                    <div class="deals-toolbar">
                        <div class="results-info">
                            <span id="deals-count">0 deals</span>
                        </div>
                        <div class="toolbar-actions">
                            <select id="deals-sort" class="sort-select" onchange="applyDealsFilters()">
                                <option value="discount:desc">Discount: High to Low</option>
                                <option value="discount:asc">Discount: Low to High</option>
                                <option value="price:asc">Price: Low to High</option>
                                <option value="price:desc">Price: High to Low</option>
                                <option value="rating:desc">Rating: High to Low</option>
                                <option value="newest">Newest Deals</option>
                            </select>
                        </div>
                    </div>

                    <!-- Deals Grid -->
                    <div id="deals-grid" class="deals-grid">
                        <!-- Deals will be loaded here -->
                    </div>

                    <!-- Loading State -->
                    <div id="deals-loading" class="loading-state" style="display: none;">
                        <div class="spinner"></div>
                        <p>Finding the best deals...</p>
                    </div>

                    <!-- Empty State -->
                    <div id="deals-empty" class="empty-state" style="display: none;">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M16 16s-1.5-2-4-2-4 2-4 2"></path>
                            <line x1="9" y1="9" x2="9.01" y2="9"></line>
                            <line x1="15" y1="9" x2="15.01" y2="9"></line>
                        </svg>
                        <h3>No deals found</h3>
                        <p>Try adjusting your filters or check back later for new deals</p>
                    </div>

                    <!-- Pagination -->
                    <div id="deals-pagination" class="pagination">
                        <!-- Pagination will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </main>

    @include('footer', ['footerMenu' => $footerMenu ?? null])
</div>

<!-- Hidden data for JavaScript -->
<script>
    site = '<?= \App\Framework\Support\SiteContext::slug() ?? '' ?>';
    categories = <?= json_encode($categories ?? []) ?>;
    brands = <?= json_encode($brands ?? []) ?>;
</script>

@js('deals-carousel.js')
@js('deals.js')
@js('price-alert.js')

</body>
</html>