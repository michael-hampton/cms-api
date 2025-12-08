// Testimonial Carousel JavaScript
let currentTestimonialIndex = 0;

function scrollTestimonials(button, direction) {
    const carousel = button.closest('.testimonial-carousel');
    const track = carousel.querySelector('[data-testimonial-track]');
    const items = track.querySelectorAll('.testimonial-item');
    const totalItems = items.length;

    if (direction === 'next') {
        currentTestimonialIndex = (currentTestimonialIndex + 1) % totalItems;
    } else {
        currentTestimonialIndex = (currentTestimonialIndex - 1 + totalItems) % totalItems;
    }

    updateTestimonialCarousel(carousel);
}

function scrollTestimonialsToIndex(button, index) {
    const carousel = button.closest('.testimonial-block').querySelector('.testimonial-carousel');
    currentTestimonialIndex = index;
    updateTestimonialCarousel(carousel);
}

function updateTestimonialCarousel(carousel) {
    const track = carousel.querySelector('[data-testimonial-track]');
    const indicators = carousel.querySelectorAll('.testimonial-indicator');

    // Update track position
    track.style.transform = `translateX(-${currentTestimonialIndex * 100}%)`;

    // Update indicators
    indicators.forEach((indicator, index) => {
        if (index === currentTestimonialIndex) {
            indicator.classList.add('active');
        } else {
            indicator.classList.remove('active');
        }
    });
}

// Auto-advance testimonials every 5 seconds
setInterval(() => {
    const carousel = document.querySelector('.testimonial-carousel');
    if (carousel) {
        const track = carousel.querySelector('[data-testimonial-track]');
        const items = track.querySelectorAll('.testimonial-item');
        currentTestimonialIndex = (currentTestimonialIndex + 1) % items.length;
        updateTestimonialCarousel(carousel);
    }
}, 5000);

let currentlyFlippedPageCard = null;

// Mobile menu toggle
document.addEventListener('DOMContentLoaded', function () {
    // Mobile menu toggle button
    const mobileToggle = document.querySelector('.mobile-menu-toggle');
    const megaMenuNav = document.querySelector('.mega-menu-nav');

    if (mobileToggle && megaMenuNav) {
        mobileToggle.addEventListener('click', function () {
            megaMenuNav.classList.toggle('active');
            this.setAttribute('aria-expanded',
                megaMenuNav.classList.contains('active')
            );
        });
    }

    // Mobile dropdown toggles
    const mobileDropdowns = document.querySelectorAll('.mega-menu-item.has-dropdown');

    if (window.innerWidth <= 1024) {
        mobileDropdowns.forEach(item => {
            const link = item.querySelector('.mega-menu-link');

            link.addEventListener('click', function (e) {
                e.preventDefault();
                item.classList.toggle('active');
            });
        });
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('.btn-flip') || e.target.closest('.btn-flip-back')) {
            const button = e.target.closest('.btn-flip') || e.target.closest('.btn-flip-back');
            const productId = button.dataset.productId;
            const card = document.querySelector(`[data-product-id=\"${productId}\"]`);
            if (card) {
                card.classList.toggle('flipped');
            }
        }
    });

    // Close menu when clicking outside
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.mega-menu-nav') &&
            !e.target.closest('.mobile-menu-toggle')) {
            megaMenuNav?.classList.remove('active');
        }
    });

    const carousels = document.querySelectorAll('[data-team-carousel]');

    carousels.forEach(carousel => {
        carousel.addEventListener('scroll', () => {
            updateTeamIndicators(carousel);
        });
    });

    // Attach flip event listeners to page grid product cards
    document.querySelectorAll('.page-grid-container .btn-flip').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const card = btn.closest('.product-card');
            flipPageCard(card);
        });
    });

    document.querySelectorAll('.page-grid-container .btn-flip-back').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const card = btn.closest('.product-card');
            flipBackPageCard(card);
        });
    });


    // Escape key to flip back
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && currentlyFlippedPageCard) {
            flipBackPageCard(currentlyFlippedPageCard);
        }
    });
});

function flipPageCard(cardElement) {
    // If clicking the same card, just toggle it
    if (currentlyFlippedPageCard === cardElement) {
        cardElement.classList.remove('flipped');
        currentlyFlippedPageCard = null;
        document.body.classList.remove('card-flipped');
        return;
    }

    // If another card is flipped, flip it back first
    if (currentlyFlippedPageCard) {
        currentlyFlippedPageCard.classList.remove('flipped');
    }

    // Flip the new card
    cardElement.classList.add('flipped');
    currentlyFlippedPageCard = cardElement;
    document.body.classList.add('card-flipped');
}

function flipBackPageCard(cardElement) {
    cardElement.classList.remove('flipped');
    if (currentlyFlippedPageCard === cardElement) {
        currentlyFlippedPageCard = null;
    }
    document.body.classList.remove('card-flipped');
}

function scrollTeamCarousel(button, direction) {
    const wrapper = button.closest('.team-carousel-wrapper');
    const carousel = wrapper.querySelector('.team-carousel');
    const cards = carousel.querySelectorAll('.person-block, .team-member');
    const cardWidth = cards[0].offsetWidth;
    const gap = 32; // 2rem gap
    const scrollAmount = cardWidth + gap;

    if (direction === 'prev') {
        carousel.scrollBy({left: -scrollAmount, behavior: 'smooth'});
    } else {
        carousel.scrollBy({left: scrollAmount, behavior: 'smooth'});
    }

    setTimeout(() => updateTeamIndicators(carousel), 300);
}

function scrollTeamToIndex(button, index) {
    const wrapper = button.closest('.team-carousel-wrapper');
    const carousel = wrapper.querySelector('.team-carousel');
    const cards = carousel.querySelectorAll('.person-block, .team-member');
    const cardWidth = cards[0].offsetWidth;
    const gap = 32;
    const scrollPosition = index * (cardWidth + gap);

    carousel.scrollTo({left: scrollPosition, behavior: 'smooth'});

    setTimeout(() => updateTeamIndicators(carousel), 300);
}

function updateTeamIndicators(carousel) {
    const wrapper = carousel.closest('.team-carousel-wrapper');
    const indicators = wrapper.querySelectorAll('.team-indicator');
    const cards = carousel.querySelectorAll('.person-block, .team-member');
    const scrollLeft = carousel.scrollLeft;
    const cardWidth = cards[0].offsetWidth;
    const gap = 32;
    const currentIndex = Math.round(scrollLeft / (cardWidth + gap));

    indicators.forEach((indicator, index) => {
        if (index === currentIndex) {
            indicator.classList.add('active');
        } else {
            indicator.classList.remove('active');
        }
    });
}