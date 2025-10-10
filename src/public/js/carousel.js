// Page Grid Carousel
function scrollPageGrid(button, direction) {
    const carousel = button.closest('.page-grid-carousel');
    const grid = carousel.querySelector('.page-grid');
    const cardWidth = grid.querySelector('.page-card').offsetWidth;
    const gap = 32; // spacing between cards
    const scrollAmount = cardWidth + gap;

    if (direction === 'prev') {
        grid.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
    } else {
        grid.scrollBy({ left: scrollAmount, behavior: 'smooth' });
    }

    updatePageGridIndicators(carousel);
}

function scrollPageGridToIndex(button, index) {
    const carousel = button.closest('.page-grid-carousel');
    const grid = carousel.querySelector('.page-grid');
    const cardWidth = grid.querySelector('.page-card').offsetWidth;
    const gap = 32;
    const scrollAmount = (cardWidth + gap) * index;

    grid.scrollTo({ left: scrollAmount, behavior: 'smooth' });
    updatePageGridIndicators(carousel);
}

function updatePageGridIndicators(carousel) {
    setTimeout(() => {
        const grid = carousel.querySelector('.page-grid');
        const indicators = carousel.querySelectorAll('.page-grid-indicator');
        const cardWidth = grid.querySelector('.page-card').offsetWidth;
        const gap = 32;
        const currentIndex = Math.round(grid.scrollLeft / (cardWidth + gap));

        indicators.forEach((indicator, index) => {
            indicator.classList.toggle('active', index === currentIndex);
        });
    }, 100);
}

// Testimonial Carousel
function scrollTestimonials(button, direction) {
    const carousel = button.closest('.testimonial-carousel');
    const track = carousel.querySelector('.testimonial-carousel-track');
    const itemWidth = track.querySelector('.testimonial-item').offsetWidth;
    const gap = 32;
    const scrollAmount = itemWidth + gap;

    if (direction === 'prev') {
        track.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
    } else {
        track.scrollBy({ left: scrollAmount, behavior: 'smooth' });
    }

    updateTestimonialIndicators(carousel);
}

function scrollTestimonialsToIndex(button, index) {
    const carousel = button.closest('.testimonial-carousel');
    const track = carousel.querySelector('.testimonial-carousel-track');
    const itemWidth = track.querySelector('.testimonial-item').offsetWidth;
    const gap = 32;
    const scrollAmount = (itemWidth + gap) * index;

    track.scrollTo({ left: scrollAmount, behavior: 'smooth' });
    updateTestimonialIndicators(carousel);
}

function updateTestimonialIndicators(carousel) {
    setTimeout(() => {
        const track = carousel.querySelector('.testimonial-carousel-track');
        const indicators = carousel.querySelectorAll('.testimonial-indicator');
        const itemWidth = track.querySelector('.testimonial-item').offsetWidth;
        const gap = 32;
        const currentIndex = Math.round(track.scrollLeft / (itemWidth + gap));

        indicators.forEach((indicator, index) => {
            indicator.classList.toggle('active', index === currentIndex);
        });
    }, 100);
}

// Update indicators on scroll
document.querySelectorAll('.page-grid').forEach(grid => {
    grid.addEventListener('scroll', () => {
        const carousel = grid.closest('.page-grid-carousel');
        if (carousel) updatePageGridIndicators(carousel);
    });
});

document.querySelectorAll('.testimonial-carousel-track').forEach(track => {
    track.addEventListener('scroll', () => {
        const carousel = track.closest('.testimonial-carousel');
        if (carousel) updateTestimonialIndicators(carousel);
    });
});