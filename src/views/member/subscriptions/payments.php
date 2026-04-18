<?php
/**
 * @var \App\Models\Member $member
 * @var \App\Models\Site $site
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History — <?= htmlspecialchars($site->name) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap"
          rel="stylesheet">
    <style>
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            /* Light Theme Palette */
            --ink: #1a1c20; /* Deep charcoal for text */
            --ink-2: #ffffff; /* Pure white for cards */
            --ink-3: #f5f2ee; /* Soft grey-beige for headers/inputs */
            --mist: #fcfaf7; /* Main page background */
            --mist-2: #efedea; /* Subtle accents */
            --accent: #9b6f3b; /* Deep gold/bronze */
            --accent-2: #7a582e;
            --ok: #2d7a58; /* Darker green for contrast */
            --fail: #a93226; /* Darker red */
            --warn: #b38f00;
            --text: #1a1c20; /* Primary text */
            --text-dim: #626770; /* Muted text */
            --border: rgba(0, 0, 0, 0.08); /* Darker borders for light bg */
            --radius: 10px;
            --serif: 'Instrument Serif', Georgia, serif;
            --sans: 'DM Sans', system-ui, sans-serif;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        body {
            font-family: var(--sans);
            background: var(--mist); /* Switched to light background */
            color: var(--text);
            min-height: 100vh;
            /* Subtle grid texture - adapted for light mode */
            background-image: linear-gradient(var(--border) 1px, transparent 1px),
            linear-gradient(90deg, var(--border) 1px, transparent 1px);
            background-size: 60px 60px;
        }

        /* ── Layout ── */
        .shell {
            max-width: 1120px;
            margin: 0 auto;
            padding: 48px 24px 80px;
        }

        /* ── Page header ── */
        .page-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            border-bottom: 1px solid var(--border);
            padding-bottom: 28px;
            margin-bottom: 40px;
        }

        .page-head-left {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .eyebrow {
            font-family: var(--sans);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: var(--accent);
        }

        .page-title {
            font-family: var(--serif);
            font-size: clamp(28px, 4vw, 44px);
            font-weight: 400;
            font-style: italic;
            color: var(--text);
            line-height: 1.1;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-dim);
            text-decoration: none;
            border: 1px solid var(--border);
            background: var(--ink-2);
            padding: 8px 16px;
            border-radius: var(--radius);
            transition: all .2s;
        }

        .back-link:hover {
            color: var(--text);
            border-color: var(--accent);
            background: var(--mist);
        }

        /* ── Stat strip ── */
        .stats-strip {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 36px;
        }

        .stat-tile {
            background: var(--ink-2);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 22px 20px;
            position: relative;
            overflow: hidden;
            opacity: 0;
            transform: translateY(10px);
            transition: opacity .4s, transform .4s;
            box-shadow: var(--shadow-sm);
        }

        .stat-tile.visible {
            opacity: 1;
            transform: none;
        }

        .stat-tile::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(200, 169, 110, .04) 0%, transparent 60%);
        }
        .stat-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--text-dim);
            margin-bottom: 10px;
        }
        .stat-value {
            font-family: var(--serif);
            font-size: 28px;
            font-style: italic;
            color: var(--text);
            line-height: 1;
        }

        .stat-value.ok {
            color: var(--ok);
        }

        .stat-value.fail {
            color: var(--fail);
        }

        /* ── Table card ── */
        .table-card {
            background: var(--ink-2);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }

        .table-card-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
        }

        .table-card-title {
            font-size: 13px;
            font-weight: 600;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--text-dim);
        }

        .record-count {
            font-size: 12px;
            color: var(--text-dim);
            background: var(--mist-2);
            border-radius: 20px;
            padding: 3px 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        thead th {
            padding: 12px 20px;
            text-align: left;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--text-dim);
            background: var(--ink-3);
            white-space: nowrap;
        }

        tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background .15s;
        }

        tbody tr:last-child {
            border-bottom: none;
        }

        tbody tr:hover {
            background: rgba(200, 169, 110, .03);
        }

        td {
            padding: 16px 20px;
            vertical-align: middle;
            color: var(--text);
        }

        /* ── Status pill ── */
        .pill {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .pill-ok {
            background: rgba(61, 153, 112, .1);
            color: #2d7a58;
            border: 1px solid rgba(61, 153, 112, .2);
        }

        .pill-fail {
            background: rgba(192, 57, 43, .08);
            color: #c0392b;
            border: 1px solid rgba(192, 57, 43, .15);
        }

        .pill-warn {
            background: rgba(212, 172, 13, .1);
            color: #9b7e00;
            border: 1px solid rgba(212, 172, 13, .2);
        }

        .pill-dim {
            background: rgba(0, 0, 0, .04);
            color: var(--text-dim);
            border: 1px solid var(--border);
        }

        /* ── Invoice link ── */
        .invoice-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            font-weight: 600;
            color: var(--accent);
            text-decoration: none;
            border-bottom: 1px solid transparent;
            transition: border-color .2s;
        }

        .invoice-link:hover {
            border-color: var(--accent);
        }

        /* ── Empty state ── */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 72px 24px;
            gap: 12px;
            color: var(--text-dim);
        }

        .empty-icon {
            font-size: 48px;
            opacity: .5;
        }

        .empty-title {
            font-family: var(--serif);
            font-style: italic;
            font-size: 22px;
            color: var(--text);
        }

        .empty-body {
            font-size: 14px;
        }

        /* ── Loading skeleton ── */
        @keyframes shimmer {
            0% {
                background-position: -600px 0;
            }
            100% {
                background-position: 600px 0;
            }
        }

        .skeleton {
            background: linear-gradient(90deg, var(--mist-2) 25%, var(--ink-3) 50%, var(--mist-2) 75%);
            background-size: 600px 100%;
            animation: shimmer 1.4s infinite linear;
            border-radius: 4px;
            height: 14px;
        }

        .skeleton-row td {
            padding: 18px 20px;
        }

        /* ── Toast ── */
        .toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            z-index: 9999;
        }

        .toast {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: var(--radius);
            font-size: 13px;
            font-weight: 500;
            background: var(--ink-2);
            border: 1px solid var(--border);
            color: var(--text);
            box-shadow: var(--shadow-md);
            animation: slideIn .25s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: none;
            }
        }

        .toast.success .toast-icon {
            color: var(--ok);
        }

        .toast.error .toast-icon {
            color: var(--fail);
        }

        .toast.info .toast-icon {
            color: var(--accent);
        }

        .toast-close {
            margin-left: auto;
            background: none;
            border: none;
            color: var(--text-dim);
            cursor: pointer;
            font-size: 16px;
        }

        @media (max-width: 768px) {
            .stats-strip {
                grid-template-columns: repeat(2, 1fr);
            }

            .page-head {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>

@include('member._header')

<main class="shell" id="paymentsApp"
      data-api-url="/api/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscription-payments"
      data-site-slug="<?= htmlspecialchars(\App\Framework\Support\SiteContext::slug()) ?>">

    <div class="page-head">
        <div class="page-head-left">
            <span class="eyebrow">Billing</span>
            <h1 class="page-title">Payment History</h1>
        </div>
        <a class="back-link" href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscriptions">
            ← Subscriptions
        </a>
    </div>

    <!-- Stats strip rendered by JS -->
    <div class="stats-strip" id="statsStrip">
        <?php for ($i = 0; $i < 4; $i++): ?>
            <div class="stat-tile">
                <div class="stat-label">
                    <div class="skeleton" style="width:70px"></div>
                </div>
                <div class="stat-value">
                    <div class="skeleton" style="width:50px;height:28px;margin-top:4px"></div>
                </div>
            </div>
        <?php endfor; ?>
    </div>

    <!-- Table rendered by JS -->
    <div class="table-card" id="tableCard">
        <div class="table-card-head">
            <span class="table-card-title">All Payments</span>
            <span class="record-count" id="recordCount">—</span>
        </div>
        <table>
            <thead>
            <tr>
                <th>Date</th>
                <th>Description</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Method</th>
                <th>Transaction ID</th>
                <th></th>
            </tr>
            </thead>
            <tbody id="paymentsBody">
            <?php for ($i = 0; $i < 5; $i++): ?>
                <tr class="skeleton-row">
                    <?php for ($j = 0; $j < 7; $j++): ?>
                        <td>
                            <div class="skeleton" style="width:<?= [80, 130, 70, 60, 55, 110, 90][$j] ?>px"></div>
                        </td>
                    <?php endfor; ?>
                </tr>
            <?php endfor; ?>
            </tbody>
        </table>
    </div>
</main>

<script>
    /**
     * PaymentsPage — drives the Payment History view.
     * All DOM construction goes through app-core UI helpers.
     */
    class PaymentsPage {
        /** @param {HTMLElement} root  The #paymentsApp shell element */
        constructor(root) {
            this.root = root;
            this.apiUrl = root.dataset.apiUrl;
            this.siteSlug = root.dataset.siteSlug;

            this.statsStrip = document.getElementById('statsStrip');
            this.paymentsBody = document.getElementById('paymentsBody');
            this.recordCount = document.getElementById('recordCount');
        }

        async init() {
            try {
                const res = await api(this.apiUrl);
                this.renderStats(res.data.paymentSummary);
                this.renderTable(res.data.payments);
            } catch (err) {
                UI.toast(err.message || 'Failed to load payments', 'error');
                this.renderError();
            }
        }

        // ── Stats ──────────────────────────────────────────────────────────────

        renderStats(summary) {
            const fmt = (n) => Number(n).toLocaleString('en-GB', {minimumFractionDigits: 2});

            const tiles = [
                {
                    label: 'Total Payments',
                    value: String(summary.total_count),
                    cls: '',
                },
                {
                    label: 'Total Paid',
                    value: `${summary.currency} ${fmt(summary.total_paid)}`,
                    cls: '',
                },
                {
                    label: 'Successful',
                    value: String(summary.successful_count),
                    cls: 'ok',
                },
                {
                    label: 'Failed',
                    value: String(summary.failed_count),
                    cls: summary.failed_count > 0 ? 'fail' : '',
                },
            ];

            UI.render(this.statsStrip, tiles.map((t, i) => {
                const tile = UI.el('div', {className: 'stat-tile'}, [
                    UI.el('div', {className: 'stat-label'}, [t.label]),
                    UI.el('div', {className: `stat-value ${t.cls}`}, [t.value]),
                ]);
                // Stagger-in animation
                setTimeout(() => tile.classList.add('visible'), i * 80);
                return tile;
            }));
        }

        // ── Table ──────────────────────────────────────────────────────────────

        renderTable(payments) {
            UI.text(this.recordCount, `${payments.length} record${payments.length !== 1 ? 's' : ''}`);

            if (!payments.length) {
                UI.render(
                    this.paymentsBody.parentElement,  // replace whole table
                    this.emptyState(),
                );
                return;
            }

            UI.render(this.paymentsBody, payments.map(p => this.paymentRow(p)));
        }

        paymentRow(payment) {
            const siteSlug = this.siteSlug;

            const statusMap = {
                completed: ['pill-ok', 'Completed'],
                failed: ['pill-fail', 'Failed'],
                pending: ['pill-warn', 'Pending'],
                refunded: ['pill-dim', 'Refunded'],
            };
            const [pillCls, pillLabel] = statusMap[payment.status?.toLowerCase()] ?? ['pill-dim', payment.status ?? '—'];

            const invoiceCell = payment.status === 'completed'
                ? UI.el('a', {
                    className: 'invoice-link',
                    href: `/${siteSlug}/member/invoices/${payment.id}/download`,
                }, [
                    UI.rawEl(`<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>`),
                    'Invoice',
                ])
                : document.createTextNode('—');

            return UI.el('tr', {}, [
                UI.el('td', {}, [UI.formatDate(payment.created_at)]),
                UI.el('td', {}, [payment.subscription_id ? 'Subscription Payment' : 'One-time Payment']),
                UI.el('td', {style: 'font-weight:600;font-variant-numeric:tabular-nums'}, [
                    `${payment.currency} ${Number(payment.amount).toFixed(2)}`,
                ]),
                UI.el('td', {}, [UI.el('span', {className: `pill ${pillCls}`}, [pillLabel])]),
                UI.el('td', {}, [payment.payment_method ? this.capitalize(payment.payment_method) : '—']),
                UI.el('td', {style: 'font-family:monospace;font-size:12px;color:var(--text-dim)'}, [
                    payment.transaction_id ?? '—',
                ]),
                UI.el('td', {}, [invoiceCell]),
            ]);
        }

        emptyState() {
            return UI.emptyState({
                icon: '💳',
                title: 'No payments yet',
                body: 'Your payment history will appear here once you have an active subscription.',
            });
        }

        renderError() {
            UI.render(this.paymentsBody, [
                UI.el('tr', {}, [
                    UI.el('td', {colspan: '7', style: 'text-align:center;padding:40px;color:var(--text-dim)'}, [
                        'Unable to load payments — please refresh the page.',
                    ]),
                ]),
            ]);
        }

        // ── Helpers ────────────────────────────────────────────────────────────

        capitalize(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const root = document.getElementById('paymentsApp');
        if (root) new PaymentsPage(root).init();
    });
</script>
</body>
</html>