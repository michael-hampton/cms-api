<?php
// views/dashboard/widgets/bundles-widget.php

$maxItems = $maxItems ?? 6;
$sortBy = $sortBy ?? 'newest';
$widgetId = 'bundles-widget-' . uniqid();
?>

<div class="dashboard-widget bundles-widget" id="<?= $widgetId ?>">
    <div class="widget-header">
        <div class="header-content">
            <svg class="widget-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                <line x1="12" y1="22.08" x2="12" y2="12"/>
            </svg>
            <div>
                <h3 class="widget-title">Product Bundles</h3>
                <p class="widget-subtitle">Save more with bundled deals</p>
            </div>
        </div>
        <div class="widget-controls">
            <select class="sort-select" data-widget="<?= $widgetId ?>">
                <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest</option>
                <option value="savings" <?= $sortBy === 'savings' ? 'selected' : '' ?>>Highest Savings</option>
                <option value="featured" <?= $sortBy === 'featured' ? 'selected' : '' ?>>Featured</option>
            </select>
            <a href="/bundles" class="btn-view-all">
                View All
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
    </div>

    <div class="widget-loading" style="display: none;">
        <div class="spinner"></div>
        <p>Loading bundles...</p>
    </div>

    <div class="widget-error" style="display: none;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <p class="error-message">Failed to load bundles</p>
    </div>

    <div class="widget-content">
        <div class="bundles-grid">
            <!-- Bundles will be loaded here -->
        </div>
    </div>

    <div class="widget-empty" style="display: none;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
        </svg>
        <p>No bundles available</p>
    </div>
</div>

