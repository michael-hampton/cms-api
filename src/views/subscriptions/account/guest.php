<?php
/**
 * Guest-facing PressStack account shell.
 *
 * This is intentionally rendered for logged-out account page GET requests. The
 * shell is locked and the email lookup modal is always visible.
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
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Instrument+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --ink: #0d0d0f;
            --ink-soft: #3a3840;
            --ink-muted: #8a8795;
            --paper: #f8f6f1;
            --white: #fff;
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
            --shadow-lg: 0 18px 60px rgba(13, 13, 15, .24);
            --font-display: 'Playfair Display', Georgia, serif;
            --font-body: 'Instrument Sans', system-ui, sans-serif;
        }
        body {
            min-height: 100vh;
            background: var(--paper);
            color: var(--ink);
            font-family: var(--font-body);
            line-height: 1.6;
        }
        .site-header {
            position: sticky;
            top: 0;
            z-index: 200;
            height: 64px;
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 0 28px;
            background: var(--ink);
        }
        .header-brand { display: flex; align-items: center; gap: 10px; color: #fff; text-decoration: none; }
        .header-brand__icon { width: 34px; height: 34px; border-radius: 7px; background: var(--gold); }
        .header-brand__wordmark { display: flex; flex-direction: column; line-height: 1; }
        .header-brand__name { color: #fff; font-family: var(--font-display); font-size: 19px; font-weight: 700; letter-spacing: -.02em; }
        .header-brand__name em { color: var(--gold-mid); font-style: italic; }
        .header-brand__tagline { margin-top: 1px; color: rgba(255,255,255,.35); font-size: 9px; font-weight: 500; letter-spacing: .18em; text-transform: uppercase; }
        .header-back { color: rgba(255,255,255,.45); font-size: 12px; font-weight: 700; letter-spacing: .04em; text-decoration: none; text-transform: uppercase; }
        .header-section-label { color: rgba(255,255,255,.48); font-size: 13px; font-weight: 500; }
        .header-member { margin-left: auto; display: flex; align-items: center; gap: 12px; color: rgba(255,255,255,.6); font-size: 13px; }
        .header-member__avatar { width: 34px; height: 34px; display: grid; place-items: center; border-radius: 50%; background: linear-gradient(135deg, var(--gold) 0%, #8b6914 100%); color: var(--ink); font-family: var(--font-display); font-weight: 700; }
        .account-guest-shell {
            display: grid;
            grid-template-columns: 256px 1fr;
            min-height: calc(100vh - 64px);
            max-width: 1340px;
            margin: 0 auto;
            padding: 36px 28px 80px;
            gap: 36px;
            align-items: start;
            filter: blur(1.5px);
            pointer-events: none;
            user-select: none;
        }
        .account-nav, .card, .stat-card { overflow: hidden; background: var(--white); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow-xs); }
        .account-nav__header { padding: 22px; background: linear-gradient(160deg, var(--ink) 0%, #1e1b3a 100%); color: #fff; }
        .account-nav__name { font-family: var(--font-display); font-size: 17px; }
        .account-nav__email { color: rgba(255,255,255,.45); font-size: 12px; }
        .account-nav__links { padding: 8px 0; }
        .nav-link { display: flex; padding: 11px 22px; border-left: 2.5px solid transparent; color: var(--ink-soft); font-size: 13.5px; font-weight: 500; text-decoration: none; }
        .nav-link.active { background: var(--gold-light); border-left-color: var(--gold); color: var(--ink); font-weight: 700; }
        .account-nav__footer { padding: 14px 22px; border-top: 1px solid var(--border-soft); background: var(--paper); }
        .account-nav__footer-label { color: var(--ink-muted); font-size: 10px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
        .page-heading { margin-bottom: 28px; }
        .page-heading__eyebrow { margin-bottom: 6px; color: var(--gold); font-size: 10.5px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
        .page-heading__title { margin-bottom: 5px; font-family: var(--font-display); font-size: clamp(26px, 3.5vw, 36px); line-height: 1.1; }
        .page-heading__sub { color: var(--ink-muted); font-size: 14px; }
        .overview-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-card { padding: 22px; position: relative; }
        .stat-card::after { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 2.5px; background: linear-gradient(90deg, var(--gold) 0%, transparent 80%); }
        .stat-card__label { margin-bottom: 10px; color: var(--ink-muted); font-size: 10.5px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }
        .stat-card__value { color: var(--ink); font-family: var(--font-display); font-size: 40px; line-height: 1; }
        .stat-card__sub { margin-top: 5px; color: var(--ink-muted); font-size: 12px; }
        .card__header { padding: 18px 24px 16px; border-bottom: 1px solid var(--border-soft); }
        .card__title { font-family: var(--font-display); font-size: 18px; }
        .card__body { padding: 36px 24px; color: var(--ink-muted); }
        .account-guest-modal-overlay {
            position: fixed !important;
            inset: 0 !important;
            z-index: 2147483000 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 24px !important;
            background: rgba(13, 13, 15, .62) !important;
            backdrop-filter: blur(5px);
        }
        .account-guest-modal {
            width: 100%;
            max-width: 492px;
            overflow: hidden;
            border-radius: var(--radius);
            background: var(--white);
            box-shadow: var(--shadow-lg);
        }
        .account-guest-modal__header { padding: 24px 24px 16px; background: linear-gradient(160deg, var(--ink) 0%, #1e1b3a 100%); color: #fff; }
        .account-guest-modal__title { font-family: var(--font-display); font-size: 23px; line-height: 1.2; }
        .account-guest-modal__body { padding: 24px; }
        .account-guest-modal__body p { margin-bottom: 18px; color: var(--ink-muted); font-size: 14px; }
        .form-field { display: flex; flex-direction: column; gap: 7px; margin-bottom: 16px; }
        .form-field label { color: var(--ink-soft); font-size: 12px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .form-field input { width: 100%; padding: 12px 13px; border: 1px solid var(--border); border-radius: var(--radius-sm); background: #fff; color: var(--ink); font: inherit; }
        .form-field input:focus { outline: 2px solid rgba(184, 134, 11, .22); border-color: var(--gold); }
        .alert { margin-bottom: 14px; padding: 11px 13px; border-radius: var(--radius-sm); background: var(--red-light); color: var(--red); font-size: 13px; }
        .btn { width: 100%; display: inline-flex; align-items: center; justify-content: center; padding: 11px 18px; border: 0; border-radius: var(--radius-sm); background: var(--ink); color: #fff; cursor: pointer; font: inherit; font-size: 13.5px; font-weight: 700; }
        .account-guest-modal__hint { margin-top: 13px; color: var(--ink-muted); font-size: 12.5px; }
        @media (max-width: 860px) {
            .site-header { padding: 0 16px; gap: 12px; }
            .header-back, .header-section-label { display: none; }
            .account-guest-shell { grid-template-columns: 1fr; padding: 24px 16px 60px; }
            .account-nav { display: none; }
            .overview-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body data-account-auth-state="guest">
<header class="site-header">
    <a href="/press-stack" class="header-brand">
        <div class="header-brand__icon" aria-hidden="true"></div>
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

<div class="account-guest-shell" aria-hidden="true">
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

<div class="account-guest-modal-overlay" id="account-guest-email-modal" role="dialog" aria-modal="true" aria-labelledby="press-stack-account-login-title" data-account-guest-modal>
    <form class="account-guest-modal" method="post" action="/press-stack/account/login">
        <div class="account-guest-modal__header">
            <h2 class="account-guest-modal__title" id="press-stack-account-login-title">Find your PressStack account</h2>
        </div>
        <div class="account-guest-modal__body">
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
            <div class="account-guest-modal__hint">No password needed here because this account area is separate from the member hub.</div>
        </div>
    </form>
</div>
<script>
    document.getElementById('account-email')?.focus();
</script>
</body>
</html>
