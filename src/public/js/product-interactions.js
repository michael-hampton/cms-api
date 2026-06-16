// Shared product interaction functionality
(() => {
    'use strict';

    if (window.productInteractionsInitialised) return;
    window.productInteractionsInitialised = true;

    document.addEventListener('click', async event => {
        const wishlistButton = event.target.closest('.btn-wishlist, [data-action="toggle-wishlist"]');
        if (!wishlistButton) return;

        event.preventDefault();
        event.stopPropagation();

        const productId = Number(wishlistButton.dataset.productId);
        if (!Number.isInteger(productId) || productId <= 0 || wishlistButton.disabled) return;

        const site = window.SITE || document.querySelector('[data-site]')?.dataset.site;
        if (!site) {
            showToast('Unable to determine the current site.', 'error');
            return;
        }

        const active = wishlistButton.classList.contains('active');
        wishlistButton.disabled = true;

        try {
            const response = await fetch(
                active
                    ? `/api/${encodeURIComponent(site)}/wishlist/remove/${productId}`
                    : `/api/${encodeURIComponent(site)}/wishlist/add`,
                {
                    method: active ? 'DELETE' : 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: active ? null : JSON.stringify({product_id: productId}),
                },
            );

            const payload = await response.json();
            if (!response.ok || payload.success === false) {
                throw new Error(payload.message ?? payload.error ?? 'Unable to update wishlist.');
            }

            const nextActive = !active;
            wishlistButton.classList.toggle('active', nextActive);
            wishlistButton.setAttribute('aria-pressed', String(nextActive));
            wishlistButton.setAttribute(
                'aria-label',
                nextActive ? 'Remove from wishlist' : 'Add to wishlist',
            );
            wishlistButton.querySelector('svg')?.setAttribute(
                'fill',
                nextActive ? 'currentColor' : 'none',
            );

            updateCount('wishlist-count', payload.count ?? payload.data?.count);
            showToast(
                payload.message ?? (nextActive ? 'Added to wishlist' : 'Removed from wishlist'),
                'success',
            );
            document.dispatchEvent(new CustomEvent('wishlist:updated', {detail: payload}));
        } catch (error) {
            console.error('Error toggling wishlist:', error);
            showToast(error.message ?? 'Unable to update wishlist.', 'error');
        } finally {
            wishlistButton.disabled = false;
        }
    });

    function updateCount(id, value) {
        const count = Number(value ?? NaN);
        if (!Number.isFinite(count)) return;

        const element = document.getElementById(id);
        if (!element) return;

        element.textContent = String(count);
        element.style.display = count > 0 ? 'block' : 'none';
    }

    function showToast(message, type = 'info') {
        let toast = document.getElementById('toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'toast';
            toast.className = 'toast';
            document.body.appendChild(toast);
        }

        toast.textContent = message;
        toast.className = `toast ${type} show`;
        window.setTimeout(() => toast.classList.remove('show'), 3000);
    }
})();
