(function () {
    'use strict';

    class ProductModal {
        constructor() {
            this.modal = null;
            this.overlay = null;
            this.currentProduct = null;
            this.currentVariant = null;
            this.recentlyViewed = this.loadRecentlyViewed();

            // Reviews pagination state
            this._reviews = [];
            this._reviewsPage = 0;
            this._reviewsPerPage = 5;
            this._reviewsPanelOpen = false;

            this.init();
        }

        init() {
            this.createModalStructure();
            this.attachEventListeners();
            this.checkURLForProduct();
        }

        createModalStructure() {
            this.overlay = document.createElement('div');
            this.overlay.className = 'product-modal-overlay';
            this.overlay.addEventListener('click', () => this.close());

            this.modal = document.createElement('div');
            this.modal.className = 'product-modal';
            this.modal.innerHTML = `
                <div class="modal-header-actions">
                    <button class="modal-action-btn modal-btn-wishlist" id="modal-wishlist-btn" aria-label="Add to wishlist" title="Add to wishlist" style="display:none">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                        </svg>
                    </button>
                    <button class="modal-action-btn modal-btn-cart" id="modal-cart-btn" aria-label="Add to cart" title="Add to cart" style="display:none">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                    </button>
                    <button class="modal-action-btn modal-btn-share" id="modal-share-btn" aria-label="Share" title="Share" style="display:none">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="18" cy="5" r="3"></circle>
                            <circle cx="6" cy="12" r="3"></circle>
                            <circle cx="18" cy="19" r="3"></circle>
                            <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                            <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                        </svg>
                    </button>
                    <button class="modal-close" aria-label="Close modal">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
                <div class="modal-content">
                    <div class="modal-loading">
                        <div class="spinner"></div>
                        <p>Loading product details...</p>
                    </div>
                </div>
            `;

            document.body.appendChild(this.overlay);
            document.body.appendChild(this.modal);
            this.modal.querySelector('.modal-close').addEventListener('click', () => this.close());
        }

        attachEventListeners() {
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.modal.classList.contains('active')) {
                    this.close();
                }
            });

            this.modal.addEventListener('click', (e) => e.stopPropagation());

            document.addEventListener('click', (e) => {
                const productCard = e.target.closest('[data-product-id]');
                if (productCard && !e.target.closest('.btn-compare, .btn-wishlist, .btn-cart, .btn-add-to-cart, .btn-flip, .btn-share, .merchant-badge, .merchant-count, .product-card-back')) {
                    e.preventDefault();
                    const productId = productCard.dataset.productId;
                    this.open(productId);
                }
            });
        }

        checkURLForProduct() {
            const params = new URLSearchParams(window.location.search);
            const productId = params.get('product');
            const variantId = params.get('variant');
            if (productId) this.open(productId, variantId);
        }

        async open(productId, variantId = null) {
            try {
                this.modal.classList.add('active');
                this.overlay.classList.add('active');
                document.body.style.overflow = 'hidden';

                this._reviews = [];
                this._reviewsPage = 0;
                this._reviewsPanelOpen = false;

                this.updateURL(productId, variantId);

                const content = this.modal.querySelector('.modal-content');
                content.innerHTML = `
                    <div class="modal-loading">
                        <div class="spinner"></div>
                        <p>Loading product details...</p>
                    </div>
                `;

                const endpoint = window.location.pathname.includes('/deals')
                    ? `/${SITE}/deals/${productId}/modal`
                    : `/${SITE}/products/${productId}/modal`;

                const response = await fetch(endpoint);
                const data = await response.json();

                if (!data.success) throw new Error(data.message || 'Failed to load product');

                this.currentProduct = data.product;
                this.currentVariant = variantId
                    ? this.currentProduct.variants.find(v => v.id == variantId)
                    : null;

                this._reviews = data.product.reviews || [];

                this._showHeaderButtons();
                this._wireHeaderButtons();
                this.addToRecentlyViewed(productId);
                this.render(data);

            } catch (error) {
                console.error('Error loading product:', error);
                this.showError('Failed to load product details. Please try again.');
            }
        }

        _showHeaderButtons() {
            ['modal-wishlist-btn', 'modal-cart-btn', 'modal-share-btn'].forEach(id => {
                const btn = this.modal.querySelector(`#${id}`);
                if (btn) btn.style.display = '';
            });
        }

        _wireHeaderButtons() {
            const product = this.currentProduct;
            if (!product) return;

            // Cart
            const cartBtn = this.modal.querySelector('#modal-cart-btn');
            if (cartBtn) {
                const fresh = cartBtn.cloneNode(true);
                cartBtn.replaceWith(fresh);
                fresh.addEventListener('click', async () => {
                    fresh.disabled = true;
                    fresh.classList.add('loading');
                    try {
                        const res = await fetch(`/api/${SITE}/cart/add`, {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                                product_id: product.id,
                                variant_id: this.currentVariant?.id ?? null,
                                quantity: 1
                            })
                        });
                        const d = await res.json();
                        if (d.success) {
                            this.showToast('Added to cart!', 'success');
                            fresh.classList.add('added');
                            setTimeout(() => fresh.classList.remove('added'), 1500);
                        } else {
                            this.showToast(d.message || 'Failed to add to cart', 'error');
                        }
                    } catch {
                        this.showToast('Failed to add to cart', 'error');
                    } finally {
                        fresh.disabled = false;
                        fresh.classList.remove('loading');
                    }
                });
            }

            // Wishlist
            const wishlistBtn = this.modal.querySelector('#modal-wishlist-btn');
            if (wishlistBtn) {
                const fresh = wishlistBtn.cloneNode(true);
                wishlistBtn.replaceWith(fresh);
                fresh.addEventListener('click', async () => {
                    const isActive = fresh.classList.contains('active');
                    const url = isActive
                        ? `/api/${SITE}/wishlist/remove/${product.id}`
                        : `/api/${SITE}/wishlist/add`;
                    try {
                        const res = await fetch(url, {
                            method: isActive ? 'DELETE' : 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: isActive ? null : JSON.stringify({product_id: product.id})
                        });
                        const d = await res.json();
                        if (d.success) {
                            fresh.classList.toggle('active');
                            this.showToast(isActive ? 'Removed from wishlist' : 'Added to wishlist!', 'success');
                        }
                    } catch {
                        this.showToast('Failed to update wishlist', 'error');
                    }
                });
            }

            // Share
            const shareBtn = this.modal.querySelector('#modal-share-btn');
            if (shareBtn) {
                const fresh = shareBtn.cloneNode(true);
                shareBtn.replaceWith(fresh);
                fresh.addEventListener('click', async () => {
                    const url = window.location.href;
                    if (navigator.share) {
                        try {
                            await navigator.share({title: product.name, url});
                        } catch (err) {
                            if (err.name !== 'AbortError') this.copyToClipboard(url);
                        }
                    } else {
                        this.copyToClipboard(url);
                    }
                });
            }
        }

        close() {
            this.modal.classList.remove('active');
            this.overlay.classList.remove('active');
            document.body.style.overflow = '';
            this.currentProduct = null;
            this.currentVariant = null;
            this._reviews = [];
            this._reviewsPage = 0;
            this._reviewsPanelOpen = false;

            ['modal-wishlist-btn', 'modal-cart-btn', 'modal-share-btn'].forEach(id => {
                const btn = this.modal.querySelector(`#${id}`);
                if (btn) btn.style.display = 'none';
            });

            const url = new URL(window.location);
            url.searchParams.delete('product');
            url.searchParams.delete('variant');
            window.history.pushState({}, '', url);
        }

        updateURL(productId, variantId = null) {
            const url = new URL(window.location);
            url.searchParams.set('product', productId);
            if (variantId) {
                url.searchParams.set('variant', variantId);
            } else {
                url.searchParams.delete('variant');
            }
            window.history.pushState({}, '', url);
        }

        render(data) {
            const {product, related_products, similar_items} = data;
            const isMobile = window.innerWidth < 768;

            const content = this.modal.querySelector('.modal-content');
            content.innerHTML = `
                <div class="modal-grid ${isMobile ? 'mobile' : 'desktop'}">
                    <div class="modal-images">
                        ${this.renderImageGallery(product)}
                    </div>
                    <div class="modal-details">
                        ${this.renderProductInfo(product)}
                        ${this.renderRatingSummary(product)}
                        ${this.renderVariants(product)}
                        ${this.renderMerchants(product)}
                        ${this.renderPriceHistory(product)}
                        ${this.renderPriceComparison(product)}
                        ${this.renderSpecifications(product)}
                        ${this.renderProsConsFromReviews()}
                        ${this.renderReviewsSection(product)}
                    </div>
                </div>
                ${this.renderCrossSelling(related_products, similar_items)}
            `;

            this.attachImageHandlers();
            this.attachVariantHandlers();
            this.attachReviewHandlers();
        }

        // ─── Rating summary (stars + count, clickable) ────────────────────────────

        renderRatingSummary(product) {
            if (!product.review_count || product.review_count === 0) return '';
            const avg = parseFloat(product.average_rating) || 0;
            const count = product.review_count;
            return `
                <div class="modal-rating-summary" id="modal-rating-summary" role="button" tabindex="0"
                     aria-label="Read ${count} reviews">
                    <div class="modal-stars-inline">${this.renderStarsHtml(avg)}</div>
                    <span class="modal-rating-avg">${avg.toFixed(1)}</span>
                    <span class="modal-rating-count">${count} review${count !== 1 ? 's' : ''}</span>
                    <svg class="modal-rating-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </div>
            `;
        }

        // ─── Price history ────────────────────────────────────────────────────────

        renderPriceHistory(product) {
            const history = product.price_history;
            if (!history || history.length < 2) return '';

            const prices = history.map(p => parseFloat(p.price));
            const currentPrice = prices[prices.length - 1];
            const lowestPrice = Math.min(...prices);
            const highestPrice = Math.max(...prices);
            const isAtLowest = currentPrice <= lowestPrice;
            const savingVsHighest = highestPrice > currentPrice
                ? Math.round(((highestPrice - currentPrice) / highestPrice) * 100)
                : 0;

            return `
                <div class="modal-section modal-price-history">
                    <h4 class="modal-section-title">
                        Price History
                        <span class="modal-section-sub">last 90 days</span>
                    </h4>
                    <div class="modal-price-stats">
                        <div class="modal-price-stat">
                            <span class="modal-price-stat-label">Current</span>
                            <span class="modal-price-stat-val current">$${currentPrice.toFixed(2)}</span>
                        </div>
                        <div class="modal-price-stat">
                            <span class="modal-price-stat-label">Lowest</span>
                            <span class="modal-price-stat-val low">$${lowestPrice.toFixed(2)}</span>
                        </div>
                        <div class="modal-price-stat">
                            <span class="modal-price-stat-label">Highest</span>
                            <span class="modal-price-stat-val high">$${highestPrice.toFixed(2)}</span>
                        </div>
                    </div>
                    ${isAtLowest ? `<div class="modal-price-tag modal-price-tag--good">✓ Currently at lowest price</div>` : ''}
                    ${savingVsHighest > 0 && !isAtLowest ? `<div class="modal-price-tag modal-price-tag--info">💰 ${savingVsHighest}% below peak price</div>` : ''}
                    <div class="modal-price-chart">
                        <svg viewBox="0 0 200 50" preserveAspectRatio="none" class="modal-price-chart-svg">
                            ${this._sparklinePath(history)}
                        </svg>
                    </div>
                </div>
            `;
        }

        _sparklinePath(history) {
            if (history.length < 2) return '';
            const prices = history.map(p => parseFloat(p.price));
            const min = Math.min(...prices);
            const max = Math.max(...prices);
            const range = max - min || 1;
            const points = prices.map((price, i) => {
                const x = (i / (prices.length - 1)) * 200;
                const y = 48 - ((price - min) / range) * 44;
                return `${x.toFixed(1)},${y.toFixed(1)}`;
            }).join(' ');
            const lastX = 200;
            const lastY = (48 - ((prices[prices.length - 1] - min) / range) * 44).toFixed(1);
            return `
                <polyline points="${points}" fill="none" stroke="#4F46E5" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="${lastX}" cy="${lastY}" r="3" fill="#4F46E5"/>
            `;
        }

        // ─── Price comparison ─────────────────────────────────────────────────────

        renderPriceComparison(product) {
            const comparison = product.price_comparison;
            if (!comparison) return '';
            const badgeClass = comparison.price_comparison === 'better' ? 'modal-badge--green'
                : comparison.price_comparison === 'worse' ? 'modal-badge--red'
                    : 'modal-badge--grey';
            return `
                <div class="modal-section modal-price-comparison">
                    <h4 class="modal-section-title">Price Comparison</h4>
                    <div class="modal-comparison-rows">
                        <div class="modal-comparison-row">
                            <span>vs. Category Average</span>
                            <span class="modal-badge ${badgeClass}">${this.escapeHtml(comparison.price_difference ?? '—')}</span>
                        </div>
                        ${comparison.category_avg_price ? `
                            <div class="modal-comparison-row">
                                <span>Category Average</span>
                                <span>$${parseFloat(comparison.category_avg_price).toFixed(2)}</span>
                            </div>
                        ` : ''}
                        ${comparison.discount_vs_regular ? `
                            <div class="modal-comparison-row">
                                <span>Your Savings vs RRP</span>
                                <span class="modal-badge modal-badge--green">${this.escapeHtml(comparison.discount_vs_regular)}</span>
                            </div>
                        ` : ''}
                        ${comparison.products_in_category ? `
                            <div class="modal-comparison-row">
                                <span>Similar Products</span>
                                <span>${comparison.products_in_category} in category</span>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        }

        // ─── Pros & cons (derived from review titles) ─────────────────────────────

        // renderProsConsFromReviews() {
        //     const reviews = this._reviews;
        //     if (!reviews || reviews.length < 3) return '';
        //
        //     const pros = reviews.filter(r => r.rating >= 4 && r.title).slice(0, 3).map(r => r.title);
        //     const cons = reviews.filter(r => r.rating <= 2 && r.title).slice(0, 3).map(r => r.title);
        //
        //     if (pros.length === 0 && cons.length === 0) return '';
        //
        //     return `
        //         <div class="modal-section modal-pros-cons">
        //             <h4 class="modal-section-title">What Reviewers Say</h4>
        //             <div class="modal-pros-cons-grid">
        //                 ${pros.length > 0 ? `
        //                     <div class="modal-pros">
        //                         <div class="modal-pros-header">
        //                             <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5">
        //                                 <polyline points="20 6 9 17 4 12"></polyline>
        //                             </svg>
        //                             Pros
        //                         </div>
        //                         <ul class="modal-pros-cons-list">
        //                             ${pros.map(p => `<li>${this.escapeHtml(p)}</li>`).join('')}
        //                         </ul>
        //                     </div>
        //                 ` : ''}
        //                 ${cons.length > 0 ? `
        //                     <div class="modal-cons">
        //                         <div class="modal-cons-header">
        //                             <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.5">
        //                                 <line x1="18" y1="6" x2="6" y2="18"></line>
        //                                 <line x1="6" y1="6" x2="18" y2="18"></line>
        //                             </svg>
        //                             Cons
        //                         </div>
        //                         <ul class="modal-pros-cons-list">
        //                             ${cons.map(c => `<li>${this.escapeHtml(c)}</li>`).join('')}
        //                         </ul>
        //                     </div>
        //                 ` : ''}
        //             </div>
        //         </div>
        //     `;
        // }

        renderProsConsFromReviews() {
            const product = this.currentProduct;
            if (!product) return '';

            const pros = product.pros || [
                'Great build quality',
                'Fast performance',
                'Good value for money',
            ];
            const cons = product.cons || [
                'Battery life could be better',
                'Limited colour options',
            ];

            if (pros.length === 0 && cons.length === 0) return '';

            return `
        <div class="modal-section modal-pros-cons">
            <h4 class="modal-section-title">What Reviewers Say</h4>
            <div class="modal-pros-cons-grid">
                ${pros.length > 0 ? `
                    <div class="modal-pros">
                        <div class="modal-pros-header">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            Pros
                        </div>
                        <ul class="modal-pros-cons-list">
                            ${pros.map(p => `<li>${this.escapeHtml(p)}</li>`).join('')}
                        </ul>
                    </div>
                ` : ''}
                ${cons.length > 0 ? `
                    <div class="modal-cons">
                        <div class="modal-cons-header">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.5">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                            Cons
                        </div>
                        <ul class="modal-pros-cons-list">
                            ${cons.map(c => `<li>${this.escapeHtml(c)}</li>`).join('')}
                        </ul>
                    </div>
                ` : ''}
            </div>
        </div>
    `;
        }

        // ─── Reviews panel ────────────────────────────────────────────────────────

        renderReviewsSection(product) {
            if (!this._reviews.length) return '';
            return `
                <div class="modal-reviews-wrapper" id="modal-reviews-wrapper">
                   <button class="modal-reviews-toggle" id="modal-reviews-toggle" aria-expanded="false">
                        <div class="modal-stars-inline">${this.renderStarsHtml(parseFloat(product.average_rating) || 0)}</div>
                        <span>${this._reviews.length} review${this._reviews.length !== 1 ? 's' : ''}</span>
                        <svg class="modal-reviews-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                    <div class="modal-reviews-panel" id="modal-reviews-panel" hidden>
                        <div id="modal-reviews-list"></div>
                        <div id="modal-reviews-more-wrap" style="text-align:center;margin-top:1rem;"></div>
                    </div>
                </div>
            `;
        }

        attachReviewHandlers() {
            const ratingSummary = this.modal.querySelector('#modal-rating-summary');
            if (ratingSummary) {
                ratingSummary.addEventListener('click', () => this._openReviewsPanel());
                ratingSummary.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') this._openReviewsPanel();
                });
            }

            const toggle = this.modal.querySelector('#modal-reviews-toggle');
            if (toggle) {
                toggle.addEventListener('click', () => {
                    this._reviewsPanelOpen ? this._closeReviewsPanel() : this._openReviewsPanel();
                });
            }
        }

        _openReviewsPanel() {
            this._reviewsPanelOpen = true;
            const panel = this.modal.querySelector('#modal-reviews-panel');
            const toggle = this.modal.querySelector('#modal-reviews-toggle');
            if (panel) panel.hidden = false;
            if (toggle) toggle.setAttribute('aria-expanded', 'true');
            this.modal.querySelectorAll('.modal-reviews-chevron, .modal-rating-chevron').forEach(el => {
                el.style.transform = 'rotate(180deg)';
            });
            if (this._reviewsPage === 0) {
                this._reviewsPage = 1;
                this._renderReviewPage();
            }
        }

        _closeReviewsPanel() {
            this._reviewsPanelOpen = false;
            const panel = this.modal.querySelector('#modal-reviews-panel');
            const toggle = this.modal.querySelector('#modal-reviews-toggle');
            if (panel) panel.hidden = true;
            if (toggle) toggle.setAttribute('aria-expanded', 'false');
            this.modal.querySelectorAll('.modal-reviews-chevron, .modal-rating-chevron').forEach(el => {
                el.style.transform = '';
            });
        }

        _renderReviewPage() {
            const list = this.modal.querySelector('#modal-reviews-list');
            const moreWrap = this.modal.querySelector('#modal-reviews-more-wrap');
            if (!list) return;

            const end = this._reviewsPage * this._reviewsPerPage;
            const visible = this._reviews.slice(0, end);
            const remaining = this._reviews.length - end;

            list.innerHTML = visible.map(r => this._reviewCardHtml(r)).join('');

            if (remaining > 0) {
                moreWrap.innerHTML = `
                    <button class="modal-reviews-show-more" id="modal-reviews-show-more">
                        Show more reviews (${remaining} remaining)
                    </button>
                `;
                moreWrap.querySelector('#modal-reviews-show-more').addEventListener('click', () => {
                    this._reviewsPage++;
                    this._renderReviewPage();
                });
            } else {
                moreWrap.innerHTML = '';
            }
        }

        _reviewCardHtml(review) {
            const verified = review.is_verified_purchase
                ? '<span class="modal-review-verified">✓ Verified Purchase</span>'
                : '';
            return `
                <div class="modal-review-card">
                    <div class="modal-review-header">
                        <div>
                            <div class="modal-stars-inline modal-stars-sm">${this.renderStarsHtml(review.rating)}</div>
                            ${review.title ? `<div class="modal-review-title">${this.escapeHtml(review.title)}</div>` : ''}
                        </div>
                        <div class="modal-review-meta">
                            <span class="modal-review-author">${this.escapeHtml(review.author_name)}</span>
                            ${verified}
                        </div>
                    </div>
                    <p class="modal-review-comment">${this.escapeHtml(review.comment)}</p>
                    ${review.helpful_count > 0
                ? `<div class="modal-review-helpful">${review.helpful_count} people found this helpful</div>`
                : ''}
                </div>
            `;
        }

        renderStarsHtml(rating) {
            let html = '';
            for (let i = 1; i <= 5; i++) {
                if (i <= Math.floor(rating)) {
                    html += '<span class="modal-star filled">★</span>';
                } else if (i - 0.5 <= rating) {
                    html += '<span class="modal-star half">★</span>';
                } else {
                    html += '<span class="modal-star empty">★</span>';
                }
            }
            return html;
        }

        renderStars(rating) {
            return this.renderStarsHtml(rating);
        }

        renderImageGallery(product) {
            const images = product.images && product.images.length
                ? product.images
                : [{url: '/placeholder.jpg', alt: product.name}];
            const mainImage = images.find(img => img.is_primary) || images[0];
            return `
                <div class="image-gallery">
                    <div class="main-image">
                        <img src="${mainImage.url}" alt="${this.escapeHtml(mainImage.alt || product.name)}" id="modal-main-image">
                    </div>
                    ${images.length > 1 ? `
                        <div class="image-thumbnails">
                            ${images.map((img, i) => `
                                <button class="thumbnail ${i === 0 ? 'active' : ''}" data-image-url="${img.url}">
                                    <img src="${img.url}" alt="${this.escapeHtml(img.alt || product.name)}">
                                </button>
                            `).join('')}
                        </div>
                    ` : ''}
                </div>
            `;
        }

        renderProductInfo(product) {
            const currentPrice = this.currentVariant?.sale_price || this.currentVariant?.price
                || product.sale_price || product.price;
            const originalPrice = this.currentVariant?.price || product.price;
            const hasDiscount = currentPrice < originalPrice;
            const discountPercent = hasDiscount
                ? Math.round(((originalPrice - currentPrice) / originalPrice) * 100)
                : 0;
            return `
                <div class="product-info">
                    ${product.brand ? `<div class="product-brand">${this.escapeHtml(product.brand.name)}</div>` : ''}
                    <h2 class="product-title">${this.escapeHtml(product.name)}</h2>
                    <div class="product-price">
                        <span class="current-price">$${parseFloat(currentPrice).toFixed(2)}</span>
                        ${hasDiscount ? `
                            <span class="original-price">$${parseFloat(originalPrice).toFixed(2)}</span>
                            <span class="discount-badge">-${discountPercent}%</span>
                        ` : ''}
                    </div>
                    ${product.description ? `
                        <div class="product-description"><p>${this.escapeHtml(product.description)}</p></div>
                    ` : ''}
                    <div class="product-metadata">
                        ${product.brand ? `<div><strong>Brand:</strong> ${this.escapeHtml(product.brand.name)}</div>` : ''}
                        ${product.category ? `<div><strong>Category:</strong> ${this.escapeHtml(product.category.name)}</div>` : ''}
                        ${product.in_stock
                ? '<div class="stock-status in-stock">✓ In Stock</div>'
                : '<div class="stock-status out-of-stock">Out of Stock</div>'}
                    </div>
                </div>
            `;
        }

        renderVariants(product) {
            if (!product.variants || product.variants.length === 0) return '';
            const variantGroups = {};
            product.variants.forEach(variant => {
                Object.entries(variant.attributes || {}).forEach(([key, value]) => {
                    if (!variantGroups[key]) variantGroups[key] = new Set();
                    variantGroups[key].add(value);
                });
            });
            return `
                <div class="product-variants">
                    <h4>Available Options</h4>
                    ${Object.entries(variantGroups).map(([attrName, values]) => `
                        <div class="variant-group">
                            <label>${attrName}</label>
                            <div class="variant-options">
                                ${Array.from(values).map(value => {
                const variant = product.variants.find(v => v.attributes[attrName] === value);
                const isSelected = this.currentVariant?.id === variant.id;
                const isDisabled = !variant.in_stock;
                return `
                                        <button class="variant-option ${isSelected ? 'selected' : ''} ${isDisabled ? 'disabled' : ''}"
                                                data-variant-id="${variant.id}" ${isDisabled ? 'disabled' : ''}>
                                            ${this.escapeHtml(value)}
                                        </button>
                                    `;
            }).join('')}
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        }

        renderMerchants(product) {
            let merchants = this.currentVariant
                ? (this.currentVariant.merchants || [])
                : (product.merchants || []);
            if (merchants.length === 0) return '';
            merchants = [...merchants].sort((a, b) => (a.sale_price || a.price) - (b.sale_price || b.price));
            const stats = product.merchant_price_stats;
            const visible = merchants.slice(0, 3);
            return `
                <div class="product-merchants">
                    <h4>Available From ${merchants.length} Retailer${merchants.length > 1 ? 's' : ''}</h4>
                    <div class="merchant-list">
                        ${visible.map((m, i) => `
                            <div class="merchant-item ${i === 0 ? 'best-price' : ''}">
                                <div class="merchant-info">
                                    <span class="merchant-name">${this.escapeHtml(m.name)}</span>
                                    ${i === 0 ? '<span class="best-price-badge">Best Price</span>' : ''}
                                </div>
                                <div class="merchant-price">
                                    <span class="price">$${parseFloat(m.sale_price || m.price).toFixed(2)}</span>
                                    ${m.has_discount ? `<span class="discount">-${m.discount_percentage}%</span>` : ''}
                                </div>
                            </div>
                        `).join('')}
                        ${merchants.length > 3 ? `<button class="show-more-merchants">Show ${merchants.length - 3} More</button>` : ''}
                    </div>
                    ${stats ? `
                        <div class="modal-merchant-stats">
                            <div class="modal-merchant-stat">
                                <span class="modal-merchant-stat-label">Lowest</span>
                                <span class="modal-merchant-stat-val stat-low">$${stats.lowest.toFixed(2)}</span>
                            </div>
                            <div class="modal-merchant-stat">
                                <span class="modal-merchant-stat-label">Average</span>
                                <span class="modal-merchant-stat-val">$${stats.average.toFixed(2)}</span>
                            </div>
                            <div class="modal-merchant-stat">
                                <span class="modal-merchant-stat-label">Highest</span>
                                <span class="modal-merchant-stat-val stat-high">$${stats.highest.toFixed(2)}</span>
                            </div>
                        </div>
                    ` : ''}
                    <div class="product-cta" style="margin-top:0.75rem;">
                        ${merchants[0]?.url
                ? `<a href="${merchants[0].url}" target="_blank" rel="noopener noreferrer" class="btn-primary">
                                Shop Now at ${this.escapeHtml(merchants[0].name)}
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                    <polyline points="15 3 21 3 21 9"></polyline>
                                    <line x1="10" y1="14" x2="21" y2="3"></line>
                                </svg>
                               </a>`
                : '<button class="btn-primary" disabled>Currently Unavailable</button>'}
                    </div>
                </div>
            `;
        }

        renderSpecifications(product) {
            if (!product.specifications || product.specifications.length === 0) return '';
            return `
                <div class="product-specifications">
                    <h4>Specifications</h4>
                    ${product.specifications.map(group => `
                        <div class="spec-group">
                            <h5>${this.escapeHtml(group.category)}</h5>
                            <dl class="spec-list">
                                ${group.items.map(item => `
                                    <div class="spec-item">
                                        <dt>${this.escapeHtml(item.key)}</dt>
                                        <dd>${this.escapeHtml(item.value)}</dd>
                                    </div>
                                `).join('')}
                            </dl>
                        </div>
                    `).join('')}
                </div>
            `;
        }

        renderCrossSelling(relatedProducts, similarItems) {
            return `
                ${relatedProducts && relatedProducts.length > 0 ? `
                    <div class="cross-sell-section">
                        <h3>You May Also Like</h3>
                        <div class="product-grid-mini">
                            ${relatedProducts.map(p => this.renderMiniCard(p)).join('')}
                        </div>
                    </div>
                ` : ''}
                ${similarItems && similarItems.length > 0 ? `
                    <div class="cross-sell-section">
                        <h3>Explore Similar Items</h3>
                        <div class="product-grid-mini">
                            ${similarItems.map(p => this.renderMiniCard(p)).join('')}
                        </div>
                    </div>
                ` : ''}
            `;
        }

        renderMiniCard(product) {
            return `
                <div class="mini-product-card" data-product-id="${product.id}">
                    <div class="mini-card-image">
                        <img src="${product.image || '/placeholder.jpg'}" alt="${this.escapeHtml(product.name)}">
                    </div>
                    <div class="mini-card-info">
                        <h4>${this.escapeHtml(product.name)}</h4>
                        ${product.brand ? `<p class="mini-brand">${this.escapeHtml(product.brand)}</p>` : ''}
                        <div class="mini-price">
                            ${product.sale_price ? `
                                <span class="sale">$${parseFloat(product.sale_price).toFixed(2)}</span>
                                <span class="original">$${parseFloat(product.price).toFixed(2)}</span>
                            ` : `<span>$${parseFloat(product.price).toFixed(2)}</span>`}
                        </div>
                    </div>
                </div>
            `;
        }

        attachImageHandlers() {
            const thumbnails = this.modal.querySelectorAll('.thumbnail');
            const mainImage = this.modal.querySelector('#modal-main-image');
            thumbnails.forEach(thumb => {
                thumb.addEventListener('click', function () {
                    thumbnails.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    mainImage.src = this.dataset.imageUrl;
                });
            });
        }

        attachVariantHandlers() {
            this.modal.querySelectorAll('.variant-option:not(.disabled)').forEach(button => {
                button.addEventListener('click', () => {
                    const variantId = button.dataset.variantId;
                    const variant = this.currentProduct.variants.find(v => v.id == variantId);
                    if (!variant) return;
                    this.currentVariant = variant;
                    this.updateURL(this.currentProduct.id, variantId);
                    const detailsSection = this.modal.querySelector('.modal-details');
                    detailsSection.innerHTML = `
                        ${this.renderProductInfo(this.currentProduct)}
                        ${this.renderRatingSummary(this.currentProduct)}
                        ${this.renderVariants(this.currentProduct)}
                        ${this.renderMerchants(this.currentProduct)}
                        ${this.renderPriceHistory(this.currentProduct)}
                        ${this.renderPriceComparison(this.currentProduct)}
                        ${this.renderSpecifications(this.currentProduct)}
                        ${this.renderProsConsFromReviews()}
                        ${this.renderReviewsSection(this.currentProduct)}
                    `;
                    this.attachVariantHandlers();
                    this.attachReviewHandlers();
                    this._wireHeaderButtons();
                    if (variant.images && variant.images.length > 0) {
                        const mainImg = this.modal.querySelector('#modal-main-image');
                        if (mainImg) mainImg.src = variant.images[0].url;
                    }
                });
            });
        }

        async copyToClipboard(text) {
            try {
                await navigator.clipboard.writeText(text);
            } catch {
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.cssText = 'position:fixed;opacity:0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
            }
            this.showToast('Link copied to clipboard!', 'success');
        }

        showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => toast.classList.add('show'), 100);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        showError(message) {
            const content = this.modal.querySelector('.modal-content');
            content.innerHTML = `
                <div class="modal-error">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <p>${message}</p>
                    <button class="btn-secondary" onclick="productModal.close()">Close</button>
                </div>
            `;
        }

        loadRecentlyViewed() {
            try {
                const stored = localStorage.getItem('recently_viewed_products');
                return stored ? JSON.parse(stored) : [];
            } catch {
                return [];
            }
        }

        addToRecentlyViewed(productId) {
            this.recentlyViewed = this.recentlyViewed.filter(id => id != productId);
            this.recentlyViewed.unshift(productId);
            this.recentlyViewed = this.recentlyViewed.slice(0, 6);
            try {
                localStorage.setItem('recently_viewed_products', JSON.stringify(this.recentlyViewed));
            } catch { /* storage unavailable */
            }
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = String(text ?? '');
            return div.innerHTML;
        }
    }

    window.productModal = new ProductModal();

})();