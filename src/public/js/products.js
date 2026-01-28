(function() {
    'use strict';

    const comparisonState = {
        products: new Set(),
        maxProducts: 4
    };

    // State management
    const state = {
        currentPage: 1,
        perPage: 12,
        sortBy: 'created_at',
        sortOrder: 'desc',
        filters: {
            search: '',
            categoryIds: [],
            brandIds: [],
            specificationIds: [],
            minPrice: '',
            maxPrice: '',
            onSale: false
        },
        cartCount: 0,
        wishlistCount: 0,
        debounceTimer: null
    };

    // DOM Elements
    const elements = {
        searchInput: document.getElementById('search-input'),
        searchBtn: document.getElementById('search-btn'),
        minPriceInput: document.getElementById('min-price'),
        maxPriceInput: document.getElementById('max-price'),
        onSaleFilter: document.getElementById('on-sale-filter'),
        applyFiltersBtn: document.getElementById('apply-filters'),
        resetFiltersBtn: document.getElementById('reset-filters'),
        sortSelect: document.getElementById('sort-select'),
        perPageSelect: document.getElementById('per-page-select'),
        productsGrid: document.getElementById('products-grid'),
        loadingState: document.getElementById('loading-state'),
        emptyState: document.getElementById('empty-state'),
        pagination: document.getElementById('pagination'),
        resultsCount: document.getElementById('results-count'),
        cartCount: document.getElementById('cart-count'),
        wishlistCount: document.getElementById('wishlist-count'),
        toast: document.getElementById('toast')
    };

    // Initialize
    function init() {
        loadFromURL();
        attachEventListeners();
        updateCounts();
    }

    // Add comparison checkbox to product cards
    function attachComparisonHandlers() {
        document.querySelectorAll('.btn-compare').forEach(btn => {
            btn.addEventListener('click', handleCompareToggle);
        });
    }

    function handleCompareToggle(e) {
        const btn = e.currentTarget;
        const productId = parseInt(btn.dataset.productId);

        if (comparisonState.products.has(productId)) {
            comparisonState.products.delete(productId);
            btn.classList.remove('active');
        } else {
            if (comparisonState.products.size >= comparisonState.maxProducts) {
                showToast('Maximum 4 products can be compared', 'error');
                return;
            }
            comparisonState.products.add(productId);
            btn.classList.add('active');
        }

        updateComparisonBar();
    }

    function renderStars(rating) {
        const fullStars = Math.floor(rating);
        const hasHalfStar = rating % 1 >= 0.5;
        let html = '<div class="rating-stars">';

        for (let i = 0; i < 5; i++) {
            if (i < fullStars) {
                html += `<svg class="rating-star filled" viewBox="0 0 24 24">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
            </svg>`;
            } else {
                html += `<svg class="rating-star empty" viewBox="0 0 24 24">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
            </svg>`;
            }
        }

        html += '</div>';
        return html;
    }

    // Helper function to render merchants
    function renderMerchants(merchants) {
        if (!merchants || merchants.length === 0) return '';

        if (merchants.length === 1) {
            const merchant = merchants[0];
            const merchantName = merchant.merchant?.name || merchant.name || 'Unknown';
            const merchantUrl = merchant.url || merchant.merchant?.url || '#';

            return `<div class="product-merchants">
            <a href="${escapeHtml(merchantUrl)}" 
               target="_blank" 
               rel="noopener noreferrer"
               class="merchant-badge ${merchant.is_best_price ? 'best-price' : ''}"
               onclick="event.stopPropagation()">
                ${escapeHtml(merchantName)}
                ${merchant.discount_percentage > 0 ? ` -${merchant.discount_percentage}%` : ''}
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left: 4px;">
                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                    <polyline points="15 3 21 3 21 9"></polyline>
                    <line x1="10" y1="14" x2="21" y2="3"></line>
                </svg>
            </a>
        </div>`;
        }

        // Multiple merchants - find best price
        const bestMerchant = merchants.reduce((best, current) => {
            const bestPrice = best.sale_price > 0 ? best.sale_price : best.price;
            const currentPrice = current.sale_price > 0 ? current.sale_price : current.price;
            return currentPrice < bestPrice ? current : best;
        }, merchants[0]);

        const merchantName = bestMerchant.merchant?.name || bestMerchant.name || 'Unknown';
        const merchantUrl = bestMerchant.url || bestMerchant.merchant?.url || '#';

        return `<div class="product-merchants">
        <a href="${escapeHtml(merchantUrl)}" 
           target="_blank" 
           rel="noopener noreferrer"
           class="merchant-badge best-price"
           onclick="event.stopPropagation()">
            ${escapeHtml(merchantName)}
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left: 4px;">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                <polyline points="15 3 21 3 21 9"></polyline>
                <line x1="10" y1="14" x2="21" y2="3"></line>
            </svg>
        </a>
        ${merchants.length > 1 ? `<span class="merchant-count">+${merchants.length - 1} more</span>` : ''}
    </div>`;
    }

    function renderTopReview(review) {
        if (!review) return '';

        console.log('review', review)

        return `
        <button class="btn-show-review" onclick="toggleReview(this)">
            <span>Top Review</span>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
        </button>
        <div class="top-review-section">
            <div class="review-header">
                ${renderStars(review.rating)}
                <span class="review-helpful">${review.helpful_count} helpful</span>
            </div>
            ${review.title ? `<div class="review-title">${escapeHtml(review.title)}</div>` : ''}
            <div class="review-comment">${escapeHtml(review.comment)}</div>
            <div class="review-author">by ${escapeHtml(review.author_name)}</div>
        </div>
    `;
    }

    // Toggle review visibility
    function toggleReview(btn) {
        const section = btn.nextElementSibling;
        const isExpanded = section.classList.contains('show');

        section.classList.toggle('show');
        btn.classList.toggle('expanded');

        const text = btn.querySelector('span');
        text.textContent = isExpanded ? 'Top Review' : 'Hide Review';
    }

    window.toggleReview = toggleReview;

    function updateComparisonBar() {
        const count = comparisonState.products.size;
        const bar = document.getElementById('comparison-bar');

        if (!bar) return;

        if (count >= 2) {
            bar.style.display = 'flex';
            bar.querySelector('.comparison-count').textContent = `${count} products selected`;
        } else {
            bar.style.display = 'none';
        }
    }

    function compareProducts() {
        if (comparisonState.products.size < 2) {
            showToast('Select at least 2 products to compare', 'error');
            return;
        }

        const ids = Array.from(comparisonState.products).join(',');
        window.location.href = `/${SITE}/compare?ids=${ids}`;
    }

    window.compareProducts = compareProducts;

    // Load state from URL
    function loadFromURL() {
        const params = new URLSearchParams(window.location.search);

        state.currentPage = parseInt(params.get('page')) || 1;
        state.perPage = parseInt(params.get('per_page')) || 12;
        state.sortBy = params.get('sort_by') || 'created_at';
        state.sortOrder = params.get('sort_order') || 'desc';
        state.filters.search = params.get('q') || '';
        state.filters.categoryIds = params.get('category_ids') ? params.get('category_ids').split(',') : [];
        state.filters.brandIds = params.get('brand_ids') ? params.get('brand_ids').split(',') : [];
        state.filters.specificationIds = params.get('spec_ids') ? params.get('spec_ids').split(',') : [];
        state.filters.minPrice = params.get('min_price') || '';
        state.filters.maxPrice = params.get('max_price') || '';
        state.filters.onSale = params.get('on_sale') === '1';

        // Update UI to match state
        elements.searchInput.value = state.filters.search;
        elements.minPriceInput.value = state.filters.minPrice;
        elements.maxPriceInput.value = state.filters.maxPrice;
        elements.onSaleFilter.checked = state.filters.onSale;
        elements.sortSelect.value = `${state.sortBy}:${state.sortOrder}`;
        elements.perPageSelect.value = state.perPage.toString();

        // Check appropriate checkboxes
        state.filters.categoryIds.forEach(id => {
            const checkbox = document.querySelector(`input[name="category[]"][value="${id}"]`);
            if (checkbox) checkbox.checked = true;
        });

        state.filters.brandIds.forEach(id => {
            const checkbox = document.querySelector(`input[name="brand[]"][value="${id}"]`);
            if (checkbox) checkbox.checked = true;
        });

        state.filters.specificationIds.forEach(value => {
            const checkbox = document.querySelector(`input[name^="spec_"][value="${value}"]`);
            if (checkbox) checkbox.checked = true;
        });

        loadProducts();
    }

    // Update URL with current state
    function updateURL() {
        const params = new URLSearchParams();

        if (state.currentPage > 1) params.set('page', state.currentPage);
        if (state.perPage !== 12) params.set('per_page', state.perPage);
        if (state.sortBy !== 'created_at' || state.sortOrder !== 'desc') {
            params.set('sort_by', state.sortBy);
            params.set('sort_order', state.sortOrder);
        }
        if (state.filters.search) params.set('q', state.filters.search);
        if (state.filters.categoryIds.length) params.set('category_ids', state.filters.categoryIds.join(','));
        if (state.filters.brandIds.length) params.set('brand_ids', state.filters.brandIds.join(','));
        if (state.filters.specificationIds.length) params.set('spec_ids', state.filters.specificationIds.join(','));
        if (state.filters.minPrice) params.set('min_price', state.filters.minPrice);
        if (state.filters.maxPrice) params.set('max_price', state.filters.maxPrice);
        if (state.filters.onSale) params.set('on_sale', '1');

        const newURL = `${window.location.pathname}${params.toString() ? '?' + params.toString() : ''}`;
        window.history.pushState({}, '', newURL);
    }

    // Debounced filter update
    function debouncedFilterUpdate() {
        clearTimeout(state.debounceTimer);
        state.debounceTimer = setTimeout(() => {
            updateFiltersAndLoad();
        }, 300);
    }


    // Event Listeners
    function attachEventListeners() {
        // Search
        elements.searchBtn.addEventListener('click', handleSearch);
        elements.searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') handleSearch();
        });
        elements.searchInput.addEventListener('input', debouncedFilterUpdate);

        // Auto-apply filters on change
        document.querySelectorAll('input[name="category[]"]').forEach(cb => {
            cb.addEventListener('change', debouncedFilterUpdate);
        });

        document.querySelectorAll('input[name="brand[]"]').forEach(cb => {
            cb.addEventListener('change', debouncedFilterUpdate);
        });

        document.querySelectorAll('input[name^="spec_"]').forEach(cb => {
            cb.addEventListener('change', debouncedFilterUpdate);
        });

        elements.minPriceInput.addEventListener('input', debouncedFilterUpdate);
        elements.maxPriceInput.addEventListener('input', debouncedFilterUpdate);
        elements.onSaleFilter.addEventListener('change', debouncedFilterUpdate);

        // Keep apply/reset buttons for manual control
        elements.applyFiltersBtn.addEventListener('click', updateFiltersAndLoad);
        elements.resetFiltersBtn.addEventListener('click', resetFilters);

        // Sorting and pagination
        elements.sortSelect.addEventListener('change', handleSortChange);
        elements.perPageSelect.addEventListener('change', handlePerPageChange);

        // Browser back/forward
        window.addEventListener('popstate', loadFromURL);

        // Product card click to open modal
        elements.productsGrid.addEventListener('click', (e) => {
            e.preventDefault();
            const productCard = e.target.closest('.product-card');
            alert('here')

            // Don't open modal if clicking action buttons
            if (e.target.closest('.btn-compare, .btn-flip, .btn-wishlist, .btn-cart, .btn-show-review, .product-card-back')) {
                return;
            }

            if (productCard) {
                const productId = productCard.dataset.productId;
                if (window.productModal) {
                    window.productModal.open(productId);
                }
            }
        });
    }

    // Handle search
    function handleSearch() {
        state.filters.search = elements.searchInput.value.trim();
        state.currentPage = 1;
        updateFiltersAndLoad();
    }

    // Update filters and load
    function updateFiltersAndLoad() {
        // Get all checked category checkboxes
        state.filters.categoryIds = Array.from(
            document.querySelectorAll('input[name="category[]"]:checked')
        ).map(cb => cb.value);

        // Get all checked brand checkboxes
        state.filters.brandIds = Array.from(
            document.querySelectorAll('input[name="brand[]"]:checked')
        ).map(cb => cb.value);

        // Get specification filters
        const specCheckboxes = document.querySelectorAll('[name^="spec_"]:checked');
        state.filters.specificationIds = Array.from(specCheckboxes).map(cb => cb.value);

        state.filters.search = elements.searchInput.value.trim();
        state.filters.minPrice = elements.minPriceInput.value;
        state.filters.maxPrice = elements.maxPriceInput.value;
        state.filters.onSale = elements.onSaleFilter.checked;
        state.currentPage = 1;

        updateURL();
        loadProducts();
    }

    // Apply filters
    function applyFilters() {
        // Get all checked category checkboxes
        state.filters.categoryIds = Array.from(
            document.querySelectorAll('input[name="category[]"]:checked')
        ).map(cb => cb.value);

        // Get all checked brand checkboxes
        state.filters.brandIds = Array.from(
            document.querySelectorAll('input[name="brand[]"]:checked')
        ).map(cb => cb.value);

        const specCheckboxes = document.querySelectorAll('[name^="spec_"]');

        state.filters.specificationIds = Array.from(specCheckboxes)
            .filter(cb => cb.checked)
            .map(cb => cb.value);

        state.filters.minPrice = elements.minPriceInput.value;
        state.filters.maxPrice = elements.maxPriceInput.value;
        state.filters.onSale = elements.onSaleFilter.checked;
        state.currentPage = 1;
        loadProducts();
    }

    // Reset filters
    function resetFilters() {
        state.filters = {
            search: '',
            categoryIds: [],
            brandIds: [],
            specificationIds: [],
            minPrice: '',
            maxPrice: '',
            onSale: false
        };
        state.currentPage = 1;

        elements.searchInput.value = '';
        document.querySelectorAll('input[name="category[]"]').forEach(cb => cb.checked = false);
        document.querySelectorAll('input[name="brand[]"]').forEach(cb => cb.checked = false);
        document.querySelectorAll('input[name^="spec_"]').forEach(cb => cb.checked = false);
        elements.minPriceInput.value = '';
        elements.maxPriceInput.value = '';
        elements.onSaleFilter.checked = false;

        updateURL();
        loadProducts();
    }

    // Handle sort change
    function handleSortChange() {
        const [sortBy, sortOrder] = elements.sortSelect.value.split(':');
        state.sortBy = sortBy;
        state.sortOrder = sortOrder;
        state.currentPage = 1;
        updateURL();
        loadProducts();
    }

    // Handle per page change
    function handlePerPageChange() {
        state.perPage = parseInt(elements.perPageSelect.value);
        state.currentPage = 1;
        updateURL();
        loadProducts();
    }

    // Load products
    async function loadProducts() {
        showLoading();

        const params = new URLSearchParams({
            page: state.currentPage,
            per_page: state.perPage,
            sort_by: state.sortBy,
            sort_order: state.sortOrder,
            q: state.filters.search,
            category_ids: state.filters.categoryIds.join(','),
            brand_ids: state.filters.brandIds.join(','),
            spec_ids: state.filters.specificationIds.join(','),
            min_price: state.filters.minPrice,
            max_price: state.filters.maxPrice,
            on_sale: state.filters.onSale ? '1' : ''
        });

        try {
            const response = await fetch(`/api/${SITE}/product-list/search?${params}`);
            const data = await response.json();

            if (response.ok) {
                renderProducts(data.data);
                renderPagination(data.pagination);
                updateResultsCount(data.pagination.total);
            } else {
                showError('Failed to load products');
            }
        } catch (error) {
            console.error('Error loading products:', error);
            showError('An error occurred while loading products');
        } finally {
            hideLoading();
        }
    }

    // Render products
    function renderProducts(products) {
        hideEmptyState();

        if (!products || products.length === 0) {
            showEmptyState();
            return;
        }

        // In renderProducts function, update the product card HTML:

        elements.productsGrid.innerHTML = products.map(product => `
    <div class="product-card" data-product-id="${product.id}">
        <div class="product-card-inner">
            <div class="product-card-front">
                <button class="btn-flip" data-product-id="${product.id}" title="View details">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/>
                    </svg>
                </button>
                
                <button class="btn-share" onclick='openShareModal(${JSON.stringify({
            id: product.id,
            name: product.name,
            slug: product.slug,
            price: product.price,
            sale_price: product.sale_price,
            image: product.image,
            merchant_name: product.merchants && product.merchants.length > 0 ? product.merchants[0].name : null
        })})' title="Share product">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="18" cy="5" r="3"></circle>
                        <circle cx="6" cy="12" r="3"></circle>
                        <circle cx="18" cy="19" r="3"></circle>
                        <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                        <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                    </svg>
                </button>
                
                <a href="/${SITE}/shop/details/${product.slug}" class="product-image">
                    <img src="${product.image || '/images/placeholder.jpg'}" 
                         alt="${escapeHtml(product.title || product.name)}">
                    ${product.discount_percentage > 0 ? `
                        <span class="badge-sale">-${product.discount_percentage}%</span>
                    ` : ''}
                </a>
                
                <div class="product-content">
                    <h3 class="product-name">
                        <a href="/${SITE}/shop/details/${product.slug}">${escapeHtml(product.title || product.name)}</a>
                    </h3>
                    
                    ${product.average_rating > 0 ? `
                        <div class="product-rating">
                            <div class="stars-small">
                                ${renderStars(product.average_rating)}
                            </div>
                            <span class="rating-count">(${product.review_count || 0})</span>
                        </div>
                    ` : ''}
                    ${renderMerchants(product.availableMerchants || [])}
                        <div class="product-price">
                            ${product.sale_price && product.sale_price < product.price ? `
                                <span class="price-sale">$${formatPrice(product.sale_price)}</span>
                                <span class="price-original">$${formatPrice(product.price)}</span>
                            ` : `
                                <span class="price-current">$${formatPrice(product.price)}</span>
                            `}
                        </div>
                        <div class="product-actions">
                         <button class="btn-compare" data-product-id="${product.id}" title="Add to comparison">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M9 11l3 3L22 4"></path>
                                <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"></path>
                            </svg>
                        </button>
                            <button class="btn-add-to-cart" data-product-id="${product.id}">
                                Add to Cart
                            </button>
                            <button class="btn-wishlist" data-product-id="${product.id}">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    ${product.top_review ? renderTopReview(product.top_review) : ''}
                </div>
                
                <div class="product-card-back">
                    <div class="card-back-header">
                        <h3 class="card-back-title">${escapeHtml(product.name)}</h3>
                        <button class="btn-flip-back" data-product-id="${product.id}" title="Flip back">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                    
                    <div class="card-back-content">
                        <div class="card-back-dynamic-content"></div>
                    </div>
                    
                    <div class="card-back-actions">
                        <button class="btn-compare" data-product-id="${product.id}" title="Add to comparison">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M9 11l3 3L22 4"></path>
                                <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"></path>
                            </svg>
                        </button>
                        <button class="btn-back-action btn-add-cart-back" data-product-id="${product.id}">
                            Add to Cart
                        </button>
                        <a href="/${SITE}/shop/details/${product.slug}" class="btn-back-action btn-view-details">
                            Full Details
                        </a>
                    </div>
                </div>
            </div>
        </div>
    `).join('');

        attachProductEventListeners();
        attachFlipEventListeners();
        attachComparisonHandlers();
    }

    // Attach event listeners to product buttons
    function attachProductEventListeners() {
        // Add to cart buttons
        document.querySelectorAll('.btn-add-to-cart').forEach(btn => {
            btn.addEventListener('click', handleAddToCart);
        });

        // Wishlist buttons
        /*document.querySelectorAll('.btn-wishlist').forEach(btn => {
            btn.addEventListener('click', handleToggleWishlist);
        });*/
    }

    // Attach flip card event listeners
    function attachFlipEventListeners() {
        // Flip to back buttons
        document.querySelectorAll('.btn-flip').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const productId = btn.dataset.productId;
                const card = btn.closest('.product-card');
                flipCard(productId, card);
            });
        });

        // Flip to front buttons
        document.querySelectorAll('.btn-flip-back').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const card = btn.closest('.product-card');
                flipBackCard(card);
            });
        });

        // Add to cart from back
        document.querySelectorAll('.btn-add-cart-back').forEach(btn => {
            btn.addEventListener('click', handleAddToCart);
        });
    }

    // Handle add to cart
    async function handleAddToCart(e) {
        const btn = e.currentTarget;
        const productId = btn.dataset.productId;

        btn.disabled = true;
        btn.textContent = 'Adding...';

        try {
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
                showToast(data.message, 'success');
                state.cartCount = data.count;
                updateCartCount();
            } else {
                showToast(data.message, 'error');
            }
        } catch (error) {
            console.error('Error adding to cart:', error);
            showToast('Failed to add item to cart', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Add to Cart';
        }
    }

    // Handle toggle wishlist
    async function handleToggleWishlist(e) {
        const btn = e.currentTarget;
        const productId = btn.dataset.productId;
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
                body: isInWishlist ? null : JSON.stringify({ product_id: productId })
            });

            const data = await response.json();

            if (data.success) {
                btn.classList.toggle('active');
                showToast(data.message, 'success');
                state.wishlistCount = data.count;
                updateWishlistCount();
            } else {
                showToast(data.message, 'error');
            }
        } catch (error) {
            console.error('Error toggling wishlist:', error);
            showToast('An error occurred', 'error');
        } finally {
            btn.disabled = false;
        }
    }

    // Render pagination
    function renderPagination(pagination) {
        if (!pagination || pagination.last_page <= 1) {
            elements.pagination.innerHTML = '';
            return;
        }

        const { current_page, last_page } = pagination;
        let html = '';

        html += `
            <button ${current_page === 1 ? 'disabled' : ''} 
                    data-page="${current_page - 1}">
                Previous
            </button>
        `;

        const startPage = Math.max(1, current_page - 2);
        const endPage = Math.min(last_page, current_page + 2);

        if (startPage > 1) {
            html += `<button data-page="1">1</button>`;
            if (startPage > 2) html += `<span>...</span>`;
        }

        for (let i = startPage; i <= endPage; i++) {
            html += `
                <button class="${i === current_page ? 'active' : ''}" 
                        data-page="${i}">
                    ${i}
                </button>
            `;
        }

        if (endPage < last_page) {
            if (endPage < last_page - 1) html += `<span>...</span>`;
            html += `<button data-page="${last_page}">${last_page}</button>`;
        }

        html += `
            <button ${current_page === last_page ? 'disabled' : ''} 
                    data-page="${current_page + 1}">
                Next
            </button>
        `;

        elements.pagination.innerHTML = html;

        elements.pagination.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', () => {
                state.currentPage = parseInt(btn.dataset.page);
                updateURL();
                loadProducts();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });
    }

    // Update results count
    function updateResultsCount(total) {
        elements.resultsCount.textContent = `${total} product${total !== 1 ? 's' : ''}`;
    }

    // Update counts
    async function updateCounts() {
        try {
            const cartResponse = await fetch(`/api/${SITE}/cart`);
            const cartData = await cartResponse.json();
            state.cartCount = cartData.count || 0;
            updateCartCount();

            const wishlistResponse = await fetch(`/api/${SITE}/wishlist`);
            const wishlistData = await wishlistResponse.json();
            state.wishlistCount = wishlistData.count || 0;
            updateWishlistCount();
        } catch (error) {
            console.error('Error updating counts:', error);
        }
    }

    // Update cart count display
    function updateCartCount() {
        if (elements.cartCount) {
            elements.cartCount.textContent = state.cartCount;
            elements.cartCount.style.display = state.cartCount > 0 ? 'block' : 'none';
        }
    }

    // Update wishlist count display
    function updateWishlistCount() {
        if (elements.wishlistCount) {
            elements.wishlistCount.textContent = state.wishlistCount;
            elements.wishlistCount.style.display = state.wishlistCount > 0 ? 'block' : 'none';
        }
    }

    // Show loading state
    function showLoading() {
        elements.loadingState.style.display = 'block';
        elements.productsGrid.style.display = 'none';
        elements.emptyState.style.display = 'none';
    }

    // Hide loading state
    function hideLoading() {
        elements.loadingState.style.display = 'none';
        elements.productsGrid.style.display = 'grid';
    }

    // Show empty state
    function showEmptyState() {
        elements.productsGrid.style.display = 'none';
        elements.productsGrid.innerHTML = ''; // Clear the grid
        elements.emptyState.style.display = 'block';
        elements.pagination.innerHTML = '';
    }

    // Hide empty state
    function hideEmptyState() {
        elements.emptyState.style.display = 'none';
    }

    // Show toast notification
    function showToast(message, type = 'info') {
        elements.toast.textContent = message;
        elements.toast.className = `toast ${type} show`;

        setTimeout(() => {
            elements.toast.classList.remove('show');
        }, 3000);
    }

    // Show error
    function showError(message) {
        showToast(message, 'error');
        showEmptyState();
    }

    // Utility functions
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatPrice(price) {
        return parseFloat(price).toFixed(2);
    }

    // Show more/less functionality
    function expandFilterList(filterType) {
        const dataElement = document.getElementById(`all-${filterType === 'category' ? 'categories' : 'brands'}`);
        const allItems = JSON.parse(dataElement.textContent);
        const listElement = document.getElementById(`${filterType}-list`);

        listElement.innerHTML = allItems.map(item => `
            <label class="filter-checkbox-label">
                <input type="checkbox" class="filter-checkbox" name="${filterType}[]" value="${item.id}">
                <span class="filter-name">${escapeHtml(item.name)}</span>
                <span class="filter-count">${item.product_count || 0}</span>
            </label>
        `).join('');

        // Re-attach event listeners
        listElement.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            cb.addEventListener('change', debouncedFilterUpdate);
        });
    }

    function collapsFilterList(filterType) {
        const dataElement = document.getElementById(`all-${filterType === 'category' ? 'categories' : 'brands'}`);
        const allItems = JSON.parse(dataElement.textContent);
        const listElement = document.getElementById(`${filterType}-list`);

        listElement.innerHTML = allItems.slice(0, 5).map(item => `
            <label class="filter-checkbox-label">
                <input type="checkbox" class="filter-checkbox" name="${filterType}[]" value="${item.id}">
                <span class="filter-name">${escapeHtml(item.name)}</span>
                <span class="filter-count">${item.product_count || 0}</span>
            </label>
        `).join('');

        // Re-attach event listeners
        listElement.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            cb.addEventListener('change', debouncedFilterUpdate);
        });
    }

    // Show more/less button click handler
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('show-more-btn')) {
            const btn = e.target;
            const filterType = btn.dataset.filter;
            const isExpanded = btn.classList.contains('expanded');

            if (isExpanded) {
                collapsFilterList(filterType);
                btn.classList.remove('expanded');
                btn.textContent = 'Show More';
            } else {
                expandFilterList(filterType);
                btn.classList.add('expanded');
                btn.textContent = 'Show Less';
            }
        }
    });

    // Flip card functionality
    let currentlyFlippedCard = null;

    function flipCard(productId, cardElement) {
        // If clicking the same card, just toggle it
        if (currentlyFlippedCard === cardElement) {
            cardElement.classList.remove('flipped');
            currentlyFlippedCard = null;
            document.body.classList.remove('card-flipped');
            return;
        }

        // If another card is flipped, flip it back first
        if (currentlyFlippedCard) {
            currentlyFlippedCard.classList.remove('flipped');
        }

        // Flip the new card
        cardElement.classList.add('flipped');
        currentlyFlippedCard = cardElement;
        document.body.classList.add('card-flipped');

        // Load detailed data for the back of the card if not already loaded
        if (!cardElement.dataset.backLoaded) {
            loadCardBackData(productId, cardElement);
            cardElement.dataset.backLoaded = 'true';
        }
    }

    function flipBackCard(cardElement) {
        cardElement.classList.remove('flipped');
        if (currentlyFlippedCard === cardElement) {
            currentlyFlippedCard = null;
        }
        document.body.classList.remove('card-flipped');
    }

