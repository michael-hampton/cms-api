(() => {
    'use strict';

    const section = document.getElementById('subscription-upgrade-section');
    const optionsContainer = document.getElementById('subscription-upgrade-options');
    const previewContainer = document.getElementById('subscription-upgrade-preview');
    const message = document.getElementById('subscription-upgrade-message');

    if (!section || !optionsContainer || !previewContainer || !message) {
        return;
    }

    let subscription = null;

    const request = async (url, options = {}) => {
        const response = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                ...(options.headers || {}),
            },
            ...options,
        });
        const data = await response.json();
        const result = data.data ?? data;

        if (!response.ok || result.success === false) {
            throw new Error(result.message || 'The request could not be completed.');
        }

        return result;
    };

    const setMessage = (text, error = false) => {
        message.textContent = text;
        message.classList.toggle('is-visible', Boolean(text));
        message.classList.toggle('is-error', error);
    };

    const money = value => `£${Number(value || 0).toFixed(2)}`;

    const buildFeatureList = features => {
        const list = document.createElement('ul');
        list.className = 'subscription-upgrade-features';

        for (const feature of Array.isArray(features) ? features : []) {
            const item = document.createElement('li');
            item.textContent = feature;
            list.append(item);
        }

        return list;
    };

    const renderOptions = upgrade => {
        optionsContainer.replaceChildren();

        if (!upgrade.can_upgrade || !upgrade.options?.length) {
            setMessage(upgrade.reason || 'No upgrade options are currently available.');
            return;
        }

        setMessage('');

        for (const option of upgrade.options) {
            const card = document.createElement('article');
            card.className = 'subscription-upgrade-option';

            const heading = document.createElement('h4');
            heading.textContent = option.plan_name;

            const description = document.createElement('p');
            description.textContent = option.description || '';

            const price = document.createElement('strong');
            price.className = 'subscription-upgrade-option__price';
            price.textContent = `${money(option.new_total_price)} / ${option.billing_period || 'term'}`;

            const difference = document.createElement('span');
            difference.className = 'subscription-upgrade-option__difference';
            difference.textContent = `Estimated change today: ${money(option.price_difference)}`;

            const previewButton = document.createElement('button');
            previewButton.type = 'button';
            previewButton.className = 'btn btn--ghost btn--sm';
            previewButton.textContent = 'Review upgrade';
            previewButton.dataset.upgradePlanId = option.plan_id;

            card.append(heading, description, price, difference, buildFeatureList(option.features), previewButton);
            optionsContainer.append(card);
        }
    };

    const renderPreview = preview => {
        previewContainer.replaceChildren();
        previewContainer.hidden = false;

        const title = document.createElement('h4');
        title.textContent = `${preview.current_plan?.name || 'Current plan'} → ${preview.upgrade_plan?.name || 'Upgrade plan'}`;

        const charge = document.createElement('p');
        charge.textContent = `Estimated immediate charge: ${money(preview.pricing?.immediate_charge)}`;

        const ongoing = document.createElement('p');
        ongoing.textContent = `New plan price: ${money(preview.pricing?.upgrade_price)}`;

        const note = document.createElement('p');
        note.className = 'subscription-upgrade-preview__note';
        note.textContent = preview.pricing?.estimate_note || 'The final charge will be confirmed before the upgrade is applied.';

        const confirm = document.createElement('button');
        confirm.type = 'button';
        confirm.className = 'btn btn--gold btn--sm';
        confirm.textContent = 'Confirm upgrade';
        confirm.dataset.confirmUpgradePlanId = preview.upgrade_plan?.id || '';

        previewContainer.append(title, charge, ongoing, note, confirm);
    };

    const loadOptions = async () => {
        if (!subscription?.can_upgrade || !subscription.upgrade_options_endpoint) {
            section.hidden = true;
            return;
        }

        section.hidden = false;
        setMessage('Loading upgrade options…');
        previewContainer.hidden = true;
        optionsContainer.replaceChildren();

        try {
            const result = await request(subscription.upgrade_options_endpoint);
            renderOptions(result.upgrade || {});
        } catch (error) {
            setMessage(error.message || 'Failed to load upgrade options.', true);
        }
    };

    document.addEventListener('click', async event => {
        const manageTrigger = event.target.closest('[data-open-subscription-manage]');
        if (manageTrigger) {
            try {
                subscription = JSON.parse(manageTrigger.dataset.subscriptionManage || '{}');
            } catch {
                subscription = null;
            }

            loadOptions();
            return;
        }

        const previewButton = event.target.closest('[data-upgrade-plan-id]');
        if (previewButton) {
            setMessage('Loading upgrade preview…');

            try {
                const result = await request(subscription.upgrade_preview_endpoint, {
                    method: 'POST',
                    body: JSON.stringify({
                        upgrade_plan_id: previewButton.dataset.upgradePlanId,
                    }),
                });
                renderPreview(result.preview || {});
                setMessage('');
            } catch (error) {
                setMessage(error.message || 'Failed to preview upgrade.', true);
            }
            return;
        }

        const confirmButton = event.target.closest('[data-confirm-upgrade-plan-id]');
        if (confirmButton) {
            confirmButton.disabled = true;
            setMessage('Applying upgrade…');

            try {
                const result = await request(subscription.upgrade_endpoint, {
                    method: 'POST',
                    body: JSON.stringify({
                        upgrade_plan_id: confirmButton.dataset.confirmUpgradePlanId,
                    }),
                });
                setMessage(result.message || 'Subscription upgraded successfully.');
                previewContainer.hidden = true;
                await loadOptions();
            } catch (error) {
                setMessage(error.message || 'Failed to upgrade subscription.', true);
            } finally {
                confirmButton.disabled = false;
            }
        }
    });
})();
