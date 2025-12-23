// Testimonial Carousel JavaScript
let currentTestimonialIndex = 0;

// Search params
let searchTimeout;
let currentOffset = 0;
let currentQuery = '';
let currentCategory = '';
let currentAuthor = '';
let currentTag = '';
let currentTab = 'explore';
const searchLimit = 20;
let searchFirstLoad = true;

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

    // Real-time search as user types
    document.getElementById('searchInput').addEventListener('input', function (e) {
        const query = e.target.value.trim();
        const clearBtn = document.getElementById('searchClearBtn');

        // Show/hide clear button
        clearBtn.style.display = query ? 'flex' : 'none';

        // Debounce search
        clearTimeout(searchTimeout);

        if (query.length === 0) {
            clearSearch();
            return;
        }

        if (query.length < 2) return; // Wait for at least 2 characters

        searchTimeout = setTimeout(() => {
            currentQuery = query;
            currentOffset = 0;
            performSearch();
        }, 300); // 300ms delay
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

/******************* Search ***************************/


function toggleSearch() {
    const overlay = document.getElementById('searchOverlay');
    const input = document.getElementById('searchInput');

    if (overlay.classList.contains('active')) {
        overlay.classList.remove('active');
        // Reset search when closing
        clearSearch();
    } else {
        overlay.classList.add('active');
        setTimeout(() => input.focus(), 100);
        performSearch();
    }
}

function clearSearch() {
    const input = document.getElementById('searchInput');
    input.value = '';
    currentQuery = '';
    currentOffset = 0;
    currentCategory = '';
    currentAuthor = '';
    currentTag = '';
    searchFirstLoad = true

    // Hide clear button
    document.getElementById('searchClearBtn').style.display = 'none';

    // Show empty state
    document.getElementById('searchEmptyState').style.display = 'block';
    document.getElementById('searchResultsHeader').style.display = 'none';
    document.getElementById('searchLoadMore').style.display = 'none';
    document.getElementById('searchNoResults').style.display = 'none';

    // Clear results
    document.getElementById('searchResultsGrid').innerHTML = '';
    document.getElementById('shopResultsGrid').innerHTML = '';

    // Reset category filter
    document.querySelectorAll('.category-pill').forEach(pill => {
        pill.classList.remove('active');
        if (pill.dataset.category === '') {
            pill.classList.add('active');
        }
    });

    performSearch();
}

async function performSearch() {
    const loading = document.getElementById('searchLoading');
    const emptyState = document.getElementById('searchEmptyState');
    const resultsHeader = document.getElementById('searchResultsHeader');
    const noResults = document.getElementById('searchNoResults');
    const searchResultsContainer = document.getElementById('searchResultsGrid')

    // Show loading
    loading.style.display = 'block';
    emptyState.style.display = 'none';
    resultsHeader.style.display = 'none';
    noResults.style.display = 'none';
    searchResultsContainer.style.display = 'none'

    try {
        const params = new URLSearchParams({
            q: currentQuery,
            limit: searchLimit,
            offset: currentOffset,
            site_name: SITE
        });

        if (currentCategory) {
            params.append('category', currentCategory);
        }

        if (currentAuthor) {
            params.append('author', currentAuthor);
        }

        if (currentTag) {
            params.append('tag', currentTag);
        }

        const response = await fetch(`/api/${SITE}/pages/search?${params}`);
        const data = await response.json();


        // Hide loading
        loading.style.display = 'none';

        if (data.results && data.results.length > 0) {
            searchResultsContainer.style.display = 'grid'
            displayResults(data);

            // Show categories if they exist
            if (data.categories && data.categories.length > 0 && currentOffset === 0 && searchFirstLoad) {
                displayCategories(data.categories);
            }

            if (data.authors && data.authors.length > 0 && currentOffset === 0 && searchFirstLoad) {
                displayAuthors(data.authors);
            }

            if (data.tags && data.tags.length > 0 && currentOffset === 0 && searchFirstLoad) {
                displayTags(data.tags);
            }
        } else if (currentOffset === 0) {
            // No results found
            noResults.style.display = 'block';
        }
        searchFirstLoad = false;
    } catch (error) {
        console.error('Search error:', error);
        loading.style.display = 'none';
        noResults.style.display = 'block';
    }
}

function displayCategories(categories) {
    const container = document.getElementById('searchCategories');
    container.style.display = 'flex';

    // Keep "All" button and add category pills
    const allButton = container.querySelector('[data-category=""]');
    container.innerHTML = '';
    container.appendChild(allButton);

    categories.forEach(cat => {
        const pill = document.createElement('button');
        pill.className = 'category-pill';
        pill.dataset.category = cat.id;
        pill.textContent = cat.name;
        pill.onclick = () => filterByCategory(cat.id);
        container.appendChild(pill);
    });
}

function displayAuthors(authors) {
    const container = document.getElementById('searchAuthors');
    if (!container) return;

    container.style.display = 'flex';
    container.innerHTML = '';

    // Add "All Authors" button
    const allButton = document.createElement('button');
    allButton.className = 'filter-pill active';
    allButton.dataset.author = '';
    allButton.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> All Authors';
    allButton.onclick = () => filterByAuthor('');
    container.appendChild(allButton);

    authors.forEach(author => {
        const pill = document.createElement('button');
        pill.className = 'filter-pill';
        pill.dataset.author = author.id;
        pill.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> ${author.name}`;
        pill.onclick = () => filterByAuthor(author.id);
        container.appendChild(pill);
    });
}

function displayTags(tags) {
    const container = document.getElementById('searchTags');
    if (!container) return;

    container.style.display = 'flex';
    container.innerHTML = '';

    // Add "All Tags" button
    const allButton = document.createElement('button');
    allButton.className = 'filter-pill active';
    allButton.dataset.tag = '';
    allButton.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg> All Tags';
    allButton.onclick = () => filterByTag('');
    container.appendChild(allButton);

    tags.forEach(tag => {
        const pill = document.createElement('button');
        pill.className = 'filter-pill';
        pill.dataset.tag = tag.id;
        pill.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg> ${tag.name}`;
        pill.onclick = () => filterByTag(tag.id);
        container.appendChild(pill);
    });
}

function filterByCategory(categoryId) {
    currentCategory = categoryId;
    currentOffset = 0;

    // Update active state - FIX: Convert both to strings for comparison
    document.querySelectorAll('.category-pill').forEach(pill => {
        const pillCategory = pill.dataset.category;
        // Handle both empty string and actual category IDs
        if (categoryId === '' && pillCategory === '') {
            pill.classList.add('active');
        } else if (String(categoryId) === String(pillCategory)) {
            pill.classList.add('active');
        } else {
            pill.classList.remove('active');
        }
    });

    performSearch();
}

function filterByAuthor(authorId) {
    currentAuthor = authorId;
    currentOffset = 0;

    // Update active state
    document.querySelectorAll('[data-author]').forEach(pill => {
        pill.classList.remove('active');
        if (String(authorId) === String(pill.dataset.author)) {
            pill.classList.add('active');
        }
    });

    performSearch();
}

function filterByTag(tagId) {
    currentTag = tagId;
    currentOffset = 0;

    // Update active state
    document.querySelectorAll('[data-tag]').forEach(pill => {
        pill.classList.remove('active');
        if (String(tagId) === String(pill.dataset.tag)) {
            pill.classList.add('active');
        }
    });

    performSearch();
}

function displayResults(data) {
    const exploreGrid = document.getElementById('searchResultsGrid');
    const shopGrid = document.getElementById('shopResultsGrid');
    const resultsHeader = document.getElementById('searchResultsHeader');
    const loadMore = document.getElementById('searchLoadMore');
    const totalResults = document.getElementById('totalResults');
    const exploreCount = document.getElementById('exploreCount');
    const shopCount = document.getElementById('shopCount');
    const noResults = document.getElementById('searchNoResults');

    noResults.style.display = 'none';

    // Show results header
    resultsHeader.style.display = 'block';
    totalResults.textContent = data.total;

    // Clear grids if starting fresh
    if (currentOffset === 0) {
        exploreGrid.innerHTML = '';
        shopGrid.innerHTML = '';
    }

    // Separate results by page type
    let exploreResults = [];
    let shopResults = [];
    data.results.forEach(page => {
        if (page.page_type === 'deal' || page.page_type === 'product') {
            shopResults.push(page);
        } else {
            exploreResults.push(page);
        }

        page?.blocks.forEach((block) => {
            if (block.type === 'deal' || block.type === 'product') {
                shopResults.push(page);
            }
        })
    });

    // Update counts
    exploreCount.textContent = exploreResults.length + (currentOffset > 0 ? parseInt(exploreGrid.children.length) : 0);
    shopCount.textContent = shopResults.length + (currentOffset > 0 ? parseInt(shopGrid.children.length) : 0);

// Render explore results
    exploreResults.forEach(page => {
        exploreGrid.appendChild(createResultCard(page));
    });

// Render shop results
    shopResults.forEach(page => {
        shopGrid.appendChild(createResultCard(page, true));
    });

// Show/hide load more button
    if (data.has_more) {
        loadMore.style.display = 'block';
    } else {
        loadMore.style.display = 'none';
    }
}

function createResultCard(page, isShop = false) {
    const card = document.createElement('div');
    const isLocked = !page.can_view;
    const accessLevel = page.access_level || 'free';

    card.className = 'search-result-card' + (isLocked ? ' search-result-locked' : '');

    // Add click handler for the entire card
    if (!isLocked) {
        card.style.cursor = 'pointer';
        card.onclick = () => window.location.href = `/${SITE}${page.url || '/' + page.slug}`;
    } else {
        card.style.cursor = 'pointer';
        card.onclick = () => showSubscriptionModal(page.denial_reason, page.title);
    }

    const categoryName = page.categories && page.categories.length > 0
        ? (page.categories[0].name || '')
        : '';

    // Build access badge HTML
    let accessBadgeHTML = '';
    if (accessLevel !== 'free') {
        const badgeText = accessLevel === 'premium' ? 'Premium' : 'Members Only';
        const badgeIcon = accessLevel === 'premium'
            ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>'
            : '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/></svg>';

        accessBadgeHTML = `
            <span class="search-access-badge access-${accessLevel}">
                ${badgeIcon}
                ${badgeText}
            </span>
        `;
    }

    // Lock overlay for restricted content
    const lockOverlayHTML = isLocked ? `
        <div class="search-result-lock-overlay">
            <svg class="lock-icon" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
        </div>
    ` : '';

    card.innerHTML = `
        <div class="search-result-image-wrapper">
            ${page.image_url ? `
                <img src="${page.image_url}" 
                     alt="${page.title}" 
                     class="search-result-image ${isLocked ? 'image-blurred' : ''}"
                     style="${isLocked ? 'filter: blur(8px);' : ''}">
            ` : `
                <div class="search-result-image search-result-no-image">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                        <circle cx="8.5" cy="8.5" r="1.5"/>
                        <polyline points="21 15 16 10 5 21"/>
                    </svg>
                </div>
            `}
            ${lockOverlayHTML}
            ${accessBadgeHTML}
        </div>
        <div class="search-result-content">
            <div class="search-result-meta">
                ${categoryName ? `<span class="result-category">${categoryName}</span>` : ''}
                ${page.time_ago ? `<span class="result-time">${page.time_ago}</span>` : ''}
            </div>
            <h4 class="search-result-title ${isLocked ? 'title-locked' : ''}">${page.title}</h4>
            ${page.meta_description ? `
                <p class="search-result-excerpt ${isLocked ? 'excerpt-locked' : ''}">
                    ${page.meta_description.substring(0, 120)}${page.meta_description.length > 120 ? '...' : ''}
                </p>
            ` : ''}
            <div class="search-result-footer">
                ${page.authors && page.authors.length > 0 ? `
                    <span class="result-author">By ${page.authors[0].name}</span>
                ` : '<span></span>'}
                ${isLocked ? `
                    <button class="result-unlock-btn" onclick="event.stopPropagation(); showSubscriptionModal('${page.denial_reason}', '${page.title.replace(/'/g, "\\'")}');">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        ${getDenialButtonText(page.denial_reason)}
                    </button>
                ` : `
                    <a href="/${SITE}${page.url || '/' + page.slug}" 
                       class="result-read-more" 
                       onclick="event.stopPropagation();">
                        ${isShop ? 'View Deal' : 'Read More'}
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="9 18 15 12 9 6"></polyline>
                        </svg>
                    </a>
                `}
            </div>
        </div>
    `;

    return card;
}

function getDenialButtonText(denialReason) {
    switch (denialReason) {
        case 'member_required':
            return 'Sign Up';
        case 'subscription_required':
        case 'no_subscription_history':
            return 'Subscribe';
        case 'published_before_subscription':
            return 'Upgrade';
        case 'published_after_subscription':
            return 'Resubscribe';
        default:
            return 'Unlock';
    }
}

function showSubscriptionModal(denialReason, pageTitle) {
    // Implement your subscription modal logic here
    let message = '';

    switch (denialReason) {
        case 'member_required':
            message = 'Create a free account to access this content.';
            break;
        case 'subscription_required':
        case 'no_subscription_history':
            message = 'Subscribe to access premium content like "' + pageTitle + '"';
            break;
        case 'published_before_subscription':
            message = 'This article was published before your subscription. Upgrade to access our full archive.';
            break;
        case 'published_after_subscription':
            message = 'This article was published after your subscription ended. Resubscribe to continue reading.';
            break;
        default:
            message = 'Unlock this content with a subscription.';
    }

    // Example: Simple alert (replace with your actual modal)
    alert(message);

    // Or redirect to subscription page
    // window.location.href = `/${SITE}/subscribe?reason=${denialReason}`;
}

function switchTab(tabName) {
    currentTab = tabName;
// Update tab buttons
    document.querySelectorAll('.search-tab').forEach(tab => {
        tab.classList.toggle('active', tab.dataset.tab === tabName);
    });

// Update tab content
    document.querySelectorAll('.search-tab-content').forEach(content => {
        content.classList.toggle('active', content.id === tabName + 'Content');
    });
}

function loadMoreResults() {
    currentOffset += searchLimit;
    performSearch();
}

// Close search on escape key
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && document.getElementById('searchOverlay').classList.contains('active')) {
        toggleSearch();
    }
});


