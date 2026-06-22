(() => {
    'use strict';

    let trigger = null;

    document.addEventListener('click', async event => {
        const accountAction = event.target.closest('[data-account-action="api"]');
        const endpoint = accountAction?.dataset.endpoint || '';

        if (!endpoint.includes('/resubscribe')) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        const originalLabel = accountAction.textContent;
        accountAction.disabled = true;
        accountAction.textContent = 'Working…';

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({}),
            });
            const data = await response.json();
            const payload = data.data ?? data;

            if (!response.ok || payload.success === false) {
                throw new Error(payload.message || 'The action could not be completed.');
            }

            if (payload.redirect_url) {
                window.location.assign(payload.redirect_url);
                return;
            }

            window.location.reload();
        } catch (error) {
            accountAction.disabled = false;
            accountAction.textContent = originalLabel;
            window.alert(error.message || 'The action could not be completed.');
        }
    }, true);

    document.addEventListener('click', event => {
        const openButton = event.target.closest('[data-open-subscription-modal]');

        if (openButton) {
            event.preventDefault();
            trigger = openButton;

            if (window.subscriptionModalManager?.show) {
                window.subscriptionModalManager.show(null, null, true);
                return;
            }

            if (typeof window.openSubscriptionModal === 'function') {
                window.openSubscriptionModal();
                return;
            }

            if (typeof window.showSubscriptionModal === 'function') {
                window.showSubscriptionModal(null, null, true);
                return;
            }

            const modal = document.getElementById('subscriptionModal');
            if (!modal) {
                console.error('Subscription modal is not available on this page.');
                return;
            }

            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
            modal.querySelector('button, [href], input, select, textarea')?.focus();
            return;
        }

        if (event.target.closest('#sub-modal-close-btn')) {
            trigger?.focus();
            trigger = null;
        }
    });
})();
