<?php
// Near the top after the plan is loaded, add this to calculate effective prices
foreach ($plan->pricingTiers as $tier) {
    $tier->effective_print_price = $tier->sale_price ?? $tier->price;
    $tier->effective_digital_price = $tier->digital_sale_price ?? $tier->digital_price ?? $tier->sale_price ?? $tier->price;
    $tier->has_print_sale = $tier->sale_price && $tier->sale_price < $tier->price;
    $tier->has_digital_sale = $tier->digital_sale_price && $tier->digital_sale_price < ($tier->digital_price ?? $tier->price);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($plan->name) ?> - Subscribe</title>
    <script src="https://js.stripe.com/v3/"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #2563eb;
            --primary-dark: #1e40af;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --border-color: #e2e8f0;
            --bg-light: #f8fafc;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: var(--text-primary);
            background-color: var(--bg-light);
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem 20px;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .breadcrumb a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .plan-header {
            background: white;
            padding: 3rem 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .plan-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .plan-description {
            font-size: 1.125rem;
            color: var(--text-secondary);
            line-height: 1.8;
        }

        .plan-card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }

        /* Duration Options */
        .duration-options {
            margin-bottom: 2rem;
        }

        .duration-option {
            border: 2px solid var(--border-color);
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }

        .duration-option:hover {
            border-color: var(--primary-color);
            transform: translateX(5px);
        }

        .duration-option.selected {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.05);
        }

        .duration-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .duration-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }

        .duration-label {
            font-weight: 600;
            font-size: 1.125rem;
            color: var(--text-primary);
        }

        .duration-price {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .duration-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.95rem;
            color: var(--text-secondary);
            flex-wrap: wrap;
            gap: 1rem;
        }

        .duration-period {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .savings-badge {
            background: var(--danger-color);
            color: white;
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .original-price {
            text-decoration: line-through;
            color: var(--text-secondary);
            font-size: 1rem;
            margin-left: 0.5rem;
        }

        .price-per-issue {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        /* Delivery Options */
        .delivery-options {
            margin-bottom: 2rem;
        }

        .delivery-option {
            border: 2px solid var(--border-color);
            border-radius: 0.75rem;
            padding: 1.25rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: start;
            gap: 1rem;
        }

        .delivery-option:hover {
            border-color: var(--primary-color);
        }

        .delivery-option.selected {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.05);
        }

        .delivery-option input[type="radio"] {
            margin-top: 0.25rem;
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .delivery-content {
            flex: 1;
        }

        .delivery-label {
            font-weight: 600;
            font-size: 1.125rem;
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .delivery-desc {
            font-size: 0.95rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* Features List */
        .features-list {
            list-style: none;
            margin-bottom: 2rem;
        }

        .features-list li {
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .features-list li:last-child {
            border-bottom: none;
        }

        .check-icon {
            width: 24px;
            height: 24px;
            background: var(--success-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .check-icon svg {
            width: 14px;
            height: 14px;
            stroke: white;
            stroke-width: 3;
        }

        /* Buttons */
        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            border: none;
            border-radius: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
            font-size: 1.125rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            width: 100%;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .btn-secondary:hover {
            background: var(--bg-light);
        }

        /* Cart Badge */
        .cart-badge {
            position: fixed;
            top: 2rem;
            right: 2rem;
            background: white;
            border-radius: 1rem;
            padding: 1rem 1.5rem;
            box-shadow: var(--shadow-lg);
            cursor: pointer;
            transition: all 0.3s;
            z-index: 1000;
        }

        .cart-badge:hover {
            transform: scale(1.05);
        }

        .cart-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .cart-icon {
            position: relative;
        }

        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .cart-total {
            font-weight: 700;
            color: var(--primary-color);
            font-size: 1.125rem;
        }

        /* Mini Cart */
        .mini-cart {
            position: fixed;
            top: 0;
            right: -400px;
            width: 400px;
            height: 100vh;
            background: white;
            box-shadow: var(--shadow-lg);
            transition: right 0.3s;
            z-index: 1001;
            display: flex;
            flex-direction: column;
        }

        .mini-cart.open {
            right: 0;
        }

        .mini-cart-header {
            padding: 1.5rem;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .mini-cart-header h3 {
            font-size: 1.25rem;
        }

        .close-cart {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
        }

        .mini-cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
        }

        .cart-item {
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .cart-item-name {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .cart-item-details {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .cart-item-price {
            font-weight: 600;
            color: var(--primary-color);
        }

        .mini-cart-footer {
            padding: 1.5rem;
            border-top: 2px solid var(--border-color);
        }

        .cart-total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            font-size: 1.125rem;
            font-weight: 700;
        }

        .cart-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            z-index: 1000;
        }

        .cart-overlay.show {
            display: block;
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 0.75rem;
            box-shadow: var(--shadow-lg);
            display: none;
            align-items: center;
            gap: 1rem;
            z-index: 10000;
        }

        .toast.show {
            display: flex;
            animation: slideIn 0.3s ease-out;
        }

        .toast.success {
            border-left: 4px solid var(--success-color);
        }

        .toast.error {
            border-left: 4px solid var(--danger-color);
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .plan-title {
                font-size: 2rem;
            }

            .mini-cart {
                width: 100%;
                right: -100%;
            }

            .cart-badge {
                bottom: 2rem;
                top: auto;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="/subscriptions">← Back to Shop</a>
    </div>

    <!-- Plan Header -->
    <div class="plan-header">
        <h1 class="plan-title"><?= htmlspecialchars($plan->name) ?></h1>
        <?php if ($plan->description): ?>
            <p class="plan-description"><?= htmlspecialchars($plan->description) ?></p>
        <?php endif; ?>
    </div>

    <!-- Duration Selection -->
    <div class="plan-card">
        <h2 class="section-title">Choose Your Subscription</h2>

        <div class="duration-options">
            <?php foreach ($plan->pricingTiers as $index => $pricing):
                $actualPrice = $pricing->sale_price && $pricing->sale_price < $pricing->price ? $pricing->sale_price : $pricing->price;
                $originalPrice = $pricing->price;
                ?>
                <div class="duration-option" data-plan="<?= $plan->id ?>">
                    <input
                            type="radio"
                            name="duration_<?= $plan->id ?>"
                            value="<?= $pricing->duration_months ?>"
                            data-pricing-id="<?= $pricing->id ?>"
                            data-price="<?= $pricing->price ?>"
                            data-digital="<?= $pricing->digital_price ?? 0 ?>"
                            data-original-price="<?= $pricing->sale_price ?? $pricing->price ?>"
                            data-original-digital="<?= $pricing->digital_sale_price ?? $pricing->digitial_price ?>"
                            data-issues="<?= $pricing->issue_count ?>"
                            <?= $index === 0 ? 'checked' : '' ?>>

                    <div class="duration-header">
                        <span class="duration-label"><?= htmlspecialchars($pricing->label) ?></span>
                        <div>
                            <span class="duration-price">£<?= number_format($actualPrice, 2) ?></span>
                            <?php if ($pricing->hasDiscount()): ?>
                                <span class="original-price">£<?= number_format($originalPrice, 2) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="duration-details">
                        <span class="duration-period">
                            <?= htmlspecialchars($pricing->period_description) ?>
                        </span>

                        <?php if ($pricing->issue_count > 0): ?>
                            <span class="price-per-issue">
                                £<?= number_format($pricing->getPricePerIssue(), 2) ?> per issue
                            </span>
                        <?php endif; ?>

                        <?php if ($pricing->getSavingsText()): ?>
                            <span class="savings-badge"><?= $pricing->getSavingsText() ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Delivery Type Selection -->
        <?php
        $deliveryOptions = $plan->getDeliveryOptions();
        $hasMultipleOptions = count($deliveryOptions) > 1;
        ?>

        <?php if ($hasMultipleOptions): ?>
            <h2 class="section-title">Delivery Type</h2>
            <div class="delivery-options">
                <?php if ($plan->hasDigitalOption()): ?>
                    <div class="delivery-option" data-plan="<?= $plan->id ?>">
                        <input
                                type="radio"
                                name="delivery_<?= $plan->id ?>"
                                value="digital"
                                checked>
                        <div class="delivery-content">
                            <span class="delivery-label">Digital Edition</span>
                            <p class="delivery-desc">
                                Instant access to digital content. Download and read on any device.
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($plan->hasPrintOption()): ?>
                    <div class="delivery-option" data-plan="<?= $plan->id ?>">
                        <input
                                type="radio"
                                name="delivery_<?= $plan->id ?>"
                                value="print"
                                <?= !$plan->hasDigitalOption() ? 'checked' : '' ?>>
                        <div class="delivery-content">
                            <span class="delivery-label">Print Edition</span>
                            <p class="delivery-desc">
                                Physical magazine delivered to your doorstep. Shipping included.
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Hidden input for single delivery option -->
            <input
                    type="radio"
                    name="delivery_<?= $plan->id ?>"
                    value="<?= $deliveryOptions[0] ?>"
                    checked
                    style="display: none;">
        <?php endif; ?>

        <!-- Add to Cart Button -->
        <button class="btn btn-primary" onclick="addToCart(<?= $plan->id ?>)">
            Add to Cart
        </button>
    </div>

    <!-- Features -->
    <?php if (!empty($plan->features)): ?>
        <div class="plan-card">
            <h2 class="section-title">What's Included</h2>
            <ul class="features-list">
                <?php foreach ($plan->features as $feature): ?>
                    <li>
                        <div class="check-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                        </div>
                        <span><?= htmlspecialchars($feature) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

<!-- Cart Badge -->
<div class="cart-badge" onclick="openMiniCart()">
    <div class="cart-info">
        <div class="cart-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="9" cy="21" r="1"></circle>
                <circle cx="20" cy="21" r="1"></circle>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
            </svg>
            <span class="cart-count" id="header-cart-count" style="display: none;">0</span>
        </div>
        <span class="cart-total" id="cart-total">£0.00</span>
    </div>
</div>

<!-- Mini Cart -->
<div class="mini-cart" id="mini-cart">
    <div class="mini-cart-header">
        <h3>Your Cart (<span id="cart-count">0</span>)</h3>
        <button class="close-cart" onclick="closeMiniCart()">×</button>
    </div>

    <div class="mini-cart-items" id="cart-items">
        <p style="text-align: center; color: var(--text-secondary); padding: 2rem;">
            Your cart is empty
        </p>
    </div>

    <div class="mini-cart-footer">
        <div class="cart-total-row">
            <span>Total:</span>
            <span id="mini-cart-total">£0.00</span>
        </div>
        <button class="btn btn-primary" onclick="goToCheckout()">
            Proceed to Checkout
        </button>
    </div>
</div>

<!-- Cart Overlay -->
<div class="cart-overlay" id="cart-overlay" onclick="closeMiniCart()"></div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script>
    const API_BASE = '/api/<?= \App\Framework\Support\SiteContext::slug() ?? 'default' ?>';
    const SITE = '<?= \App\Framework\Support\SiteContext::slug() ?? 'default' ?>';
    let cartData = {items: [], total: 0, count: 0};

    // Initialize
    document.addEventListener('DOMContentLoaded', function () {
        loadCart();
        initializeSelections();
    });

    function initializeSelections() {
        const planId = <?= $plan->id ?>;

        // Handle duration selection
        document.querySelectorAll('.duration-option').forEach(option => {
            option.addEventListener('click', function () {
                const radio = this.querySelector('input[type="radio"]');

                document.querySelectorAll(`.duration-option[data-plan="${planId}"]`)
                    .forEach(opt => opt.classList.remove('selected'));

                this.classList.add('selected');
                radio.checked = true;

                // Update pricing if digital is selected
                updatePricingDisplay();
            });
        });

        // Handle delivery selection
        document.querySelectorAll('.delivery-option').forEach(option => {
            option.addEventListener('click', function () {
                const radio = this.querySelector('input[type="radio"]');

                document.querySelectorAll(`.delivery-option[data-plan="${planId}"]`)
                    .forEach(opt => opt.classList.remove('selected'));

                this.classList.add('selected');
                radio.checked = true;

                // Update pricing display based on delivery type
                updatePricingDisplay();
            });
        });

        // Set initial selected state
        document.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
            const parentOption = radio.closest('.duration-option') || radio.closest('.delivery-option');
            if (parentOption) {
                parentOption.classList.add('selected');
            }
        });
    }

    function updatePricingDisplay() {
        const planId = <?= $plan->id ?>;
        const deliveryRadio = document.querySelector(`input[name="delivery_${planId}"]:checked`);
        const isDigital = deliveryRadio && deliveryRadio.value === 'digital';

        document.querySelectorAll(`.duration-option[data-plan="${planId}"]`).forEach(option => {
            const radio = option.querySelector('input[type="radio"]');
            const priceElement = option.querySelector('.duration-price');
            const originalPriceElement = option.querySelector('.original-price');
            const digitalPrice = parseFloat(radio.dataset.digital);
            const printPrice = parseFloat(radio.dataset.price);
            const digitalSalePrice = parseFloat(radio.dataset.originalDigital);
            const originalSalePrice = parseFloat(radio.dataset.originalPrice);

            if (isDigital && digitalPrice > 0) {
                priceElement.textContent = '£' + digitalSalePrice.toFixed(2);
                // Update original price if it exists
                if (digitalSalePrice < digitalPrice) {
                    originalPriceElement.textContent = '£' + digitalPrice.toFixed(2);
                }
            } else {
                priceElement.textContent = '£' + originalSalePrice.toFixed(2);
                // Restore original price display
                const originalPrint = parseFloat(radio.dataset.originalPrice || printPrice);
                if (originalPrint < printPrice) {
                    originalPriceElement.textContent = '£' + printPrice.toFixed(2);
                }
            }
        });
    }

    async function loadCart() {
        try {
            const response = await fetch(`${API_BASE}/cart`);
            const result = await response.json();
            cartData = result;
            updateCartDisplay();
        } catch (error) {
            console.error('Error loading cart:', error);
        }
    }

    function updateCartDisplay() {
        const count = cartData.count || 0;
        document.getElementById('cart-count').textContent = count;
        document.getElementById('cart-total').textContent = '£' + (cartData.total || 0).toFixed(2);
        document.getElementById('mini-cart-total').textContent = '£' + (cartData.total || 0).toFixed(2);

        const headerBadge = document.getElementById('header-cart-count');
        if (count > 0) {
            headerBadge.textContent = count;
            headerBadge.style.display = 'flex';
        } else {
            headerBadge.style.display = 'none';
        }

        const cartItemsContainer = document.getElementById('cart-items');
        if (!cartData.items || cartData.items.length === 0) {
            cartItemsContainer.innerHTML = '<p style="text-align: center; color: var(--text-secondary); padding: 2rem;">Your cart is empty</p>';
            return;
        }

        cartItemsContainer.innerHTML = cartData.items.map(item => `
            <div class="cart-item">
                <div class="cart-item-name">${item.product_name || item.options?.plan_name || 'Subscription'}</div>
                <div class="cart-item-details">
                    ${item.options?.delivery_type || 'Print'} • ${item.options?.duration_months || 12} months
                </div>
                <div class="cart-item-price">£${(item.price || 0).toFixed(2)}</div>
            </div>
        `).join('');
    }

    async function addToCart(planId) {
        const durationRadio = document.querySelector(`input[name="duration_${planId}"]:checked`);
        const deliveryRadio = document.querySelector(`input[name="delivery_${planId}"]:checked`);

        if (!durationRadio) {
            showToast('Please select a subscription duration', 'error');
            return;
        }

        if (!deliveryRadio) {
            showToast('Please select a delivery type', 'error');
            return;
        }

        const pricingId = durationRadio.dataset.pricingId;
        const duration = durationRadio.value;
        const deliveryType = deliveryRadio.value;
        const digitalPrice = parseFloat(durationRadio.dataset.digital);
        const printPrice = parseFloat(durationRadio.dataset.price);
        const price = (deliveryType === 'digital' && digitalPrice > 0) ? digitalPrice : printPrice;
        const issues = durationRadio.dataset.issues;

        try {
            const response = await fetch(`${API_BASE}/cart/subscription`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    plan_id: planId,
                    pricing_id: parseInt(pricingId),
                    delivery_type: deliveryType,
                    duration_months: parseInt(duration),
                    price: parseFloat(price),
                    issues: parseInt(issues)
                })
            });

            const result = await response.json();

            if (result.success) {
                cartData = result;
                updateCartDisplay();
                openMiniCart();
                showToast('Added to cart!', 'success');
            } else {
                showToast(result.message || 'Failed to add to cart', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('An error occurred', 'error');
        }
    }

    function openMiniCart() {
        document.getElementById('mini-cart').classList.add('open');
        document.getElementById('cart-overlay').classList.add('show');
    }

    function closeMiniCart() {
        document.getElementById('mini-cart').classList.remove('open');
        document.getElementById('cart-overlay').classList.remove('show');
    }

    function goToCheckout() {
        window.location.href = '/' + SITE + '/checkout?type=subscription';
    }

    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.className = `toast ${type} show`;
        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }
</script>
</body>
</html>