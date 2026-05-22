/**
 * Shop Component Architecture Refactor
 * Fully object-oriented, state-driven implementation preserving 100% original functionality.
 */

// Global Utility Helpers
class ProductUtils {
    static escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    static formatPrice(price) {
        return parseFloat(price).toFixed(2);
    }

    static getStockStatus(quantity) {
        if (quantity === 0) {
            return { class: 'out-of-stock', text: 'Out of Stock' };
        } else if (quantity < 10) {
            return { class: 'low-stock', text: `Only ${quantity} left in stock` };
        } else {
            return { class: 'in-stock', text: 'In Stock' };
        }
    }

    static generatePriceChartSVG(priceHistory) {
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
}

// State Management Subsystem
class ProductStateManager {
    constructor() {
        this.listeners = [];
        this.state = {
            currentPage: 1,
            perPage: 12,
            sortBy: 'created_at',
            sortOrder: 'desc',
            activeTab: 'all',
            filters: {
                search: '',
                categoryIds: [],
                brandIds: [],
                specificationIds: [],
                minPrice: '',
                maxPrice: '',
                onSale: false,
                minRating: null,
                minDiscount: null,
                hasVoucher: false,
            },
            activeSuggestedFilters: new Set(),
            lastSuggestions: [],
            allDiscoveredFilters: new Map(),
            cartCount: 0,
            wishlistCount: 0,
            wishlistProductIds: new Set(),
            cartProductIds: new Set(),
            debounceTimer: null
        };
    }

    onChange(callback) {
        this.listeners.push(callback);
    }

    notify(reason = 'state_changed') {
        this.listeners.forEach(callback => callback(this.state, reason));
    }

    seedInitialState() {
        const d = window.INITIAL_DATA;
        if (!d) return;

        if (typeof d.cartCount === 'number') {
            this.state.cartCount = d.cartCount;
        }
        if (Array.isArray(d.cartProductIds)) {
            this.state.cartProductIds = new Set(d.cartProductIds.map(Number));
        }
        if (typeof d.wishlistCount === 'number') {
            this.state.wishlistCount = d.wishlistCount;
        }
        if (Array.isArray(d.wishlistProductIds)) {
            this.state.wishlistProductIds = new Set(d.wishlistProductIds.map(Number));
        }
        this.notify('initial_seeded');
    }

    loadFromURL() {
        const params = new URLSearchParams(window.location.search);

        this.state.currentPage = parseInt(params.get('page')) || 1;
        this.state.perPage = parseInt(params.get('per_page')) || 12;
        this.state.sortBy = params.get('sort_by') || 'created_at';
        this.state.sortOrder = params.get('sort_order') || 'desc';
        this.state.activeTab = params.get('tab') || 'all';
        this.state.filters.search = params.get('q') || '';
        this.state.filters.categoryIds = params.get('category_ids') ? params.get('category_ids').split(',') : [];
        this.state.filters.brandIds = params.get('brand_ids') ? params.get('brand_ids').split(',') : [];
        this.state.filters.specificationIds = params.get('spec_ids') ? params.get('spec_ids').split(',') : [];
        this.state.filters.minPrice = params.get('min_price') || '';
        this.state.filters.maxPrice = params.get('max_price') || '';
        this.state.filters.onSale = params.get('on_sale') === '1';
        this.state.filters.minRating = params.get('min_rating') ? parseInt(params.get('min_rating')) : null;
        this.state.filters.minDiscount = params.get('min_discount') ? parseInt(params.get('min_discount')) : null;
        this.state.filters.hasVoucher = params.get('has_voucher') === '1';

        this.notify('url_loaded');
    }

    updateURL() {
        const params = new URLSearchParams();

        if (this.state.activeTab && this.state.activeTab !== 'all') params.set('tab', this.state.activeTab);
        if (this.state.currentPage > 1) params.set('page', this.state.currentPage);
        if (this.state.perPage !== 12) params.set('per_page', this.state.perPage);
        if (this.state.sortBy !== 'created_at' || this.state.sortOrder !== 'desc') {
            params.set('sort_by', this.state.sortBy);
            params.set('sort_order', this.state.sortOrder);
        }
        if (this.state.filters.search) params.set('q', this.state.filters.search);
        if (this.state.filters.categoryIds.length) params.set('category_ids', this.state.filters.categoryIds.join(','));
        if (this.state.filters.brandIds.length) params.set('brand_ids', this.state.filters.brandIds.join(','));
        if (this.state.filters.specificationIds.length) params.set('spec_ids', this.state.filters.specificationIds.join(','));
        if (this.state.filters.minPrice) params.set('min_price', this.state.filters.minPrice);
        if (this.state.filters.maxPrice) params.set('max_price', this.state.filters.maxPrice);
        if (this.state.onSale || this.state.filters.onSale) params.set('on_sale', '1');
        if (this.state.filters.minRating) params.set('min_rating', this.state.filters.minRating);
        if (this.state.filters.minDiscount) params.set('min_discount', this.state.filters.minDiscount);
        if (this.state.filters.hasVoucher) params.set('has_voucher', '1');

        const newURL = `${window.location.pathname}${params.toString() ? '?' + params.toString() : ''}`;
        window.history.pushState({}, '', newURL);
    }

    resetFiltersState() {
        this.state.filters = {
            search: '',
            categoryIds: [],
            brandIds: [],
            specificationIds: [],
            minPrice: '',
            maxPrice: '',
            onSale: false,
            minRating: null,
            minDiscount: null,
            hasVoucher: false
        };
        this.state.activeSuggestedFilters.clear();
        this.state.lastSuggestions = [];
        this.state.currentPage = 1;
    }
}

// API Connection Service Layer
class ProductApiService {
    static async fetchProducts(state) {
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
            on_sale: state.filters.onSale ? '1' : '',
        });

        if (state.filters.minRating) params.set('min_rating', state.filters.minRating);
        if (state.filters.minDiscount) params.set('min_discount', state.filters.minDiscount);
        if (state.filters.minDiscountPercent) params.set('min_discount', state.filters.minDiscountPercent);
        if (state.filters.hasVoucher) params.set('has_voucher', '1');

        const response = await fetch(`/api/${SITE}/product-list/search?${params}`);
        if (!response.ok) throw new Error('Failed to load products');
        return response.json();
    }

    static async fetchCardBackData(productId) {
        const response = await fetch(`/api/${SITE}/product-list/${productId}/details`);
        if (!response.ok) throw new Error('Failed to load details');
        return response.json();
    }

    static async addToCart(productId) {
        const response = await fetch(`/api/${SITE}/cart/add`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: productId, quantity: 1 })
        });
        return response.json();
    }

    static async toggleWishlist(productId, isInWishlist) {
        const url = isInWishlist
            ? `/api/${SITE}/wishlist/remove/${productId}`
            : `/api/${SITE}/wishlist/add`;

        const response = await fetch(url, {
            method: isInWishlist ? 'DELETE' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: isInWishlist ? null : JSON.stringify({ product_id: productId })
        });
        return response.json();
    }

    static async recordBoostClick(productId, context) {
        return fetch(`/api/${SITE}/boost/click`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: productId, context })
        }).catch(err => console.error('Analytics record error:', err));
    }

    static async fetchCountsSync() {
        const cartResponse = await fetch(`/api/${SITE}/cart`);
        const cartData = await cartResponse.json();
        const wishlistResponse = await fetch(`/api/${SITE}/wishlist`);
        const wishlistData = await wishlistResponse.json();
        return { cartData, wishlistData };
    }
}

