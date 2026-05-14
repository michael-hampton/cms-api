@extends('checkout/layout/shop')

@section('title', 'Shopping Cart - YourStore')

@section('logic')
<?php
/**
 * Cart page
 *
 * Expected view data (from controller):
 *   array[]     $items         — flat cart items array (with estimated_delivery, trial_days)
 *   float       $subtotal
 *   float       $shipping
 *   float       $tax
 *   float       $tax_rate      — decimal, e.g. 0.20
 *   string      $currency      — display symbol/code
 *   array[]     $hasPreOrders  — pre-order warning entries
 *   array|null  $startOptions  — subscription start-date options keyed by plan_id
 */

use App\Enums\Subscriptions\SubscriptionType;
use App\Framework\Support\SiteContext;
use App\Helpers\CartViewHelpers;

$appliedVoucher = $appliedVoucher ?? null;

$finalTotal = (float)($subtotal ?? 0)
        + (float)($tax ?? 0)
        + (float)($shipping ?? 0)
        - (float)($appliedVoucher['discount'] ?? 0);

$itemsByMerchant = CartViewHelpers::groupByMerchant($items ?? []);

$site = SiteContext::slug();
$apiBase = '/api/' . $site;
?>
@endsection

@section('styles')
<style>
    /* ── Cart layout ──────────────────────────────────────────────── */
    .cart-page-wrapper {
        display: flex;
        flex-direction: column;
    }

    .cart-layout {
        display: grid;
        grid-template-columns: 1fr 400px;
        gap: 2rem;
        margin-bottom: 3rem;
    }

    .cart-items {
        background: white;
        border-radius: .75rem;
        padding: 1.5rem;
        box-shadow: var(--shadow);
    }

    .cart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid var(--border-color);
    }

    .cart-header h2 {
        font-size: 1.25rem;
        font-weight: 600;
    }

    /* ── Cart item row ────────────────────────────────────────────── */
    .cart-item {
        display: grid;
        grid-template-columns: 120px 1fr auto;
        gap: 1.5rem;
        padding: 1.5rem 0;
        border-bottom: 1px solid var(--border-color);
    }

    .cart-item:last-child {
        border-bottom: none;
    }

    .item-image {
        width: 120px;
        height: 120px;
        object-fit: cover;
        border-radius: .5rem;
        border: 1px solid var(--border-color);
    }

    .item-details {
        display: flex;
        flex-direction: column;
        gap: .5rem;
    }

    .item-name {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--text-primary);
        text-decoration: none;
        transition: color .3s;
    }

    .item-name:hover {
        color: var(--primary-color);
    }

    .item-price .sale-price {
        color: var(--danger-color);
        font-weight: 600;
        margin-right: .5rem;
    }

    .quantity-controls {
        display: flex;
        align-items: center;
        gap: .5rem;
        margin-top: .5rem;
    }

    .qty-btn {
        width: 32px;
        height: 32px;
        border: 1px solid var(--border-color);
        background: white;
        border-radius: .375rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all .3s;
    }

    .qty-btn:hover {
        border-color: var(--primary-color);
        color: var(--primary-color);
    }

    .qty-btn:disabled {
        opacity: .5;
        cursor: not-allowed;
    }

    .qty-input {
        width: 60px;
        height: 32px;
        text-align: center;
        border: 1px solid var(--border-color);
        border-radius: .375rem;
        font-weight: 500;
    }

    .item-actions {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        align-items: flex-end;
    }

    .item-subtotal {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .remove-btn {
        background: none;
        border: none;
        color: var(--danger-color);
        cursor: pointer;
        padding: .5rem;
        border-radius: .375rem;
        transition: background-color .3s;
    }

    .remove-btn:hover {
        background: rgba(239, 68, 68, .1);
    }

    /* ── Merchant group ───────────────────────────────────────────── */
    .merchant-group {
        margin-bottom: 2rem;
    }

    .merchant-header {
        padding: 1rem;
        background: #f8fafc;
        border-radius: .5rem;
        margin-bottom: 1rem;
    }

    .merchant-header h3 {
        font-size: 1.125rem;
        font-weight: 600;
        color: #1e293b;
    }

    .merchant-header p {
        font-size: .875rem;
        color: #64748b;
        margin-top: .25rem;
    }

    /* ── Empty state ──────────────────────────────────────────────── */
    /* The empty cart block is a sibling of the grid, NOT inside it.  */
    .empty-cart {
        background: white;
        border-radius: .75rem;
        padding: 4rem 2rem;
        text-align: center;
        box-shadow: var(--shadow);
        margin-bottom: 3rem;
    }

    .empty-cart svg {
        width: 80px;
        height: 80px;
        color: var(--text-secondary);
        margin: 0 auto 1rem;
        display: block;
    }

    .empty-cart h3 {
        font-size: 1.5rem;
        margin-bottom: .5rem;
        color: var(--text-primary);
    }

    .empty-cart p {
        color: var(--text-secondary);
        margin-bottom: 2rem;
    }

    /* ── Loading state ────────────────────────────────────────────── */
    .loading-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 4rem 2rem;
    }

    /* ── Responsive ───────────────────────────────────────────────── */
    @media (max-width: 968px) {
        .cart-layout {
            grid-template-columns: 1fr;
        }

        .cart-item {
            grid-template-columns: 100px 1fr;
            gap: 1rem;
        }

        .item-image {
            width: 100px;
            height: 100px;
        }

        .item-actions {
            grid-column: 2;
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
        }
    }
