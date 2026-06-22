(() => {
    'use strict';

    const initialise = () => {
        const modal = document.getElementById('renewal-offer-modal');

        if (!modal) {
            return;
        }

        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }

        class RenewalOfferModalState {
            constructor() {
                this.offer = null;
                this.isOpen = false;
                this.trigger = null;
            }

            open(offer, trigger) {
                this.offer = offer || {};
                this.trigger = trigger || null;
                this.isOpen = true;
            }

            close() {
                this.offer = null;
                this.trigger = null;
                this.isOpen = false;
            }
        }

        class RenewalOfferModalController {
            constructor(element, state) {
                this.modal = element;
                this.state = state;

                this.elements = {
                    description: element.querySelector('#renewal-offer-description'),
                    price: element.querySelector('#renewal-offer-price'),
                    term: element.querySelector('#renewal-offer-term'),
                    renewalDate: element.querySelector('#renewal-offer-date'),
                    closeButton: element.querySelector('[data-renewal-offer-close]'),
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
                    event.preventDefault();
                    this.handleOpenTrigger(trigger);
                    return;
                }

                if (event.target.closest('[data-renewal-offer-close]') || event.target === this.modal) {
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

                this.open(offer, trigger);
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

            open(offer, trigger) {
                this.state.open(offer, trigger);
                this.render();

                this.modal.hidden = false;
                this.modal.classList.add('open');
                document.body.classList.add('renewal-offer-open');

                this.elements.closeButton?.focus();
            }

            close() {
                const previousTrigger = this.state.trigger;

                this.modal.classList.remove('open');
                this.modal.hidden = true;
                document.body.classList.remove('renewal-offer-open');

                this.state.close();
                this.render();

                previousTrigger?.focus();
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

        new RenewalOfferModalController(
            modal,
            new RenewalOfferModalState(),
        );
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialise, { once: true });
    } else {
        initialise();
    }
})();