// Comparison Business Manager
class ComparisonManager {
    constructor(app) {
        this.app = app;
        this.products = new Set();
        this.maxProducts = 4;
        this.barElement = document.getElementById('comparison-bar');
    }

    handleToggle(productId, btnElement) {
        if (this.products.has(productId)) {
            this.products.delete(productId);
            btnElement.classList.remove('active');
            // Sync dual buttons (front and back of card layout)
            document.querySelectorAll(`.btn-compare[data-product-id="${productId}"]`).forEach(b => b.classList.remove('active'));
        } else {
            if (this.products.size >= this.maxProducts) {
                this.app.ui.showToast('Maximum 4 products can be compared', 'error');
                return;
            }
            this.products.add(productId);
            document.querySelectorAll(`.btn-compare[data-product-id="${productId}"]`).forEach(b => b.classList.add('active'));
        }
        this.updateBarUI();
    }

    updateBarUI() {
        if (!this.barElement) return;
        const count = this.products.size;

        if (count >= 2) {
            this.barElement.style.display = 'flex';
            this.barElement.querySelector('.comparison-count').textContent = `${count} products selected`;
        } else {
            this.barElement.style.display = 'none';
        }
    }

    compareProducts() {
        if (this.products.size < 2) {
            this.app.ui.showToast('Select at least 2 products to compare', 'error');
            return;
        }
        const ids = Array.from(this.products).join(',');
        window.location.href = `/${SITE}/compare?ids=${ids}`;
    }
}

// User Interface Grid and Template Renderer
class ProductGridUI {
    constructor(app) {
        this.app = app;
        this.currentlyFlippedCard = null;

        this.elements = {
            productsGrid: document.getElementById('products-grid'),
            loadingState: document.getElementById('loading-state'),
            tabLoading: document.getElementById('tab-loading'),
            emptyState: document.getElementById('empty-state'),
            pagination: document.getElementById('pagination'),
            resultsCount: document.getElementById('results-count'),
            cartCount: document.getElementById('cart-count'),
            wishlistCount: document.getElementById('wishlist-count'),
            toast: document.getElementById('toast')
        };

        this.initDelegatedEvents();
    }

