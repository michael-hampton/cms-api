<?php
// views/dashboard/widgets/product-offers-widget.php

$maxItems = $maxItems ?? 6;
$sortBy = $sortBy ?? 'newest';
$widgetId = 'offers-widget-' . uniqid();
?>

<div class="dashboard-widget offers-widget" id="<?= $widgetId ?>">
    <div class="widget-header">
        <div class="header-content">
            <svg class="widget-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
            <div>
                <h3 class="widget-title">Product Offers</h3>
                <p class="widget-subtitle">Special deals and discounts</p>
            </div>
        </div>
        <div class="widget-controls">
            <select class="sort-select" data-widget="<?= $widgetId ?>">
                <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest</option>
                <option value="discount" <?= $sortBy === 'discount' ? 'selected' : '' ?>>Highest Discount</option>
                <option value="featured" <?= $sortBy === 'featured' ? 'selected' : '' ?>>Featured</option>
            </select>
            <a href="/product-offers" class="btn-view-all">
                View All
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
    </div>

    <div class="widget-loading" style="display: none;">
        <div class="spinner"></div>
        <p>Loading offers...</p>
    </div>

    <div class="widget-error" style="display: none;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <p class="error-message">Failed to load offers</p>
    </div>

    <div class="widget-content">
        <div class="offers-grid">
            <!-- Offers will be loaded here -->
        </div>
    </div>

    <div class="widget-empty" style="display: none;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <p>No offers available</p>
    </div>
</div>

