<style>
    .page-actions { display:flex; align-items:center; gap:1rem; margin:1.5rem 0; padding:1rem 0; border-top:1px solid #e5e7eb; border-bottom:1px solid #e5e7eb; }
    .like-button { display:inline-flex; align-items:center; gap:.5rem; padding:.75rem 1.5rem; background:#fff; border:2px solid #e5e7eb; border-radius:.5rem; cursor:pointer; font-size:1rem; font-weight:500; transition:all .2s ease; color:#6b7280; }
    .like-button:hover { border-color:#ef4444; color:#ef4444; background:#fef2f2; }
    .like-button.liked { background:#ef4444; border-color:#ef4444; color:white; }
    .like-button.liked:hover { background:#dc2626; border-color:#dc2626; }
    .like-button:disabled { opacity:.5; cursor:not-allowed; }
    .like-icon { font-size:1.25rem; transition:transform .2s ease; }
    .like-button:active .like-icon { transform:scale(1.2); }
    .like-count { font-weight:600; }
    .login-prompt { display:inline-flex; align-items:center; gap:.5rem; padding:.75rem 1.5rem; background:#f3f4f6; border-radius:.5rem; color:#6b7280; font-size:.875rem; }
    .login-prompt a { color:#667eea; font-weight:600; text-decoration:none; }
    .login-prompt a:hover { text-decoration:underline; }
    .view-count { display:flex; align-items:center; gap:.5rem; color:#6b7280; font-size:.875rem; }
</style>

<div class="page-actions">
    <?php if (isset($member) && $member): ?>
        <button class="like-button <?= $isLiked ? 'liked' : '' ?>" id="like-button" data-page-id="<?= $page->id ?>">
            <span class="like-icon"><?= $isLiked ? '❤️' : '🤍' ?></span>
            <span class="like-text"><?= $isLiked ? 'Liked' : 'Like' ?></span>
            <span class="like-count" id="like-count">(<?= $likeCount ?>)</span>
        </button>
    <?php else: ?>
        <div class="login-prompt">
            <span>❤️</span>
            <a href="/<?= htmlspecialchars($siteSlug ?? '') ?>/member/login?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>">Login to like this page</a>
            <span class="like-count">(<?= $likeCount ?> <?= $likeCount === 1 ? 'like' : 'likes' ?>)</span>
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
(() => {
    const initialise = (element, component) => {
        if (component.type !== 'page-actions') return;

        const button = element.querySelector('#like-button');
        if (!button || button.dataset.hydrated === 'true') return;

        button.dataset.hydrated = 'true';
        button.addEventListener('click', async () => {
            const endpoints = component.endpoints || {};
            const liked = button.classList.contains('liked');
            const endpoint = endpoints.like;
            if (!endpoint) return;

            button.disabled = true;

            try {
                const response = await fetch(endpoint, {
                    method: liked ? 'DELETE' : 'PUT',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: liked ? null : '{}'
                });
                const payload = await response.json();
                if (!response.ok || !payload.data) {
                    throw new Error(payload.message || 'Failed to update like');
                }

                const viewer = payload.data;
                button.classList.toggle('liked', viewer.liked);
                button.querySelector('.like-icon').textContent = viewer.liked ? '❤️' : '🤍';
                button.querySelector('.like-text').textContent = viewer.liked ? 'Liked' : 'Like';
                button.querySelector('#like-count').textContent = `(${viewer.like_count})`;
            } catch (error) {
                alert(error.message || 'An error occurred. Please try again.');
            } finally {
                button.disabled = false;
            }
        });
    };

    document.addEventListener('public-content:component-mounted', event => {
        initialise(event.detail.element, event.detail.component);
    });
})();
</script>
