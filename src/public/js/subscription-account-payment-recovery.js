(() => {
    'use strict';

    const initialise = () => {
        const modal = document.getElementById('payment-recovery-modal');

        if (!modal) {
            return;
        }

        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }

        class SubscriptionPaymentRecoveryController {
            constructor(element) {
                this.modal = element;
                this.frame = element.querySelector('#payment-recovery-frame');
                this.message = element.querySelector('#payment-recovery-message');
                this.copy = element.querySelector('#payment-recovery-copy');
                this.closeButtons = element.querySelectorAll('[data-payment-recovery-close]');
                this.state = 'closed';
                this.trigger = null;
                this.recoveryUrl = null;

                if (!this.frame) {
                    return;
                }

                this.configureFrame();
                this.bindEvents();
            }

            configureFrame() {
                this.frame.style.width = '100%';
                this.frame.style.minHeight = '70vh';
                this.frame.style.border = '0';
            }

            bindEvents() {
                document.addEventListener('click', event => {
                    const trigger = event.target.closest('[data-open-payment-recovery]');

                    if (trigger) {
                        event.preventDefault();
                        this.open(trigger);
                        return;
                    }

                    if (event.target.closest('[data-payment-recovery-close]') || event.target === this.modal) {
                        this.close();
                    }
                });

                document.addEventListener('keydown', event => {
                    if (event.key === 'Escape' && this.state !== 'closed') {
                        this.close();
                    }
                });
            }

            open(trigger) {
                this.trigger = trigger;
                this.recoveryUrl = trigger.dataset.paymentRecoveryUrl || '';
                this.state = this.recoveryUrl ? 'loading' : 'unavailable';
                this.render();
                this.modal.hidden = false;
                this.modal.classList.add('open');
                document.body.classList.add('payment-recovery-open');
                this.closeButtons[0]?.focus();
            }

            close() {
                if (this.state === 'closed') {
                    return;
                }

                this.modal.classList.remove('open');
                this.modal.hidden = true;
                document.body.classList.remove('payment-recovery-open');
                this.frame.hidden = true;
                this.frame.removeAttribute('src');
                this.clearMessage();
                this.state = 'closed';
                this.recoveryUrl = null;
                this.trigger?.focus();
                this.trigger = null;
            }

            render() {
                this.clearMessage();
                this.frame.hidden = true;
                this.frame.removeAttribute('src');

                if (!this.recoveryUrl) {
                    this.showError('Payment recovery is not available for this subscription.');
                    return;
                }

                if (this.copy && this.trigger?.dataset.paymentRecoveryLabel) {
                    this.copy.textContent = `${this.trigger.dataset.paymentRecoveryLabel} to restore your subscription.`;
                }

                this.frame.src = this.recoveryUrl;
                this.frame.hidden = false;
                this.state = 'ready';
            }

            showError(message) {
                if (!this.message) {
                    return;
                }

                this.message.textContent = message;
                this.message.classList.add('is-visible', 'is-error');
            }

            clearMessage() {
                if (!this.message) {
                    return;
                }

                this.message.textContent = '';
                this.message.classList.remove('is-visible', 'is-error');
            }
        }

        new SubscriptionPaymentRecoveryController(modal);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialise, { once: true });
    } else {
        initialise();
    }
})();
