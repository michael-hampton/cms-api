<div class="bundles-carousel-section" id="bundles-carousel-section" style="display: none;">
    <div class="section-header"
         style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h2 style="font-size: 1.75rem; font-weight: 700; color: #1e293b; margin-bottom: 0.5rem;">
                🎁 Bundle Deals
            </h2>
            <p style="color: #64748b; font-size: 0.875rem;">
                Save more when you buy together
            </p>
        </div>
        <div class="carousel-controls" style="display: flex; gap: 0.5rem;">
            <button onclick="scrollBundlesCarousel('left')" class="carousel-btn"
                    style="width: 40px; height: 40px; border-radius: 50%; border: 1px solid #e2e8f0; background: white; cursor: pointer;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>
            <button onclick="scrollBundlesCarousel('right')" class="carousel-btn"
                    style="width: 40px; height: 40px; border-radius: 50%; border: 1px solid #e2e8f0; background: white; cursor: pointer;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>
        </div>
    </div>

    <div class="bundles-carousel" id="bundles-carousel"
         style="display: flex; gap: 1.5rem; overflow-x: auto; scroll-behavior: smooth; padding-bottom: 1rem; -webkit-overflow-scrolling: touch; scrollbar-width: thin;">
        <!-- Bundles will be populated here -->
    </div>
</div>

<script>
    SITE = '<?= \App\Framework\Support\SiteContext::slug()?>'

    function scrollBundlesCarousel(direction) {
        const carousel = document.getElementById('bundles-carousel');
        const scrollAmount = 300;

        if (direction === 'left') {
            carousel.scrollLeft -= scrollAmount;
        } else {
            carousel.scrollLeft += scrollAmount;
        }
    }

    async function loadBundlesCarousel() {
        try {
            const response = await fetch(`/${SITE}/bundles/search?status=active`);
            const data = await response.json();

            if (!data.success || !data.bundles?.items || data.bundles?.items.length === 0) {
                document.getElementById('bundles-carousel-section').style.display = 'none';
                return;
            }

            const carousel = document.getElementById('bundles-carousel');
            carousel.innerHTML = data.bundles.items.map(bundle => createBundleCard(bundle)).join('');

            document.getElementById('bundles-carousel-section').style.display = 'block';
        } catch (error) {
            console.error('Error loading bundles:', error);
        }
    }

    function createBundleCard(bundle) {
        const isInStock = bundle.in_stock;
        const savings = bundle.savings;

        return `
        <div class="bundle-card" style="min-width: 300px; background: white; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; transition: transform 0.3s;">
            <div style="position: relative; padding-top: 75%; overflow: hidden; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                ${bundle.image ? `
                <img src="${bundle.image}"
                     alt="${bundle.name}"
                     style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0.3;">
                ` : ''}

                <div style="position: absolute; top: 1rem; left: 1rem; right: 1rem;">
                    <span style="background: #10b981; color: white; padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.75rem; font-weight: 600;">
                       Bundle
                    </span>

                    ${bundle.is_multi_merchant ? `
                    <span style="display: block; margin-top: 0.5rem; background: #3b82f6; color: white; padding: 0.25rem 0.75rem; border-radius: 1rem; font-size: 0.75rem; font-weight: 600; width: fit-content;">
                        ${bundle.merchants?.length} Merchants
                    </span>
                    ` : ''}
                </div>

                <div style="position: absolute; bottom: 1rem; left: 1rem; right: 1rem; color: white;">
                    <div style="font-size: 0.875rem; font-weight: 600; margin-bottom: 0.25rem;">
                        ${bundle.items?.length} items
                    </div>
                    <div style="font-size: 1.5rem; font-weight: 700;">
                        Save $${savings.toFixed(2)}
                    </div>
                </div>
            </div>

            <div style="padding: 1.25rem;">
                <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem; color: #1e293b;">
                    ${bundle.name}
                </h3>

                ${bundle.description ? `
                <p style="font-size: 0.875rem; color: #64748b; margin-bottom: 1rem; line-height: 1.5;">
                    ${bundle.description.substring(0, 80)}${bundle.description.length > 80 ? '...' : ''}
                </p>
                ` : ''}

                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                    <span style="font-size: 1.5rem; font-weight: 700; color: #10b981;">
                        $${parseFloat(bundle.bundle_price).toFixed(2)}
                    </span>
                    <span style="font-size: 1rem; color: #64748b; text-decoration: line-through;">
                        $${parseFloat(bundle.total_price).toFixed(2)}
                    </span>
                    <span style="background: #10b981; color: white; padding: 0.125rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600;">
                        -${bundle.discount_percentage}%
                    </span>
                </div>

                ${bundle.is_multi_merchant ? `
                <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 1rem; padding: 0.5rem; background: #f8fafc; border-radius: 0.375rem;">
                    <strong>Note:</strong> Items will ship from multiple merchants
                </div>
                ` : ''}

                <div style="display: flex; gap: 0.5rem;">
                    <button onclick="addBundleToCart(${bundle.bundle_id})"
                            ${!isInStock ? 'disabled' : ''}
                            style="flex: 1; padding: 0.75rem; background: ${isInStock ? '#10b981' : '#94a3b8'}; color: white; border: none; border-radius: 0.5rem; font-weight: 600; cursor: ${isInStock ? 'pointer' : 'not-allowed'};">
                        ${isInStock ? 'Add Bundle' : 'Unavailable'}
                    </button>

                    <button onclick="addBundleToWishlist(${bundle.bundle_id})"
                            ${!isInStock ? 'disabled' : ''}
                            style="padding: 0.75rem; background: white; color: #10b981; border: 2px solid #10b981; border-radius: 0.5rem; cursor: ${isInStock ? 'pointer' : 'not-allowed'};">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    `;
    }

    async function addBundleToCart(bundleId) {
        try {
            const response = await fetch(`/api/${SITE}/cart/bundle`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({bundle_id: bundleId})
            });

            const data = await response.json();

            if (data.success) {
                showToast('Bundle added to cart!');
                loadCartCount();
            } else {
                showToast(data.message || 'Failed to add bundle', 'error');
            }
        } catch (error) {
            console.error('Error adding bundle to cart:', error);
            showToast('Failed to add bundle to cart', 'error');
        }
    }

    async function addBundleToWishlist(bundleId) {
        try {
            const response = await fetch(`/api/${SITE}/wishlist/bundle`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({bundle_id: bundleId})
            });

            const data = await response.json();

            if (data.success) {
                showToast('Bundle added to wishlist!');
            } else {
                showToast(data.message || 'Failed to add to wishlist', 'error');
            }
        } catch (error) {
            console.error('Error adding bundle to wishlist:', error);
            showToast('Failed to add to wishlist', 'error');
        }
    }

    // Load on page load
    if (typeof SITE !== 'undefined') {
        loadBundlesCarousel();
    }
</script>

<style>
    .bundles-carousel::-webkit-scrollbar {
        height: 8px;
    }

    .bundles-carousel::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 4px;
    }

    .bundles-carousel::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }

    .bundles-carousel::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    .bundle-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    }
</style>