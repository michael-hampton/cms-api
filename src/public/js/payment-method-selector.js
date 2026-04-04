(function () {
    function initPaymentMethodSelector() {
        document.querySelectorAll('.payment-method').forEach(function (method) {
            method.addEventListener('click', function () {
                document.querySelectorAll('.payment-method')
                    .forEach(function (m) {
                        m.classList.remove('selected');
                    });

                this.classList.add('selected');

                var radio = this.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;

                // Optional hook — implement in page script to react to changes
                if (typeof window.onPaymentMethodChange === 'function') {
                    window.onPaymentMethodChange(this.dataset.method);
                }
            });
        });
    }

    // Run after DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPaymentMethodSelector);
    } else {
        initPaymentMethodSelector();
    }
})();