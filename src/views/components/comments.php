<?php
// Add helper function for date formatting
function formatCommentDate($date)
{
    $timestamp = strtotime($date);
    $now = time();
    $diff = $now - $timestamp;

    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $timestamp);
    }
}

// Check if user is authenticated
$memberAuth = \App\Framework\Authorization\MemberAuth::getMember();
$isAuthenticated = $memberAuth !== null;
?>


    <section class="comments-wrapper">
        <div class="comments-header">
            <h2 class="comments-title">
                <svg class="comments-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                <span id="comment-count"><?= count($comments ?? []) ?></span>
                <?= count($comments ?? []) === 1 ? 'Comment' : 'Comments' ?>
            </h2>
            <p class="comments-subtitle">Join the conversation and share your thoughts</p>
        </div>

        <div class="comments-container" id="comments-container">
            <?php if (isset($comments) && !empty($comments)): ?>
                <?php foreach ($comments as $comment): ?>
                    <article class="comment-card" data-comment-id="<?= $comment->id ?>">
                        <div class="comment-avatar">
                            <?php if ($comment->member && $comment->member->avatar): ?>
                                <img src="<?= htmlspecialchars($comment->member->avatar) ?>"
                                     alt="<?= htmlspecialchars($comment->name) ?>" class="avatar-image">
                            <?php else: ?>
                                <div class="avatar-circle">
                                    <?= strtoupper(substr($comment->member->first_name, 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="comment-body">
                            <div class="comment-meta">
                                <?php if (!empty($comment->name)): ?>
                                <h4 class="comment-author">
                                    <?= htmlspecialchars($comment->name) ?>
                                    <?php if ($comment->member_id): ?>
                                        <svg class="verified-badge" width="16" height="16" viewBox="0 0 24 24"
                                             fill="currentColor">
                                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    <?php endif; ?>
                                </h4>
                                <?php endif; ?>
                                <time class="comment-date" datetime="<?= $comment->created_at ?>">
                                    <?= formatCommentDate($comment->created_at) ?>
                                </time>
                            </div>
                            <div class="comment-content">
                                <?= nl2br(htmlspecialchars($comment->content)) ?>
                            </div>
                            <div class="comment-actions">
                                <button class="comment-action-btn reply-btn" data-comment-id="<?= $comment->id ?>">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                                    </svg>
                                    Reply
                                </button>
                                <button class="comment-action-btn like-btn" data-comment-id="<?= $comment->id ?>">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                                    </svg>
                                    <span class="like-count">0</span>
                                </button>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-comments">
                    <svg class="no-comments-icon" width="48" height="48" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                    <h3>No comments yet</h3>
                    <p>Be the first to share your thoughts!</p>
                </div>
            <?php endif; ?>
        </div>

        @include('components.comment-badge-section', ['isAuthenticated' => $isAuthenticated, 'nextCommentBadge' =>
        $nextCommentBadge ?? null])

        <!-- Comment Form -->
        <div class="comment-form-wrapper">
            <?php if ($isAuthenticated): ?>
                <div class="authenticated-user-info">
                    <div class="user-badge">
                        <?php if ($memberAuth->avatar): ?>
                            <img src="<?= htmlspecialchars($memberAuth->avatar) ?>"
                                 alt="<?= htmlspecialchars($memberAuth->name) ?>" class="user-badge-avatar">
                        <?php else: ?>
                            <div class="user-badge-placeholder">
                                <?= strtoupper(substr($memberAuth->first_name, 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <span class="user-badge-name">Commenting as <strong><?= htmlspecialchars($memberAuth->first_name) ?> <?= htmlspecialchars($memberAuth->last_name) ?></strong></span>
                    </div>
                </div>
            <?php endif; ?>

            <h3 class="form-title"><?= $isAuthenticated ? 'Leave a Comment' : 'Join the Conversation' ?></h3>

            <form id="comment-form" class="comment-form" method="POST" action="/comments">
                <input type="hidden" name="page_id" value="<?= $page->id ?>">
                <?php if ($isAuthenticated): ?>
                    <input type="hidden" name="member_id" value="<?= $memberAuth->id ?>">
                <?php endif; ?>

                <?php if (!$isAuthenticated): ?>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="comment-name">
                                Name <span class="required">*</span>
                            </label>
                            <input
                                    type="text"
                                    id="comment-name"
                                    name="name"
                                    placeholder="Your name"
                                    required
                                    class="form-input"
                            >
                        </div>

                        <div class="form-group">
                            <label for="comment-email">
                                Email <span class="required">*</span>
                            </label>
                            <input
                                    type="email"
                                    id="comment-email"
                                    name="email"
                                    placeholder="your@email.com"
                                    required
                                    class="form-input"
                            >
                            <small class="form-help">Your email will not be published</small>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="comment-content">
                        Comment <span class="required">*</span>
                    </label>
                    <textarea
                            id="comment-content"
                            name="content"
                            placeholder="Share your thoughts..."
                            required
                            rows="6"
                            class="form-textarea"
                    ></textarea>
                    <div class="character-count">
                        <span id="char-count">0</span> / 1000 characters
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M22 2L11 13"></path>
                            <path d="M22 2L15 22L11 13L2 9L22 2Z"></path>
                        </svg>
                        Post Comment
                    </button>
                </div>

                <div id="form-message" class="form-message" style="display: none;"></div>
            </form>
        </div>
    </section>

<style>
    /* Modern Comment Section Styles */
    .comments-wrapper {
        margin: 4rem 0;
        padding: 3rem 0;
        border-top: 2px solid #e5e7eb;
    }

    .comments-header {
        margin-bottom: 3rem;
        text-align: center;
    }

    .comments-title {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        font-size: 2rem;
        font-weight: 700;
        color: #111827;
        margin-bottom: 0.5rem;
    }

    .comments-icon {
        stroke-width: 2;
        color: #3b82f6;
    }

    .comments-subtitle {
        color: #6b7280;
        font-size: 1.125rem;
    }

    .comments-container {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        margin-bottom: 3rem;
    }

    .comment-card {
        display: flex;
        gap: 1.25rem;
        padding: 2rem;
        background: #ffffff;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        transition: all 0.3s ease;
    }

    .comment-card:hover {
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        border-color: #3b82f6;
        transform: translateY(-2px);
    }

    .comment-avatar {
        flex-shrink: 0;
    }

    .avatar-circle {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        font-weight: 600;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .comment-body {
        flex: 1;
        min-width: 0;
    }

    .comment-meta {
        display: flex;
        align-items: baseline;
        gap: 1rem;
        margin-bottom: 0.75rem;
        flex-wrap: wrap;
    }

    .comment-author {
        font-size: 1.125rem;
        font-weight: 600;
        color: #111827;
        margin: 0;
    }

    .comment-date {
        font-size: 0.875rem;
        color: #6b7280;
    }

    .comment-content {
        color: #374151;
        line-height: 1.7;
        font-size: 1rem;
        margin-bottom: 1rem;
    }

    .comment-actions {
        display: flex;
        gap: 1rem;
        align-items: center;
    }

    .comment-action-btn {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: transparent;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        color: #6b7280;
        font-size: 0.875rem;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .comment-action-btn:hover {
        background: #f3f4f6;
        border-color: #3b82f6;
        color: #3b82f6;
    }

    .comment-action-btn svg {
        stroke-width: 2;
    }

    .like-btn.liked {
        background: #dbeafe;
        border-color: #3b82f6;
        color: #3b82f6;
    }

    .like-btn.liked svg {
        fill: #3b82f6;
    }

    .no-comments {
        text-align: center;
        padding: 4rem 2rem;
        background: #f9fafb;
        border-radius: 12px;
        border: 2px dashed #d1d5db;
    }

    .no-comments-icon {
        stroke-width: 1.5;
        color: #9ca3af;
        margin-bottom: 1rem;
    }

    .no-comments h3 {
        font-size: 1.5rem;
        color: #374151;
        margin-bottom: 0.5rem;
    }

    .no-comments p {
        color: #6b7280;
        font-size: 1rem;
    }

    /* Comment Form Styles */
    .comment-form-wrapper {
        background: #f8f9fa;
        padding: 3rem;
        border-radius: 16px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        border: 2px solid #e9ecef;
    }

    .form-title {
        color: #212529;
        font-size: 1.75rem;
        margin-bottom: 2rem;
        text-align: center;
    }

    .comment-form {
        max-width: 800px;
        margin: 0 auto;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .form-group label {
        color: #495057;
        font-weight: 600;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .required {
        color: #dc3545;
    }

    .form-input,
    .form-textarea {
        padding: 0.875rem 1.25rem;
        border: 2px solid #dee2e6;
        border-radius: 8px;
        background: white;
        color: #212529;
        font-size: 1rem;
        transition: all 0.3s ease;
        font-family: inherit;
    }

    .form-input:focus,
    .form-textarea:focus {
        outline: none;
        border-color: #6c757d;
        box-shadow: 0 0 0 3px rgba(108, 117, 125, 0.1);
    }

    .form-textarea {
        resize: vertical;
        min-height: 120px;
    }

    .form-help {
        color: #6c757d;
        font-size: 0.75rem;
        margin-top: 0.25rem;
    }

    .character-count {
        text-align: right;
        color: #6c757d;
        font-size: 0.875rem;
        margin-top: 0.5rem;
    }

    #char-count {
        font-weight: 600;
        color: #495057;
    }

    .form-actions {
        display: flex;
        justify-content: center;
        margin-top: 2rem;
    }

    .btn-submit {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 1rem 2.5rem;
        background: #495057;
        color: white;
        border: none;
        border-radius: 50px;
        font-size: 1.125rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 6px rgba(73, 80, 87, 0.3);
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 12px rgba(73, 80, 87, 0.4);
        background: #343a40;
    }

    .btn-submit:active {
        transform: translateY(0);
    }

    .btn-submit svg {
        stroke-width: 2;
    }

    .form-message {
        margin-top: 1.5rem;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        text-align: center;
        font-weight: 500;
        animation: slideIn 0.3s ease;
    }

    .form-message.success {
        background: #d4edda;
        border: 2px solid #c3e6cb;
        color: #155724;
    }

    .form-message.error {
        background: #f8d7da;
        border: 2px solid #f5c6cb;
        color: #721c24;
    }

    .form-message.pending {
        background: #fff3cd;
        border: 2px solid #ffeaa7;
        color: #856404;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Loading State */
    .btn-submit.loading {
        position: relative;
        color: transparent;
        pointer-events: none;
    }

    .btn-submit.loading::after {
        content: '';
        position: absolute;
        width: 20px;
        height: 20px;
        top: 50%;
        left: 50%;
        margin-left: -10px;
        margin-top: -10px;
        border: 3px solid rgba(102, 126, 234, 0.3);
        border-top-color: #667eea;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .comments-wrapper {
            padding: 2rem 0;
        }

        .comments-title {
            font-size: 1.5rem;
        }

        .comment-card {
            flex-direction: column;
            padding: 1.5rem;
        }

        .avatar-circle {
            width: 40px;
            height: 40px;
            font-size: 1rem;
        }

        .comment-form-wrapper {
            padding: 2rem 1.5rem;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }

        .btn-submit {
            width: 100%;
            justify-content: center;
        }
    }

    /* Dark Mode Support */
    @media (prefers-color-scheme: dark) {
        .comments-wrapper {
            border-top-color: #374151;
        }

        .comment-card {
            background: #1f2937;
            border-color: #374151;
        }

        .comment-card:hover {
            background: #111827;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .comment-author {
            color: #f9fafb;
        }

        .comment-content {
            color: #d1d5db;
        }

        .no-comments {
            background: #1f2937;
            border-color: #4b5563;
        }

        .no-comments h3 {
            color: #f9fafb;
        }
    }

    .authenticated-user-info {
        margin-bottom: 1.5rem;
    }

    .user-badge {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 20px;
        background: white;
        border-radius: 8px;
        border: 2px solid #dee2e6;
    }

    .user-badge-name {
        color: #495057;
        font-size: 0.95rem;
    }

    .user-badge-name strong {
        font-weight: 700;
        color: #212529;
    }

    .avatar-image {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #e5e7eb;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .verified-badge {
        color: #2563eb;
        margin-left: 4px;
    }
</style>

    <script>
        site = '<?= \App\Framework\Support\SiteContext::slug()?>';
        const isAuthenticated = <?= $isAuthenticated ? 'true' : 'false' ?>;

        // Enhanced Comment Form Functionality
        (function () {
            const form = document.getElementById('comment-form');
            const textarea = document.getElementById('comment-content');
            const charCount = document.getElementById('char-count');
            const submitBtn = form.querySelector('.btn-submit');
            const messageDiv = document.getElementById('form-message');

            // Character counter
            textarea.addEventListener('input', function () {
                const length = this.value.length;
                charCount.textContent = length;

                if (length > 1000) {
                    charCount.style.color = '#ef4444';
                    this.value = this.value.substring(0, 1000);
                } else if (length > 900) {
                    charCount.style.color = '#f59e0b';
                } else {
                    charCount.style.color = 'white';
                }
            });

            // Form submission
            form.addEventListener('submit', async function (e) {
                e.preventDefault();

                const formData = new FormData(form);
                const data = Object.fromEntries(formData);

                // Show loading state
                submitBtn.classList.add('loading');
                submitBtn.disabled = true;
                messageDiv.style.display = 'none';

                try {
                    const response = await fetch('/' + site + '/comments', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify(data)
                    });

                    const result = await response.json();

                    // Remove loading state
                    submitBtn.classList.remove('loading');
                    submitBtn.disabled = false;

                    if (result.success) {
                        showMessage(result.message, result.status === 'approved' ? 'success' : 'pending');

                        // If approved, add comment to the list
                        if (result.status === 'approved' && result.comment) {
                            addCommentToList(result.comment);
                            updateCommentCount(1);
                        }

                        // Reset form
                        form.reset();
                        charCount.textContent = '0';

                        // Scroll to comments
                        setTimeout(() => {
                            document.getElementById('comments-container').scrollIntoView({
                                behavior: 'smooth',
                                block: 'start'
                            });
                        }, 1000);
                    } else {
                        showMessage(result.message || 'Failed to post comment', 'error');
                    }
                } catch (error) {
                    submitBtn.classList.remove('loading');
                    submitBtn.disabled = false;
                    showMessage('An error occurred. Please try again.', 'error');
                    console.error('Comment submission error:', error);
                }
            });

            function showMessage(message, type) {
                messageDiv.textContent = message;
                messageDiv.className = `form-message ${type}`;
                messageDiv.style.display = 'block';

                setTimeout(() => {
                    messageDiv.style.display = 'none';
                }, 5000);
            }

            function addCommentToList(comment) {
                const container = document.getElementById('comments-container');
                const noComments = container.querySelector('.no-comments');

                if (noComments) {
                    noComments.remove();
                }

                const avatarHtml = comment.member_id && comment.member_avatar
                    ? `<img src="${escapeHtml(comment.member_avatar)}" alt="${escapeHtml(comment.name)}" class="avatar-image">`
                    : `<div class="avatar-circle">${comment.name.charAt(0).toUpperCase()}</div>`;

                const verifiedBadge = comment.member_id
                    ? `<svg class="verified-badge" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`
                    : '';

                const commentHtml = `
                <article class="comment-card" data-comment-id="${comment.id}" style="animation: slideIn 0.5s ease;">
                    <div class="comment-avatar">
                        ${avatarHtml}
                    </div>
                    <div class="comment-body">
                        <div class="comment-meta">
                            <h4 class="comment-author">
                                ${escapeHtml(comment.name)}
                                ${verifiedBadge}
                            </h4>
                            <time class="comment-date" datetime="${comment.created_at}">
                                Just now
                            </time>
                        </div>
                        <div class="comment-content">
                            ${escapeHtml(comment.content).replace(/\n/g, '<br>')}
                        </div>
                        <div class="comment-actions">
                            <button class="comment-action-btn reply-btn" data-comment-id="${comment.id}">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                                </svg>
                                Reply
                            </button>
                            <button class="comment-action-btn like-btn" data-comment-id="${comment.id}">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                                </svg>
                                <span class="like-count">0</span>
                            </button>
                        </div>
                    </div>
                </article>
            `;

                container.insertAdjacentHTML('afterbegin', commentHtml);
            }

            function updateCommentCount(change) {
                const countElement = document.getElementById('comment-count');
                const currentCount = parseInt(countElement.textContent);
                const newCount = currentCount + change;
                countElement.textContent = newCount;

                // Update plural
                const titleElement = document.querySelector('.comments-title');
                const commentWord = newCount === 1 ? 'Comment' : 'Comments';
                titleElement.innerHTML = titleElement.innerHTML.replace(/Comments?/, commentWord);
            }

            function escapeHtml(text) {
                const map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return text.replace(/[&<>"']/g, m => map[m]);
            }

            // Like button functionality
            document.addEventListener('click', function (e) {
                if (e.target.closest('.like-btn')) {
                    const btn = e.target.closest('.like-btn');
                    const countSpan = btn.querySelector('.like-count');
                    const currentCount = parseInt(countSpan.textContent);

                    if (btn.classList.contains('liked')) {
                        btn.classList.remove('liked');
                        countSpan.textContent = currentCount - 1;
                    } else {
                        btn.classList.add('liked');
                        countSpan.textContent = currentCount + 1;
                    }
                }
            });

            // Reply button functionality
            document.addEventListener('click', function (e) {
                if (e.target.closest('.reply-btn')) {
                    const btn = e.target.closest('.reply-btn');
                    const commentCard = btn.closest('.comment-card');
                    const authorName = commentCard.querySelector('.comment-author').textContent.trim();

                    textarea.focus();
                    textarea.value = `@${authorName} `;
                    textarea.dispatchEvent(new Event('input'));
                }
            });

            // Auto-save draft to localStorage (only for non-authenticated users or content)
            let draftTimeout;
            textarea.addEventListener('input', function () {
                clearTimeout(draftTimeout);
                draftTimeout = setTimeout(() => {
                    localStorage.setItem('comment-draft-<?= $page->id ?>', this.value);
                }, 1000);
            });

            // Restore draft
            const draft = localStorage.getItem('comment-draft-<?= $page->id ?>');
            if (draft && !isAuthenticated) {
                textarea.value = draft;
                textarea.dispatchEvent(new Event('input'));
            }

            // Clear draft on successful submission
            form.addEventListener('submit', function () {
                localStorage.removeItem('comment-draft-<?= $page->id ?>');
            });
        })();
    </script>