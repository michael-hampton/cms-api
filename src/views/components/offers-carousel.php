<div class="offers-carousel-section" id="offers-carousel-section" style="display: none;">
    <div class="section-header"
         style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h2 style="font-size: 1.75rem; font-weight: 700; color: #1e293b; margin-bottom: 0.5rem;">
                ⚡ Limited-Time Offers
            </h2>
            <p style="color: #64748b; font-size: 0.875rem;">
                Exclusive deals that won't last long
            </p>
        </div>
        <div class="carousel-controls" style="display: flex; gap: 0.5rem;">
            <button onclick="scrollOffersCarousel('left')" class="carousel-btn"
                    style="width: 40px; height: 40px; border-radius: 50%; border: 1px solid #e2e8f0; background: white; cursor: pointer;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>
            <button onclick="scrollOffersCarousel('right')" class="carousel-btn"
                    style="width: 40px; height: 40px; border-radius: 50%; border: 1px solid #e2e8f0; background: white; cursor: pointer;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>
        </div>
    </div>

    <div class="offers-carousel" id="offers-carousel"
         style="display: flex; gap: 1.5rem; overflow-x: auto; scroll-behavior: smooth; padding-bottom: 1rem; -webkit-overflow-scrolling: touch; scrollbar-width: thin;">
        <!-- Offers will be populated here -->
    </div>
</div>

<script>
    SITE = '<?= \App\Framework\Support\SiteContext::slug()?>'

    function scrollOffersCarousel(direction) {
        const carousel = document.getElementById('offers-carousel');
        const scrollAmount = 300;

        if (direction === 'left') {
            carousel.scrollLeft -= scrollAmount;
        } else {
            carousel.scrollLeft += scrollAmount;
        }
    }

    async function loadOffersCarousel() {
        try {
            const response = await fetch(`/${SITE}/product-offers/search?status=active`);
            const data = await response.json();

            console.log('data', data.data.offers.items)

            if (!data.success || !data.data?.offers?.items || data.data?.offers?.items.length === 0) {
                document.getElementById('offers-carousel-section').style.display = 'none';
                return;
            }

            const carousel = document.getElementById('offers-carousel');
            carousel.innerHTML = data.data.offers.items.map(offer => createOfferCard(offer)).join('');

            document.getElementById('offers-carousel-section').style.display = 'block';
        } catch (error) {
            console.error('Error loading offers:', error);
        }
    }

    function createOfferCard(offer) {
        const isInStock = offer.product?.stock_quantity > 0;
        const discount = offer.discount_percentage;

        console.log('offer', offer)

        return `
        <div class="offer-card" style="min-width: 280px; background: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; transition: transform 0.3s;">
            <div style="position: relative; padding-top: 100%; overflow: hidden; background: #f8fafc;">
                <img src="${offer.product?.image || '/images/placeholder.jpg'}"
                     alt="${offer.product?.name}"
                     style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover;">

                <span style="position: absolute; top: 1rem; left: 1rem; background: #ef4444; color: white; padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.75rem; font-weight: 600;">
                    ${discount}% OFF
                </span>

                <span style="position: absolute; top: 1rem; right: 1rem; background: #f59e0b; color: white; padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.75rem; font-weight: 600;">
                   Offer
                </span>

                ${!isInStock ? '<span style="position: absolute; bottom: 1rem; left: 1rem; background: #64748b; color: white; padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.75rem; font-weight: 600;">Out of Stock</span>' : ''}
            </div>

            <div style="padding: 1.25rem;">
                <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem; color: #1e293b;">
                    ${offer.product?.name}
                </h3>

                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                    <span style="font-size: 1.25rem; font-weight: 700; color: #ef4444;">
                        $${parseFloat(offer.sale_price).toFixed(2)}
                    </span>
                    <span style="font-size: 1rem; color: #64748b; text-decoration: line-through;">
                        $${parseFloat(offer.product?.price).toFixed(2)}
                    </span>
                </div>

                ${offer.merchant?.name ? `
                <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 1rem;">
                    Sold by: ${offer.merchant?.name}
                </div>
                ` : ''}

                <div style="display: flex; gap: 0.5rem;">
                    <button onclick="addOfferToCart(${offer.id})"
                            ${!isInStock ? 'disabled' : ''}
                            style="flex: 1; padding: 0.75rem; background: ${isInStock ? '#2563eb' : '#94a3b8'}; color: white; border: none; border-radius: 0.5rem; font-weight: 600; cursor: ${isInStock ? 'pointer' : 'not-allowed'};">
                        ${isInStock ? 'Add to Cart' : 'Out of Stock'}
                    </button>

                    <button onclick="addOfferToWishlist(${offer.id})"
                            ${!isInStock ? 'disabled' : ''}
                            style="padding: 0.75rem; background: white; color: #2563eb; border: 2px solid #2563eb; border-radius: 0.5rem; cursor: ${isInStock ? 'pointer' : 'not-allowed'};">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    `;
    }

    async function addOfferToCart(offerId) {
        try {
            const response = await fetch(`/api/${SITE}/cart/offer`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({product_offer_id: offerId})
            });

            const data = await response.json();

            if (data.success) {
                alert('yes1')
                showToast('Offer added to cart!');
                await loadCartCountForOffer();
            } else {
                showToast(data.message || 'Failed to add offer', 'error');
            }
        } catch (error) {
            console.error('Error adding offer to cart:', error);
            showToast('Failed to add offer to cart', 'error');
        }
    }

    // Update wishlist count
    function updateWishlistCount(count) {
        document.getElementById('wishlist-count').textContent = count;
    }

    // Update cart count
    function updateCartCount(count) {
        document.getElementById('cart-count').textContent = count;
    }

    // Load cart count
    async function loadCartCountForOffer() {
        try {
            const response = await fetch(`/api/${SITE}/cart`);
            const data = await response.json();
            updateCartCount(data.count || 0);
        } catch (error) {
            alert('no')
            console.error('Error loading cart count:', error);
        }
    }

    async function addOfferToWishlist(offerId) {
        try {
            const response = await fetch(`/${SITE}/wishlist/offer`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({offer_id: offerId})
            });

            const data = await response.json();

            if (data.success) {
                showToast('Offer added to wishlist!');
                updateWishlistCount(data.count);
            } else {
                showToast(data.message || 'Failed to add to wishlist', 'error');
            }
        } catch (error) {
            console.error('Error adding offer to wishlist:', error);
            showToast('Failed to add to wishlist', 'error');
        }
    }

    // Load on page load
    if (typeof SITE !== 'undefined') {
        loadOffersCarousel();
    }
</script>

<style>
    .offers-carousel::-webkit-scrollbar {
        height: 8px;
    }

    .offers-carousel::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 4px;
    }

    .offers-carousel::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }

    .offers-carousel::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    .offer-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    }

    .carousel-btn:hover {
        background: #f8fafc;
        border-color: #2563eb;
    }
</style>