<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product->name) ?> - Product Detail</title>
    <meta name="description" content="<?= htmlspecialchars($product->meta_description ?? $product->description) ?>">
    <link rel="stylesheet" href="/css/product-detail.css">
    <?php if (isset($structuredData)): ?>
        <script type="application/ld+json">
            <?= json_encode($structuredData) ?>
        </script>
    <?php endif; ?>
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
                    <a href="/products">Shop</a>
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

    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <div class="container">
            <a href="/">Home</a>
            <span class="separator">/</span>
            <a href="/products">Products</a>
            <span class="separator">/</span>
            <span><?= htmlspecialchars($product->name) ?></span>
        </div>
    </div>

    <!-- Product Detail -->
    <main class="main-content">
        <div class="container">
            <div class="product-detail">
                <!-- Product Images -->
                <div class="product-images">
                    <div class="main-image">
                        <img src="<?= htmlspecialchars($product->image_url ?? '/images/placeholder.jpg') ?>"
                             alt="<?= htmlspecialchars($product->name) ?>"
                             id="main-product-image">
                        <?php if ($product->discount_percentage > 0): ?>
                            <div class="badge-sale">-<?= $product->discount_percentage ?>%</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Product Info -->
                <div class="product-info">
                    <h1 class="product-title"><?= htmlspecialchars($product->name) ?></h1>

                    <div class="product-meta">
                        <?php if (isset($product->brand)): ?>
                            <span class="brand">Brand: <strong><?= htmlspecialchars($product->brand->name) ?></strong></span>
                        <?php endif; ?>
                        <?php if (isset($product->category)): ?>
                            <span class="category">Category: <strong><?= htmlspecialchars($product->category->name) ?></strong></span>
                        <?php endif; ?>
                    </div>

                    <div class="product-price">
                        <?php if ($product->sale_price && $product->sale_price < $product->price): ?>
                            <span class="price-sale">$<?= number_format($product->sale_price, 2) ?></span>
                            <span class="price-original">$<?= number_format($product->price, 2) ?></span>
                        <?php else: ?>
                            <span class="price-current">$<?= number_format($product->price, 2) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="product-description">
                        <p><?= nl2br(htmlspecialchars($product->description)) ?></p>
                    </div>

                    <div class="product-stock">
                        <?php if ($product->in_stock ?? true): ?>
                            <span class="stock-status in-stock">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                    In Stock
                                </span>
                        <?php else: ?>
                            <span class="stock-status out-of-stock">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="15" y1="9" x2="9" y2="15"></line>
                                        <line x1="9" y1="9" x2="15" y2="15"></line>
                                    </svg>
                                    Out of Stock
                                </span>
                        <?php endif; ?>
                    </div>

                    <div class="product-actions">
                        <div class="quantity-selector">
                            <button type="button" class="qty-btn" id="qty-decrease">-</button>
                            <input type="number" id="quantity" value="1" min="1" max="99" readonly>
                            <button type="button" class="qty-btn" id="qty-increase">+</button>
                        </div>

                        <button class="btn btn-primary btn-add-to-cart" id="add-to-cart-btn" data-product-id="<?= $product->id ?>">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <circle cx="9" cy="21" r="1"></circle>
                                <circle cx="20" cy="21" r="1"></circle>
                                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                            </svg>
                            Add to Cart
                        </button>

                        <button class="btn btn-wishlist <?= $isInWishlist ? 'active' : '' ?>"
                                id="toggle-wishlist-btn"
                                data-product-id="<?= $product->id ?>"
                                data-in-wishlist="<?= $isInWishlist ? 'true' : 'false' ?>">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="<?= $isInWishlist ? 'currentColor' : 'none' ?>" stroke="currentColor">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                            </svg>
                            <?= $isInWishlist ? 'Remove from Wishlist' : 'Add to Wishlist' ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Related Products -->
            <?php if (!empty($relatedProducts) && count($relatedProducts) > 0): ?>
                <section class="related-products">
                    <h2 class="section-title">Related Products</h2>
                    <div class="products-grid">
                        <?php foreach ($relatedProducts as $relatedProduct): ?>
                            <div class="product-card">
                                <a href="/products/<?= htmlspecialchars($relatedProduct->slug) ?>" class="product-image">
                                    <img src="<?= htmlspecialchars($relatedProduct->image_url ?? '/images/placeholder.jpg') ?>"
                                         alt="<?= htmlspecialchars($relatedProduct->name) ?>">
                                    <?php if ($relatedProduct->discount_percentage > 0): ?>
                                        <span class="badge-sale">-<?= $relatedProduct->discount_percentage ?>%</span>
                                    <?php endif; ?>
                                </a>
                                <div class="product-content">
                                    <h3 class="product-name">
                                        <a href="/products/<?= htmlspecialchars($relatedProduct->slug) ?>">
                                            <?= htmlspecialchars($relatedProduct->name) ?>
                                        </a>
                                    </h3>
                                    <div class="product-price">
                                        <?php if ($relatedProduct->sale_price && $relatedProduct->sale_price < $relatedProduct->price): ?>
                                            <span class="price-sale">$<?= number_format($relatedProduct->sale_price, 2) ?></span>
                                            <span class="price-original">$<?= number_format($relatedProduct->price, 2) ?></span>
                                        <?php else: ?>
                                            <span class="price-current">$<?= number_format($relatedProduct->price, 2) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <button class="btn btn-sm btn-add-to-cart" data-product-id="<?= $relatedProduct->id ?>">
                                        Add to Cart
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- Recently Viewed -->
            <?php if (!empty($recentlyViewed) && count($recentlyViewed) > 1): ?>
                <section class="recently-viewed">
                    <h2 class="section-title">Recently Viewed</h2>
                    <div class="products-grid">
                        <?php foreach ($recentlyViewed as $viewedProduct): ?>
                            <?php if ($viewedProduct->id !== $product->id): ?>
                                <div class="product-card">
                                    <a href="/products/<?= htmlspecialchars($viewedProduct->slug) ?>" class="product-image">
                                        <img src="<?= htmlspecialchars($viewedProduct->image_url ?? '/images/placeholder.jpg') ?>"
                                             alt="<?= htmlspecialchars($viewedProduct->name) ?>">
                                    </a>
                                    <div class="product-content">
                                        <h3 class="product-name">
                                            <a href="/products/<?= htmlspecialchars($viewedProduct->slug) ?>">
                                                <?= htmlspecialchars($viewedProduct->name) ?>
                                            </a>
                                        </h3>
                                        <div class="product-price">
                                            <span class="price-current">$<?= number_format($viewedProduct->price, 2) ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
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

<script src="/js/product-detail.js"></script>
</body>
</html>