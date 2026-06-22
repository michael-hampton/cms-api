(() => {
    'use strict';

    class OrderAccountPage {
        static STEPS = ['Review', 'Reason', 'Done'];

        #state = {
            orderId: null,
            orderNumber: '',
            orderTotal: '',
            step: 1,
            completed: false,
            returnFocus: null,
        };

        #els = {};

        constructor(root = document) {
            this.root = root;
            this.#bindElements();
            this.#bindEvents();
        }

        #bindElements() {
            this.#els = {
                filterForm: this.root.getElementById('orders-filter-form'),
                filterSearch: this.root.getElementById('filter-search'),
                filterDateFrom: this.root.getElementById('filter-date-from'),
                filterDateTo: this.root.getElementById('filter-date-to'),
                filterStatus: this.root.getElementById('filter-status'),
                modal: this.root.getElementById('order-cancel-modal'),
                summary: this.root.getElementById('oc-order-summary'),
                indicator: this.root.getElementById('oc-step-indicator'),
                footer: this.root.getElementById('oc-modal-footer'),
            };
        }

        #bindEvents() {
            this.root.addEventListener('click', event => this.#handleClick(event));
            this.root.addEventListener('change', event => this.#handleChange(event));
            this.root.addEventListener('keydown', event => {
                if (event.key === 'Escape' && this.#els.modal?.classList.contains('open')) {
                    this.#closeModal();
                }
            });
        }

        #handleClick(event) {
            const cancelTrigger = event.target.closest('[data-order-cancel]');
            if (cancelTrigger) {
                this.#openModal(cancelTrigger);
                return;
            }

            const action = event.target.closest('[data-order-action]')?.dataset.orderAction;
            if (!action) {
                if (event.target === this.#els.modal) {
                    this.#closeModal();
                }
                return;
            }

            const actions = {
                clearFilters: () => this.#clearFilters(),
                close: () => this.#closeModal(),
                next: () => this.#setState({step: Math.min(3, this.#state.step + 1)}),
                back: () => this.#setState({step: Math.max(1, this.#state.step - 1)}),
                submit: button => this.#submitCancellation(button),
            };
            actions[action]?.(event.target.closest('[data-order-action]'));
        }

        #handleChange(event) {
            if (event.target.matches('[data-order-filter-submit]')) {
                this.#normaliseDateRange();
                this.#els.filterForm?.submit();
                return;
            }

            if (event.target.matches('input[name="oc_reason"]')) {
                this.root.querySelectorAll('.oc-reason-radio').forEach(label => {
                    label.classList.toggle('selected', label.contains(event.target));
                    label.classList.remove('is-invalid');
                });
            }
        }

        #openModal(trigger) {
            this.#state = {
                orderId: trigger.dataset.orderId,
                orderNumber: trigger.dataset.orderNumber || '',
                orderTotal: trigger.dataset.orderTotal || '',
                step: 1,
                completed: false,
                returnFocus: trigger,
            };
            this.root.querySelectorAll('input[name="oc_reason"]').forEach(input => {
                input.checked = false;
            });
            this.root.querySelectorAll('.oc-reason-radio').forEach(label => {
                label.classList.remove('selected', 'is-invalid');
            });
            this.#els.modal?.classList.add('open');
            document.body.style.overflow = 'hidden';
            this.#render();
            this.#els.modal?.querySelector('.modal__close')?.focus();
        }

        #closeModal() {
            this.#els.modal?.classList.remove('open');
            document.body.style.overflow = '';
            this.#state.returnFocus?.focus();

            if (this.#state.completed) {
                window.location.assign(this.#els.modal?.dataset.completedUrl || window.location.href);
            }
        }

        #setState(patch) {
            Object.assign(this.#state, patch);
            this.#render();
        }

        #render() {
            if (!this.#els.modal) {
                return;
            }

            this.#els.summary.innerHTML = `
                <div class="oc-order-summary__row"><span>Order</span><strong>${this.#escape(this.#state.orderNumber)}</strong></div>
                <div class="oc-order-summary__row"><span>Total</span><strong>£${this.#escape(this.#state.orderTotal)}</strong></div>`;

            this.#els.indicator.innerHTML = `<div class="steps">${OrderAccountPage.STEPS.map((label, index) => {
                const number = index + 1;
                const className = number < this.#state.step ? 'done' : (number === this.#state.step ? 'active' : '');
                const divider = index < OrderAccountPage.STEPS.length - 1 ? '<div class="step__divider"></div>' : '';
                return `<div class="step ${className}"><div class="step__num">${number < this.#state.step ? '✓' : number}</div>${label}</div>${divider}`;
            }).join('')}</div>`;

            [1, 2, 3].forEach(number => {
                this.root.getElementById(`oc-step-${number}`)?.classList.toggle('active', number === this.#state.step);
            });

            if (this.#state.step === 1) {
                this.#els.footer.innerHTML = `
                    <button class="btn btn--ghost" type="button" data-order-action="close">Keep Order</button>
                    <button class="btn btn--danger" type="button" data-order-action="next">Continue</button>`;
            } else if (this.#state.step === 2) {
                this.#els.footer.innerHTML = `
                    <button class="btn btn--ghost" type="button" data-order-action="back">Back</button>
                    <button class="btn btn--danger" type="button" data-order-action="submit">Confirm Cancel</button>`;
            } else {
                this.#els.footer.innerHTML = `
                    <button class="btn btn--primary" type="button" data-order-action="close">Close</button>`;
            }
        }

        async #submitCancellation(button) {
            const reason = this.root.querySelector('input[name="oc_reason"]:checked')?.value;
            if (!reason) {
                this.root.querySelectorAll('.oc-reason-radio').forEach(label => label.classList.add('is-invalid'));
                return;
            }

            button.disabled = true;
            button.textContent = 'Cancelling…';

            try {
                const endpoint = (this.#els.modal.dataset.cancelEndpoint || '')
                    .replace('__ORDER_ID__', encodeURIComponent(this.#state.orderId));
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': this.root.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({reason}),
                });
                const data = await response.json();
                const payload = data.data ?? data;

                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || 'Something went wrong. Please try again.');
                }

                this.#setState({step: 3, completed: true});
            } catch (error) {
                window.alert(error.message || 'Network error. Please try again.');
                button.disabled = false;
                button.textContent = 'Confirm Cancel';
            }
        }

        #clearFilters() {
            [this.#els.filterSearch, this.#els.filterDateFrom, this.#els.filterDateTo, this.#els.filterStatus]
                .filter(Boolean)
                .forEach(input => {
                    input.value = '';
                });
            this.#els.filterForm?.submit();
        }

        #normaliseDateRange() {
            const from = this.#els.filterDateFrom;
            const to = this.#els.filterDateTo;
            if (from?.value && to?.value && from.value > to.value) {
                [from.value, to.value] = [to.value, from.value];
            }
        }

        #escape(value) {
            const element = document.createElement('div');
            element.textContent = String(value ?? '');
            return element.innerHTML;
        }
    }

    document.addEventListener('DOMContentLoaded', () => new OrderAccountPage());
})();
