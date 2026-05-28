<?php ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - <?= htmlspecialchars($site->name) ?></title>
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
            --shadow: 0 1px 3px rgba(0, 0, 0, .1);
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

        /* ── Loader ── */
        .page-loader {
            text-align: center;
            padding: 5rem;
            color: var(--text-secondary);
        }

        /* ── Page header ── */
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
            margin-bottom: .5rem;
        }

        .order-meta {
            color: var(--text-secondary);
            font-size: .875rem;
        }

        .status-badge {
            padding: .5rem 1rem;
            border-radius: 2rem;
            font-size: .875rem;
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
            font-size: .875rem;
            color: var(--text-secondary);
            margin-bottom: .5rem;
        }

        .summary-value {
            font-size: 1.25rem;
            font-weight: 600;
        }

        /* ── Layout ── */
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
            margin-bottom: 2rem;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        /* ── Order items ── */
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
            margin-bottom: .25rem;
        }

        .item-meta {
            font-size: .875rem;
            color: var(--text-secondary);
        }

        .item-price {
            text-align: right;
            font-weight: 600;
        }

        .item-description {
            margin-top: .5rem;
            font-size: .875rem;
            color: var(--text-secondary);
        }

        /* ── Totals ── */
        .totals-table {
            border-top: 1px solid var(--border-color);
            padding-top: 1rem;
            margin-top: 1rem;
        }

        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: .5rem 0;
        }

        .totals-row.total {
            font-size: 1.25rem;
            font-weight: 700;
            padding-top: 1rem;
            margin-top: .5rem;
            border-top: 2px solid var(--border-color);
        }

        /* ── Addresses ── */
        .address-block {
            margin-bottom: 1.5rem;
        }

        .address-title {
            font-weight: 600;
            margin-bottom: .75rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        .address-content {
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* ── Actions ── */
        .actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            padding: .75rem 1.5rem;
            border: none;
            border-radius: .5rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            transition: all .2s;
            font-size: .875rem;
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
            background: var(--warning-color);
            color: white;
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        /* ── Modal shared ── */
        .modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 1000;
            background: rgba(0, 0, 0, .5);
            display: none;
            align-items: center;
            justify-content: center;
            overflow-y: auto;
            padding: 1rem;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-container {
            background: white;
            border-radius: 1rem;
            max-width: 600px;
            width: 100%;
            position: relative;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, .1);
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .modal-header h2 {
            margin: 0;
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

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: .5rem;
            font-size: .875rem;
        }

        select, textarea {
            width: 100%;
            padding: .75rem;
            border: 1px solid var(--border-color);
            border-radius: .5rem;
            font-size: .875rem;
            font-family: inherit;
        }

        /* ── Cancel modal ── */
        .cancel-modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            gap: 1rem;
            align-items: flex-start;
            background: linear-gradient(to bottom, #fef2f2, white);
            border-top: 4px solid var(--danger-color);
            border-radius: 1rem 1rem 0 0;
        }

        .cancel-warning-icon {
            font-size: 2rem;
        }

        .cancel-modal-header h2 {
            margin: 0;
            font-size: 1.25rem;
        }

        .cancel-modal-header p {
            margin: .25rem 0 0;
            font-size: .875rem;
            color: var(--text-secondary);
        }

        .cancel-modal-body {
            padding: 1.5rem;
        }

        .cancel-checkbox-label {
            display: flex;
            align-items: center;
            gap: .5rem;
            cursor: pointer;
            font-weight: normal;
            margin-bottom: .75rem;
        }

        .cancel-checkbox-label input {
            width: 18px;
            height: 18px;
        }

        .cancel-warning-box {
            padding: 1rem;
            background: #fef3c7;
            border: 1px solid #fbbf24;
            border-radius: .5rem;
            margin-top: 1rem;
            font-size: .875rem;
        }

        .cancel-warning-box strong {
            color: #92400e;
            display: block;
            margin-bottom: .5rem;
        }

        .cancel-warning-box ul {
            margin: .5rem 0 0 1.25rem;
            color: #78350f;
        }

        .cancel-modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: .75rem;
            justify-content: flex-end;
        }

        /* ── Refund modal ── */
        .refund-modal {
            position: fixed;
            inset: 0;
            z-index: 1000;
            display: none;
        }

        .refund-modal.show {
            display: block;
        }

        .refund-modal-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, .5);
        }

        .refund-modal-container {
            position: relative;
            background: white;
            max-width: 800px;
            margin: 2rem auto;
            border-radius: 1rem;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, .1);
        }

        .refund-modal-header {
            padding: 2rem;
            border-bottom: 1px solid var(--border-color);
            position: relative;
        }

        .refund-modal-header h2 {
            margin-bottom: .5rem;
        }

        .refund-modal-subtitle {
            color: var(--text-secondary);
            font-size: .875rem;
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

        .refund-modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .refund-type-section {
            margin-bottom: 2rem;
        }

        .refund-type-section > label {
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
            border-radius: .75rem;
            padding: 1.5rem;
            cursor: pointer;
            transition: all .2s;
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
            margin-bottom: .25rem;
        }

        .refund-type-option p {
            font-size: .875rem;
            color: var(--text-secondary);
            margin: 0;
        }

        .refund-summary {
            background: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: .75rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .refund-summary-row {
            display: flex;
            justify-content: space-between;
            padding: .5rem 0;
        }

        .refund-amount-row {
            border-top: 2px solid var(--border-color);
            margin-top: .5rem;
            padding-top: 1rem;
            font-weight: 600;
            font-size: 1.25rem;
            color: var(--danger-color);
        }

        .refund-progress {
            margin: 1rem 0 .5rem;
            height: .5rem;
            background: var(--border-color);
            border-radius: .25rem;
            overflow: hidden;
        }

        .refund-progress-bar {
            height: 100%;
            background: linear-gradient(to right, #ef4444, #dc2626);
            transition: width .3s;
        }

        .refund-percentage {
            text-align: center;
            font-size: .75rem;
            color: var(--text-secondary);
        }

        .refund-items-list {
            border: 1px solid var(--border-color);
            border-radius: .75rem;
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
            margin-bottom: .25rem;
        }

        .refund-item-info span {
            font-size: .875rem;
            color: var(--text-secondary);
        }

        .refund-qty-input {
            width: 80px;
            padding: .5rem;
            border: 1px solid var(--border-color);
            border-radius: .375rem;
        }

        .refund-options {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            padding: 1rem;
            background: var(--bg-light);
            border-radius: .5rem;
        }

        .refund-options label {
            display: flex;
            align-items: center;
            gap: .75rem;
            cursor: pointer;
        }

        /* ── Toast ── */
        .toast-container {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: .75rem;
            pointer-events: none;
        }

        .toast {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: 1rem 1.25rem;
            border-radius: .75rem;
            font-size: .9375rem;
            font-weight: 500;
            pointer-events: all;
            animation: slideIn .3s ease;
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

        .toast-icon {
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .toast-close {
            margin-left: auto;
            background: none;
            border: none;
            cursor: pointer;
            color: inherit;
            opacity: .6;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(100%)
            }
            to {
                opacity: 1;
                transform: translateX(0)
            }
        }

        @keyframes slideOut {
            from {
                opacity: 1
            }
            to {
                opacity: 0;
                transform: translateX(100%)
            }
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .refund-type-buttons {
                grid-template-columns: 1fr;
            }

            .actions {
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

<main class="container" id="main-content">
    <div class="page-loader">Loading order details…</div>
</main>

<div id="refundModal" class="refund-modal"></div>
<div id="cancelModal" class="modal-overlay"></div>

<script>
    /* ─────────────────────────────────────────────────────────────
       Component: OrderHeader
    ───────────────────────────────────────────────────────────── */
    class OrderHeader {
        constructor(order) {
            this.order = order;
        }

        render() {
            const o = this.order;
            const summaryItems = [
                ['Order Date', UI.formatDate(o.created_at)],
                ['Invoice Number', `#${o.order_number}`],
                ['Account Number', String(o.user_id)],
                ['Total Amount', `${o.currency} ${o.total.toFixed(2)}`],
                ['Payment Status', null, o.is_paid ? 'Paid' : 'Pending', o.is_paid ? 'var(--success-color)' : 'var(--warning-color)'],
                ['Order Status', o.status],
            ];

            return UI.el('div', {className: 'page-header'}, [
                UI.el('div', {className: 'header-top'}, [
                    UI.el('div', {}, [
                        UI.el('h1', {className: 'order-title'}, [`Order #${o.order_number}`]),
                        UI.el('div', {className: 'order-meta'}, [`Placed on ${UI.formatDate(o.created_at, true)}`]),
                    ]),
                    UI.el('span', {className: `status-badge ${o.status.toLowerCase()}`}, [o.status]),
                ]),
                UI.el('div', {className: 'order-summary'},
                    summaryItems.map(([label, value, display, color]) =>
                        UI.el('div', {className: 'summary-item'}, [
                            UI.el('span', {className: 'summary-label'}, [label]),
                            UI.el('span', {
                                className: 'summary-value',
                                style: color ? {color} : {}
                            }, [display ?? value]),
                        ])
                    )
                ),
            ]);
        }
    }

    /* ─────────────────────────────────────────────────────────────
       Component: OrderItems
    ───────────────────────────────────────────────────────────── */
    class OrderItems {
        constructor(order) {
            this.order = order;
        }

        render() {
            const o = this.order;

            const itemEls = o.items.map(item =>
                UI.el('div', {className: 'item'}, [
                    UI.el('div', {className: 'item-details'}, [
                        UI.el('div', {className: 'item-name'}, [item.product_name]),
                        UI.el('div', {className: 'item-meta'}, [`Quantity: ${item.quantity} × ${o.currency} ${item.unit_price.toFixed(2)}`]),
                        item.metadata?.description
                            ? UI.el('div', {className: 'item-description'}, [item.metadata.description])
                            : null,
                    ]),
                    UI.el('div', {className: 'item-price'}, [`${o.currency} ${(item.quantity * item.unit_price).toFixed(2)}`]),
                ])
            );

            const totalsRows = [
                UI.el('div', {className: 'totals-row'}, [UI.el('span', {}, ['Subtotal']), UI.el('span', {}, [`${o.currency} ${o.subtotal.toFixed(2)}`])]),
                o.shipping > 0 ? UI.el('div', {className: 'totals-row'}, [UI.el('span', {}, ['Shipping']), UI.el('span', {}, [`${o.currency} ${o.shipping.toFixed(2)}`])]) : null,
                o.tax > 0 ? UI.el('div', {className: 'totals-row'}, [UI.el('span', {}, ['Tax']), UI.el('span', {}, [`${o.currency} ${o.tax.toFixed(2)}`])]) : null,
                o.discount > 0 ? UI.el('div', {
                    className: 'totals-row',
                    style: {color: 'var(--success-color)'}
                }, [UI.el('span', {}, ['Discount']), UI.el('span', {}, [`-${o.currency} ${o.discount.toFixed(2)}`])]) : null,
                UI.el('div', {className: 'totals-row total'}, [UI.el('span', {}, ['Total']), UI.el('span', {}, [`${o.currency} ${o.total.toFixed(2)}`])]),
            ].filter(Boolean);

            return UI.el('div', {className: 'card'}, [
                UI.el('h2', {className: 'card-title'}, ['Order Items']),
                UI.el('div', {className: 'items-list'}, itemEls),
                UI.el('div', {className: 'totals-table'}, totalsRows),
            ]);
        }
    }

    /* ─────────────────────────────────────────────────────────────
       Component: OrderAddresses
    ───────────────────────────────────────────────────────────── */
    class OrderAddresses {
        constructor(order) {
            this.order = order;
        }

        _renderAddress(label, addr) {
            if (!addr) return null;
            return UI.el('div', {className: 'address-block'}, [
                UI.el('div', {className: 'address-title'}, [label]),
                UI.el('div', {className: 'address-content'}, [
                    addr.address_line_1,
                    UI.el('br'),
                    `${addr.city}, ${addr.state} ${addr.postcode}`,
                    UI.el('br'),
                    addr.country
                ]),
            ]);
        }

        render() {
            const o = this.order;
            return UI.el('div', {className: 'card'}, [
                UI.el('h2', {className: 'card-title'}, ['Shipping & Billing']),
                this._renderAddress('📦 Shipping Address', o.shipping_address),
                this._renderAddress('💳 Billing Address', o.billing_address),
                o.customer_notes ? UI.el('div', {className: 'address-block'}, [
                    UI.el('div', {className: 'address-title'}, ['📝 Customer Notes']),
                    UI.el('div', {className: 'address-content'}, [o.customer_notes]),
                ]) : null,
            ]);
        }
    }

    /* ─────────────────────────────────────────────────────────────
       Component: OrderActions
    ───────────────────────────────────────────────────────────── */
    class OrderActions {
        constructor(order, siteSlug) {
            this.order = order;
            this.siteSlug = siteSlug;
        }

        render() {
            const o = this.order;
            return UI.el('div', {className: 'actions'}, [
                UI.el('a', {
                    href: `/${this.siteSlug}/member/orders`,
                    className: 'btn btn-secondary'
                }, ['← Back to Orders']),
                o.can_be_refunded ? UI.el('button', {
                    className: 'btn btn-warning',
                    onclick: () => window.orderApp.openRefundModal()
                }, ['Process Refund']) : null,
                o.can_be_cancelled ? UI.el('button', {
                    className: 'btn btn-warning',
                    onclick: () => window.orderApp.openCancelModal()
                }, ['Cancel Order']) : null,
            ].filter(Boolean));
        }
    }

    /* ─────────────────────────────────────────────────────────────
       Component: RefundModal (singleton IIFE)
    ───────────────────────────────────────────────────────────── */
    const RefundModal = (() => {
        function open(orderData, currentRefundType, onTypeChange) {
            const el = document.getElementById('refundModal');
            UI.render(el, [buildContent(orderData, currentRefundType, onTypeChange)]);
            el.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function close() {
            document.getElementById('refundModal').classList.remove('show');
            document.body.style.overflow = '';
        }

        function buildContent(o, currentRefundType, onTypeChange) {
            const overlay = UI.el('div', {className: 'refund-modal-overlay', onclick: close});

            const fullOpt = _buildTypeOption('full', 'Full Refund', 'Refund the entire order amount', currentRefundType, onTypeChange);
            const partialOpt = _buildTypeOption('partial', 'Partial Refund', 'Refund specific items or amount', currentRefundType, onTypeChange);

            const amountEl = UI.el('span', {id: 'refundAmount'}, [`${o.currency} ${o.total.toFixed(2)}`]);
            const barEl = UI.el('div', {
                id: 'refundProgressBar',
                className: 'refund-progress-bar',
                style: {width: '100%'}
            });
            const pctEl = UI.el('div', {
                id: 'refundPercentage',
                className: 'refund-percentage'
            }, ['100% of order total']);

            const partialItemsEl = UI.el('div', {id: 'partialRefundItems', style: {display: 'none'}}, [
                UI.el('label', {}, ['Items to Refund']),
                UI.el('div', {className: 'refund-items-list'},
                    o.items.map(item => {
                        const qtyInput = UI.el('input', {
                            type: 'number', className: 'refund-qty-input',
                            min: '0', max: String(item.quantity), value: '0',
                            'data-item-id': String(item.id),
                            'data-product-id': String(item.product_id),
                            'data-price': String(item.unit_price),
                            'data-product-name': item.product_name,
                        });
                        qtyInput.addEventListener('change', () => window.orderApp.onPartialRefundChange());
                        return UI.el('div', {className: 'refund-item'}, [
                            UI.el('div', {className: 'refund-item-info'}, [
                                UI.el('strong', {}, [item.product_name]),
                                UI.el('span', {}, [`${o.currency} ${item.unit_price.toFixed(2)} × ${item.quantity}`]),
                            ]),
                            UI.el('div', {className: 'refund-item-controls'}, [qtyInput]),
                        ]);
                    })
                ),
            ]);

            const reasonSel = UI.el('select', {id: 'refundReason', required: 'true'}, [
                UI.el('option', {value: ''}, ['Select a reason']),
                ...['customer_request', 'damaged_item', 'wrong_item', 'not_received', 'quality_issue', 'changed_mind', 'duplicate_order', 'other']
                    .map(v => UI.el('option', {value: v}, [v.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())])),
            ]);

            const notesArea = UI.el('textarea', {id: 'refundNotes', rows: '3', placeholder: 'Add any internal notes…'});
            const processBtn = UI.el('button', {
                className: 'btn btn-warning',
                onclick: () => window.orderApp.processRefund()
            }, ['Process Refund']);

            const container = UI.el('div', {className: 'refund-modal-container'}, [
                UI.el('div', {className: 'refund-modal-header'}, [
                    UI.el('h2', {}, ['Process Refund']),
                    UI.el('p', {className: 'refund-modal-subtitle'}, [`Order #${o.order_number}`]),
                    UI.el('button', {className: 'refund-close-btn', onclick: close}, ['×']),
                ]),
                UI.el('div', {className: 'refund-modal-body'}, [
                    UI.el('div', {className: 'refund-type-section'}, [
                        UI.el('label', {}, ['Refund Type']),
                        UI.el('div', {className: 'refund-type-buttons'}, [fullOpt, partialOpt]),
                    ]),
                    UI.el('div', {className: 'refund-summary'}, [
                        UI.el('div', {className: 'refund-summary-row'}, [UI.el('span', {}, ['Order Total:']), UI.el('span', {}, [`${o.currency} ${o.total.toFixed(2)}`])]),
                        UI.el('div', {className: 'refund-summary-row refund-amount-row'}, [UI.el('span', {}, ['Refund Amount:']), amountEl]),
                        UI.el('div', {className: 'refund-progress'}, [barEl]),
                        pctEl,
                    ]),
                    partialItemsEl,
                    UI.el('div', {className: 'form-group'}, [
                        UI.el('label', {}, ['Reason for Refund *']),
                        reasonSel,
                    ]),
                    UI.el('div', {className: 'form-group'}, [
                        UI.el('label', {}, ['Internal Notes']),
                        notesArea,
                    ]),
                    UI.el('div', {className: 'refund-options'}, [
                        UI.el('label', {}, [UI.el('input', {
                            type: 'checkbox',
                            id: 'notifyCustomer',
                            checked: 'true'
                        }), UI.el('span', {}, ['Notify customer via email'])]),
                        UI.el('label', {}, [UI.el('input', {
                            type: 'checkbox',
                            id: 'restockItems',
                            checked: 'true'
                        }), UI.el('span', {}, ['Restock items to inventory'])]),
                    ]),
                ]),
                UI.el('div', {className: 'refund-modal-footer'}, [
                    UI.el('button', {className: 'btn btn-secondary', onclick: close}, ['Cancel']),
                    processBtn,
                ]),
            ]);

            return UI.fragment([overlay, container]);
        }

        function _buildTypeOption(value, strong, desc, currentType, onTypeChange) {
            const opt = UI.el('label', {
                className: `refund-type-option${currentType === value ? ' active' : ''}`,
                id: `opt-${value}`,
            }, [
                UI.el('input', {type: 'radio', name: 'refund_type', value}),
                UI.el('div', {}, [UI.el('strong', {}, [strong]), UI.el('p', {}, [desc])]),
            ]);
            opt.addEventListener('click', () => onTypeChange(value));
            return opt;
        }

        return {open, close};
    })();

    /* ─────────────────────────────────────────────────────────────
       Component: CancelModal (singleton IIFE)
    ───────────────────────────────────────────────────────────── */
    const CancelModal = (() => {
        function open(orderData) {
            const el = document.getElementById('cancelModal');
            UI.render(el, [buildContent(orderData)]);
            el.classList.add('show');
        }

        function close() {
            document.getElementById('cancelModal').classList.remove('show');
        }

        function buildContent(o) {
            const reasonSel = UI.el('select', {id: 'cancelReason'}, [
                UI.el('option', {value: ''}, ['Select a reason…']),
                ...['customer_request', 'out_of_stock', 'payment_failed', 'fraudulent', 'duplicate', 'other']
                    .map(v => UI.el('option', {value: v}, [v.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())])),
            ]);

            return UI.el('div', {className: 'modal-container'}, [
                UI.el('div', {className: 'cancel-modal-header'}, [
                    UI.el('span', {className: 'cancel-warning-icon'}, ['⚠️']),
                    UI.el('div', {}, [
                        UI.el('h2', {}, ['Cancel Order']),
                        UI.el('p', {}, [`Are you sure you want to cancel order #${o.order_number}?`]),
                    ]),
                ]),
                UI.el('div', {className: 'cancel-modal-body'}, [
                    UI.el('div', {className: 'form-group'}, [
                        UI.el('label', {}, ['Cancellation Reason *']),
                        reasonSel,
                    ]),
                    UI.el('div', {}, [
                        UI.el('label', {className: 'cancel-checkbox-label'}, [
                            UI.el('input', {type: 'checkbox', id: 'notifyCustomerCancel', checked: 'true'}),
                            UI.el('span', {}, ['Send cancellation notification to customer']),
                        ]),
                    ]),
                    UI.el('div', {className: 'cancel-warning-box'}, [
                        UI.el('strong', {}, ['Important: Cancelling this order will:']),
                        UI.el('ul', {}, [
                            UI.el('li', {}, ['Mark the order as cancelled in the system']),
                            UI.el('li', {}, ['Release any reserved inventory']),
                            UI.el('li', {}, ['Stop any pending fulfilment processes']),
                        ]),
                    ]),
                ]),
                UI.el('div', {className: 'cancel-modal-footer'}, [
                    UI.el('button', {className: 'btn btn-secondary', onclick: () => close()}, ['Keep Order']),
                    UI.el('button', {
                        className: 'btn btn-danger',
                        onclick: () => window.orderApp.confirmCancelOrder()
                    }, ['Cancel Order']),
                ]),
            ]);
        }

        return {open, close};
    })();

    /* ─────────────────────────────────────────────────────────────
       OrderManager — orchestrates loading, rendering, and actions
    ───────────────────────────────────────────────────────────── */
    class OrderStore {
        constructor() {
            this.state = {
                orderData: null,
                loading: false,
                error: null,
                currentRefundType: 'full',
                refundSubmitting: false,
                cancelSubmitting: false,
            };
            this.listeners = [];
        }

        subscribe(listener) {
            this.listeners.push(listener);
            listener(this.state);
        }

        setState(patch) {
            this.state = {
                ...this.state,
                ...patch,
            };

            this.listeners.forEach(listener => listener(this.state));
        }
    }

    class OrderManager {
        constructor({siteSlug}) {
            this.siteSlug = siteSlug;
            this.store = new OrderStore();

            this.store.subscribe(state => this._render(state));

            document.addEventListener('DOMContentLoaded', () => this._load());
        }

        /* ── Bootstrap ── */

        async _load() {
            const orderId = window.location.pathname.split('/').pop();
            this.store.setState({loading: true, error: null});

            try {
                const json = await api(`/api/${this.siteSlug}/member/orders/${orderId}`);
                this.store.setState({
                    orderData: json.data,
                    loading: false,
                });
            } catch (_) {
                this.store.setState({
                    loading: false,
                    error: 'Failed to load order details. Please refresh the page.',
                });
                UI.render(document.getElementById('main-content'), [
                    UI.el('div', {className: 'page-loader'}, ['']),
                ]);
                UI.toast('Failed to load order details. Please refresh the page.', 'error');
            }
        }

        _render(state) {
            if (state.loading || !state.orderData) {
                return;
            }

            const o = state.orderData;
            const main = document.getElementById('main-content');

            UI.render(main, [
                new OrderHeader(o).render(),
                UI.el('div', {className: 'content-grid'}, [
                    new OrderItems(o).render(),
                    new OrderAddresses(o).render(),
                ]),
                new OrderActions(o, this.siteSlug).render(),
            ]);
        }

        /* ── Modal openers ── */

        openRefundModal() {
            this.store.setState({currentRefundType: 'full'});
            RefundModal.open(this.store.state.orderData, this.store.state.currentRefundType, type => this._updateRefundType(type));
        }

        openCancelModal() {
            CancelModal.open(this.store.state.orderData);
        }

        /* ── Refund type switching ── */

        _updateRefundType(type) {
            this.store.setState({currentRefundType: type});

            document.getElementById('opt-full')?.classList.toggle('active', type === 'full');
            document.getElementById('opt-partial')?.classList.toggle('active', type === 'partial');

            const partialEl = document.getElementById('partialRefundItems');
            if (partialEl) partialEl.style.display = type === 'partial' ? 'block' : 'none';

            this._updateRefundDisplay(type === 'full' ? this.store.state.orderData.total : this._calculatePartialAmount());
        }

        _calculatePartialAmount() {
            let total = 0;
            document.querySelectorAll('.refund-qty-input').forEach(input => {
                total += (parseInt(input.value) || 0) * (parseFloat(input.dataset.price) || 0);
            });
            return total;
        }

        _getPartialItems() {
            return Array.from(document.querySelectorAll('.refund-qty-input'))
                .filter(input => parseInt(input.value) > 0)
                .map(input => ({
                    id: input.dataset.itemId,
                    product_name: input.dataset.productName,
                    quantity: parseInt(input.value),
                    amount: parseInt(input.value) * parseFloat(input.dataset.price),
                    product_id: input.dataset.productId,
                }));
        }

        _updateRefundDisplay(amount) {
            const orderData = this.store.state.orderData;
            const pctSafe = orderData.total > 0 ? (amount / orderData.total) * 100 : 0;
            const amountEl = document.getElementById('refundAmount');
            const barEl = document.getElementById('refundProgressBar');
            const pctEl = document.getElementById('refundPercentage');
            if (amountEl) UI.text(amountEl, `${orderData.currency} ${amount.toFixed(2)}`);
            if (barEl) barEl.style.width = `${Math.min(pctSafe, 100)}%`;
            if (pctEl) UI.text(pctEl, `${Math.round(pctSafe)}% of order total`);
        }

        /* Called by qty input change listeners */
        onPartialRefundChange() {
            this._updateRefundDisplay(this._calculatePartialAmount());
        }

        /* ── Actions ── */

        async processRefund() {
            const reason = document.getElementById('refundReason')?.value;
            if (!reason) {
                UI.toast('Please select a reason for the refund', 'error');
                return;
            }

            try {
                this.store.setState({refundSubmitting: true});
                await api(`/api/${this.siteSlug}/member/orders/${this.store.state.orderData.id}/refund`, {
                    method: 'POST',
                    body: JSON.stringify({
                        order_id: this.store.state.orderData.id,
                        refund_type: this.store.state.currentRefundType,
                        refund_amount: this.store.state.currentRefundType === 'full' ? this.store.state.orderData.total : this._calculatePartialAmount(),
                        reason,
                        internal_notes: document.getElementById('refundNotes')?.value,
                        notify_customer: document.getElementById('notifyCustomer')?.checked ?? true,
                        restock_items: document.getElementById('restockItems')?.checked ?? true,
                        items: this.store.state.currentRefundType === 'partial' ? this._getPartialItems() : [],
                    }),
                });
                UI.toast('Refund processed successfully', 'success');
                RefundModal.close();
                location.reload();
            } catch (err) {
                UI.toast(err.message ?? 'Failed to process refund', 'error');
            } finally {
                this.store.setState({refundSubmitting: false});
            }
        }

        async confirmCancelOrder() {
            const reason = document.getElementById('cancelReason')?.value;
            if (!reason) {
                UI.toast('Please select a cancellation reason', 'error');
                return;
            }

            try {
                this.store.setState({cancelSubmitting: true});
                await api(`/api/${this.siteSlug}/member/orders/${this.store.state.orderData.id}/cancel`, {
                    method: 'POST',
                    body: JSON.stringify({
                        reason,
                        notify_customer: document.getElementById('notifyCustomerCancel')?.checked ?? true,
                    }),
                });
                UI.toast('Order cancelled successfully', 'success');
                CancelModal.close();
                location.reload();
            } catch (err) {
                UI.toast(err.message ?? 'Failed to cancel order', 'error');
            } finally {
                this.store.setState({cancelSubmitting: false});
            }
        }
    }

    /* ─────────────────────────────────────────────────────────────
       Init
    ───────────────────────────────────────────────────────────── */
    window.orderApp = new OrderManager({
        siteSlug: '<?= \App\Framework\Support\SiteContext::slug() ?>',
    });
</script>
</body>
</html>
