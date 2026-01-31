<style>
    .related-offers-section,
    .related-bundles-section {
        margin: 48px 0;
        padding: 32px;
        background: white;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .section-header {
        text-align: center;
        margin-bottom: 32px;
    }

    .section-header h2 {
        font-size: 32px;
        font-weight: 700;
        color: #1e293b;
        margin: 0 0 8px 0;
    }

    .section-header p {
        font-size: 16px;
        color: #64748b;
        margin: 0;
    }

    .offers-carousel,
    .bundles-carousel {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
    }

    .offer-card-compact {
        position: relative;
        padding: 20px;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        transition: all 0.3s;
        cursor: pointer;
    }

    .offer-card-compact:hover {
        border-color: #f59e0b;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }

    .offer-badge {
        position: absolute;
        top: 16px;
        right: 16px;
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
        padding: 6px 12px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 11px;
        box-shadow: 0 2px 6px rgba(239, 68, 68, 0.3);
    }

    .offer-info h4 {
        font-size: 14px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin: 0 0 12px 0;
    }

    .offer-prices {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 8px;
    }

    .sale-price {
        font-size: 28px;
        font-weight: 700;
        color: #f59e0b;
    }

    .original-price {
        font-size: 18px;
        color: #94a3b8;
        text-decoration: line-through;
    }

    .savings-text {
        font-size: 14px;
        color: #059669;
        font-weight: 600;
        margin: 0 0 16px 0;
    }

    .btn-get-offer {
        width: 100%;
        padding: 12px 20px;
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-get-offer:hover {
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
        transform: translateY(-1px);
    }

    .bundle-card-compact {
        padding: 20px;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        transition: all 0.3s;
        cursor: pointer;
    }

    .bundle-card-compact:hover {
        border-color: #f59e0b;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }

    .bundle-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 16px;
    }

    .bundle-header h4 {
        font-size: 18px;
        font-weight: 700;
        color: #1e293b;
        margin: 0;
        flex: 1;
    }

    .discount-badge {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
        padding: 4px 10px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 11px;
        margin-left: 12px;
        box-shadow: 0 2px 6px rgba(239, 68, 68, 0.3);
    }

    .bundle-preview {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px;
        background: #f8fafc;
        border-radius: 8px;
        margin-bottom: 16px;
    }

    .items-count {
        font-size: 13px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .bundle-savings {
        font-size: 14px;
        font-weight: 700;
        color: #059669;
    }

    .bundle-pricing {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
    }

    .bundle-price {
        font-size: 28px;
        font-weight: 700;
        color: #f59e0b;
    }

    .regular-price {
        font-size: 18px;
        color: #94a3b8;
        text-decoration: line-through;
    }

    .btn-view-bundle {
        width: 100%;
        padding: 12px 20px;
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-view-bundle:hover {
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
        transform: translateY(-1px);
    }

    @media (max-width: 768px) {
        .offers-carousel,
        .bundles-carousel {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php if (!empty($relatedOffers)): ?>
    <div class="related-offers-section">
        <div class="section-header">
            <h2>Special Offers</h2>
            <p>Get this product at a discounted price</p>
        </div>
        <div class="offers-carousel">
            <?php foreach ($relatedOffers as $offer): ?>
                <div class="offer-card-compact" data-offer-id="<?= $offer['id'] ?>">
                    <div class="offer-badge"><?= $offer['discount_percentage'] ?>% OFF</div>
                    <div class="offer-info">
                        <h4><?= htmlspecialchars($offer['merchant']['name'] ?? 'Unknown Merchant') ?></h4>
                        <div class="offer-prices">
                            <span class="sale-price">$<?= number_format($offer['sale_price'], 2) ?></span>
                            <span class="original-price">$<?= number_format($offer['original_price'], 2) ?></span>
                        </div>
                        <p class="savings-text">Save
                            $<?= number_format($offer['original_price'] - $offer['sale_price'], 2) ?></p>
                    </div>
                    <button class="btn-get-offer" data-offer-id="<?= $offer['id'] ?>">
                        Get This Offer
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<script>
    // Product Detail Page Interactions
    (function () {
        // Get offer buttons
        document.querySelectorAll('.btn-get-offer').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const offerId = btn.dataset.offerId;

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
                        showNotification('Offer added to cart!', 'success');
                    } else {
                        showNotification('Failed to add offer to cart', 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('Failed to add offer to cart', 'error');
                }
            });
        });

        // View bundle buttons
        document.querySelectorAll('.btn-view-bundle').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const bundleId = btn.dataset.bundleId;
                window.location.href = `/bundles/${bundleId}`;
            });
        });

        // Click on offer card
        document.querySelectorAll('.offer-card-compact').forEach(card => {
            card.addEventListener('click', () => {
                const offerId = card.dataset.offerId;
                window.location.href = `/product-offers/${offerId}`;
            });
        });

        // Click on bundle card
        document.querySelectorAll('.bundle-card-compact').forEach(card => {
            card.addEventListener('click', () => {
                const bundleId = card.dataset.bundleId;
                window.location.href = `/bundles/${bundleId}`;
            });
        });

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
    })();
</script>
