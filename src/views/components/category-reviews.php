<style>
    /* Review Styling */
    .reviews-masonry {
        column-count: 2;
        column-gap: 1.5rem;
    }

    .review-bubble {
        break-inside: avoid;
        background: #f9fafb;
        padding: 1.5rem;
        border-radius: 16px;
        margin-bottom: 1.5rem;
        border: 1px solid #e5e7eb;
    }

    .review-stars {
        color: #fbbf24;
        margin-bottom: 0.5rem;
    }

    .review-text {
        font-style: italic;
        color: #374151;
        margin-bottom: 1rem;
    }

    .review-footer {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .review-prod-thumb {
        width: 40px;
        height: 40px;
        border-radius: 4px;
        object-fit: cover;
    }

    .reviewer-name {
        display: block;
        font-weight: 600;
        font-size: 0.875rem;
    }

    .review-prod-name {
        font-size: 0.75rem;
        color: #9ca3af;
    }

    @media (max-width: 640px) {
        .reviews-masonry {
            column-count: 1;
        }
    }
</style>

<section class="category-reviews-section">
    <h3 class="reviews-header">Recent Feedback</h3>
    <div class="reviews-masonry">
        <?php foreach ($reviews as $review): ?>
            <div class="review-bubble">
                <div class="review-stars">
                    <?= str_repeat('★', $review['rating']) ?><?= str_repeat('☆', 5 - $review['rating']) ?>
                </div>
                <p class="review-text">"<?= htmlspecialchars($review['comment']) ?>"</p>
                <div class="review-footer">
                    <img src="<?= $review['product']['image'] ?>" class="review-prod-thumb">
                    <div class="review-meta">
                        <span class="reviewer-name"><?= $review['user_name'] ?></span>
                        <span class="review-prod-name">on <?= $review['product']['name'] ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>