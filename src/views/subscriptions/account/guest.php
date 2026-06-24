<?php
/**
 * Guest-facing PressStack account shell.
 *
 * Rendered when a visitor opens any PressStack account page without an existing
 * member session or member token. The page stays visible, but interaction is
 * blocked by the account lookup modal.
 */

$pageTitle = $page_title ?? 'My Account';
$activeTab = $active_tab ?? 'overview';
$redirect = $account_login_redirect ?? ($_SERVER['REQUEST_URI'] ?? '/press-stack/account');
$error = $account_login_error ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(\App\Framework\Security\Csrf::getToken()) ?>">
    <title><?= htmlspecialchars($pageTitle) ?> — PressStack</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Instrument+Sans:wght@300;400;500;600&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/css/subscription-account.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --ink: #0d0d0f;
            --ink-soft: #3a3840;
            --ink-muted: #8a8795;
            --paper: #f8f6f1;
            --white: #ffffff;
            --border: #dedad2;
            --border-soft: #eae7e0;
            --gold: #b8860b;
            --gold-light: #fdf6e3;
            --gold-mid: #e8c96d;
            --red: #c0392b;
            --red-light: #fde8e6;
            --radius: 14px;
            --radius-sm: 9px;
            --shadow-xs: 0 1px 2px rgba(13, 13, 15, .05);
            --shadow-lg: 0 10px 40px rgba(13, 13, 15, .14);
            --font-display: 'Playfair Display', Georgia, serif;
            --font-body: 'Instrument Sans', system-ui, sans-serif;
            --transition: all .18s cubic-bezier(.4, 0, .2, 1);
        }
        body {
            font-family: var(--font-body);
            background: var(--paper);
            color: var(--ink);
            min-height: 100vh;
            line-height: 1.6;
        }
        .site-header {
            background: var(--ink);
            height: 64px;
            display: flex;
            align-items: center;
            padding: 0 28px;
            gap: 20px;
            position: sticky;
            top: 0;
            z-index: 200;
        }
        .header-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .header-brand__icon {
            width: 34px;
            height: 34px;
            background: var(--gold);
            border-radius: 7px;
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
        }
        .header-brand__icon::before,
        .header-brand__icon::after,
        .header-brand__icon-inner {
            content: '';
            position: absolute;
            left: 6px;
            height: 2px;
            background: var(--ink);
            border-radius: 2px;
        }
        .header-brand__icon::before { top: 10px; right: 10px; }
        .header-brand__icon::after { top: 16px; right: 6px; }
        .header-brand__icon-inner { bottom: 10px; right: 14px; }
        .header-brand__wordmark { display: flex; flex-direction: column; line-height: 1; }
        .header-brand__name {
            font-family: var(--font-display);
            font-size: 19px;
            font-weight: 700;
            color: #fff;
            letter-spacing: -.02em;
        }
        .header-brand__name em { color: var(--gold-mid); font-style: italic; }
        .header-brand__tagline {
            font-size: 9px;
            font-weight: 500;
            letter-spacing: .18em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .35);
            margin-top: 1px;
        }
        .header-back {
            color: rgba(255, 255, 255, .45);
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .header-section-label {
            color: rgba(255, 255, 255, .48);
            font-size: 13px;
            font-weight: 500;
        }
        .header-member {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255, 255, 255, .6);
            font-size: 13px;
        }
        .header-member__avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--gold) 0%, #8b6914 100%);
            color: var(--ink);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--font-display);
            font-weight: 700;
            border: 2px solid rgba(255, 255, 255, .15);
        }
        .shell {
            display: grid;
            grid-template-columns: 256px 1fr;
            min-height: calc(100vh - 64px);
            max-width: 1340px;
            margin: 0 auto;
            padding: 36px 28px 80px;
            gap: 36px;
            align-items: start;
            filter: blur(1px);
            pointer-events: none;
            user-select: none;
        }
        .account-nav, .card, .stat-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-xs);
            overflow: hidden;
        }
        .account-nav__header {
            padding: 22px;
            background: linear-gradient(160deg, var(--ink) 0%, #1e1b3a 100%);
            color: #fff;
        }
        .account-nav__name { font-family: var(--font-display); font-size: 17px; }
        .account-nav__email { font-size: 12px; color: rgba(255,255,255,.45); }
        .account-nav__links { padding: 8px 0; }
        .nav-link {
            display: flex;
            padding: 11px 22px;
            font-size: 13.5px;
            font-weight: 500;
            color: var(--ink-soft);
            text-decoration: none;
            border-left: 2.5px solid transparent;
        }
        .nav-link.active { background: var(--gold-light); border-left-color: var(--gold); color: var(--ink); font-weight: 600; }
        .account-nav__footer { padding: 14px 22px; border-top: 1px solid var(--border-soft); background: var(--paper); }
        .account-nav__footer-label { font-size: 10px; font-weight: 600; letter-spacing: .12em; text-transform: uppercase; color: var(--ink-muted); }
        .page-heading { margin-bottom: 28px; }
        .page-heading__eyebrow { font-size: 10.5px; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--gold); margin-bottom: 6px; }
        .page-heading__title { font-family: var(--font-display); font-size: clamp(26px, 3.5vw, 36px); line-height: 1.1; margin-bottom: 5px; }
        .page-heading__sub { font-size: 14px; color: var(--ink-muted); }
        .overview-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-card { padding: 22px; position: relative; }
        .stat-card::after { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 2.5px; background: linear-gradient(90deg, var(--gold) 0%, transparent 80%); }
        .stat-card__label { font-size: 10.5px; font-weight: 600; letter-spacing: .1em; text-transform: uppercase; color: var(--ink-muted); margin-bottom: 10px; }
        .stat-card__value { font-family: var(--font-display); font-size: 40px; line-height: 1; color: var(--ink); }
        .stat-card__sub { font-size: 12px; color: var(--ink-muted); margin-top: 5px; }
        .card__header { padding: 18px 24px 16px; border-bottom: 1px solid var(--border-soft); }
        .card__title { font-family: var(--font-display); font-size: 18px; }
        .card__body { padding: 36px 24px; color: var(--ink-muted); }
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(13, 13, 15, .58);
            backdrop-filter: blur(5px);
            z-index: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .modal {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 492px;
            overflow: hidden;
        }
        .modal__header {
            padding: 24px 24px 16px;
            background: linear-gradient(160deg, var(--ink) 0%, #1e1b3a 100%);
            color: #fff;
        }
        .modal__title { font-family: var(--font-display); font-size: 23px; line-height: 1.2; }
        .modal__body { padding: 24px; }
        .modal__body p { color: var(--ink-muted); font-size: 14px; margin-bottom: 18px; }
        .form-field { display: flex; flex-direction: column; gap: 7px; margin-bottom: 16px; }
        .form-field label { font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--ink-soft); }
        .form-field input {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 12px 13px;
            font: inherit;
            color: var(--ink);
            background: #fff;
        }
        .form-field input:focus { outline: 2px solid rgba(184, 134, 11, .22); border-color: var(--gold); }
        .alert {
            padding: 11px 13px;
            border-radius: var(--radius-sm);
            background: var(--red-light);
            color: var(--red);
            font-size: 13px;
            margin-bottom: 14px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 11px 18px;
            border-radius: var(--radius-sm);
            border: none;
            font: inherit;
            font-size: 13.5px;
            font-weight: 700;
            cursor: pointer;
            background: var(--ink);
            color: #fff;
        }
        .modal__hint { margin-top: 13px; color: var(--ink-muted); font-size: 12.5px; }
        @media (max-width: 860px) {
            .site-header { padding: 0 16px; gap: 12px; }
            .header-back, .header-section-label { display: none; }
            .shell { grid-template-columns: 1fr; padding: 24px 16px 60px; }
            .account-nav { display: none; }
            .overview-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<header class="site-header">
    <a href="/press-stack" class="header-brand">
        <div class="header-brand__icon" aria-hidden="true"><span class="header-brand__icon-inner"></span></div>
        <div class="header-brand__wordmark">
            <span class="header-brand__name">Press<em>Stack</em></span>
            <span class="header-brand__tagline">Publishing Platform</span>
        </div>
    </a>
    <a href="/press-stack" class="header-back">Back to Shop</a>
    <span class="header-section-label">My Account</span>
    <div class="header-member">
        <span>Guest</span>
        <div class="header-member__avatar">?</div>
    </div>
</header>

<div class="shell" aria-hidden="true">
    <aside class="account-nav">
        <div class="account-nav__header">
            <div class="account-nav__name">Account</div>
            <div class="account-nav__email">Sign in to continue</div>
        </div>
        <nav class="account-nav__links">
            <a href="/press-stack/account" class="nav-link <?= $activeTab === 'overview' ? 'active' : '' ?>">Overview</a>
            <a href="/press-stack/account/subscriptions" class="nav-link <?= $activeTab === 'subscriptions' ? 'active' : '' ?>">Subscriptions</a>
            <a href="/press-stack/account/orders" class="nav-link <?= $activeTab === 'orders' ? 'active' : '' ?>">Orders</a>
            <a href="/press-stack/account/billing" class="nav-link <?= $activeTab === 'billing' ? 'active' : '' ?>">Billing</a>
        </nav>
        <div class="account-nav__footer"><span class="account-nav__footer-label">PressStack Member</span></div>
    </aside>

    <main class="page-content">
        <div class="page-heading">
            <div class="page-heading__eyebrow">Dashboard</div>
            <h1 class="page-heading__title"><?= htmlspecialchars($pageTitle) ?></h1>
            <p class="page-heading__sub">Enter your email address to load your PressStack account.</p>
        </div>

        <div class="overview-grid">
            <div class="stat-card"><div class="stat-card__label">Active subscriptions</div><div class="stat-card__value">—</div><div class="stat-card__sub">Sign in to view</div></div>
            <div class="stat-card"><div class="stat-card__label">Orders placed</div><div class="stat-card__value">—</div><div class="stat-card__sub">Sign in to view</div></div>
            <div class="stat-card"><div class="stat-card__label">Member since</div><div class="stat-card__value" style="font-size:22px; padding-top:8px;">—</div></div>
        </div>

        <div class="card">
            <div class="card__header"><span class="card__title">Account details</span></div>
            <div class="card__body">Your subscriptions, orders and billing information will appear here once we find your account.</div>
        </div>
    </main>
</div>

<div class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="press-stack-account-login-title">
    <form class="modal" method="post" action="/press-stack/account/login">
        <div class="modal__header">
            <h2 class="modal__title" id="press-stack-account-login-title">Find your PressStack account</h2>
        </div>
        <div class="modal__body">
            <p>Enter the email address used for your subscription or order. We’ll find the account, sign you in, and bring you back to this page.</p>
            <?php if ($error): ?>
                <div class="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Framework\Security\Csrf::getToken()) ?>">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
            <div class="form-field">
                <label for="account-email">Email address</label>
                <input id="account-email" name="email" type="email" autocomplete="email" required autofocus>
            </div>
            <button type="submit" class="btn">Continue to my account</button>
            <div class="modal__hint">No password needed here because this account area is separate from the member hub.</div>
        </div>
    </form>
</div>
</body>
</html>
