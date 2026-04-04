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

    /* radio-option-card overrides scoped to payment method selector */
    .payment-methods .radio-option-card {
        border: 2px solid var(--border-color);
        border-radius: .5rem;
        padding: 1rem;
        cursor: pointer;
        transition: all .3s;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .payment-methods .radio-option-card:hover {
        border-color: var(--primary-color);
    }

    .payment-methods .radio-option-card:has(.radio-option-input:checked) {
        border-color: var(--primary-color);
        background: rgba(37, 99, 235, .05);
    }

    /* Fallback for browsers without :has() — JS adds .selected */
    .payment-methods .radio-option-card.selected {
        border-color: var(--primary-color);
        background: rgba(37, 99, 235, .05);
    }

    .radio-option-input {
        width: 20px;
        height: 20px;
        cursor: pointer;
        flex-shrink: 0;
    }

    .radio-option-title {
        font-weight: 600;
        margin-bottom: .25rem;
    }

    .radio-option-description {
        font-size: .875rem;
        color: var(--text-secondary);
    }
</style>

<div class="form-section">
    <h2 class="section-title">Payment Method</h2>
    <div class="payment-methods" id="payment-method-selector">
        <?php foreach ($methods as $method):
            $isSelected = ($method['value'] === $selected);

            // Call the engine's internal method directly using $this
            echo $this->partial('checkout/components/form/radio-option', [
                    'name' => 'payment_method',
                    'value' => $method['value'],
                    'id' => 'payment-method-' . $method['value'],
                    'checked' => $isSelected,
                    'cardClass' => 'payment-method' . ($isSelected ? ' selected' : ''),
                    'dataAttr' => 'data-method="' . htmlspecialchars($method['value']) . '"',
                    'content' => '<div class="radio-option-title">' . htmlspecialchars($method['name']) . '</div>'
                            . '<div class="radio-option-description">' . htmlspecialchars($method['description'] ?? '') . '</div>'
            ]);
        endforeach; ?>
    </div>
</div>

<script>
    (function () {
        function showPaymentSection(method) {
            const savedCardsSection = document.getElementById('saved-cards-section');
            const newCardSection = document.getElementById('new-card-section');

            document.querySelectorAll('[data-payment-section]').forEach(function (el) {
                el.style.display = el.dataset.paymentSection === method ? 'block' : 'none';
            });

            if (method === 'card') {
                if (savedCardsSection && savedCardsSection.querySelector('.radio-option-card')) {
                    savedCardsSection.style.display = 'block';
                    if (newCardSection) newCardSection.style.display = 'none';
                } else {
                    if (savedCardsSection) savedCardsSection.style.display = 'none';
                    if (newCardSection) newCardSection.style.display = 'block';
                }
            } else {
                if (savedCardsSection) savedCardsSection.style.display = 'none';
                if (newCardSection) newCardSection.style.display = 'none';
            }

            window.selectedPaymentMethod = method;

            if (typeof window.onPaymentMethodChange === 'function') {
                window.onPaymentMethodChange(method);
            }
        }

        function init() {
            const selector = document.getElementById('payment-method-selector');
            if (!selector) return;

            selector.querySelectorAll('.payment-method').forEach(function (card) {
                card.addEventListener('click', function () {
                    selector.querySelectorAll('.payment-method').forEach(function (m) {
                        m.classList.remove('selected');
                    });
                    this.classList.add('selected');
                    const radio = this.querySelector('input[type="radio"]');
                    if (radio) radio.checked = true;
                    showPaymentSection(this.dataset.method);
                });
            });

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