<style>
    .bundles-widget .widget-header {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    }

    .bundles-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }

    .bundle-item {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
        cursor: pointer;
        transition: all 0.3s;
        background: white;
    }

    .bundle-item:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        border-color: #f59e0b;
    }

    .bundle-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        padding: 16px;
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        border-bottom: 2px solid #fbbf24;
    }

    .bundle-name {
        font-size: 16px;
        font-weight: 700;
        color: #92400e;
        margin: 0;
        flex: 1;
        line-height: 1.4;
    }

    .bundle-discount-badge {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
        padding: 4px 10px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 11px;
        white-space: nowrap;
        margin-left: 12px;
        box-shadow: 0 2px 6px rgba(239, 68, 68, 0.3);
    }

    .bundle-items-preview {
        padding: 16px;
    }

    .items-count {
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 12px;
    }

    .items-images {
        display: flex;
        align-items: center;
    }

    .item-thumbnail {
        width: 50px;
        height: 50px;
        border-radius: 8px;
        object-fit: cover;
        border: 3px solid white;
        background: #f1f5f9;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        margin-left: -8px;
    }

    .item-thumbnail:first-child {
        margin-left: 0;
    }

    .more-items {
        width: 50px;
        height: 50px;
        border-radius: 8px;
        background: #f1f5f9;
        border: 3px solid white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        color: #64748b;
        font-size: 13px;
        margin-left: -8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .bundle-pricing {
        padding: 16px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
    }

    .price-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }

    .price-row:last-of-type {
        margin-bottom: 12px;
    }

    .price-label {
        font-size: 13px;
        color: #64748b;
        font-weight: 600;
    }

    .regular-price {
        font-size: 14px;
        color: #94a3b8;
        text-decoration: line-through;
    }

    .price-row.highlight .price-label {
        color: #1e293b;
        font-weight: 700;
    }

    .bundle-price {
        font-size: 24px;
        font-weight: 700;
        color: #f59e0b;
    }

    .savings-badge {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 8px 12px;
        background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
        border-radius: 6px;
        font-size: 14px;
        font-weight: 700;
        color: #166534;
    }

    .savings-badge svg {
        width: 16px;
        height: 16px;
        stroke: #059669;
    }

    .btn-add-bundle {
        width: 100%;
        padding: 12px 16px;
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
        border: none;
        border-top: 2px solid #e2e8f0;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .btn-add-bundle:hover {
        background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
    }

    .btn-add-bundle svg {
        width: 18px;
        height: 18px;
        stroke: currentColor;
    }

    @media (max-width: 768px) {
        .bundles-grid {
            grid-template-columns: 1fr;
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
        const bundlesGrid = widget.querySelector('.bundles-grid');

        async function loadBundles() {
            showLoading();

            try {
                const params = new URLSearchParams({
                    status: 'published',
                    is_active: 'true'
                });

                const response = await fetch(`/api/bundles?${params.toString()}`);
                const data = await response.json();

                if (data.success && data.bundles && data.bundles.items) {
                    let bundles = data.bundles.items;
                    bundles = sortBundles(bundles);
                    bundles = bundles.slice(0, maxItems);
                    renderBundles(bundles);
                } else {
                    showEmpty();
                }
            } catch (error) {
                console.error('Error loading bundles:', error);
                showError();
            }
        }

        function sortBundles(bundles) {
            return bundles.sort((a, b) => {
                switch (currentSort) {
                    case 'newest':
                        return new Date(b.created_at) - new Date(a.created_at);
                    case 'savings':
                        const savingsA = a.total_price - a.bundle_price;
                        const savingsB = b.total_price - b.bundle_price;
                        return savingsB - savingsA;
                    case 'featured':
                        return (b.is_featured ? 1 : 0) - (a.is_featured ? 1 : 0);
                    default:
                        return 0;
                }
            });
        }

        function renderBundles(bundles) {
            hideLoading();
            hideError();

            if (bundles.length === 0) {
                showEmpty();
                return;
            }

            hideEmpty();

            bundlesGrid.innerHTML = bundles.map(bundle => createBundleCard(bundle)).join('');

            // Attach event listeners
            bundlesGrid.querySelectorAll('.bundle-item').forEach(card => {
                card.addEventListener('click', function (e) {
                    if (!e.target.closest('.btn-add-bundle')) {
                        window.location.href = `/bundles/${this.dataset.bundleId}`;
                    }
                });
            });

            bundlesGrid.querySelectorAll('.btn-add-bundle').forEach(btn => {
                btn.addEventListener('click', async function (e) {
                    e.stopPropagation();
                    const bundleId = this.dataset.bundleId;
                    await addBundleToCart(bundleId);
                });
            });
        }

        function createBundleCard(bundle) {
            const savings = bundle.total_price - bundle.bundle_price;
            const displayItems = bundle.items.slice(0, 3);
            const remainingCount = bundle.items.length - 3;

            const itemImages = displayItems.map(item => {
                const product = item.product || item.product_offer?.product;
                const imageUrl = product?.images?.[0]?.url || '/assets/images/placeholder.jpg';
                const productName = product?.name || 'Product';
                return `<img src="${imageUrl}" alt="${productName}" class="item-thumbnail">`;
            }).join('');

            const moreItems = remainingCount > 0
                ? `<div class="more-items">+${remainingCount}</div>`
                : '';

            return `
            <div class="bundle-item" data-bundle-id="${bundle.id}">
                <div class="bundle-header">
                    <h4 class="bundle-name">${bundle.name}</h4>
                    <div class="bundle-discount-badge">${bundle.discount_percentage}% OFF</div>
                </div>
                <div class="bundle-items-preview">
                    <div class="items-count">${bundle.items.length} item(s)</div>
                    <div class="items-images">
                        ${itemImages}
                        ${moreItems}
                    </div>
                </div>
                <div class="bundle-pricing">
                    <div class="price-row">
                        <span class="price-label">Regular:</span>
                        <span class="regular-price">$${bundle.total_price.toFixed(2)}</span>
                    </div>
                    <div class="price-row highlight">
                        <span class="price-label">Bundle:</span>
                        <span class="bundle-price">$${bundle.bundle_price.toFixed(2)}</span>
                    </div>
                    <div class="savings-badge">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                        Save $${savings.toFixed(2)}
                    </div>
                </div>
                <button class="btn-add-bundle" data-bundle-id="${bundle.id}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                    </svg>
                    Add Bundle to Cart
                </button>
            </div>
        `;
        }

        async function addBundleToCart(bundleId) {
            try {
                const response = await fetch('/api/cart/bundles', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        bundle_id: bundleId
                    })
                });

                if (response.ok) {
                    showNotification('Bundle added to cart!', 'success');
                    if (window.updateCartCount) {
                        window.updateCartCount();
                    }
                } else {
                    showNotification('Failed to add bundle to cart', 'error');
                }
            } catch (error) {
                console.error('Error adding bundle to cart:', error);
                showNotification('Failed to add bundle to cart', 'error');
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
            loadBundles();
        });

        // Initial load
        loadBundles();
    })();
</script>