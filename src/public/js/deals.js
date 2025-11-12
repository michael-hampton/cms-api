// Deals Page Functionality
let currentFilters = {
    tab: 'all',
    rating: [],
    category: [],
    brand: [],
    minPrice: null,
    maxPrice: null,
    discount: null,
    sort: 'discount:desc',
    page: 1,
    perPage: 24
};

let allCategories = [];
let allBrands = [];

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    loadStaticData();
    loadDeals();
});

function loadStaticData() {
    if (categories) {
        allCategories = categories;
    }

    if (brands) {
        allBrands = brands;
    }
}

// Tab Switching
function switchTab(tab) {
    // Update active tab
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tab);
    });

    currentFilters.tab = tab;
    currentFilters.page = 1;

    // NEW: Clear all filters when switching to "all"
    if (tab === 'all') {
        // Reset form inputs
        document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
        document.querySelectorAll('input[type="radio"]').forEach(radio => radio.checked = false);
        document.getElementById('min-price').value = '';
        document.getElementById('max-price').value = '';
        document.getElementById('custom-discount').value = '';

        // Reset filter object
        currentFilters.rating = [];
        currentFilters.category = [];
        currentFilters.brand = [];
        currentFilters.minPrice = null;
        currentFilters.maxPrice = null;
        currentFilters.discount = null;
        delete currentFilters.hasVoucher;
    }
    // Apply tab-specific filters for other tabs
    else if (tab === 'under25') {
        currentFilters.maxPrice = 25;
        document.getElementById('max-price').value = 25;
    } else if (tab === 'over50') {
        currentFilters.discount = 50;
        document.querySelector('input[name="discount"][value="50"]').checked = true;
    } else if (tab === 'vouchers') {
        currentFilters.hasVoucher = true;
    } else if (tab.startsWith('cat-')) {
        const categoryId = tab.replace('cat-', '');
        currentFilters.category = [categoryId];
        const checkbox = document.querySelector(`input[name="category[]"][value="${categoryId}"]`);
        if (checkbox) checkbox.checked = true;
    }

    loadDeals();
}

// Filter Functions
function toggleFilterSection(button) {
    const section = button.closest('.filter-section');
    section.classList.toggle('open');
}

function applyDealsFilters() {
    // Collect all filter values
    currentFilters.rating = Array.from(document.querySelectorAll('input[name="rating[]"]:checked'))
        .map(cb => parseInt(cb.value));

    currentFilters.category = Array.from(document.querySelectorAll('input[name="category[]"]:checked'))
        .map(cb => parseInt(cb.value));

    currentFilters.brand = Array.from(document.querySelectorAll('input[name="brand[]"]:checked'))
        .map(cb => parseInt(cb.value));

    const minPrice = document.getElementById('min-price')?.value;
    const maxPrice = document.getElementById('max-price')?.value;

    currentFilters.minPrice = minPrice ? parseFloat(minPrice) : null;
    currentFilters.maxPrice = maxPrice ? parseFloat(maxPrice) : null;

    const discountRadio = document.querySelector('input[name="discount"]:checked');
    currentFilters.discount = discountRadio ? parseInt(discountRadio.value) : null;

    const customDiscount = document.getElementById('custom-discount')?.value;
    if (customDiscount) {
        currentFilters.discount = parseInt(customDiscount);
    }

    currentFilters.sort = document.getElementById('deals-sort')?.value || 'discount:desc';
    currentFilters.page = 1;

    loadDeals();
}

function resetDealsFilters() {
    // Clear all checkboxes and inputs
    document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
    document.querySelectorAll('input[type="radio"]').forEach(radio => radio.checked = false);
    document.getElementById('min-price').value = '';
    document.getElementById('max-price').value = '';
    document.getElementById('custom-discount').value = '';
    document.getElementById('brand-search').value = '';

    // Reset filters object
    currentFilters = {
        tab: currentFilters.tab,
        rating: [],
        category: [],
        brand: [],
        minPrice: null,
        maxPrice: null,
        discount: null,
        sort: 'discount:desc',
        page: 1,
        perPage: 24
    };

    loadDeals();
}

function updatePriceRange() {
    const minRange = document.getElementById('price-range-min');
    const maxRange = document.getElementById('price-range-max');
    const minInput = document.getElementById('min-price');
    const maxInput = document.getElementById('max-price');

    if (minRange && maxRange && minInput && maxInput) {
        minInput.value = minRange.value;
        maxInput.value = maxRange.value;
    }
}

