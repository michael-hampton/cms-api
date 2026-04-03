<?php
/**
 * Payment method selector — radio card group.
 *
 * @var array|null $methods Array of ['value' => '', 'name' => '', 'description' => ''].
 *                           Defaults to card / paypal / bank.
 * @var string $selected Currently selected value. Defaults to 'card'.
 */
$selected = $selected ?? 'card';
$methods = $methods ?? [
        [
                'value' => 'card',
                'name' => 'Credit / Debit Card',
                'description' => 'Visa, Mastercard, American Express',
        ],
        [
                'value' => 'paypal',
                'name' => 'PayPal',
                'description' => 'Pay securely with your PayPal account',
        ],
        [
                'value' => 'bank',
                'name' => 'Bank Transfer',
                'description' => 'Direct bank transfer',
        ],
];
?>
<style>
    .payment-methods {
        display: grid;
        gap: 1rem;
    }

    .payment-method {
        border: 2px solid var(--border-color);
        border-radius: 0.5rem;
        padding: 1rem;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .payment-method:hover {
        border-color: var(--primary-color);
    }

    .payment-method.selected {
        border-color: var(--primary-color);
        background: rgba(37, 99, 235, 0.05);
    }

    .payment-radio {
        width: 20px;
        height: 20px;
        cursor: pointer;
    }

    .payment-info .payment-name {
        font-weight: 600;
        margin-bottom: 0.25rem;
    }

    .payment-info .payment-description {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }
</style>

<div class="payment-methods" id="payment-method-selector">
    <?php foreach ($methods as $method): ?>
        <label class="payment-method <?= $method['value'] === $selected ? 'selected' : '' ?>"
               data-method="<?= htmlspecialchars($method['value']) ?>">
            <input
                    type="radio"
                    name="payment_method"
                    value="<?= htmlspecialchars($method['value']) ?>"
                    class="payment-radio"
                    <?= $method['value'] === $selected ? 'checked' : '' ?>
            >
            <div class="payment-info">
                <div class="payment-name"><?= htmlspecialchars($method['name']) ?></div>
                <?php if (!empty($method['description'])): ?>
                    <div class="payment-description"><?= htmlspecialchars($method['description']) ?></div>
                <?php endif; ?>
            </div>
        </label>
    <?php endforeach; ?>
</div>

<script>
    (function () {
        const selector = document.getElementById('payment-method-selector');
        if (!selector) return;
        selector.querySelectorAll('.payment-method').forEach(el => {
            el.addEventListener('click', function () {
                selector.querySelectorAll('.payment-method').forEach(m => m.classList.remove('selected'));
                this.classList.add('selected');
                this.querySelector('input[type="radio"]').checked = true;
            });
        });
    })();
</script>