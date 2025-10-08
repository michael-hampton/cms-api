<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop - All Products</title>
    <link rel="stylesheet" href="/css/products.css">
</head>
<body>
<div class="page-wrapper">
    <!-- Header -->
    <header class="site-header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <a href="/">YourStore</a>
                </div>
                <nav class="main-nav">
                    <a href="/">Home</a>
                    <a href="/products" class="active">Shop</a>
                    <a href="/about">About</a>
                    <a href="/contact">Contact</a>
                </nav>
                <div class="header-actions">
                    <button class="icon-btn" id="wishlist-btn">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                        </svg>
                        <span class="badge" id="wishlist-count">0</span>
                    </button>
                    <button class="icon-btn" id="cart-btn">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                        <span class="badge" id="cart-count">0</span>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <h1 class="hero-title">Discover Amazing Products</h1>
            <p class="hero-subtitle">Quality items curated just for you</p>
        </div>
    </section>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <div class="shop-layout">
                <!-- Sidebar -->
                <aside class="shop-sidebar">
                    <div class="sidebar-section">
                        <h3 class="sidebar-title">Search</h3>
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

                    <div class="sidebar-section">
                        <h3 class="sidebar-title">Categories</h3>
                        <div class="filter-group">
                            <select id="category-filter" class="filter-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category->id ?>"><?= htmlspecialchars($category->name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="sidebar-section">
                        <h3 class="sidebar-title">Brands</h3>
                        <div class="filter-group">
                            <select id="brand-filter" class="filter-select">
                                <option value="">All Brands</option>
                                <?php foreach ($brands as $brand): ?>
                                    <option value="<?= $brand->id ?>"><?= htmlspecialchars($brand->name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="sidebar-section">
                        <h3 class="sidebar-title">Price Range</h3>
                        <div class="price-range">
                            <input type="number" id="min-price" placeholder="Min" class="price-input">
                            <span class="price-separator">-</span>
                            <input type="number" id="max-price" placeholder="Max" class="price-input">
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

<script src="/js/products.js"></script>
</body>
</html>