function applyCustomDiscount() {
    const customDiscount = document.getElementById('custom-discount').value;
    if (customDiscount) {
        // Uncheck radio buttons
        document.querySelectorAll('input[name="discount"]').forEach(radio => radio.checked = false);
    }
    applyDealsFilters();
}

function filterBrands() {
    const searchTerm = document.getElementById('brand-search').value.toLowerCase();
    const brandList = document.getElementById('brand-list');

    if (!brandList) return;

    const filteredBrands = allBrands.filter(brand =>
        brand.name.toLowerCase().includes(searchTerm)
    );

    brandList.innerHTML = filteredBrands.slice(0, 10).map(brand => `
        <label class="filter-option">
            <input type="checkbox" name="brand[]" value="${brand.id}" onchange="applyDealsFilters()">
            <span>${brand.name}</span>
        </label>
    `).join('');
}

function showMoreFilters(type) {
    const list = type === 'category' ? allCategories : allBrands;
    const container = document.getElementById(`${type}-list`);

    if (!container) {
        console.error(`Container not found: ${type}-list`);
        return;
    }

    if (!list || list.length === 0) {
        console.error(`No data available for: ${type}`);
        return;
    }

    container.innerHTML = list.map(item => `
        <label class="filter-option">
            <input type="checkbox" name="${type}[]" value="${item.id}" onchange="applyDealsFilters()">
            <span>${item.name}</span>
            ${item.product_count ? `<span class="count">(${item.product_count})</span>` : ''}
        </label>
    `).join('');

    // Find and remove "Show More" button - it might be sibling or in parent
    const parentSection = container.closest('.filter-section');
    if (parentSection) {
        const showMoreBtn = parentSection.querySelector('.show-more');
        if (showMoreBtn) {
            showMoreBtn.remove();
        }
    }
}

// Load Deals
async function loadDeals() {
    const grid = document.getElementById('deals-grid');
    const loading = document.getElementById('deals-loading');
    const empty = document.getElementById('deals-empty');
    const count = document.getElementById('deals-count');
    const pagination = document.getElementById('deals-pagination');

    // Show loading state
    grid.style.display = 'none';
    empty.style.display = 'none';
    loading.style.display = 'flex';

    try {
        const queryParams = new URLSearchParams();

        Object.keys(currentFilters).forEach(key => {
            const value = currentFilters[key];
            if (value !== null && value !== undefined) {
                if (Array.isArray(value)) {
                    value.forEach(v => queryParams.append(`${key}[]`, v));
                } else {
                    queryParams.append(key, value);
                }
            }
        });

        const response = await fetch(`/api/${site}/deals/filtered?${queryParams.toString()}`);
        const data = await response.json();

        loading.style.display = 'none';

        if (data.deals && data.deals.length > 0) {
            grid.style.display = 'grid';
            grid.innerHTML = data.deals.map(deal => createDealTile(deal)).join('');

            if (count) {
                const total = data.total || data.deals.length;
                count.textContent = `${total} ${total === 1 ? 'deal' : 'deals'}`;
            }

            if (pagination && data.pagination) {
                renderPagination(data.pagination);
            }
        } else {
            empty.style.display = 'flex';
        }
    } catch (error) {
        console.error('Error loading deals:', error);
        loading.style.display = 'none';
        empty.style.display = 'flex';
        showToast('Failed to load deals. Please try again.', 'error');
    }
}

function createDealTile(deal) {
    return `
        <div class="deal-tile">
            <div class="deal-tile-badge">${deal.discount_percentage}% OFF</div>
            
            <button class="deal-tile-wishlist-btn ${deal.in_wishlist ? 'active' : ''}" onclick="event.stopPropagation(); toggleWishlist(${deal.id}, this)">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                </svg>
            </button>
            
            <a href="/shop/details/${deal.slug}">
                <img src="${deal.image}" alt="${deal.title}" class="deal-tile-image">
            </a>
            
            <div style="padding: 1rem">
            
                <a href="/shop/details/${deal.slug}" class="deal-tile-title">${deal.title}</a>
                
                ${deal.rating > 0 ? `
                    <div class="deal-tile-rating">
                        <div class="stars" style="--rating: ${deal.rating}"></div>
                        <span class="count">(${deal.review_count})</span>
                    </div>
                ` : ''}
                
                <div class="deal-tile-prices">
                    <span class="deal-tile-was">Was £${parseFloat(deal.original_price).toFixed(2)}</span>
                    <span class="deal-tile-now">£${parseFloat(deal.sale_price).toFixed(2)}</span>
                </div>
                
                <div class="deal-tile-actions">
                    <button class="deal-tile-add-cart" onclick="event.stopPropagation(); addToCart(${deal.id})">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                        Add
                    </button>
                    <button class="deal-cta" onclick="window.location.href='/shop/details/${deal.slug}'">View</button>
                </div>
            </div>
        </div>
    `;
}

