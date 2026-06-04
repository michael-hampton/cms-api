/**
 * Deals Page — class/state-based architecture.
 * Mirrors products.js structure exactly; differences are:
 *   - API endpoint: /api/${SITE}/deals/filtered
 *   - Product data shape uses product_id, original_price, title/name, final_price
 */

// ─── Utilities ───────────────────────────────────────────────────────────────

class DealsUtils {
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
        if (quantity === 0) return { class: 'out-of-stock', text: 'Out of Stock' };
        if (quantity < 10)  return { class: 'low-stock',   text: `Only ${quantity} left in stock` };
        return { class: 'in-stock', text: 'In Stock' };
    }

    static generatePriceChartSVG(priceHistory) {
        if (!priceHistory || priceHistory.length < 2) return '';
        const prices    = priceHistory.map(p => p.price);
        const minPrice  = Math.min(...prices);
        const maxPrice  = Math.max(...prices);
        const priceRange = maxPrice - minPrice || 1;
        const points = priceHistory.map((item, index) => {
            const x = (index / (priceHistory.length - 1)) * 100;
            const y = 40 - ((item.price - minPrice) / priceRange) * 35;
            return `${x},${y}`;
        }).join(' ');
        return `<polyline points="${points}" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>`;
    }
}

// ─── State Manager ───────────────────────────────────────────────────────────

class DealsStateManager {
    constructor() {
        this.listeners = [];
        this.state = {
            currentPage:  1,
            perPage:      12,
            sortBy:       'created_at',
            sortOrder:    'desc',
            activeTab:    'all',
            filters: {
                search:           '',
                categoryIds:      [],
                brandIds:         [],
                specificationIds: [],
                minPrice:         '',
                maxPrice:         '',
                onSale:           false,
                minRating:        null,
                minDiscount:      null,
                hasVoucher:       false,
                regionSetIds:     [],
            },
            activeSuggestedFilters: new Set(),
            allDiscoveredFilters:   new Map(),
            cartCount:         0,
            wishlistCount:     0,
            wishlistProductIds: new Set(),
            cartProductIds:    new Set(),
            debounceTimer:     null,
        };
    }

    onChange(callback) { this.listeners.push(callback); }

    notify(reason = 'state_changed') {
        this.listeners.forEach(cb => cb(this.state, reason));
    }

    seedInitialState() {
        const d = window.INITIAL_DATA;
        if (!d) return;
        if (typeof d.cartCount === 'number')    this.state.cartCount = d.cartCount;
        if (Array.isArray(d.cartProductIds))    this.state.cartProductIds = new Set(d.cartProductIds.map(Number));
        if (typeof d.wishlistCount === 'number') this.state.wishlistCount = d.wishlistCount;
        if (Array.isArray(d.wishlistProductIds)) this.state.wishlistProductIds = new Set(d.wishlistProductIds.map(Number));
        if (d.selectedRegionSetId)              this.state.filters.regionSetIds = [d.selectedRegionSetId];
        this.notify('initial_seeded');
    }

    loadFromURL() {
        const p = new URLSearchParams(window.location.search);
        this.state.currentPage  = parseInt(p.get('page'))     || 1;
        this.state.perPage      = parseInt(p.get('per_page')) || 12;
        this.state.sortBy       = p.get('sort_by')    || 'created_at';
        this.state.sortOrder    = p.get('sort_order') || 'desc';
        this.state.activeTab    = p.get('tab')        || 'all';
        this.state.filters.search           = p.get('q')           || '';
        this.state.filters.categoryIds      = p.get('category_ids') ? p.get('category_ids').split(',') : [];
        this.state.filters.brandIds         = p.get('brand_ids')    ? p.get('brand_ids').split(',')    : [];
        this.state.filters.specificationIds = p.get('spec_ids')     ? p.get('spec_ids').split(',')     : [];
        this.state.filters.minPrice         = p.get('min_price')    || '';
        this.state.filters.maxPrice         = p.get('max_price')    || '';
        this.state.filters.onSale           = p.get('on_sale') === '1';
        this.state.filters.minRating        = p.get('min_rating')   ? parseInt(p.get('min_rating'))   : null;
        this.state.filters.minDiscount      = p.get('min_discount') ? parseInt(p.get('min_discount')) : null;
        this.state.filters.hasVoucher       = p.get('has_voucher') === '1';
        this.state.filters.regionSetIds     = p.get('region_set_ids')
            ? p.get('region_set_ids').split(',').map(Number)
            : [];
        this.notify('url_loaded');
    }

    updateURL() {
        const p = new URLSearchParams();
        if (this.state.activeTab && this.state.activeTab !== 'all') p.set('tab', this.state.activeTab);
        if (this.state.currentPage > 1)   p.set('page',      this.state.currentPage);
        if (this.state.perPage !== 12)    p.set('per_page',  this.state.perPage);
        if (this.state.sortBy !== 'created_at' || this.state.sortOrder !== 'desc') {
            p.set('sort_by',    this.state.sortBy);
            p.set('sort_order', this.state.sortOrder);
        }
        if (this.state.filters.search)               p.set('q',              this.state.filters.search);
        if (this.state.filters.categoryIds.length)   p.set('category_ids',   this.state.filters.categoryIds.join(','));
        if (this.state.filters.brandIds.length)      p.set('brand_ids',      this.state.filters.brandIds.join(','));
        if (this.state.filters.specificationIds.length) p.set('spec_ids',    this.state.filters.specificationIds.join(','));
        if (this.state.filters.minPrice)             p.set('min_price',      this.state.filters.minPrice);
        if (this.state.filters.maxPrice)             p.set('max_price',      this.state.filters.maxPrice);
        if (this.state.filters.onSale)               p.set('on_sale',        '1');
        if (this.state.filters.minRating)            p.set('min_rating',     this.state.filters.minRating);
        if (this.state.filters.minDiscount)          p.set('min_discount',   this.state.filters.minDiscount);
        if (this.state.filters.hasVoucher)           p.set('has_voucher',    '1');
        if (this.state.filters.regionSetIds.length)  p.set('region_set_ids', this.state.filters.regionSetIds.join(','));
        window.history.pushState({}, '', `${window.location.pathname}${p.toString() ? '?' + p.toString() : ''}`);
    }

