(function() {
    'use strict';

    // State
    const state = {
        productId: null,
        currentPage: 1
    };

    // DOM Elements
    const elements = {
        writeReviewBtn: document.getElementById('write-review-btn'),
        reviewModal: document.getElementById('review-modal'),
        closeModalBtn: document.getElementById('close-review-modal'),
        cancelReviewBtn: document.getElementById('cancel-review'),
        reviewForm: document.getElementById('review-form'),
        reviewsList: document.getElementById('reviews-list'),
        reviewsPagination: document.getElementById('reviews-pagination'),
        toast: document.getElementById('toast')
    };

    // Initialize
    function init() {
        // Get product ID from page
        const addToCartBtn = document.getElementById('add-to-cart-btn');
        if (addToCartBtn) {
            state.productId = parseInt(addToCartBtn.dataset.productId);
        }

        if (!state.productId) {
            console.error('Product ID not found');
            return;
        }

        attachEventListeners();
    }

    // Event Listeners
    function attachEventListeners() {
        // Open review modal
        if (elements.writeReviewBtn) {
            elements.writeReviewBtn.addEventListener('click', openReviewModal);
        }

        // Close modal
        if (elements.closeModalBtn) {
            elements.closeModalBtn.addEventListener('click', closeReviewModal);
        }

        if (elements.cancelReviewBtn) {
            elements.cancelReviewBtn.addEventListener('click', closeReviewModal);
        }

        // Close modal on outside click
        if (elements.reviewModal) {
            elements.reviewModal.addEventListener('click', (e) => {
                if (e.target === elements.reviewModal) {
                    closeReviewModal();
                }
            });
        }

        // Submit review form
        if (elements.reviewForm) {
            elements.reviewForm.addEventListener('submit', handleSubmitReview);
        }

        // Helpful buttons
        attachHelpfulListeners();

        // Pagination
        attachPaginationListeners();
    }

    // Open review modal
    function openReviewModal() {
        elements.reviewModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    // Close review modal
    function closeReviewModal() {
        elements.reviewModal.style.display = 'none';
        document.body.style.overflow = 'auto';
        elements.reviewForm.reset();
    }

    // Submit review
    async function handleSubmitReview(e) {
        e.preventDefault();

        const formData = new FormData(elements.reviewForm);
        const data = {
            rating: parseInt(formData.get('rating')),
            title: formData.get('title'),
            comment: formData.get('comment')
        };

        // Validation
        if (!data.rating) {
            showToast('Please select a rating', 'error');
            return;
        }

        if (!data.comment || data.comment.trim().length < 10) {
            showToast('Please write a review (minimum 10 characters)', 'error');
            return;
        }

        const submitBtn = elements.reviewForm.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';

        try {
            const response = await fetch(`/api/${SITE}/products/${state.productId}/reviews`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                showToast(result.message, 'success');
                closeReviewModal();

                // Reload page to show new review
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showToast(result.message, 'error');
            }
        } catch (error) {
            console.error('Error submitting review:', error);
            showToast('Failed to submit review. Please try again.', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit Review';
        }
    }

    // Attach helpful button listeners
    function attachHelpfulListeners() {
        document.querySelectorAll('.btn-helpful').forEach(btn => {
            btn.addEventListener('click', handleHelpfulClick);
        });
    }

    // Handle helpful/unhelpful click
    async function handleHelpfulClick(e) {
        const btn = e.currentTarget;
        const reviewId = parseInt(btn.dataset.reviewId);
        const isHelpful = btn.dataset.helpful === 'true';

        btn.disabled = true;

        try {
            const response = await fetch(`/api/${SITE}/reviews/${reviewId}/helpful`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ is_helpful: isHelpful })
            });

            const result = await response.json();

            if (result.success) {
                // Update counts in UI
                const reviewItem = btn.closest('.review-item');
                const helpfulCount = reviewItem.querySelector('.helpful-count');
                const unhelpfulCount = reviewItem.querySelector('.unhelpful-count');

                if (helpfulCount) helpfulCount.textContent = result.helpful_count;
                if (unhelpfulCount) unhelpfulCount.textContent = result.unhelpful_count;

                // Toggle active state
                const allBtns = reviewItem.querySelectorAll('.btn-helpful');
                allBtns.forEach(b => b.classList.remove('active'));

                if (result.message !== 'Vote removed') {
                    btn.classList.add('active');
                }

                showToast(result.message, 'success');
            } else {
                showToast(result.message, 'error');
            }
        } catch (error) {
            console.error('Error marking review helpful:', error);
            showToast('An error occurred', 'error');
        } finally {
            btn.disabled = false;
        }
    }

    // Attach pagination listeners
    function attachPaginationListeners() {
        if (elements.reviewsPagination) {
            elements.reviewsPagination.querySelectorAll('button').forEach(btn => {
                btn.addEventListener('click', () => {
                    const page = parseInt(btn.dataset.page);
                    loadReviews(page);
                });
            });
        }
    }

    // Load reviews for a specific page
    async function loadReviews(page) {
        try {
            const response = await fetch(`/api/${SITE}/products/${state.productId}/reviews?page=${page}&per_page=5`);
            const data = await response.json();

            if (data.reviews) {
                renderReviews(data.reviews);
                renderPagination(data.pagination);

                // Scroll to reviews
                elements.reviewsList.scrollIntoView({ behavior: 'smooth' });
            }
        } catch (error) {
            console.error('Error loading reviews:', error);
            showToast('Failed to load reviews', 'error');
        }
    }

    // Render reviews
    function renderReviews(reviews) {
        if (reviews.length === 0) {
            elements.reviewsList.innerHTML = '<p class="no-reviews">No reviews yet. Be the first to review this product!</p>';
            return;
        }

        const html = reviews.map(review => `
            <div class="review-item" data-review-id="${review.id}">
                <div class="review-header">
                    <div class="review-author">
                        <strong>${escapeHtml(review.author_name)}</strong>
                        ${review.is_verified_purchase ? `
                            <span class="verified-badge">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                </svg>
                                Verified Purchase
                            </span>
                        ` : ''}
                    </div>
                    <div class="review-date">${escapeHtml(review.formatted_date)}</div>
                </div>

                <div class="review-rating">
                    ${generateStars(review.rating)}
                </div>

                ${review.title ? `<h4 class="review-title">${escapeHtml(review.title)}</h4>` : ''}

                <p class="review-comment">${escapeHtml(review.comment).replace(/\n/g, '<br>')}</p>

                <div class="review-actions">
                    <button class="btn-helpful" data-review-id="${review.id}" data-helpful="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                        </svg>
                        Helpful (<span class="helpful-count">${review.helpful_count}</span>)
                    </button>
                    <button class="btn-helpful" data-review-id="${review.id}" data-helpful="false">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"></path>
                        </svg>
                        Not Helpful (<span class="unhelpful-count">${review.unhelpful_count}</span>)
                    </button>
                </div>
            </div>
        `).join('');

        elements.reviewsList.innerHTML = html;
        attachHelpfulListeners();
    }

    // Render pagination
    function renderPagination(pagination) {
        if (!pagination || pagination.last_page <= 1) {
            if (elements.reviewsPagination) {
                elements.reviewsPagination.innerHTML = '';
            }
            return;
        }

        let html = '';

        if (pagination.current_page > 1) {
            html += `<button class="btn btn-secondary" data-page="${pagination.current_page - 1}">Previous</button>`;
        }

        for (let i = 1; i <= pagination.last_page; i++) {
            const isActive = i === pagination.current_page;
            html += `<button class="btn ${isActive ? 'btn-primary' : 'btn-secondary'}" data-page="${i}">${i}</button>`;
        }

        if (pagination.current_page < pagination.last_page) {
            html += `<button class="btn btn-secondary" data-page="${pagination.current_page + 1}">Next</button>`;
        }

        if (elements.reviewsPagination) {
            elements.reviewsPagination.innerHTML = html;
            attachPaginationListeners();
        }
    }

    // Generate star HTML
    function generateStars(rating) {
        let html = '';
        for (let i = 1; i <= 5; i++) {
            const filled = i <= rating ? 'filled' : '';
            html += `
                <svg class="star ${filled}" width="16" height="16" viewBox="0 0 24 24">
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                </svg>
            `;
        }
        return html;
    }

    // Utility functions
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showToast(message, type = 'info') {
        if (elements.toast) {
            elements.toast.textContent = message;
            elements.toast.className = `toast ${type} show`;

            setTimeout(() => {
                elements.toast.classList.remove('show');
            }, 3000);
        }
    }

    // Start the app
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();