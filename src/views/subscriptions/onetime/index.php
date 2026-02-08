<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscribe - YourStore</title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .header {
            background: white;
            box-shadow: var(--shadow);
            padding: 1rem 0;
            margin-bottom: 3rem;
        }

        .hero {
            text-align: center;
            padding: 3rem 0;
            background: white;
            margin-bottom: 3rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
        }

        .hero h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .hero p {
            font-size: 1.25rem;
            color: var(--text-secondary);
            max-width: 600px;
            margin: 0 auto;
        }

        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 4rem;
        }

        .plan-card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: var(--shadow);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .plan-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 2px solid var(--border-color);
        }

        .plan-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .plan-description {
            color: var(--text-secondary);
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
        }

        .duration-options {
            margin-bottom: 2rem;
        }

        .duration-option {
            border: 2px solid var(--border-color);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }

        .duration-option:hover {
            border-color: var(--primary-color);
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
            margin-bottom: 0.5rem;
        }

        .duration-label {
            font-weight: 600;
            font-size: 1.1rem;
        }

        .duration-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .duration-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .duration-period {
            font-size: 0.875rem;
        }

        .savings-badge {
            background: var(--danger-color);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .original-price {
            text-decoration: line-through;
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-left: 0.5rem;
        }

        .delivery-options {
            margin-bottom: 2rem;
        }

        .delivery-section-title {
            font-weight: 600;
            margin-bottom: 1rem;
            font-size: 0.95rem;
        }

        .delivery-option {
            border: 2px solid var(--border-color);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 0.75rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .delivery-option:hover {
            border-color: var(--primary-color);
        }

        .delivery-option.selected {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.05);
        }

        .delivery-option input[type="radio"] {
            margin-right: 0.75rem;
        }

        .delivery-label {
            font-weight: 600;
            display: block;
            margin-bottom: 0.25rem;
        }

        .delivery-desc {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .features-list {
            list-style: none;
            margin-bottom: 2rem;
        }

        .features-list li {
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .features-list li:last-child {
            border-bottom: none;
        }

        .check-icon {
            color: var(--success-color);
            font-weight: 700;
        }

        .btn {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1rem;
            background: var(--primary-color);
            color: white;
        }

        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            box-shadow: var(--shadow-lg);
            display: none;
            align-items: center;
            gap: 1rem;
            z-index: 1000;
            animation: slideIn 0.3s ease-out;
        }

        .toast.show {
            display: flex;
        }

        .toast.success {
            border-left: 4px solid var(--success-color);
        }

        .toast.error {
            border-left: 4px solid var(--danger-color);
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .plans-grid {
                grid-template-columns: 1fr;
            }

            .hero h1 {
                font-size: 2rem;
            }
        }

        /* Mini Cart Styles */
        .mini-cart {
            position: fixed;
            top: 0;
            right: -400px;
            width: 400px;
            height: 100vh;
            background: white;
            box-shadow: -5px 0 15px rgba(0, 0, 0, 0.1);
            z-index: 1001;
            transition: right 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .mini-cart.open {
            right: 0;
        }

        .cart-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100vh;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: none;
        }

        .cart-overlay.show {
            display: block;
        }

        .mini-cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 2px solid var(--border-color);
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 2rem;
            cursor: pointer;
            color: var(--text-secondary);
            line-height: 1;
        }

        .cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
        }

        .cart-item {
            padding: 1rem;
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

        .cart-footer {
            padding: 1.5rem;
            border-top: 2px solid var(--border-color);
        }

        .cart-total {
            display: flex;
            justify-content: space-between;
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .btn-secondary {
            width: 100%;
            padding: 0.875rem;
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 0.5rem;
        }

        .btn-secondary:hover {
            background: var(--bg-light);
        }

        /* Search and Sort Styles */
        .controls-container {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .search-input, .sort-select {
            padding: 0.75rem;
            border: 2px solid var(--border-color);
            border-radius: 0.5rem;
            font-size: 1rem;
        }

        .search-input {
            flex: 1;
        }

        .sort-select {
            min-width: 200px;
        }

        .cart-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
<header class="header">
    <div class="container" style="display: flex; justify-content: space-between; align-items: center;">
        <h2>YourStore</h2>
        <button onclick="openMiniCart()"
                style="position: relative; background: var(--primary-color); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 0.5rem; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="9" cy="21" r="1"></circle>
                <circle cx="20" cy="21" r="1"></circle>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
            </svg>
            Cart
            <span id="header-cart-count" class="cart-badge" style="display: none;">0</span>
        </button>
    </div>
</header>
<main class="container">

    <!-- Mini Cart -->
    <div id="mini-cart" class="mini-cart">
        <div class="mini-cart-header">
            <h3>Cart (<span id="cart-count">0</span>)</h3>
            <button onclick="closeMiniCart()" class="close-btn">×</button>
        </div>
        <div id="cart-items" class="cart-items"></div>
        <div class="cart-footer">
            <div class="cart-total">
                <span>Total:</span>
                <span id="cart-total">£0.00</span>
            </div>
            <button class="btn" onclick="goToCheckout()">Proceed to Checkout</button>
            <button class="btn-secondary" onclick="closeMiniCart()">Continue Shopping</button>
        </div>
    </div>
    <div id="cart-overlay" class="cart-overlay" onclick="closeMiniCart()"></div>

    <!-- Search and Sort Controls -->
    <div class="controls-container">
        <input type="text" id="search-plans" placeholder="Search plans..." class="search-input">
        <select id="sort-plans" class="sort-select">
            <option value="">Sort by...</option>
            <option value="price-low">Price: Low to High</option>
            <option value="price-high">Price: High to Low</option>
            <option value="name">Name: A-Z</option>
        </select>
    </div>

    <div class="hero">
        <h1>Choose Your Subscription</h1>
        <p>Select the perfect plan for your needs. Digital or print delivery available.</p>
    </div>

    <div class="plans-grid">
        <?php foreach ($plans as $plan): ?>
            <div class="plan-card">
                <div class="plan-header">
                    <div class="plan-name"><?= htmlspecialchars($plan['name']) ?></div>
                    <?php if (!empty($plan['description'])): ?>
                        <div class="plan-description">
                            <?= htmlspecialchars($plan['description']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Duration Options -->
                <!-- Replace lines 347-396 with this: -->
                <div class="duration-options">
                    <?php foreach ($plan['pricing_tiers'] as $tier): ?>
                        <div class="duration-option" data-plan="<?= $plan['id'] ?>">
                            <input type="radio"
                                   name="duration_<?= $plan['id'] ?>"
                                   value="<?= $tier['duration_months'] ?>"
                                   data-price="<?= $tier['price'] ?>"
                                   data-digital="<?= $tier['digital_price'] ?>"
                                   data-issues="<?= $tier['issue_count'] ?>"
                                   data-pricing-id="<?= $tier['id'] ?>"
                                    <?= $tier['is_default'] ? 'checked' : '' ?>>
                            <div class="duration-header">
                                <span class="duration-label"><?= htmlspecialchars($tier['label']) ?></span>
                                <div>
                                    <?php if ($tier['has_discount']): ?>
                                        <span class="original-price">£<?= number_format($tier['original_price'], 2) ?></span>
                                    <?php endif; ?>
                                    <span class="duration-price">£<?= number_format($tier['price'], 2) ?></span>
                                </div>
                            </div>
                            <div class="duration-details">
                                <span class="duration-period"><?= htmlspecialchars($tier['period_description']) ?></span>
                                <?php if ($tier['savings_text']): ?>
                                    <span class="savings-badge"><?= htmlspecialchars($tier['savings_text']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Delivery Options -->
                <?php if (count($plan['delivery_options']) > 1): ?>
                    <div class="delivery-options">
                        <div class="delivery-section-title">Delivery Method</div>
                        <div class="delivery-option" data-plan="<?= $plan['id'] ?>">
                            <input type="radio" name="delivery_<?= $plan['id'] ?>" value="print" checked>
                            <div>
                                <span class="delivery-label">Print</span>
                                <span class="delivery-desc">Print magazine delivered to your door</span>
                            </div>
                        </div>
                        <div class="delivery-option" data-plan="<?= $plan['id'] ?>">
                            <input type="radio" name="delivery_<?= $plan['id'] ?>" value="digital">
                            <div>
                                <span class="delivery-label">Digital</span>
                                <span class="delivery-desc">Instant digital access</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($plan['features'])): ?>
                    <ul class="features-list">
                        <?php foreach ($plan['features'] as $feature): ?>
                            <li>
                                <span class="check-icon">✓</span>
                                <?= htmlspecialchars($feature) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <button class="btn" onclick="addToCart(<?= $plan['id'] ?>)">
                    Add to basket
                </button>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<div id="toast" class="toast"></div>

<script>
    const SITE = '<?= \App\Framework\Support\SiteContext::slug()?>'
    const API_BASE = '/api/' + SITE;
    let cartData = {items: [], total: 0, count: 0};
    let allPlans = [];

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function () {
        allPlans = Array.from(document.querySelectorAll('.plan-card'));
        loadCart();
        initializeEventListeners();
    });

    function initializeEventListeners() {
        // Duration option selection
        document.querySelectorAll('.duration-option').forEach(option => {
            option.addEventListener('click', function () {
                const planId = this.dataset.plan;
                const radio = this.querySelector('input[type="radio"]');
                document.querySelectorAll(`.duration-option[data-plan="${planId}"]`)
                    .forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                radio.checked = true;
            });
        });

        // Delivery option selection
        document.querySelectorAll('.delivery-option').forEach(option => {
            option.addEventListener('click', function () {
                const planId = this.dataset.plan;
                const radio = this.querySelector('input[type="radio"]');

                if (radio.value === 'digital') {
                    document.querySelectorAll(`.duration-option[data-plan="${planId}"]`)
                        .forEach(function (test) {
                            const radio = test.querySelector('input[type="radio"]');
                            const digitalPrice = radio.dataset.digital;
                            if (digitalPrice && digitalPrice > 0) {
                                test.querySelector('.duration-price').textContent = '£' + parseFloat(digitalPrice).toFixed(2);
                            } else {
                                test.querySelector('.duration-price').textContent = '£' + parseFloat(radio.dataset.price).toFixed(2);
                            }
                        });
                } else {
                    document.querySelectorAll(`.duration-option[data-plan="${planId}"]`)
                        .forEach(function (test) {
                            test.querySelector('.duration-price').textContent = '£' + parseFloat(test.querySelector('input[type="radio"]').dataset.price).toFixed(2);
                        });
                }

                document.querySelectorAll(`.delivery-option[data-plan="${planId}"]`)
                    .forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                radio.checked = true;
            });
        });

        // Set initial selected state
        document.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
            const parentOption = radio.closest('.duration-option') || radio.closest('.delivery-option');
            if (parentOption) {
                parentOption.classList.add('selected');
            }
        });

        // Search
        document.getElementById('search-plans').addEventListener('input', filterAndSortPlans);

        // Sort
        document.getElementById('sort-plans').addEventListener('change', filterAndSortPlans);
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

        // Update header badge
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
            <div class="cart-item-name">${item.name || 'Subscription'}</div>
            <div class="cart-item-details">
                ${item.options?.delivery_type || 'Print'} • ${item.options?.duration_months || 12} months
            </div>
            <div class="cart-item-price">£${(item.price || 0).toFixed(2)}</div>
        </div>
    `).join('');
    }


    // Handle duration option selection
    document.querySelectorAll('.duration-option').forEach(option => {
        option.addEventListener('click', function () {
            const planId = this.dataset.plan;
            const radio = this.querySelector('input[type="radio"]');

            document.querySelectorAll(`.duration-option[data-plan="${planId}"]`)
                .forEach(opt => opt.classList.remove('selected'));

            this.classList.add('selected');
            radio.checked = true;
        });
    });

    // Handle delivery option selection
    document.querySelectorAll('.delivery-option').forEach(option => {
        option.addEventListener('click', function () {
            const planId = this.dataset.plan;
            const radio = this.querySelector('input[type="radio"]');

            if (radio.value === 'digital') {
                document.querySelectorAll(`.duration-option[data-plan="${planId}"]`)
                    .forEach(function (test) {
                        const radio = test.querySelector('input[type="radio"]');
                        const digitalPrice = radio.dataset.digital

                        if (digitalPrice && digitalPrice > 0) {
                            test.querySelector('.duration-price').textContent = digitalPrice;
                        } else {
                            test.querySelector('.duration-price').textContent = radio.dataset.price;
                        }
                    });
            } else {
                document.querySelectorAll(`.duration-option[data-plan="${planId}"]`)
                    .forEach(function (test) {
                        test.querySelector('.duration-price').textContent = test.querySelector('input[type="radio"]').dataset.price;
                    });
            }

            document.querySelectorAll(`.delivery-option[data-plan="${planId}"]`)
                .forEach(opt => opt.classList.remove('selected'));

            this.classList.add('selected');
            radio.checked = true;
        });
    });

    // Set initial selected state
    document.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
        const parentOption = radio.closest('.duration-option') || radio.closest('.delivery-option');
        if (parentOption) {
            parentOption.classList.add('selected');
        }
    });

    async function addToCart(planId) {
        const durationRadio = document.querySelector(`input[name="duration_${planId}"]:checked`);
        const deliveryRadio = document.querySelector(`input[name="delivery_${planId}"]:checked`);

        const pricingId = durationRadio ? durationRadio.dataset.pricingId : null;
        const duration = durationRadio ? durationRadio.value : '12';
        const price = durationRadio ? durationRadio.dataset.price : null;
        const issues = durationRadio ? durationRadio.dataset.issues : '12';
        const deliveryType = deliveryRadio ? deliveryRadio.value : 'print';

        try {
            const response = await fetch(`${API_BASE}/cart/subscription`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    plan_id: planId,
                    pricing_id: pricingId,
                    delivery_type: deliveryType,
                    duration_months: parseInt(duration),
                    price: parseFloat(price),
                    issues: parseInt(issues)
                })
            });

            const result = await response.json();

            if (result.success) {
                cartData = result;

                console.log('cartData: ', cartData)

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

    function filterAndSortPlans() {
        const searchTerm = document.getElementById('search-plans').value.toLowerCase();
        const sortBy = document.getElementById('sort-plans').value;

        let filteredPlans = allPlans.filter(plan => {
            const name = plan.querySelector('.plan-name').textContent.toLowerCase();
            const description = plan.querySelector('.plan-description')?.textContent.toLowerCase() || '';
            return name.includes(searchTerm) || description.includes(searchTerm);
        });

        // Sort
        if (sortBy) {
            filteredPlans.sort((a, b) => {
                if (sortBy === 'price-low' || sortBy === 'price-high') {
                    const priceA = parseFloat(a.querySelector('.duration-option.selected .duration-price')?.textContent.replace('£', '') || 0);
                    const priceB = parseFloat(b.querySelector('.duration-option.selected .duration-price')?.textContent.replace('£', '') || 0);
                    return sortBy === 'price-low' ? priceA - priceB : priceB - priceA;
                } else if (sortBy === 'name') {
                    const nameA = a.querySelector('.plan-name').textContent;
                    const nameB = b.querySelector('.plan-name').textContent;
                    return nameA.localeCompare(nameB);
                }
                return 0;
            });
        }

        // Update display
        const grid = document.querySelector('.plans-grid');
        grid.innerHTML = '';
        filteredPlans.forEach(plan => grid.appendChild(plan));
        allPlans.forEach(plan => plan.style.display = 'none');
        filteredPlans.forEach(plan => plan.style.display = 'block');
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