    resetFiltersState() {
        this.state.filters = {
            search: '', categoryIds: [], brandIds: [], specificationIds: [],
            minPrice: '', maxPrice: '', onSale: false, minRating: null,
            minDiscount: null, hasVoucher: false, regionSetIds: [],
        };
        this.state.activeSuggestedFilters.clear();
        this.state.allDiscoveredFilters.clear();
        this.state.currentPage = 1;
    }
}

// ─── API Service ─────────────────────────────────────────────────────────────

class DealsApiService {
    static async fetchDeals(state) {
        const p = new URLSearchParams({
            page:         state.currentPage,
            per_page:     state.perPage,
            sort_by:      state.sortBy,
            sort_order:   state.sortOrder,
            q:            state.filters.search,
            category_ids: state.filters.categoryIds.join(','),
            brand_ids:    state.filters.brandIds.join(','),
            spec_ids:     state.filters.specificationIds.join(','),
            min_price:    state.filters.minPrice,
            max_price:    state.filters.maxPrice,
            on_sale:      state.filters.onSale ? '1' : '',
        });
        if (state.filters.minRating)           p.set('min_rating',     state.filters.minRating);
        if (state.filters.minDiscount)         p.set('min_discount',   state.filters.minDiscount);
        if (state.filters.hasVoucher)          p.set('has_voucher',    '1');
        if (state.filters.regionSetIds.length) p.set('region_set_ids', state.filters.regionSetIds.join(','));

        const response = await fetch(`/api/${SITE}/deals/filtered?${p}`);
        if (!response.ok) throw new Error('Failed to load deals');
        return response.json();
    }

    static async fetchCardBackData(productId) {
        const response = await fetch(`/api/${SITE}/product-list/${productId}/details`);
        if (!response.ok) throw new Error('Failed to load details');
        return response.json();
    }

    static async addToCart(productId) {
        const response = await fetch(`/api/${SITE}/cart/add`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ product_id: productId, quantity: 1 }),
        });
        return response.json();
    }

    static async toggleWishlist(productId, isInWishlist) {
        const url = isInWishlist
            ? `/api/${SITE}/wishlist/remove/${productId}`
            : `/api/${SITE}/wishlist/add`;
        const response = await fetch(url, {
            method:  isInWishlist ? 'DELETE' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    isInWishlist ? null : JSON.stringify({ product_id: productId }),
        });
        return response.json();
    }

    static async recordBoostClick(productId, context) {
        return fetch(`/api/${SITE}/boost/click`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ product_id: productId, context }),
        }).catch(err => console.error('Boost click error:', err));
    }

    static async fetchCountsSync() {
        const [cartResp, wishlistResp] = await Promise.all([
            fetch(`/api/${SITE}/cart`),
            fetch(`/api/${SITE}/wishlist`),
        ]);
        return {
            cartData:     await cartResp.json(),
            wishlistData: await wishlistResp.json(),
        };
    }
}

// ─── Comparison Manager ───────────────────────────────────────────────────────

class DealsComparisonManager {
    constructor(app) {
        this.app        = app;
        this.products   = new Set();
        this.maxProducts = 4;
        this.barElement = document.getElementById('comparison-bar');
    }

    handleToggle(productId, btnElement) {
        if (this.products.has(productId)) {
            this.products.delete(productId);
            document.querySelectorAll(`.btn-compare[data-product-id="${productId}"]`)
                .forEach(b => b.classList.remove('active'));
        } else {
            if (this.products.size >= this.maxProducts) {
                this.app.ui.showToast('Maximum 4 products can be compared', 'error');
                return;
            }
            this.products.add(productId);
            document.querySelectorAll(`.btn-compare[data-product-id="${productId}"]`)
                .forEach(b => b.classList.add('active'));
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
        window.location.href = `/${SITE}/compare?ids=${Array.from(this.products).join(',')}`;
    }
}

// ─── Grid UI ─────────────────────────────────────────────────────────────────

class DealsGridUI {
    constructor(app) {
        this.app = app;
        this.currentlyFlippedCard = null;
        this.elements = {
            dealsGrid:    document.getElementById('deals-grid'),
            loadingState: document.getElementById('loading-state'),
            tabLoading:   document.getElementById('tab-loading'),
            emptyState:   document.getElementById('empty-state'),
            pagination:   document.getElementById('pagination'),
            resultsCount: document.getElementById('results-count'),
            cartCount:    document.getElementById('cart-count'),
            wishlistCount: document.getElementById('wishlist-count'),
            toast:        document.getElementById('toast'),
        };
        this.initDelegatedEvents();
    }

    initDelegatedEvents() {
        if (this.elements.dealsGrid) {
            this.elements.dealsGrid.addEventListener('click', e => {
                if (e.target.closest('.btn-compare, .btn-flip, .btn-wishlist, .btn-add-to-cart, .btn-show-review, .btn-share, .product-card-back, .merchant-badge, .merchant-count')) return;
                const card = e.target.closest('.product-card');
                if (card && window.productModal) window.productModal.open(card.dataset.productId);
            });
        }
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && this.currentlyFlippedCard) this.flipBackCard(this.currentlyFlippedCard);
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
        setTimeout(() => this.elements.toast.classList.remove('show'), 3000);
    }

    showLoading() {
        if (this.elements.loadingState) this.elements.loadingState.style.display = 'block';
        if (this.elements.dealsGrid)    this.elements.dealsGrid.style.display    = 'none';
        if (this.elements.emptyState)   this.elements.emptyState.style.display   = 'none';
    }

    hideLoading() {
        if (this.elements.loadingState) this.elements.loadingState.style.display = 'none';
        if (this.elements.dealsGrid)    this.elements.dealsGrid.style.display    = 'grid';
    }

    showTabLoading() { if (this.elements.tabLoading) this.elements.tabLoading.style.display = 'block'; }
    hideTabLoading() { if (this.elements.tabLoading) this.elements.tabLoading.style.display = 'none'; }

    showEmptyState() {
        if (this.elements.dealsGrid)  { this.elements.dealsGrid.style.display = 'none'; this.elements.dealsGrid.innerHTML = ''; }
        if (this.elements.emptyState)   this.elements.emptyState.style.display = 'block';
        if (this.elements.pagination)   this.elements.pagination.innerHTML = '';
    }

