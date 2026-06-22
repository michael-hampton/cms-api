(() => {
    'use strict';

    let trigger = null;

    function findPlanElement(planSlug, planId) {
        if (planSlug) {
            const bySlug = document.querySelector(`.sub-plan[data-plan-slug="${CSS.escape(planSlug)}"]`);
            if (bySlug) {
                return bySlug;
            }
        }

        if (planId) {
            return document.querySelector(`.sub-plan[data-plan-id="${CSS.escape(String(planId))}"]`);
        }

        return null;
    }

    function patchPayloadForResubscribe(manager) {
        if (!manager || manager.__resubscribePayloadPatched || typeof manager.buildCheckoutPayload !== 'function') {
            return;
        }

        const original = manager.buildCheckoutPayload.bind(manager);
        manager.buildCheckoutPayload = () => {
            const payload = original();
            if (window.resubscribeFromSubscriptionId) {
                payload.resubscribe_from_subscription_id = window.resubscribeFromSubscriptionId;
            }
            return payload;
        };
        manager.__resubscribePayloadPatched = true;
    }

    function selectModalPlan(openButton) {
        const manager = window.subscriptionModalManager;
        if (!manager) {
            return;
        }

        patchPayloadForResubscribe(manager);

        const sourceSubscriptionId = openButton.dataset.sourceSubscriptionId || null;
        window.resubscribeFromSubscriptionId = sourceSubscriptionId;

        const planElement = findPlanElement(openButton.dataset.planSlug || '', openButton.dataset.planId || '');
        if (!planElement) {
            return;
        }

        document.querySelectorAll('.sub-plan').forEach(plan => plan.classList.remove('selected'));
        planElement.classList.add('selected');

        manager.readPlanData(planElement);
        manager.goToStep(manager.nextStep(1));
    }

    document.addEventListener('click', event => {
        const openButton = event.target.closest('[data-open-subscription-modal]');

        if (openButton) {
            event.preventDefault();
            trigger = openButton;

            if (window.subscriptionModalManager?.show) {
                window.subscriptionModalManager.show(null, null, true);
                selectModalPlan(openButton);
                return;
            }

            if (typeof window.showSubscriptionModal === 'function') {
                window.showSubscriptionModal(null, null, true);
                selectModalPlan(openButton);
                return;
            }

            if (typeof window.openSubscriptionModal === 'function') {
                window.openSubscriptionModal();
                selectModalPlan(openButton);
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
