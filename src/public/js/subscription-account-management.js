(() => {
    'use strict';

    const drawer = document.getElementById('subscription-manage-drawer');
    if (!drawer) {
        return;
    }

    const title = document.getElementById('subscription-manage-title');
    const status = document.getElementById('subscription-manage-status');
    const facts = document.getElementById('subscription-manage-facts');
    const autoRenewSection = document.getElementById('subscription-auto-renew-section');
    const autoRenewForm = document.getElementById('subscription-auto-renew-form');
    const autoRenewToggle = document.getElementById('subscription-auto-renew-toggle');
    const consentContainer = document.getElementById('subscription-auto-renew-consent');
    const consentCheckbox = document.getElementById('subscription-auto-renew-consent-checkbox');
    const message = document.getElementById('subscription-auto-renew-message');
    const submitButton = autoRenewForm.querySelector('button[type="submit"]');

    let activeTrigger = null;
    let activeSubscription = null;

    const renderFacts = subscriptionFacts => {
        facts.replaceChildren();

        for (const fact of Array.isArray(subscriptionFacts) ? subscriptionFacts : []) {
            const row = document.createElement('div');
            row.className = 'sub-detail';

            const label = document.createElement('div');
            label.className = 'sub-detail__label';
            label.textContent = fact.label || '';

            const value = document.createElement('div');
            value.className = 'sub-detail__value';
            value.textContent = fact.value || '—';

            row.append(label, value);
            facts.append(row);
        }
    };

    const updateConsentVisibility = () => {
        const enabling = autoRenewToggle.checked && !activeSubscription?.auto_renew;
        consentContainer.hidden = !enabling;

        if (!enabling) {
            consentCheckbox.checked = false;
        }
    };

    const openDrawer = trigger => {
        try {
            activeSubscription = JSON.parse(trigger.dataset.subscriptionManage || '{}');
        } catch {
            activeSubscription = null;
        }

        if (!activeSubscription?.id) {
            return;
        }

        activeTrigger = trigger;
        title.textContent = activeSubscription.plan_name || 'Subscription';
        status.textContent = activeSubscription.status_label || '';
        renderFacts(activeSubscription.facts);

        autoRenewSection.hidden = !activeSubscription.can_manage_auto_renew;
        autoRenewToggle.checked = Boolean(activeSubscription.auto_renew);
        autoRenewForm.dataset.endpoint = activeSubscription.auto_renew_endpoint || '';
        consentCheckbox.checked = false;
        message.textContent = '';
        message.classList.remove('is-visible', 'is-error');
        updateConsentVisibility();

        drawer.hidden = false;
        drawer.classList.add('open');
        document.body.style.overflow = 'hidden';
        drawer.querySelector('[data-close-subscription-manage]')?.focus();
    };

    const closeDrawer = () => {
        drawer.classList.remove('open');
        drawer.hidden = true;
        document.body.style.overflow = '';
        activeTrigger?.focus();
        activeTrigger = null;
        activeSubscription = null;
    };

    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-open-subscription-manage]');
        if (trigger) {
            openDrawer(trigger);
            return;
        }

        if (event.target.closest('[data-close-subscription-manage]') || event.target === drawer) {
            closeDrawer();
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !drawer.hidden) {
            closeDrawer();
        }
    });

    autoRenewToggle.addEventListener('change', updateConsentVisibility);

    autoRenewForm.addEventListener('submit', async event => {
        event.preventDefault();

        if (!autoRenewForm.dataset.endpoint) {
            return;
        }

        const enabling = autoRenewToggle.checked;
        const requiresConsent = enabling && !activeSubscription.auto_renew;

        if (requiresConsent && !consentCheckbox.checked) {
            message.textContent = 'Please confirm consent before enabling automatic renewal.';
            message.classList.add('is-visible', 'is-error');
            consentCheckbox.focus();
            return;
        }

        const originalLabel = submitButton.textContent;
        submitButton.disabled = true;
        submitButton.textContent = 'Saving…';
        message.textContent = '';
        message.classList.remove('is-visible', 'is-error');

        try {
            const response = await fetch(autoRenewForm.dataset.endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({
                    auto_renew: enabling,
                    consent_given: requiresConsent && consentCheckbox.checked,
                }),
            });

            const data = await response.json();
            const payload = data.data ?? data;

            if (!response.ok || !payload.success) {
                throw new Error(payload.message || 'Failed to update automatic renewal.');
            }

            activeSubscription.auto_renew = Boolean(payload.auto_renew);
            activeTrigger.dataset.subscriptionManage = JSON.stringify(activeSubscription);
            message.textContent = payload.message || 'Renewal preference updated.';
            message.classList.add('is-visible');
            consentCheckbox.checked = false;
            updateConsentVisibility();
        } catch (error) {
            message.textContent = error.message || 'Failed to update automatic renewal.';
            message.classList.add('is-visible', 'is-error');
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = originalLabel;
        }
    });
})();