    updateResultsCount(total) {
        if (this.elements.resultsCount)
            this.elements.resultsCount.textContent = `${total} product${total !== 1 ? 's' : ''}`;
    }

    renderStars(rating) {
        const full = Math.floor(rating);
        let html = '<div class="rating-stars">';
        for (let i = 0; i < 5; i++) {
            html += `<svg class="rating-star ${i < full ? 'filled' : 'empty'}" viewBox="0 0 24 24">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
            </svg>`;
        }
        return html + '</div>';
    }

    renderMerchants(merchants) {
        if (!merchants || merchants.length === 0) return '';
        if (merchants.length === 1) {
            const m    = merchants[0];
            const name = m.merchant?.name || m.name || 'Unknown';
            const url  = m.url || '#';
            return `<div class="product-merchants">
                <a href="${DealsUtils.escapeHtml(url)}" target="_blank" rel="noopener noreferrer"
                   class="merchant-badge" onclick="event.stopPropagation()">
                    ${DealsUtils.escapeHtml(name)}
                    ${m.discount_percentage > 0 ? ` -${m.discount_percentage}%` : ''}
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left:4px">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                        <polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line>
                    </svg>
                </a>
            </div>`;
        }
        const best = merchants.reduce((b, c) => {
            return (c.sale_price > 0 ? c.sale_price : c.price) < (b.sale_price > 0 ? b.sale_price : b.price) ? c : b;
        }, merchants[0]);
        const name = best.merchant?.name || best.name || 'Unknown';
        const url  = best.url || '#';
        return `<div class="product-merchants">
            <a href="${DealsUtils.escapeHtml(url)}" target="_blank" rel="noopener noreferrer"
               class="merchant-badge best-price" onclick="event.stopPropagation()">
                ${DealsUtils.escapeHtml(name)}
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left:4px">
                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                    <polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line>
                </svg>
            </a>
            <span class="merchant-count" onclick="event.stopPropagation(); window.showAllMerchants(event, ${JSON.stringify(merchants).replace(/"/g, '&quot;')})">+${merchants.length - 1} more</span>
        </div>`;
    }

