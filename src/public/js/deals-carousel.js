// Deals Carousel Functionality
class DealsCarousel {
    constructor(selector) {
        this.carousel = document.querySelector(selector);
        if (!this.carousel) return;

        this.track = this.carousel.querySelector('.deals-carousel-track');
        this.leftArrow = this.carousel.querySelector('.carousel-arrow-left');
        this.rightArrow = this.carousel.querySelector('.carousel-arrow-right');
        this.dotsContainer = document.getElementById('carousel-dots');

        this.currentIndex = 0;
        this.itemsPerView = this.calculateItemsPerView();
        this.totalItems = this.track.children.length;
        this.maxIndex = Math.max(0, this.totalItems - this.itemsPerView);

        this.init();
    }

    init() {
        this.createDots();
        this.updateArrows();

        // Event listeners
        this.leftArrow?.addEventListener('click', () => this.scroll(-1));
        this.rightArrow?.addEventListener('click', () => this.scroll(1));

        // Touch support
        let startX = 0;
        this.track.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
        });

        this.track.addEventListener('touchend', (e) => {
            const endX = e.changedTouches[0].clientX;
            const diff = startX - endX;

            if (Math.abs(diff) > 50) {
                this.scroll(diff > 0 ? 1 : -1);
            }
        });

        // Resize handler
        window.addEventListener('resize', () => {
            this.itemsPerView = this.calculateItemsPerView();
            this.maxIndex = Math.max(0, this.totalItems - this.itemsPerView);
            this.scrollToIndex(Math.min(this.currentIndex, this.maxIndex));
        });
    }

    calculateItemsPerView() {
        const trackWidth = this.track.offsetWidth;
        const itemWidth = 250 + 16; // card width + gap
        return Math.floor(trackWidth / itemWidth);
    }

    scroll(direction) {
        const newIndex = Math.max(0, Math.min(this.maxIndex, this.currentIndex + direction));
        this.scrollToIndex(newIndex);
    }

    scrollToIndex(index) {
        this.currentIndex = index;
        const itemWidth = 250 + 16;
        const scrollAmount = this.currentIndex * itemWidth;

        this.track.scrollTo({
            left: scrollAmount,
            behavior: 'smooth'
        });

        this.updateDots();
        this.updateArrows();
    }

    createDots() {
        if (!this.dotsContainer) return;

        this.dotsContainer.innerHTML = '';
        const numDots = this.maxIndex + 1;

        for (let i = 0; i < numDots; i++) {
            const dot = document.createElement('div');
            dot.className = 'carousel-dot';
            if (i === 0) dot.classList.add('active');

            dot.addEventListener('click', () => this.scrollToIndex(i));
            this.dotsContainer.appendChild(dot);
        }
    }

    updateDots() {
        if (!this.dotsContainer) return;

        const dots = this.dotsContainer.querySelectorAll('.carousel-dot');
        dots.forEach((dot, index) => {
            dot.classList.toggle('active', index === this.currentIndex);
        });
    }

    updateArrows() {
        if (this.leftArrow) {
            this.leftArrow.style.opacity = this.currentIndex === 0 ? '0.5' : '1';
            this.leftArrow.style.pointerEvents = this.currentIndex === 0 ? 'none' : 'auto';
        }

        if (this.rightArrow) {
            this.rightArrow.style.opacity = this.currentIndex >= this.maxIndex ? '0.5' : '1';
            this.rightArrow.style.pointerEvents = this.currentIndex >= this.maxIndex ? 'none' : 'auto';
        }
    }
}

// Global functions for inline handlers
function scrollCarousel(direction) {
    if (window.dealsCarousel) {
        window.dealsCarousel.scroll(direction);
    }
}

async function refreshDeals() {
    const btn = document.querySelector('.refresh-deals-btn');
    const track = document.querySelector('.deals-carousel-track');

    if (!track) return;

    // Disable button and show loading
    btn.disabled = true;
    btn.innerHTML = '<svg class="spin" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10" stroke-opacity="0.25"/><path d="M12 2a10 10 0 0 1 10 10"/></svg> Refreshing...';

    try {
        const response = await fetch(`/api/deals/refresh`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        });

        const data = await response.json();

        if (data.deals && data.deals.length > 0) {
            // Rebuild carousel with new deals
            track.innerHTML = data.deals.map(deal => `
                <div class="deal-card">
                    <div class="deal-badge">
                        <span>${deal.discount_percentage}% OFF</span>
                    </div>
                    
                    <a href="/shop/details/${deal.slug}" class="deal-image-link">
                        <img src="${deal.image}" alt="${deal.title}" class="deal-image">
                    </a>
                    
                    <div class="deal-content">
                        <h3 class="deal-title">
                            <a href="/shop/details/${deal.slug}">${deal.title}</a>
                        </h3>
                        
                        ${deal.rating > 0 ? `
                            <div class="deal-rating">
                                <div class="stars" style="--rating: ${deal.rating}"></div>
                                <span class="review-count">(${deal.review_count})</span>
                            </div>
                        ` : ''}
                        
                        <div class="deal-prices">
                            <span class="was-price">Was £${parseFloat(deal.original_price).toFixed(2)}</span>
                            <span class="now-price">£${parseFloat(deal.sale_price).toFixed(2)}</span>
                        </div>
                        
                        <button class="deal-cta" onclick="viewDeal('${deal.slug}')">View Deal</button>
                    </div>
                </div>
            `).join('');

            // Reinitialize carousel
            window.dealsCarousel = new DealsCarousel('#deals-carousel');

            showToast('Deals refreshed successfully!', 'success');
        }
    } catch (error) {
        console.error('Error refreshing deals:', error);
        showToast('Failed to refresh deals. Please try again.', 'error');
    } finally {
        // Re-enable button
        btn.disabled = false;
        btn.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <polyline points="23 4 23 10 17 10"></polyline>
                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
            </svg>
            Refresh Deals
        `;
    }
}

function viewDeal(slug) {
    window.location.href = `/shop/details/${slug}`;
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    window.dealsCarousel = new DealsCarousel('#deals-carousel');
});