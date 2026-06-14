(() => {
    function getToken() {
        if (typeof window.TOKEN === 'function') {
            return window.TOKEN();
        }

        return localStorage.getItem('oc_token') || '';
    }

    function getSite() {
        if (typeof window.SITE === 'string' && window.SITE) {
            return window.SITE;
        }

        const match = window.location.pathname.match(/^\/([^/]+)\/open-collab\//);
        return match?.[1] ?? '';
    }

    function showInitials() {
        const preview = document.getElementById('avatar-preview');
        if (!preview) {
            return;
        }

        const displayName = document.getElementById('display-name')?.value || 'U';
        const initial = displayName.trim().charAt(0).toUpperCase() || 'U';

        preview.innerHTML = '';
        const initials = document.createElement('span');
        initials.id = 'avatar-initials';
        initials.textContent = initial;
        initials.style.cssText = [
            'font-family:var(--font-display)',
            'font-size:1.5rem',
            'color:var(--slate)',
            'user-select:none',
        ].join(';');
        preview.appendChild(initials);
    }

    function showError(message) {
        const error = document.getElementById('avatar-error');
        if (!error) {
            return;
        }

        error.textContent = message;
        error.style.display = 'block';
    }

    function clearError() {
        const error = document.getElementById('avatar-error');
        if (error) {
            error.textContent = '';
            error.style.display = 'none';
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const removeButton = document.getElementById('avatar-remove-btn');
        if (!removeButton) {
            return;
        }

        removeButton.addEventListener('click', async (event) => {
            event.preventDefault();
            event.stopImmediatePropagation();

            clearError();

            const originalText = removeButton.textContent;
            removeButton.disabled = true;
            removeButton.textContent = 'Removing…';

            try {
                const site = getSite();
                const response = await fetch(`/api/${site}/open-collab/contributor/avatar`, {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        Authorization: `Bearer ${getToken()}`,
                    },
                });

                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(
                        data.message
                        || data.error
                        || 'Could not remove profile picture.',
                    );
                }

                showInitials();
                removeButton.style.display = 'none';

                const fileInput = document.getElementById('avatar-file-input');
                if (fileInput) {
                    fileInput.value = '';
                }

                const progress = document.getElementById('avatar-progress-wrap');
                if (progress) {
                    progress.style.display = 'none';
                }

                const success = document.getElementById('profile-success');
                if (success) {
                    success.textContent = '✓ Profile picture removed';
                    success.style.display = 'flex';
                    window.setTimeout(() => {
                        success.style.display = 'none';
                    }, 3000);
                }
            } catch (error) {
                removeButton.disabled = false;
                removeButton.textContent = originalText;
                showError(
                    error instanceof Error
                        ? error.message
                        : 'Could not remove profile picture.',
                );
            }
        }, {capture: true});
    });
})();
