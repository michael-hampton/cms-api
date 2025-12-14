<?php
/**
 * @var \App\Models\Member $member
 * @var \App\Models\Site $site
 * @var array $paymentMethods
 * @var string|null $defaultPaymentMethodId
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Methods - <?= htmlspecialchars($site->name) ?></title>
    <script src="https://js.stripe.com/v3/"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #667eea;
            --primary-dark: #5568d3;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --border-color: #e5e7eb;
            --bg-light: #f5f7fa;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-light);
            color: var(--text-primary);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: white;
            color: var(--text-primary);
            border: 2px solid var(--border-color);
        }

        .btn-secondary:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .alert {
            padding: 1rem 1.25rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid var(--success-color);
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid var(--danger-color);
        }

        .payment-methods-grid {
            display: grid;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .payment-method-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1.5rem;
            transition: all 0.3s;
        }

        .payment-method-card:hover {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .payment-method-card.default {
            border: 2px solid var(--primary-color);
        }

        .payment-method-info {
            flex: 1;
        }

        .payment-method-brand {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            text-transform: capitalize;
        }

        .payment-method-details {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .default-badge {
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 0.5rem;
            display: inline-block;
        }

        .payment-method-actions {
            display: flex;
            gap: 0.75rem;
        }

        .card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: var(--shadow);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .stripe-element {
            padding: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            background: white;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 1rem;
            box-shadow: var(--shadow);
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 1rem;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal {
            background: white;
            border-radius: 1rem;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .close-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
            color: var(--text-secondary);
            font-size: 1.5rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .page-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .payment-method-card {
                flex-direction: column;
                align-items: flex-start;
            }

            .payment-method-actions {
                width: 100%;
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

@include('member._header')

<main class="container">
    <div class="page-header">
        <div>
            <h1 class="page-title">Payment Methods</h1>
            <p style="color: var(--text-secondary); margin-top: 0.5rem;">
                Manage your saved payment methods
            </p>
        </div>
        <button onclick="openAddCardModal()" class="btn btn-primary">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/>
                <line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Add Payment Method
        </button>
    </div>

    <div id="alert-container"></div>

    <?php if (empty($paymentMethods)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">💳</div>
            <h3>No Payment Methods</h3>
            <p>Add a payment method to manage your subscriptions</p>
            <button onclick="openAddCardModal()" class="btn btn-primary" style="margin-top: 1rem;">
                Add Payment Method
            </button>
        </div>
    <?php else: ?>
        <div class="payment-methods-grid">
            <?php foreach ($paymentMethods as $method): ?>
                <div class="payment-method-card <?= $method['id'] === $defaultPaymentMethodId ? 'default' : '' ?>">
                    <div class="payment-method-info">
                        <div class="payment-method-brand">
                            <?= htmlspecialchars(ucfirst($method['card']['brand'])) ?>
                        </div>
                        <div class="payment-method-details">
                            •••• •••• •••• <?= htmlspecialchars($method['card']['last4']) ?>
                            <br>
                            Expires <?= htmlspecialchars($method['card']['exp_month']) ?>
                            /<?= htmlspecialchars($method['card']['exp_year']) ?>
                        </div>
                        <?php if ($method['id'] === $defaultPaymentMethodId): ?>
                            <span class="default-badge">Default</span>
                        <?php endif; ?>
                    </div>
                    <div class="payment-method-actions">
                        <?php if ($method['id'] !== $defaultPaymentMethodId): ?>
                            <button onclick="setDefaultPaymentMethod('<?= htmlspecialchars($method['id']) ?>')"
                                    class="btn btn-secondary btn-sm">
                                Set as Default
                            </button>
                        <?php endif; ?>
                        <button onclick="deletePaymentMethod('<?= htmlspecialchars($method['id']) ?>')"
                                class="btn btn-danger btn-sm">
                            Remove
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<!-- Add Card Modal -->
<div id="addCardModal" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-title">Add Payment Method</h2>
            <button onclick="closeAddCardModal()" class="close-btn">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>

        <form id="payment-form">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Card Information</label>
                    <div id="card-element" class="stripe-element"></div>
                    <div id="card-errors"
                         style="color: var(--danger-color); margin-top: 0.5rem; font-size: 0.875rem;"></div>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" id="set-default" checked>
                        <span>Set as default payment method</span>
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" onclick="closeAddCardModal()" class="btn btn-secondary">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary" id="submit-btn">
                    Add Payment Method
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const SITE = '<?= $site->slug ?? 'default' ?>';
    const stripe = Stripe('<?= $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? '' ?>');
    const elements = stripe.elements();
    const cardElement = elements.create('card', {
        style: {
            base: {
                fontSize: '16px',
                color: '#1f2937',
                '::placeholder': {
                    color: '#9ca3af',
                },
            },
        },
    });

    document.addEventListener('DOMContentLoaded', function () {
        cardElement.mount('#card-element');

        cardElement.on('change', function (event) {
            const displayError = document.getElementById('card-errors');
            if (event.error) {
                displayError.textContent = event.error.message;
            } else {
                displayError.textContent = '';
            }
        });

        const form = document.getElementById('payment-form');
        form.addEventListener('submit', async function (event) {
            event.preventDefault();

            const submitBtn = document.getElementById('submit-btn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing...';

            const {paymentMethod, error} = await stripe.createPaymentMethod({
                type: 'card',
                card: cardElement,
            });

            if (error) {
                document.getElementById('card-errors').textContent = error.message;
                submitBtn.disabled = false;
                submitBtn.textContent = 'Add Payment Method';
            } else {
                await handlePaymentMethod(paymentMethod.id);
                submitBtn.disabled = false;
                submitBtn.textContent = 'Add Payment Method';
            }
        });
    });

    function openAddCardModal() {
        document.getElementById('addCardModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeAddCardModal() {
        document.getElementById('addCardModal').classList.remove('show');
        document.body.style.overflow = 'auto';
        cardElement.clear();
        document.getElementById('card-errors').textContent = '';
    }

    async function handlePaymentMethod(paymentMethodId) {
        const setDefault = document.getElementById('set-default').checked;

        try {
            const response = await fetch(`/${SITE}/member/payment-methods`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    payment_method_id: paymentMethodId,
                    set_default: setDefault
                })
            });

            const data = await response.json();

            if (data.success) {
                showAlert('Payment method added successfully', 'success');
                closeAddCardModal();
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message || 'Failed to add payment method', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('Failed to add payment method', 'error');
        }
    }

    async function setDefaultPaymentMethod(paymentMethodId) {
        try {
            const response = await fetch(`/${SITE}/member/payment-methods/${paymentMethodId}/set-default`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            const data = await response.json();

            if (data.success) {
                showAlert('Default payment method updated', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message || 'Failed to update default payment method', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('Failed to update default payment method', 'error');
        }
    }

    async function deletePaymentMethod(paymentMethodId) {
        if (!confirm('Are you sure you want to remove this payment method?')) {
            return;
        }

        try {
            const response = await fetch(`/${SITE}/member/payment-methods/${paymentMethodId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            const data = await response.json();

            if (data.success) {
                showAlert('Payment method removed successfully', 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showAlert(data.message || 'Failed to remove payment method', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('Failed to remove payment method', 'error');
        }
    }

    function showAlert(message, type = 'success') {
        const alertContainer = document.getElementById('alert-container');
        const alertClass = type === 'success' ? 'alert-success' : 'alert-error';

        alertContainer.innerHTML = `
            <div class="alert ${alertClass}">
                <span>${type === 'success' ? '✓' : '✕'}</span>
                ${escapeHtml(message)}
            </div>
        `;

        setTimeout(() => {
            alertContainer.innerHTML = '';
        }, 5000);

        window.scrollTo({top: 0, behavior: 'smooth'});
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
</script>

</body>
</html>