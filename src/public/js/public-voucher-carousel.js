(() => {
    'use strict';

    const find = (root, selector) => root.querySelector(selector);

    const setText = (root, selector, value) => {
        const element = find(root, selector);
        if (element) {
            element.textContent = value || '';
        }
    };

    const openVoucherModal = trigger => {
        const modal = document.querySelector('[data-voucher-modal]');
        if (!modal) {
            return;
        }

        setText(modal, '[data-voucher-modal-title]', trigger.dataset.voucherTitle);
        setText(modal, '[data-voucher-modal-description]', trigger.dataset.voucherDescription);
        setText(modal, '[data-voucher-modal-code]', trigger.dataset.voucherCode);
        setText(modal, '[data-voucher-modal-saving]', trigger.dataset.voucherSaving || 'Voucher code');
        setText(modal, '[data-voucher-modal-expires]', trigger.dataset.voucherExpires || 'Limited time');

        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('public-voucher-modal-open');
    };

    const closeVoucherModal = modal => {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('public-voucher-modal-open');
    };

    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-voucher-modal-trigger]');
        if (trigger) {
            openVoucherModal(trigger);
            return;
        }

        const close = event.target.closest('[data-voucher-modal-close]');
        if (close) {
            const modal = close.closest('[data-voucher-modal]');
            if (modal) {
                closeVoucherModal(modal);
            }
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') {
            return;
        }

        const modal = document.querySelector('[data-voucher-modal]:not([hidden])');
        if (modal) {
            closeVoucherModal(modal);
        }
    });
})();
