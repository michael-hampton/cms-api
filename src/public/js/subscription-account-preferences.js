(() => {
    'use strict';

    const section = document.getElementById('subscription-preference-section');
    const form = document.getElementById('subscription-preference-form');
    const active = document.getElementById('subscription-preference-active');
    const email = document.getElementById('subscription-preference-email');
    const frequency = document.getElementById('subscription-preference-frequency');
    const message = document.getElementById('subscription-preference-message');

    if (!section || !form || !active || !email || !frequency || !message) {
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

    const loadPreferences = async () => {
        if (!subscription?.preference_endpoint) {
            section.hidden = true;
            return;
        }

        section.hidden = false;
        setMessage('Loading email preferences…');

        try {
            const result = await request(subscription.preference_endpoint);
            const preferences = result.preferences || {};
            active.checked = Boolean(preferences.is_active);
            email.checked = Boolean(preferences.email_notifications);
            frequency.value = preferences.newsletter_frequency || 'weekly';
            setMessage('');
        } catch (error) {
            setMessage(error.message || 'Failed to load email preferences.', true);
        }
    };

    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-open-subscription-manage]');
        if (!trigger) {
            return;
        }

        try {
            subscription = JSON.parse(trigger.dataset.subscriptionManage || '{}');
        } catch {
            subscription = null;
        }

        loadPreferences();
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();

        if (!subscription?.preference_endpoint) {
            return;
        }

        const submit = form.querySelector('button[type="submit"]');
        const originalLabel = submit.textContent;
        submit.disabled = true;
        submit.textContent = 'Saving…';
        setMessage('');

        try {
            const result = await request(subscription.preference_endpoint, {
                method: 'POST',
                body: JSON.stringify({
                    is_active: active.checked,
                    email_notifications: email.checked,
                    newsletter_frequency: frequency.value,
                }),
            });
            setMessage(result.message || 'Email preferences updated.');
        } catch (error) {
            setMessage(error.message || 'Failed to update email preferences.', true);
        } finally {
            submit.disabled = false;
            submit.textContent = originalLabel;
        }
    });
})();