</style>
@endsection

@section('page-header')
@include('checkout/components/page-header', [
'title'       => 'Shopping Cart',
'breadcrumbs' => [
['label' => 'Home', 'href' => '/'],
['label' => 'Cart'],
],
])
@endsection

@section('content')
<div class="cart-page-wrapper">

    <!-- Loading -->
    <div id="loading-container" class="loading-state" style="display: none;">
        <div class="spinner"></div>
        <p>Loading your cart...</p>
    </div>

    <!-- Empty — sits OUTSIDE the cart-layout grid so it fills the full column width cleanly -->
    <div id="empty-container" class="empty-cart"
         style="display: <?= empty($items) ? 'block' : 'none' ?>;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
            <circle cx="9" cy="21" r="1"></circle>
            <circle cx="20" cy="21" r="1"></circle>
            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
        </svg>
        <h3>Your cart is empty</h3>
        <p>Add some products to get started</p>
        @include('checkout/components/form/button', [
        'label' => 'Continue Shopping',
        'variant' => 'primary',
        'fullWidth' => false,
        'onclick' => "window.location.href='/shop'",
        'style' => 'max-width: 300px; margin: 0 auto;',
        ])
    </div>

    <!-- Cart grid — only shown when items exist -->
    <div id="cart-container" class="cart-layout"
         style="display: <?= empty($items) ? 'none' : 'grid' ?>;">

        <!-- ── Left: item list ──────────────────────────────────── -->
        <div class="cart-items">
            <div class="cart-header">
                <h2>Cart Items (<span id="items-count"><?= count($items ?? []) ?></span>)</h2>
                @include('checkout/components/form/button', [
                'label' => 'Clear Cart',
                'variant' => 'danger',
                'fullWidth' => false,
                'onclick' => 'clearCart()',
                'style' => 'padding: .5rem 1rem;',
                ])
            </div>

            <div id="alert-container"></div>

            <div id="cart-items-list">
                <?php foreach ($itemsByMerchant as $merchantId => $merchantData): ?>
                    <div class="merchant-group">
                        <div class="merchant-header">
                            <h3>
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                     stroke="currentColor"
                                     style="display:inline-block;vertical-align:middle;margin-right:.5rem;"
                                     aria-hidden="true">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                                </svg>
                                <?= htmlspecialchars($merchantData['name']) ?>
                            </h3>
                            <p><?= count($merchantData['items']) ?> item(s)</p>
                        </div>

                        <?php foreach ($merchantData['items'] as $item):
                            $isFreeGift = CartViewHelpers::isFreeGift($item);
                            ?>
                            <div class="cart-item" data-item-id="<?= $item['id'] ?>">

                                <?php if (!empty($item['subscription_plan_id'])): ?>
                                    <?php
                                    $opts = $item['options'] ?? [];
                                    $deliveryType = $opts['delivery_type'] ?? SubscriptionType::DIGITAL->value;
                                    $planName = $item['plan_name'] ?? ($opts['plan_name'] ?? 'Subscription');
                                    $planId = $item['subscription_plan_id'];
                                    ?>
                                    <div style="width:120px;height:120px;border-radius:.5rem;border:1px solid var(--border-color);display:flex;align-items:center;justify-content:center;background:var(--bg-light);position:relative;">
                                        <?php if ($isFreeGift): ?>
                                            <span style="position:absolute;top:-.5rem;left:-.5rem;background:#10b981;color:white;font-size:.7rem;font-weight:700;padding:.2rem .6rem;border-radius:1rem;text-transform:uppercase;z-index:1;">🎁 Free Gift</span>
                                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#10b981"
                                                 aria-hidden="true">
                                                <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                            </svg>
                                        <?php else: ?>
                                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none"
                                                 stroke="currentColor" aria-hidden="true">
                                                <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                            </svg>
                                        <?php endif; ?>
                                    </div>

                                    <div class="item-details">
                                        <div class="item-name"><?= htmlspecialchars($planName) ?></div>
                                        <div class="item-meta" style="font-size:.875rem;color:var(--text-secondary);">
                                            <?= ucfirst($deliveryType) ?> Delivery
                                            <?php if (!empty($opts['billing_period'])): ?>
                                                &bull; <?= htmlspecialchars($opts['billing_period']) ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($item['trial_days'])): ?>
                                            <div style="display:inline-flex;align-items:center;gap:.35rem;background:#f0fdf4;border:1px solid #6ee7b7;border-radius:100px;padding:.2rem .75rem;font-size:.75rem;font-weight:600;color:#065f46;margin-top:.4rem;">
                                                🎁 <?= (int)$item['trial_days'] ?>-day free trial included
                                            </div>
                                        <?php endif; ?>
                                        <div class="item-price">
                                            <?php if ($isFreeGift): ?>
                                                <span style="color:#10b981;font-weight:700;">FREE</span>
                                                <span style="display:inline-block;background:#d1fae5;color:#065f46;font-size:.75rem;font-weight:600;padding:.2rem .6rem;border-radius:.375rem;border:1px solid #6ee7b7;margin-left:.5rem;">Complimentary</span>
                                            <?php else: ?>
                                                <span class="sale-price">
                                                    <?= htmlspecialchars($currencySymbol) ?><?= number_format((float)$item['price'], 2) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if (!empty($item['tier_label'])): ?>
                                            <div style="font-size:.75rem;color:var(--text-secondary);">
                                                <?= htmlspecialchars($item['tier_label']) ?>
                                                <?php if (!empty($item['tier_issue_count'])): ?>
                                                    &bull; <?= (int)$item['tier_issue_count'] ?> issues
                                                <?php endif; ?>
                                                <?php if (!empty($item['tier_duration_months'])): ?>
                                                    &bull; <?= (int)$item['tier_duration_months'] ?> months
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="item-actions">
                                        <div class="item-subtotal">
                                            <?= $isFreeGift
                                                    ? '<span style="color:#10b981;font-weight:700;">FREE</span>'
                                                    : htmlspecialchars($currencySymbol) . number_format((float)$item['subtotal'], 2) ?>
                                        </div>
                                        <button class="remove-btn" onclick="removeItem(<?= (int)$item['id'] ?>)"
                                                aria-label="Remove item">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                                 stroke="currentColor" aria-hidden="true">
                                                <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                            </svg>
                                        </button>
                                        <?php if (!empty($startOptions[$planId])): ?>
                                            <div class="form-group" style="margin-top:1rem;">
                                                <label class="form-label" style="font-size:.875rem;">Start Date:</label>
                                                <select id="start-date-<?= (int)$item['id'] ?>"
                                                        name="start_date"
                                                        class="form-select"
                                                        onchange="updateSubscriptionStartDate(<?= (int)$item['id'] ?>, <?= (int)$planId ?>, this.value)"
                                                        style="padding:.5rem;font-size:.875rem;">
                                                    <option value="">Select Start Date</option>
                                                    <?php foreach ($startOptions[$planId]['start_date_options'] as $startOpt): ?>
                                                        <option value="<?= htmlspecialchars($startOpt['start_date']) ?>">
                                                            <?= date('M j, Y', strtotime($startOpt['start_date'])) ?>
                                                            (Next
                                                            billing: <?= date('M j, Y', strtotime($startOpt['next_billing_date'])) ?>
                                                            )
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                <?php else: ?>

                                    <?php if ($isFreeGift): ?>
                                        <div style="position:relative;">
                                            <span style="position:absolute;top:-.5rem;left:-.5rem;background:#10b981;color:white;font-size:.7rem;font-weight:700;padding:.2rem .6rem;border-radius:1rem;text-transform:uppercase;z-index:1;">🎁 Free Gift</span>
                                            <img src="<?= htmlspecialchars($item['product_image'] ?? '/images/placeholder.jpg') ?>"
                                                 alt="<?= htmlspecialchars($item['product_name']) ?>"
                                                 class="item-image" style="border:2px solid #10b981;">
                                        </div>
                                    <?php else: ?>
                                        <img src="<?= htmlspecialchars($item['product_image'] ?? '/images/placeholder.jpg') ?>"
                                             alt="<?= htmlspecialchars($item['product_name']) ?>"
                                             class="item-image">
                                    <?php endif; ?>

                                    <div class="item-details">
                                        <a href="/shop/details/<?= htmlspecialchars($item['product_slug'] ?? '') ?>"
                                           class="item-name">
                                            <?= htmlspecialchars($item['product_name']) ?>
                                        </a>
                                        <?php if ($isFreeGift): ?>
                                            <span style="display:inline-block;background:#d1fae5;color:#065f46;font-size:.75rem;font-weight:600;padding:.2rem .6rem;border-radius:.375rem;border:1px solid #6ee7b7;margin-top:.25rem;">Complimentary — No charge</span>
                                        <?php endif; ?>
                                        <?php if (!empty($item['variant_id']) && !empty($item['variant_options'])): ?>
                                            <div style="margin-top:.5rem;">
                                                <?php foreach ($item['variant_options'] as $optName => $optVal): ?>
                                                    <span style="display:inline-block;background:var(--bg-light);color:var(--text-secondary);padding:.25rem .75rem;border-radius:1rem;font-size:.875rem;margin-right:.5rem;border:1px solid var(--border-color);">
                                                        <?= htmlspecialchars(ucfirst($optName)) ?>: <strong><?= htmlspecialchars($optVal) ?></strong>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php if (!empty($item['sku'])): ?>
                                                <div style="font-size:.75rem;color:var(--text-secondary);margin-top:.25rem;">
                                                    SKU: <?= htmlspecialchars($item['sku']) ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <div class="item-price">
                                            <?php if ($isFreeGift): ?>
                                                <span style="color:#10b981;font-weight:700;font-size:1rem;">FREE</span>
                                            <?php else: ?>
                                                <span class="sale-price">
                                                    <?= htmlspecialchars($currencySymbol) ?><?= number_format((float)$item['price'], 2) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="quantity-controls">
                                            <button class="qty-btn"
                                                    onclick="updateQuantity(<?= (int)$item['id'] ?>, <?= (int)$item['quantity'] - 1 ?>)"
                                                    aria-label="Decrease quantity">-
                                            </button>
                                            <input type="number" class="qty-input"
                                                   value="<?= (int)$item['quantity'] ?>" min="1"
                                                   onchange="updateQuantity(<?= (int)$item['id'] ?>, this.value)"
                                                   aria-label="Quantity">
                                            <button class="qty-btn"
                                                    onclick="updateQuantity(<?= (int)$item['id'] ?>, <?= (int)$item['quantity'] + 1 ?>)"
                                                    aria-label="Increase quantity">+
                                            </button>
                                        </div>
                                        <?php if (!empty($item['estimated_delivery'])): ?>
                                            <span style="font-size:.75rem;color:var(--success-color);margin-top:.25rem;">
                                                📦 Delivery: <?= htmlspecialchars($item['estimated_delivery']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="item-actions">
                                        <div class="item-subtotal">
                                            <?= $isFreeGift
                                                    ? '<span style="color:#10b981;font-weight:700;">FREE</span>'
                                                    : htmlspecialchars($currencySymbol) . number_format((float)$item['subtotal'], 2) ?>
                                        </div>
                                        <button class="remove-btn" onclick="removeItem(<?= (int)$item['id'] ?>)"
                                                aria-label="Remove item">
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                                                 stroke="currentColor" aria-hidden="true">
                                                <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                            </svg>
                                        </button>
                                    </div>

                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ── Right: order summary sidebar ────────────────────── -->
        @include('checkout/components/order-summary-sidebar', [
        'items' => $items ?? [],
        'subtotal' => $subtotal ?? 0,
        'shipping' => $shipping ?? 0,
        'tax' => $tax ?? 0,
        'finalTotal' => $finalTotal,
        'taxRate' => $tax_rate ?? 0,
        'currency' => $currency ?? '$',
        'apiBase' => $apiBase,
        'appliedVoucher' => $appliedVoucher,
        'hasPreOrders' => $hasPreOrders ?? [],
        'isSubscription' => false,
        'isCartPage' => true,
        ])
    </div><!-- /#cart-container -->

</div><!-- /.cart-page-wrapper -->
@endsection

@section('scripts')
<div id="toast" class="toast" role="status" aria-live="polite"></div>

<script>
    const API_BASE = <?= json_encode($apiBase ?? '') ?>;
    const PLAN_CURRENCY = <?= json_encode($currency ?? '$') ?>;
    const TAX_RATE = <?= json_encode((float)($tax_rate ?? 0)) ?>;
    const INITIAL_SUBTOTAL = <?= json_encode((float)($subtotal ?? 0)) ?>;
    const INITIAL_SHIPPING = <?= json_encode((float)($shipping ?? 0)) ?>;
</script>

@js('cart-utils.js')

<script>
    const SITE = <?= json_encode($site) ?>;

    let cartData = null;
    let isOneTimeSubscription = false;
    const subscriptionStartDates = {};
    window.appliedVoucher = <?= json_encode($appliedVoucher) ?>;

    // ── Subscription start-date ─────────────────────────────────────────
    async function updateSubscriptionStartDate(cartItemId, planId, startDate) {
        if (!startDate) {
            delete subscriptionStartDates[cartItemId];
            return;
        }
        subscriptionStartDates[cartItemId] = {planId, startDate};
        try {
            const res = await fetch(`${API_BASE}/cart/${cartItemId}/update-start-date`, {
                method: 'PUT',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({start_date: startDate, plan_id: planId, cart_item_id: cartItemId}),
            });
            const data = await res.json();
            data.success
                ? showToast('Start date updated')
                : showToast(data.message || 'Failed to update start date', 'error');
        } catch (err) {
            console.error(err);
            showToast('Failed to update start date', 'error');
        }
    }

    // ── Load / render ───────────────────────────────────────────────────
    async function loadCart() {
        const loading = document.getElementById('loading-container');
        const empty = document.getElementById('empty-container');
        const cartEl = document.getElementById('cart-container');

        loading.style.display = 'flex';
        empty.style.display = 'none';
        cartEl.style.display = 'none';

        try {
            const res = await fetch(`${API_BASE}/cart`);
            cartData = await res.json();

            if (!cartData.items?.length) {
                loading.style.display = 'none';
                empty.style.display = 'block';
                cartEl.style.display = 'none';
                updateCartCount(0);
                return;
            }

            renderCart();
            loading.style.display = 'none';
            cartEl.style.display = 'grid';
        } catch (err) {
            console.error(err);
            showToast('Failed to load cart', 'error');
            loading.style.display = 'none';
            empty.style.display = 'block';
            cartEl.style.display = 'none';
        }
    }

    function renderCart() {
        if (!cartData?.items?.length) {
            document.getElementById('loading-container').style.display = 'none';
            document.getElementById('empty-container').style.display = 'block';
            document.getElementById('cart-container').style.display = 'none';
            updateCartCount(0);
            return;
        }

        // ── Main items column — uses shared helper that groups by merchant ──
        renderCartItemsList(cartData.items);

        // ── Sidebar summary ────────────────────────────────────────────────
        renderOrderSummaryItems(cartData.items);

        document.getElementById('items-count').textContent = cartData.items.length;
        updateCartCount(cartData.count);
        updateTotals({subtotal: cartData.total, shipping: cartData.shipping ?? INITIAL_SHIPPING});
    }

    // ── CRUD ────────────────────────────────────────────────────────────
    async function updateQuantity(itemId, quantity) {
        quantity = Math.max(1, parseInt(quantity, 10));
        try {
            const res = await fetch(`${API_BASE}/cart/${itemId}`, {
                method: 'PUT', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({quantity}),
            });
            const data = await res.json();
            data.success
                ? (await loadCart(), showToast('Cart updated'))
                : showToast(data.message || 'Failed to update quantity', 'error');
        } catch (err) {
            console.error(err);
            showToast('Failed to update quantity', 'error');
        }
    }

    async function removeItem(itemId) {
        if (!confirm('Remove this item from cart?')) return;
        try {
            const res = await fetch(`${API_BASE}/cart/${itemId}`, {method: 'DELETE'});
            const data = await res.json();
            data.success
                ? (await loadCart(), showToast('Item removed from cart'))
                : showToast(data.message || 'Failed to remove item', 'error');
        } catch (err) {
            console.error(err);
            showToast('Failed to remove item', 'error');
        }
    }

    async function clearCart() {
        if (!confirm('Clear all items from cart?')) return;
        try {
            const res = await fetch(`${API_BASE}/cart/clear`, {method: 'DELETE'});
            const data = await res.json();
            data.success
                ? (await loadCart(), showToast('Cart cleared'))
                : showToast(data.message || 'Failed to clear cart', 'error');
        } catch (err) {
            console.error(err);
            showToast('Failed to clear cart', 'error');
        }
    }

    function proceedToCheckout() {
        if (window.appliedVoucher) sessionStorage.setItem('appliedVoucher', JSON.stringify(window.appliedVoucher));
        if (Object.keys(subscriptionStartDates).length)
            sessionStorage.setItem('subscriptionStartDates', JSON.stringify(subscriptionStartDates));
        window.location.href = isOneTimeSubscription ? '/checkout?type=subscription' : '/checkout';
    }

    function checkCartForSubscription() {
        isOneTimeSubscription = new URLSearchParams(window.location.search).get('type') === 'subscription';
    }

    async function loadWishlistCount() {
        try {
            const res = await fetch(`${API_BASE}/wishlist`);
            const data = await res.json();
            const el = document.getElementById('wishlist-count');
            if (el) el.textContent = data.data?.count || 0;
        } catch (err) { /* non-critical */
        }
    }

    // ── Init ────────────────────────────────────────────────────────────
    checkCartForSubscription();
    loadWishlistCount();

    if (window.appliedVoucher) displayAppliedVoucher();
</script>
@endsection