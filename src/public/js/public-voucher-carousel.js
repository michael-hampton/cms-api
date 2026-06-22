(() => {
    'use strict';

    const find = (root, selector) => root.querySelector(selector);

    const setText = (root, selector, value) => {
        const element = find(root, selector);
        if (element) {
            element.textContent = value || '';
        }
    };

    const setCopyButtonText = (button, value) => {
        button.textContent = value;
        window.setTimeout(() => {
            button.textContent = 'Copy';
        }, 1400);
    };

    const formatCurrency = value => {
        if (value === null || value === undefined || value === '') {
            return '';
        }

        const amount = Number(value);
        if (Number.isNaN(amount)) {
            return '';
        }

        return `£${amount.toFixed(2)}`;
    };

    const formatDate = value => {
        if (!value) {
            return 'Limited time';
        }

        const date = new Date(value.replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) {
            return value;
        }

        return date.toLocaleDateString(undefined, {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
    };

    const openVoucherModal = card => {
        const modal = document.querySelector('[data-voucher-modal]');
        if (!modal) {
            return;
        }

        const descriptionParts = [];
        if (card.dataset.description) {
            descriptionParts.push(card.dataset.description);
        }
        if (card.dataset.minimumOrderValue) {
            descriptionParts.push(`Minimum spend ${formatCurrency(card.dataset.minimumOrderValue)}.`);
        }
        if (card.dataset.maximumDiscount) {
            descriptionParts.push(`Maximum saving ${formatCurrency(card.dataset.maximumDiscount)}.`);
        }
        if (card.dataset.terms) {
            descriptionParts.push(card.dataset.terms);
        }

        setText(modal, '[data-voucher-modal-title]', card.dataset.title || 'Voucher code');
        setText(modal, '[data-voucher-modal-description]', descriptionParts.join(' '));
        setText(modal, '[data-voucher-modal-code]', card.dataset.code);
        setText(modal, '[data-voucher-modal-saving]', card.dataset.discountLabel || 'Voucher code');
        setText(modal, '[data-voucher-modal-expires]', formatDate(card.dataset.expiresAt));

        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('public-voucher-modal-open');

        const copyButton = find(modal, '[data-voucher-modal-copy]');
        if (copyButton) {
            copyButton.focus({preventScroll: true});
        }
    };

    const closeVoucherModal = modal => {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('public-voucher-modal-open');
    };

    const selectVoucherCode = codeElement => {
        const range = document.createRange();
        range.selectNodeContents(codeElement);

        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
    };

    const copyVoucherCode = button => {
        const modal = button.closest('[data-voucher-modal]');
        const codeElement = modal ? find(modal, '[data-voucher-modal-code]') : null;
        const code = codeElement ? codeElement.textContent.trim() : '';

        if (!code || !codeElement) {
            return;
        }

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(code)
                .then(() => setCopyButtonText(button, 'Copied'))
                .catch(() => {
                    selectVoucherCode(codeElement);
                    setCopyButtonText(button, 'Selected');
                });
            return;
        }

        selectVoucherCode(codeElement);
        setCopyButtonText(button, 'Selected');
    };

    const scrollCarousel = (button, direction) => {
        const carousel = button.closest('.public-voucher-carousel');
        const track = carousel ? find(carousel, '[data-voucher-carousel-track]') : null;
        if (!track) {
            return;
        }

        track.scrollBy({
            left: direction * Math.max(240, Math.floor(track.clientWidth * 0.8)),
            behavior: 'smooth',
        });
    };

    document.addEventListener('click', event => {
        const openButton = event.target.closest('[data-voucher-open]');
        if (openButton) {
            const card = openButton.closest('[data-voucher-card]');
            if (card) {
                openVoucherModal(card);
            }
            return;
        }

        const previous = event.target.closest('[data-voucher-carousel-prev]');
        if (previous) {
            scrollCarousel(previous, -1);
            return;
        }

        const next = event.target.closest('[data-voucher-carousel-next]');
        if (next) {
            scrollCarousel(next, 1);
            return;
        }

        const copy = event.target.closest('[data-voucher-modal-copy]');
        if (copy) {
            copyVoucherCode(copy);
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