// Load detailed product data for card back
    async function loadCardBackData(productId, cardElement) {
        const backContent = cardElement.querySelector('.card-back-dynamic-content');
        if (!backContent) return;

        try {
            // Show loading state
            backContent.innerHTML = '<div style="text-align: center; padding: 2rem; color: #64748b;">Loading details...</div>';

            const response = await fetch(`/api/${SITE}/product-list/${productId}/details`);
            const data = await response.json();

            if (response.ok && data.success) {
                renderCardBackContent(data.product, backContent);
            } else {
                backContent.innerHTML = '<div style="text-align: center; padding: 2rem; color: #ef4444;">Failed to load details</div>';
            }
        } catch (error) {
            console.error('Error loading card back data:', error);
            backContent.innerHTML = '<div style="text-align: center; padding: 2rem; color: #ef4444;">An error occurred</div>';
        }
    }

    function renderCardBackContent(product, container) {
        const {
            description,
            stock_quantity,
            variants,
            price_history,
            comparison,
            specifications,
            merchants,
            lowest_merchant_price,
            price,
            sale_price
        } = product;

        let html = '';

        // Description
        if (description) {
            const shortDesc = description.length > 150
                ? description.substring(0, 150) + '...'
                : description;
            html += `
            <div class="back-section">
                <h4 class="back-section-title">Description</h4>
                <p class="product-description">${escapeHtml(shortDesc)}</p>
            </div>
        `;
        }

        // Stock Status
        const stockStatus = getStockStatus(stock_quantity);
        html += `
        <div class="back-section">
            <h4 class="back-section-title">Availability</h4>
            <div class="stock-indicator ${stockStatus.class}">
                <span class="stock-dot"></span>
                <span>${stockStatus.text}</span>
            </div>
        </div>
    `;

        // Variants (if available)
        if (variants && variants.length > 0) {
            html += `
            <div class="back-section">
                <h4 class="back-section-title">Available Options</h4>
                <div class="variants-grid">
                    ${variants.map(variant => `
                        <div class="variant-option ${variant.in_stock ? '' : 'disabled'}" 
                             data-variant-id="${variant.id}"
                             data-variant-price="${variant.final_price}">
                            <div style="font-weight: 500;">${escapeHtml(variant.name)}</div>
                            ${variant.discount_percentage > 0 ? `
                                <div style="font-size: 0.75rem; color: #059669;">-${variant.discount_percentage}%</div>
                            ` : ''}
                            <div style="font-size: 0.75rem; color: #64748b;">$${formatPrice(variant.final_price)}</div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
        }

        // Price History (90 days)
        if (price_history && price_history.length > 0) {
            const prices = price_history.map(p => p.price);
            const currentPrice = prices[prices.length - 1];
            const lowestPrice = Math.min(...prices);
            const highestPrice = Math.max(...prices);

            // Calculate savings if currently at lowest
            const savingsPercent = currentPrice === lowestPrice && highestPrice > lowestPrice
                ? Math.round(((highestPrice - lowestPrice) / highestPrice) * 100)
                : 0;

            html += `
            <div class="back-section">
                <h4 class="back-section-title">Price History (90 Days)</h4>
                <div class="price-chart-container">
                    <div class="price-stats">
                        <div class="price-stat">
                            <div class="price-stat-label">Current</div>
                            <div class="price-stat-value current">$${formatPrice(currentPrice)}</div>
                        </div>
                        <div class="price-stat">
                            <div class="price-stat-label">Lowest</div>
                            <div class="price-stat-value low">$${formatPrice(lowestPrice)}</div>
                        </div>
                        <div class="price-stat">
                            <div class="price-stat-label">Highest</div>
                            <div class="price-stat-value high">$${formatPrice(highestPrice)}</div>
                        </div>
                    </div>
                    ${savingsPercent > 0 ? `
                        <div style="text-align: center; margin-bottom: 0.5rem; color: #059669; font-size: 0.875rem; font-weight: 500;">
                            💰 Save ${savingsPercent}% vs highest price!
                        </div>
                    ` : ''}
                    <div class="price-chart">
                        <svg class="price-chart-line" viewBox="0 0 100 40" preserveAspectRatio="none">
                            ${generatePriceChartSVG(price_history)}
                        </svg>
                    </div>
                </div>
            </div>
        `;
        }

        // Specifications
        if (specifications && specifications.length > 0) {
            html += `
            <div class="back-section">
                <h4 class="back-section-title">Specifications</h4>
                <div class="comparison-section">
                    ${specifications.map(spec => `
                        <div class="comparison-item">
                            <span class="comparison-label">${escapeHtml(spec.key)}</span>
                            <span class="comparison-value">${escapeHtml(spec.value)}</span>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
        }

        // Comparison with category
        if (comparison) {
            html += `
            <div class="back-section">
                <h4 class="back-section-title">Price Comparison</h4>
                <div class="comparison-section">
                    <div class="comparison-item">
                        <span class="comparison-label">vs. Category Average</span>
                        <span class="comparison-badge ${comparison.price_comparison}">
                            ${comparison.price_difference}
                        </span>
                    </div>
                    ${comparison.category_avg_price ? `
                        <div class="comparison-item">
                            <span class="comparison-label">Category Average</span>
                            <span class="comparison-value">$${comparison.category_avg_price}</span>
                        </div>
                    ` : ''}
                    ${comparison.discount_vs_regular ? `
                        <div class="comparison-item">
                            <span class="comparison-label">Your Savings</span>
                            <span class="comparison-badge better">
                                ${comparison.discount_vs_regular}
                            </span>
                        </div>
                    ` : ''}
                    ${comparison.products_in_category ? `
                        <div class="comparison-item">
                            <span class="comparison-label">Similar Products</span>
                            <span class="comparison-value">${comparison.products_in_category} in category</span>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
        }

        // Merchant availability
        if (merchants && merchants.length > 1) {
            html += `
            <div class="back-section">
                <h4 class="back-section-title">Available From</h4>
                <div class="comparison-section">
                    ${merchants.slice(0, 3).map(merchant => `
                        <div class="comparison-item">
                            <span class="comparison-label">
                                <a href="${merchant.url}" target="_blank" style="color: #2563eb; text-decoration: none;">
                                    Merchant
                                </a>
                            </span>
                            <span class="comparison-value">
                                $${formatPrice(merchant.sale_price > 0 ? merchant.sale_price : merchant.price)}
                                ${merchant.has_discount ? `
                                    <span style="color: #059669; font-size: 0.75rem; margin-left: 0.25rem;">
                                        -${merchant.discount_percentage}%
                                    </span>
                                ` : ''}
                            </span>
                        </div>
                    `).join('')}
                    ${merchants.length > 3 ? `
                        <div style="text-align: center; margin-top: 0.5rem; font-size: 0.875rem; color: #64748b;">
                            +${merchants.length - 3} more retailers
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
        }

        container.innerHTML = html;

        // Attach variant selection handlers
        if (variants && variants.length > 0) {
            attachVariantHandlers(container, product.id);
        }
    }

