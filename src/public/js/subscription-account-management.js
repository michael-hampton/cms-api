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
    const autoRenewMessage = document.getElementById('subscription-auto-renew-message');
    const autoRenewSubmit = autoRenewForm.querySelector('button[type="submit"]');
    const billingSection = document.getElementById('subscription-billing-date-section');
    const billingForm = document.getElementById('subscription-billing-date-form');
    const billingDay = document.getElementById('subscription-billing-day');
    const billingPreview = document.getElementById('subscription-billing-preview');
    const billingMessage = document.getElementById('subscription-billing-date-message');
    const billingSubmit = billingForm?.querySelector('button[type="submit"]');

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

    const resetMessage = element => {
        element.replaceChildren();
        element.classList.remove('is-visible', 'is-error');
    };

    const showMessage = (element, text, isError = false) => {
        element.textContent = text;
        element.classList.add('is-visible');
        element.classList.toggle('is-error', isError);
    };

    const formatDate = value => {
        if (!value) {
            return '—';
        }

        const date = new Date(`${value}T00:00:00`);

        return Number.isNaN(date.getTime())
            ? value
            : date.toLocaleDateString(undefined, {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
            });
    };

    const renderBillingPreview = preview => {
        billingMessage.replaceChildren();
        billingMessage.classList.add('is-visible');
        billingMessage.classList.remove('is-error');

        const panel = document.createElement('div');
        panel.className = 'billing-preview';

        const addItem = (labelText, valueText, full = false, valueClass = '') => {
            const item = document.createElement('div');
            item.className = `billing-preview__item${full ? ' billing-preview__item--full' : ''}`;

            const label = document.createElement('span');
            label.className = 'billing-preview__label';
            label.textContent = labelText;

            const value = document.createElement('strong');
            value.className = `billing-preview__value${valueClass ? ` ${valueClass}` : ''}`;
            value.textContent = valueText;

            item.append(label, value);
            panel.append(item);
        };

        addItem('Current renewal date', formatDate(preview.current_period_end));
        addItem('Proposed renewal date', formatDate(preview.new_billing_date));

        const daysDifference = Number(preview.days_difference || 0);
        const daysText = daysDifference === 0
            ? 'No change'
            : `${Math.abs(daysDifference)} day${Math.abs(daysDifference) === 1 ? '' : 's'} ${daysDifference > 0 ? 'later' : 'earlier'}`;
        addItem('Schedule change', daysText);

        const amount = Math.abs(Number(preview.proration_amount || 0));
        const isCredit = Boolean(preview.is_credit);
        const adjustmentText = amount === 0
            ? 'No estimated adjustment'
            : `${isCredit ? 'Estimated credit' : 'Estimated charge'}: £${amount.toFixed(2)}`;
        addItem(
            'Estimated billing adjustment',
            adjustmentText,
            true,
            amount === 0 ? '' : (isCredit ? 'is-credit' : 'is-charge'),
        );

        billingMessage.append(panel);
    };

    const request = async (url, payload) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify(payload),
        });

        const data = await response.json();
        const result = data.data ?? data;

        if (!response.ok || !result.success) {
            throw new Error(result.message || 'The request could not be completed.');
        }

        return result;
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
        resetMessage(autoRenewMessage);
        updateConsentVisibility();

        if (billingSection && billingForm && billingDay) {
            billingSection.hidden = !activeSubscription.can_manage_billing_date;
            billingForm.dataset.previewEndpoint = activeSubscription.billing_date_preview_endpoint || '';
            billingForm.dataset.updateEndpoint = activeSubscription.billing_date_update_endpoint || '';
            billingDay.value = String(activeSubscription.billing_day_of_month || 1);
            resetMessage(billingMessage);
        }

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
            showMessage(autoRenewMessage, 'Please confirm consent before enabling automatic renewal.', true);
            consentCheckbox.focus();
            return;
        }

        const originalLabel = autoRenewSubmit.textContent;
        autoRenewSubmit.disabled = true;
        autoRenewSubmit.textContent = 'Saving…';
        resetMessage(autoRenewMessage);

        try {
            const result = await request(autoRenewForm.dataset.endpoint, {
                auto_renew: enabling,
                consent_given: requiresConsent && consentCheckbox.checked,
            });

            activeSubscription.auto_renew = Boolean(result.auto_renew);
            activeTrigger.dataset.subscriptionManage = JSON.stringify(activeSubscription);
            showMessage(autoRenewMessage, result.message || 'Renewal preference updated.');
            consentCheckbox.checked = false;
            updateConsentVisibility();
        } catch (error) {
            showMessage(autoRenewMessage, error.message || 'Failed to update automatic renewal.', true);
        } finally {
            autoRenewSubmit.disabled = false;
            autoRenewSubmit.textContent = originalLabel;
        }
    });

    billingPreview?.addEventListener('click', async () => {
        if (!billingForm.dataset.previewEndpoint) {
            return;
        }

        billingPreview.disabled = true;
        resetMessage(billingMessage);

        try {
            const result = await request(billingForm.dataset.previewEndpoint, {
                day_of_month: billingDay.value,
            });
            renderBillingPreview(result.preview || {});
        } catch (error) {
            showMessage(billingMessage, error.message || 'Failed to preview billing date change.', true);
        } finally {
            billingPreview.disabled = false;
        }
    });

    billingForm?.addEventListener('submit', async event => {
        event.preventDefault();

        if (!billingForm.dataset.updateEndpoint) {
            return;
        }

        const originalLabel = billingSubmit.textContent;
        billingSubmit.disabled = true;
        billingSubmit.textContent = 'Saving…';
        resetMessage(billingMessage);

        try {
            const result = await request(billingForm.dataset.updateEndpoint, {
                day_of_month: billingDay.value,
            });

            activeSubscription.billing_day_of_month = Number(billingDay.value);
            activeTrigger.dataset.subscriptionManage = JSON.stringify(activeSubscription);
            showMessage(billingMessage, result.message || 'Billing date updated successfully.');
        } catch (error) {
            showMessage(billingMessage, error.message || 'Failed to update billing date.', true);
        } finally {
            billingSubmit.disabled = false;
            billingSubmit.textContent = originalLabel;
        }
    });
})();
