<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'My Account') ?> — Shop</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap"
          rel="stylesheet">
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --ink: #0f0f0f;
            --ink-soft: #3d3d3d;
            --ink-muted: #888;
            --surface: #f5f3ef;
            --white: #fff;
            --border: #e0ddd7;
            --border-soft: #ece9e3;
            --accent: #1a1a2e;
            --accent-light: #eeeef5;
            --green: #059669;
            --green-light: #d1fae5;
            --amber: #d97706;
            --amber-light: #fef3c7;
            --red: #dc2626;
            --red-light: #fee2e2;
            --blue: #2563eb;
            --blue-light: #dbeafe;
            --radius: 12px;
            --radius-sm: 8px;
            --radius-xs: 5px;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, .06);
            --shadow: 0 4px 16px rgba(0, 0, 0, .08);
            --shadow-lg: 0 8px 32px rgba(0, 0, 0, .12);
            --font-display: 'DM Serif Display', Georgia, serif;
            --font-body: 'DM Sans', system-ui, sans-serif;
            --transition: all .18s cubic-bezier(.4, 0, .2, 1);
            --nav-w: 240px;
        }

        body {
            font-family: var(--font-body);
            background: var(--surface);
            color: var(--ink);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
        }

        /* ── Top bar ─────────────────────────────────────────────── */
        .topbar {
            background: var(--white);
            border-bottom: 1px solid var(--border);
            height: 56px;
            display: flex;
            align-items: center;
            padding: 0 24px;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .topbar__back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--ink-muted);
            text-decoration: none;
            transition: var(--transition);
        }

        .topbar__back:hover {
            color: var(--ink);
        }

        .topbar__back svg {
            width: 14px;
            height: 14px;
        }

        .topbar__divider {
            width: 1px;
            height: 18px;
            background: var(--border);
        }

        .topbar__title {
            font-family: var(--font-display);
            font-size: 16px;
            color: var(--ink);
        }

        .topbar__member {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: var(--ink-soft);
        }

        .topbar__avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--accent);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 600;
            font-family: var(--font-display);
            flex-shrink: 0;
        }

        /* ── Shell ───────────────────────────────────────────────── */
        .shell {
            display: grid;
            grid-template-columns: var(--nav-w) 1fr;
            min-height: calc(100vh - 56px);
            max-width: 1320px;
            margin: 0 auto;
            padding: 32px 24px 64px;
            gap: 32px;
            align-items: start;
        }

        @media (max-width: 800px) {
            .shell {
                grid-template-columns: 1fr;
                padding: 16px;
                gap: 20px;
            }

            .account-nav {
                display: none;
            }

            .mobile-nav {
                display: flex;
            }
        }

        /* ── Sidebar nav ─────────────────────────────────────────── */
        .account-nav {
            position: sticky;
            top: 80px;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
        }

        .account-nav__header {
            padding: 20px 20px 16px;
            border-bottom: 1px solid var(--border-soft);
        }

        .account-nav__name {
            font-family: var(--font-display);
            font-size: 17px;
            color: var(--ink);
            margin-bottom: 2px;
        }

        .account-nav__email {
            font-size: 12px;
            color: var(--ink-muted);
        }

        .account-nav__links {
            padding: 10px 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 20px;
            font-size: 14px;
            color: var(--ink-soft);
            text-decoration: none;
            transition: var(--transition);
            border-left: 2px solid transparent;
        }

        .nav-link:hover {
            color: var(--ink);
            background: var(--surface);
        }

        .nav-link.active {
            color: var(--ink);
            font-weight: 600;
            border-left-color: var(--ink);
            background: var(--surface);
        }

        .nav-link svg {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
            opacity: .7;
        }

        .nav-link.active svg {
            opacity: 1;
        }

        .nav-link__badge {
            margin-left: auto;
            background: var(--accent);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 100px;
        }

        /* ── Mobile nav strip ────────────────────────────────────── */
        .mobile-nav {
            display: none;
            gap: 4px;
            overflow-x: auto;
            padding-bottom: 4px;
            scrollbar-width: none;
            margin-bottom: 4px;
        }

        .mobile-nav::-webkit-scrollbar {
            display: none;
        }

        .mobile-nav-link {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 100px;
            border: 1px solid var(--border);
            background: var(--white);
            font-size: 13px;
            font-weight: 500;
            color: var(--ink-soft);
            text-decoration: none;
            white-space: nowrap;
            transition: var(--transition);
        }

        .mobile-nav-link svg {
            width: 14px;
            height: 14px;
        }

        .mobile-nav-link.active {
            background: var(--ink);
            color: #fff;
            border-color: var(--ink);
        }

        /* ── Page content area ───────────────────────────────────── */
        .page-content {
            min-width: 0;
        }

        /* ── Shared card ─────────────────────────────────────────── */
        .card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
        }

        .card__header {
            padding: 20px 24px 16px;
            border-bottom: 1px solid var(--border-soft);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .card__title {
            font-family: var(--font-display);
            font-size: 18px;
            color: var(--ink);
        }

        .card__body {
            padding: 24px;
        }

        .card__footer {
            padding: 14px 24px;
            border-top: 1px solid var(--border-soft);
            background: var(--surface);
        }

        /* ── Status badges ───────────────────────────────────────── */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            padding: 3px 10px;
            border-radius: 100px;
        }

        .badge::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
            opacity: .7;
        }

        .badge--active {
            background: var(--green-light);
            color: var(--green);
        }

        .badge--expired {
            background: var(--border-soft);
            color: var(--ink-muted);
        }

        .badge--cancelled {
            background: var(--red-light);
            color: var(--red);
        }

        .badge--past_due {
            background: var(--amber-light);
            color: var(--amber);
        }

        .badge--pending {
            background: var(--blue-light);
            color: var(--blue);
        }

        .badge--paid {
            background: var(--green-light);
            color: var(--green);
        }

        .badge--failed {
            background: var(--red-light);
            color: var(--red);
        }

        .badge--completed {
            background: var(--green-light);
            color: var(--green);
        }

        .badge--refunded {
            background: var(--amber-light);
            color: var(--amber);
        }

        /* ── Empty state ─────────────────────────────────────────── */
        .empty-state {
            text-align: center;
            padding: 60px 24px;
        }

        .empty-state__icon {
            font-size: 40px;
            margin-bottom: 14px;
            opacity: .5;
        }

        .empty-state__title {
            font-family: var(--font-display);
            font-size: 20px;
            color: var(--ink);
            margin-bottom: 6px;
        }

        .empty-state__sub {
            font-size: 14px;
            color: var(--ink-muted);
            margin-bottom: 20px;
        }

        /* ── Buttons ─────────────────────────────────────────────── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 18px;
            border-radius: var(--radius-sm);
            font-family: var(--font-body);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: var(--transition);
            border: none;
            line-height: 1;
        }

        .btn--primary {
            background: var(--ink);
            color: #fff;
        }

        .btn--primary:hover {
            background: var(--ink-soft);
        }

        .btn--ghost {
            background: transparent;
            color: var(--ink-soft);
            border: 1px solid var(--border);
        }

        .btn--ghost:hover {
            background: var(--surface);
            color: var(--ink);
        }

        .btn--danger {
            background: var(--red-light);
            color: var(--red);
        }

        .btn--danger:hover {
            background: var(--red);
            color: #fff;
        }

        .btn--sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        /* ── Page heading ────────────────────────────────────────── */
        .page-heading {
            margin-bottom: 24px;
        }

        .page-heading__title {
            font-family: var(--font-display);
            font-size: clamp(24px, 3vw, 32px);
            line-height: 1.1;
            margin-bottom: 4px;
        }

        .page-heading__sub {
            font-size: 14px;
            color: var(--ink-muted);
        }

        /* ── Modal overlay ───────────────────────────────────────── */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .45);
            backdrop-filter: blur(3px);
            z-index: 200;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            opacity: 0;
            pointer-events: none;
            transition: opacity .22s ease;
        }

        .modal-overlay.open {
            opacity: 1;
            pointer-events: auto;
        }

        .modal {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 480px;
            overflow: hidden;
            transform: translateY(12px);
            transition: transform .22s ease;
        }

        .modal-overlay.open .modal {
            transform: translateY(0);
        }

        .modal__header {
            padding: 24px 24px 16px;
            border-bottom: 1px solid var(--border-soft);
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .modal__title {
            font-family: var(--font-display);
            font-size: 20px;
            line-height: 1.2;
        }

        .modal__close {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--ink-muted);
            padding: 4px;
            border-radius: 4px;
            line-height: 1;
            font-size: 18px;
            transition: var(--transition);
            flex-shrink: 0;
        }

        .modal__close:hover {
            color: var(--ink);
            background: var(--surface);
        }

        .modal__body {
            padding: 24px;
        }

        .modal__footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border-soft);
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        /* ── Step indicator ──────────────────────────────────────── */
        .steps {
            display: flex;
            align-items: center;
            gap: 0;
            margin-bottom: 24px;
        }

        .step {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 12px;
            font-weight: 600;
            color: var(--ink-muted);
        }

        .step.active {
            color: var(--ink);
        }

        .step.done {
            color: var(--green);
        }

        .step__num {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            border: 1.5px solid currentColor;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            flex-shrink: 0;
        }

        .step.done .step__num {
            background: var(--green);
            border-color: var(--green);
            color: #fff;
        }

        .step.active .step__num {
            background: var(--ink);
            border-color: var(--ink);
            color: #fff;
        }

        .step__divider {
            flex: 1;
            height: 1px;
            background: var(--border);
            margin: 0 8px;
        }
    </style>