    initDelegatedEvents() {
        // Main Product Grid Catch-All Router
        if (this.elements.productsGrid) {
            this.elements.productsGrid.addEventListener('click', e => {
                if (e.target.closest('.btn-compare, .btn-flip, .btn-wishlist, .btn-add-to-cart, .btn-show-review, .btn-share, .product-card-back, .merchant-badge, .merchant-count')) {
                    return;
                }
                const productCard = e.target.closest('.product-card');
                if (productCard && window.productModal) {
                    window.productModal.open(productCard.dataset.productId);
                }
            });
        }

        // Global Keydown monitoring
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.currentlyFlippedCard) {
                this.flipBackCard(this.currentlyFlippedCard);
            }
        });
    }

    syncStateDisplays(state) {
        if (this.elements.cartCount) {
            this.elements.cartCount.textContent = state.cartCount;
            this.elements.cartCount.style.display = state.cartCount > 0 ? 'block' : 'none';
        }
        if (this.elements.wishlistCount) {
            this.elements.wishlistCount.textContent = state.wishlistCount;
            this.elements.wishlistCount.style.display = state.wishlistCount > 0 ? 'block' : 'none';
        }
    }

    showToast(message, type = 'info') {
        if (!this.elements.toast) return;
        this.elements.toast.textContent = message;
        this.elements.toast.className = `toast ${type} show`;

        setTimeout(() => {
            this.elements.toast.classList.remove('show');
        }, 3000);
    }

    showLoading() {
        if (this.elements.loadingState) this.elements.loadingState.style.display = 'block';
        if (this.elements.productsGrid) this.elements.productsGrid.style.display = 'none';
        if (this.elements.emptyState) this.elements.emptyState.style.display = 'none';
    }

    hideLoading() {
        if (this.elements.loadingState) this.elements.loadingState.style.display = 'none';
        if (this.elements.productsGrid) this.elements.productsGrid.style.display = 'grid';
    }

    showTabLoading() {
        if (this.elements.tabLoading) this.elements.tabLoading.style.display = 'block';
    }

    hideTabLoading() {
        if (this.elements.tabLoading) this.elements.tabLoading.style.display = 'none';
    }

    showEmptyState() {
        if (this.elements.productsGrid) {
            this.elements.productsGrid.style.display = 'none';
            this.elements.productsGrid.innerHTML = '';
        }
        if (this.elements.emptyState) this.elements.emptyState.style.display = 'block';
        if (this.elements.pagination) this.elements.pagination.innerHTML = '';
    }

    updateResultsCount(total) {
        if (this.elements.resultsCount) {
            this.elements.resultsCount.textContent = `${total} product${total !== 1 ? 's' : ''}`;
        }
    }

    renderStars(rating) {
        const fullStars = Math.floor(rating);
        let html = '<div class="rating-stars">';
        for (let i = 0; i < 5; i++) {
            html += `<svg class="rating-star ${i < fullStars ? 'filled' : 'empty'}" viewBox="0 0 24 24">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
            </svg>`;
        }
        html += '</div>';
        return html;
    }

    renderMerchants(merchants) {
        if (!merchants || merchants.length === 0) return '';

        if (merchants.length === 1) {
            const merchant = merchants[0];
            const merchantName = merchant.merchant?.name || merchant.name || 'Unknown';
            const merchantUrl = merchant.url || merchant.merchant?.url || '#';

            return `<div class="product-merchants">
                <a href="${ProductUtils.escapeHtml(merchantUrl)}" 
                   target="_blank" 
                   rel="noopener noreferrer"
                   class="merchant-badge ${merchant.is_best_price ? 'best-price' : ''}"
                   onclick="event.stopPropagation()">
                    ${ProductUtils.escapeHtml(merchantName)}
                    ${merchant.discount_percentage > 0 ? ` -${merchant.discount_percentage}%` : ''}
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left: 4px;">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                        <polyline points="15 3 21 3 21 9"></polyline>
                        <line x1="10" y1="14" x2="21" y2="3"></line>
                    </svg>
                </a>
            </div>`;
        }

        const bestMerchant = merchants.reduce((best, current) => {
            const bestPrice = best.sale_price > 0 ? best.sale_price : best.price;
            const currentPrice = current.sale_price > 0 ? current.sale_price : current.price;
            return currentPrice < bestPrice ? current : best;
        }, merchants[0]);

        const merchantName = bestMerchant.merchant?.name || bestMerchant.name || 'Unknown';
        const merchantUrl = bestMerchant.url || bestMerchant.merchant?.url || '#';

        return `<div class="product-merchants">
            <a href="${ProductUtils.escapeHtml(merchantUrl)}" 
               target="_blank" 
               rel="noopener noreferrer"
               class="merchant-badge best-price"
               onclick="event.stopPropagation()">
                ${ProductUtils.escapeHtml(merchantName)}
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left: 4px;">
                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                    <polyline points="15 3 21 3 21 9"></polyline>
                    <line x1="10" y1="14" x2="21" y2="3"></line>
                </svg>
            </a>
            ${merchants.length > 1 ? `<span class="merchant-count" onclick="event.stopPropagation(); window.showAllMerchants(event, ${JSON.stringify(merchants).replace(/"/g, '&quot;')})">+${merchants.length - 1} more</span>` : ''}
        </div>`;
    }

    showAllMerchants(e, merchants) {
        e.stopPropagation();
        document.querySelectorAll('.merchant-popover').forEach(p => p.remove());

        const popover = document.createElement('div');
        popover.className = 'merchant-popover';
        popover.style.cssText = 'position:absolute;z-index:100;background:white;border:1px solid #e2e8f0;border-radius:8px;padding:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15);min-width:180px;';
        popover.innerHTML = merchants.map(m => {
            const name = m.merchant?.name || m.name || 'Unknown';
            const price = m.sale_price > 0 ? m.sale_price : m.price;
            const url = m.url || '#';
            return `<a href="${url}" target="_blank" rel="noopener noreferrer" 
                style="display:flex;justify-content:space-between;padding:6px 8px;text-decoration:none;color:#1e293b;font-size:0.8125rem;border-radius:4px;"
                onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                <span>${ProductUtils.escapeHtml(name)}</span>
                <span style="font-weight:600;margin-left:12px;">${CURRENCY_SYMBOL}${ProductUtils.formatPrice(price)}</span>
            </a>`;
        }).join('');

        const target = e.currentTarget;
        target.style.position = 'relative';
        target.appendChild(popover);

        setTimeout(() => {
            document.addEventListener('click', () => popover.remove(), { once: true });
        }, 0);
    }

    renderTopReview(review) {
        if (!review) return '';
        return `
            <button class="btn-show-review" onclick="event.stopPropagation(); window.toggleReview(this)">
                <span>Top Review</span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </button>
            <div class="top-review-section">
                <div class="review-header">
                    ${this.renderStars(review.rating)}
                    <span class="review-helpful">${review.helpful_count} helpful</span>
                </div>
                ${review.title ? `<div class="review-title">${ProductUtils.escapeHtml(review.title)}</div>` : ''}
                <div class="review-comment">${ProductUtils.escapeHtml(review.comment)}</div>
                <div class="review-author">by ${ProductUtils.escapeHtml(review.author_name)}</div>
            </div>
        `;
    }

    toggleReview(btn) {
        const section = btn.nextElementSibling;
        const isExpanded = section.classList.contains('show');

        section.classList.toggle('show');
        btn.classList.toggle('expanded');

        const text = btn.querySelector('span');
        text.textContent = isExpanded ? 'Top Review' : 'Hide Review';
    }

    flipCard(productId, cardElement) {
        if (this.currentlyFlippedCard === cardElement) {
            cardElement.classList.remove('flipped');
            this.currentlyFlippedCard = null;
            document.body.classList.remove('card-flipped');
            return;
        }

        if (this.currentlyFlippedCard) {
            this.currentlyFlippedCard.classList.remove('flipped');
        }

        cardElement.classList.add('flipped');
        this.currentlyFlippedCard = cardElement;
        document.body.classList.add('card-flipped');

        if (!cardElement.dataset.backLoaded) {
            this.loadCardBackData(productId, cardElement);
            cardElement.dataset.backLoaded = 'true';
        }
    }

    flipBackCard(cardElement) {
        cardElement.classList.remove('flipped');
        if (this.currentlyFlippedCard === cardElement) {
            this.currentlyFlippedCard = null;
        }
        document.body.classList.remove('card-flipped');
    }

    async loadCardBackData(productId, cardElement) {
        const backContent = cardElement.querySelector('.card-back-dynamic-content');
        if (!backContent) return;

        try {
            backContent.innerHTML = '<div style="text-align: center; padding: 2rem; color: #64748b;">Loading details...</div>';
            const data = await ProductApiService.fetchCardBackData(productId);

            if (data.success) {
                this.renderCardBackContent(data.product, backContent, productId);
            } else {
                backContent.innerHTML = '<div style="text-align: center; padding: 2rem; color: #ef4444;">Failed to load details</div>';
            }
        } catch (error) {
            console.error('Error handling card reverse construction:', error);
            backContent.innerHTML = '<div style="text-align: center; padding: 2rem; color: #ef4444;">An error occurred</div>';
        }
    }

    renderCardBackContent(product, container, productId) {
        const { description, stock_quantity, variants, price_history, comparison, specifications, merchants } = product;
        let html = '';

        if (description) {
            const shortDesc = description.length > 150 ? description.substring(0, 150) + '...' : description;
            html += `
                <div class="back-section">
                    <h4 class="back-section-title">Description</h4>
                    <p class="product-description">${ProductUtils.escapeHtml(shortDesc)}</p>
                </div>
            `;
        }

        const stockStatus = ProductUtils.getStockStatus(stock_quantity);
        html += `
            <div class="back-section">
                <h4 class="back-section-title">Availability</h4>
                <div class="stock-indicator ${stockStatus.class}">
                    <span class="stock-dot"></span>
                    <span>${stockStatus.text}</span>
                </div>
            </div>
        `;

        if (variants && variants.length > 0) {
            html += `
                <div class="back-section">
                    <h4 class="back-section-title">Available Options</h4>
                    <div class="variants-grid">
                        ${variants.map(v => `
                            <div class="variant-option ${v.in_stock ? '' : 'disabled'}" 
                                 data-variant-id="${v.id}" data-variant-price="${v.final_price}">
                                <div style="font-weight: 500;">${ProductUtils.escapeHtml(v.name)}</div>
                                ${v.discount_percentage > 0 ? `<div style="font-size: 0.75rem; color: #059669;">-${v.discount_percentage}%</div>` : ''}
                                <div style="font-size: 0.75rem; color: #64748b;">${CURRENCY_SYMBOL}${ProductUtils.formatPrice(v.final_price)}</div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }

        if (price_history && price_history.length > 0) {
            const prices = price_history.map(p => p.price);
            const currentPrice = prices[prices.length - 1];
            const lowestPrice = Math.min(...prices);
            const highestPrice = Math.max(...prices);
            const savingsPercent = currentPrice === lowestPrice && highestPrice > lowestPrice ? Math.round(((highestPrice - lowestPrice) / highestPrice) * 100) : 0;

            html += `
                <div class="back-section">
                    <h4 class="back-section-title">Price History (90 Days)</h4>
                    <div class="price-chart-container">
                        <div class="price-stats">
                            <div class="price-stat"><div class="price-stat-label">Current</div><div class="price-stat-value current">${CURRENCY_SYMBOL}${ProductUtils.formatPrice(currentPrice)}</div></div>
                            <div class="price-stat"><div class="price-stat-label">Lowest</div><div class="price-stat-value low">${CURRENCY_SYMBOL}${ProductUtils.formatPrice(lowestPrice)}</div></div>
                            <div class="price-stat"><div class="price-stat-label">Highest</div><div class="price-stat-value high">${CURRENCY_SYMBOL}${ProductUtils.formatPrice(highestPrice)}</div></div>
                        </div>
                        ${savingsPercent > 0 ? `<div style="text-align: center; margin-bottom: 0.5rem; color: #059669; font-size: 0.875rem; font-weight: 500;">💰 Save ${savingsPercent}% vs highest price!</div>` : ''}
                        <div class="price-chart">
                            <svg class="price-chart-line" viewBox="0 0 100 40" preserveAspectRatio="none">
                                ${ProductUtils.generatePriceChartSVG(price_history)}
                            </svg>
                        </div>
                    </div>
                </div>
            `;
        }

        if (specifications && specifications.length > 0) {
            html += `
                <div class="back-section">
                    <h4 class="back-section-title">Specifications</h4>
                    <div class="comparison-section">
                        ${specifications.map(spec => `
                            <div class="comparison-item">
                                <span class="comparison-label">${ProductUtils.escapeHtml(spec.key)}</span>
                                <span class="comparison-value">${ProductUtils.escapeHtml(spec.value)}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }

        if (comparison) {
            html += `
                <div class="back-section">
                    <h4 class="back-section-title">Price Comparison</h4>
                    <div class="comparison-section">
                        <div class="comparison-item"><span class="comparison-label">vs. Category Average</span><span class="comparison-badge ${comparison.price_comparison}">${comparison.price_difference}</span></div>
                        ${comparison.category_avg_price ? `<div class="comparison-item"><span class="comparison-label">Category Average</span><span class="comparison-value">${CURRENCY_SYMBOL}${comparison.category_avg_price}</span></div>` : ''}
                        ${comparison.discount_vs_regular ? `<div class="comparison-item"><span class="comparison-label">Your Savings</span><span class="comparison-badge better">${comparison.discount_vs_regular}</span></div>` : ''}
                        ${comparison.products_in_category ? `<div class="comparison-item"><span class="comparison-label">Similar Products</span><span class="comparison-value">${comparison.products_in_category} in category</span></div>` : ''}
                    </div>
                </div>
            `;
        }

        if (merchants && merchants.length > 1) {
            html += `
                <div class="back-section">
                    <h4 class="back-section-title">Available From</h4>
                    <div class="comparison-section">
                        ${merchants.slice(0, 3).map(m => `
                            <div class="comparison-item">
                                <span class="comparison-label"><a href="${m.url}" target="_blank" style="color: #2563eb; text-decoration: none;">Merchant</a></span>
                                <span class="comparison-value">${CURRENCY_SYMBOL}${ProductUtils.formatPrice(m.sale_price > 0 ? m.sale_price : m.price)}${m.has_discount ? `<span style="color: #059669; font-size: 0.75rem; margin-left: 0.25rem;">-${m.discount_percentage}%</span>` : ''}</span>
                            </div>
                        `).join('')}
                        ${merchants.length > 3 ? `<div style="text-align: center; margin-top: 0.5rem; font-size: 0.875rem; color: #64748b;">+${merchants.length - 3} more retailers</div>` : ''}
                    </div>
                </div>
            `;
        }

        container.innerHTML = html;

        // Attach variant selection handlers safely
        if (variants && variants.length > 0) {
            container.querySelectorAll('.variant-option:not(.disabled)').forEach(opt => {
                opt.addEventListener('click', function() {
                    container.querySelectorAll('.variant-option').forEach(o => o.classList.remove('selected'));
                    this.classList.add('selected');
                    console.log(`Selected variant ${this.dataset.variantId} for product ${productId}`);
                });
            });
        }
    }

    renderProductsGrid(products, stateRef) {
        if (!this.elements.productsGrid) return;
        this.elements.productsGrid.innerHTML = products.map(product => {
            const isCartActive = stateRef.cartProductIds.has(Number(product.id)) ? ' active' : '';
            const isWishlistActive = stateRef.wishlistProductIds.has(Number(product.id)) ? ' active' : '';
            const isCompareActive = this.app.comparison.products.has(Number(product.id)) ? ' active' : '';

            return `
                <div class="product-card" data-product-id="${product.id}">
                    <div class="product-card-inner">
                        <div class="product-card-front">
                            <button class="btn-flip" data-product-id="${product.id}" title="View details">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/>
                                </svg>
                            </button>
                            <button class="btn-share" data-share='${ProductUtils.escapeHtml(JSON.stringify({
                id: product.id, name: product.name, slug: product.slug, price: product.price,
                sale_price: product.sale_price, image: product.image,
                merchant_name: product.merchants && product.merchants.length > 0 ? product.merchants[0].name : null
            }))}' title="Share product">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle>
                                    <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                                </svg>
                            </button>
                            <a href="/${SITE}/shop/details/${product.slug}" class="product-image">
                                <img src="${product.image || '/images/placeholder.jpg'}" alt="${ProductUtils.escapeHtml(product.title || product.name)}">
                                ${product.discount_percentage > 0 ? `<span class="badge-sale">-${product.discount_percentage}%</span>` : ''}
                                ${product.is_boosted ? `<span class="badge-sponsored">Sponsored</span>` : ''}
                            </a>
                            <div class="product-content">
                                <h3 class="product-name"><a href="/${SITE}/shop/details/${product.slug}">${ProductUtils.escapeHtml(product.title || product.name)}</a></h3>
                                ${product.average_rating > 0 ? `
                                    <div class="product-rating">
                                        <div class="stars-small">${this.renderStars(product.average_rating)}</div>
                                        <span class="rating-count">(${product.review_count || 0})</span>
                                    </div>
                                ` : ''}
                                ${this.renderMerchants(product.availableMerchants || [])}
                                <div class="product-price">
                                    ${product.sale_price && product.sale_price < product.price ? `
                                        <span class="price-sale">${CURRENCY_SYMBOL}${ProductUtils.formatPrice(product.sale_price)}</span>
                                        <span class="price-original">${CURRENCY_SYMBOL}${ProductUtils.formatPrice(product.price)}</span>
                                    ` : `<span class="price-current">${CURRENCY_SYMBOL}${ProductUtils.formatPrice(product.price)}</span>`}
                                </div>
                                <div class="product-actions">
                                    <button class="btn-compare${isCompareActive}" data-product-id="${product.id}" title="Add to comparison">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"></path></svg>
                                    </button>
                                    <button class="btn-add-to-cart${isCartActive}" data-product-id="${product.id}">Add to Cart</button>
                                    <button class="btn-wishlist${isWishlistActive}" data-product-id="${product.id}">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                                    </button>
                                </div>
                            </div>
                            ${product.top_review ? this.renderTopReview(product.top_review) : ''}
                        </div>
                        <div class="product-card-back">
                            <div class="card-back-header">
                                <h3 class="card-back-title">${ProductUtils.escapeHtml(product.name)}</h3>
                                <button class="btn-flip-back" data-product-id="${product.id}" title="Flip back">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                </button>
                            </div>
                            <div class="card-back-content"><div class="card-back-dynamic-content"></div></div>
                            <div class="card-back-actions">
                                <button class="btn-compare${isCompareActive}" data-product-id="${product.id}" title="Add to comparison">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"></path></svg>
                                </button>
                                <button class="btn-back-action btn-add-cart-back" data-product-id="${product.id}">Add to Cart</button>
                                <a href="/${SITE}/shop/details/${product.slug}" class="btn-back-action btn-view-details">Full Details</a>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        this.attachInlineProductListeners();
    }

    attachInlineProductListeners() {
        // Wire Add to Cart Buttons
        this.elements.productsGrid.querySelectorAll('.btn-add-to-cart, .btn-add-cart-back').forEach(btn => {
            btn.addEventListener('click', e => {
                e.stopPropagation();
                this.app.executeAddToCart(e.currentTarget);
            });
        });

        // Wire Wishlist Toggle Buttons
        this.elements.productsGrid.querySelectorAll('.btn-wishlist').forEach(btn => {
            btn.addEventListener('click', e => {
                e.stopPropagation();
                this.app.executeToggleWishlist(e.currentTarget);
            });
        });

        // Wire Flip Controllers (To Back)
        this.elements.productsGrid.querySelectorAll('.btn-flip').forEach(btn => {
            btn.addEventListener('click', e => {
                e.preventDefault(); e.stopPropagation();
                this.flipCard(btn.dataset.productId, btn.closest('.product-card'));
            });
        });

        // Wire Flip Controllers (To Front)
        this.elements.productsGrid.querySelectorAll('.btn-flip-back').forEach(btn => {
            btn.addEventListener('click', e => {
                e.preventDefault(); e.stopPropagation();
                this.flipBackCard(btn.closest('.product-card'));
            });
        });

        // Wire Comparison Add checkboxes
        this.elements.productsGrid.querySelectorAll('.btn-compare').forEach(btn => {
            btn.addEventListener('click', e => {
                e.stopPropagation();
                this.app.comparison.handleToggle(parseInt(btn.dataset.productId), btn);
            });
        });

        // Wire Social Media share cards
        this.elements.productsGrid.querySelectorAll('.btn-share').forEach(btn => {
            btn.addEventListener('click', e => {
                e.stopPropagation();
                if (typeof window.openShareModal === 'function') {
                    window.openShareModal(JSON.parse(btn.dataset.share));
                }
            });
        });
    }

    renderPaginationUI(pagination, stateRef) {
        if (!this.elements.pagination) return;
        if (!pagination || pagination.last_page <= 1) {
            this.elements.pagination.innerHTML = '';
            return;
        }

        const { current_page, last_page } = pagination;
        let html = `<button ${current_page === 1 ? 'disabled' : ''} data-page="${current_page - 1}">Previous</button>`;

        const startPage = Math.max(1, current_page - 2);
        const endPage = Math.min(last_page, current_page + 2);

        if (startPage > 1) {
            html += `<button data-page="1">1</button>`;
            if (startPage > 2) html += `<span>...</span>`;
        }

        for (let i = startPage; i <= endPage; i++) {
            html += `<button class="${i === current_page ? 'active' : ''}" data-page="${i}">${i}</button>`;
        }

        if (endPage < last_page) {
            if (endPage < last_page - 1) html += `<span>...</span>`;
            html += `<button data-page="${last_page}">${last_page}</button>`;
        }

        html += `<button ${current_page === last_page ? 'disabled' : ''} data-page="${current_page + 1}">Next</button>`;
        this.elements.pagination.innerHTML = html;

        this.elements.pagination.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', () => {
                this.app.stateManager.state.currentPage = parseInt(btn.dataset.page);
                this.app.stateManager.updateURL();
                this.app.fetchAndRender();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });
    }
}

