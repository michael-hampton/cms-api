<?php
/**
 * Payment method selector — radio card group.
 *
 * Drives visibility of:
 *   #saved-cards-section / #new-card-section  (card flow)
 *   #paypal-section                            (if present)
 *   #bank-section                              (if present)
 *
 * @var array|null $methods Array of ['value','name','description']. Defaults to card/paypal/bank.
 * @var string $selected Currently selected value. Defaults to 'card'.
 */
$selected = $selected ?? 'card';
$methods = $methods ?? [
        ['value' => 'card', 'name' => 'Credit / Debit Card', 'description' => 'Visa, Mastercard, American Express'],
        ['value' => 'paypal', 'name' => 'PayPal', 'description' => 'Pay securely with your PayPal account'],
        ['value' => 'bank', 'name' => 'Bank Transfer', 'description' => 'Direct bank transfer'],
];
?>
<style>
    .payment-methods {
        display: grid;
        gap: 1rem;
    }

    .payment-method {
        border: 2px solid var(--border-color);
        border-radius: .5rem;
        padding: 1rem;
        cursor: pointer;
        transition: all .3s;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .payment-method:hover {
        border-color: var(--primary-color);
    }
    .payment-method.selected {
        border-color: var(--primary-color);
        background: rgba(37, 99, 235, .05);
    }

    .payment-radio {
        width: 20px;
        height: 20px;
        cursor: pointer;
    }

    .payment-info .payment-name {
        font-weight: 600;
        margin-bottom: .25rem;
    }

    .payment-info .payment-description {
        font-size: .875rem;
        color: var(--text-secondary);
    }
</style>

<div class="form-section">
    <h2 class="section-title">Payment Method</h2>
    <div class="payment-methods" id="payment-method-selector">
        <?php foreach ($methods as $method): ?>
            <label class="payment-method <?= $method['value'] === $selected ? 'selected' : '' ?>"
                   data-method="<?= htmlspecialchars($method['value']) ?>">
                <input type="radio"
                       name="payment_method"
                       value="<?= htmlspecialchars($method['value']) ?>"
                       class="payment-radio"
                        <?= $method['value'] === $selected ? 'checked' : '' ?>>
                <div class="payment-info">
                    <div class="payment-name"><?= htmlspecialchars($method['name']) ?></div>
                    <?php if (!empty($method['description'])): ?>
                        <div class="payment-description"><?= htmlspecialchars($method['description']) ?></div>
                    <?php endif; ?>
                </div>
            </label>
        <?php endforeach; ?>
    </div>
</div>

<script>
    (function () {
        /**
         * Show only the payment section that matches `method`.
         * Sections are identified by data-payment-section="card|paypal|bank".
         * Falls back gracefully when a section element doesn't exist.
         */
        function showPaymentSection(method) {
            // Card-specific sections
            const savedCardsSection = document.getElementById('saved-cards-section');
            const newCardSection = document.getElementById('new-card-section');
            const cardErrors = document.getElementById('card-errors');

            // Generic additional sections
            document.querySelectorAll('[data-payment-section]').forEach(function (el) {
                el.style.display = el.dataset.paymentSection === method ? 'block' : 'none';
            });

            if (method === 'card') {
                // Reveal saved cards if available, otherwise the new-card form
                if (savedCardsSection && savedCardsSection.querySelector('.saved-card')) {
                    savedCardsSection.style.display = 'block';
                    if (newCardSection) newCardSection.style.display = 'none';
                } else {
                    if (savedCardsSection) savedCardsSection.style.display = 'none';
                    if (newCardSection) newCardSection.style.display = 'block';
                }
            } else {
                // Non-card: hide both card sections
                if (savedCardsSection) savedCardsSection.style.display = 'none';
                if (newCardSection) newCardSection.style.display = 'none';
            }

            // Expose currently selected method globally so the checkout submit handler
            // can read it without querying the DOM
            window.selectedPaymentMethod = method;

            // Hook for page-level custom handling (optional)
            if (typeof window.onPaymentMethodChange === 'function') {
                window.onPaymentMethodChange(method);
            }
        }

        function init() {
            const selector = document.getElementById('payment-method-selector');
            if (!selector) return;

            selector.querySelectorAll('.payment-method').forEach(function (el) {
                el.addEventListener('click', function () {
                    selector.querySelectorAll('.payment-method').forEach(function (m) {
                        m.classList.remove('selected');
                    });
                    this.classList.add('selected');
                    const radio = this.querySelector('input[type="radio"]');
                    if (radio) radio.checked = true;
                    showPaymentSection(this.dataset.method);
                });
            });

            // Trigger once on load so the initial selected state is correct
            const initial = selector.querySelector('.payment-method.selected');
            if (initial) showPaymentSection(initial.dataset.method);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
</script>