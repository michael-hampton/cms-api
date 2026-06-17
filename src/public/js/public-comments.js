(() => {
    const initialise = (element, component) => {
        if (component.type !== 'comments') {
            return;
        }

        const form = element.querySelector('#comment-form');
        if (!form || form.dataset.publicApiHydrated === 'true') {
            return;
        }

        const endpoint = component.endpoints?.create;
        const csrfToken = component.endpoints?.csrf_token;
        const pageId = resolvePageId(endpoint, form);

        if (!endpoint || !csrfToken) {
            console.error('Comments component is missing its public API endpoint or CSRF token.');
            return;
        }

        form.dataset.publicApiHydrated = 'true';
        form.setAttribute('action', endpoint);

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            event.stopImmediatePropagation();

            const submitButton = form.querySelector('.btn-submit');
            const message = element.querySelector('#form-message');
            const characterCount = element.querySelector('#char-count');
            const payload = Object.fromEntries(new FormData(form));

            delete payload.page_id;
            delete payload.member_id;
            delete payload._token;

            submitButton?.classList.add('loading');
            if (submitButton) {
                submitButton.disabled = true;
            }
            if (message) {
                message.style.display = 'none';
            }

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();

                if (!response.ok || !result.success) {
                    const error = new Error(result.message || 'Failed to post comment.');
                    error.retryAfter = response.headers.get('Retry-After');
                    throw error;
                }

                showMessage(
                    message,
                    result.message,
                    result.status === 'approved' ? 'success' : 'pending'
                );

                if (result.status === 'approved' && result.comment) {
                    addCommentToList(element, result.comment);
                    updateCommentCount(element, 1);
                }

                form.reset();
                if (characterCount) {
                    characterCount.textContent = '0';
                }

                if (pageId !== null) {
                    localStorage.removeItem(`comment-draft-${pageId}`);
                }

                window.setTimeout(() => {
                    element.querySelector('#comments-container')?.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }, 1000);
            } catch (error) {
                const suffix = error.retryAfter
                    ? ` Try again in ${error.retryAfter} seconds.`
                    : '';
                showMessage(message, `${error.message || 'Unable to post comment.'}${suffix}`, 'error');
            } finally {
                submitButton?.classList.remove('loading');
                if (submitButton) {
                    submitButton.disabled = false;
                }
            }
        }, true);
    };

    const resolvePageId = (endpoint, form) => {
        const endpointMatch = String(endpoint ?? '').match(/\/content\/(\d+)\/comments(?:\?|$)/);
        if (endpointMatch) {
            return endpointMatch[1];
        }

        const formPageId = form.querySelector('input[name="page_id"]')?.value;
        return formPageId || null;
    };

    const showMessage = (element, text, type) => {
        if (!element) {
            return;
        }

        element.textContent = text;
        element.className = `form-message ${type}`;
        element.style.display = 'block';

        window.setTimeout(() => {
            element.style.display = 'none';
        }, 5000);
    };

    const addCommentToList = (element, comment) => {
        const container = element.querySelector('#comments-container');
        if (!container) {
            return;
        }

        container.querySelector('.no-comments')?.remove();

        const safeName = escapeHtml(comment.name);
        const verifiedBadge = comment.member_id
            ? '<svg class="verified-badge" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
            : '';

        container.insertAdjacentHTML('afterbegin', `
            <article class="comment-card" data-comment-id="${Number(comment.id)}" style="animation: slideIn 0.5s ease;">
                <div class="comment-avatar">
                    <div class="avatar-circle">${safeName.charAt(0).toUpperCase()}</div>
                </div>
                <div class="comment-body">
                    <div class="comment-meta">
                        <h4 class="comment-author">
                            ${safeName}
                            ${verifiedBadge}
                        </h4>
                        <time class="comment-date" datetime="${escapeHtml(String(comment.created_at))}">Just now</time>
                    </div>
                    <div class="comment-content">${escapeHtml(comment.content).replace(/\n/g, '<br>')}</div>
                    <div class="comment-actions">
                        <button class="comment-action-btn reply-btn" data-comment-id="${Number(comment.id)}">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                            </svg>
                            Reply
                        </button>
                        <button class="comment-action-btn like-btn" data-comment-id="${Number(comment.id)}">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                            </svg>
                            <span class="like-count">0</span>
                        </button>
                    </div>
                </div>
            </article>
        `);
    };

    const updateCommentCount = (element, change) => {
        const count = element.querySelector('#comment-count');
        if (!count) {
            return;
        }

        const newCount = (Number.parseInt(count.textContent || '0', 10) || 0) + change;
        count.textContent = String(newCount);

        const title = element.querySelector('.comments-title');
        if (title) {
            title.innerHTML = title.innerHTML.replace(/Comments?/, newCount === 1 ? 'Comment' : 'Comments');
        }
    };

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    })[character]);

    document.addEventListener('public-content:component-mounted', event => {
        initialise(event.detail.element, event.detail.component);
    });
})();
