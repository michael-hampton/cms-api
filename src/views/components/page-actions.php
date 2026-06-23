<div class="page-actions" aria-label="Article engagement">
    <?php if (isset($member) && $member): ?>
        <button
            type="button"
            class="like-button <?= $isLiked ? 'liked' : '' ?>"
            id="like-button"
            data-page-id="<?= (int) $page->id ?>"
            aria-pressed="<?= $isLiked ? 'true' : 'false' ?>"
        >
            <svg class="like-icon" width="20" height="20" viewBox="0 0 24 24" fill="<?= $isLiked ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
            </svg>
            <span class="like-text"><?= $isLiked ? 'Liked' : 'Like' ?></span>
            <span class="page-action-count" id="like-count"><?= (int) $likeCount ?></span>
        </button>
    <?php else: ?>
        <a
            class="page-action-login"
            href="/<?= htmlspecialchars($siteSlug ?? '', ENT_QUOTES, 'UTF-8') ?>/member/login?redirect=<?= rawurlencode($_SERVER['REQUEST_URI'] ?? '/') ?>"
        >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
            </svg>
            <span>Sign in to like</span>
            <span class="page-action-count"><?= (int) $likeCount ?></span>
        </a>
    <?php endif; ?>

    <?php if (isset($viewCount)): ?>
        <div class="page-action-stat" aria-label="<?= (int) $viewCount ?> views">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path>
                <circle cx="12" cy="12" r="3"></circle>
            </svg>
            <span><?= number_format((int) $viewCount) ?></span>
            <span class="page-action-label"><?= (int) $viewCount === 1 ? 'view' : 'views' ?></span>
        </div>
    <?php endif; ?>
</div>

<style>
    .page-actions {
        display:flex;
        align-items:center;
        flex-wrap:wrap;
        gap:.75rem;
        margin:0 0 2rem;
        padding:.9rem;
        border:1px solid #e5e7eb;
        border-radius:14px;
        background:#fff;
        box-shadow:0 8px 24px rgba(15,23,42,.05);
    }
    .like-button,
    .page-action-login,
    .page-action-stat {
        display:inline-flex;
        align-items:center;
        gap:.55rem;
        min-height:42px;
        padding:.65rem .9rem;
        border-radius:999px;
        font-size:.9rem;
        font-weight:700;
        line-height:1;
    }
    .like-button {
        border:1px solid #fecdd3;
        background:#fff1f2;
        color:#be123c;
        cursor:pointer;
        transition:transform .2s ease,box-shadow .2s ease,background .2s ease,color .2s ease;
    }
    .like-button:hover {
        transform:translateY(-1px);
        box-shadow:0 6px 16px rgba(190,18,60,.14);
    }
    .like-button.liked {
        border-color:#e11d48;
        background:#e11d48;
        color:#fff;
    }
    .like-button:disabled { opacity:.55; cursor:wait; transform:none; }
    .like-icon { flex:0 0 auto; }
    .page-action-login {
        border:1px solid #dbeafe;
        background:#eff6ff;
        color:#1d4ed8;
        text-decoration:none;
        transition:transform .2s ease,box-shadow .2s ease;
    }
    .page-action-login:hover {
        transform:translateY(-1px);
        box-shadow:0 6px 16px rgba(37,99,235,.12);
    }
    .page-action-stat {
        color:#475569;
        background:#f8fafc;
        border:1px solid #e2e8f0;
    }
    .page-action-count {
        display:inline-grid;
        place-items:center;
        min-width:24px;
        height:24px;
        padding:0 .4rem;
        border-radius:999px;
        background:rgba(255,255,255,.72);
        color:inherit;
        font-size:.78rem;
    }
    .like-button.liked .page-action-count { background:rgba(255,255,255,.18); }
    .page-action-label { color:#94a3b8; font-weight:600; }
    @media (max-width:560px) {
        .page-actions { align-items:stretch; }
        .like-button,.page-action-login,.page-action-stat { justify-content:center; flex:1 1 auto; }
    }
</style>
