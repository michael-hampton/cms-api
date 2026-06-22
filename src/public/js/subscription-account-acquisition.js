(() => {
    'use strict';

    let trigger = null;

    function escapeSelector(value) {
        if (window.CSS?.escape) {
            return CSS.escape(String(value));
        }

        return String(value).replace(/"/g, '\\"');
    }

    function findPlanElement(planSlug, planId) {
        if (planSlug) {
            const bySlug = document.querySelector(`.sub-plan[data-plan-slug="${escapeSelector(planSlug)}"]`);
            if (bySlug) {
                return bySlug;
            }
        }

        if (planId) {
            return document.querySelector(`.sub-plan[data-plan-id="${escapeSelector(planId)}"]`);
        }

        return null;
    }

    function setModalError(message) {
        const modal = document.getElementById('subscriptionModal');
        const error = modal?.querySelector('#sub-payment-error, #card-errors, .sub-error');
        if (error) {
            error.textContent = message;
        }
    }

    function patchPayloadForResubscribe(manager) {
        if (!manager || manager.__resubscribePayloadPatched) {
            return;
        }

        if (typeof manager.buildCheckoutPayload === 'function') {
            const originalBuildCheckoutPayload = manager.buildCheckoutPayload.bind(manager);
            manager.buildCheckoutPayload = () => {
                const payload = originalBuildCheckoutPayload();
                if (window.resubscribeFromSubscriptionId) {
                    payload.resubscribe_from_subscription_id = window.resubscribeFromSubscriptionId;
                }
                return payload;
            };
        }

        if (manager.api && typeof manager.api.addPlanToCart === 'function') {
            const originalAddPlanToCart = manager.api.addPlanToCart.bind(manager.api);
            manager.api.addPlanToCart = (plan) => {
                if (!plan.deliveryType) {
                    throw new Error('A delivery type is required before this subscription can be added to the cart.');
                }

                return originalAddPlanToCart(plan);
            };
        }

        manager.__resubscribePayloadPatched = true;
    }

    function clearPlanSelection() {
        document.querySelectorAll('.sub-plan').forEach(plan => {
            plan.classList.remove('selected', 'active', 'sub-plan--current');
            plan.removeAttribute('aria-selected');
            plan.style.removeProperty('border-color');
            plan.style.removeProperty('box-shadow');

            const badge = plan.querySelector('[data-current-plan-badge]');
            if (badge) {
                badge.remove();
            }

            const button = plan.querySelector('.sub-plan-btn');
            if (button && button.dataset.originalLabel) {
                button.textContent = button.dataset.originalLabel;
            }
        });
    }

    function markPlanSelected(planElement) {
        clearPlanSelection();
        planElement.classList.add('selected', 'active', 'sub-plan--current');
        planElement.setAttribute('aria-selected', 'true');
        planElement.style.borderColor = 'var(--sub-primary)';
        planElement.style.boxShadow = '0 10px 24px rgba(99, 102, 241, .18)';

        const header = planElement.querySelector('.sub-plan-header') || planElement;
        const badge = document.createElement('div');
        badge.dataset.currentPlanBadge = 'true';
        badge.className = 'sub-plan-current-badge';
        badge.textContent = 'Current plan';
        header.prepend(badge);

        const button = planElement.querySelector('.sub-plan-btn');
        if (button) {
            button.dataset.originalLabel = button.dataset.originalLabel || button.textContent.trim();
            button.textContent = 'Selected plan';
        }

        planElement.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    }

    function selectModalPlan(openButton) {
        const manager = window.subscriptionModalManager;
        if (!manager) {
            return;
        }

        patchPayloadForResubscribe(manager);

        const sourceSubscriptionId = openButton.dataset.sourceSubscriptionId || null;
        window.resubscribeFromSubscriptionId = sourceSubscriptionId;

        const planSlug = openButton.dataset.planSlug || '';
        const planId = openButton.dataset.planId || '';
        const planElement = findPlanElement(planSlug, planId);

        if (!planElement) {
            setModalError('This subscription plan is no longer available.');
            console.error('Resubscribe plan could not be found in the modal.', { planSlug, planId });
            return;
        }

        manager.readPlanData(planElement);

        if (!manager.selectedPlan?.deliveryType) {
            setModalError('This subscription plan is missing a delivery type and cannot be added to the cart.');
            console.error('Resubscribe plan is missing delivery type.', { planSlug, planId });
            return;
        }

        markPlanSelected(planElement);
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