// Sidebar Forms and Filtering Interactive Controls Handler
class FilterControlsUI {
    constructor(app) {
        this.app = app;

        this.inputs = {
            searchInput: document.getElementById('search-input'),
            searchBtn: document.getElementById('search-btn'),
            minPriceInput: document.getElementById('min-price'),
            maxPriceInput: document.getElementById('max-price'),
            onSaleFilter: document.getElementById('on-sale-filter'),
            applyFiltersBtn: document.getElementById('apply-filters'),
            resetFiltersBtn: document.getElementById('reset-filters'),
            sortSelect: document.getElementById('sort-select'),
            perPageSelect: document.getElementById('per-page-select')
        };

        this.initInputListeners();
        this.initSearchResetButton();
        this.initTabSystem();
        this.initSidebarCollapsibles();
    }

    initInputListeners() {
        const triggerDebouncedUpdate = () => {
            clearTimeout(this.app.stateManager.state.debounceTimer);
            this.app.stateManager.state.debounceTimer = setTimeout(() => {
                this.syncFormInputsToState();
                this.app.stateManager.updateURL();
                this.app.fetchAndRender();
            }, 300);
        };

        if (this.inputs.searchBtn) this.inputs.searchBtn.addEventListener('click', () => this.executeManualSearch());
        if (this.inputs.searchInput) {
            this.inputs.searchInput.addEventListener('keypress', e => { if (e.key === 'Enter') this.executeManualSearch(); });
            this.inputs.searchInput.addEventListener('input', triggerDebouncedUpdate);
        }

        // Checklist mutations binding
        document.querySelectorAll('input[name="category[]"], input[name="brand[]"], input[name^="spec_"], input[name="min_rating"]')
            .forEach(el => el.addEventListener('change', triggerDebouncedUpdate));

        if (this.inputs.minPriceInput) this.inputs.minPriceInput.addEventListener('input', triggerDebouncedUpdate);
        if (this.inputs.maxPriceInput) this.inputs.maxPriceInput.addEventListener('input', triggerDebouncedUpdate);
        if (this.inputs.onSaleFilter) this.inputs.onSaleFilter.addEventListener('change', triggerDebouncedUpdate);

        if (this.inputs.applyFiltersBtn) this.inputs.applyFiltersBtn.addEventListener('click', () => {
            this.syncFormInputsToState();
            this.app.stateManager.updateURL();
            this.app.fetchAndRender();
        });

        if (this.inputs.resetFiltersBtn) this.inputs.resetFiltersBtn.addEventListener('click', () => this.resetAllFiltersUI());

        if (this.inputs.sortSelect) this.inputs.sortSelect.addEventListener('change', () => {
            const [sortBy, sortOrder] = this.inputs.sortSelect.value.split(':');
            this.app.stateManager.state.sortBy = sortBy;
            this.app.stateManager.state.sortOrder = sortOrder;
            this.app.stateManager.state.currentPage = 1;
            this.app.stateManager.updateURL();
            this.app.fetchAndRender();
        });

        if (this.inputs.perPageSelect) this.inputs.perPageSelect.addEventListener('change', () => {
            this.app.stateManager.state.perPage = parseInt(this.inputs.perPageSelect.value);
            this.app.stateManager.state.currentPage = 1;
            this.app.stateManager.updateURL();
            this.app.fetchAndRender();
        });
    }

