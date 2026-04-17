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

        /* Toast notifications */
        .toast-container {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            pointer-events: none;
        }

        .toast {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.25rem;
            border-radius: 0.75rem;
            font-size: 0.9375rem;
            font-weight: 500;
            box-shadow: var(--shadow-lg);
            pointer-events: all;
            animation: slideIn 0.3s ease;
            max-width: 360px;
        }

        .toast.success {
            background: #ecfdf5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .toast.error {
            background: #fef2f2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .toast.info {
            background: #eff6ff;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }

        .toast-icon {
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .toast-close {
            margin-left: auto;
            background: none;
            border: none;
            cursor: pointer;
            color: inherit;
            opacity: 0.6;
            font-size: 1.1rem;
            padding: 0;
            line-height: 1;
        }

        .toast-close:hover {
            opacity: 1;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideOut {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100%);
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
            <p style="color:var(--text-secondary);margin-top:.5rem;">View and manage all your comments</p>
        </div>
        <a href="/member/dashboard" class="btn btn-secondary">← Back to Dashboard</a>
    </div>

    <div id="alert-container"></div>
    <div id="comments-root">
        <p style="text-align:center;color:var(--text-secondary);padding:2rem;">Loading comments…</p>
    </div>
</main>

<script>
    const API_BASE = '/api/' + SITE_SLUG;

    /**
     * Component: Individual Comment Card
     */
    class CommentCard {
        constructor(data, manager) {
            this.data = data;
            this.manager = manager;
            this.el = null; // Reference to the DOM element
        }

        render() {
            const c = this.data;
            const status = (c.status || 'pending').toLowerCase();

            this.el = UI.el('div', {
                className: 'comment-card',
                'data-comment-id': c.id
            }, [
                // Header: Meta Info and Status Badge
                UI.el('div', {className: 'comment-header'}, [
                    UI.el('div', {className: 'comment-meta'}, [
                        UI.el('div', {className: 'comment-page'}, [c.page_title || 'Page']),
                        UI.el('div', {className: 'comment-date'}, [`Posted on ${UI.formatDate(c.created_at)}`]),
                    ]),
                    UI.el('span', {className: `status-badge ${status}`}, [c.status])
                ]),

                // Content
                UI.el('div', {className: 'comment-content'}, [
                    // Handling line breaks cleanly
                    ...(c.content || '').split('\n').map(line => [line, UI.el('br')]).flat()
                ]),

                // Actions
                UI.el('div', {className: 'comment-actions'}, [
                    c.page_slug ? UI.el('a', {
                        href: `/${c.page_slug}#comment-${c.id}`,
                        className: 'btn btn-secondary btn-sm'
                    }, ['View on Page']) : null,

                    UI.el('button', {
                        className: 'btn btn-danger btn-sm',
                        onclick: () => this.handleDelete()
                    }, ['Delete Comment'])
                ])
            ]);

            return this.el;
        }

        async handleDelete() {
            if (!confirm('Are you sure you want to delete this comment?')) return;

            try {
                const success = await this.manager.deleteCommentApi(this.data.id);
                if (success) {
                    // Animate out
                    this.el.style.transition = 'opacity .3s, transform .3s';
                    this.el.style.opacity = '0';
                    this.el.style.transform = 'translateX(-20px)';

                    setTimeout(() => {
                        this.el.remove();
                        this.manager.checkEmptyState();
                    }, 300);
                }
            } catch (e) {
                UI.toast('Failed to delete comment', 'error');
            }
        }
    }

    /**
     * Orchestrator: Manages the collection of comments
     */
    class CommentManager {
        constructor() {
            this.root = document.getElementById('comments-root');
            this.init();
        }

        async init() {
            await this.loadComments();
        }

        async loadComments() {
            alert(API_BASE)
            try {
                const res = await api(`${API_BASE}/member/comments`);
                this.render(res.data?.comments || []);
            } catch (e) {
                this.root.innerHTML = `<p class="error-text">Failed to load comments. Please refresh.</p>`;
            }
        }

        render(comments) {
            if (!comments.length) {
                this.renderEmptyState();
                return;
            }

            const cards = comments.map(c => new CommentCard(c, this).render());
            UI.render(this.root, UI.el('div', {className: 'comments-list'}, cards));
        }

        renderEmptyState() {
            UI.render(this.root, UI.el('div', {className: 'empty-state'}, [
                UI.el('div', {className: 'empty-state-icon'}, ['💬']),
                UI.el('h3', {}, ['No Comments Yet']),
                UI.el('p', {}, ["You haven't posted any comments yet. Start engaging with content!"]),
                UI.el('a', {href: '/', className: 'btn btn-secondary'}, ['Browse Content'])
            ]));
        }

        async deleteCommentApi(id) {
            try {
                const res = await api(`${API_BASE}/member/comments/${id}`, {method: 'DELETE'});
                if (res.success) {
                    UI.toast('Comment deleted successfully', 'success');
                    return true;
                }
                UI.toast(res.message || 'Error deleting comment', 'error');
                return false;
            } catch (e) {
                UI.toast('System error during deletion', 'error');
                return false;
            }
        }

        checkEmptyState() {
            const remaining = this.root.querySelectorAll('.comment-card').length;
            if (remaining === 0) {
                this.renderEmptyState();
            }
        }
    }

    // Boot
    document.addEventListener('DOMContentLoaded', () => {
        window.commentApp = new CommentManager();
    });
</script>
</body>
</html>