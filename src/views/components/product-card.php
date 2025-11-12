<?php
$product = $product ?? null;
if (!$product) return;

$productUrl = "/shop/details/{$product->slug}";
$productImage = $product->image ?? '/images/placeholder.jpg';
$hasDiscount = !empty($product->sale_price) && $product->sale_price < $product->price;
$displayPrice = $hasDiscount ? $product->sale_price : $product->price;
?>

<div class="product-card">
    <a href="<?= $productUrl ?>" class="product-image">
        <img src="<?= $productImage ?>" alt="<?= htmlspecialchars($product->name) ?>">
        <?php if ($hasDiscount && !empty($product->discount_percentage)): ?>
            <span class="badge-sale">-<?= $product->discount_percentage ?>%</span>
        <?php endif; ?>
    </a>
    <div class="product-content">
        <h3 class="product-name">
            <a href="<?= $productUrl ?>"><?= htmlspecialchars($product->name) ?></a>
        </h3>
        <div class="product-price">
            <?php if ($hasDiscount): ?>
                <span class="price-sale">$<?= number_format($product->sale_price, 2) ?></span>
                <span class="price-original">$<?= number_format($product->price, 2) ?></span>
            <?php else: ?>
                <span class="price-current">$<?= number_format($product->price, 2) ?></span>
            <?php endif; ?>
        </div>
        <div class="product-actions">
            <button class="btn-add-to-cart" data-product-id="<?= $product->id ?>">
                Add to Cart
            </button>
            <button class="btn-wishlist" data-product-id="<?= $product->id ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                </svg>
            </button>
        </div>
    </div>
</div>