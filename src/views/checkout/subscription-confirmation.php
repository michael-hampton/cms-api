<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Confirmed — YourStore</title>
    <style>
        *, *::before, *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --success: #059669;
            --success-light: #ecfdf5;
            --success-border: #6ee7b7;
            --danger: #ef4444;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --border: #e2e8f0;
            --bg: #f8fafc;
            --text: #1e293b;
            --muted: #64748b;
            --shadow: 0 1px 3px rgba(0, 0, 0, .1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, .1);
            --radius: .75rem;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
        }

        .container {
            max-width: 760px;
            margin: 0 auto;
            padding: 0 1.25rem;
        }

        /* ── Header ──────────────────────────────────────────── */
        .site-header {
            background: #fff;
            box-shadow: var(--shadow);
            padding: .875rem 0;
        }

        .site-header .inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.375rem;
            font-weight: 800;
            color: var(--primary);
            text-decoration: none;
        }

        /* ── Hero banner ─────────────────────────────────────── */
        .confirmation-hero {
            background: linear-gradient(135deg, #1e40af 0%, #2563eb 50%, #3b82f6 100%);
            color: #fff;
            padding: 3rem 1.25rem 4rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .confirmation-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Ccircle cx='30' cy='30' r='28'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat;
            pointer-events: none;
        }

        .checkmark-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .15);
            border: 3px solid rgba(255, 255, 255, .6);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.25rem;
            animation: pop .4s cubic-bezier(.175, .885, .32, 1.275) both;
        }

        @keyframes pop {
            from {
                transform: scale(.6);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .confirmation-hero h1 {
            font-size: 1.875rem;
            font-weight: 800;
            margin-bottom: .5rem;
            letter-spacing: -.02em;
        }

        .confirmation-hero p {
            font-size: 1rem;
            opacity: .85;
            max-width: 480px;
            margin: 0 auto;
        }

        /* ── Cards ───────────────────────────────────────────── */
        .cards-wrapper {
            padding-bottom: 3rem;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            margin-top: 20px;
        }

        .card {
            background: #fff;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: .625rem;
            padding: 1.125rem 1.5rem;
            border-bottom: 1px solid var(--border);
            background: var(--bg);
        }

        .card-header svg {
            color: var(--primary);
            flex-shrink: 0;
        }

        .card-header h2 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text);
        }

        .card-body {
            padding: 1.5rem;
        }

        /* ── Subscription plan card ──────────────────────────── */
        .plan-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .plan-icon {
            width: 52px;
            height: 52px;
            border-radius: .625rem;
            background: linear-gradient(135deg, var(--primary) 0%, #3b82f6 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: #fff;
        }

        .plan-name {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--text);
        }

        .plan-description {
            font-size: .875rem;
            color: var(--muted);
            margin-top: .1rem;
        }

        /* Status badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            font-size: .75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            padding: .25rem .625rem;
            border-radius: 99px;
        }

        .status-badge.active {
            background: var(--success-light);
            color: var(--success);
            border: 1px solid var(--success-border);
        }

        .status-badge.pending {
            background: var(--warning-light);
            color: #92400e;
            border: 1px solid #fcd34d;
        }

        /* ── Details grid ────────────────────────────────────── */
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .875rem 1.5rem;
        }

        @media (max-width: 540px) {
            .details-grid {
                grid-template-columns: 1fr;
            }
        }

        .detail-item label {
            display: block;
            font-size: .72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--muted);
            margin-bottom: .2rem;
        }

        .detail-item span {
            font-size: .9rem;
            font-weight: 600;
            color: var(--text);
        }

        /* ── Billing timeline ────────────────────────────────── */
        .timeline {
            list-style: none;
        }

        .timeline li {
            display: flex;
            align-items: flex-start;
            gap: .875rem;
            padding: .875rem 0;
            border-bottom: 1px dashed var(--border);
            position: relative;
        }

        .timeline li:last-child {
            border-bottom: none;
        }

        .tl-dot {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: .1rem;
        }

        .tl-dot.done {
            background: var(--success-light);
            color: var(--success);
        }

        .tl-dot.next {
            background: #eff6ff;
            color: var(--primary);
        }

        .tl-dot.later {
            background: var(--bg);
            color: var(--muted);
            border: 1px solid var(--border);
        }

        .tl-info strong {
            font-size: .9rem;
            display: block;
            color: var(--text);
        }

        .tl-info span {
            font-size: .8rem;
            color: var(--muted);
        }

        /* ── Delivery info ───────────────────────────────────── */
        .delivery-badge {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            font-size: .8rem;
            font-weight: 600;
            padding: .4rem .875rem;
            border-radius: 99px;
            border: 1px solid;
        }

        .delivery-badge.digital {
            background: #eff6ff;
            color: var(--primary);
            border-color: #bfdbfe;
        }

        .delivery-badge.print {
            background: #f0fdf4;
            color: var(--success);
            border-color: var(--success-border);
        }

        /* ── Address block ───────────────────────────────────── */
        .address-block {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: .5rem;
            padding: 1rem;
            font-size: .875rem;
            line-height: 1.7;
            color: var(--text);
        }

        /* ── Payment block ───────────────────────────────────── */
        .payment-row {
            display: flex;
            align-items: center;
            gap: .75rem;
        }

        .card-brand-badge {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: .375rem;
            padding: .25rem .625rem;
            font-size: .75rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text);
        }

        /* ── Price summary ───────────────────────────────────── */
        .price-summary {
            display: flex;
            flex-direction: column;
            gap: .5rem;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            font-size: .875rem;
            color: var(--muted);
        }

        .price-row.total {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text);
            padding-top: .75rem;
            border-top: 2px solid var(--border);
            margin-top: .25rem;
        }

        /* ── CTA actions ─────────────────────────────────────── */
        .actions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .75rem;
        }

        @media (max-width: 480px) {
            .actions-grid {
                grid-template-columns: 1fr;
            }
        }

        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            padding: .875rem 1.25rem;
            border-radius: .5rem;
            font-size: .9rem;
            font-weight: 700;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all .2s;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 2px 8px rgba(37, 99, 235, .3);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #fff;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn-secondary:hover {
            background: #eff6ff;
        }

        .btn-ghost {
            background: var(--bg);
            color: var(--muted);
            border: 1px solid var(--border);
        }

        .btn-ghost:hover {
            color: var(--text);
            background: #f1f5f9;
        }

        /* ── Email notice ─────────────────────────────────────── */
        .email-notice {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: 1rem 1.25rem;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: .5rem;
            font-size: .85rem;
            color: #1e40af;
        }

        .email-notice svg {
            flex-shrink: 0;
        }

        /* ── Footer ──────────────────────────────────────────── */
        .site-footer {
            background: #fff;
            border-top: 1px solid var(--border);
            padding: 1.5rem 0;
            text-align: center;
            color: var(--muted);
            font-size: .85rem;
        }
    </style>
