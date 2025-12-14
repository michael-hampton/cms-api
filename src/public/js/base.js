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

    const newsletterSignupModal = document.getElementById('newsletter-account-modal');
    const createAccountBtn = document.getElementById('footer-create-account-btn');
    const modalCloseBtn = document.getElementById('modal-close-btn');
    const modalCancelBtn = document.getElementById('modal-cancel-btn');
    const modalOverlay = document.getElementById('modal-overlay');

    function openNewsletterModal() {
        if (newsletterSignupModal) {
            alert('yes')
            newsletterSignupModal.style.display = 'block';
            document.body.style.overflow = 'hidden';

            const emailInput = document.getElementById('footer-newsletter-email');
            const modalEmailInput = document.getElementById('modal-email');
            if (emailInput && modalEmailInput) {
                modalEmailInput.value = pendingEmail || emailInput.value;
            }
        }
    }

    function closeNewsletterModal() {
        if (newsletterSignupModal) {
            newsletterSignupModal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }

    if (createAccountBtn) {
        createAccountBtn.addEventListener('click', openNewsletterModal);
    }

    if (modalCloseBtn) {
        modalCloseBtn.addEventListener('click', closeNewsletterModal);
    }

    if (modalCancelBtn) {
        modalCancelBtn.addEventListener('click', closeNewsletterModal);
    }

    if (modalOverlay) {
        modalOverlay.addEventListener('click', closeNewsletterModal);
    }

// Escape key to close modal
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal && modal.style.display === 'block') {
            closeNewsletterModal();
        }
    });

// Account creation form submission
    const accountForm = document.getElementById('footer-account-creation-form');
    if (accountForm) {
        accountForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(e.target);
            const password = formData.get('password');
            const passwordConfirm = formData.get('password_confirm');
            const messageEl = document.getElementById('modal-message');
            const submitBtn = document.getElementById('modal-submit-btn');

            // Validate passwords match
            if (password !== passwordConfirm) {
                messageEl.className = 'modal-message error';
                messageEl.textContent = 'Passwords do not match';
                return;
            }

            const data = {
                email: formData.get('email'),
                create_account: true,
                first_name: formData.get('first_name'),
                last_name: formData.get('last_name'),
                password: password
            };

            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating Account...';

            try {
                const response = await fetch(`/default/${SITE}/newsletter/signup`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                console.log('resut', result)

                if (result.success && result.data) {
                    if (result.data.account_created && result.data.logged_in) {
                        messageEl.className = 'modal-message success';
                        messageEl.textContent = '✓ Account created and you\'re logged in!';

                        setTimeout(() => {
                            closeNewsletterModal();
                            window.location.reload(); // Reload to update UI with logged-in state
                        }, 1500);
                    } else if (result.data.account_created) {
                        messageEl.className = 'modal-message success';
                        messageEl.textContent = '✓ Account created! Redirecting to login...';

                        setTimeout(() => {
                            window.location.href = '/member/login';
                        }, 1500);
                    } else if (result.data.account_exists) {
                        messageEl.className = 'modal-message error';
                        messageEl.textContent = 'You already have an account. Please log in.';
                    }
                } else {
                    messageEl.className = 'modal-message error';
                    messageEl.textContent = '✕ ' + (result.message || 'Failed to create account');
                }
            } catch (error) {
                console.error('Error:', error);
                messageEl.className = 'modal-message error';
                messageEl.textContent = '✕ An error occurred. Please try again.';
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Create Account';
            }
        });
    }
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