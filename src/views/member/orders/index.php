<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - <?= htmlspecialchars($site->name) ?></title>
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
            --warning-color: #f59e0b;
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

        .orders-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .order-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            transition: all 0.3s;
        }

        .order-card:hover {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .order-number {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .order-date {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.processing {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-badge.completed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge.cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .order-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.25rem;
        }

        .detail-value {
            font-weight: 600;
            color: var(--text-primary);
        }

        .order-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
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

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
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

            .order-header {
                flex-direction: column;
                gap: 1rem;
            }

            .order-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        .cancel-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 1rem;
        }

        .cancel-modal-container {
            background: white;
            border-radius: 1rem;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            border-top: 4px solid var(--danger-color);
        }

        .cancel-modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            gap: 1rem;
            align-items: flex-start;
            background: linear-gradient(to bottom, #fef2f2 0%, white 100%);
        }

        .cancel-warning-icon {
            font-size: 2rem;
        }

        .cancel-modal-header h2 {
            margin: 0;
            font-size: 1.25rem;
            color: var(--text-primary);
        }

        .cancel-modal-header p {
            margin: 0.25rem 0 0 0;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .cancel-modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
            padding: 0.25rem 0.5rem;
        }

        .cancel-modal-body {
            padding: 1.5rem;
        }

        .cancel-form-group {
            margin-bottom: 1rem;
        }

        .cancel-form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            color: var(--text-primary);
        }

        .cancel-form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            font-size: 0.875rem;
        }

        .cancel-checkbox-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: normal !important;
            cursor: pointer;
        }

        .cancel-checkbox-label input {
            width: 18px;
            height: 18px;
        }

        .cancel-warning-box {
            padding: 1rem;
            background: #fef3c7;
            border: 1px solid #fbbf24;
            border-radius: 0.5rem;
            margin-top: 1rem;
            font-size: 0.875rem;
        }

        .cancel-warning-box strong {
            color: #92400e;
            display: block;
            margin-bottom: 0.5rem;
        }

        .cancel-warning-box ul {
            margin: 0.5rem 0 0 1.25rem;
            padding: 0;
            color: #78350f;
        }

        .cancel-modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }
    </style>
</head>
<body>

@include('member._header')

<main class="container">
    <div class="page-header">
        <div>
            <h1 class="page-title">My Orders</h1>
            <p style="color: var(--text-secondary); margin-top: 0.5rem;">
                View and track all your orders
            </p>
        </div>
        <a href="/member/dashboard" class="btn btn-secondary">
            ← Back to Dashboard
        </a>
    </div>

    <?php if ($orders->isEmpty()): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🛍️</div>
            <h3>No Orders Yet</h3>
            <p>You haven't placed any orders yet. Start shopping to see your orders here.</p>
            <a href="/" class="btn btn-primary">Start Shopping</a>
        </div>
    <?php else: ?>
    <div class="orders-list">
        <?php foreach ($orders as $order): ?>
        <div class="order-card">
            <div class="order-header">
                <div>
                    <div class="order-number">Order #<?= htmlspecialchars($order->order_number) ?></div>
                    <div class="order-date">
                        Placed on <?= $order->created_at->format('F j, Y') ?>
                    </div>
                </div>
                <span class="status-badge <?= strtolower($order->status) ?>">
                            <?= htmlspecialchars($order->status) ?>
                        </span>
            </div>

            <div class="order-details">
                <div class="detail-item">
                    <span class="detail-label">Total Amount</span>
                    <span class="detail-value"><?= htmlspecialchars($order->currency) ?> <?= number_format($order->total, 2) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Payment Status</span>
                    <span class="detail-value" style="color: <?= $order->isPaid() ? 'var(--success-color)' : 'var(--warning-color)' ?>">
                                <?= $order->isPaid() ? 'Paid' : 'Pending' ?>
                            </span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Items</span>
                    <span class="detail-value"><?= $order->items->count() ?> item(s)</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Payment Method</span>
                    <span class="detail-value"><?= htmlspecialchars($order->payment_method ?: 'N/A') ?></span>
                </div>
            </div>

            <div class="order-actions">
                <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/orders/<?= $order->id ?>"
                   class="btn btn-primary btn-sm">
                    View Details
                </a>

                    <button onclick="cancelOrder(<?= $order->id ?>)" class="btn btn-secondary btn-sm">
                        Cancel Order
                    </button>

            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

<div class="cancel-modal-overlay" style="display: none;">
    <div class="cancel-modal-container">
        <div class="cancel-modal-header">
            <div class="cancel-warning-icon">⚠️</div>
            <div>
                <h2>Cancel Order</h2>
                <p>This action will cancel the order and cannot be undone</p>
            </div>
            <button class="cancel-modal-close" onclick="this.closest('.cancel-modal-overlay').remove()">×</button>
        </div>
        <div class="cancel-modal-body">
            <div class="cancel-form-group">
                <label>Cancellation Reason *</label>
                <select id="cancelReason" class="cancel-form-control" required>
                    <option value="">Select a reason...</option>
                    <option value="customer_request">Customer Request</option>
                    <option value="out_of_stock">Out of Stock</option>
                    <option value="payment_failed">Payment Failed</option>
                    <option value="fraudulent">Fraudulent Order</option>
                    <option value="duplicate">Duplicate Order</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="cancel-form-group">
                <label class="cancel-checkbox-label">
                    <input type="checkbox" id="notifyCustomer" checked>
                    <span>Send cancellation notification to customer</span>
                </label>
            </div>
            <div class="cancel-warning-box">
                <strong>Important:</strong> Cancelling this order will:
                <ul>
                    <li>Mark the order as cancelled in the system</li>
                    <li>Release any reserved inventory</li>
                    <li>Stop any pending fulfillment processes</li>
                </ul>
            </div>
        </div>
        <div class="cancel-modal-footer">
            <input type="hidden" id="cancelOrderId" value="">
            <button class="btn btn-secondary" onclick="this.closest('.cancel-modal-overlay').style.display='none'">Keep
                Order
            </button>
            <button class="btn btn-danger" onclick="confirmCancelOrder()">Cancel Order</button>
        </div>
    </div>
</div>

<script>
    async function cancelOrder(orderId) {
        document.getElementById('cancelOrderId').value = orderId;
        document.querySelector('.cancel-modal-overlay').style.display = 'flex';
    }

    async function confirmCancelOrder() {
        const reason = document.getElementById('cancelReason').value;
        const notifyCustomer = document.getElementById('notifyCustomer').checked;
        const orderId = document.getElementById('cancelOrderId').value

        if (!reason) {
            alert('Please select a cancellation reason');
            return;
        }

        try {
            const response = await fetch(`/member/orders/${orderId}/cancel`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            const data = await response.json();

            if (data.success) {
                alert('Order cancelled successfully');
                location.reload();
            } else {
                alert(data.message || 'Failed to cancel order');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to cancel order');
        }
    }
</script>
</body>
</html>