</head>
<body>

<!-- Header -->
<header class="site-header">
    <div class="container">
        <div class="inner">
            <a href="/" class="logo">YourStore</a>
            <a href="/shop" style="font-size:.875rem; color:var(--muted); text-decoration:none;">Continue Shopping →</a>
        </div>
    </div>
</header>

<!-- Hero -->
<div class="confirmation-hero">
    <div class="container">
        <div class="checkmark-circle">
            <svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"
                 stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
        </div>
        <h1>Subscription Confirmed!</h1>
        <p>
            You're all set. Your
            <strong><?= htmlspecialchars($subscription->plan->name ?? $plan->name ?? 'subscription') ?></strong>
            is now active.
            <?php if (!empty($customerEmail)): ?>
                A confirmation has been sent to <strong><?= htmlspecialchars($customerEmail) ?></strong>.
            <?php endif; ?>
        </p>
    </div>
</div>

<!-- Cards -->
<div class="container">
    <div class="cards-wrapper">

        <!-- 1 · Plan summary ──────────────────────────────────── -->
        <div class="card">
            <div class="card-header">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                </svg>
                <h2>Your Subscription</h2>
            </div>
            <div class="card-body">
                <div class="plan-row">
                    <div class="plan-icon">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2">
                            <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                            <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="plan-name"><?= htmlspecialchars($plan->name ?? 'Subscription Plan') ?></div>
                        <?php if (!empty($plan->description)): ?>
                            <div class="plan-description"><?= htmlspecialchars($plan->description) ?></div>
                        <?php endif; ?>
                    </div>
                    <div style="margin-left:auto; flex-shrink:0">
                        <span class="status-badge active">
                            <svg width="9" height="9" viewBox="0 0 10 10"><circle cx="5" cy="5" r="5"
                                                                                  fill="currentColor"/></svg>
                            Active
                        </span>
                    </div>
                </div>

                <div class="details-grid">
                    <div class="detail-item">
                        <label>Subscription ID</label>
                        <span>#<?= htmlspecialchars((string)($subscriptionId ?? $subscription->id ?? '—')) ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Billing Period</label>
                        <span><?= htmlspecialchars(ucfirst($plan->billing_period ?? 'Monthly')) ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Start Date</label>
                        <span>
                            <?php
                            $startDate = $subscription->starts_at ?? $subscription->start_date ?? now();
                            echo is_string($startDate)
                                    ? date('F j, Y', strtotime($startDate))
                                    : (is_object($startDate) ? $startDate->format('F j, Y') : date('F j, Y'));
                            ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <label>Next Billing</label>
                        <span>
                            <?php
                            $nextBilling = $subscription->next_billing_date ?? $subscription->renews_at ?? null;
                            echo $nextBilling
                                    ? (is_string($nextBilling) ? date('F j, Y', strtotime($nextBilling)) : $nextBilling->format('F j, Y'))
                                    : '—';
                            ?>
                        </span>
                    </div>
                </div>

                <!-- Delivery type -->
                <?php

                $deliveryType = $subscription['delivery_type']
                        ?? $subscription->options['delivery_type']
                        ?? ($plan->getDeliveryOptions()[0] ?? '');
                $isDigital = strtolower($deliveryType) === 'digital';
                ?>
                <div style="margin-top:1.25rem; display:flex; align-items:center; gap:.75rem; flex-wrap:wrap;">
                    <span class="delivery-badge <?= $isDigital ? 'digital' : 'print' ?>">
                        <?php if ($isDigital): ?>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2">
                                <rect x="2" y="3" width="20" height="14" rx="2"/>
                                <path d="M8 21h8M12 17v4"/>
                            </svg>
                            Digital Access
                        <?php else: ?>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                <circle cx="12" cy="10" r="3"/>
                            </svg>
                            Physical Delivery
                        <?php endif; ?>
                    </span>

                    <?php if (!empty($subscription->issue_count) || !empty($plan->issue_count)): ?>
                        <span style="font-size:.8rem; color:var(--muted);">
                            <?= (int)($subscription->issue_count ?? $plan->issue_count) ?> issues included
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 2 · Billing timeline ─────────────────────────────── -->
        <div class="card">
            <div class="card-header">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                <h2>Billing Timeline</h2>
            </div>
            <div class="card-body">
                <ul class="timeline">
                    <li>
                        <div class="tl-dot done">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2.5">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                        </div>
                        <div class="tl-info">
                            <strong>Payment received</strong>
                            <span>
                                <?php
                                $paidAt = $order->created_at ?? $subscription->created_at ?? now();
                                echo is_string($paidAt) ? date('F j, Y', strtotime($paidAt)) : (is_object($paidAt) ? $paidAt->format('F j, Y') : date('F j, Y'));
                                ?>
                                ·
                                <?php
                                $amount = $order->total ?? $subscription->price ?? $plan->price ?? null;
                                echo $amount !== null ? '£' . number_format((float)$amount, 2) : '';
                                ?>
                            </span>
                        </div>
                    </li>

                    <?php if (!$isDigital): ?>
                        <li>
                            <div class="tl-dot next">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2">
                                    <path d="M5 12h14M12 5l7 7-7 7"/>
                                </svg>
                            </div>
                            <div class="tl-info">
                                <strong>First issue dispatched</strong>
                                <span>
                                <?php
                                $dispatchDate = $subscription->first_issue_date ?? null;
                                echo $dispatchDate
                                        ? (is_string($dispatchDate) ? date('F j, Y', strtotime($dispatchDate)) : $dispatchDate->format('F j, Y'))
                                        : 'Within 5–7 business days';
                                ?>
                            </span>
                            </div>
                        </li>
                    <?php endif; ?>

                    <li>
                        <div class="tl-dot later">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                        </div>
                        <div class="tl-info">
                            <strong>Next billing</strong>
                            <span>
                                <?php
                                echo $nextBilling
                                        ? (is_string($nextBilling) ? date('F j, Y', strtotime($nextBilling)) : $nextBilling->format('F j, Y'))
                                        : '—';
                                ?>
                                · auto-renews unless cancelled
                            </span>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <!-- 3 · Delivery / access details ────────────────────── -->
        <?php if ($isDigital && $plan->hasDigitalOption() && !empty($plan->digital_download_url)): ?>
            <div class="card" style="border: 2px solid #bfdbfe;">
                <div class="card-header" style="background:#eff6ff">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--primary)"
                         stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                    <h2 style="color:var(--primary)">Access Your Content</h2>
                </div>
                <div class="card-body" style="display:flex; flex-direction:column; gap:.875rem;">
                    <p style="font-size:.875rem; color:var(--muted);">
                        Your digital content is ready to access immediately.
                    </p>
                    <a href="<?= htmlspecialchars($plan->digital_download_url) ?>"
                       class="btn btn-primary" style="max-width:240px">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        Access Now
                    </a>
                </div>
            </div>
        <?php elseif (!$isDigital && !empty($shippingAddress)): ?>
            <div class="card">
                <div class="card-header">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                        <circle cx="12" cy="10" r="3"/>
                    </svg>
                    <h2>Delivery Address</h2>
                </div>
                <div class="card-body">
                    <div class="address-block">
                        <?php
                        $addr = $shippingAddress;
                        if (is_array($addr)) {
                            $lines = array_filter([
                                    ($addr['first_name'] ?? '') . ' ' . ($addr['last_name'] ?? ''),
                                    $addr['address'] ?? $addr['line1'] ?? null,
                                    $addr['address2'] ?? $addr['line2'] ?? null,
                                    $addr['city'] ?? null,
                                    $addr['state'] ?? null,
                                    $addr['postal_code'] ?? $addr['postcode'] ?? null,
                                    $addr['country'] ?? null,
                            ]);
                            echo implode('<br>', array_map('htmlspecialchars', $lines));
                        } elseif (is_object($addr)) {
                            echo nl2br(htmlspecialchars($addr->formatted ?? implode(', ', array_filter([
                                    $addr->address, $addr->city, $addr->postal_code, $addr->country
                            ]))));
                        }
                        ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- 4 · Payment summary ──────────────────────────────── -->
        <div class="card">
            <div class="card-header">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="1" y="4" width="22" height="16" rx="2"/>
                    <line x1="1" y1="10" x2="23" y2="10"/>
                </svg>
                <h2>Payment</h2>
            </div>
            <div class="card-body" style="display:flex; flex-direction:column; gap:1.125rem;">
                <!-- Card used -->
                <?php if (!empty($order->payment_method) || !empty($paymentMethod)): ?>
                    <?php
                    $pm = $order->payment_method ?? $paymentMethod;
                    $brand = is_array($pm) ? ($pm['brand'] ?? '') : (is_object($pm) ? ($pm->brand ?? '') : '');
                    $last4 = is_array($pm) ? ($pm['last4'] ?? '') : (is_object($pm) ? ($pm->last4 ?? '') : '');
                    ?>
                    <div class="payment-row">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--muted)"
                             stroke-width="2">
                            <rect x="1" y="4" width="22" height="16" rx="2"/>
                            <line x1="1" y1="10" x2="23" y2="10"/>
                        </svg>
                        <?php if ($brand): ?>
                            <span class="card-brand-badge"><?= htmlspecialchars(strtoupper($brand)) ?></span>
                        <?php endif; ?>
                        <?php if ($last4): ?>
                            <span style="font-size:.9rem; color:var(--text);">•••• <?= htmlspecialchars($last4) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Price breakdown -->
                <div class="price-summary">
                    <?php
                    $subTotal = $order->subtotal ?? $plan->price ?? null;
                    $taxAmt = $order->tax ?? null;
                    $shipAmt = $order->shipping ?? 0;
                    $totalAmt = $order->total ?? $subscription->price ?? null;
                    ?>
                    <?php if ($subTotal !== null): ?>
                        <div class="price-row">
                            <span>Plan price</span>
                            <span>£<?= number_format((float)$subTotal, 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($shipAmt > 0): ?>
                        <div class="price-row">
                            <span>Shipping</span>
                            <span>£<?= number_format((float)$shipAmt, 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($taxAmt !== null && $taxAmt > 0): ?>
                        <div class="price-row">
                            <span>Tax</span>
                            <span>£<?= number_format((float)$taxAmt, 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($totalAmt !== null): ?>
                        <div class="price-row total">
                            <span>Total paid</span>
                            <span>£<?= number_format((float)$totalAmt, 2) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 5 · Email confirmation notice ───────────────────── -->
        <?php if (!empty($customerEmail)): ?>
            <div class="email-notice">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                    <polyline points="22,6 12,13 2,6"/>
                </svg>
                A confirmation email with full details has been sent to
                <strong>&nbsp;<?= htmlspecialchars($customerEmail) ?></strong>.
            </div>
        <?php endif; ?>

        <!-- 6 · CTA actions ─────────────────────────────────── -->
        <div class="card">
            <div class="card-body">
                <div class="actions-grid">
                    <a href="/<?= htmlspecialchars(\App\Framework\Support\SiteContext::slug()) ?>/member/subscriptions"
                       class="btn btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                        </svg>
                        Manage Subscription
                    </a>
                    <a href="/shop" class="btn btn-secondary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2">
                            <circle cx="9" cy="21" r="1"/>
                            <circle cx="20" cy="21" r="1"/>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                        </svg>
                        Continue Shopping
                    </a>
                    <?php if (!empty($order->order_number ?? $orderId)): ?>
                        <a href="/order-confirmation?order_id=<?= htmlspecialchars((string)($order->order_number ?? $orderId)) ?>"
                           class="btn btn-ghost" style="grid-column: 1 / -1; justify-content:center">
                            View Order Details
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div><!-- /.cards-wrapper -->
</div>

<!-- Footer -->
<footer class="site-footer">
    <div class="container">
        &copy; <?= date('Y') ?> YourStore. All rights reserved. ·
        <a href="/help" style="color:var(--muted); text-decoration:none;">Need help?</a>
    </div>
</footer>

<script>
    /* Clear cart session after successful subscription confirmation */
    sessionStorage.removeItem('appliedVoucher');
    sessionStorage.removeItem('subscriptionStartDates');
</script>

</body>
</html>