    syncFormInputsToState() {
        const state = this.app.stateManager.state;
        state.filters.categoryIds = Array.from(document.querySelectorAll('input[name="category[]"]:checked')).map(cb => cb.value);
        state.filters.brandIds = Array.from(document.querySelectorAll('input[name="brand[]"]:checked')).map(cb => cb.value);
        state.filters.specificationIds = Array.from(document.querySelectorAll('[name^="spec_"]:checked')).map(cb => cb.value);

        const selectedRating = document.querySelector('input[name="min_rating"]:checked');
        state.filters.minRating = selectedRating && selectedRating.value ? parseInt(selectedRating.value) : null;

        state.filters.search = this.inputs.searchInput ? this.inputs.searchInput.value.trim() : '';
        state.filters.minPrice = this.inputs.minPriceInput ? this.inputs.minPriceInput.value : '';
        state.filters.maxPrice = this.inputs.maxPriceInput ? this.inputs.maxPriceInput.value : '';
        state.filters.onSale = this.inputs.onSaleFilter ? this.inputs.onSaleFilter.checked : false;
        state.currentPage = 1;
    }

    syncStateToFormUI(state) {
        if (this.inputs.searchInput) this.inputs.searchInput.value = state.filters.search;
        if (this.inputs.minPriceInput) this.inputs.minPriceInput.value = state.filters.minPrice;
        if (this.inputs.maxPriceInput) this.inputs.maxPriceInput.value = state.filters.maxPrice;
        if (this.inputs.onSaleFilter) this.inputs.onSaleFilter.checked = state.filters.onSale;

        if (this.inputs.sortSelect) this.inputs.sortSelect.value = `${state.sortBy}:${state.sortOrder}`;
        if (this.inputs.perPageSelect) this.inputs.perPageSelect.value = state.perPage.toString();

        document.querySelectorAll('input[name="category[]"]').forEach(cb => cb.checked = state.filters.categoryIds.includes(cb.value));
        document.querySelectorAll('input[name="brand[]"]').forEach(cb => cb.checked = state.filters.brandIds.includes(cb.value));
        document.querySelectorAll('[name^="spec_"]').forEach(cb => cb.checked = state.filters.specificationIds.includes(cb.value));

        if (state.filters.minRating) {
            const radio = document.querySelector(`input[name="min_rating"][value="${state.filters.minRating}"]`);
            if (radio) radio.checked = true;
        }

        const resetBtn = document.getElementById('search-reset-btn');
        if (resetBtn && this.inputs.searchInput) {
            resetBtn.style.display = this.inputs.searchInput.value.trim() ? 'block' : 'none';
        }
    }

