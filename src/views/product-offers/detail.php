<?php

/** @var \App\Models\ProductOffer $offer */
$product = $offer->product;
$merchant = $offer->merchant;
$imageUrl = $product?->images->first()?->url ?? $product?->image ?? '/assets/images/placeholder.jpg';
$savings = $product->price - $offer->sale_price;

?>

<div class="offer-detail-page">
    <div class="container">
        <!-- Breadcrumb -->
        <nav class="breadcrumb">
            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/offers">Offers</a>
            <span class="separator">›</span>
            <span class="current"><?= htmlspecialchars($product->name) ?></span>
        </nav>

        <div class="offer-detail-grid">
            <!-- Left Column - Product Info -->
            <div class="offer-main">
                <!-- Product Images -->
                <div class="product-images">
                    <div class="main-image-wrapper">
                        <img src="<?= htmlspecialchars($imageUrl) ?>"
                             alt="<?= htmlspecialchars($product->name) ?>"
                             class="main-image"
                             id="mainImage">
                        <div class="discount-badge-large"><?= $offer->discount_percentage ?>% OFF</div>
                    </div>

                    <?php if (count($product->images) > 1): ?>
                        <div class="thumbnail-gallery">
                            <?php foreach ($product->images as $index => $image): ?>
                                <img src="<?= htmlspecialchars($image->url) ?>"
                                     alt="<?= htmlspecialchars($product->name) ?>"
                                     class="thumbnail <?= $index === 0 ? 'active' : '' ?>"
                                     onclick="changeMainImage('<?= htmlspecialchars($image->url) ?>', this)">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Product Details -->
                <div class="product-details">
                    <h1 class="product-title"><?= htmlspecialchars($product->name) ?></h1>

                    <div class="merchant-info">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             class="merchant-icon">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        </svg>
                        <span>Sold by <strong><?= htmlspecialchars($merchant->name) ?></strong></span>
                    </div>

                    <?php if ($product->description): ?>
                        <div class="product-description">
                            <h2>Product Description</h2>
                            <p><?= nl2br(htmlspecialchars($product->description)) ?></p>
                        </div>
                    <?php endif; ?>

                    <!-- Product Specifications -->
                    <?php if ($product->specifications && count($product->specifications) > 0): ?>
                        <div class="product-specs">
                            <h2>Specifications</h2>
                            <div class="specs-grid">
                                <?php foreach ($product->specifications as $spec): ?>
                                    <div class="spec-item">
                                        <span class="spec-label"><?= htmlspecialchars($spec->key) ?>:</span>
                                        <span class="spec-value"><?= htmlspecialchars($spec->value) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column - Pricing & Actions -->
            <div class="offer-sidebar">
                <div class="pricing-card">
                    <div class="pricing-header">
                        <h3>Special Offer</h3>
                        <?php if ($offer->valid_until): ?>
                            <div class="offer-expires">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                     class="clock-icon">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                                Expires: <?= date('M d, Y', strtotime($offer->valid_until)) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="pricing-details">
                        <div class="price-row">
                            <span class="label">Regular Price:</span>
                            <span class="regular-price">$<?= number_format($product->price, 2) ?></span>
                        </div>

                        <div class="price-row offer-price-row">
                            <span class="label">Offer Price:</span>
                            <span class="offer-price">$<?= number_format($offer->sale_price, 2) ?></span>
                        </div>

                        <div class="savings-highlight">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                 class="savings-icon">
                                <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            </svg>
                            <span>You Save $<?= number_format($savings, 2) ?></span>
                        </div>
                    </div>

                    <div class="quantity-selector">
                        <label>Quantity:</label>
                        <div class="quantity-controls">
                            <button class="qty-btn" onclick="changeQuantity(-1)">−</button>
                            <input type="number" id="quantity" value="1" min="1" max="99" readonly>
                            <button class="qty-btn" onclick="changeQuantity(1)">+</button>
                        </div>
                    </div>

                    <div class="action-buttons">
                        <button class="btn-add-to-cart" data-offer-id="<?= $offer->id ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="9" cy="21" r="1"/>
                                <circle cx="20" cy="21" r="1"/>
                                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                            </svg>
                            Add to Cart
                        </button>

                        <button class="btn-wishlist" data-offer-id="<?= $offer->id ?>">
                            <svg viewBox="0 0 24 24" stroke-width="2">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                            </svg>
                            Add to Wishlist
                        </button>
                    </div>

                    <!-- Merchant Link -->
                    <?php if ($offer->affiliate_url): ?>
                        <a href="<?= htmlspecialchars($offer->affiliate_url) ?>"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="btn-merchant-link"
                           onclick="trackOfferClick()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                                <polyline points="15 3 21 3 21 9"/>
                                <line x1="10" y1="14" x2="21" y2="3"/>
                            </svg>
                            View on <?= htmlspecialchars($merchant->name) ?>
                        </a>
                    <?php endif; ?>

                    <!-- Trust Badges -->
                    <div class="trust-section">
                        <div class="trust-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            </svg>
                            <span>Secure Purchase</span>
                        </div>
                        <div class="trust-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>Best Price Guarantee</span>
                        </div>
                    </div>
                </div>

                <!-- Stock Status -->
                <div class="stock-card">
                    <div class="stock-status in-stock">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        <span>In Stock</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .offer-detail-page {
        background: #f8fafc;
        min-height: 100vh;
        padding: 40px 0;
    }

    .container {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 20px;
    }

    .breadcrumb {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 32px;
        font-size: 14px;
    }

    .breadcrumb a {
        color: #64748b;
        text-decoration: none;
        transition: color 0.2s;
    }

    .breadcrumb a:hover {
        color: #f59e0b;
    }

    .breadcrumb .separator {
        color: #cbd5e1;
    }

    .breadcrumb .current {
        color: #1e293b;
        font-weight: 600;
    }

    .offer-detail-grid {
        display: grid;
        grid-template-columns: 1fr 400px;
        gap: 32px;
    }

    .offer-main {
        display: flex;
        flex-direction: column;
        gap: 32px;
    }

    .product-images {
        background: white;
        border-radius: 16px;
        padding: 40px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .main-image-wrapper {
        position: relative;
        margin-bottom: 20px;
    }

    .main-image {
        width: 100%;
        height: 500px;
        object-fit: contain;
        background: #f8fafc;
        border-radius: 12px;
    }

    .discount-badge-large {
        position: absolute;
        top: 20px;
        right: 20px;
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
        padding: 12px 24px;
        border-radius: 12px;
        font-weight: 700;
        font-size: 18px;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
    }

    .thumbnail-gallery {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 12px;
    }

    .thumbnail {
        width: 100%;
        height: 100px;
        object-fit: cover;
        border-radius: 8px;
        cursor: pointer;
        border: 2px solid transparent;
        transition: all 0.2s;
    }

    .thumbnail:hover,
    .thumbnail.active {
        border-color: #f59e0b;
    }

    .product-details {
        background: white;
        border-radius: 16px;
        padding: 40px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .product-title {
        font-size: 32px;
        font-weight: 700;
        color: #1e293b;
        margin: 0 0 20px 0;
        line-height: 1.2;
    }

    .merchant-info {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 16px;
        background: #f8fafc;
        border-radius: 8px;
        margin-bottom: 32px;
        font-size: 14px;
        color: #64748b;
    }

    .merchant-icon {
        width: 18px;
        height: 18px;
    }

    .product-description,
    .product-specs {
        margin-bottom: 32px;
    }

    .product-description h2,
    .product-specs h2 {
        font-size: 20px;
        font-weight: 700;
        color: #1e293b;
        margin: 0 0 16px 0;
    }

    .product-description p {
        font-size: 16px;
        color: #64748b;
        line-height: 1.7;
    }

    .specs-grid {
        display: grid;
        gap: 12px;
    }

    .spec-item {
        display: grid;
        grid-template-columns: 180px 1fr;
        padding: 12px;
        background: #f8fafc;
        border-radius: 6px;
        font-size: 14px;
    }

    .spec-label {
        font-weight: 600;
        color: #475569;
    }

    .spec-value {
        color: #64748b;
    }

    .offer-sidebar {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .pricing-card,
    .stock-card {
        background: white;
        border-radius: 16px;
        padding: 32px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        position: sticky;
        top: 20px;
    }

    .pricing-header h3 {
        font-size: 20px;
        font-weight: 700;
        color: #1e293b;
        margin: 0 0 12px 0;
    }

    .offer-expires {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        color: #ef4444;
        font-weight: 600;
        margin-bottom: 24px;
    }

    .clock-icon {
        width: 16px;
        height: 16px;
    }

    .pricing-details {
        margin-bottom: 24px;
    }

    .price-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }

    .price-row .label {
        font-size: 14px;
        color: #64748b;
        font-weight: 600;
    }

    .regular-price {
        font-size: 18px;
        color: #94a3b8;
        text-decoration: line-through;
        font-weight: 600;
    }

    .offer-price-row {
        padding: 16px;
        background: #fef3c7;
        border-radius: 8px;
    }

    .offer-price {
        font-size: 36px;
        font-weight: 700;
        color: #f59e0b;
    }

    .savings-highlight {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 16px;
        background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
        border-radius: 8px;
        font-size: 16px;
        font-weight: 700;
        color: #166534;
        margin-top: 12px;
    }

    .savings-icon {
        width: 20px;
        height: 20px;
    }

    .quantity-selector {
        margin-bottom: 20px;
    }

    .quantity-selector label {
        display: block;
        font-size: 14px;
        font-weight: 600;
        color: #475569;
        margin-bottom: 8px;
    }

    .quantity-controls {
        display: grid;
        grid-template-columns: auto 1fr auto;
        gap: 0;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        overflow: hidden;
    }

    .qty-btn {
        width: 48px;
        height: 48px;
        background: #f8fafc;
        border: none;
        font-size: 20px;
        font-weight: 700;
        color: #475569;
        cursor: pointer;
        transition: all 0.2s;
    }

    .qty-btn:hover {
        background: #e2e8f0;
    }

    #quantity {
        border: none;
        text-align: center;
        font-size: 16px;
        font-weight: 600;
        color: #1e293b;
        background: white;
    }

    .action-buttons {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-bottom: 20px;
    }

    .btn-add-to-cart,
    .btn-wishlist,
    .btn-merchant-link {
        padding: 16px 24px;
        border: none;
        border-radius: 10px;
        font-weight: 700;
        font-size: 16px;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        text-decoration: none;
    }

    .btn-add-to-cart {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
    }

    .btn-add-to-cart:hover {
        box-shadow: 0 8px 20px rgba(245, 158, 11, 0.4);
        transform: translateY(-2px);
    }

    .btn-add-to-cart svg,
    .btn-wishlist svg,
    .btn-merchant-link svg {
        width: 22px;
        height: 22px;
        stroke: currentColor;
        fill: none;
    }

    .btn-wishlist {
        background: white;
        border: 2px solid #e2e8f0;
        color: #475569;
    }

    .btn-wishlist:hover {
        background: #fef3c7;
        border-color: #fbbf24;
    }

    .btn-wishlist.active {
        background: #fef3c7;
        border-color: #fbbf24;
    }

    .btn-wishlist.active svg {
        fill: #f59e0b;
        stroke: #f59e0b;
    }

    .btn-merchant-link {
        background: #1e293b;
        color: white;
    }

    .btn-merchant-link:hover {
        background: #334155;
        transform: translateY(-2px);
    }

    .trust-section {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        padding-top: 24px;
        border-top: 2px solid #f1f5f9;
    }

    .trust-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
        text-align: center;
    }

    .trust-item svg {
        width: 24px;
        height: 24px;
        color: #10b981;
    }

    .trust-item span {
        font-size: 12px;
        color: #64748b;
        font-weight: 600;
    }

    .stock-status {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 16px;
        border-radius: 8px;
        font-weight: 600;
    }

    .stock-status.in-stock {
        background: #dcfce7;
        color: #166534;
    }

    .stock-status svg {
        width: 20px;
        height: 20px;
    }

    @media (max-width: 1024px) {
        .offer-detail-grid {
            grid-template-columns: 1fr;
        }

        .pricing-card {
            position: static;
        }
    }

    @media (max-width: 768px) {
        .main-image {
            height: 350px;
        }

        .product-title {
            font-size: 24px;
        }

        .spec-item {
            grid-template-columns: 1fr;
            gap: 4px;
        }

        .trust-section {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
    (function () {
        const offerId = <?= $offer->id ?>;
        let quantity = 1;
        let wishlist = JSON.parse(localStorage.getItem('wishlist') || '[]');

        const addButton = document.querySelector('.btn-add-to-cart');
        const wishlistButton = document.querySelector('.btn-wishlist');
        const quantityInput = document.getElementById('quantity');

        // Check if in wishlist
        if (wishlist.includes(offerId)) {
            wishlistButton.classList.add('active');
        }

        // Change main image
        window.changeMainImage = function (url, thumbnail) {
            document.getElementById('mainImage').src = url;
            document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
            thumbnail.classList.add('active');
        };

        // Change quantity
        window.changeQuantity = function (delta) {
            quantity = Math.max(1, Math.min(99, quantity + delta));
            quantityInput.value = quantity;
        };

        // Add to cart
        addButton.addEventListener('click', async () => {
            try {
                const response = await fetch('/api/<?= \App\Framework\Support\SiteContext::slug() ?>/cart/offer', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        product_offer_id: offerId,
                        quantity: quantity
                    })
                });

                if (response.ok) {
                    showNotification('Added to cart!', 'success');
                } else {
                    showNotification('Failed to add to cart', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Failed to add to cart', 'error');
            }
        });

        // Toggle wishlist
        wishlistButton.addEventListener('click', () => {
            const index = wishlist.indexOf(offerId);

            if (index > -1) {
                wishlist.splice(index, 1);
                wishlistButton.classList.remove('active');
                showNotification('Removed from wishlist', 'info');
            } else {
                wishlist.push(offerId);
                wishlistButton.classList.add('active');
                showNotification('Added to wishlist!', 'success');
            }

            localStorage.setItem('wishlist', JSON.stringify(wishlist));
        });

        // Track offer click
        window.trackOfferClick = async function () {
            try {
                await fetch('/api/<?= \App\Framework\Support\SiteContext::slug() ?>/products/<?= $product->id ?>/offers/<?= $offer->id ?>/track', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'}
                });
            } catch (error) {
                console.error('Error tracking click:', error);
            }
        };

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 24px;
            background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
            color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 10000;
            font-weight: 600;
        `;
            notification.textContent = message;
            document.body.appendChild(notification);

            setTimeout(() => notification.remove(), 3000);
        }
    })();
</script>