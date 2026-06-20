(() => {
    'use strict';

    const section = document.getElementById('subscription-delivery-address-section');
    const list = document.getElementById('subscription-delivery-address-list');
    const message = document.getElementById('subscription-delivery-address-message');

    if (!section || !list || !message) {
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

    const renderAddresses = addresses => {
        list.replaceChildren();

        if (!addresses.length) {
            setMessage('No shipping address is available for this subscription.');
            return;
        }

        setMessage('');

        for (const address of addresses) {
            const card = document.createElement('article');
            card.className = 'subscription-delivery-address';

            const body = document.createElement('div');
            body.className = 'subscription-delivery-address__body';

            const lineOne = document.createElement('strong');
            lineOne.textContent = address.address_line_1 || '';

            const lineTwo = document.createElement('span');
            lineTwo.textContent = [address.address_line_2, address.city, address.postcode, address.country]
                .filter(Boolean)
                .join(', ');

            body.append(lineOne, lineTwo);

            const button = document.createElement('button');
            button.type = 'button';
            button.className = address.is_default ? 'btn btn--ghost btn--sm' : 'btn btn--gold btn--sm';
            button.textContent = address.is_default ? 'Current address' : 'Use this address';
            button.disabled = Boolean(address.is_default);
            button.dataset.deliveryAddressId = address.id;

            card.append(body, button);
            list.append(card);
        }
    };

    const loadAddresses = async () => {
        if (!subscription?.can_manage_delivery || !subscription.delivery_address_endpoint) {
            section.hidden = true;
            return;
        }

        section.hidden = false;
        setMessage('Loading delivery addresses…');
        list.replaceChildren();

        try {
            const result = await request(subscription.delivery_address_endpoint);
            renderAddresses(result.addresses || []);
        } catch (error) {
            setMessage(error.message || 'Failed to load delivery addresses.', true);
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

            loadAddresses();
            return;
        }

        const addressButton = event.target.closest('[data-delivery-address-id]');
        if (!addressButton || !subscription?.delivery_address_update_endpoint) {
            return;
        }

        addressButton.disabled = true;
        setMessage('Updating delivery address…');

        try {
            const endpoint = subscription.delivery_address_update_endpoint
                .replace('__ADDRESS_ID__', addressButton.dataset.deliveryAddressId);
            const result = await request(endpoint, {
                method: 'POST',
                body: '{}',
            });
            setMessage(result.message || 'Delivery address updated.');
            await loadAddresses();
        } catch (error) {
            addressButton.disabled = false;
            setMessage(error.message || 'Failed to update delivery address.', true);
        }
    });
})();