    executeManualSearch() {
        if (this.inputs.searchInput) {
            this.app.stateManager.state.filters.search = this.inputs.searchInput.value.trim();
        }
        this.app.stateManager.state.currentPage = 1;
        this.syncFormInputsToState();
        this.app.stateManager.updateURL();
        this.app.fetchAndRender();
    }

    initSearchResetButton() {
        const input = this.inputs.searchInput;
        if (!input) return;

        const resetBtn = document.createElement('button');
        resetBtn.type = 'button';
        resetBtn.id = 'search-reset-btn';
        resetBtn.setAttribute('aria-label', 'Clear search');
        resetBtn.innerHTML = `
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        `;
        resetBtn.style.cssText = `
            display: none; position: absolute; right: 2.75rem; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer; color: var(--text-secondary, #64748b);
            padding: 0.25rem; line-height: 0; border-radius: 50%; transition: color 0.15s;
        `;

        resetBtn.addEventListener('mouseenter', () => resetBtn.style.color = 'var(--text-primary, #1e293b)');
        resetBtn.addEventListener('mouseleave', () => resetBtn.style.color = 'var(--text-secondary, #64748b)');

        const wrapper = input.parentElement;
        if (wrapper) {
            wrapper.style.position = 'relative';
            wrapper.appendChild(resetBtn);
        }

        const syncVisibility = () => { resetBtn.style.display = input.value.trim() ? 'block' : 'none'; };
        input.addEventListener('input', syncVisibility);

        resetBtn.addEventListener('click', () => {
            input.value = '';
            this.app.stateManager.state.filters.search = '';
            syncVisibility();
            this.app.stateManager.state.currentPage = 1;
            this.app.stateManager.updateURL();
            this.app.fetchAndRender();
            input.focus();
        });

        syncVisibility();
    }

    initTabSystem() {
        document.querySelectorAll('#product-tabs .tab-btn').forEach(btn => {
            btn.addEventListener('click', () => this.switchTab(btn.dataset.tab));
        });
    }

