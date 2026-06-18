<?php
$memberAuth = \App\Framework\Authorization\MemberAuth::getMember();
$isAuthenticated = $memberAuth !== null;
?>
<section class="comments-wrapper" data-comments-widget>
    <div class="comments-header">
        <h2 class="comments-title">
            <span id="comment-count">0</span>
            <span id="comment-count-label">Comments</span>
        </h2>
        <p class="comments-subtitle">Join the conversation and share your thoughts</p>
    </div>

    <div id="comments-loading" class="comments-loading">Loading comments…</div>
    <div class="comments-container" id="comments-container" aria-live="polite"></div>

    <nav id="comments-pagination" class="comments-pagination" aria-label="Comments pagination" hidden>
        <button type="button" class="comment-page-btn" data-comments-previous>Previous</button>
        <span data-comments-page></span>
        <button type="button" class="comment-page-btn" data-comments-next>Next</button>
    </nav>

    @include('components.comment-badge-section', [
        'isAuthenticated' => $isAuthenticated,
        'nextCommentBadge' => $nextCommentBadge ?? null,
    ])

    <div class="comment-form-wrapper">
        <?php if ($isAuthenticated): ?>
            <div class="authenticated-user-info">
                <span>Commenting as <strong><?= htmlspecialchars($memberAuth->first_name) ?> <?= htmlspecialchars($memberAuth->last_name) ?></strong></span>
            </div>
        <?php endif; ?>

        <h3 class="form-title"><?= $isAuthenticated ? 'Leave a Comment' : 'Join the Conversation' ?></h3>

        <form id="comment-form" class="comment-form" method="POST">
            <input type="hidden" name="page_id" value="<?= (int) $page->id ?>">
            <?php if ($isAuthenticated): ?>
                <input type="hidden" name="member_id" value="<?= (int) $memberAuth->id ?>">
            <?php else: ?>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="comment-name">Name <span class="required">*</span></label>
                        <input id="comment-name" name="name" type="text" required class="form-input">
                    </div>
                    <div class="form-group">
                        <label for="comment-email">Email <span class="required">*</span></label>
                        <input id="comment-email" name="email" type="email" required class="form-input">
                    </div>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="comment-content">Comment <span class="required">*</span></label>
                <textarea id="comment-content" name="content" required rows="6" maxlength="1000" class="form-textarea"></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-submit">Post Comment</button>
            </div>

            <div id="form-message" class="form-message" style="display:none"></div>
        </form>
    </div>
</section>
