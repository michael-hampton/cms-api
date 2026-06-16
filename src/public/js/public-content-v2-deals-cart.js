(() => {
    'use strict';

    class DealsCartController {
        constructor(root) {
            this.root = root;
            this.onClick = this.onClick.bind(this);
        }

        start() {
            if (this.root.dataset.dealsCartHydrated === 'true') return;
            this.root.dataset.dealsCartHydrated = 'true';
            this.root.addEventListener('click', this.onClick);
        }

        async onClick(event) {
            const button = event.target.closest('[data-action="add-to-cart"]');
            if (!button || !this.root.contains(button)) return;

            event.preventDefault();
            event.stopPropagation();

            const productId = Number(button.dataset.productId);
            if (!Number.isInteger(productId) || productId <= 0) return;

            const label = button.querySelector('span');
            const original = label?.textContent ?? 'Add to Cart';
            button.disabled = true;
            if (label) label.textContent = 'Adding…';

            try {
                const response = await fetch(`/api/${window.SITE}/cart/add`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({product_id: productId, quantity: 1}),
                });

                const payload = await response.json();
                if (!response.ok || payload.success === false) {
                    throw new Error(payload.message ?? payload.error ?? 'Unable to add this deal to your cart.');
                }

                if (label) label.textContent = 'Added';
                this.updateCartCount(payload);
                document.dispatchEvent(new CustomEvent('cart:updated', {detail: payload}));

                window.setTimeout(() => {
                    if (label) label.textContent = original;
                }, 1500);
            } catch (error) {
                if (label) label.textContent = 'Try Again';
                window.alert(error.message ?? 'Unable to add this deal to your cart.');
                window.setTimeout(() => {
                    if (label) label.textContent = original;
                }, 1500);
            } finally {
                button.disabled = false;
            }
        }

        updateCartCount(payload) {
            const count = Number(
                payload.cart_count
                ?? payload.data?.cart_count
                ?? payload.data?.count
                ?? payload.count
                ?? NaN,
            );

            if (!Number.isFinite(count)) return;

            const badge = document.getElementById('cart-count');
            if (!badge) return;

            badge.textContent = String(count);
            badge.style.display = count > 0 ? 'block' : 'none';
        }
    }

    const initialise = root => {
        root.querySelectorAll('.deals-carousel-wrapper').forEach(element => {
            new DealsCartController(element).start();
        });
    };

    document.addEventListener('public-content:document-composed', event => {
        initialise(event.detail.root);
    });
})();