</head>
<body>

<!-- Top bar -->
<div class="topbar">
    <a href="/subscriptions" class="topbar__back">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Shop
    </a>
    <div class="topbar__divider"></div>
    <span class="topbar__title">My Account</span>
    <div class="topbar__member">
        <?= htmlspecialchars($member->name ?? $member->email ?? '') ?>
        <div class="topbar__avatar">
            <?= strtoupper(substr($member->name ?? $member->email ?? 'M', 0, 1)) ?>
        </div>
    </div>
</div>

<!-- Mobile nav strip -->
<div style="padding: 12px 16px 0; max-width:1320px; margin:0 auto;">
    <nav class="mobile-nav">
        <?php $t = $active_tab ?? 'overview'; ?>
        <a href="/account" class="mobile-nav-link <?= $t === 'overview' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="7" height="7"/>
                <rect x="14" y="3" width="7" height="7"/>
                <rect x="3" y="14" width="7" height="7"/>
                <rect x="14" y="14" width="7" height="7"/>
            </svg>
            Overview
        </a>
        <a href="/account/subscriptions" class="mobile-nav-link <?= $t === 'subscriptions' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
            Subscriptions
        </a>
        <a href="/account/orders" class="mobile-nav-link <?= $t === 'orders' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/>
                <line x1="3" y1="6" x2="21" y2="6"/>
                <path d="M16 10a4 4 0 01-8 0"/>
            </svg>
            Orders
        </a>
        <a href="/account/billing" class="mobile-nav-link <?= $t === 'billing' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="1" y="4" width="22" height="16" rx="2"/>
                <line x1="1" y1="10" x2="23" y2="10"/>
            </svg>
            Billing
        </a>
    </nav>