// Get stock status
    function getStockStatus(quantity) {
        if (quantity === 0) {
            return {class: 'out-of-stock', text: 'Out of Stock'};
        } else if (quantity < 10) {
            return {class: 'low-stock', text: `Only ${quantity} left in stock`};
        } else {
            return {class: 'in-stock', text: 'In Stock'};
        }
    }

// Generate SVG path for price chart
    function generatePriceChartSVG(priceHistory) {
        if (!priceHistory || priceHistory.length < 2) return '';

        const prices = priceHistory.map(p => p.price);
        const minPrice = Math.min(...prices);
        const maxPrice = Math.max(...prices);
        const priceRange = maxPrice - minPrice || 1;

        const points = priceHistory.map((item, index) => {
            const x = (index / (priceHistory.length - 1)) * 100;
            const y = 40 - ((item.price - minPrice) / priceRange) * 35;
            return `${x},${y}`;
        }).join(' ');

        return `
        <polyline 
            points="${points}" 
            fill="none" 
            stroke="#2563eb" 
            stroke-width="2" 
            stroke-linecap="round" 
            stroke-linejoin="round"
        />
    `;
    }

// Attach variant selection handlers
    function attachVariantHandlers(container, productId) {
        container.querySelectorAll('.variant-option:not(.disabled)').forEach(option => {
            option.addEventListener('click', function () {
                // Remove selected from all
                container.querySelectorAll('.variant-option').forEach(o => o.classList.remove('selected'));
                // Add selected to clicked
                this.classList.add('selected');
                // Store selected variant
                const variantId = this.dataset.variantId;
                console.log(`Selected variant ${variantId} for product ${productId}`);
            });
        });
    }

// Escape key to flip back
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && currentlyFlippedCard) {
            flipBackCard(currentlyFlippedCard);
        }
    });

    // Start the app
    init();
})();

function toggleSection(sectionName) {
    const section = document.querySelector(`[data-section="${sectionName}"]`);
    const content = section.querySelector('.section-content');
    const chevron = section.querySelector('.chevron');

    content.classList.toggle('open');
    chevron.classList.toggle('rotated');

    // Save state
    localStorage.setItem(`sidebar-${sectionName}`, content.classList.contains('open'));
}

// Restore saved states
document.addEventListener('DOMContentLoaded', () => {
    ['search', 'categories', 'brands', 'price', 'sale'].forEach(section => {
        const isOpen = localStorage.getItem(`sidebar-${section}`) !== 'false';
        if (!isOpen) {
            toggleSection(section);
        }
    });
});