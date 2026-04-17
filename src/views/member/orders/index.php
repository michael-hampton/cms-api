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

        /* Toast */
        .toast-container {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            pointer-events: none;
        }

        .toast {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.25rem;
            border-radius: 0.75rem;
            font-size: 0.9375rem;
            font-weight: 500;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, .1);
            pointer-events: all;
            animation: slideIn 0.3s ease;
            max-width: 360px;
        }

        .toast.success {
            background: #ecfdf5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .toast.error {
            background: #fef2f2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .toast.info {
            background: #eff6ff;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }

        .toast-close {
            margin-left: auto;
            background: none;
            border: none;
            cursor: pointer;
            color: inherit;
            opacity: 0.6;
            font-size: 1.1rem;
        }

        .toast-close:hover {
            opacity: 1;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideOut {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }

        /* Layout */
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

        /* Buttons */
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

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        /* Orders list */
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
            box-shadow: 0 4px 6px rgba(0, 0, 0, .1);
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

        /* Empty state */
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

        /* Skeleton */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: shimmer 1.4s infinite;
            border-radius: 0.5rem;
        }

        @keyframes shimmer {
            0% {
                background-position: 200% 0;
            }
            100% {
                background-position: -200% 0;
            }
        }

        /* Cancel modal */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 1rem;
        }

        .modal-box {
            background: white;
            border-radius: 1rem;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, .1);
            border-top: 4px solid var(--danger-color);
            animation: modalIn 0.25s ease;
        }

        @keyframes modalIn {
            from {
                opacity: 0;
                transform: scale(.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            gap: 1rem;
            align-items: flex-start;
            background: linear-gradient(to bottom, #fef2f2, white);
            border-radius: 1rem 1rem 0 0;
        }

        .modal-header-icon {
            font-size: 2rem;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.25rem;
            color: var(--text-primary);
        }

        .modal-header p {
            margin: 0.25rem 0 0;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-family: inherit;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            font-weight: normal;
        }

        .checkbox-label input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .warning-box {
            padding: 1rem;
            background: #fffbeb;
            border: 1px solid #fbbf24;
            border-radius: 0.5rem;
            margin-top: 1rem;
            font-size: 0.875rem;
        }

        .warning-box strong {
            color: #92400e;
            display: block;
            margin-bottom: 0.5rem;
        }

        .warning-box ul {
            margin: 0.5rem 0 0 1.25rem;
            padding: 0;
            color: #78350f;
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
    </style>
</head>
<body>

@include('member._header')

<div class="toast-container" id="toastContainer"></div>

<main class="container">
    <div class="page-header">
        <div>
            <h1 class="page-title">My Orders</h1>
            <p style="color:var(--text-secondary);margin-top:0.5rem;">View and track all your orders</p>
        </div>
        <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/dashboard" class="btn btn-secondary">
            ← Back to Dashboard
        </a>
    </div>

    <div class="orders-list" id="orders-container">
        <!-- Skeleton loaders -->
        <?php for ($i = 0; $i < 3; $i++): ?>
            <div class="order-card">
                <div class="skeleton" style="height:1.25rem;width:40%;margin-bottom:0.75rem;"></div>
                <div class="skeleton" style="height:1rem;width:25%;margin-bottom:1rem;"></div>
                <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;">
                    <?php for ($j = 0; $j < 4; $j++): ?>
                        <div class="skeleton" style="height:2.5rem;"></div>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endfor; ?>
    </div>
</main>

<!-- Cancel modal -->
<div class="modal-backdrop" id="cancelModalBackdrop" style="display:none;" onclick="handleBackdropClick(event)">
    <div class="modal-box" id="cancelModalBox">
        <div class="modal-header">
            <div class="modal-header-icon">⚠️</div>
            <div>
                <h2>Cancel Order</h2>
                <p>This action will cancel the order and cannot be undone</p>
            </div>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Cancellation Reason *</label>
                <select id="cancelReason" class="form-control">
                    <option value="">Select a reason…</option>
                    <option value="customer_request">Customer Request</option>
                    <option value="out_of_stock">Out of Stock</option>
                    <option value="payment_failed">Payment Failed</option>
                    <option value="fraudulent">Fraudulent Order</option>
                    <option value="duplicate">Duplicate Order</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="notifyCustomer" checked>
                    <span>Send cancellation notification to customer</span>
                </label>
            </div>
            <div class="warning-box">
                <strong>Important: Cancelling this order will:</strong>
                <ul>
                    <li>Mark the order as cancelled in the system</li>
                    <li>Release any reserved inventory</li>
                    <li>Stop any pending fulfillment processes</li>
                </ul>
            </div>
        </div>
        <div class="modal-footer">
            <input type="hidden" id="cancelOrderId">
            <button class="btn btn-secondary" onclick="closeCancelModal()">Keep Order</button>
            <button class="btn btn-danger" id="confirmCancelBtn" onclick="confirmCancelOrder()">Cancel Order</button>
        </div>
    </div>
</div>

<script>

    class CancelModal {
        constructor() {
            this.orderId = null;
            this._bind();
        }

        _bind() {
            document.getElementById('cancelModalBackdrop').addEventListener('click', e => {
                if (e.target.id === 'cancelModalBackdrop') this.close();
            });
        }

        open(orderId) {
            this.orderId = orderId;
            document.getElementById('cancelReason').value = '';
            document.getElementById('notifyCustomer').checked = true;
            document.getElementById('confirmCancelBtn').disabled = false;
            document.getElementById('confirmCancelBtn').textContent = 'Cancel Order';
            document.getElementById('cancelModalBackdrop').style.display = 'flex';
        }

        close() {
            document.getElementById('cancelModalBackdrop').style.display = 'none';
        }

        async confirm() {
            const reason = document.getElementById('cancelReason').value;
            const notify = document.getElementById('notifyCustomer').checked;
            const btn = document.getElementById('confirmCancelBtn');
            if (!reason) {
                UI.toast('Please select a cancellation reason.', 'error');
                return;
            }
            btn.disabled = true;
            btn.textContent = 'Cancelling…';
            try {
                await api(`/api/${SITE_SLUG}/member/orders/${this.orderId}/cancel`, {
                    method: 'POST',
                    body: JSON.stringify({reason, notify_customer: notify}),
                });
                this.close();
                UI.toast('Order cancelled successfully.', 'success');
                setTimeout(() => ordersList.load(), 800);
            } catch (e) {
                UI.toast(e.message || 'Failed to cancel order.', 'error');
                btn.disabled = false;
                btn.textContent = 'Cancel Order';
            }
        }
    }

    class OrdersList {
        constructor(modal) {
            this.modal = modal;
        }

        async load() {
            const container = document.getElementById('orders-container');
            try {
                const json = await api(`/api/${SITE_SLUG}/member/orders`);
                this.render(container, json.data);
            } catch (e) {
                UI.toast('Failed to load orders. Please refresh.', 'error');
                UI.render(container, [UI.emptyState({
                    icon: '⚠️', title: 'Failed to Load Orders',
                    body: 'Please try refreshing the page.',
                    action: UI.el('button', {className: 'btn btn-primary', onclick: () => this.load()}, ['Retry']),
                })]);
            }
        }

        render(container, orders) {
            if (!orders?.length) {
                UI.render(container, [UI.emptyState({
                    icon: '🛍️', title: 'No Orders Yet',
                    body: "You haven't placed any orders yet.",
                    action: UI.el('a', {href: '/', className: 'btn btn-primary'}, ['Start Shopping']),
                })]);
                return;
            }

            UI.render(container, orders.map(order => {
                const date = new Date(order.created_at).toLocaleDateString('en-US',
                    {month: 'long', day: 'numeric', year: 'numeric'});

                const actions = [
                    UI.el('a', {
                        href: `/${SITE_SLUG}/member/orders/${order.id}`,
                        className: 'btn btn-primary btn-sm',
                    }, ['View Details']),
                ];
                //if (order.can_cancel) {
                const cancelBtn = UI.el('button', {className: 'btn btn-secondary btn-sm'}, ['Cancel Order']);
                cancelBtn.addEventListener('click', () => this.modal.open(order.id));
                actions.push(cancelBtn);
                //}

                return UI.el('div', {className: 'order-card'}, [
                    UI.el('div', {className: 'order-header'}, [
                        UI.el('div', {}, [
                            UI.el('div', {className: 'order-number'}, [`Order #${order.order_number}`]),
                            UI.el('div', {className: 'order-date'}, [`Placed on ${date}`]),
                        ]),
                        UI.statusBadge(order.status),
                    ]),
                    UI.el('div', {className: 'order-details'}, [
                        this._detail('Total Amount', `${order.currency} ${parseFloat(order.total).toFixed(2)}`),
                        this._detail('Payment Status', order.is_paid ? 'Paid' : 'Pending',
                            order.is_paid ? 'var(--success-color)' : 'var(--warning-color)'),
                        this._detail('Items', `${order.items?.length ?? 0} item(s)`),
                        this._detail('Payment Method', order.payment_method || 'N/A'),
                    ]),
                    UI.el('div', {className: 'order-actions'}, actions),
                ]);
            }));
        }

        _detail(label, value, color = null) {
            return UI.el('div', {className: 'detail-item'}, [
                UI.el('span', {className: 'detail-label'}, [label]),
                UI.el('span', {className: 'detail-value', style: color ? {color} : {}}, [value]),
            ]);
        }
    }

    let ordersList;
    document.addEventListener('DOMContentLoaded', () => {
        const modal = new CancelModal();
        // Expose confirm so the inline button in the PHP template can call it
        window.confirmCancelOrder = () => modal.confirm();
        window.closeCancelModal = () => modal.close();
        ordersList = new OrdersList(modal);
        ordersList.load();
    });
</script>
</body>
</html>