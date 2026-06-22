(() => {
    'use strict';

    const initialise = () => {
        const modal = document.getElementById('subscription-pause-modal');

        if (!modal) {
            return;
        }

        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }

        class SubscriptionPauseController {
            constructor(element) {
                this.modal = element;
                this.title = element.querySelector('#subscription-pause-title');
                this.review = element.querySelector('#subscription-pause-review');
                this.impact = element.querySelector('#subscription-pause-impact');
                this.message = element.querySelector('#subscription-pause-message');
                this.confirmButton = element.querySelector('#subscription-pause-confirm');
                this.cancelButton = element.querySelector('#subscription-pause-cancel');
                this.state = 'closed';
                this.flow = null;
                this.trigger = null;
                this.bindEvents();
            }

            bindEvents() {
                document.addEventListener('click', event => {
                    const trigger = event.target.closest('[data-open-subscription-pause]');

                    if (trigger) {
                        event.preventDefault();
                        this.open(trigger);
                        return;
                    }

                    if (event.target.closest('[data-subscription-pause-close]') || event.target === this.modal) {
                        this.close();
                    }
                });

                document.addEventListener('keydown', event => {
                    if (event.key === 'Escape' && this.state !== 'closed') {
                        this.close();
                    }
                });

                this.confirmButton.addEventListener('click', () => this.submit());
            }

            open(trigger) {
                let flow;

                try {
                    flow = JSON.parse(trigger.dataset.subscriptionPause || '{}');
                } catch {
                    return;
                }

                if (!flow?.endpoint) {
                    return;
                }

                this.flow = flow;
                this.trigger = trigger;
                this.state = 'review';
                this.render();
                this.modal.hidden = false;
                this.modal.classList.add('open');
                document.body.classList.add('subscription-pause-open');
                this.cancelButton.focus();
            }

            close() {
                if (this.state === 'submitting') {
                    return;
                }

                this.modal.classList.remove('open');
                this.modal.hidden = true;
                document.body.classList.remove('subscription-pause-open');
                this.state = 'closed';
                this.flow = null;
                this.clearMessage();
                this.trigger?.focus();
                this.trigger = null;
            }

            render() {
                this.title.textContent = this.flow.title || 'Pause subscription';
                this.review.textContent = this.flow.review_copy || '';
                this.cancelButton.textContent = this.flow.cancel_label || 'Keep subscription active';
                this.confirmButton.textContent = this.flow.confirm_label || 'Confirm pause';
                this.impact.replaceChildren();

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
                    if (!this.flow[key]) {
                        continue;
                    }

                    const item = document.createElement('li');
                    item.textContent = this.flow[key];
                    this.impact.append(item);
                }

                this.setSubmitting(false);
                this.clearMessage();
            }

            async submit() {
                if (this.state === 'submitting' || !this.flow?.endpoint) {
                    return;
                }

                this.state = 'submitting';
                this.setSubmitting(true);
                this.clearMessage();

                try {
                    const response = await fetch(this.flow.endpoint, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                        body: JSON.stringify({}),
                    });
                    const payload = await response.json().catch(() => ({}));
                    const result = payload.data ?? payload;

                    if (response.status === 401) {
                        window.location.assign(this.modal.dataset.loginUrl || '/member/login');
                        return;
                    }

                    if (!response.ok || result.success === false) {
                        throw new Error(result.message || 'The subscription could not be paused.');
                    }

                    this.state = 'success';
                    this.confirmButton.textContent = 'Paused';
                    window.location.reload();
                } catch (error) {
                    this.state = 'error';
                    this.showError(error.message || 'The subscription could not be paused.');
                    this.setSubmitting(false);
                }
            }

            setSubmitting(submitting) {
                this.confirmButton.disabled = submitting;
                this.cancelButton.disabled = submitting;
                this.confirmButton.textContent = submitting
                    ? 'Pausing…'
                    : (this.flow?.confirm_label || 'Confirm pause');
            }

            showError(message) {
                this.message.textContent = message;
                this.message.classList.add('is-visible', 'is-error');
            }

            clearMessage() {
                this.message.textContent = '';
                this.message.classList.remove('is-visible', 'is-error');
            }
        }

        new SubscriptionPauseController(modal);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialise, { once: true });
    } else {
        initialise();
    }
})();