function renderPagination(pagination) {
    const container = document.getElementById('deals-pagination');
    if (!container) return;

    const { currentPage, totalPages, hasNext, hasPrev } = pagination;

    let html = '';

    // Previous button
    html += `
        <button ${!hasPrev ? 'disabled' : ''} onclick="goToPage(${currentPage - 1})">
            Previous
        </button>
    `;

    // Page numbers
    const maxVisible = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
    let endPage = Math.min(totalPages, startPage + maxVisible - 1);

    if (endPage - startPage < maxVisible - 1) {
        startPage = Math.max(1, endPage - maxVisible + 1);
    }

    if (startPage > 1) {
        html += `<button onclick="goToPage(1)">1</button>`;
        if (startPage > 2) {
            html += `<span>...</span>`;
        }
    }

    for (let i = startPage; i <= endPage; i++) {
        html += `
            <button class="${i === currentPage ? 'active' : ''}" onclick="goToPage(${i})">
                ${i}
            </button>
        `;
    }

    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            html += `<span>...</span>`;
        }
        html += `<button onclick="goToPage(${totalPages})">${totalPages}</button>`;
    }

    // Next button
    html += `
        <button ${!hasNext ? 'disabled' : ''} onclick="goToPage(${currentPage + 1})">
            Next
        </button>
    `;

    container.innerHTML = html;
}

function goToPage(page) {
    currentFilters.page = page;
    loadDeals();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Toast notification
function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    if (!toast) return;

    toast.textContent = message;
    toast.className = `toast toast-${type} show`;

    setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}

async function subscribeDealAlert() {
    const email = document.getElementById('deal-alert-email').value;

    if (!email || !isValidEmail(email)) {
        showToast('Please enter a valid email address', 'error');
        return;
    }

    try {
        const response = await fetch('/api/deal-alerts/subscribe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ email })
        });

        const data = await response.json();

        if (data.success) {
            showToast(data.message, 'success');
            document.getElementById('deal-alert-email').value = '';
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        console.error('Error subscribing:', error);
        showToast('Failed to subscribe. Please try again.', 'error');
    }
}

// Add these functions to deals.js

async function toggleWishlist(productId, button) {
    try {
        const isInWishlist = button.classList.contains('active');
        const url = isInWishlist
            ? `/api/${site}/wishlist/${productId}`
            : `/api/${site}/wishlist`;

        const method = isInWishlist ? 'DELETE' : 'POST';
        const body = isInWishlist ? null : JSON.stringify({ product_id: productId });

        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            },
            body: body
        });

        const data = await response.json();

        if (data.success) {
            button.classList.toggle('active');
            showToast(data.message, 'success');

            // Update wishlist count if available
            updateWishlistCount(data.count);
        } else {
            showToast(data.message || 'Failed to update wishlist', 'error');
        }
    } catch (error) {
        console.error('Error toggling wishlist:', error);
        showToast('Failed to update wishlist. Please try again.', 'error');
    }
}

async function addToCart(productId) {
    try {
        const response = await fetch(`/api/${site}/cart`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: productId,
                quantity: 1
            })
        });

        const data = await response.json();

        if (data.success) {
            showToast('Added to cart!', 'success');

            // Update cart count if available
            updateCartCount(data.count);
        } else {
            showToast(data.message || 'Failed to add to cart', 'error');
        }
    } catch (error) {
        console.error('Error adding to cart:', error);
        showToast('Failed to add to cart. Please try again.', 'error');
    }
}

function updateWishlistCount(count) {
    const wishlistCountEl = document.querySelector('.wishlist-count');
    if (wishlistCountEl) {
        wishlistCountEl.textContent = count;
        if (count > 0) {
            wishlistCountEl.style.display = 'inline-block';
        } else {
            wishlistCountEl.style.display = 'none';
        }
    }
}

function updateCartCount(count) {
    const cartCountEl = document.querySelector('.cart-count');
    if (cartCountEl) {
        cartCountEl.textContent = count;
        if (count > 0) {
            cartCountEl.style.display = 'inline-block';
        } else {
            cartCountEl.style.display = 'none';
        }
    }
}

function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}