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
    alert('here')
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

    // Apply tab-specific filters
    if (tab === 'under25') {
        currentFilters.maxPrice = 25;
        document.getElementById('max-price').value = 25;
    } else if (tab === 'over50') {
        currentFilters.discount = 50;
        document.querySelector('input[name="discount"][value="50"]').checked = true;
    } else if (tab === 'vouchers') {
        // Special handling for vouchers tab
        currentFilters.hasVoucher = true;
    } else if (tab.startsWith('cat-')) {
        const categoryId = tab.replace('cat-', '');
        currentFilters.category = [categoryId];
        document.querySelector(`input[name="category[]"][value="${categoryId}"]`).checked = true;
    } else {
        // Reset tab-specific filters for "all"
        delete currentFilters.hasVoucher;
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

    if (!container) return;

    container.innerHTML = list.map(item => `
        <label class="filter-option">
            <input type="checkbox" name="${type}[]" value="${item.id}" onchange="applyDealsFilters()">
            <span>${item.name}</span>
            ${item.product_count ? `<span class="count">(${item.product_count})</span>` : ''}
        </label>
    `).join('');

    // Remove "Show More" button
    const btn = container.nextElementSibling;
    if (btn && btn.classList.contains('show-more')) {
        btn.remove();
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
        <div class="deal-tile" onclick="window.location.href='/products/${deal.slug}'">
            <div class="deal-tile-badge">${deal.discount_percentage}% OFF</div>
            
            <img src="${deal.image}" alt="${deal.title}" class="deal-tile-image">
            
            <a href="/products/${deal.slug}" class="deal-tile-title">${deal.title}</a>
            
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