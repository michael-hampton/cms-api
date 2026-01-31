<?php
// views/bundles/index.php

$pageTitle = 'Product Bundles';
require_once __DIR__ . '/../layouts/header.php';
?>

    <div class="bundles-page">
        <div class="page-header">
            <h1>Product Bundles</h1>
            <p class="subtitle">Save more with our curated product bundles</p>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <div class="search-container">
                <input
                        type="text"
                        id="searchInput"
                        class="search-input"
                        placeholder="Search bundles by name or products...">
                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="m21 21-4.35-4.35"/>
                </svg>
            </div>

            <div class="filter-group">
                <select id="categoryFilter" class="filter-select">
                    <option value="">All Categories</option>
                </select>

                <select id="savingsFilter" class="filter-select">
                    <option value="">All Savings</option>
                    <option value="20">Save $20 or more</option>
                    <option value="50">Save $50 or more</option>
                    <option value="100">Save $100 or more</option>
                </select>

                <select id="priceRangeFilter" class="filter-select">
                    <option value="">All Prices</option>
                    <option value="0-100">Under $100</option>
                    <option value="100-200">$100 - $200</option>
                    <option value="200-500">$200 - $500</option>
                    <option value="500">$500 and above</option>
                </select>

                <select id="merchantFilter" class="filter-select">
                    <option value="">All Merchants</option>
                    <option value="single">Single Merchant</option>
                    <option value="multi">Multi-Merchant</option>
                </select>

                <button id="clearFilters" class="btn-clear-filters">Clear Filters</button>
            </div>
        </div>

        <!-- Sorting -->
        <div class="sorting-section">
            <label>Sort by:</label>
            <select id="sortBy" class="sort-select">
                <option value="newest">Newest First</option>
                <option value="savings-high">Highest Savings</option>
                <option value="price-low">Price: Low to High</option>
                <option value="price-high">Price: High to Low</option>
                <option value="name">Name: A to Z</option>
            </select>
        </div>

        <!-- Loading State -->
        <div id="loadingState" class="loading-state" style="display: none;">
            <div class="spinner"></div>
            <p>Loading bundles...</p>
        </div>

        <!-- Bundles Grid -->
        <div id="bundlesGrid" class="bundles-grid">
            <!-- Bundles will be dynamically loaded here -->
        </div>

        <!-- Empty State -->
        <div id="emptyState" class="empty-state" style="display: none;">
            <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
            </svg>
            <h3>No bundles found</h3>
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
        .bundles-page {
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

        .bundles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 28px;
            margin-bottom: 40px;
        }

        .bundle-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
            cursor: pointer;
            border: 2px solid #e2e8f0;
        }

        .bundle-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 32px rgba(0, 0, 0, 0.15);
            border-color: #f59e0b;
        }

        .bundle-header {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            padding: 24px;
            border-bottom: 2px solid #fbbf24;
        }

        .bundle-name {
            font-size: 22px;
            font-weight: 700;
            color: #92400e;
            margin: 0 0 8px 0;
        }

        .bundle-description {
            font-size: 14px;
            color: #78350f;
            margin: 0;
            line-height: 1.5;
        }

        .multi-merchant-badge {
            display: inline-block;
            margin-top: 8px;
            padding: 4px 12px;
            background: #dbeafe;
            color: #1e40af;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .bundle-content {
            padding: 24px;
        }

        .bundle-items {
            margin-bottom: 20px;
        }

        .items-header {
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }

        .bundle-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            background: #f8fafc;
            border-radius: 8px;
            margin-bottom: 8px;
        }

        .item-image {
            width: 50px;
            height: 50px;
            border-radius: 6px;
            object-fit: cover;
            background: #e2e8f0;
        }

        .item-info {
            flex: 1;
        }

        .item-name {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .item-merchant {
            font-size: 12px;
            color: #64748b;
        }

        .item-quantity {
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            padding: 4px 10px;
            background: white;
            border-radius: 6px;
        }

        .show-more-items {
            text-align: center;
            padding: 8px;
            color: #f59e0b;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .bundle-pricing {
            padding: 20px;
            background: #f8fafc;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .pricing-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .pricing-row:last-child {
            margin-bottom: 0;
        }

        .pricing-label {
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

        .bundle-price {
            font-size: 32px;
            font-weight: 700;
            color: #f59e0b;
        }

        .savings-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            color: #166534;
        }

        .discount-badge {
            display: inline-block;
            padding: 6px 12px;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 700;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
        }

        .bundle-actions {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
        }

        .btn-add-bundle {
            padding: 14px 24px;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-add-bundle:hover {
            box-shadow: 0 8px 20px rgba(245, 158, 11, 0.4);
            transform: translateY(-2px);
        }

        .btn-wishlist-bundle {
            width: 52px;
            padding: 14px;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-wishlist-bundle:hover {
            background: #fef3c7;
            border-color: #fbbf24;
        }

        .btn-wishlist-bundle.active {
            background: #fef3c7;
            border-color: #fbbf24;
        }

        .btn-wishlist-bundle svg {
            width: 22px;
            height: 22px;
            stroke: currentColor;
            fill: none;
        }

        .btn-wishlist-bundle.active svg {
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
            .bundles-grid {
                grid-template-columns: 1fr;
            }

            .filter-group {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <script>
        // Product Bundles Page Logic
        (function () {
            let currentPage = 1;
            let totalPages = 1;
            let allBundles = [];
            let filteredBundles = [];
            let categories = new Set();
            let wishlist = JSON.parse(localStorage.getItem('bundleWishlist') || '[]');

            const elements = {
                searchInput: document.getElementById('searchInput'),
                categoryFilter: document.getElementById('categoryFilter'),
                savingsFilter: document.getElementById('savingsFilter'),
                priceRangeFilter: document.getElementById('priceRangeFilter'),
                merchantFilter: document.getElementById('merchantFilter'),
                clearFilters: document.getElementById('clearFilters'),
                sortBy: document.getElementById('sortBy'),
                loadingState: document.getElementById('loadingState'),
                bundlesGrid: document.getElementById('bundlesGrid'),
                emptyState: document.getElementById('emptyState'),
                pagination: document.getElementById('pagination'),
                prevPage: document.getElementById('prevPage'),
                nextPage: document.getElementById('nextPage'),
                pageInfo: document.getElementById('pageInfo')
            };

            // Initialize
            async function init() {
                await loadBundles();
                setupEventListeners();
            }

            // Load bundles from API
            async function loadBundles() {
                showLoading();

                try {
                    const response = await fetch('/api/bundles?status=published&is_active=true');
                    const data = await response.json();

                    allBundles = data.bundles.items;

                    // Extract categories from bundle items
                    allBundles.forEach(bundle => {
                        bundle.items.forEach(item => {
                            const product = item.product || item.product_offer?.product;
                            if (product?.category) {
                                categories.add(product.category);
                            }
                        });
                    });

                    populateCategoryFilter();
                    applyFilters();
                } catch (error) {
                    console.error('Error loading bundles:', error);
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

            // Check if bundle contains multiple merchants
            function isMultiMerchant(bundle) {
                const merchantIds = new Set();
                bundle.items.forEach(item => {
                    const merchantId = item.product?.merchant_id || item.product_offer?.merchant_id;
                    if (merchantId) merchantIds.add(merchantId);
                });
                return merchantIds.size > 1;
            }

            // Apply filters
            function applyFilters() {
                const searchTerm = elements.searchInput.value.toLowerCase();
                const categoryValue = elements.categoryFilter.value;
                const savingsValue = parseFloat(elements.savingsFilter.value) || 0;
                const priceRange = elements.priceRangeFilter.value;
                const merchantValue = elements.merchantFilter.value;

                filteredBundles = allBundles.filter(bundle => {
                    // Search filter (name or product names)
                    const matchesSearch = !searchTerm ||
                        bundle.name?.toLowerCase().includes(searchTerm) ||
                        bundle.items.some(item => {
                            const product = item.product || item.product_offer?.product;
                            return product?.name?.toLowerCase().includes(searchTerm);
                        });

                    // Category filter (any item matches)
                    const matchesCategory = !categoryValue ||
                        bundle.items.some(item => {
                            const product = item.product || item.product_offer?.product;
                            return product?.category === categoryValue;
                        });

                    // Savings filter
                    const savings = bundle.total_price - bundle.bundle_price;
                    const matchesSavings = !savingsValue || savings >= savingsValue;

                    // Price range filter
                    let matchesPrice = true;
                    if (priceRange) {
                        const price = bundle.bundle_price;
                        if (priceRange.includes('-')) {
                            const [min, max] = priceRange.split('-').map(Number);
                            matchesPrice = price >= min && price <= max;
                        } else {
                            matchesPrice = price >= parseInt(priceRange);
                        }
                    }

                    // Merchant filter
                    let matchesMerchant = true;
                    if (merchantValue) {
                        const isMulti = isMultiMerchant(bundle);
                        matchesMerchant = merchantValue === 'multi' ? isMulti : !isMulti;
                    }

                    return matchesSearch && matchesCategory && matchesSavings && matchesPrice && matchesMerchant;
                });

                applySorting();
                renderBundles();
            }

            // Apply sorting
            function applySorting() {
                const sortValue = elements.sortBy.value;

                filteredBundles.sort((a, b) => {
                    switch (sortValue) {
                        case 'newest':
                            return new Date(b.created_at) - new Date(a.created_at);
                        case 'savings-high':
                            const savingsA = a.total_price - a.bundle_price;
                            const savingsB = b.total_price - b.bundle_price;
                            return savingsB - savingsA;
                        case 'price-low':
                            return a.bundle_price - b.bundle_price;
                        case 'price-high':
                            return b.bundle_price - a.bundle_price;
                        case 'name':
                            return (a.name || '').localeCompare(b.name || '');
                        default:
                            return 0;
                    }
                });
            }

            // Render bundles
            function renderBundles() {
                hideLoading();

                if (filteredBundles.length === 0) {
                    showEmpty();
                    return;
                }

                hideEmpty();

                const itemsPerPage = 9;
                totalPages = Math.ceil(filteredBundles.length / itemsPerPage);
                const start = (currentPage - 1) * itemsPerPage;
                const end = start + itemsPerPage;
                const pageBundles = filteredBundles.slice(start, end);

                elements.bundlesGrid.innerHTML = pageBundles.map(bundle => createBundleCard(bundle)).join('');
                updatePagination();

                // Attach event listeners to new cards
                attachCardListeners();
            }

            // Create bundle card HTML
            function createBundleCard(bundle) {
                const isInWishlist = wishlist.includes(bundle.id);
                const savings = bundle.total_price - bundle.bundle_price;
                const isMulti = isMultiMerchant(bundle);

                // Show max 3 items, with "show more" for the rest
                const displayItems = bundle.items.slice(0, 3);
                const remainingCount = bundle.items.length - 3;

                return `
            <div class="bundle-card" data-bundle-id="${bundle.id}">
                <div class="bundle-header">
                    <h3 class="bundle-name">${bundle.name}</h3>
                    ${bundle.description ? `<p class="bundle-description">${bundle.description}</p>` : ''}
                    ${isMulti ? '<span class="multi-merchant-badge">Multi-Merchant</span>' : ''}
                </div>
                <div class="bundle-content">
                    <div class="bundle-items">
                        <div class="items-header">${bundle.items.length} Item(s) in Bundle</div>
                        ${displayItems.map(item => {
                    const product = item.product || item.product_offer?.product;
                    const merchant = item.product?.merchant || item.product_offer?.merchant;
                    const imageUrl = product?.images?.[0]?.url || '/assets/images/placeholder.jpg';

                    return `
                                <div class="bundle-item">
                                    <img src="${imageUrl}" alt="${product?.name || 'Product'}" class="item-image">
                                    <div class="item-info">
                                        <div class="item-name">${product?.name || 'Unknown Product'}</div>
                                        ${merchant ? `<div class="item-merchant">${merchant.name}</div>` : ''}
                                    </div>
                                    <span class="item-quantity">×${item.quantity}</span>
                                </div>
                            `;
                }).join('')}
                        ${remainingCount > 0 ? `<div class="show-more-items">+${remainingCount} more item${remainingCount > 1 ? 's' : ''}</div>` : ''}
                    </div>

                    <div class="bundle-pricing">
                        <div class="pricing-row">
                            <span class="pricing-label">Regular Price:</span>
                            <span class="regular-price">$${bundle.total_price.toFixed(2)}</span>
                        </div>
                        <div class="pricing-row">
                            <span class="pricing-label">Bundle Price:</span>
                            <span class="bundle-price">$${bundle.bundle_price.toFixed(2)}</span>
                        </div>
                        <div class="pricing-row">
                            <span class="savings-badge">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 18px; height: 18px;">
                                    <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                </svg>
                                Save $${savings.toFixed(2)}
                            </span>
                            <span class="discount-badge">${bundle.discount_percentage}% OFF</span>
                        </div>
                    </div>

                    <div class="bundle-actions">
                        <button class="btn-add-bundle" data-bundle-id="${bundle.id}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px;">
                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                            </svg>
                            Add Bundle to Cart
                        </button>
                        <button class="btn-wishlist-bundle ${isInWishlist ? 'active' : ''}" data-bundle-id="${bundle.id}">
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
                document.querySelectorAll('.btn-add-bundle').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const bundleId = parseInt(btn.dataset.bundleId);
                        addBundleToCart(bundleId);
                    });
                });

                // Wishlist buttons
                document.querySelectorAll('.btn-wishlist-bundle').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const bundleId = parseInt(btn.dataset.bundleId);
                        toggleWishlist(bundleId, btn);
                    });
                });

                // Card click (go to detail)
                document.querySelectorAll('.bundle-card').forEach(card => {
                    card.addEventListener('click', () => {
                        const bundleId = card.dataset.bundleId;
                        window.location.href = `/bundles/${bundleId}`;
                    });
                });
            }

            // Add bundle to cart
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
                    } else {
                        showNotification('Failed to add bundle to cart', 'error');
                    }
                } catch (error) {
                    console.error('Error adding bundle to cart:', error);
                    showNotification('Failed to add bundle to cart', 'error');
                }
            }

            // Toggle wishlist
            function toggleWishlist(bundleId, button) {
                const index = wishlist.indexOf(bundleId);

                if (index > -1) {
                    wishlist.splice(index, 1);
                    button.classList.remove('active');
                    showNotification('Removed from wishlist', 'info');
                } else {
                    wishlist.push(bundleId);
                    button.classList.add('active');
                    showNotification('Added to wishlist!', 'success');
                }

                localStorage.setItem('bundleWishlist', JSON.stringify(wishlist));
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

                elements.savingsFilter.addEventListener('change', () => {
                    currentPage = 1;
                    applyFilters();
                });

                elements.priceRangeFilter.addEventListener('change', () => {
                    currentPage = 1;
                    applyFilters();
                });

                elements.merchantFilter.addEventListener('change', () => {
                    currentPage = 1;
                    applyFilters();
                });

                elements.sortBy.addEventListener('change', () => {
                    applyFilters();
                });

                elements.clearFilters.addEventListener('click', () => {
                    elements.searchInput.value = '';
                    elements.categoryFilter.value = '';
                    elements.savingsFilter.value = '';
                    elements.priceRangeFilter.value = '';
                    elements.merchantFilter.value = '';
                    currentPage = 1;
                    applyFilters();
                });

                elements.prevPage.addEventListener('click', () => {
                    if (currentPage > 1) {
                        currentPage--;
                        renderBundles();
                        window.scrollTo({top: 0, behavior: 'smooth'});
                    }
                });

                elements.nextPage.addEventListener('click', () => {
                    if (currentPage < totalPages) {
                        currentPage++;
                        renderBundles();
                        window.scrollTo({top: 0, behavior: 'smooth'});
                    }
                });
            }

            // Utility functions
            function showLoading() {
                elements.loadingState.style.display = 'block';
                elements.bundlesGrid.style.display = 'none';
                elements.emptyState.style.display = 'none';
            }

            function hideLoading() {
                elements.loadingState.style.display = 'none';
                elements.bundlesGrid.style.display = 'grid';
            }

            function showEmpty() {
                elements.emptyState.style.display = 'block';
                elements.bundlesGrid.style.display = 'none';
                elements.pagination.style.display = 'none';
            }

            function hideEmpty() {
                elements.emptyState.style.display = 'none';
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

            // Initialize on load
            init();
        })();
    </script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>