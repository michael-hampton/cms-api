<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Comments - <?= htmlspecialchars($site->name) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #667eea;
            --primary-dark: #5568d3;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --border-color: #e5e7eb;
            --bg-light: #f5f7fa;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-light);
            color: var(--text-primary);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .btn-secondary {
            background: white;
            color: var(--text-primary);
            border: 2px solid var(--border-color);
        }

        .btn-secondary:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .alert {
            padding: 1rem 1.25rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid var(--success-color);
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid var(--danger-color);
        }

        .comments-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .comment-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            transition: all 0.3s;
        }

        .comment-card:hover {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .comment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .comment-meta {
            flex: 1;
        }

        .comment-page {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .comment-date {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.approved {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.spam {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-badge.rejected {
            background: #f3f4f6;
            color: #4b5563;
        }

        .comment-content {
            color: var(--text-primary);
            line-height: 1.8;
            margin-bottom: 1rem;
        }

        .comment-actions {
            display: flex;
            gap: 0.75rem;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 1rem;
            box-shadow: var(--shadow);
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .page-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .comment-header {
                flex-direction: column;
                gap: 1rem;
            }

            .comment-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

@include('member._header')


<main class="container">
    <div class="page-header">
        <div>
            <h1 class="page-title">My Comments</h1>
            <p style="color: var(--text-secondary); margin-top: 0.5rem;">
                View and manage all your comments
            </p>
        </div>
        <a href="/member/dashboard" class="btn btn-secondary">
            ← Back to Dashboard
        </a>
    </div>

    <div id="alert-container"></div>

    <?php if ($comments->isEmpty()): ?>
        <div class="empty-state">
            <div class="empty-state-icon">💬</div>
            <h3>No Comments Yet</h3>
            <p>You haven't posted any comments yet. Start engaging with content!</p>
            <a href="/" class="btn btn-secondary">Browse Content</a>
        </div>
    <?php else: ?>
        <div class="comments-list">
            <?php foreach ($comments as $comment): ?>
                <div class="comment-card" data-comment-id="<?= $comment->id ?>">
                    <div class="comment-header">
                        <div class="comment-meta">
                            <div class="comment-page">
                                <?php
                                $page = $comment->page();
                                echo $page ? htmlspecialchars($page->title) : 'Page';
                                ?>
                            </div>
                            <div class="comment-date">
                                Posted on <?= date('F j, Y \a\t g:i A', strtotime($comment->created_at)) ?>
                            </div>
                        </div>
                        <span class="status-badge <?= strtolower($comment->status) ?>">
                            <?= htmlspecialchars($comment->status) ?>
                        </span>
                    </div>

                    <div class="comment-content">
                        <?= nl2br(htmlspecialchars($comment->content)) ?>
                    </div>

                    <div class="comment-actions">
                        <?php if ($page = $comment->page()): ?>
                            <a href="/<?= htmlspecialchars($page->slug) ?>#comment-<?= $comment->id ?>"
                               class="btn btn-secondary btn-sm">
                                View on Page
                            </a>
                        <?php endif; ?>
                        <button onclick="deleteComment(<?= $comment->id ?>)" class="btn btn-danger btn-sm">
                            Delete Comment
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<script>
    async function deleteComment(commentId) {
        if (!confirm('Are you sure you want to delete this comment? This action cannot be undone.')) {
            return;
        }

        try {
            const response = await fetch(`/member/comments/${commentId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            const data = await response.json();

            if (data.success) {
                showAlert('Comment deleted successfully', 'success');

                // Remove the comment card from the DOM
                const commentCard = document.querySelector(`[data-comment-id="${commentId}"]`);
                if (commentCard) {
                    commentCard.style.transition = 'opacity 0.3s, transform 0.3s';
                    commentCard.style.opacity = '0';
                    commentCard.style.transform = 'translateX(-20px)';

                    setTimeout(() => {
                        commentCard.remove();

                        // Check if there are no more comments
                        const remainingComments = document.querySelectorAll('.comment-card');
                        if (remainingComments.length === 0) {
                            location.reload();
                        }
                    }, 300);
                }
            } else {
                showAlert(data.message || 'Failed to delete comment', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('Failed to delete comment', 'error');
        }
    }

    function showAlert(message, type = 'success') {
        const alertContainer = document.getElementById('alert-container');
        const alertClass = type === 'success' ? 'alert-success' : 'alert-error';

        alertContainer.innerHTML = `
            <div class="alert ${alertClass}">
                <span>${type === 'success' ? '✓' : '✕'}</span>
                ${escapeHtml(message)}
            </div>
        `;

        setTimeout(() => {
            alertContainer.innerHTML = '';
        }, 5000);

        window.scrollTo({top: 0, behavior: 'smooth'});
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
</script>
</body>
</html>