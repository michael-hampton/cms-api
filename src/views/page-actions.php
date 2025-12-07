<style>
    .page-actions {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin: 1.5rem 0;
        padding: 1rem 0;
        border-top: 1px solid #e5e7eb;
        border-bottom: 1px solid #e5e7eb;
    }

    .like-button {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        background: #fff;
        border: 2px solid #e5e7eb;
        border-radius: 0.5rem;
        cursor: pointer;
        font-size: 1rem;
        font-weight: 500;
        transition: all 0.2s ease;
        color: #6b7280;
    }

    .like-button:hover {
        border-color: #ef4444;
        color: #ef4444;
        background: #fef2f2;
    }

    .like-button.liked {
        background: #ef4444;
        border-color: #ef4444;
        color: white;
    }

    .like-button.liked:hover {
        background: #dc2626;
        border-color: #dc2626;
    }

    .like-button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .like-icon {
        font-size: 1.25rem;
        transition: transform 0.2s ease;
    }

    .like-button:active .like-icon {
        transform: scale(1.2);
    }

    .like-count {
        font-weight: 600;
    }

    .login-prompt {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        background: #f3f4f6;
        border-radius: 0.5rem;
        color: #6b7280;
        font-size: 0.875rem;
    }

    .login-prompt a {
        color: #667eea;
        font-weight: 600;
        text-decoration: none;
    }

    .login-prompt a:hover {
        text-decoration: underline;
    }

    .view-count {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #6b7280;
        font-size: 0.875rem;
    }
</style>

<!-- Page Actions (Like Button) -->
<div class="page-actions">
    <?php if (isset($member) && $member): ?>
        <button
                class="like-button <?= $isLiked ? 'liked' : '' ?>"
                id="like-button"
                data-page-id="<?= $page->id ?>"
        >
                                    <span class="like-icon">
                                        <?= $isLiked ? '❤️' : '🤍' ?>
                                    </span>
            <span class="like-text">
                                        <?= $isLiked ? 'Liked' : 'Like' ?>
                                    </span>
            <span class="like-count" id="like-count">
                                        (<?= $likeCount ?>)
                                    </span>
        </button>
    <?php else: ?>
        <div class="login-prompt">
            <span>❤️</span>
            <a href="<?= \App\Framework\Support\SiteContext::slug() ?>/member/login?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>">
                Login to like this page
            </a>
            <span class="like-count">
                                        (<?= $likeCount ?> <?= $likeCount === 1 ? 'like' : 'likes' ?>)
                                    </span>
        </div>
    <?php endif; ?>

    <?php if (isset($viewCount)): ?>
        <div class="view-count">
            <span>👁️</span>
            <span><?= $viewCount ?> <?= $viewCount === 1 ? 'view' : 'views' ?></span>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const likeButton = document.getElementById('like-button');

        if (likeButton) {
            likeButton.addEventListener('click', async function() {
                const pageId = this.dataset.pageId;
                const button = this;

                // Disable button during request
                button.disabled = true;

                try {
                    const response = await fetch(`/${site}/pages/like/${pageId}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const data = await response.json();

                    if (data.success) {
                        const isLiked = data.data.liked;
                        const likeCount = data.data.like_count;

                        // Update button appearance
                        if (isLiked) {
                            button.classList.add('liked');
                            button.querySelector('.like-icon').textContent = '❤️';
                            button.querySelector('.like-text').textContent = 'Liked';
                        } else {
                            button.classList.remove('liked');
                            button.querySelector('.like-icon').textContent = '🤍';
                            button.querySelector('.like-text').textContent = 'Like';
                        }

                        // Update like count
                        document.getElementById('like-count').textContent = `(${likeCount})`;
                    } else {
                        alert(data.message || 'Failed to toggle like');
                    }
                } catch (error) {
                    console.error('Error toggling like:', error);
                    alert('An error occurred. Please try again.');
                } finally {
                    // Re-enable button
                    button.disabled = false;
                }
            });
        }
    });
</script>
