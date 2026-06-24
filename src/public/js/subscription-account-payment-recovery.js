(() => {
    'use strict';

    const modal = document.getElementById('payment-recovery-modal');
    const frame = document.getElementById('payment-recovery-frame');
    const message = document.getElementById('payment-recovery-message');
    const copy = document.getElementById('payment-recovery-copy');

    if (!modal || !frame) {
        return;
    }

    let opener = null;

    function show() {
        modal.hidden = false;
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        modal.querySelector('button, [href], input, select, textarea')?.focus();
    }

    function close() {
        modal.classList.remove('show');
        modal.hidden = true;
        frame.hidden = true;
        frame.removeAttribute('src');
        document.body.style.overflow = '';
        opener?.focus();
        opener = null;
    }

    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-open-payment-recovery]');

        if (trigger) {
            event.preventDefault();
            const url = trigger.dataset.paymentRecoveryUrl || '';
            opener = trigger;

            if (!url) {
                if (message) {
                    message.textContent = 'Payment recovery is not available for this subscription.';
                }
                show();
                return;
            }

            if (message) {
                message.textContent = '';
            }

            if (copy && trigger.dataset.paymentRecoveryLabel) {
                copy.textContent = `${trigger.dataset.paymentRecoveryLabel} to restore your subscription.`;
            }

            frame.src = url;
            frame.hidden = false;
            show();
            return;
        }

        if (event.target.closest('[data-payment-recovery-close]')) {
            close();
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !modal.hidden) {
            close();
        }
    });
})();