    renderTopReview(review) {
        if (!review || !Object.keys(review).length) return '';
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
                ${review.title ? `<div class="review-title">${DealsUtils.escapeHtml(review.title)}</div>` : ''}
                <div class="review-comment">${DealsUtils.escapeHtml(review.comment)}</div>
                <div class="review-author">by ${DealsUtils.escapeHtml(review.author_name)}</div>
            </div>`;
    }

    toggleReview(btn) {
        const section    = btn.nextElementSibling;
        const isExpanded = section.classList.contains('show');
        section.classList.toggle('show');
        btn.classList.toggle('expanded');
        btn.querySelector('span').textContent = isExpanded ? 'Top Review' : 'Hide Review';
    }

    flipCard(productId, cardElement) {
        if (this.currentlyFlippedCard === cardElement) {
            cardElement.classList.remove('flipped');
            this.currentlyFlippedCard = null;
            document.body.classList.remove('card-flipped');
            return;
        }
        if (this.currentlyFlippedCard) this.currentlyFlippedCard.classList.remove('flipped');
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
        if (this.currentlyFlippedCard === cardElement) this.currentlyFlippedCard = null;
        document.body.classList.remove('card-flipped');
    }

    async loadCardBackData(productId, cardElement) {
        const backContent = cardElement.querySelector('.card-back-dynamic-content');
        if (!backContent) return;
        try {
            backContent.innerHTML = '<div style="text-align:center;padding:2rem;color:#64748b">Loading details...</div>';
            const data = await DealsApiService.fetchCardBackData(productId);
            if (data.success) {
                this.renderCardBackContent(data.product, backContent, productId);
            } else {
                backContent.innerHTML = '<div style="text-align:center;padding:2rem;color:#ef4444">Failed to load details</div>';
            }
        } catch {
            backContent.innerHTML = '<div style="text-align:center;padding:2rem;color:#ef4444">An error occurred</div>';
        }
    }

    renderCardBackContent(product, container, productId) {
        const { description, stock_quantity, variants, price_history, comparison, specifications, merchants } = product;
        let html = '';

        if (description) {
            const short = description.length > 150 ? description.substring(0, 150) + '...' : description;
            html += `<div class="back-section"><h4 class="back-section-title">Description</h4><p class="product-description">${DealsUtils.escapeHtml(short)}</p></div>`;
        }

        const stock = DealsUtils.getStockStatus(stock_quantity);
        html += `<div class="back-section"><h4 class="back-section-title">Availability</h4>
            <div class="stock-indicator ${stock.class}"><span class="stock-dot"></span><span>${stock.text}</span></div></div>`;

        if (variants?.length) {
            html += `<div class="back-section"><h4 class="back-section-title">Available Options</h4><div class="variants-grid">
                ${variants.map(v => `
                    <div class="variant-option ${v.in_stock ? '' : 'disabled'}" data-variant-id="${v.id}" data-variant-price="${v.final_price}">
                        <div style="font-weight:500">${DealsUtils.escapeHtml(v.name)}</div>
                        ${v.discount_percentage > 0 ? `<div style="font-size:.75rem;color:#059669">-${v.discount_percentage}%</div>` : ''}
                        <div style="font-size:.75rem;color:#64748b">${CURRENCY_SYMBOL}${DealsUtils.formatPrice(v.final_price)}</div>
                    </div>`).join('')}
            </div></div>`;
        }

        if (price_history?.length) {
            const prices       = price_history.map(p => p.price);
            const currentPrice = prices[prices.length - 1];
            const lowestPrice  = Math.min(...prices);
            const highestPrice = Math.max(...prices);
            const savingsPct   = currentPrice === lowestPrice && highestPrice > lowestPrice
                ? Math.round(((highestPrice - lowestPrice) / highestPrice) * 100) : 0;
            html += `<div class="back-section"><h4 class="back-section-title">Price History (90 Days)</h4>
                <div class="price-chart-container">
                    <div class="price-stats">
                        <div class="price-stat"><div class="price-stat-label">Current</div><div class="price-stat-value current">${CURRENCY_SYMBOL}${DealsUtils.formatPrice(currentPrice)}</div></div>
                        <div class="price-stat"><div class="price-stat-label">Lowest</div><div class="price-stat-value low">${CURRENCY_SYMBOL}${DealsUtils.formatPrice(lowestPrice)}</div></div>
                        <div class="price-stat"><div class="price-stat-label">Highest</div><div class="price-stat-value high">${CURRENCY_SYMBOL}${DealsUtils.formatPrice(highestPrice)}</div></div>
                    </div>
                    ${savingsPct > 0 ? `<div style="text-align:center;margin-bottom:.5rem;color:#059669;font-size:.875rem;font-weight:500">💰 Save ${savingsPct}% vs highest price!</div>` : ''}
                    <div class="price-chart"><svg class="price-chart-line" viewBox="0 0 100 40" preserveAspectRatio="none">${DealsUtils.generatePriceChartSVG(price_history)}</svg></div>
                </div></div>`;
        }

        if (specifications?.length) {
            html += `<div class="back-section"><h4 class="back-section-title">Specifications</h4><div class="comparison-section">
                ${specifications.map(s => `
                    <div class="comparison-item">
                        <span class="comparison-label">${DealsUtils.escapeHtml(s.key)}</span>
                        <span class="comparison-value">${DealsUtils.escapeHtml(s.value)}</span>
                    </div>`).join('')}
            </div></div>`;
        }

        if (comparison) {
            html += `<div class="back-section"><h4 class="back-section-title">Price Comparison</h4><div class="comparison-section">
                <div class="comparison-item"><span class="comparison-label">vs. Category Average</span><span class="comparison-badge ${comparison.price_comparison}">${comparison.price_difference}</span></div>
                ${comparison.category_avg_price ? `<div class="comparison-item"><span class="comparison-label">Category Average</span><span class="comparison-value">${CURRENCY_SYMBOL}${comparison.category_avg_price}</span></div>` : ''}
                ${comparison.discount_vs_regular ? `<div class="comparison-item"><span class="comparison-label">Your Savings</span><span class="comparison-badge better">${comparison.discount_vs_regular}</span></div>` : ''}
                ${comparison.products_in_category ? `<div class="comparison-item"><span class="comparison-label">Similar Products</span><span class="comparison-value">${comparison.products_in_category} in category</span></div>` : ''}
            </div></div>`;
        }

        if (merchants?.length > 1) {
            html += `<div class="back-section"><h4 class="back-section-title">Available From</h4><div class="comparison-section">
                ${merchants.slice(0, 3).map(m => `
                    <div class="comparison-item">
                        <span class="comparison-label"><a href="${m.url}" target="_blank" style="color:#2563eb;text-decoration:none">Merchant</a></span>
                        <span class="comparison-value">${CURRENCY_SYMBOL}${DealsUtils.formatPrice(m.sale_price > 0 ? m.sale_price : m.price)}
                            ${m.has_discount ? `<span style="color:#059669;font-size:.75rem;margin-left:.25rem">-${m.discount_percentage}%</span>` : ''}
                        </span>
                    </div>`).join('')}
                ${merchants.length > 3 ? `<div style="text-align:center;margin-top:.5rem;font-size:.875rem;color:#64748b">+${merchants.length - 3} more retailers</div>` : ''}
            </div></div>`;
        }

        container.innerHTML = html;

        if (variants?.length) {
            container.querySelectorAll('.variant-option:not(.disabled)').forEach(opt => {
                opt.addEventListener('click', function () {
                    container.querySelectorAll('.variant-option').forEach(o => o.classList.remove('selected'));
                    this.classList.add('selected');
                });
            });
        }
    }

    // Deal cards use product_id, original_price, title fields
    renderDealsGrid(deals, stateRef) {
        if (!this.elements.dealsGrid) return;
        if (!deals || deals.length === 0) { this.showEmptyState(); return; }

        this.elements.dealsGrid.innerHTML = deals.map(deal => {
            const id              = deal.product_id;
            const name            = deal.title || deal.name || '';
            const isCartActive    = stateRef.cartProductIds.has(Number(id)) ? ' active' : '';
            const isWishlistActive = stateRef.wishlistProductIds.has(Number(id)) ? ' active' : '';
            const isCompareActive = this.app.comparison.products.has(Number(id)) ? ' active' : '';

            return `
            <div class="product-card" data-product-id="${id}">
                <div class="product-card-inner">
                    <div class="product-card-front">
                        <button class="btn-flip" data-product-id="${id}" title="View details">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/>
                            </svg>
                        </button>
                        <button class="btn-share" data-share='${DealsUtils.escapeHtml(JSON.stringify({
                id, name, slug: deal.slug, price: deal.original_price,
                sale_price: deal.sale_price, image: deal.image,
            }))}' title="Share product">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle>
                                <circle cx="18" cy="19" r="3"></circle>
                                <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                                <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                            </svg>
                        </button>
                        <a href="/${SITE}/shop/details/${deal.slug}" class="product-image">
                            <img src="${deal.image || '/images/placeholder.jpg'}" alt="${DealsUtils.escapeHtml(name)}">
                            ${deal.discount_percentage > 0 ? `<span class="badge-sale">-${deal.discount_percentage}%</span>` : ''}
                            ${deal.is_boosted ? `<span class="badge-sponsored">Sponsored</span>` : ''}
                        </a>
                        <div class="product-content">
                            <h3 class="product-name">
                                <a href="/${SITE}/shop/details/${deal.slug}">${DealsUtils.escapeHtml(name)}</a>
                            </h3>
                            ${deal.average_rating > 0 ? `
                                <div class="product-rating">
                                    <div class="stars-small">${this.renderStars(deal.average_rating)}</div>
                                    <span class="rating-count">(${deal.review_count || 0})</span>
                                </div>` : ''}
                            ${this.renderMerchants(deal.availableMerchants || [])}
                            <div class="product-price">
                                ${deal.sale_price && deal.sale_price < deal.original_price ? `
                                    <span class="price-sale">${CURRENCY_SYMBOL}${DealsUtils.formatPrice(deal.sale_price)}</span>
                                    <span class="price-original">${CURRENCY_SYMBOL}${DealsUtils.formatPrice(deal.original_price)}</span>
                                ` : `<span class="price-current">${CURRENCY_SYMBOL}${DealsUtils.formatPrice(deal.original_price)}</span>`}
                            </div>
                            <div class="product-actions">
                                <button class="btn-compare${isCompareActive}" data-product-id="${id}">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path d="M9 11l3 3L22 4"></path>
                                        <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"></path>
                                    </svg>
                                </button>
                                <button class="btn-add-to-cart${isCartActive}" data-product-id="${id}">Add to Cart</button>
                                <button class="btn-wishlist${isWishlistActive}" data-product-id="${id}">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        ${deal.top_review && Object.keys(deal.top_review).length ? this.renderTopReview(deal.top_review) : ''}
                    </div>
                    <div class="product-card-back">
                        <div class="card-back-header">
                            <h3 class="card-back-title">${DealsUtils.escapeHtml(name)}</h3>
                            <button class="btn-flip-back" data-product-id="${id}" title="Flip back">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </button>
                        </div>
                        <div class="card-back-content"><div class="card-back-dynamic-content"></div></div>
                        <div class="card-back-actions">
                            <button class="btn-compare${isCompareActive}" data-product-id="${id}">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M9 11l3 3L22 4"></path>
                                    <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"></path>
                                </svg>
                            </button>
                            <button class="btn-back-action btn-add-cart-back" data-product-id="${id}">Add to Cart</button>
                            <a href="/${SITE}/shop/details/${deal.slug}" class="btn-back-action btn-view-details">Full Details</a>
                        </div>
                    </div>
                </div>
            </div>`;
        }).join('');

        this.attachInlineCardListeners();
    }

    attachInlineCardListeners() {
        this.elements.dealsGrid.querySelectorAll('.btn-add-to-cart, .btn-add-cart-back').forEach(btn => {
            btn.addEventListener('click', e => { e.stopPropagation(); this.app.executeAddToCart(e.currentTarget); });
        });
        this.elements.dealsGrid.querySelectorAll('.btn-wishlist').forEach(btn => {
            btn.addEventListener('click', e => { e.stopPropagation(); this.app.executeToggleWishlist(e.currentTarget); });
        });
        this.elements.dealsGrid.querySelectorAll('.btn-flip').forEach(btn => {
            btn.addEventListener('click', e => { e.preventDefault(); e.stopPropagation(); this.flipCard(btn.dataset.productId, btn.closest('.product-card')); });
        });
        this.elements.dealsGrid.querySelectorAll('.btn-flip-back').forEach(btn => {
            btn.addEventListener('click', e => { e.preventDefault(); e.stopPropagation(); this.flipBackCard(btn.closest('.product-card')); });
        });
        this.elements.dealsGrid.querySelectorAll('.btn-compare').forEach(btn => {
            btn.addEventListener('click', e => { e.stopPropagation(); this.app.comparison.handleToggle(parseInt(btn.dataset.productId), btn); });
        });
        this.elements.dealsGrid.querySelectorAll('.btn-share').forEach(btn => {
            btn.addEventListener('click', e => { e.stopPropagation(); if (typeof window.openShareModal === 'function') window.openShareModal(JSON.parse(btn.dataset.share)); });
        });
    }

    renderPaginationUI(pagination, stateRef) {
        if (!this.elements.pagination) return;
        if (!pagination || pagination.last_page <= 1) { this.elements.pagination.innerHTML = ''; return; }
        const { current_page, last_page } = pagination;
        let html = `<button ${current_page === 1 ? 'disabled' : ''} data-page="${current_page - 1}">Previous</button>`;
        const start = Math.max(1, current_page - 2);
        const end   = Math.min(last_page, current_page + 2);
        if (start > 1) { html += `<button data-page="1">1</button>`; if (start > 2) html += `<span>...</span>`; }
        for (let i = start; i <= end; i++) html += `<button class="${i === current_page ? 'active' : ''}" data-page="${i}">${i}</button>`;
        if (end < last_page) { if (end < last_page - 1) html += `<span>...</span>`; html += `<button data-page="${last_page}">${last_page}</button>`; }
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

// ─── Filter Controls ──────────────────────────────────────────────────────────

class DealsFilterControlsUI {
    constructor(app) {
        this.app = app;
        this.inputs = {
            searchInput:    document.getElementById('search-input'),
            searchBtn:      document.getElementById('search-btn'),
            minPriceInput:  document.getElementById('min-price'),
            maxPriceInput:  document.getElementById('max-price'),
            onSaleFilter:   document.getElementById('on-sale-filter'),
            applyFiltersBtn: document.getElementById('apply-filters'),
            resetFiltersBtn: document.getElementById('reset-filters'),
            sortSelect:     document.getElementById('sort-select'),
            perPageSelect:  document.getElementById('per-page-select'),
        };
        this.initInputListeners();
        this.initSearchResetButton();
        this.initTabSystem();
        this.initSidebarCollapsibles();
    }

    initInputListeners() {
        const debounced = () => {
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
            this.inputs.searchInput.addEventListener('input', debounced);
        }
        document.querySelectorAll('input[name="category[]"], input[name="brand[]"], input[name^="spec_"], input[name="min_rating"]')
            .forEach(el => el.addEventListener('change', debounced));
        document.querySelectorAll('input[name="region_set"]')
            .forEach(el => el.addEventListener('change', debounced));
        if (this.inputs.minPriceInput)  this.inputs.minPriceInput.addEventListener('input', debounced);
        if (this.inputs.maxPriceInput)  this.inputs.maxPriceInput.addEventListener('input', debounced);
        if (this.inputs.onSaleFilter)   this.inputs.onSaleFilter.addEventListener('change', debounced);
        if (this.inputs.applyFiltersBtn) this.inputs.applyFiltersBtn.addEventListener('click', () => {
            this.syncFormInputsToState();
            this.app.stateManager.updateURL();
            this.app.fetchAndRender();
        });
        if (this.inputs.resetFiltersBtn) this.inputs.resetFiltersBtn.addEventListener('click', () => this.resetAllFiltersUI());
        if (this.inputs.sortSelect) this.inputs.sortSelect.addEventListener('change', () => {
            const [sortBy, sortOrder] = this.inputs.sortSelect.value.split(':');
            this.app.stateManager.state.sortBy    = sortBy;
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
        state.filters.categoryIds      = Array.from(document.querySelectorAll('input[name="category[]"]:checked')).map(cb => cb.value);
        state.filters.brandIds         = Array.from(document.querySelectorAll('input[name="brand[]"]:checked')).map(cb => cb.value);
        state.filters.specificationIds = Array.from(document.querySelectorAll('[name^="spec_"]:checked')).map(cb => cb.value);
        const rating = document.querySelector('input[name="min_rating"]:checked');
        state.filters.minRating  = rating?.value ? parseInt(rating.value) : null;
        state.filters.search     = this.inputs.searchInput?.value.trim() || '';
        state.filters.minPrice   = this.inputs.minPriceInput?.value || '';
        state.filters.maxPrice   = this.inputs.maxPriceInput?.value || '';
        state.filters.onSale     = this.inputs.onSaleFilter?.checked || false;
        const selectedRegion     = document.querySelector('input[name="region_set"]:checked');
        state.filters.regionSetIds = selectedRegion?.value
            ? [parseInt(selectedRegion.dataset.regionId)]
            : [];
        state.currentPage = 1;
    }

    syncStateToFormUI(state) {
        if (this.inputs.searchInput)   this.inputs.searchInput.value   = state.filters.search;
        if (this.inputs.minPriceInput) this.inputs.minPriceInput.value = state.filters.minPrice;
        if (this.inputs.maxPriceInput) this.inputs.maxPriceInput.value = state.filters.maxPrice;
        if (this.inputs.onSaleFilter)  this.inputs.onSaleFilter.checked = state.filters.onSale;
        if (this.inputs.sortSelect)    this.inputs.sortSelect.value    = `${state.sortBy}:${state.sortOrder}`;
        if (this.inputs.perPageSelect) this.inputs.perPageSelect.value = state.perPage.toString();
        document.querySelectorAll('input[name="category[]"]').forEach(cb => cb.checked = state.filters.categoryIds.includes(cb.value));
        document.querySelectorAll('input[name="brand[]"]').forEach(cb => cb.checked = state.filters.brandIds.includes(cb.value));
        document.querySelectorAll('[name^="spec_"]').forEach(cb => cb.checked = state.filters.specificationIds.includes(cb.value));
        if (state.filters.minRating) {
            const radio = document.querySelector(`input[name="min_rating"][value="${state.filters.minRating}"]`);
            if (radio) radio.checked = true;
        }
        const regionSetId = state.filters.regionSetIds[0] ?? null;
        document.querySelectorAll('input[name="region_set"]').forEach(radio => {
            radio.checked = regionSetId
                ? parseInt(radio.dataset.regionId) === regionSetId
                : radio.value === '';
        });
    }

    executeManualSearch() {
        if (this.inputs.searchInput) this.app.stateManager.state.filters.search = this.inputs.searchInput.value.trim();
        this.app.stateManager.state.currentPage = 1;
        this.syncFormInputsToState();
        this.app.stateManager.updateURL();
        this.app.fetchAndRender();
    }

    initSearchResetButton() {
        const input = this.inputs.searchInput;
        if (!input) return;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.id = 'search-reset-btn';
        btn.setAttribute('aria-label', 'Clear search');
        btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>`;
        btn.style.cssText = `display:none;position:absolute;right:2.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--text-secondary,#64748b);padding:.25rem;line-height:0;border-radius:50%;transition:color .15s`;
        btn.addEventListener('mouseenter', () => btn.style.color = 'var(--text-primary,#1e293b)');
        btn.addEventListener('mouseleave', () => btn.style.color = 'var(--text-secondary,#64748b)');
        const wrapper = input.parentElement;
        if (wrapper) { wrapper.style.position = 'relative'; wrapper.appendChild(btn); }
        const syncVis = () => { btn.style.display = input.value.trim() ? 'block' : 'none'; };
        input.addEventListener('input', syncVis);
        btn.addEventListener('click', () => {
            input.value = '';
            this.app.stateManager.state.filters.search = '';
            syncVis();
            this.app.stateManager.state.currentPage = 1;
            this.app.stateManager.updateURL();
            this.app.fetchAndRender();
            input.focus();
        });
        syncVis();
    }

    initTabSystem() {
        document.querySelectorAll('.deals-tabs .tab-btn').forEach(btn => {
            btn.addEventListener('click', () => this.switchTab(btn.dataset.tab));
        });
    }

    switchTab(tab) {
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.toggle('active', btn.dataset.tab === tab));
        this.app.stateManager.resetFiltersState();
        this.app.stateManager.state.activeTab = tab;
        if (this.inputs.searchInput)   this.inputs.searchInput.value   = '';
        if (this.inputs.minPriceInput) this.inputs.minPriceInput.value = '';
        if (this.inputs.maxPriceInput) this.inputs.maxPriceInput.value = '';
        if (this.inputs.onSaleFilter)  this.inputs.onSaleFilter.checked = false;
        document.querySelectorAll('input[name="category[]"], input[name="brand[]"], input[name^="spec_"], input[name="min_rating"]').forEach(cb => cb.checked = false);
        if (tab === 'under25')  { this.app.stateManager.state.filters.maxPrice = '25';  if (this.inputs.maxPriceInput) this.inputs.maxPriceInput.value = '25'; }
        else if (tab === 'under50')  { this.app.stateManager.state.filters.maxPrice = '50';  if (this.inputs.maxPriceInput) this.inputs.maxPriceInput.value = '50'; }
        else if (tab === 'under100') { this.app.stateManager.state.filters.maxPrice = '100'; if (this.inputs.maxPriceInput) this.inputs.maxPriceInput.value = '100'; }
        else if (tab === 'over50')   { this.app.stateManager.state.filters.onSale = true; this.app.stateManager.state.filters.minDiscountPercent = 50; if (this.inputs.onSaleFilter) this.inputs.onSaleFilter.checked = true; }
        else if (tab === 'vouchers') { this.app.stateManager.state.filters.hasVoucher = true; }
        else if (tab.startsWith('cat-')) {
            const catId = tab.replace('cat-', '');
            this.app.stateManager.state.filters.categoryIds = [catId];
            const cb = document.querySelector(`input[name="category[]"][value="${catId}"]`);
            if (cb) cb.checked = true;
        }
        this.app.stateManager.state.currentPage = 1;
        this.app.ui.showTabLoading();
        this.app.stateManager.updateURL();
        this.app.fetchAndRender();
    }

    generateSuggestedFilters(deals) {
        const container = document.getElementById('suggested-filters-list');
        const section   = document.getElementById('suggested-filters');
        if (!container || !section) return;
        const state = this.app.stateManager.state;

        if (deals?.length) {
            // deals use original_price / sale_price
            const prices   = deals.map(d => d.sale_price || d.original_price).filter(p => p > 0);
            const maxPrice = Math.max(...prices);
            const potential = [
                { type: 'price',    label: `Under ${CURRENCY_SYMBOL}25`,  maxPrice: 25 },
                { type: 'price',    label: `Under ${CURRENCY_SYMBOL}50`,  maxPrice: 50 },
                { type: 'price',    label: `Under ${CURRENCY_SYMBOL}100`, maxPrice: 100 },
                { type: 'discount', label: '50% Off or More', minDiscount: 50 },
                { type: 'voucher',  label: 'Has Voucher',     hasVoucher: true },
            ];
            potential.forEach(s => {
                const meets =
                    (s.type === 'price'    && maxPrice > s.maxPrice) ||
                    (s.type === 'discount' && deals.some(d => d.discount_percentage >= s.minDiscount)) ||
                    (s.type === 'voucher'  && deals.some(d => d.has_voucher));
                if (meets) state.allDiscoveredFilters.set(s.label, s);
            });
            const brandCounts = {};
            deals.forEach(d => { const n = d.brand; if (n) brandCounts[n] = (brandCounts[n] || 0) + 1; });
            Object.entries(brandCounts).sort((a, b) => b[1] - a[1]).slice(0, 3).forEach(([brand]) => {
                state.allDiscoveredFilters.set(`brand-${brand}`, { type: 'brand', label: brand, brand });
            });
        }

        if (!state.allDiscoveredFilters.size && !state.activeSuggestedFilters.size) { section.style.display = 'none'; return; }

        container.innerHTML = Array.from(state.allDiscoveredFilters.values()).map(s => {
            const key      = JSON.stringify(s);
            const isActive = state.activeSuggestedFilters.has(key);
            return `<button class="suggested-filter-chip ${isActive ? 'active' : ''}" data-type="${s.type}" data-value='${key}' onclick="window.applySuggestedFilter(this)">${s.label}</button>`;
        }).join('');
        section.style.display = 'block';
    }

    applySuggestedFilter(btn) {
        const filter = JSON.parse(btn.dataset.value);
        const key    = JSON.stringify(filter);
        const state  = this.app.stateManager.state;
        const wasActive = state.activeSuggestedFilters.has(key);
        state.activeSuggestedFilters.clear();
        state.filters.maxPrice = '';
        if (this.inputs.maxPriceInput) this.inputs.maxPriceInput.value = '';
        state.filters.onSale = false;
        if (this.inputs.onSaleFilter) this.inputs.onSaleFilter.checked = false;
        delete state.filters.minDiscountPercent;
        state.filters.hasVoucher = false;
        if (!wasActive) {
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
                const cb = Array.from(document.querySelectorAll('input[name="brand[]"]'))
                    .find(cb => cb.nextElementSibling?.textContent?.trim() === filter.brand);
                if (cb) { cb.checked = true; state.filters.brandIds.push(cb.value); }
            }
        }
        state.currentPage = 1;
        this.app.stateManager.updateURL();
        this.app.fetchAndRender();
    }

    resetAllFiltersUI() {
        this.app.stateManager.resetFiltersState();
        if (this.inputs.searchInput)   this.inputs.searchInput.value   = '';
        if (this.inputs.minPriceInput) this.inputs.minPriceInput.value = '';
        if (this.inputs.maxPriceInput) this.inputs.maxPriceInput.value = '';
        if (this.inputs.onSaleFilter)  this.inputs.onSaleFilter.checked = false;
        document.querySelectorAll('input[name="category[]"], input[name="brand[]"], input[name^="spec_"], input[name="region_set"]').forEach(cb => cb.checked = false);
        const allRegionsRadio = document.querySelector('input[name="region_set"][value=""]');
        if (allRegionsRadio) allRegionsRadio.checked = true;
        this.app.stateManager.updateURL();
        this.app.fetchAndRender();
    }

    initSidebarCollapsibles() {
        document.addEventListener('click', e => {
            if (!e.target.classList.contains('show-more-btn')) return;
            const btn        = e.target;
            const filterType = btn.dataset.filter;
            const isExpanded = btn.classList.contains('expanded');
            if (isExpanded) {
                this.collapseFilterList(filterType);
                btn.classList.remove('expanded');
                btn.textContent = 'Show More';
            } else {
                this.expandFilterList(filterType);
                btn.classList.add('expanded');
                btn.textContent = 'Show Less';
            }
        });
        ['search', 'categories', 'brands', 'price', 'sale'].forEach(name => {
            if (localStorage.getItem(`sidebar-${name}`) === 'false') this.toggleSection(name);
        });
    }

    toggleSection(sectionName) {
        const section  = document.querySelector(`[data-section="${sectionName}"]`);
        if (!section) return;
        const content  = section.querySelector('.section-content');
        const chevron  = section.querySelector('.chevron');
        if (content) content.classList.toggle('open');
        if (chevron) chevron.classList.toggle('rotated');
        if (content) localStorage.setItem(`sidebar-${sectionName}`, content.classList.contains('open'));
    }

    expandFilterList(filterType) {
        const dataEl   = document.getElementById(`all-${filterType === 'category' ? 'categories' : 'brands'}`);
        if (!dataEl) return;
        const items    = JSON.parse(dataEl.textContent);
        const listEl   = document.getElementById(`${filterType}-list`);
        listEl.innerHTML = items.map(item => this.filterCheckboxHTML(filterType, item)).join('');
        this.rebindListCheckboxes(listEl);
    }

    collapseFilterList(filterType) {
        const dataEl   = document.getElementById(`all-${filterType === 'category' ? 'categories' : 'brands'}`);
        if (!dataEl) return;
        const items    = JSON.parse(dataEl.textContent);
        const listEl   = document.getElementById(`${filterType}-list`);
        listEl.innerHTML = items.slice(0, 5).map(item => this.filterCheckboxHTML(filterType, item)).join('');
        this.rebindListCheckboxes(listEl);
    }

    filterCheckboxHTML(filterType, item) {
        return `<label class="filter-checkbox-label">
            <input type="checkbox" class="filter-checkbox" name="${filterType}[]" value="${item.id}">
            <span class="filter-name">${DealsUtils.escapeHtml(item.name)}</span>
            <span class="filter-count">${item.product_count || 0}</span>
        </label>`;
    }

    rebindListCheckboxes(container) {
        const debounced = () => {
            clearTimeout(this.app.stateManager.state.debounceTimer);
            this.app.stateManager.state.debounceTimer = setTimeout(() => {
                this.syncFormInputsToState();
                this.app.stateManager.updateURL();
                this.app.fetchAndRender();
            }, 300);
        };
        container.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            const key = cb.name === 'category[]' ? 'categoryIds' : 'brandIds';
            cb.checked = this.app.stateManager.state.filters[key].includes(cb.value);
            cb.addEventListener('change', debounced);
        });
    }
}