    switchTab(tab) {
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tab);
        });

        this.app.stateManager.resetFiltersState();
        this.app.stateManager.state.activeTab = tab;

        // Reset visual inputs cleanly
        if (this.inputs.searchInput) this.inputs.searchInput.value = '';
        if (this.inputs.minPriceInput) this.inputs.minPriceInput.value = '';
        if (this.inputs.maxPriceInput) this.inputs.maxPriceInput.value = '';
        if (this.inputs.onSaleFilter) this.inputs.onSaleFilter.checked = false;
        document.querySelectorAll('input[name="category[]"], input[name="brand[]"], input[name^="spec_"], input[name="min_rating"]').forEach(cb => cb.checked = false);

        // Process discrete configurations
        if (tab === 'under25') {
            this.app.stateManager.state.filters.maxPrice = '25';
            if (this.inputs.maxPriceInput) this.inputs.maxPriceInput.value = '25';
        } else if (tab === 'under50') {
            this.app.stateManager.state.filters.maxPrice = '50';
            if (this.inputs.maxPriceInput) this.inputs.maxPriceInput.value = '50';
        } else if (tab === 'under100') {
            this.app.stateManager.state.filters.maxPrice = '100';
            if (this.inputs.maxPriceInput) this.inputs.maxPriceInput.value = '100';
        } else if (tab === 'over50') {
            this.app.stateManager.state.filters.onSale = true;
            this.app.stateManager.state.filters.minDiscountPercent = 50;
            if (this.inputs.onSaleFilter) this.inputs.onSaleFilter.checked = true;
        } else if (tab === 'vouchers') {
            this.app.stateManager.state.filters.hasVoucher = true;
        } else if (tab.startsWith('cat-')) {
            const categoryId = tab.replace('cat-', '');
            this.app.stateManager.state.filters.categoryIds = [categoryId];
            const checkbox = document.querySelector(`input[name="category[]"][value="${categoryId}"]`);
            if (checkbox) checkbox.checked = true;
        }

        this.app.stateManager.state.currentPage = 1;
        this.app.ui.showTabLoading();
        this.app.stateManager.updateURL();
        this.app.fetchAndRender();
    }

    generateSuggestedFilters(products) {
        const suggestions = [];
        const state = this.app.stateManager.state;

        if (!this.persistentFilters && products && products.length > 0) {
            const prices = products.map(p => p.sale_price || p.price).filter(p => p > 0);
            if (prices.length > 0) {
                const maxPrice = Math.max(...prices);
                if (maxPrice > 100) {
                    suggestions.push({ type: 'price', label: `Under ${CURRENCY_SYMBOL}25`, maxPrice: 25 });
                    suggestions.push({ type: 'price', label: `Under ${CURRENCY_SYMBOL}50`, maxPrice: 50 });
                    suggestions.push({ type: 'price', label: `Under ${CURRENCY_SYMBOL}100`, maxPrice: 100 });
                } else if (maxPrice > 50) {
                    suggestions.push({ type: 'price', label: `Under ${CURRENCY_SYMBOL}25`, maxPrice: 25 });
                    suggestions.push({ type: 'price', label: `Under ${CURRENCY_SYMBOL}50`, maxPrice: 50 });
                }
            }

            if (products.some(p => p.discount_percentage >= 50)) {
                suggestions.push({ type: 'discount', label: '50% Off or More', minDiscount: 50 });
            }
            if (products.some(p => p.has_voucher)) {
                suggestions.push({ type: 'voucher', label: 'Has Voucher', hasVoucher: true });
            }

            const brandCounts = {};
            products.forEach(p => {
                const brandName = p.brand?.name ?? '';
                if (brandName) brandCounts[brandName] = (brandCounts[brandName] || 0) + 1;
            });

            Object.entries(brandCounts)
                .sort((a, b) => b[1] - a[1])
                .slice(0, 3)
                .forEach(([brand, count]) => {
                    if (count >= 1) {
                        suggestions.push({ type: 'brand', label: brand, brand: brand });
                    }
                });

            this.persistentFilters = suggestions;
        }

        const hasActiveQuickFilter = state.activeSuggestedFilters.size > 0;
        if (suggestions.length > 0) {
            state.lastSuggestions = suggestions;
        }

        const toRender = this.persistentFilters || [];

        const container = document.getElementById('suggested-filters-list');
        const section = document.getElementById('suggested-filters');

        if (!container || !section) return;

        if (toRender.length > 0) {
            const incoming = toRender.map(s => JSON.stringify(s));
            const existing = Array.from(container.querySelectorAll('.suggested-filter-chip')).map(btn => btn.dataset.value);
            const sameChips = incoming.length === existing.length && incoming.every((v, i) => v === existing[i]);

            if (!sameChips) {
                container.innerHTML = toRender.map(s => `
                    <button class="suggested-filter-chip" data-type="${s.type}" data-value='${JSON.stringify(s)}' onclick="window.applySuggestedFilter(this)">
                        ${s.label}
                    </button>
                `).join('');
            }

            container.querySelectorAll('.suggested-filter-chip').forEach(btn => {
                btn.classList.toggle('active', state.activeSuggestedFilters.has(btn.dataset.value));
            });

            section.style.display = 'block';
        } else {
            section.style.display = 'none';
        }
    }

    applySuggestedFilter(btn) {
        const filter = JSON.parse(btn.dataset.value);
        const key = JSON.stringify(filter);
        const state = this.app.stateManager.state;

        const isAlreadyActive = state.activeSuggestedFilters.has(key);
        state.activeSuggestedFilters.clear();

        // Standard operational filter restorations
        state.filters.maxPrice = '';
        if (this.inputs.maxPriceInput) this.inputs.maxPriceInput.value = '';
        state.filters.onSale = false;
        if (this.inputs.onSaleFilter) this.inputs.onSaleFilter.checked = false;
        delete state.filters.minDiscountPercent;
        state.filters.hasVoucher = false;

        if (!isAlreadyActive) {
            state.activeSuggestedFilters.add(key);

            if (filter.type === 'price') {
                state.filters.maxPrice = filter.maxPrice.toString();
                if (this.inputs.maxPriceInput) this.inputs.maxPriceInput.value = filter.maxPrice;
            } else if (filter.type === 'discount') {
                state.filters.minDiscountPercent = filter.minDiscount;
                state.filters.onSale = true;
                if (this.inputs.onSaleFilter) this.inputs.onSaleFilter.checked = true;
            } else if (filter.type === 'voucher') {
                state.filters.hasVoucher = true;
            } else if (filter.type === 'brand') {
                state.filters.brandIds = [];
                document.querySelectorAll('input[name="brand[]"]').forEach(cb => cb.checked = false);

                const checkbox = Array.from(document.querySelectorAll('input[name="brand[]"]'))
                    .find(cb => cb.nextElementSibling?.textContent?.trim() === filter.brand);
                if (checkbox) {
                    checkbox.checked = true;
                    state.filters.brandIds.push(checkbox.value);
                }
            }
        }

        state.currentPage = 1;
        this.app.stateManager.updateURL();
        this.app.fetchAndRender();
    }

    resetAllFiltersUI() {
        this.app.stateManager.resetFiltersState();

        if (this.inputs.searchInput) this.inputs.searchInput.value = '';
        if (this.inputs.minPriceInput) this.inputs.minPriceInput.value = '';
        if (this.inputs.maxPriceInput) this.inputs.maxPriceInput.value = '';
        if (this.inputs.onSaleFilter) this.inputs.onSaleFilter.checked = false;
        document.querySelectorAll('input[name="category[]"], input[name="brand[]"], input[name^="spec_"]').forEach(cb => cb.checked = false);

        this.app.stateManager.updateURL();
        this.app.fetchAndRender();
    }

    initSidebarCollapsibles() {
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('show-more-btn')) {
                const btn = e.target;
                const filterType = btn.dataset.filter;
                const isExpanded = btn.classList.contains('expanded');

                if (isExpanded) {
                    this.collapsFilterList(filterType);
                    btn.classList.remove('expanded');
                    btn.textContent = 'Show More';
                } else {
                    this.expandFilterList(filterType);
                    btn.classList.add('expanded');
                    btn.textContent = 'Show Less';
                }
            }
        });

        // Auto-restore storage preferences
        ['search', 'categories', 'brands', 'price', 'sale'].forEach(sectionName => {
            const isOpen = localStorage.getItem(`sidebar-${sectionName}`) !== 'false';
            if (!isOpen) {
                this.toggleSectionElement(sectionName);
            }
        });
    }

    toggleSectionElement(sectionName) {
        const section = document.querySelector(`[data-section="${sectionName}"]`);
        if (!section) return;
        const content = section.querySelector('.section-content');
        const chevron = section.querySelector('.chevron');

        if (content) content.classList.toggle('open');
        if (chevron) chevron.classList.toggle('rotated');

        if (content) {
            localStorage.setItem(`sidebar-${sectionName}`, content.classList.contains('open'));
        }
    }

    expandFilterList(filterType) {
        const dataElement = document.getElementById(`all-${filterType === 'category' ? 'categories' : 'brands'}`);
        if (!dataElement) return;
        const allItems = JSON.parse(dataElement.textContent);
        const listElement = document.getElementById(`${filterType}-list`);

        listElement.innerHTML = allItems.map(item => `
            <label class="filter-checkbox-label">
                <input type="checkbox" class="filter-checkbox" name="${filterType}[]" value="${item.id}">
                <span class="filter-name">${ProductUtils.escapeHtml(item.name)}</span>
                <span class="filter-count">${item.product_count || 0}</span>
            </label>
        `).join('');

        this.rebindListCheckboxes(listElement);
    }

    collapsFilterList(filterType) {
        const dataElement = document.getElementById(`all-${filterType === 'category' ? 'categories' : 'brands'}`);
        if (!dataElement) return;
        const allItems = JSON.parse(dataElement.textContent);
        const listElement = document.getElementById(`${filterType}-list`);

        listElement.innerHTML = allItems.slice(0, 5).map(item => `
            <label class="filter-checkbox-label">
                <input type="checkbox" class="filter-checkbox" name="${filterType}[]" value="${item.id}">
                <span class="filter-name">${ProductUtils.escapeHtml(item.name)}</span>
                <span class="filter-count">${item.product_count || 0}</span>
            </label>
        `).join('');

        this.rebindListCheckboxes(listElement);
    }

    rebindListCheckboxes(container) {
        const triggerDebouncedUpdate = () => {
            clearTimeout(this.app.stateManager.state.debounceTimer);
            this.app.stateManager.state.debounceTimer = setTimeout(() => {
                this.syncFormInputsToState();
                this.app.stateManager.updateURL();
                this.app.fetchAndRender();
            }, 300);
        };
        container.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            cb.checked = this.app.stateManager.state.filters[cb.name === 'category[]' ? 'categoryIds' : 'brandIds'].includes(cb.value);
            cb.addEventListener('change', triggerDebouncedUpdate);
        });
    }
}

