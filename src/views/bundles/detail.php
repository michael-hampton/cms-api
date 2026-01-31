<?php
/** @var \App\Models\ProductOfferBundle $bundle */
?>

<div class="bundle-detail-page">
    <div class="container">
        <!-- Breadcrumb -->
        <nav class="breadcrumb">
            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/bundles">Bundles</a>
            <span class="separator">›</span>
            <span class="current"><?= htmlspecialchars($bundle->name) ?></span>
        </nav>

        <div class="bundle-detail-grid">
            <!-- Left Column - Bundle Info -->
            <div class="bundle-main">
                <div class="bundle-header">
                    <h1 class="bundle-title"><?= htmlspecialchars($bundle->name) ?></h1>
                    <?php if ($bundle->description): ?>
                        <p class="bundle-description"><?= htmlspecialchars($bundle->description) ?></p>
                    <?php endif; ?>

                    <?php
                    $merchantIds = [];
                    foreach ($bundle->items as $item) {
                        $merchantId = $item->product_offer?->merchant_id ?? $item->product?->merchants[0]?->merchant_id;
                        if ($merchantId) {
                            $merchantIds[$merchantId] = true;
                        }
                    }
                    $isMultiMerchant = count($merchantIds) > 1;
                    ?>

                    <?php if ($isMultiMerchant): ?>
                        <span class="multi-merchant-badge">Multi-Merchant Bundle</span>
                    <?php endif; ?>
                </div>

                <!-- Bundle Items -->
                <div class="bundle-items-section">
                    <h2 class="section-title">What's Included (<?= count($bundle->items) ?> Items)</h2>

                    <div class="bundle-items-list">
                        <?php foreach ($bundle->items as $item): ?>
                            <?php
                            $product = $item->product_offer?->product ?? $item->product;
                            $merchant = $item->product_offer?->merchant ?? $item->product?->merchants[0]?->merchant;
                            $imageUrl = $product?->images[0]?->url ?? $product?->image ?? '/assets/images/placeholder.jpg';
                            $productPrice = $item->product_offer?->sale_price ?? $product?->sale_price ?? $product?->price ?? 0;
                            ?>

                            <div class="bundle-item-card">
                                <div class="item-image-wrapper">
                                    <img src="<?= htmlspecialchars($imageUrl) ?>"
                                         alt="<?= htmlspecialchars($product?->name ?? 'Product') ?>"
                                         class="item-image">
                                    <span class="item-quantity-badge">×<?= $item->quantity ?></span>
                                </div>

                                <div class="item-details">
                                    <h3 class="item-name"><?= htmlspecialchars($product?->name ?? 'Unknown Product') ?></h3>

                                    <?php if ($merchant): ?>
                                        <div class="item-merchant">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                 class="merchant-icon">
                                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                            </svg>
                                            <?= htmlspecialchars($merchant->name) ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($product?->description): ?>
                                        <p class="item-description"><?= htmlspecialchars(substr($product->description, 0, 150)) ?><?= strlen($product->description) > 150 ? '...' : '' ?></p>
                                    <?php endif; ?>

                                    <div class="item-price">
                                        <span class="price-label">Individual Price:</span>
                                        <span class="price-value">$<?= number_format($productPrice * $item->quantity, 2) ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Bundle Features/Benefits -->
                <?php if ($bundle->features): ?>
                    <div class="bundle-features-section">
                        <h2 class="section-title">Bundle Benefits</h2>
                        <ul class="features-list">
                            <?php foreach (json_decode($bundle->features, true) ?? [] as $feature): ?>
                                <li class="feature-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                         class="check-icon">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                    <?= htmlspecialchars($feature) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right Column - Pricing & Actions -->
            <div class="bundle-sidebar">
                <div class="pricing-card">
                    <div class="pricing-header">
                        <h3>Bundle Pricing</h3>
                    </div>

                    <div class="pricing-breakdown">
                        <div class="price-row">
                            <span class="price-label">Individual Items Total:</span>
                            <span class="regular-price">$<?= number_format($bundle->total_price, 2) ?></span>
                        </div>

                        <div class="price-row bundle-price-row">
                            <span class="price-label">Bundle Price:</span>
                            <span class="bundle-price">$<?= number_format($bundle->bundle_price, 2) ?></span>
                        </div>

                        <div class="savings-row">
                            <div class="savings-badge">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                     class="savings-icon">
                                    <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                </svg>
                                <span>You Save $<?= number_format($bundle->total_price - $bundle->bundle_price, 2) ?></span>
                            </div>
                            <div class="discount-badge"><?= $bundle->discount_percentage ?>% OFF</div>
                        </div>
                    </div>

                    <div class="action-buttons">
                        <button class="btn-add-bundle" data-bundle-id="<?= $bundle->id ?>">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="9" cy="21" r="1"/>
                                <circle cx="20" cy="21" r="1"/>
                                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                            </svg>
                            Add Bundle to Cart
                        </button>

                        <button class="btn-wishlist" data-bundle-id="<?= $bundle->id ?>">
                            <svg viewBox="0 0 24 24" stroke-width="2">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                            </svg>
                            Add to Wishlist
                        </button>
                    </div>

                    <!-- Trust Badges -->
                    <div class="trust-badges">
                        <div class="trust-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            </svg>
                            <span>Secure Checkout</span>
                        </div>
                        <div class="trust-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                            </svg>
                            <span>Best Value</span>
                        </div>
                    </div>
                </div>

                <!-- Stock Status (if needed) -->
                <div class="availability-card">
                    <div class="availability-status available">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        <span>All items in stock</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .bundle-detail-page {
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

    .bundle-detail-grid {
        display: grid;
        grid-template-columns: 1fr 400px;
        gap: 32px;
    }

    .bundle-main {
        background: white;
        border-radius: 16px;
        padding: 40px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .bundle-header {
        margin-bottom: 40px;
        padding-bottom: 32px;
        border-bottom: 2px solid #f1f5f9;
    }

    .bundle-title {
        font-size: 36px;
        font-weight: 700;
        color: #1e293b;
        margin: 0 0 16px 0;
        line-height: 1.2;
    }

    .bundle-description {
        font-size: 18px;
        color: #64748b;
        line-height: 1.6;
        margin: 0 0 16px 0;
    }

    .multi-merchant-badge {
        display: inline-block;
        padding: 8px 16px;
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        color: #1e40af;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .section-title {
        font-size: 24px;
        font-weight: 700;
        color: #1e293b;
        margin: 0 0 24px 0;
    }

    .bundle-items-section {
        margin-bottom: 40px;
    }

    .bundle-items-list {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .bundle-item-card {
        display: grid;
        grid-template-columns: 140px 1fr;
        gap: 24px;
        padding: 24px;
        background: #f8fafc;
        border-radius: 12px;
        transition: all 0.2s;
    }

    .bundle-item-card:hover {
        background: #f1f5f9;
    }

    .item-image-wrapper {
        position: relative;
    }

    .item-image {
        width: 100%;
        height: 140px;
        object-fit: cover;
        border-radius: 8px;
        background: white;
    }

    .item-quantity-badge {
        position: absolute;
        top: -8px;
        right: -8px;
        background: #f59e0b;
        color: white;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 14px;
        box-shadow: 0 2px 8px rgba(245, 158, 11, 0.4);
    }

    .item-details {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .item-name {
        font-size: 18px;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
    }

    .item-merchant {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        color: #64748b;
        font-weight: 600;
    }

    .merchant-icon {
        width: 16px;
        height: 16px;
    }

    .item-description {
        font-size: 14px;
        color: #64748b;
        line-height: 1.5;
        margin: 4px 0;
    }

    .item-price {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: auto;
    }

    .price-label {
        font-size: 13px;
        color: #64748b;
        font-weight: 600;
    }

    .price-value {
        font-size: 16px;
        font-weight: 700;
        color: #1e293b;
    }

    .bundle-features-section {
        padding: 32px;
        background: #fef3c7;
        border-radius: 12px;
    }

    .features-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .feature-item {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 16px;
        color: #78350f;
        font-weight: 500;
    }

    .check-icon {
        width: 20px;
        height: 20px;
        color: #f59e0b;
        flex-shrink: 0;
    }

    .bundle-sidebar {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .pricing-card,
    .availability-card {
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
        margin: 0 0 24px 0;
    }

    .pricing-breakdown {
        display: flex;
        flex-direction: column;
        gap: 16px;
        margin-bottom: 24px;
    }

    .price-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .price-row .price-label {
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

    .bundle-price-row {
        padding: 16px;
        background: #fef3c7;
        border-radius: 8px;
        margin: 8px 0;
    }

    .bundle-price {
        font-size: 32px;
        font-weight: 700;
        color: #f59e0b;
    }

    .savings-row {
        display: flex;
        gap: 12px;
        align-items: center;
        padding-top: 16px;
        border-top: 2px solid #f1f5f9;
    }

    .savings-badge {
        flex: 1;
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 16px;
        background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
        border-radius: 8px;
        font-size: 15px;
        font-weight: 700;
        color: #166534;
    }

    .savings-icon {
        width: 20px;
        height: 20px;
    }

    .discount-badge {
        padding: 12px 16px;
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
        border-radius: 8px;
        font-size: 15px;
        font-weight: 700;
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
    }

    .action-buttons {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-bottom: 24px;
    }

    .btn-add-bundle,
    .btn-wishlist {
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
    }

    .btn-add-bundle {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
    }

    .btn-add-bundle:hover {
        box-shadow: 0 8px 20px rgba(245, 158, 11, 0.4);
        transform: translateY(-2px);
    }

    .btn-add-bundle svg,
    .btn-wishlist svg {
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

    .trust-badges {
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

    .availability-status {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 16px;
        border-radius: 8px;
        font-weight: 600;
    }

    .availability-status.available {
        background: #dcfce7;
        color: #166534;
    }

    .availability-status svg {
        width: 20px;
        height: 20px;
    }

    @media (max-width: 1024px) {
        .bundle-detail-grid {
            grid-template-columns: 1fr;
        }

        .pricing-card {
            position: static;
        }
    }

    @media (max-width: 768px) {
        .bundle-main {
            padding: 24px;
        }

        .bundle-title {
            font-size: 28px;
        }

        .bundle-item-card {
            grid-template-columns: 1fr;
            gap: 16px;
        }

        .trust-badges {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
    (function () {
        const bundleId = <?= $bundle->id ?>;
        let wishlist = JSON.parse(localStorage.getItem('bundleWishlist') || '[]');

        const addButton = document.querySelector('.btn-add-bundle');
        const wishlistButton = document.querySelector('.btn-wishlist');

        // Check if in wishlist
        if (wishlist.includes(bundleId)) {
            wishlistButton.classList.add('active');
        }

        // Add to cart
        addButton.addEventListener('click', async () => {
            try {
                const response = await fetch('/api/<?= \App\Framework\Support\SiteContext::slug() ?>/cart/bundle', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        bundle_id: bundleId,
                        quantity: 1
                    })
                });

                if (response.ok) {
                    showNotification('Bundle added to cart!', 'success');
                } else {
                    showNotification('Failed to add bundle to cart', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Failed to add bundle to cart', 'error');
            }
        });

        // Toggle wishlist
        wishlistButton.addEventListener('click', () => {
            const index = wishlist.indexOf(bundleId);

            if (index > -1) {
                wishlist.splice(index, 1);
                wishlistButton.classList.remove('active');
                showNotification('Removed from wishlist', 'info');
            } else {
                wishlist.push(bundleId);
                wishlistButton.classList.add('active');
                showNotification('Added to wishlist!', 'success');
            }

            localStorage.setItem('bundleWishlist', JSON.stringify(wishlist));
        });

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