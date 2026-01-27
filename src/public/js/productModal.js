(function () {
    'use strict';

    class ProductModal {
        constructor() {
            this.modal = null;
            this.overlay = null;
            this.currentProduct = null;
            this.currentVariant = null;
            this.recentlyViewed = this.loadRecentlyViewed();
            this.init();
        }

        init() {
            this.createModalStructure();
            this.attachEventListeners();
            this.checkURLForProduct();
        }

        createModalStructure() {
            // Create overlay
            this.overlay = document.createElement('div');
            this.overlay.className = 'product-modal-overlay';
            this.overlay.addEventListener('click', () => this.close());

            // Create modal
            this.modal = document.createElement('div');
            this.modal.className = 'product-modal';
            this.modal.innerHTML = `
                <button class="modal-close" aria-label="Close modal">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
                <div class="modal-content">
                    <div class="modal-loading">
                        <div class="spinner"></div>
                        <p>Loading product details...</p>
                    </div>
                </div>
            `;

            document.body.appendChild(this.overlay);
            document.body.appendChild(this.modal);

            // Close button handler
            this.modal.querySelector('.modal-close').addEventListener('click', () => this.close());
        }

        attachEventListeners() {
            // Escape key to close
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.modal.classList.contains('active')) {
                    this.close();
                }
            });

            // Prevent modal click from closing
            this.modal.addEventListener('click', (e) => {
                e.stopPropagation();
            });

            // Delegate click events for product cards
            document.addEventListener('click', (e) => {
                const productCard = e.target.closest('[data-product-id]');
                if (productCard && !e.target.closest('.btn-compare, .btn-wishlist, .btn-cart')) {
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

            if (productId) {
                this.open(productId, variantId);
            }
        }

        async open(productId, variantId = null) {
            try {
                this.modal.classList.add('active');
                this.overlay.classList.add('active');
                document.body.style.overflow = 'hidden';

                // Update URL
                this.updateURL(productId, variantId);

                // Show loading state
                const content = this.modal.querySelector('.modal-content');
                content.innerHTML = `
                    <div class="modal-loading">
                        <div class="spinner"></div>
                        <p>Loading product details...</p>
                    </div>
                `;

                // Fetch product data
                const endpoint = window.location.pathname.includes('/deals')
                    ? `/${SITE}/deals/${productId}/modal`
                    : `/${SITE}/products/${productId}/modal`;

                const response = await fetch(endpoint);
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Failed to load product');
                }

                this.currentProduct = data.product;
                this.currentVariant = variantId
                    ? this.currentProduct.variants.find(v => v.id == variantId)
                    : null;

                // Add to recently viewed
                this.addToRecentlyViewed(productId);

                // Render product
                this.render(data);

            } catch (error) {
                console.error('Error loading product:', error);
                this.showError('Failed to load product details. Please try again.');
            }
        }

        close() {
            this.modal.classList.remove('active');
            this.overlay.classList.remove('active');
            document.body.style.overflow = '';
            this.currentProduct = null;
            this.currentVariant = null;

            // Remove product from URL
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
            const {product, related_products, similar_items, recently_viewed} = data;
            const isMobile = window.innerWidth < 768;

            const content = this.modal.querySelector('.modal-content');
            content.innerHTML = `
        <div class="modal-grid ${isMobile ? 'mobile' : 'desktop'}">
            <!-- Left Column: Images -->
            <div class="modal-images">
                ${this.renderImageGallery(product)}
            </div>

            <!-- Right Column: Details -->
            <div class="modal-details">
                ${this.renderProductInfo(product)}
                ${this.renderVariants(product)}
                ${this.renderMerchants(product)}
                ${this.renderCTA(product)}
                ${this.renderShareButton(product)}
                ${this.renderSpecifications(product)}
            </div>
        </div>

        <!-- Cross-selling sections -->
        ${this.renderCrossSelling(related_products, similar_items)}
        ${recently_viewed && recently_viewed.length > 0 ? `
            <div class="cross-sell-section">
                <h3>Recently Viewed</h3>
                <div class="product-grid-mini">
                    ${recently_viewed.map(p => this.renderMiniCard(p)).join('')}
                </div>
            </div>
        ` : ''}
    `;

            // Attach handlers
            this.attachImageHandlers();
            this.attachVariantHandlers();
            this.attachShareHandler();
        }

        renderImageGallery(product) {
            const images = product.images.length ? product.images : [{url: '/placeholder.jpg', alt: product.name}];
            const mainImage = images.find(img => img.is_primary) || images[0];

            return `
                <div class="image-gallery">
                    <div class="main-image">
                        <img src="${mainImage.url}" alt="${mainImage.alt || product.name}" id="modal-main-image">
                    </div>
                    ${images.length > 1 ? `
                        <div class="image-thumbnails">
                            ${images.map((img, index) => `
                                <button class="thumbnail ${index === 0 ? 'active' : ''}" data-image-url="${img.url}">
                                    <img src="${img.url}" alt="${img.alt || product.name}">
                                </button>
                            `).join('')}
                        </div>
                    ` : ''}
                </div>
            `;
        }

        renderProductInfo(product) {
            const currentPrice = this.currentVariant?.sale_price || this.currentVariant?.price || product.sale_price || product.price;
            const originalPrice = this.currentVariant?.price || product.price;
            const hasDiscount = currentPrice < originalPrice;
            const discountPercent = hasDiscount ? Math.round(((originalPrice - currentPrice) / originalPrice) * 100) : 0;

            return `
                <div class="product-info">
                    ${product.brand ? `<div class="product-brand">${this.escapeHtml(product.brand.name)}</div>` : ''}
                    <h2 class="product-title">${this.escapeHtml(product.name)}</h2>
                    
                    <div class="product-price">
                        <span class="current-price">$${currentPrice.toFixed(2)}</span>
                        ${hasDiscount ? `
                            <span class="original-price">$${originalPrice.toFixed(2)}</span>
                            <span class="discount-badge">-${discountPercent}%</span>
                        ` : ''}
                    </div>

                    ${product.average_rating > 0 ? `
                        <div class="product-rating">
                            ${this.renderStars(product.average_rating)}
                            <span class="rating-count">(${product.review_count} reviews)</span>
                        </div>
                    ` : ''}

                    ${product.description ? `
                        <div class="product-description">
                            <p>${this.escapeHtml(product.description)}</p>
                        </div>
                    ` : ''}

                    <div class="product-metadata">
                        ${product.brand ? `<div><strong>Brand:</strong> ${this.escapeHtml(product.brand.name)}</div>` : ''}
                        ${product.category ? `<div><strong>Category:</strong> ${this.escapeHtml(product.category.name)}</div>` : ''}
                        ${product.in_stock ? `
                            <div class="stock-status in-stock">✓ In Stock</div>
                        ` : `
                            <div class="stock-status out-of-stock">Out of Stock</div>
                        `}
                    </div>
                </div>
            `;
        }

        renderVariants(product) {
            if (!product.variants || product.variants.length === 0) return '';

            // Group variants by attribute type (e.g., Color, Size, Storage)
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
                                        <button 
                                            class="variant-option ${isSelected ? 'selected' : ''} ${isDisabled ? 'disabled' : ''}"
                                            data-variant-id="${variant.id}"
                                            ${isDisabled ? 'disabled' : ''}
                                        >
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
            let merchants = product.merchants || [];

            // If variant selected, show variant-specific merchants
            if (this.currentVariant) {
                merchants = this.currentVariant.merchants || [];
            }

            if (merchants.length === 0) return '';

            // Sort by price
            merchants.sort((a, b) => {
                const priceA = a.sale_price || a.price;
                const priceB = b.sale_price || b.price;
                return priceA - priceB;
            });

            const showAll = merchants.length <= 3;
            const visibleMerchants = showAll ? merchants : merchants.slice(0, 3);

            return `
                <div class="product-merchants">
                    <h4>Available From ${merchants.length} Retailer${merchants.length > 1 ? 's' : ''}</h4>
                    <div class="merchant-list">
                        ${visibleMerchants.map((merchant, index) => {
                const price = merchant.sale_price || merchant.price;
                const hasDiscount = merchant.has_discount;
                return `
                                <div class="merchant-item ${index === 0 ? 'best-price' : ''}">
                                    <div class="merchant-info">
                                        <span class="merchant-name">${this.escapeHtml(merchant.name)}</span>
                                        ${index === 0 ? '<span class="best-price-badge">Best Price</span>' : ''}
                                    </div>
                                    <div class="merchant-price">
                                        <span class="price">$${price.toFixed(2)}</span>
                                        ${hasDiscount ? `<span class="discount">-${merchant.discount_percentage}%</span>` : ''}
                                    </div>
                                </div>
                            `;
            }).join('')}
                        ${!showAll ? `
                            <button class="show-more-merchants" id="show-more-merchants">
                                Show ${merchants.length - 3} More
                            </button>
                        ` : ''}
                    </div>
                </div>
            `;
        }

        renderCTA(product) {
            const primaryMerchant = this.currentVariant?.merchants[0] || product.merchants[0];

            if (!primaryMerchant) {
                return `
                    <div class="product-cta">
                        <button class="btn-primary" disabled>Currently Unavailable</button>
                    </div>
                `;
            }

            return `
                <div class="product-cta">
                    <a href="${primaryMerchant.url}" target="_blank" rel="noopener noreferrer" class="btn-primary">
                        Shop Now at ${this.escapeHtml(primaryMerchant.name)}
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                            <polyline points="15 3 21 3 21 9"></polyline>
                            <line x1="10" y1="14" x2="21" y2="3"></line>
                        </svg>
                    </a>
                </div>
            `;
        }

        renderShareButton(product) {
            return `
                <div class="product-share">
                    <button class="btn-share" id="share-product">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="18" cy="5" r="3"></circle>
                            <circle cx="6" cy="12" r="3"></circle>
                            <circle cx="18" cy="19" r="3"></circle>
                            <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                            <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                        </svg>
                        Share Product
                    </button>
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
                            <h5>${group.category}</h5>
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

        renderRecentlyViewed() {
            if (this.recentlyViewed.length === 0) return '';

            return `
                <div class="cross-sell-section">
                    <h3>Recently Viewed</h3>
                    <div class="product-grid-mini" id="recently-viewed-grid">
                        <!-- Will be populated dynamically -->
                    </div>
                </div>
            `;
        }

        renderMiniCard(product) {
            return `
                <div class="mini-product-card" data-product-id="${product.id}">
                    <div class="mini-card-image">
                        <img src="${product.image || '/placeholder.jpg'}" alt="${product.name}">
                    </div>
                    <div class="mini-card-info">
                        <h4>${this.escapeHtml(product.name)}</h4>
                        ${product.brand ? `<p class="mini-brand">${this.escapeHtml(product.brand)}</p>` : ''}
                        <div class="mini-price">
                            ${product.sale_price ? `
                                <span class="sale">$${product.sale_price.toFixed(2)}</span>
                                <span class="original">$${product.price.toFixed(2)}</span>
                            ` : `
                                <span>$${product.price.toFixed(2)}</span>
                            `}
                        </div>
                        ${product.average_rating > 0 ? `
                            <div class="mini-rating">
                                ${this.renderStars(product.average_rating)}
                                <span>(${product.review_count})</span>
                            </div>
                        ` : ''}
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
            const variantButtons = this.modal.querySelectorAll('.variant-option:not(.disabled)');

            variantButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const variantId = button.dataset.variantId;
                    const variant = this.currentProduct.variants.find(v => v.id == variantId);

                    if (variant) {
                        this.currentVariant = variant;
                        this.updateURL(this.currentProduct.id, variantId);

                        // Re-render relevant sections
                        const detailsSection = this.modal.querySelector('.modal-details');
                        detailsSection.innerHTML = `
                            ${this.renderProductInfo(this.currentProduct)}
                            ${this.renderVariants(this.currentProduct)}
                            ${this.renderMerchants(this.currentProduct)}
                            ${this.renderCTA(this.currentProduct)}
                            ${this.renderShareButton(this.currentProduct)}
                            ${this.renderSpecifications(this.currentProduct)}
                        `;

                        // Re-attach handlers
                        this.attachVariantHandlers();
                        this.attachShareHandler();

                        // Update images if variant has specific images
                        if (variant.images && variant.images.length > 0) {
                            const mainImage = this.modal.querySelector('#modal-main-image');
                            mainImage.src = variant.images[0].url;
                        }
                    }
                });
            });
        }

        attachShareHandler() {
            const shareButton = this.modal.querySelector('#share-product');
            if (!shareButton) return;

            shareButton.addEventListener('click', async () => {
                const url = window.location.href;
                const title = this.currentProduct.name;
                const text = `Check out this product: ${title}`;

                // Try native share API first
                if (navigator.share) {
                    try {
                        await navigator.share({title, text, url});
                    } catch (error) {
                        if (error.name !== 'AbortError') {
                            this.copyToClipboard(url);
                        }
                    }
                } else {
                    this.copyToClipboard(url);
                }
            });
        }

        async copyToClipboard(text) {
            try {
                await navigator.clipboard.writeText(text);
                this.showToast('Link copied to clipboard!', 'success');
            } catch (error) {
                // Fallback
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                this.showToast('Link copied to clipboard!', 'success');
            }
        }

        showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('show');
            }, 100);

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
            } catch (error) {
                return [];
            }
        }

        addToRecentlyViewed(productId) {
            // Remove if already exists
            this.recentlyViewed = this.recentlyViewed.filter(id => id != productId);

            // Add to beginning
            this.recentlyViewed.unshift(productId);

            // Keep only last 6
            this.recentlyViewed = this.recentlyViewed.slice(0, 6);

            // Save
            localStorage.setItem('recently_viewed_products', JSON.stringify(this.recentlyViewed));
        }

        renderStars(rating) {
            let html = '';
            for (let i = 1; i <= 5; i++) {
                if (i <= rating) {
                    html += '<span class="star filled">★</span>';
                } else {
                    html += '<span class="star">☆</span>';
                }
            }
            return html;
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    // Initialize modal
    window.productModal = new ProductModal();

})();