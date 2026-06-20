(() => {
    'use strict';

    let trigger = null;

    document.addEventListener('click', event => {
        const openButton = event.target.closest('[data-open-subscription-modal]');

        if (openButton) {
            trigger = openButton;

            if (typeof window.openSubscriptionModal === 'function') {
                window.openSubscriptionModal(null, null, true);
                return;
            }

            const modal = document.getElementById('subscriptionModal');
            modal?.classList.add('show');
            document.body.style.overflow = 'hidden';
            modal?.querySelector('button, [href], input, select, textarea')?.focus();
            return;
        }

        if (event.target.closest('#sub-modal-close-btn')) {
            trigger?.focus();
            trigger = null;
        }
    });
})();
