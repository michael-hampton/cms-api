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

                localStorage.removeItem(`comment-draft-${component.page_id || ''}`);
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

    const showMessage = (element, text, type) => {
        if (!element) {
            return;
        }

        element.textContent = text;
        element.className = `form-message ${type}`;
        element.style.display = 'block';
    };

    const addCommentToList = (element, comment) => {
        const container = element.querySelector('#comments-container');
        if (!container) {
            return;
        }

        container.querySelector('.no-comments')?.remove();

        const verifiedBadge = comment.member_id
            ? '<svg class="verified-badge" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
            : '';

        container.insertAdjacentHTML('afterbegin', `
            <article class="comment-card" data-comment-id="${Number(comment.id)}">
                <div class="comment-avatar">
                    <div class="avatar-circle">${escapeHtml(comment.name).charAt(0).toUpperCase()}</div>
                </div>
                <div class="comment-body">
                    <div class="comment-meta">
                        <h4 class="comment-author">${escapeHtml(comment.name)} ${verifiedBadge}</h4>
                        <time class="comment-date" datetime="${escapeHtml(String(comment.created_at))}">Just now</time>
                    </div>
                    <div class="comment-content">${escapeHtml(comment.content).replace(/\n/g, '<br>')}</div>
                </div>
            </article>
        `);
    };

    const updateCommentCount = (element, change) => {
        const count = element.querySelector('#comment-count');
        if (!count) {
            return;
        }

        count.textContent = String((Number.parseInt(count.textContent || '0', 10) || 0) + change);
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
