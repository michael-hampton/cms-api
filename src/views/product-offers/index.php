<?php
// views/product-offers/index.php
?>

    <div class="offers-page">
        <div class="page-header">
            <h1>Product Offers</h1>
            <p class="subtitle">Browse and discover special deals on products</p>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <div class="search-container">
                <input
                        type="text"
                        id="searchInput"
                        class="search-input"
                        placeholder="Search by product name or merchant...">
                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="m21 21-4.35-4.35"/>
                </svg>
            </div>

            <div class="filter-group">
                <select id="categoryFilter" class="filter-select">
                    <option value="">All Categories</option>
                </select>

                <select id="discountFilter" class="filter-select">
                    <option value="">All Discounts</option>
                    <option value="10">10% or more</option>
                    <option value="20">20% or more</option>
                    <option value="30">30% or more</option>
                    <option value="50">50% or more</option>
                </select>

                <select id="priceRangeFilter" class="filter-select">
                    <option value="">All Prices</option>
                    <option value="0-50">Under $50</option>
                    <option value="50-100">$50 - $100</option>
                    <option value="100-200">$100 - $200</option>
                    <option value="200">$200 and above</option>
                </select>

                <button id="clearFilters" class="btn-clear-filters">Clear Filters</button>
            </div>
        </div>

        <!-- Sorting -->
        <div class="sorting-section">
            <label>Sort by:</label>
            <select id="sortBy" class="sort-select">
                <option value="newest">Newest First</option>
                <option value="price-low">Price: Low to High</option>
                <option value="price-high">Price: High to Low</option>
                <option value="discount-high">Highest Discount</option>
                <option value="name">Name: A to Z</option>
            </select>
        </div>

        <!-- Loading State -->
        <div id="loadingState" class="loading-state" style="display: none;">
            <div class="spinner"></div>
            <p>Loading offers...</p>
        </div>

        <!-- Offers Grid -->
        <div id="offersGrid" class="offers-grid">
            <!-- Offers will be dynamically loaded here -->
        </div>

        <!-- Empty State -->
        <div id="emptyState" class="empty-state" style="display: none;">
            <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <h3>No offers found</h3>
            <p>Try adjusting your filters to see more results</p>
        </div>

        <!-- Pagination -->
        <div id="pagination" class="pagination" style="display: none;">
            <button id="prevPage" class="btn-page" disabled>Previous</button>
            <span id="pageInfo" class="page-info"></span>
            <button id="nextPage" class="btn-page">Next</button>
        </div>
    </div>

    <style>
        .offers-page {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .page-header {
            margin-bottom: 40px;
            text-align: center;
        }

        .page-header h1 {
            font-size: 42px;
            font-weight: 700;
            color: #1e293b;
            margin: 0 0 12px 0;
        }

        .subtitle {
            font-size: 18px;
            color: #64748b;
            margin: 0;
        }

        .filters-section {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 32px;
        }

        .search-container {
            position: relative;
            margin-bottom: 20px;
        }

        .search-input {
            width: 100%;
            padding: 14px 20px 14px 50px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.2s;
        }

        .search-input:focus {
            outline: none;
            border-color: #f59e0b;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            width: 22px;
            height: 22px;
            color: #94a3b8;
            pointer-events: none;
        }

        .filter-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
        }

        .filter-select {
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-select:hover {
            border-color: #cbd5e1;
        }

        .filter-select:focus {
            outline: none;
            border-color: #f59e0b;
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
        }

        .btn-clear-filters {
            padding: 12px 24px;
            background: #f1f5f9;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            color: #475569;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-clear-filters:hover {
            background: #e2e8f0;
        }

        .sorting-section {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }

        .sorting-section label {
            font-weight: 600;
            color: #475569;
        }

        .sort-select {
            padding: 10px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            cursor: pointer;
        }

        .loading-state {
            text-align: center;
            padding: 60px 20px;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f1f5f9;
            border-top-color: #f59e0b;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .offers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .offer-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            cursor: pointer;
        }

        .offer-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
        }

        .offer-image {
            width: 100%;
            height: 240px;
            object-fit: cover;
            background: #f1f5f9;
        }

        .offer-badge {
            position: absolute;
            top: 16px;
            right: 16px;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }

        .offer-content {
            padding: 20px;
        }

        .offer-merchant {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .offer-name {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            margin: 0 0 12px 0;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .offer-pricing {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .offer-price {
            font-size: 28px;
            font-weight: 700;
            color: #f59e0b;
        }

        .offer-original-price {
            font-size: 18px;
            color: #94a3b8;
            text-decoration: line-through;
        }

        .offer-actions {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 8px;
        }

        .btn-add-to-cart {
            padding: 12px 20px;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-add-to-cart:hover {
            box-shadow: 0 6px 16px rgba(245, 158, 11, 0.4);
            transform: translateY(-1px);
        }

        .btn-wishlist {
            width: 48px;
            padding: 12px;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-wishlist:hover {
            background: #fef3c7;
            border-color: #fbbf24;
        }

        .btn-wishlist.active {
            background: #fef3c7;
            border-color: #fbbf24;
        }

        .btn-wishlist svg {
            width: 20px;
            height: 20px;
            stroke: currentColor;
            fill: none;
        }

        .btn-wishlist.active svg {
            fill: #f59e0b;
            stroke: #f59e0b;
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
        }

        .empty-icon {
            width: 80px;
            height: 80px;
            color: #cbd5e1;
            margin: 0 auto 24px;
        }

        .empty-state h3 {
            font-size: 24px;
            color: #475569;
            margin: 0 0 12px 0;
        }

        .empty-state p {
            font-size: 16px;
            color: #94a3b8;
        }

        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
        }

        .btn-page {
            padding: 10px 24px;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-weight: 600;
            color: #475569;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-page:hover:not(:disabled) {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .btn-page:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .page-info {
            font-weight: 600;
            color: #475569;
        }

        @media (max-width: 768px) {
            .offers-grid {
                grid-template-columns: 1fr;
            }

            .filter-group {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <script>
        // Product Offers Page Logic
        (function () {
            let currentPage = 1;
            let totalPages = 1;
            let allOffers = [];
            let filteredOffers = [];
            let categories = new Set();
            let wishlist = JSON.parse(localStorage.getItem('wishlist') || '[]');

            const elements = {
                searchInput: document.getElementById('searchInput'),
                categoryFilter: document.getElementById('categoryFilter'),
                discountFilter: document.getElementById('discountFilter'),
                priceRangeFilter: document.getElementById('priceRangeFilter'),
                clearFilters: document.getElementById('clearFilters'),
                sortBy: document.getElementById('sortBy'),
                loadingState: document.getElementById('loadingState'),
                offersGrid: document.getElementById('offersGrid'),
                emptyState: document.getElementById('emptyState'),
                pagination: document.getElementById('pagination'),
                prevPage: document.getElementById('prevPage'),
                nextPage: document.getElementById('nextPage'),
                pageInfo: document.getElementById('pageInfo')
            };

            // Initialize
            async function init() {
                await loadOffers();
                setupEventListeners();
            }

            // Load offers from API
            async function loadOffers() {
                showLoading();

                try {
                    const response = await fetch('/<?= \App\Framework\Support\SiteContext::slug() ?>/product-offers/search?status=published&is_active=true');
                    const data = await response.json();

                    allOffers = data.data.offers.items;

                    // Extract categories
                    allOffers.forEach(offer => {
                        if (offer.product?.category) {
                            categories.add(offer.product.category);
                        }
                    });

                    populateCategoryFilter();
                    applyFilters();
                } catch (error) {
                    console.error('Error loading offers:', error);
                    showEmpty();
                }
            }

            // Populate category filter
            function populateCategoryFilter() {
                const sortedCategories = Array.from(categories).sort();
                sortedCategories.forEach(category => {
                    const option = document.createElement('option');
                    option.value = category;
                    option.textContent = category;
                    elements.categoryFilter.appendChild(option);
                });
            }

            // Apply filters
            function applyFilters() {
                const searchTerm = elements.searchInput.value.toLowerCase();
                const categoryValue = elements.categoryFilter.value;
                const discountValue = parseInt(elements.discountFilter.value) || 0;
                const priceRange = elements.priceRangeFilter.value;

                filteredOffers = allOffers.filter(offer => {
                    // Search filter
                    const matchesSearch = !searchTerm ||
                        offer.product?.name?.toLowerCase().includes(searchTerm) ||
                        offer.merchant?.name?.toLowerCase().includes(searchTerm);

                    // Category filter
                    const matchesCategory = !categoryValue ||
                        offer.product?.category === categoryValue;

                    // Discount filter
                    const matchesDiscount = !discountValue ||
                        offer.discount_percentage >= discountValue;

                    // Price range filter
                    let matchesPrice = true;
                    if (priceRange) {
                        const price = offer.sale_price;
                        if (priceRange.includes('-')) {
                            const [min, max] = priceRange.split('-').map(Number);
                            matchesPrice = price >= min && price <= max;
                        } else {
                            matchesPrice = price >= parseInt(priceRange);
                        }
                    }

                    return matchesSearch && matchesCategory && matchesDiscount && matchesPrice;
                });

                applySorting();
                renderOffers();
            }

            // Apply sorting
            function applySorting() {
                const sortValue = elements.sortBy.value;

                filteredOffers.sort((a, b) => {
                    switch (sortValue) {
                        case 'newest':
                            return new Date(b.created_at) - new Date(a.created_at);
                        case 'price-low':
                            return a.sale_price - b.sale_price;
                        case 'price-high':
                            return b.sale_price - a.sale_price;
                        case 'discount-high':
                            return b.discount_percentage - a.discount_percentage;
                        case 'name':
                            return (a.product?.name || '').localeCompare(b.product?.name || '');
                        default:
                            return 0;
                    }
                });
            }

            // Render offers
            function renderOffers() {
                hideLoading();

                if (filteredOffers.length === 0) {
                    showEmpty();
                    return;
                }

                hideEmpty();

                const itemsPerPage = 12;
                totalPages = Math.ceil(filteredOffers.length / itemsPerPage);
                const start = (currentPage - 1) * itemsPerPage;
                const end = start + itemsPerPage;
                const pageOffers = filteredOffers.slice(start, end);

                elements.offersGrid.innerHTML = pageOffers.map(offer => createOfferCard(offer)).join('');
                updatePagination();

                // Attach event listeners to new cards
                attachCardListeners();
            }

            // Create offer card HTML
            function createOfferCard(offer) {
                const isInWishlist = wishlist.includes(offer.id);
                const imageUrl = offer.product?.images?.[0]?.url || '/assets/images/placeholder.jpg';

                return `
            <div class="offer-card" data-offer-id="${offer.id}">
                <div style="position: relative;">
                    <img src="${imageUrl}" alt="${offer.product?.name || 'Product'}" class="offer-image">
                    <div class="offer-badge">${offer.product.discount_percentage}% OFF</div>
                </div>
                <div class="offer-content">
                    <div class="offer-merchant">${offer.merchant?.name || offer.product?.merchant?.name || 'Unknown Merchant'}</div>
                    <h3 class="offer-name">${offer.product?.name || 'Unnamed Product'}</h3>
                    <div class="offer-pricing">
                        <span class="offer-price">$${offer.sale_price.toFixed(2)}</span>
                        <span class="offer-original-price">$${offer.product.price.toFixed(2)}</span>
                    </div>
                    <div class="offer-actions">
                        <button class="btn-add-to-cart" data-offer-id="${offer.id}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px;">
                                <circle cx="9" cy="21" r="1"/>
                                <circle cx="20" cy="21" r="1"/>
                                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                            </svg>
                            Add to Cart
                        </button>
                        <button class="btn-wishlist ${isInWishlist ? 'active' : ''}" data-offer-id="${offer.id}">
                            <svg viewBox="0 0 24 24" stroke-width="2">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        `;
            }

            // Attach card listeners
            function attachCardListeners() {
                // Add to cart buttons
                document.querySelectorAll('.btn-add-to-cart').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const offerId = parseInt(btn.dataset.offerId);
                        addToCart(offerId);
                    });
                });

                // Wishlist buttons
                document.querySelectorAll('.btn-wishlist').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const offerId = parseInt(btn.dataset.offerId);
                        toggleWishlist(offerId, btn);
                    });
                });

                // Card click (go to detail)
                document.querySelectorAll('.offer-card').forEach(card => {
                    card.addEventListener('click', () => {
                        const offerId = card.dataset.offerId;
                        window.location.href = `/product-offers/${offerId}`;
                    });
                });
            }

            // Add to cart
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
                    } else {
                        showNotification('Failed to add to cart', 'error');
                    }
                } catch (error) {
                    console.error('Error adding to cart:', error);
                    showNotification('Failed to add to cart', 'error');
                }
            }

            // Toggle wishlist
            function toggleWishlist(offerId, button) {
                const index = wishlist.indexOf(offerId);

                if (index > -1) {
                    wishlist.splice(index, 1);
                    button.classList.remove('active');
                    showNotification('Removed from wishlist', 'info');
                } else {
                    wishlist.push(offerId);
                    button.classList.add('active');
                    showNotification('Added to wishlist!', 'success');
                }

                localStorage.setItem('wishlist', JSON.stringify(wishlist));
            }

            // Update pagination
            function updatePagination() {
                if (totalPages <= 1) {
                    elements.pagination.style.display = 'none';
                    return;
                }

                elements.pagination.style.display = 'flex';
                elements.prevPage.disabled = currentPage === 1;
                elements.nextPage.disabled = currentPage === totalPages;
                elements.pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
            }

            // Setup event listeners
            function setupEventListeners() {
                elements.searchInput.addEventListener('input', () => {
                    currentPage = 1;
                    applyFilters();
                });

                elements.categoryFilter.addEventListener('change', () => {
                    currentPage = 1;
                    applyFilters();
                });

                elements.discountFilter.addEventListener('change', () => {
                    currentPage = 1;
                    applyFilters();
                });

                elements.priceRangeFilter.addEventListener('change', () => {
                    currentPage = 1;
                    applyFilters();
                });

                elements.sortBy.addEventListener('change', () => {
                    applyFilters();
                });

                elements.clearFilters.addEventListener('click', () => {
                    elements.searchInput.value = '';
                    elements.categoryFilter.value = '';
                    elements.discountFilter.value = '';
                    elements.priceRangeFilter.value = '';
                    currentPage = 1;
                    applyFilters();
                });

                elements.prevPage.addEventListener('click', () => {
                    if (currentPage > 1) {
                        currentPage--;
                        renderOffers();
                        window.scrollTo({top: 0, behavior: 'smooth'});
                    }
                });

                elements.nextPage.addEventListener('click', () => {
                    if (currentPage < totalPages) {
                        currentPage++;
                        renderOffers();
                        window.scrollTo({top: 0, behavior: 'smooth'});
                    }
                });
            }

            // Utility functions
            function showLoading() {
                elements.loadingState.style.display = 'block';
                elements.offersGrid.style.display = 'none';
                elements.emptyState.style.display = 'none';
            }

            function hideLoading() {
                elements.loadingState.style.display = 'none';
                elements.offersGrid.style.display = 'grid';
            }

            function showEmpty() {
                elements.emptyState.style.display = 'block';
                elements.offersGrid.style.display = 'none';
                elements.pagination.style.display = 'none';
            }

            function hideEmpty() {
                elements.emptyState.style.display = 'none';
            }

            function showNotification(message, type = 'info') {
                // Simple notification (you can enhance this)
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

            // Initialize on load
            init();
        })();
    </script>