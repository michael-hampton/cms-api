<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Access - <?= htmlspecialchars($content->title ?? 'Content') ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }

        .content-preview {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .pricing-card {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 30px;
            margin: 20px 0;
        }

        .price {
            font-size: 48px;
            font-weight: bold;
            color: #333;
            margin: 20px 0;
        }

        .duration {
            color: #666;
            font-size: 18px;
        }

        .btn {
            display: inline-block;
            padding: 15px 40px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            font-size: 18px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn:hover {
            background: #0056b3;
        }

        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .features {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }

        .features li {
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .features li:before {
            content: "✓ ";
            color: #28a745;
            font-weight: bold;
            margin-right: 10px;
        }

        #payment-message {
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
            display: none;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
<h1>Purchase Access: <?= htmlspecialchars($content->title ?? 'Content') ?></h1>

<div class="content-preview">
    <h2>Content Preview</h2>
    <p><strong>Type:</strong> <?= htmlspecialchars(ucfirst($content_type)) ?></p>
    <?php if (isset($content->excerpt)): ?>
        <p><?= htmlspecialchars($content->excerpt) ?></p>
    <?php endif; ?>
</div>

<div class="pricing-card">
    <h2>Get Full Access</h2>
    <div class="price">
        <?= htmlspecialchars($pricing['currency']) ?>
        <?= number_format($pricing['price'], 2) ?>
    </div>
    <div class="duration">
        Valid for <?= $pricing['duration_days'] ?> days
    </div>

    <ul class="features">
        <li>Full access to this <?= htmlspecialchars($content_type) ?></li>
        <li>Available for <?= $pricing['duration_days'] ?> days</li>
        <li>Instant access after payment</li>
        <li>No recurring charges</li>
    </ul>

    <form id="purchase-form" method="POST" action="/<?= htmlspecialchars($site->slug) ?>/member/single-access/purchase">
        <input type="hidden" name="content_type" value="<?= htmlspecialchars($content_type) ?>">
        <input type="hidden" name="content_id" value="<?= htmlspecialchars($content_id) ?>">

        <button type="submit" class="btn" id="purchase-btn">
            Purchase Access
        </button>
    </form>

    <div id="payment-message"></div>
</div>

<script>
    document.getElementById('purchase-form').addEventListener('submit', async (e) => {
        e.preventDefault();

        const btn = document.getElementById('purchase-btn');
        const messageDiv = document.getElementById('payment-message');

        btn.disabled = true;
        btn.textContent = 'Processing...';
        messageDiv.style.display = 'none';

        try {
            const formData = new FormData(e.target);
            const response = await fetch(e.target.action, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                // Here you would integrate with Stripe or your payment processor
                // For now, show success message
                messageDiv.className = 'success';
                messageDiv.textContent = 'Payment intent created! Redirecting to payment...';
                messageDiv.style.display = 'block';

                // In production, redirect to Stripe checkout or show payment form
                // window.location.href = data.checkout_url;
            } else {
                messageDiv.className = 'error';
                messageDiv.textContent = data.message || 'Payment failed. Please try again.';
                messageDiv.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Purchase Access';
            }
        } catch (error) {
            messageDiv.className = 'error';
            messageDiv.textContent = 'An error occurred. Please try again.';
            messageDiv.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Purchase Access';
        }
    });
</script>
</body>
</html>