// ─── App Orchestrator ─────────────────────────────────────────────────────────

class DealsApp {
    constructor() {
        this.stateManager = new DealsStateManager();
        this.ui           = new DealsGridUI(this);
        this.controls     = new DealsFilterControlsUI(this);
        this.comparison   = new DealsComparisonManager(this);

        this.stateManager.onChange((state, reason) => {
            if (reason === 'url_loaded' || reason === 'initial_seeded') {
                this.controls.syncStateToFormUI(state);
            }
            this.ui.syncStateDisplays(state);
        });

        window.addEventListener('popstate', () => {
            this.stateManager.loadFromURL();
            this.fetchAndRender();
        });

        this.stateManager.loadFromURL();
        this.stateManager.seedInitialState();
        this.fetchAndRender();
    }

    async fetchAndRender() {
        this.ui.showLoading();
        try {
            const data = await DealsApiService.fetchDeals(this.stateManager.state);
            this.ui.renderDealsGrid(data.data, this.stateManager.state);
            this.ui.renderPaginationUI(data.pagination, this.stateManager.state);
            this.ui.updateResultsCount(data.pagination.total);
            this.controls.generateSuggestedFilters(data.data);
        } catch (error) {
            console.error('Error loading deals:', error);
            this.ui.showToast('An error occurred while loading deals', 'error');
            this.ui.showEmptyState();
        } finally {
            this.ui.hideLoading();
            this.ui.hideTabLoading();
            if (typeof window.refreshRecommendations === 'function') window.refreshRecommendations();
        }
    }

