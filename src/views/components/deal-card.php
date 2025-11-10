<div class="deal-card">
    <div class="deal-badge">
        <span><?= $deal['discount_percentage'] ?>% OFF</span>
    </div>

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

        <button class="deal-cta" onclick="viewDeal('<?= $deal['slug'] ?>')">View Deal</button>
    </div>
</div>