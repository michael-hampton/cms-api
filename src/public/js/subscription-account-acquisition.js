(() => {
    'use strict';

    let trigger = null;

    document.addEventListener('click', event => {
        const openButton = event.target.closest('[data-open-subscription-modal]');

        if (openButton) {
            event.preventDefault();
            trigger = openButton;

            const planSlug = openButton.dataset.planSlug || null;
            const planId = openButton.dataset.planId || null;

            if (window.subscriptionModalManager?.show) {
                window.subscriptionModalManager.show(planSlug, planId, true);
                return;
            }

            if (typeof window.showSubscriptionModal === 'function') {
                window.showSubscriptionModal(planSlug, planId, true);
                return;
            }

            if (typeof window.openSubscriptionModal === 'function') {
                window.openSubscriptionModal();
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