// System Orchestrator Core Component Connection Pipeline
class ProductApp {
    constructor() {
        this.stateManager = new ProductStateManager();
        this.ui = new ProductGridUI(this);
        this.controls = new FilterControlsUI(this);
        this.comparison = new ComparisonManager(this);

        this.stateManager.onChange((state, reason) => {
            if (reason === 'url_loaded' || reason === 'initial_seeded') {
                this.controls.syncStateToFormUI(state);
            }
            this.ui.syncStateDisplays(state);
        });

        this.initGlobalEvents();
    }

    initGlobalEvents() {
        window.addEventListener('popstate', () => {
            this.stateManager.loadFromURL();
            this.fetchAndRender();
        });

        // Initialize App Lifecycle
        this.stateManager.loadFromURL();
        this.stateManager.seedInitialState();
        this.fetchAndRender();
    }

    async fetchAndRender() {
        this.ui.showLoading();
        try {
            const data = await ProductApiService.fetchProducts(this.stateManager.state);

            this.ui.renderProductsGrid(data.data, this.stateManager.state);
            this.ui.renderPaginationUI(data.pagination, this.stateManager.state);
            this.ui.updateResultsCount(data.pagination.total);
            this.controls.generateSuggestedFilters(data.data);
        } catch (error) {
            console.error('Critical operational failure fetching catalog data:', error);
            this.ui.showToast('An error occurred while loading products', 'error');
            this.ui.showEmptyState();
        } finally {
            this.ui.hideLoading();
            this.ui.hideTabLoading();
            if (typeof window.refreshRecommendations === 'function') {
                window.refreshRecommendations();
            }
        }
    }

    async executeAddToCart(btnElement) {
        const productId = btnElement.dataset.productId;
        btnElement.disabled = true;
        const originalText = btnElement.textContent;
        btnElement.textContent = 'Adding...';

        try {
            const data = await ProductApiService.addToCart(productId);
            if (data.success) {
                this.ui.showToast(data.message, 'success');
                this.stateManager.state.cartCount = data.count;
                this.stateManager.state.cartProductIds.add(Number(productId));

                // Toggle active state on all dynamic matching buttons
                document.querySelectorAll(`.btn-add-to-cart[data-product-id="${productId}"]`).forEach(b => b.classList.add('active'));
                this.stateManager.notify('cart_updated');
                await ProductApiService.recordBoostClick(productId, 'listing');
            } else {
                this.ui.showToast(data.message, 'error');
            }
        } catch (err) {
            console.error('Add to cart operation error:', err);
            this.ui.showToast('Failed to add item to cart', 'error');
        } finally {
            btnElement.disabled = false;
            btnElement.textContent = originalText;
        }
    }

    async executeToggleWishlist(btnElement) {
        const productId = btnElement.dataset.productId;
        const isInWishlist = btnElement.classList.contains('active');
        btnElement.disabled = true;

        try {
            const data = await ProductApiService.toggleWishlist(productId, isInWishlist);
            if (data.success) {
                this.ui.showToast(data.message, 'success');
                this.stateManager.state.wishlistCount = data.count;

                const numericId = parseInt(productId, 10);
                if (isInWishlist) {
                    this.stateManager.state.wishlistProductIds.delete(numericId);
                    document.querySelectorAll(`.btn-wishlist[data-product-id="${productId}"]`).forEach(b => b.classList.remove('active'));
                } else {
                    this.stateManager.state.wishlistProductIds.add(numericId);
                    document.querySelectorAll(`.btn-wishlist[data-product-id="${productId}"]`).forEach(b => b.classList.add('active'));
                }
                this.stateManager.notify('wishlist_updated');
            } else {
                this.ui.showToast(data.message, 'error');
            }
        } catch (err) {
            console.error('Wishlist modification error:', err);
            this.ui.showToast('An error occurred updating wishlist tracking', 'error');
        } finally {
            btnElement.disabled = false;
        }
    }

    async runCountsEndpointSync() {
        try {
            const { cartData, wishlistData } = await ProductApiService.fetchCountsSync();
            this.stateManager.state.cartCount = cartData.count || 0;
            this.stateManager.state.wishlistCount = wishlistData.count || 0;

            if (Array.isArray(wishlistData.product_ids)) {
                this.stateManager.state.wishlistProductIds = new Set(wishlistData.product_ids);
            }
            this.stateManager.notify('counts_synced');
        } catch (err) {
            console.error('Count synchronisation system issue:', err);
        }
    }
}

// global initialization context hooks
document.addEventListener('DOMContentLoaded', () => {
    // Instantiate Core Singleton orchestrator to window root context scope
    window.AppInstance = new ProductApp();

    // Map Global Proxy Interface hooks explicitly back to matching layout elements
    window.switchTab = (tabName) => window.AppInstance.controls.switchTab(tabName);
    window.showAllMerchants = (e, merchants) => window.AppInstance.ui.showAllMerchants(e, merchants);
    window.toggleReview = (btnElement) => window.AppInstance.ui.toggleReview(btnElement);
    window.compareProducts = () => window.AppInstance.comparison.compareProducts();
    window.applySuggestedFilter = (btnElement) => window.AppInstance.controls.applySuggestedFilter(btnElement);
    window.toggleSection = (sectionName) => window.AppInstance.controls.toggleSectionElement(sectionName);
    window.updateCounts = () => window.AppInstance.runCountsEndpointSync();

    document.addEventListener('category:select', (e) => {
        const app = window.AppInstance;
        if (!app) return;

        const categoryId = e.detail?.categoryId;

        // 1. Update the underlying application state
        if (categoryId) {
            // Store it as a string to match checkbox values in your filters array
            app.stateManager.state.filters.categoryIds = [String(categoryId)];
            app.stateManager.state.activeTab = `cat-${categoryId}`;
        } else {
            // Handle clearing/deselection gracefully
            app.stateManager.state.filters.categoryIds = [];
            app.stateManager.state.activeTab = 'all';
        }

        // Reset page back to 1 since the search results subset is changing
        app.stateManager.state.currentPage = 1;

        // 2. Sync changes back up to sidebar visual checkboxes
        document.querySelectorAll('input[name="category[]"]').forEach(cb => {
            cb.checked = app.stateManager.state.filters.categoryIds.includes(cb.value);
        });

        // 3. Re-serialize state directly out to the browser window URL string
        //app.stateManager.updateURL();

        // 4. Trigger the AJAX execution pipeline to redraw the items grid
        app.fetchAndRender();
    });
});