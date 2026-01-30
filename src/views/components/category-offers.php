<?php
/**
 * Category Offers Component
 * Displays active offers for products in the category
 *
 * @var array $offers - Array of product offer objects with product and merchant data
 * @var object $category - Category object
 */

$offerCount = count($offers ?? []);
$hasOffers = $offerCount > 0;
?>

@css('category-offers.css')

<section class="category-offers-section">
    <div class="category-offers-header">
        <div>
            <h2 class="category-offers-title">
                <svg class="category-offers-icon" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                </svg>
                Special Offers
            </h2>
            <?php if ($hasOffers): ?>
                <p class="category-offers-subtitle">Limited time deals
                    on <?= htmlspecialchars($category->name ?? 'Category') ?> products</p>
            <?php endif; ?>
        </div>
        <?php if ($hasOffers): ?>
            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/shop?category_ids=<?= $category->id ?>&on_sale=1"
               class="view-all-offers-btn">
                View All Offers
            </a>
        <?php endif; ?>
    </div>

    <?php if ($hasOffers): ?>
        <div class="category-offers-grid" id="category-offers-grid">
            <?php foreach ($offers as $offer):
                $product = $offer['product'] ?? null;
                if (!$product) continue;

                $merchant = $offer['merchant'] ?? null;
                $discountPercentage = $offer['discount_percentage'] ?? 0;
                $savings = $product['price'] - $offer['sale_price'];

                // Calculate time remaining
                $endDate = new DateTime($offer['end_date']);
                $now = new DateTime();
                $diff = $now->diff($endDate);
                $daysLeft = $diff->days;
                $hoursLeft = $diff->h;
                ?>
                <div class="category-offer-card" data-product-id="<?= $product['id'] ?>"
                     data-offer-id="<?= $offer['id'] ?>">
                    <div class="category-offer-ribbon">
                        SAVE <?= $discountPercentage ?>%
                    </div>

                    <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/shop/details/<?= htmlspecialchars($product['slug']) ?>"
                       class="category-offer-image">
                        <img src="<?= htmlspecialchars($product['image'] ?? '/images/placeholder.jpg') ?>"
                             alt="<?= htmlspecialchars($product['name']) ?>">

                        <?php if ($daysLeft <= 2): ?>
                            <div class="category-offer-timer">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <?php if ($daysLeft == 0): ?>
                                    Ends in <?= $hoursLeft ?>h
                                <?php else: ?>
                                    <?= $daysLeft ?> day<?= $daysLeft > 1 ? 's' : '' ?> left
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </a>

                    <div class="category-offer-content">
                        <?php if ($merchant): ?>
                            <div class="category-offer-merchant">
                                <svg width="12" height="12" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M3 3h18v18H3V3zm16 16V5H5v14h14z"/>
                                </svg>
                                <?= htmlspecialchars($merchant['name']) ?>
                            </div>
                        <?php endif; ?>

                        <h3 class="category-offer-name">
                            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/shop/details/<?= htmlspecialchars($product['slug']) ?>">
                                <?= htmlspecialchars($product['name']) ?>
                            </a>
                        </h3>

                        <?php if (isset($product['average_rating']) && $product['average_rating'] > 0): ?>
                            <div class="category-offer-rating">
                                <div class="category-offer-rating-stars">
                                    <?php
                                    $rating = $product['average_rating'];
                                    for ($i = 1; $i <= 5; $i++):
                                        $filled = $i <= floor($rating);
                                        ?>
                                        <svg class="category-offer-rating-star <?= $filled ? 'filled' : 'empty' ?>"
                                             viewBox="0 0 24 24">
                                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                        </svg>
                                    <?php endfor; ?>
                                </div>
                                <span class="category-offer-rating-count">(<?= $product['review_count'] ?? 0 ?>)</span>
                            </div>
                        <?php endif; ?>

                        <div class="category-offer-prices">
                            <div class="category-offer-price-row">
                                <span class="category-offer-price-label">Was:</span>
                                <span class="category-offer-price-original">$<?= number_format($product['price'], 2) ?></span>
                            </div>
                            <div class="category-offer-price-row">
                                <span class="category-offer-price-label">Now:</span>
                                <span class="category-offer-price-sale">$<?= number_format($offer['sale_price'], 2) ?></span>
                                <span class="category-offer-savings">
                                    <svg width="12" height="12" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M9 11l3 3L22 4"/>
                                    </svg>
                                    Save $<?= number_format($savings, 2) ?>
                                </span>
                            </div>
                        </div>

                        <div class="category-offer-dates">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <div class="category-offer-dates-text">
                                Offer valid until <?= date('M j, Y', strtotime($offer['end_date'])) ?>
                            </div>
                        </div>

                        <div class="category-offer-actions">
                            <button class="category-offer-btn-cart"
                                    data-product-id="<?= $product['id'] ?>"
                                    onclick="handleCategoryOfferAddToCart(event, <?= $product['id'] ?>)">
                                Add to Cart
                            </button>
                            <button class="category-offer-btn-wishlist"
                                    data-product-id="<?= $product['id'] ?>"
                                    onclick="handleCategoryOfferWishlist(event, <?= $product['id'] ?>)">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="category-offers-empty">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/>
            </svg>
            <h3>No Active Offers</h3>
            <p>Check back soon for amazing deals on <?= htmlspecialchars($category->name ?? 'these') ?> products!</p>
        </div>
    <?php endif; ?>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const offerCards = document.querySelectorAll('.category-offer-card');
        offerCards.forEach(card => {
            const offerId = card.dataset.offerId;
            const productId = card.dataset.productId;

            // Track view
            fetch(`/api/${SITE}/products/${productId}/offers/${offerId}/track`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'view'
                })
            }).catch(err => console.error('Failed to track view:', err));
        });
    });

    // Add to Cart Handler for Offers
    async function handleCategoryOfferAddToCart(event, productId) {
        event.preventDefault();
        const btn = event.currentTarget;
        const originalText = btn.textContent;
        const offerId = btn.closest('.category-offer-card').dataset.offerId;

        btn.disabled = true;
        btn.textContent = 'Adding...';

        try {
            // Track the click
            await fetch(`/api/${SITE}/products/${productId}/offers/${offerId}/track`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'click'
                })
            });

            const response = await fetch(`/api/${SITE}/cart/add`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    product_id: productId,
                    quantity: 1
                })
            });

            const data = await response.json();

            if (data.success) {
                showCategoryOfferToast(data.message || 'Added to cart!', 'success');
                const cartCount = document.getElementById('cart-count');
                if (cartCount && data.count) {
                    cartCount.textContent = data.count;
                    cartCount.style.display = 'block';
                }
            } else {
                showCategoryOfferToast(data.message || 'Failed to add to cart', 'error');
            }
        } catch (error) {
            console.error('Error adding to cart:', error);
            showCategoryOfferToast('Failed to add item to cart', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    // Wishlist Handler for Offers
    async function handleCategoryOfferWishlist(event, productId) {
        event.preventDefault();
        const btn = event.currentTarget;
        const isInWishlist = btn.classList.contains('active');

        btn.disabled = true;

        try {
            const url = isInWishlist
                ? `/api/${SITE}/wishlist/remove/${productId}`
                : `/api/${SITE}/wishlist/add`;

            const response = await fetch(url, {
                method: isInWishlist ? 'DELETE' : 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: isInWishlist ? null : JSON.stringify({product_id: productId})
            });

            const data = await response.json();

            if (data.success) {
                btn.classList.toggle('active');
                showCategoryOfferToast(data.message, 'success');

                // Update wishlist count if element exists
                const wishlistCount = document.getElementById('wishlist-count');
                if (wishlistCount && data.count !== undefined) {
                    wishlistCount.textContent = data.count;
                    wishlistCount.style.display = data.count > 0 ? 'block' : 'none';
                }
            } else {
                showCategoryOfferToast(data.message || 'Failed to update wishlist', 'error');
            }
        } catch (error) {
            console.error('Error toggling wishlist:', error);
            showCategoryOfferToast('An error occurred', 'error');
        } finally {
            btn.disabled = false;
        }
    }

    // Toast notification for offers
    function showCategoryOfferToast(message, type = 'info') {
        // Check if toast element exists, if not create it
        let toast = document.getElementById('category-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'category-toast';
            toast.className = 'toast';
            document.body.appendChild(toast);
        }

        toast.textContent = message;
        toast.className = `toast ${type} show`;

        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }
</script>