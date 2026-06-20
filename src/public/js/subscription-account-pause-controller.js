(() => {
    'use strict';

    const start = () => {
        const modal = document.getElementById('subscription-pause-modal');
        if (!modal) return;

        const title = modal.querySelector('#subscription-pause-title');
        const review = modal.querySelector('#subscription-pause-review');
        const impact = modal.querySelector('#subscription-pause-impact');
        const message = modal.querySelector('#subscription-pause-message');
        const confirmButton = modal.querySelector('#subscription-pause-confirm');
        const cancelButton = modal.querySelector('#subscription-pause-cancel');
        let state = 'closed';
        let flow = null;
        let trigger = null;

        const clearMessage = () => {
            message.textContent = '';
            message.classList.remove('is-visible', 'is-error');
        };

        const setSubmitting = submitting => {
            confirmButton.disabled = submitting;
            cancelButton.disabled = submitting;
            confirmButton.textContent = submitting
                ? 'Pausing…'
                : (flow?.confirm_label || 'Confirm pause');
        };

        const close = () => {
            if (state === 'submitting') return;

            modal.classList.remove('is-open');
            modal.hidden = true;
            document.body.classList.remove('subscription-pause-open');
            state = 'closed';
            flow = null;
            clearMessage();
            trigger?.focus();
            trigger = null;
        };

        const open = button => {
            try {
                flow = JSON.parse(button.dataset.subscriptionPause || '{}');
            } catch {
                return;
            }

            if (!flow?.endpoint) return;

            trigger = button;
            state = 'review';
            title.textContent = flow.title || 'Pause subscription';
            review.textContent = flow.review_copy || '';
            cancelButton.textContent = flow.cancel_label || 'Keep subscription active';
            impact.replaceChildren();

            for (const key of [
                'billing_copy',
                'access_copy',
                'delivery_copy',
                'fulfilment_copy',
                'renewal_copy',
                'resume_copy',
                'duration_copy',
                'restrictions_copy',
            ]) {
                if (!flow[key]) continue;
                const item = document.createElement('li');
                item.textContent = flow[key];
                impact.append(item);
            }

            clearMessage();
            setSubmitting(false);
            modal.hidden = false;
            modal.classList.add('is-open');
            document.body.classList.add('subscription-pause-open');
            cancelButton.focus();
        };

        const submit = async () => {
            if (state === 'submitting' || !flow?.endpoint) return;

            state = 'submitting';
            setSubmitting(true);
            clearMessage();

            try {
                const response = await fetch(flow.endpoint, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({}),
                });
                const payload = await response.json().catch(() => ({}));
                const result = payload.data ?? payload;

                if (response.status === 401) {
                    window.location.assign(modal.dataset.loginUrl || '/member/login');
                    return;
                }

                if (!response.ok || result.success === false) {
                    throw new Error(result.message || 'The subscription could not be paused.');
                }

                state = 'success';
                confirmButton.textContent = 'Paused';
                window.location.reload();
            } catch (error) {
                state = 'error';
                message.textContent = error.message || 'The subscription could not be paused.';
                message.classList.add('is-visible', 'is-error');
                setSubmitting(false);
            }
        };

        document.addEventListener('click', event => {
            const pauseTrigger = event.target.closest('[data-open-subscription-pause]');
            if (pauseTrigger) {
                event.preventDefault();
                open(pauseTrigger);
                return;
            }

            if (event.target.closest('[data-subscription-pause-close]') || event.target === modal) {
                close();
            }
        });

        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && state !== 'closed') close();
        });

        confirmButton.addEventListener('click', submit);
    };

    document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', start, { once: true })
        : start();
})();
