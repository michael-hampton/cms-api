(() => {
    'use strict';

    const historyList = document.getElementById('subscription-history-list');
    const historyMessage = document.getElementById('subscription-history-message');
    const historyMore = document.getElementById('subscription-history-more');
    const deliverySection = document.getElementById('subscription-delivery-section');
    const deliveryForm = document.getElementById('subscription-delivery-form');
    const deliveryStatus = document.getElementById('subscription-delivery-status');
    const deliveryResume = document.getElementById('subscription-delivery-resume');
    const deliveryMessage = document.getElementById('subscription-delivery-message');

    let subscription = null;
    let historyPage = 1;

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

    const message = (element, text, error = false) => {
        if (!element) {
            return;
        }

        element.textContent = text;
        element.classList.toggle('is-visible', Boolean(text));
        element.classList.toggle('is-error', error);
    };

    const appendHistory = events => {
        for (const event of events) {
            const row = document.createElement('div');
            row.className = 'subscription-history-item';

            const name = document.createElement('strong');
            name.textContent = String(event.event_type || 'Subscription updated').replaceAll('_', ' ');

            const date = document.createElement('time');
            date.textContent = event.occurred_at
                ? new Date(event.occurred_at).toLocaleString()
                : '';

            row.append(name, date);
            historyList.append(row);
        }
    };

    const loadHistory = async (reset = false) => {
        if (!historyList || !subscription?.history_endpoint) {
            return;
        }

        if (reset) {
            historyPage = 1;
            historyList.replaceChildren();
        }

        message(historyMessage, 'Loading history…');

        try {
            const separator = subscription.history_endpoint.includes('?') ? '&' : '?';
            const result = await request(
                `${subscription.history_endpoint}${separator}page=${historyPage}&per_page=10`,
            );
            appendHistory(result.events || []);
            historyMore.hidden = !result.pagination?.has_more;
            message(
                historyMessage,
                historyList.children.length ? '' : 'No subscription history is available yet.',
            );
        } catch (error) {
            message(historyMessage, error.message || 'Failed to load subscription history.', true);
        }
    };

    const loadDelivery = async () => {
        if (!deliverySection || !subscription?.can_manage_delivery) {
            if (deliverySection) {
                deliverySection.hidden = true;
            }
            return;
        }

        deliverySection.hidden = false;
        message(deliveryMessage, 'Loading delivery settings…');

        try {
            const result = await request(subscription.delivery_status_endpoint);
            const paused = Boolean(result.is_paused);
            deliveryForm.hidden = false;
            deliveryResume.hidden = !paused;
            deliveryForm.querySelector('button[type="submit"]').hidden = paused;
            deliveryStatus.textContent = paused
                ? `Delivery is paused until ${result.pause_end || 'the selected date'}.`
                : 'Manage temporary delivery pauses for this print subscription.';
            message(deliveryMessage, '');
        } catch (error) {
            message(deliveryMessage, error.message || 'Failed to load delivery settings.', true);
        }
    };

    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-open-subscription-manage]');
        if (trigger) {
            try {
                subscription = JSON.parse(trigger.dataset.subscriptionManage || '{}');
            } catch {
                subscription = null;
            }

            loadHistory(true);
            loadDelivery();
            return;
        }

        if (event.target === historyMore) {
            historyPage += 1;
            loadHistory();
        }
    });

    deliveryForm?.addEventListener('submit', async event => {
        event.preventDefault();
        message(deliveryMessage, 'Saving delivery pause…');

        const formData = new FormData(deliveryForm);

        try {
            const result = await request(subscription.delivery_pause_endpoint, {
                method: 'POST',
                body: JSON.stringify({
                    pause_start: formData.get('pause_start'),
                    pause_end: formData.get('pause_end'),
                    reason: formData.get('reason'),
                }),
            });
            message(deliveryMessage, result.message || 'Delivery paused successfully.');
            await loadDelivery();
        } catch (error) {
            message(deliveryMessage, error.message || 'Failed to pause delivery.', true);
        }
    });

    deliveryResume?.addEventListener('click', async () => {
        message(deliveryMessage, 'Resuming delivery…');

        try {
            const result = await request(subscription.delivery_resume_endpoint, {
                method: 'POST',
                body: '{}',
            });
            message(deliveryMessage, result.message || 'Delivery resumed successfully.');
            await loadDelivery();
        } catch (error) {
            message(deliveryMessage, error.message || 'Failed to resume delivery.', true);
        }
    });
})();
