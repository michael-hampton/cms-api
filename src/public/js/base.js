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
                const response = await fetch(`/${SITE}/default/newsletter/signup`, {
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

    const createAccountCheckbox = document.getElementById('createAccount');
    const accountFields = document.getElementById('accountFields');
    const firstName = document.getElementById('firstName');
    const lastName = document.getElementById('lastName');
    const password = document.getElementById('accountPassword');

    if (createAccountCheckbox) {
        createAccountCheckbox.addEventListener('change', function () {
            if (this.checked) {
                accountFields.classList.add('show');
                firstName.required = true;
                lastName.required = true;
                password.required = true;
            } else {
                accountFields.classList.remove('show');
                firstName.required = false;
                lastName.required = false;
                password.required = false;
                // Clear fields
                firstName.value = '';
                lastName.value = '';
                password.value = '';
            }
        });
    }

    const commentForm = document.getElementById('commentForm');
    const commentText = document.getElementById('commentText');
    const charCount = document.getElementById('commentCharCount');

    if (commentText) {
        commentText.addEventListener('input', function () {
            charCount.textContent = this.value.length;
        });
    }

    if (commentForm) {
        commentForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            const submitBtn = this.querySelector('.comment-submit');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Posting...';

            const formData = {
                pageUrl: document.getElementById('commentPageUrl').value,
                pageId: document.getElementById('commentPageId').value,
                name: document.getElementById('commentName').value,
                email: document.getElementById('commentEmail').value,
                comment: document.getElementById('commentText').value
            };

            try {
                const response = await fetch('/api/comments', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });

                const data = await response.json();

                if (response.ok) {
                    showMessage('commentMessage', 'Comment posted successfully!', 'success');
                    setTimeout(() => {
                        closeCommentModal();
                        showToast('Comment Posted!', 'Your comment has been submitted');
                    }, 1500);
                } else {
                    showMessage('commentMessage', data.message || 'Failed to post comment', 'error');
                }
            } catch (error) {
                console.error('Error posting comment:', error);
                showMessage('commentMessage', 'An error occurred. Please try again.', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Post Comment';
            }
        });
    }

    const newsletterForm = document.getElementById('newsletterForm');

    document.getElementById('newsletterForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();

        const email = document.getElementById('newsletterEmail').value;
        const createAccount = document.getElementById('createAccount').checked;
        const firstName = document.getElementById('firstName')?.value || '';
        const lastName = document.getElementById('lastName')?.value || '';
        const password = document.getElementById('accountPassword')?.value || '';
        const otherBrands = document.getElementById('otherBrands').checked;
        const trustedPartners = document.getElementById('trustedPartners').checked;

        const messageEl = document.getElementById('newsletterMessage');
        messageEl.style.display = 'none';
        messageEl.textContent = '';

        // Validate account fields if creating account
        if (createAccount) {
            if (!firstName || !lastName || !password) {
                showError('Please fill in all account fields');
                return;
            }

            if (password.length < 8) {
                showError('Password must be at least 8 characters');
                return;
            }
        }

        const submitBtn = document.getElementById('newsletterSubmitBtn');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = createAccount ? 'Creating Account...' : 'Subscribing...';

        try {
            const siteSlug = window.location.pathname.split('/')[1] || 'default';

            const requestBody = {
                email,
                other_brands: otherBrands,
                trusted_partners: trustedPartners
            };

            // Add account creation fields if checked
            if (createAccount) {
                requestBody.create_account = true;
                requestBody.first_name = firstName;
                requestBody.last_name = lastName;
                requestBody.password = password;
            }

            console.log('Sending request:', requestBody);

            const response = await fetch(`/${siteSlug}/default/newsletter/signup`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(requestBody)
            });

            const result = await response.json();
            console.log('Received response:', result);

            if (result.success) {
                subscribedEmail = email;
                availableNewsletters = result.data?.available_newsletters || [];

                // Update success message if account was created
                if (result.data?.account_created) {
                    document.getElementById('successTitle').textContent = 'Welcome! Your account is ready.';
                    document.getElementById('successMessage').textContent = 'Your newsletter subscription and account have been created successfully.';
                } else {
                    document.getElementById('successTitle').textContent = 'You are now subscribed.';
                    document.getElementById('successMessage').textContent = 'Your newsletter sign-up was successful.';
                }

                // Build newsletter cards dynamically
                buildNewsletterCards(availableNewsletters);

                // Show success step
                document.getElementById('newsletterStep1').style.display = 'none';
                document.getElementById('newsletterStep2').style.display = 'block';
            } else {
                showError(result.message || 'Subscription failed. Please try again.');
            }
        } catch (error) {
            console.error('Newsletter signup error:', error);
            showError('An error occurred. Please try again.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
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

function toggleShareDropdown(button) {
    event.stopPropagation();
    const dropdown = button.querySelector('.share-dropdown');
    const isActive = dropdown.classList.contains('active');

    // Close all other dropdowns
    document.querySelectorAll('.share-dropdown.active').forEach(d => {
        if (d !== dropdown) d.classList.remove('active');
    });

    dropdown.classList.toggle('active');

    // Close dropdown when clicking outside
    if (!isActive) {
        setTimeout(() => {
            document.addEventListener('click', closeAllDropdowns);
        }, 0);
    }
}

function closeAllDropdowns() {
    document.querySelectorAll('.share-dropdown.active').forEach(d => {
        d.classList.remove('active');
    });
    document.removeEventListener('click', closeAllDropdowns);
}

// Social Share Functions
function shareToFacebook(url) {
    const shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
    window.open(shareUrl, 'facebook-share', 'width=600,height=400');
    showToast('Shared to Facebook', 'Link opened in new window');
    closeAllDropdowns();
}

function shareToTwitter(url, text) {
    const shareUrl = `https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${encodeURIComponent(text)}`;
    window.open(shareUrl, 'twitter-share', 'width=600,height=400');
    showToast('Shared to Twitter', 'Link opened in new window');
    closeAllDropdowns();
}

function shareToLinkedIn(url) {
    const shareUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(url)}`;
    window.open(shareUrl, 'linkedin-share', 'width=600,height=400');
    showToast('Shared to LinkedIn', 'Link opened in new window');
    closeAllDropdowns();
}

function shareToWhatsApp(url, text) {
    const shareUrl = `https://wa.me/?text=${encodeURIComponent(text + ' ' + url)}`;
    window.open(shareUrl, 'whatsapp-share', 'width=600,height=400');
    showToast('Shared to WhatsApp', 'Link opened in new window');
    closeAllDropdowns();
}

function shareToReddit(url, title) {
    const shareUrl = `https://reddit.com/submit?url=${encodeURIComponent(url)}&title=${encodeURIComponent(title)}`;
    window.open(shareUrl, 'reddit-share', 'width=600,height=400');
    showToast('Shared to Reddit', 'Link opened in new window');
    closeAllDropdowns();
}

function copyLink(url) {
    navigator.clipboard.writeText(url).then(() => {
        showToast('Link Copied!', 'URL copied to clipboard');
        closeAllDropdowns();
    }).catch(err => {
        console.error('Failed to copy link:', err);
        showToast('Copy Failed', 'Could not copy link to clipboard', 'error');
    });
}

function openCommentModal(pageUrl, pageId) {
    const modal = document.getElementById('commentModal');
    document.getElementById('commentPageUrl').value = pageUrl;
    document.getElementById('commentPageId').value = pageId;
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeCommentModal() {
    const modal = document.getElementById('commentModal');
    modal.classList.remove('active');
    document.body.style.overflow = '';
    document.getElementById('commentForm').reset();
    document.getElementById('commentCharCount').textContent = '0';
    hideMessage('commentMessage');
}

function openNewsletterModal() {
    document.getElementById('newsletterModal').classList.add('show');
    document.body.style.overflow = 'hidden';
    // Reset to step 1
    document.getElementById('newsletterStep1').style.display = 'block';
    document.getElementById('newsletterStep2').style.display = 'none';
}

function closeNewsletterModal() {
    document.getElementById('newsletterModal').classList.remove('show');
    document.body.style.overflow = '';
}

// Utility Functions
function showMessage(elementId, message, type) {
    const messageEl = document.getElementById(elementId);
    messageEl.textContent = message;
    messageEl.className = `form-message ${type} active`;
}

function hideMessage(elementId) {
    const messageEl = document.getElementById(elementId);
    messageEl.classList.remove('active');
}

function showToast(title, message, type = 'success') {
    const toast = document.getElementById('toast');
    const toastTitle = document.getElementById('toastTitle');
    const toastMessage = document.getElementById('toastMessage');

    toastTitle.textContent = title;
    toastMessage.textContent = message;

    const icon = toast.querySelector('.toast-icon');
    icon.className = `toast-icon ${type}`;

    toast.classList.add('active');

    setTimeout(() => {
        toast.classList.remove('active');
    }, 3000);
}

function showError(message) {
    const messageEl = document.getElementById('newsletterMessage');
    messageEl.textContent = message;
    messageEl.style.display = 'block';
    messageEl.style.background = '#fee2e2';
    messageEl.style.color = '#991b1b';
    messageEl.style.padding = '12px 16px';
    messageEl.style.borderRadius = '8px';
    messageEl.style.marginBottom = '16px';
}

// Build newsletter cards dynamically
function buildNewsletterCards(newsletters) {
    const grid = document.querySelector('.newsletters-grid');
    if (!grid) return;

    // Keep first card, remove others
    const firstCard = grid.querySelector('.newsletter-card');
    grid.innerHTML = '';
    if (firstCard) {
        grid.appendChild(firstCard);
    }

    const gradients = [
        'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
        'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
        'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
        'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
        'linear-gradient(135deg, #30cfd0 0%, #330867 100%)',
        'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)'
    ];

    newsletters.forEach((newsletter, index) => {
        const card = document.createElement('div');
        card.className = 'newsletter-card';
        card.innerHTML = `
            <div class="newsletter-card-image" style="background: ${gradients[index % gradients.length]};">
                <div class="newsletter-card-badge">${escapeHtml(newsletter.frequency || 'REGULAR UPDATES')}</div>
            </div>
            <div class="newsletter-card-content">
                <h4>${escapeHtml(newsletter.title)}</h4>
                <p>${escapeHtml(newsletter.description || 'Stay updated with the latest content.')}</p>
                <button class="newsletter-card-btn" onclick="subscribeToNewsletter(${newsletter.id}, this)">
                    SIGNUP +
                </button>
            </div>
        `;
        grid.appendChild(card);
    });
}

async function subscribeToNewsletter(newsletterId, button) {
    if (!subscribedEmail) {
        alert('Email not found. Please try again.');
        return;
    }

    button.disabled = true;
    button.textContent = 'Subscribing...';

    try {
        const siteSlug = window.location.pathname.split('/')[1] || 'default';

        const response = await fetch(`/${siteSlug}/default/newsletter/signup`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                email: subscribedEmail,
                newsletter_id: newsletterId
            })
        });

        const result = await response.json();

        if (result.success) {
            button.innerHTML = `<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="20 6 9 17 4 12"/>
            </svg> SELECTED`;
            button.classList.add('selected');
            button.disabled = false;
            button.onclick = null;
        } else {
            button.textContent = 'Failed - Try Again';
            button.disabled = false;
        }
    } catch (error) {
        console.error('Subscription error:', error);
        button.textContent = 'Error - Try Again';
        button.disabled = false;
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modals on overlay click
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal-overlay')) {
        if (e.target.id === 'commentModal') {
            closeCommentModal();
        } else if (e.target.id === 'newsletterModal') {
            closeNewsletterModal();
        }
    }
});

// Close modals on escape key
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeCommentModal();
        closeNewsletterModal();
        closeAllDropdowns();
    }
});
