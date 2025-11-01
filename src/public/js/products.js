(function() {
    'use strict';

    // State management
    const state = {
        currentPage: 1,
        perPage: 12,
        sortBy: 'created_at',
        sortOrder: 'desc',
        filters: {
            search: '',
            categoryId: '',
            brandId: '',
            minPrice: '',
            maxPrice: '',
            onSale: false
        },
        cartCount: 0,
        wishlistCount: 0
    };

    // DOM Elements
    const elements = {
        searchInput: document.getElementById('search-input'),
        searchBtn: document.getElementById('search-btn'),
        categoryFilter: document.getElementById('category-filter'),
        brandFilter: document.getElementById('brand-filter'),
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
        attachEventListeners();
        loadProducts();
        updateCounts();
    }

    // Event Listeners
    function attachEventListeners() {
        // Search
        elements.searchBtn.addEventListener('click', handleSearch);
        elements.searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') handleSearch();
        });

        // Filters
        elements.applyFiltersBtn.addEventListener('click', applyFilters);
        elements.resetFiltersBtn.addEventListener('click', resetFilters);

        // Sorting and pagination
        elements.sortSelect.addEventListener('change', handleSortChange);
        elements.perPageSelect.addEventListener('change', handlePerPageChange);

        // Enter key on price inputs
        elements.minPriceInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') applyFilters();
        });
        elements.maxPriceInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') applyFilters();
        });
    }

    // Handle search
    function handleSearch() {
        state.filters.search = elements.searchInput.value.trim();
        state.currentPage = 1;
        loadProducts();
    }

    // Apply filters
    function applyFilters() {
        state.filters.categoryId = elements.categoryFilter.value;
        state.filters.brandId = elements.brandFilter.value;
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
            categoryId: '',
            brandId: '',
            minPrice: '',
            maxPrice: '',
            onSale: false
        };
        state.currentPage = 1;

        elements.searchInput.value = '';
        elements.categoryFilter.value = '';
        elements.brandFilter.value = '';
        elements.minPriceInput.value = '';
        elements.maxPriceInput.value = '';
        elements.onSaleFilter.checked = false;

        loadProducts();
    }

    // Handle sort change
    function handleSortChange() {
        const [sortBy, sortOrder] = elements.sortSelect.value.split(':');
        state.sortBy = sortBy;
        state.sortOrder = sortOrder;
        state.currentPage = 1;
        loadProducts();
    }

    // Handle per page change
    function handlePerPageChange() {
        state.perPage = parseInt(elements.perPageSelect.value);
        state.currentPage = 1;
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
            category_id: state.filters.categoryId,
            brand_id: state.filters.brandId,
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
        if (!products || products.length === 0) {
            showEmptyState();
            return;
        }

        hideEmptyState();

        elements.productsGrid.innerHTML = products.map(product => `
            <div class="product-card">
                <a href="/shop/details/${product.slug}" class="product-image">
                    <img src="${product.image || '/images/placeholder.jpg'}" 
                         alt="${escapeHtml(product.name)}">
                    ${product.discount_percentage > 0 ? `
                        <span class="badge-sale">-${product.discount_percentage}%</span>
                    ` : ''}
                </a>
                <div class="product-content">
                    <h3 class="product-name">
                        <a href="/shop/details/${product.slug}">${escapeHtml(product.name)}</a>
                    </h3>
                    <div class="product-price">
                        ${product.sale_price && product.sale_price < product.price ? `
                            <span class="price-sale">$${formatPrice(product.sale_price)}</span>
                            <span class="price-original">$${formatPrice(product.price)}</span>
                        ` : `
                            <span class="price-current">$${formatPrice(product.price)}</span>
                        `}
                    </div>
                    <div class="product-actions">
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
            </div>
        `).join('');

        // Attach event listeners to new buttons
        attachProductEventListeners();
    }

    // Attach event listeners to product buttons
    function attachProductEventListeners() {
        // Add to cart buttons
        document.querySelectorAll('.btn-add-to-cart').forEach(btn => {
            btn.addEventListener('click', handleAddToCart);
        });

        // Wishlist buttons
        document.querySelectorAll('.btn-wishlist').forEach(btn => {
            btn.addEventListener('click', handleToggleWishlist);
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

        // Previous button
        html += `
            <button ${current_page === 1 ? 'disabled' : ''} 
                    data-page="${current_page - 1}">
                Previous
            </button>
        `;

        // Page numbers
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

        // Next button
        html += `
            <button ${current_page === last_page ? 'disabled' : ''} 
                    data-page="${current_page + 1}">
                Next
            </button>
        `;

        elements.pagination.innerHTML = html;

        // Attach click handlers
        elements.pagination.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', () => {
                state.currentPage = parseInt(btn.dataset.page);
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
            // Get cart count
            const cartResponse = await fetch(`/api/${SITE}/cart`);
            const cartData = await cartResponse.json();
            state.cartCount = cartData.count || 0;
            updateCartCount();

            // Get wishlist count
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
        elements.cartCount.textContent = state.cartCount;
        elements.cartCount.style.display = state.cartCount > 0 ? 'block' : 'none';
    }

    // Update wishlist count display
    function updateWishlistCount() {
        elements.wishlistCount.textContent = state.wishlistCount;
        elements.wishlistCount.style.display = state.wishlistCount > 0 ? 'block' : 'none';
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

    // Start the app
    init();
})();