    async executeAddToCart(btnElement) {
        const productId   = btnElement.dataset.productId;
        btnElement.disabled = true;
        const originalText = btnElement.textContent;
        btnElement.textContent = 'Adding...';
        try {
            const data = await DealsApiService.addToCart(productId);
            if (data.success) {
                this.ui.showToast(data.message, 'success');
                this.stateManager.state.cartCount = data.count;
                this.stateManager.state.cartProductIds.add(Number(productId));
                document.querySelectorAll(`.btn-add-to-cart[data-product-id="${productId}"]`).forEach(b => b.classList.add('active'));
                this.stateManager.notify('cart_updated');
                await DealsApiService.recordBoostClick(productId, 'deals');
            } else {
                this.ui.showToast(data.message, 'error');
            }
        } catch {
            this.ui.showToast('Failed to add item to cart', 'error');
        } finally {
            btnElement.disabled = false;
            btnElement.textContent = originalText;
        }
    }

    async executeToggleWishlist(btnElement) {
        const productId  = btnElement.dataset.productId;
        const isInWishlist = btnElement.classList.contains('active');
        btnElement.disabled = true;
        try {
            const data = await DealsApiService.toggleWishlist(productId, isInWishlist);
            if (data.success) {
                this.ui.showToast(data.message, 'success');
                this.stateManager.state.wishlistCount = data.count;
                const numId = parseInt(productId, 10);
                if (isInWishlist) {
                    this.stateManager.state.wishlistProductIds.delete(numId);
                    document.querySelectorAll(`.btn-wishlist[data-product-id="${productId}"]`).forEach(b => b.classList.remove('active'));
                } else {
                    this.stateManager.state.wishlistProductIds.add(numId);
                    document.querySelectorAll(`.btn-wishlist[data-product-id="${productId}"]`).forEach(b => b.classList.add('active'));
                }
                this.stateManager.notify('wishlist_updated');
            } else {
                this.ui.showToast(data.message, 'error');
            }
        } catch {
            this.ui.showToast('An error occurred updating wishlist', 'error');
        } finally {
            btnElement.disabled = false;
        }
    }

