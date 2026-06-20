(() => {
    'use strict';

    const section = document.getElementById('subscription-issue-delivery-section');
    const list = document.getElementById('subscription-issue-delivery-list');
    const message = document.getElementById('subscription-issue-delivery-message');

    if (!section || !list || !message) {
        return;
    }

    const setMessage = (text, error = false) => {
        message.textContent = text;
        message.classList.toggle('is-visible', Boolean(text));
        message.classList.toggle('is-error', error);
    };

    const formatDate = value => {
        if (!value) {
            return 'Date pending';
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

    const render = deliveries => {
        list.replaceChildren();

        if (!deliveries.length) {
            setMessage('The delivery schedule is being prepared.');
            return;
        }

        setMessage('');

        for (const delivery of deliveries) {
            const row = document.createElement('article');
            row.className = 'subscription-issue-delivery';

            const details = document.createElement('div');
            details.className = 'subscription-issue-delivery__details';

            const title = document.createElement('strong');
            title.textContent = delivery.issue_title || `Issue #${delivery.issue_number}`;

            const date = document.createElement('span');
            date.textContent = formatDate(delivery.estimated_delivery_date);

            details.append(title, date);
            row.append(details);

            if (delivery.tracking_number) {
                const tracking = document.createElement('a');
                tracking.className = 'btn btn--ghost btn--sm';
                tracking.textContent = `Track ${delivery.tracking_number}`;
                tracking.href = delivery.tracking_url || '#';
                tracking.target = '_blank';
                tracking.rel = 'noopener';
                row.append(tracking);
            }

            list.append(row);
        }
    };

    document.addEventListener('click', async event => {
        const trigger = event.target.closest('[data-open-subscription-manage]');
        if (!trigger) {
            return;
        }

        let subscription = {};

        try {
            subscription = JSON.parse(trigger.dataset.subscriptionManage || '{}');
        } catch {
            subscription = {};
        }

        if (!subscription.can_manage_delivery || !subscription.issue_delivery_endpoint) {
            section.hidden = true;
            return;
        }

        section.hidden = false;
        list.replaceChildren();
        setMessage('Loading upcoming deliveries…');

        try {
            const response = await fetch(subscription.issue_delivery_endpoint, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await response.json();
            const result = data.data ?? data;

            if (!response.ok || result.success === false) {
                throw new Error(result.message || 'Failed to load deliveries.');
            }

            render(result.deliveries || []);
        } catch (error) {
            setMessage(error.message || 'Failed to load deliveries.', true);
        }
    });
})();
