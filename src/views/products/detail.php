<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product->name) ?> - Product Detail</title>
    <meta name="description" content="<?= htmlspecialchars($product->meta_description ?? $product->description) ?>">
    @css('product-detail.css')
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

            <!-- Reviews Section -->
            <section class="product-reviews">
                <div class="reviews-summary">
                    <h2 class="section-title">Customer Reviews</h2>

                    <div class="rating-overview">
                        <div class="average-rating">
                            <div class="rating-number"><?= number_format($reviewStats['average_rating'], 1) ?></div>
                            <div class="stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <svg class="star <?= $i <= round($reviewStats['average_rating']) ? 'filled' : '' ?>"
                                         width="24" height="24" viewBox="0 0 24 24">
                                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                    </svg>
                                <?php endfor; ?>
                            </div>
                            <div class="rating-count"><?= $reviewStats['total_reviews'] ?> reviews</div>
                        </div>

                        <div class="rating-breakdown">
                            <?php foreach ([5, 4, 3, 2, 1] as $rating): ?>
                                <div class="rating-bar-row">
                                    <span class="rating-label"><?= $rating ?> stars</span>
                                    <div class="rating-bar">
                                        <div class="rating-bar-fill"
                                             style="width: <?= $reviewStats['rating_percentages'][$rating] ?>%"></div>
                                    </div>
                                    <span class="rating-count"><?= $reviewStats['rating_breakdown'][$rating] ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if ($canReview['can_review']): ?>
                        <button class="btn btn-primary" id="write-review-btn">Write a Review</button>
                    <?php else: ?>
                        <p class="review-notice"><?= htmlspecialchars($canReview['reason']) ?></p>
                    <?php endif; ?>
                </div>

                <!-- Review Form Modal -->
                <div id="review-modal" class="modal" style="display: none;">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Write a Review</h3>
                            <button class="modal-close" id="close-review-modal">&times;</button>
                        </div>
                        <form id="review-form">
                            <div class="form-group">
                                <label>Rating *</label>
                                <div class="star-rating-input">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>" required>
                                        <label for="star<?= $i ?>">
                                            <svg width="32" height="32" viewBox="0 0 24 24">
                                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                            </svg>
                                        </label>
                                    <?php endfor; ?>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="review-title">Review Title</label>
                                <input type="text" id="review-title" name="title"
                                       placeholder="Sum up your experience" maxlength="200">
                            </div>

                            <div class="form-group">
                                <label for="review-comment">Your Review *</label>
                                <textarea id="review-comment" name="comment" rows="6"
                                          placeholder="Share your thoughts about this product" required></textarea>
                            </div>

                            <div class="modal-actions">
                                <button type="button" class="btn btn-secondary" id="cancel-review">Cancel</button>
                                <button type="submit" class="btn btn-primary">Submit Review</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Reviews List -->
                <div class="reviews-list" id="reviews-list">
                    <?php if (empty($reviews)): ?>
                        <p class="no-reviews">No reviews yet. Be the first to review this product!</p>
                    <?php else: ?>
                        <?php foreach ($reviews as $review): ?>
                            <div class="review-item" data-review-id="<?= $review['id'] ?>">
                                <div class="review-header">
                                    <div class="review-author">
                                        <strong><?= htmlspecialchars($review['author_name']) ?></strong>
                                        <?php if ($review['is_verified_purchase']): ?>
                                            <span class="verified-badge">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                    </svg>
                                    Verified Purchase
                                </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="review-date"><?= htmlspecialchars($review['formatted_date']) ?></div>
                                </div>

                                <div class="review-rating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <svg class="star <?= $i <= $review['rating'] ? 'filled' : '' ?>"
                                             width="16" height="16" viewBox="0 0 24 24">
                                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                        </svg>
                                    <?php endfor; ?>
                                </div>

                                <?php if (!empty($review['title'])): ?>
                                    <h4 class="review-title"><?= htmlspecialchars($review['title']) ?></h4>
                                <?php endif; ?>

                                <p class="review-comment"><?= nl2br(htmlspecialchars($review['comment'])) ?></p>

                                <div class="review-actions">
                                    <button class="btn-helpful" data-review-id="<?= $review['id'] ?>" data-helpful="true">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                                        </svg>
                                        Helpful (<span class="helpful-count"><?= $review['helpful_count'] ?></span>)
                                    </button>
                                    <button class="btn-helpful" data-review-id="<?= $review['id'] ?>" data-helpful="false">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"></path>
                                        </svg>
                                        Not Helpful (<span class="unhelpful-count"><?= $review['unhelpful_count'] ?></span>)
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Pagination -->
                        <?php if ($reviewPagination['last_page'] > 1): ?>
                            <div class="reviews-pagination" id="reviews-pagination">
                                <?php if ($reviewPagination['current_page'] > 1): ?>
                                    <button class="btn btn-secondary" data-page="<?= $reviewPagination['current_page'] - 1 ?>">
                                        Previous
                                    </button>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $reviewPagination['last_page']; $i++): ?>
                                    <button class="btn <?= $i === $reviewPagination['current_page'] ? 'btn-primary' : 'btn-secondary' ?>"
                                            data-page="<?= $i ?>">
                                        <?= $i ?>
                                    </button>
                                <?php endfor; ?>

                                <?php if ($reviewPagination['current_page'] < $reviewPagination['last_page']): ?>
                                    <button class="btn btn-secondary" data-page="<?= $reviewPagination['current_page'] + 1 ?>">
                                        Next
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </section>

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

<script>
    SITE = 'test-mike'
</script>

@js('product-reviews.js')
@js('product-detail.js')
</body>
</html>