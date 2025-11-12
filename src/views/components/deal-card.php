<div class="deal-card">
    <div class="deal-badge">
        <span><?= $deal['discount_percentage'] ?>% OFF</span>
    </div>

    <button class="deal-wishlist-btn" onclick="event.stopPropagation(); toggleWishlist(<?= $deal['product_id'] ?>, this)">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
        </svg>
    </button>

    <a href="/shop/details/<?= $deal['slug'] ?>" class="deal-image-link">
        <img src="<?= $deal['image'] ?>" alt="<?= htmlspecialchars($deal['title']) ?>" class="deal-image">
    </a>

    <div class="deal-content">
        <h3 class="deal-title">
            <a href="/shop/details/<?= $deal['slug'] ?>"><?= htmlspecialchars($deal['title']) ?></a>
        </h3>

        <?php if ($deal['rating'] > 0): ?>
            <div class="deal-rating">
                <div class="stars" style="--rating: <?= $deal['rating'] ?>"></div>
                <span class="review-count">(<?= $deal['review_count'] ?>)</span>
            </div>
        <?php endif; ?>

        <div class="deal-prices">
            <span class="was-price">Was £<?= number_format($deal['original_price'], 2) ?></span>
            <span class="now-price">£<?= number_format($deal['sale_price'], 2) ?></span>
        </div>

        <div class="deal-actions">
            <button class="deal-add-cart" onclick="event.stopPropagation(); addToCart(<?= $deal['product_id'] ?>)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                </svg>
                Add to Cart
            </button>
            <button class="deal-cta" onclick="window.location.href='/shop/details/<?= $deal['slug'] ?>'">View Deal</button>
        </div>

    </div>
</div>