<style>
    .dashboard-widget {
        background: white;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        margin-bottom: 24px;
    }

    .widget-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 24px;
        border-bottom: 2px solid #f1f5f9;
        background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
        flex-wrap: wrap;
        gap: 16px;
    }

    .header-content {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .widget-icon {
        width: 40px;
        height: 40px;
        padding: 8px;
        background: white;
        border-radius: 10px;
        color: #f59e0b;
        box-shadow: 0 2px 8px rgba(245, 158, 11, 0.2);
    }

    .widget-title {
        font-size: 20px;
        font-weight: 700;
        color: #1e293b;
        margin: 0 0 4px 0;
    }

    .widget-subtitle {
        font-size: 13px;
        color: #64748b;
        margin: 0;
    }

    .widget-controls {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .sort-select {
        padding: 8px 12px;
        border: 2px solid #e2e8f0;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        background: white;
        cursor: pointer;
        transition: all 0.2s;
    }

    .sort-select:hover {
        border-color: #f59e0b;
    }

    .sort-select:focus {
        outline: none;
        border-color: #f59e0b;
        box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
    }

    .btn-view-all {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        background: white;
        border: 2px solid #f59e0b;
        border-radius: 8px;
        color: #f59e0b;
        font-weight: 600;
        font-size: 14px;
        text-decoration: none;
        transition: all 0.2s;
    }

    .btn-view-all:hover {
        background: #f59e0b;
        color: white;
    }

    .btn-view-all svg {
        width: 16px;
        height: 16px;
        stroke: currentColor;
    }

    .widget-loading,
    .widget-error,
    .widget-empty {
        text-align: center;
        padding: 60px 20px;
    }

    .spinner {
        width: 40px;
        height: 40px;
        border: 4px solid #f1f5f9;
        border-top-color: #f59e0b;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        margin: 0 auto 16px;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    .widget-error svg,
    .widget-empty svg {
        width: 48px;
        height: 48px;
        color: #cbd5e1;
        margin: 0 auto 12px;
    }

    .widget-error p,
    .widget-empty p {
        color: #64748b;
        margin: 0;
    }

    .widget-content {
        padding: 24px;
    }

    .offers-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
    }

    .offer-item {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
        cursor: pointer;
        transition: all 0.3s;
    }

    .offer-item:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        border-color: #f59e0b;
    }

    .offer-image-container {
        position: relative;
        width: 100%;
        height: 180px;
        background: #f1f5f9;
    }

    .offer-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .discount-badge {
        position: absolute;
        top: 12px;
        right: 12px;
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
        padding: 6px 12px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 12px;
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
    }

    .offer-details {
        padding: 16px;
    }

    .offer-name {
        font-size: 16px;
        font-weight: 700;
        color: #1e293b;
        margin: 0 0 6px 0;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        line-height: 1.4;
    }

    .offer-merchant {
        font-size: 12px;
        color: #64748b;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.5px;
        margin: 0 0 12px 0;
    }

    .offer-pricing {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 12px;
    }

    .sale-price {
        font-size: 22px;
        font-weight: 700;
        color: #f59e0b;
    }

    .original-price {
        font-size: 16px;
        color: #94a3b8;
        text-decoration: line-through;
    }

    .btn-add-cart {
        width: 100%;
        padding: 10px 16px;
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .btn-add-cart:hover {
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
        transform: translateY(-1px);
    }

    .btn-add-cart svg {
        width: 18px;
        height: 18px;
        stroke: currentColor;
    }

    @media (max-width: 768px) {
        .offers-grid {
            grid-template-columns: 1fr;
        }

        .widget-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .widget-controls {
            width: 100%;
            justify-content: space-between;
        }
    }
</style>

<script>
    (function () {
        const widgetId = '<?= $widgetId ?>';
        const maxItems = <?= $maxItems ?>;
        let currentSort = '<?= $sortBy ?>';

        const widget = document.getElementById(widgetId);
        const sortSelect = widget.querySelector('.sort-select');
        const loadingEl = widget.querySelector('.widget-loading');
        const errorEl = widget.querySelector('.widget-error');
        const contentEl = widget.querySelector('.widget-content');
        const emptyEl = widget.querySelector('.widget-empty');
        const offersGrid = widget.querySelector('.offers-grid');

        async function loadOffers() {
            showLoading();

            try {
                const params = new URLSearchParams({
                    status: 'published',
                    is_active: 'true',
                    per_page: maxItems
                });

                // Apply sorting
                switch (currentSort) {
                    case 'newest':
                        params.append('sort_by', 'created_at');
                        params.append('sort_order', 'desc');
                        break;
                    case 'discount':
                        params.append('sort_by', 'discount_percentage');
                        params.append('sort_order', 'desc');
                        break;
                    case 'featured':
                        params.append('is_featured', 'true');
                        break;
                }

                const response = await fetch(`/api/product-offers?${params.toString()}`);
                const data = await response.json();

                if (data.success && data.offers && data.offers.items) {
                    renderOffers(data.offers.items);
                } else {
                    showEmpty();
                }
            } catch (error) {
                console.error('Error loading offers:', error);
                showError();
            }
        }

        function renderOffers(offers) {
            hideLoading();
            hideError();

            if (offers.length === 0) {
                showEmpty();
                return;
            }

            hideEmpty();

            offersGrid.innerHTML = offers.map(offer => createOfferCard(offer)).join('');

            // Attach event listeners
            offersGrid.querySelectorAll('.offer-item').forEach(card => {
                card.addEventListener('click', function (e) {
                    if (!e.target.closest('.btn-add-cart')) {
                        window.location.href = `/product-offers/${this.dataset.offerId}`;
                    }
                });
            });

            offersGrid.querySelectorAll('.btn-add-cart').forEach(btn => {
                btn.addEventListener('click', async function (e) {
                    e.stopPropagation();
                    const offerId = this.dataset.offerId;
                    await addToCart(offerId);
                });
            });
        }

        function createOfferCard(offer) {
            const imageUrl = offer.product?.images?.[0]?.url || '/assets/images/placeholder.jpg';
            const productName = offer.product?.name || 'Unnamed Product';
            const merchantName = offer.merchant?.name || 'Unknown Merchant';

            return `
            <div class="offer-item" data-offer-id="${offer.id}">
                <div class="offer-image-container">
                    <img src="${imageUrl}" alt="${productName}" class="offer-image">
                    <div class="discount-badge">${offer.discount_percentage}% OFF</div>
                </div>
                <div class="offer-details">
                    <h4 class="offer-name">${productName}</h4>
                    <p class="offer-merchant">${merchantName}</p>
                    <div class="offer-pricing">
                        <span class="sale-price">$${offer.sale_price.toFixed(2)}</span>
                        <span class="original-price">$${offer.original_price.toFixed(2)}</span>
                    </div>
                    <button class="btn-add-cart" data-offer-id="${offer.id}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="9" cy="21" r="1"/>
                            <circle cx="20" cy="21" r="1"/>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                        </svg>
                        Add to Cart
                    </button>
                </div>
            </div>
        `;
        }

        async function addToCart(offerId) {
            try {
                const response = await fetch('/api/cart/items', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        product_offer_id: offerId,
                        quantity: 1
                    })
                });

                if (response.ok) {
                    showNotification('Added to cart!', 'success');
                    // Update cart count if available
                    if (window.updateCartCount) {
                        window.updateCartCount();
                    }
                } else {
                    showNotification('Failed to add to cart', 'error');
                }
            } catch (error) {
                console.error('Error adding to cart:', error);
                showNotification('Failed to add to cart', 'error');
            }
        }

        function showLoading() {
            loadingEl.style.display = 'block';
            contentEl.style.display = 'none';
            errorEl.style.display = 'none';
            emptyEl.style.display = 'none';
        }

        function hideLoading() {
            loadingEl.style.display = 'none';
            contentEl.style.display = 'block';
        }

        function showError() {
            errorEl.style.display = 'block';
            contentEl.style.display = 'none';
            loadingEl.style.display = 'none';
            emptyEl.style.display = 'none';
        }

        function hideError() {
            errorEl.style.display = 'none';
        }

        function showEmpty() {
            emptyEl.style.display = 'block';
            contentEl.style.display = 'none';
            loadingEl.style.display = 'none';
            errorEl.style.display = 'none';
        }

        function hideEmpty() {
            emptyEl.style.display = 'none';
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.textContent = message;
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
            animation: slideIn 0.3s ease-out;
        `;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease-out';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Event listeners
        sortSelect.addEventListener('change', function () {
            currentSort = this.value;
            loadOffers();
        });

        // Initial load
        loadOffers();
    })();
</script>