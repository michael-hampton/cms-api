(() => {
    'use strict';

    class RenewalOfferModalState {
        constructor() {
            this.offer = null;
            this.isOpen = false;
        }

        setOffer(offer) {
            this.offer = offer || {};
        }

        clear() {
            this.offer = null;
            this.isOpen = false;
        }

        open() {
            this.isOpen = true;
        }

        close() {
            this.isOpen = false;
        }
    }

    class RenewalOfferModalController {
        constructor(modal, state) {
            this.modal = modal;
            this.state = state;

            this.elements = {
                description: document.getElementById('renewal-offer-description'),
                price: document.getElementById('renewal-offer-price'),
                term: document.getElementById('renewal-offer-term'),
                renewalDate: document.getElementById('renewal-offer-date'),
            };

            this.bindEvents();
        }

        bindEvents() {
            document.addEventListener('click', event => this.handleDocumentClick(event));
            document.addEventListener('keydown', event => this.handleKeydown(event));
        }

        handleDocumentClick(event) {
            const trigger = event.target.closest('[data-open-renewal-offer]');

            if (trigger) {
                this.handleOpenTrigger(trigger);
                return;
            }

            if (event.target.closest('[data-renewal-offer-close]')) {
                this.close();
                return;
            }

            if (event.target === this.modal) {
                this.close();
            }
        }

        handleKeydown(event) {
            if (event.key === 'Escape' && this.state.isOpen) {
                this.close();
            }
        }

        handleOpenTrigger(trigger) {
            const offer = this.parseOffer(trigger);

            if (!offer) {
                return;
            }

            this.open(offer);
        }

        parseOffer(trigger) {
            try {
                const payload = JSON.parse(trigger.dataset.renewalOffer || '{}');

                if (!payload || typeof payload !== 'object') {
                    return null;
                }

                return payload;
            } catch {
                return null;
            }
        }

        open(offer) {
            this.state.setOffer(offer);
            this.state.open();

            this.render();
            this.modal.hidden = false;
        }

        close() {
            this.state.clear();
            this.modal.hidden = true;
            this.render();
        }

        render() {
            const offer = this.state.offer || {};

            this.setText(
                this.elements.description,
                offer.description || offer.label || 'Renewal offer',
            );

            this.setText(this.elements.price, offer.price_label || '');
            this.setText(this.elements.term, offer.term || '');
            this.setText(this.elements.renewalDate, offer.renewal_date || '');
        }

        setText(element, value) {
            if (!element) {
                return;
            }

            element.textContent = value;
        }
    }

    const modal = document.getElementById('renewal-offer-modal');

    if (!modal) {
        return;
    }

    new RenewalOfferModalController(
        modal,
        new RenewalOfferModalState(),
    );
})();