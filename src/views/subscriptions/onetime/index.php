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
            margin-bottom: 0.5rem;
        }

        .plan-price {
            font-size: 3rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .plan-period {
            color: var(--text-secondary);
            font-size: 1rem;
        }

        .delivery-options {
            margin-bottom: 2rem;
        }

        .delivery-option {
            border: 2px solid var(--border-color);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
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
            border-left: 4px solid #ef4444;
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

            .plan-price {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>
<header class="header">
    <div class="container">
        <h2>YourStore</h2>
    </div>
</header>
<main class="container">
    <div class="hero">
        <h1>Choose Your Subscription</h1>
        <p>Select the perfect plan for your needs. Digital or print delivery available.</p>
    </div>

    <div class="plans-grid">
        <?php foreach ($plans as $plan): ?>
            <div class="plan-card">
                <div class="plan-header">
                    <div class="plan-name"><?= htmlspecialchars($plan['name']) ?></div>
                    <div class="plan-price">
                        $<?= number_format($plan['price'], 2) ?>
                    </div>
                    <div class="plan-period">
                        <?= htmlspecialchars($plan['billing_period']) ?>
                    </div>
                </div>

                <?php if (!empty($plan['description'])): ?>
                    <p style="margin-bottom: 1.5rem; color: var(--text-secondary);">
                        <?= htmlspecialchars($plan['description']) ?>
                    </p>
                <?php endif; ?>

                <?php if (count($plan['delivery_options']) > 1): ?>
                    <div class="delivery-options">
                        <label class="delivery-option" data-plan="<?= $plan['id'] ?>">
                            <input type="radio" name="delivery_<?= $plan['id'] ?>" value="digital" checked>
                            <div>
                                <span class="delivery-label">Digital Access</span>
                                <span class="delivery-desc">Instant download link after payment</span>
                            </div>
                        </label>
                        <label class="delivery-option" data-plan="<?= $plan['id'] ?>">
                            <input type="radio" name="delivery_<?= $plan['id'] ?>" value="print">
                            <div>
                                <span class="delivery-label">Print Edition</span>
                                <span class="delivery-desc">Shipped to your address</span>
                            </div>
                        </label>
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
                    Add to Cart
                </button>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<div id="toast" class="toast"></div>

<script>
    const SITE = 'test-mike';
    const API_BASE = '/api/' + SITE;

    // Handle delivery option selection
    document.querySelectorAll('.delivery-option').forEach(option => {
        option.addEventListener('click', function () {
            const planId = this.dataset.plan;
            const radio = this.querySelector('input[type="radio"]');

            document.querySelectorAll(`.delivery-option[data-plan="${planId}"]`)
                .forEach(opt => opt.classList.remove('selected'));

            this.classList.add('selected');
            radio.checked = true;
        });
    });

    // Set initial selected state
    document.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
        radio.closest('.delivery-option').classList.add('selected');
    });

    async function addToCart(planId) {
        const deliveryRadio = document.querySelector(`input[name="delivery_${planId}"]:checked`);
        const deliveryType = deliveryRadio ? deliveryRadio.value : 'digital';

        try {
            const response = await fetch(`${API_BASE}/cart/subscription`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    plan_id: planId,
                    delivery_type: deliveryType
                })
            });

            const result = await response.json();

            if (result.success) {
                showToast('Added to cart! Redirecting to checkout...', 'success');
                setTimeout(() => {
                    window.location.href = '/checkout';
                }, 1500);
            } else {
                showToast(result.message || 'Failed to add to cart', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showToast('An error occurred', 'error');
        }
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