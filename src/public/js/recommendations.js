/**
 * recommendations.js
 *
 * Lazy-loads a "Recommended For You" / "Popular Right Now" strip below the
 * product grid pagination on both the products and deals pages.
 *
 * Requires: SITE global (set in the page), fetch API.
 */
(function () {
    'use strict';

    const CONTAINER_ID = 'recommendations-section';
    const ENDPOINT = `/api/${SITE}/recommendations/products`;

    // We wait until the grid has rendered before inserting the strip.
    // products.js / deals.js call window.onRecommendationsReady() after
    // their first loadProducts() completes. If that hook hasn't been set up
    // yet, fall back to DOMContentLoaded.
    function bootstrap() {
        const pagination = document.getElementById('pagination');
        if (!pagination) return;

        // Insert placeholder immediately so layout doesn't jump
        const section = document.createElement('section');
        section.id = CONTAINER_ID;
        section.className = 'recommendations-section';
        section.innerHTML = `
            <div class="recommendations-inner">
                <div class="recommendations-loading">
                    <div class="spinner"></div>
                </div>
            </div>
        `;
        pagination.insertAdjacentElement('afterend', section);

        // Collect product IDs already visible on the page to exclude them
        const visibleIds = collectVisibleProductIds();

        fetchRecommendations(visibleIds).then(data => {
            if (!data || !data.products || data.products.length === 0) {
                section.remove();
                return;
            }
            renderRecommendations(section, data);
        }).catch(() => section.remove());
    }

    function collectVisibleProductIds() {
        const cards = document.querySelectorAll('[data-product-id]');
        return Array.from(new Set(
            Array.from(cards).map(c => c.dataset.productId).filter(Boolean)
        ));
    }

    async function fetchRecommendations(excludeIds) {
        const params = new URLSearchParams({
            limit: 8,
            exclude: excludeIds.join(','),
        });
        const response = await fetch(`${ENDPOINT}?${params}`);
        if (!response.ok) throw new Error('Recommendations fetch failed');
        return response.json();
    }

    function renderRecommendations(section, data) {
        section.innerHTML = `
            <div class="recommendations-inner">
                <div class="recommendations-header">
                    <h2 class="recommendations-heading">
                        ${data.personalised
            ? `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>`
            : `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>`
        }
                        ${escapeHtml(data.heading)}
                    </h2>
                </div>
                <div class="recommendations-grid">
                    ${data.products.map(p => renderCard(p)).join('')}
                </div>
            </div>
        `;

        // Wire up card clicks to open the product modal if it exists
        section.querySelectorAll('.rec-card').forEach(card => {
            card.addEventListener('click', (e) => {
                if (e.target.closest('a, button')) return;
                const productId = card.dataset.productId;
                if (window.productModal && productId) {
                    window.productModal.open(productId);
                }
            });
        });
    }

    function renderCard(product) {
        const price = product.sale_price && product.sale_price < product.price
            ? product.sale_price
            : product.price;

        const hasDiscount = product.discount_percentage > 0;

        return `
            <div class="rec-card" data-product-id="${product.id}">
                <a href="/${SITE}/shop/details/${product.slug}" class="rec-card-image-link" tabindex="-1">
                    <div class="rec-card-image">
                        <img src="${escapeHtml(product.image || '/images/placeholder.jpg')}"
                             alt="${escapeHtml(product.name)}"
                             loading="lazy">
                        ${hasDiscount ? `<span class="rec-badge-sale">-${product.discount_percentage}%</span>` : ''}
                    </div>
                </a>
                <div class="rec-card-body">
                    ${product.brand ? `<div class="rec-brand">${escapeHtml(product.brand)}</div>` : ''}
                    <a href="/${SITE}/shop/details/${product.slug}" class="rec-name">${escapeHtml(product.name)}</a>
                    ${product.average_rating > 0 ? `
                        <div class="rec-rating">
                            ${renderStars(product.average_rating)}
                            <span class="rec-rating-count">(${product.review_count})</span>
                        </div>
                    ` : ''}
                    <div class="rec-price">
                        ${product.sale_price && product.sale_price < product.price ? `
                            <span class="rec-price-sale">$${formatPrice(product.sale_price)}</span>
                            <span class="rec-price-original">$${formatPrice(product.price)}</span>
                        ` : `
                            <span class="rec-price-current">$${formatPrice(product.price)}</span>
                        `}
                    </div>
                </div>
            </div>
        `;
    }

    function renderStars(rating) {
        let html = '<span class="rec-stars">';
        for (let i = 1; i <= 5; i++) {
            html += `<span class="${i <= Math.floor(rating) ? 'rec-star filled' : 'rec-star'}">★</span>`;
        }
        html += '</span>';
        return html;
    }

    function formatPrice(price) {
        return parseFloat(price).toFixed(2);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    // Allow products.js / deals.js to notify us after their grid renders
    // so we can collect the most up-to-date visible product IDs.
    window.refreshRecommendations = function () {
        const existing = document.getElementById(CONTAINER_ID);
        if (existing) existing.remove();
        bootstrap();
    };

    // Initial bootstrap once the DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap);
    } else {
        bootstrap();
    }
})();