    async runCountsEndpointSync() {
        try {
            const { cartData, wishlistData } = await DealsApiService.fetchCountsSync();
            this.stateManager.state.cartCount    = cartData.count    || 0;
            this.stateManager.state.wishlistCount = wishlistData.count || 0;
            if (Array.isArray(wishlistData.product_ids)) {
                this.stateManager.state.wishlistProductIds = new Set(wishlistData.product_ids);
            }
            this.stateManager.notify('counts_synced');
        } catch (err) {
            console.error('Count sync error:', err);
        }
    }
}

// ─── Bootstrap ────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    window.DealsAppInstance = new DealsApp();

    window.switchTab          = tab  => window.DealsAppInstance.controls.switchTab(tab);
    window.toggleSection      = name => window.DealsAppInstance.controls.toggleSection(name);
    window.toggleReview       = btn  => window.DealsAppInstance.ui.toggleReview(btn);
    window.applySuggestedFilter = btn => window.DealsAppInstance.controls.applySuggestedFilter(btn);
    window.compareProducts    = ()   => window.DealsAppInstance.comparison.compareProducts();
    window.updateCounts       = ()   => window.DealsAppInstance.runCountsEndpointSync();
    window.showAllMerchants   = (e, merchants) => window.DealsAppInstance.ui.renderMerchants(merchants); // popover handled inline
});