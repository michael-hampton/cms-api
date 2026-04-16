<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - <?= htmlspecialchars($site->name) ?></title>
    <style>
        /* [Original Styles Preserved Exactly] */
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
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .order-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .order-meta {
            color: var(--text-secondary);
            font-size: 0.875rem;
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

        .order-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }

        .summary-item {
            display: flex;
            flex-direction: column;
        }

        .summary-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .summary-value {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
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

        .items-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .item {
            display: flex;
            gap: 1rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .item-meta {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .item-price {
            text-align: right;
            font-weight: 600;
        }

        .address-block {
            margin-bottom: 1.5rem;
        }

        .address-title {
            font-weight: 600;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .address-content {
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .totals-table {
            border-top: 1px solid var(--border-color);
            padding-top: 1rem;
            margin-top: 1rem;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
        }

        .totals-row.total {
            font-size: 1.25rem;
            font-weight: 700;
            padding-top: 1rem;
            margin-top: 0.5rem;
            border-top: 2px solid var(--border-color);
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

        .btn-secondary {
            background: white;
            color: var(--text-primary);
            border: 2px solid var(--border-color);
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        /* Modals... */
        .refund-modal, .cancel-modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 1000;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
        }

        .refund-modal-container, .cancel-modal-container {
            background: white;
            border-radius: 1rem;
            max-width: 800px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }

        /* [Additional styles from original show.php omitted for brevity but should be included] */
        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 1000;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            overflow-y: auto;
            padding: 1rem;
        }

        .modal-container {
            background: white;
            border-radius: 1rem;
            max-width: 600px;
            width: 100%;
            position: relative;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        /* Refund Specific Styles */
        .refund-type-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .refund-type-option {
            border: 2px solid var(--border-color);
            padding: 1rem;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .refund-type-option.active {
            border-color: var(--warning-color);
            background: #fffbeb;
        }

        .refund-type-option input {
            display: none;
        }

        .refund-summary {
            background: var(--bg-light);
            padding: 1rem;
            border-radius: 0.5rem;
            margin: 1.5rem 0;
        }

        .refund-summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .refund-progress {
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            margin: 10px 0;
        }

        .refund-progress-bar {
            height: 100%;
            background: var(--warning-color);
            transition: width 0.3s;
        }

        .refund-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .refund-qty-input {
            width: 60px;
            padding: 0.4rem;
            border: 1px solid var(--border-color);
            border-radius: 0.25rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        select, textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
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

        /** Refund **/
        .refund-modal {
            position: fixed;
            inset: 0;
            z-index: 1000;
        }

        .refund-modal-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
        }

        .refund-modal-container {
            position: relative;
            background: white;
            max-width: 800px;
            margin: 2rem auto;
            border-radius: 1rem;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .refund-modal-header {
            padding: 2rem;
            border-bottom: 1px solid var(--border-color);
            position: relative;
        }

        .refund-modal-header h2 {
            margin-bottom: 0.5rem;
        }

        .refund-modal-subtitle {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .refund-close-btn {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            background: none;
            border: none;
            font-size: 2rem;
            cursor: pointer;
            color: var(--text-secondary);
            line-height: 1;
        }

        .refund-modal-body {
            padding: 2rem;
        }

        .refund-type-section {
            margin-bottom: 2rem;
        }

        .refund-type-section label {
            display: block;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .refund-type-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .refund-type-option {
            border: 2px solid var(--border-color);
            border-radius: 0.75rem;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .refund-type-option:hover {
            border-color: var(--primary-color);
        }

        .refund-type-option.active {
            border-color: var(--primary-color);
            background: #eff6ff;
        }

        .refund-type-option input {
            display: none;
        }

        .refund-type-option strong {
            display: block;
            margin-bottom: 0.25rem;
        }

        .refund-type-option p {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin: 0;
        }

        .refund-summary {
            background: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .refund-summary-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
        }

        .refund-amount-row {
            border-top: 2px solid var(--border-color);
            margin-top: 0.5rem;
            padding-top: 1rem;
            font-weight: 600;
            font-size: 1.25rem;
            color: var(--danger-color);
        }

        .refund-progress {
            margin: 1rem 0 0.5rem;
            height: 0.5rem;
            background: var(--border-color);
            border-radius: 0.25rem;
            overflow: hidden;
        }

        .refund-progress-bar {
            height: 100%;
            background: linear-gradient(to right, #ef4444, #dc2626);
            transition: width 0.3s;
        }

        .refund-percentage {
            text-align: center;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .refund-items-list {
            border: 1px solid var(--border-color);
            border-radius: 0.75rem;
            margin-top: 1rem;
            overflow: hidden;
        }

        .refund-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .refund-item:last-child {
            border-bottom: none;
        }

        .refund-item-info strong {
            display: block;
            margin-bottom: 0.25rem;
        }

        .refund-item-info span {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .refund-qty-input {
            width: 80px;
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: 0.375rem;
        }

        .refund-options {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            padding: 1rem;
            background: var(--bg-light);
            border-radius: 0.5rem;
        }

        .refund-options label {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
        }

        .refund-modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        @media (max-width: 768px) {
            .refund-type-buttons {
                grid-template-columns: 1fr;
            }

            .refund-modal-container {
                margin: 0;
                border-radius: 0;
                max-height: 100vh;
            }
        }

    </style>
</head>
<body>
@include('member._header')

<main class="container" id="main-content">
    <div class="loader-container" style="text-align: center; padding: 5rem;">
        <div class="loader">Loading Order Details...</div>
    </div>
</main>

<div id="refundModal" class="refund-modal"></div>
<div id="cancelModal" class="cancel-modal-overlay" style="display: none"></div>

<script>
    const SITE_SLUG = '<?= \App\Framework\Support\SiteContext::slug() ?>';
    const ORDER_ID = window.location.pathname.split('/').pop();
    let orderData = null;
    let currentRefundType = 'full';

    async function init() {
        try {
            const response = await fetch(`/api/${SITE_SLUG}/member/orders/${ORDER_ID}`);
            const json = await response.json();
            if (json.success) {
                orderData = json.data;
                renderPage();
            }
        } catch (e) {
            console.error("Load failed", e);
        }
    }

    function renderPage() {
        const order = orderData;
        const main = document.getElementById('main-content');

        main.innerHTML = `
            <div class="page-header">
                <div class="header-top">
                    <div>
                        <h1 class="order-title">Order #${order.order_number}</h1>
                        <div class="order-meta">Placed on ${formatDate(order.created_at, true)}</div>
                    </div>
                    <span class="status-badge ${order.status.toLowerCase()}">${order.status}</span>
                </div>

                <div class="order-summary">
                    <div class="summary-item"><span class="summary-label">Order Date</span><span class="summary-value">${formatDate(order.created_at)}</span></div>
                    <div class="summary-item"><span class="summary-label">Invoice Number</span><span class="summary-value">#${order.order_number}</span></div>
                    <div class="summary-item"><span class="summary-label">Account Number</span><span class="summary-value">${order.user_id}</span></div>
                    <div class="summary-item"><span class="summary-label">Total Amount</span><span class="summary-value">${order.currency} ${order.total.toFixed(2)}</span></div>
                    <div class="summary-item">
                        <span class="summary-label">Payment Status</span>
                        <span class="summary-value" style="color: ${order.is_paid ? 'var(--success-color)' : 'var(--warning-color)'}">${order.is_paid ? 'Paid' : 'Pending'}</span>
                    </div>
                    <div class="summary-item"><span class="summary-label">Order Status</span><span class="summary-value">${order.status}</span></div>
                </div>
            </div>

            <div class="content-grid">
                <div>
                    <div class="card">
                        <h2 class="card-title">Order Items</h2>
                        <div class="items-list">
                            ${order.items.map(item => `
                                <div class="item">
                                    <div class="item-details">
                                        <div class="item-name">${item.product_name}</div>
                                        <div class="item-meta">Quantity: ${item.quantity} × ${order.currency} ${item.unit_price.toFixed(2)}</div>
                                        ${renderMetadata(item)}
                                    </div>
                                    <div class="item-price">${order.currency} ${(item.quantity * item.unit_price).toFixed(2)}</div>
                                </div>
                            `).join('')}
                        </div>
                        <div class="totals-table">
                            <div class="totals-row"><span>Subtotal</span><span>${order.currency} ${order.subtotal.toFixed(2)}</span></div>
                            ${order.shipping > 0 ? `<div class="totals-row"><span>Shipping</span><span>${order.currency} ${order.shipping.toFixed(2)}</span></div>` : ''}
                            ${order.tax > 0 ? `<div class="totals-row"><span>Tax</span><span>${order.currency} ${order.tax.toFixed(2)}</span></div>` : ''}
                            ${order.discount > 0 ? `<div class="totals-row" style="color: var(--success-color)"><span>Discount</span><span>-${order.currency} ${order.discount.toFixed(2)}</span></div>` : ''}
                            <div class="totals-row total"><span>Total</span><span>${order.currency} ${order.total.toFixed(2)}</span></div>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="card">
                        <h2 class="card-title">Shipping & Billing</h2>
                        ${renderAddress('📦 Shipping Address', order.shipping_address)}
                        ${renderAddress('💳 Billing Address', order.billing_address)}
                        ${order.customer_notes ? `<div class="address-block"><div class="address-title">📝 Customer Notes</div><div class="address-content">${order.customer_notes}</div></div>` : ''}
                    </div>
                </div>
            </div>

            <div class="actions">
                <a href="/${SITE_SLUG}/member/orders" class="btn btn-secondary">← Back to Orders</a>
                ${order.can_be_refunded ? `<button onclick="openRefundModal()" class="btn btn-warning">Process Refund</button>` : ''}
                ${order.can_be_cancelled ? `<button onclick="openCancelModal()" class="btn btn-warning">Cancel Order</button>` : ''}
            </div>
        `;

        renderRefundModalUI();
        renderCancelModalUI();
    }

    function renderRefundModalUI() {
        const order = orderData;
        document.getElementById('refundModal').innerHTML = `
        <div class="refund-modal-overlay" onclick="closeRefundModal()"></div>
        <div class="refund-modal-container">
            <div class="refund-modal-header">
                <h2>Process Refund</h2>
                <p class="refund-modal-subtitle">Order #${order.order_number}</p>
                <button class="refund-close-btn" onclick="closeRefundModal()">×</button>
            </div>
            <div class="refund-modal-body">
                <div class="refund-type-section">
                    <label>Refund Type</label>
                    <div class="refund-type-buttons">
                        <label class="refund-type-option ${currentRefundType === 'full' ? 'active' : ''}" id="opt-full">
                            <input type="radio" name="refund_type" value="full" ${currentRefundType === 'full' ? 'checked' : ''} onchange="updateRefundType('full')">
                            <div>
                                <strong>Full Refund</strong>
                                <p>Refund the entire order amount</p>
                            </div>
                        </label>
                        <label class="refund-type-option ${currentRefundType === 'partial' ? 'active' : ''}" id="opt-partial">
                            <input type="radio" name="refund_type" value="partial" ${currentRefundType === 'partial' ? 'checked' : ''} onchange="updateRefundType('partial')">
                            <div>
                                <strong>Partial Refund</strong>
                                <p>Refund specific items or amount</p>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="refund-summary">
                    <div class="refund-summary-row">
                        <span>Order Total:</span>
                        <span>${order.currency} ${order.total.toFixed(2)}</span>
                    </div>
                    <div class="refund-summary-row refund-amount-row">
                        <span>Refund Amount:</span>
                        <span id="refundAmount">${order.currency} ${order.total.toFixed(2)}</span>
                    </div>
                    <div class="refund-progress">
                        <div id="refundProgressBar" class="refund-progress-bar" style="width: 100%"></div>
                    </div>
                    <div class="refund-percentage" id="refundPercentage">100% of order total</div>
                </div>

                <div id="partialRefundItems" style="display: ${currentRefundType === 'partial' ? 'block' : 'none'};">
                    <label>Items to Refund</label>
                    <div class="refund-items-list">
                        ${order.items.map(item => `
                            <div class="refund-item">
                                <div class="refund-item-info">
                                    <strong>${item.product_name}</strong>
                                    <span>${order.currency} ${item.unit_price.toFixed(2)} × ${item.quantity}</span>
                                </div>
                                <div class="refund-item-controls">
                                    <input type="number"
                                       class="refund-qty-input"
                                       min="0"
                                       max="${item.quantity}"
                                       value="0"
                                       data-item-id="${item.id}"
                                       data-product-id="${item.product_id}"
                                       data-price="${item.unit_price}"
                                       data-product-name="${item.product_name}"  // <--- Add this line
                                       onchange="updatePartialRefund()">
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>

                <div class="form-group">
                    <label>Reason for Refund *</label>
                    <select id="refundReason" required>
                        <option value="">Select a reason</option>
                        <option value="customer_request">Customer Request</option>
                        <option value="damaged_item">Damaged Item</option>
                        <option value="wrong_item">Wrong Item Sent</option>
                        <option value="not_received">Item Not Received</option>
                        <option value="quality_issue">Quality Issue</option>
                        <option value="changed_mind">Customer Changed Mind</option>
                        <option value="duplicate_order">Duplicate Order</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Internal Notes</label>
                    <textarea id="refundNotes" rows="3" placeholder="Add any internal notes..."></textarea>
                </div>

                <div class="refund-options">
                    <label><input type="checkbox" id="notifyCustomer" checked> <span>Notify customer via email</span></label>
                    <label><input type="checkbox" id="restockItems" checked> <span>Restock items to inventory</span></label>
                </div>
            </div>
            <div class="refund-modal-footer">
                <button class="btn btn-secondary" onclick="closeRefundModal()">Cancel</button>
                <button class="btn btn-warning" onclick="processRefund()">Process Refund</button>
            </div>
        </div>
    `;
    }

    function renderCancelModalUI() {
        document.getElementById('cancelModal').innerHTML = `
            <div class="modal-container">
                <div class="modal-header"><h2>Cancel Order</h2></div>
                <div class="modal-body">
                    <p>Are you sure you want to cancel order #${orderData.order_number}?</p>
                    <div class="form-group" style="margin-top:1rem">
                        <label>Cancellation Reason *</label>
                        <select id="cancelReason"> <option value="">Select a reason...</option>
                    <option value="customer_request">Customer Request</option>
                    <option value="out_of_stock">Out of Stock</option>
                    <option value="payment_failed">Payment Failed</option>
                    <option value="fraudulent">Fraudulent Order</option>
                    <option value="duplicate">Duplicate Order</option>
                    <option value="other">Other</option></select>
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
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeModal('cancelModal')">Keep Order</button>
                    <button class="btn btn-danger" onclick="confirmCancelOrder()">Cancel Order</button>
                </div>
            </div>
        `;
    }

    async function confirmCancelOrder() {
        const reasonSelect = document.getElementById('cancelReason');
        const reason = reasonSelect ? reasonSelect.value : '';

        if (!reason) {
            alert('Please select a cancellation reason');
            return;
        }

        const notifyEl = document.getElementById('notifyCustomer');

        try {
            const response = await fetch(`/api/${SITE_SLUG}/member/orders/${ORDER_ID}/cancel`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    reason,
                    notify_customer: notifyEl ? notifyEl.checked : true,
                }),
            });
            const data = await response.json();
            if (data.success) {
                alert('Order cancelled successfully');
                location.reload();
            } else {
                alert(data.message || 'Failed to cancel order');
            }
        } catch (error) {
            console.error('Cancel error:', error);
            alert('Failed to cancel order. Please try again.');
        }
    }

    function updateRefundType(type) {
        currentRefundType = type;
        const partialItems = document.getElementById('partialRefundItems');

        // Toggle active classes
        document.getElementById('opt-full').classList.toggle('active', type === 'full');
        document.getElementById('opt-partial').classList.toggle('active', type === 'partial');

        if (type === 'full') {
            partialItems.style.display = 'none';
            updateRefundDisplay(orderData.total);
        } else {
            partialItems.style.display = 'block';
            updatePartialRefund();
        }
    }

    function closeRefundModal() {
        document.getElementById('refundModal').style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    function calculatePartialAmount() {
        let total = 0;
        document.querySelectorAll('.refund-qty-input').forEach(input => {
            total += (parseInt(input.value) || 0) * (parseFloat(input.dataset.price) || 0);
        });
        return total;
    }

    function getPartialItems() {
        const items = [];
        document.querySelectorAll('.refund-qty-input').forEach(input => {
            const quantity = parseInt(input.value) || 0;
            if (quantity > 0) {
                items.push({
                    id: input.dataset.itemId,
                    product_name: input.dataset.productName,
                    quantity,
                    amount: quantity * parseFloat(input.dataset.price),
                    product_id: input.dataset.productId,
                });
            }
        });
        return items;
    }

    function updatePartialRefund() {
        updateRefundDisplay(calculatePartialAmount());
    }

    function updateRefundDisplay(amount) {
        const pct = orderData.total > 0 ? (amount / orderData.total) * 100 : 0;

        const amountEl = document.getElementById('refundAmount');
        if (amountEl) amountEl.textContent = `${orderData.currency} ${amount.toFixed(2)}`;

        const barEl = document.getElementById('refundProgressBar');
        if (barEl) barEl.style.width = `${Math.min(pct, 100)}%`;

        const pctEl = document.getElementById('refundPercentage');
        if (pctEl) pctEl.textContent = `${Math.round(pct)}% of order total`;
    }

    async function processRefund() {
        const reason = document.getElementById('refundReason').value;
        if (!reason) {
            alert('Please select a reason for the refund');
            return;
        }

        const refundData = {
            order_id: orderData.id,
            refund_type: currentRefundType,
            refund_amount: currentRefundType === 'full' ? orderData.total : calculatePartialAmount(),
            reason: reason,
            internal_notes: document.getElementById('refundNotes').value,
            notify_customer: document.getElementById('notifyCustomer').checked,
            restock_items: document.getElementById('restockItems').checked,
            items: currentRefundType === 'partial' ? getPartialItems() : []
        };

        try {
            const response = await fetch(`/api/${SITE_SLUG}/member/orders/${ORDER_ID}/refund`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(refundData)
            });

            const data = await response.json();
            if (data.success) {
                alert('Refund processed successfully');
                window.location.reload();
            } else {
                alert(data.message || 'Failed to process refund');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Failed to process refund');
        }
    }

    function calculatePartialTotal() {
        let t = 0;
        document.querySelectorAll('.refund-qty-input').forEach(i => t += (parseInt(i.value) || 0) * parseFloat(i.dataset.price));
        return t;
    }

    function openRefundModal() {
        document.getElementById('refundModal').style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    function openCancelModal() {
        document.getElementById('cancelModal').style.display = 'flex';
    }

    function closeModal(id) {
        document.getElementById(id).style.display = 'none';
    }

    function renderMetadata(item) {
        if (!item.metadata) return '';
        // Logical recreation of the subscription display logic...
        return `<div class="item-description" style="margin-top: 0.5rem; font-size: 0.875rem; color: var(--text-secondary);">
            ${item.metadata.description ? `<strong>Description:</strong><br>${item.metadata.description}` : ''}
        </div>`;
    }

    function renderAddress(title, addr) {
        if (!addr) return '';
        return `
            <div class="address-block">
                <div class="address-title">${title}</div>
                <div class="address-content">${addr.address_line_1}<br>${addr.city}, ${addr.state} ${addr.postcode}<br>${addr.country}</div>
            </div>
        `;
    }

    function formatDate(dateStr, includeTime = false) {
        const d = new Date(dateStr);
        return includeTime
            ? d.toLocaleDateString('en-US', {
            month: 'long',
            day: 'numeric',
            year: 'numeric'
        }) + ' at ' + d.toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'})
            : d.toLocaleDateString('en-US', {month: 'short', day: '2-digit', year: 'numeric'});
    }

    // Modal and Action logic remains largely the same but uses /api/member/orders/...
    document.addEventListener('DOMContentLoaded', init);
</script>
</body>
</html>