(() => {
    'use strict';

    const MODAL_ID = 'public-voucher-code-modal';

    const formatMoney = value => {
        if (value === null || value === undefined || value === '') return null;
        const numeric = Number(value);
        if (Number.isNaN(numeric)) return null;
        return `£${numeric.toFixed(2)}`;
    };

    const formatDate = value => {
        if (!value) return null;
        const date = new Date(value.replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return null;
        return date.toLocaleDateString(undefined, {day: 'numeric', month: 'short', year: 'numeric'});
    };

    const ensureModal = () => {
        let modal = document.getElementById(MODAL_ID);
        if (modal) return modal;

        modal = document.createElement('div');
        modal.id = MODAL_ID;
        modal.className = 'public-voucher-modal';
        modal.hidden = true;
        modal.innerHTML = `
            <div class="public-voucher-modal__backdrop" data-voucher-close></div>
            <div class="public-voucher-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="public-voucher-modal-title">
                <button type="button" class="public-voucher-modal__close" data-voucher-close aria-label="Close voucher modal">×</button>
                <p class="public-voucher-modal__eyebrow" data-voucher-modal-saving></p>
                <h2 id="public-voucher-modal-title" class="public-voucher-modal__title" data-voucher-modal-title></h2>
                <p class="public-voucher-modal__description" data-voucher-modal-description></p>
                <div class="public-voucher-modal__code-row">
                    <code class="public-voucher-modal__code" data-voucher-modal-code></code>
                    <button type="button" class="public-voucher-modal__copy" data-voucher-copy>Copy code</button>
                </div>
                <dl class="public-voucher-modal__details" data-voucher-modal-details></dl>
                <p class="public-voucher-modal__terms" data-voucher-modal-terms></p>
            </div>
        `;
        document.body.append(modal);
        return modal;
    };

    const openModal = card => {
        const modal = ensureModal();
        const data = card.dataset;
        const details = [];
        const minSpend = formatMoney(data.minimumOrderValue);
        const maxDiscount = formatMoney(data.maximumDiscount);
        const expires = formatDate(data.expiresAt);

        if (minSpend) details.push(['Minimum spend', minSpend]);
        if (maxDiscount) details.push(['Maximum discount', maxDiscount]);
        if (expires) details.push(['Expires', expires]);

        modal.querySelector('[data-voucher-modal-saving]').textContent = data.discountLabel || 'Voucher code';
        modal.querySelector('[data-voucher-modal-title]').textContent = data.title || 'Voucher code';
        modal.querySelector('[data-voucher-modal-description]').textContent = data.description || 'Use this code at checkout.';
        modal.querySelector('[data-voucher-modal-code]').textContent = data.code || '';
        modal.querySelector('[data-voucher-modal-terms]').textContent = data.terms || '';
        modal.querySelector('[data-voucher-modal-details]').innerHTML = details
            .map(([term, value]) => `<div><dt>${term}</dt><dd>${value}</dd></div>`)
            .join('');

        modal.hidden = false;
        document.documentElement.classList.add('public-voucher-modal-open');
        modal.querySelector('[data-voucher-close]').focus?.();
    };

    const closeModal = () => {
        const modal = document.getElementById(MODAL_ID);
        if (!modal) return;

        modal.hidden = true;
        document.documentElement.classList.remove('public-voucher-modal-open');
    };

    const copyCode = async modal => {
        const code = modal.querySelector('[data-voucher-modal-code]')?.textContent?.trim();
        const button = modal.querySelector('[data-voucher-copy]');
        if (!code || !button) return;

        try {
            await navigator.clipboard.writeText(code);
            button.textContent = 'Copied';
            window.setTimeout(() => { button.textContent = 'Copy code'; }, 1800);
        } catch (error) {
            button.textContent = 'Select code';
        }
    };

    document.addEventListener('click', event => {
        const openButton = event.target.closest('[data-voucher-open]');
        if (openButton) {
            const card = openButton.closest('[data-voucher-card]');
            if (card) openModal(card);
            return;
        }

        if (event.target.closest('[data-voucher-close]')) {
            closeModal();
            return;
        }

        const copyButton = event.target.closest('[data-voucher-copy]');
        if (copyButton) {
            const modal = copyButton.closest(`#${MODAL_ID}`);
            if (modal) copyCode(modal);
            return;
        }

        const prev = event.target.closest('[data-voucher-carousel-prev]');
        const next = event.target.closest('[data-voucher-carousel-next]');
        if (prev || next) {
            const carousel = event.target.closest('.public-voucher-carousel');
            const track = carousel?.querySelector('[data-voucher-carousel-track]');
            if (!track) return;

            const step = Math.max(280, Math.floor(track.clientWidth * 0.8));
            track.scrollBy({left: prev ? -step : step, behavior: 'smooth'});
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') closeModal();
    });
})();
