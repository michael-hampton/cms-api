<?php
$currency = $currency ?? '$';
$apiBase = $apiBase ?? '';
$appliedVoucher = $appliedVoucher ?? null;
$hasPreOrders = $hasPreOrders ?? [];
$isSubscription = $isSubscription ?? false;
$submitBtnId = $submitBtnId ?? 'place-order-btn';
$submitBtnLabel = $submitBtnLabel ?? 'Place Order';
$backUrl = $backUrl ?? '/cart';
$isCartPage = $isCartPage ?? false;

$hasAppliedVoucher = !empty($appliedVoucher['code']);
?>

<div class="order-summary" style="background: white; border-radius: 0.75rem; padding: 1.5rem;
     box-shadow: var(--shadow); height: fit-content; position: sticky; top: 100px;">

    <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem;
               padding-bottom: 1rem; border-bottom: 2px solid var(--border-color);">
        Order Summary
        <?php if (!empty($displayCurrency)): ?>
            <span style="display: inline-block; background: #eff6ff; color: var(--primary-color);
                         font-size: 0.75rem; font-weight: 600; padding: 0.125rem 0.5rem;
                         border-radius: 0.25rem; border: 1px solid #bfdbfe;
                         margin-left: 0.5rem; vertical-align: middle;">
                <?= htmlspecialchars($displayCurrency) ?>
            </span>
        <?php endif; ?>
    </h3>

    <!-- Item rows (merchant-grouped) -->
    <div id="order-items">
        @include('checkout/components/order-summary-items', [
        'items' => $items ?? [],
        'currency' => $currency,
        'showDivider' => true,
        ])
    </div>

    <!-- Voucher section -->
    @include('checkout/components/voucher-section', [
    'currency' => $currency,
    'sessionVoucherCode' => $appliedVoucher['code'] ?? null,
    'appliedDiscount' => $appliedVoucher['discount'] ?? null,
    ])

    <!-- Totals -->
    <div class="summary-row" id="discount-row"
         style="<?= $hasAppliedVoucher ? 'display: flex;' : 'display: none;' ?> color: var(--success-color);">
        <span>Discount:</span>
        <span id="discount-amount">
            <?= $hasAppliedVoucher
                    ? '-' . $currency . number_format((float)$appliedVoucher['discount'], 2)
                    : '' ?>
        </span>
    </div>

    <div class="summary-row">
        <span>Subtotal:</span>
        <span id="subtotal"><?= $currency ?> <?= number_format((float)($subtotal ?? 0), 2) ?></span>
    </div>
    <div class="summary-row">
        <span>Shipping:</span>
        <span id="shipping">
            <?php $shippingAmt = (float)($shipping ?? 0); ?>
            <?= $shippingAmt > 0 ? $currency . ' ' . number_format($shippingAmt, 2) : 'Free' ?>
        </span>
    </div>
    <div class="summary-row">
        <span>Tax:</span>
        <span id="tax"><?= $currency ?> <?= number_format((float)($tax ?? 0), 2) ?></span>
    </div>
    <div class="summary-row total" style="font-size: 1.25rem; font-weight: 700;
         padding-top: 1rem; border-top: 2px solid var(--border-color); margin-top: 1rem;">
        <span>Total:</span>
        <span id="total" data-total="<?= (float)($finalTotal ?? 0) ?>">
            <?= $currency ?> <?= number_format((float)($finalTotal ?? 0), 2) ?>
        </span>
    </div>

    <!-- Pre-order warning -->
    @include('checkout/components/pre-order-warning', ['preOrders' => $hasPreOrders])

    <!-- Auto-renewal consent (subscriptions only) -->
    <?php if ($isSubscription): ?>
        @include('checkout/components/auto-renewal-consent', [
        'showGlobal' => true,
        'showUs'     => false,
        ])
    <?php endif; ?>

    <!-- CTA -->
    <?php if ($isCartPage): ?>
        @include('checkout/components/form/button', [
        'id'      => 'checkout-btn',
        'label'   => 'Proceed to Checkout',
        'variant' => 'primary',
        'onclick' => 'proceedToCheckout()',
        ])
    <?php else: ?>
        @include('checkout/components/form/button', [
        'id'      => $submitBtnId,
        'label'   => $submitBtnLabel,
        'variant' => 'primary',
        'onclick' => ''
        ])
    <?php endif; ?>

    @include('checkout/components/form/button', [
    'label' => 'Continue Shopping',
    'variant' => 'secondary',
    'onclick' => "window.location.href='/cart'",
    ])

    @include('checkout/components/security-badge')
</div>