</div>

<div class="shell">
    <!-- Sidebar nav -->
    <aside class="account-nav">
        <div class="account-nav__header">
            <div class="account-nav__name"><?= htmlspecialchars($member->name ?? 'Account') ?></div>
            <div class="account-nav__email"><?= htmlspecialchars($member->email ?? '') ?></div>
        </div>
        <nav class="account-nav__links">
            <?php $t = $active_tab ?? 'overview'; ?>
            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/subscriptions/onetime/account"
               class="nav-link <?= $t === 'overview' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"/>
                    <rect x="14" y="3" width="7" height="7"/>
                    <rect x="3" y="14" width="7" height="7"/>
                    <rect x="14" y="14" width="7" height="7"/>
                </svg>
                Overview
            </a>
            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/subscriptions/onetime/account/subscriptions"
               class="nav-link <?= $t === 'subscriptions' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
                Subscriptions
                <?php if (($subscription_summary['active'] ?? 0) > 0): ?>
                    <span class="nav-link__badge"><?= $subscription_summary['active'] ?></span>
                <?php endif; ?>
            </a>
            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/subscriptions/onetime/account/orders"
               class="nav-link <?= $t === 'orders' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/>
                    <line x1="3" y1="6" x2="21" y2="6"/>
                    <path d="M16 10a4 4 0 01-8 0"/>
                </svg>
                Orders
            </a>
            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/subscriptions/onetime/account/billing"
               class="nav-link <?= $t === 'billing' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="1" y="4" width="22" height="16" rx="2"/>
                    <line x1="1" y1="10" x2="23" y2="10"/>
                </svg>
                Billing
            </a>
        </nav>
    </aside>