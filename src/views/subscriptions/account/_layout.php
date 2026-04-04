<?php
/**
 * PressStack account layout.
 *
 * Requires authentication — redirects to the member login page if the visitor
 * is not logged in.  Uses the same MemberAuth guard as the rest of the app.
 */

use App\Framework\Authorization\MemberAuth;
use App\Framework\Support\SiteContext;

// Guard — must be the very first thing before any HTML output.
if (!MemberAuth::check()) {
    $site = SiteContext::slug();
    $currentPath = $_SERVER['REQUEST_URI'] ?? ('/' . $site . '/account');
    $loginUrl = '/' . $site . '/member/login?redirect=' . urlencode($currentPath);
    header('Location: ' . $loginUrl, true, 302);
    exit;
}

// Ensure $member is always available in the layout and child views.
// Controllers should pass it, but fall back to the auth guard as a safety net.
if (!isset($member)) {
    $member = MemberAuth::getMember();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'My Account') ?> — PressStack</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Instrument+Sans:wght@300;400;500;600&family=Instrument+Serif:ital@0;1&display=swap"
          rel="stylesheet">
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            /* Core palette — deep editorial ink on warm paper */
            --ink: #0d0d0f;
            --ink-soft: #3a3840;
            --ink-muted: #8a8795;
            --paper: #f8f6f1;
            --paper-dark: #f0ede6;
            --white: #ffffff;
            --border: #dedad2;
            --border-soft: #eae7e0;

            /* Brand accent — a sophisticated amber-gold */
            --gold: #b8860b;
            --gold-light: #fdf6e3;
            --gold-mid: #e8c96d;

            /* Accent — deep midnight navy */
            --navy: #12123a;
            --navy-light: #eeeef8;

            /* Semantic colours */
            --green: #0b6e4f;
            --green-light: #d4f0e4;
            --amber: #b35c00;
            --amber-light: #fdecd3;
            --red: #c0392b;
            --red-light: #fde8e6;
            --blue: #1a4fa0;
            --blue-light: #dce8f8;

            /* Radii */
            --radius: 14px;
            --radius-sm: 9px;
            --radius-xs: 5px;

            /* Shadows */
            --shadow-xs: 0 1px 2px rgba(13, 13, 15, .05);
            --shadow-sm: 0 2px 8px rgba(13, 13, 15, .07);
            --shadow: 0 4px 20px rgba(13, 13, 15, .09);
            --shadow-lg: 0 10px 40px rgba(13, 13, 15, .14);

            /* Typography */
            --font-display: 'Playfair Display', Georgia, serif;
            --font-serif: 'Instrument Serif', Georgia, serif;
            --font-body: 'Instrument Sans', system-ui, sans-serif;

            --transition: all .18s cubic-bezier(.4, 0, .2, 1);
            --nav-w: 256px;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: var(--font-body);
            background: var(--paper);
            color: var(--ink);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
        }

        /* ─── HEADER ───────────────────────────────────────────────────── */
        .site-header {
            background: var(--ink);
            border-bottom: 1px solid rgba(255, 255, 255, .06);
            height: 64px;
            display: flex;
            align-items: center;
            padding: 0 28px;
            gap: 0;
            position: sticky;
            top: 0;
            z-index: 200;
        }

        /* Wordmark lockup */
        .header-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            flex-shrink: 0;
        }

        .header-brand__icon {
            width: 34px;
            height: 34px;
            background: var(--gold);
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            position: relative;
            overflow: hidden;
        }

        /* Stacked-lines logo mark */
        .header-brand__icon::before,
        .header-brand__icon::after {
            content: '';
            position: absolute;
            left: 6px;
            right: 6px;
            height: 2px;
            background: var(--ink);
            border-radius: 2px;
        }

        .header-brand__icon::before {
            top: 10px;
            right: 10px;
        }

        .header-brand__icon::after {
            top: 16px;
        }

        .header-brand__icon-inner {
            position: absolute;
            left: 6px;
            right: 14px;
            height: 2px;
            background: var(--ink);
            border-radius: 2px;
            bottom: 10px;
        }

        .header-brand__wordmark {
            display: flex;
            flex-direction: column;
            line-height: 1;
        }

        .header-brand__name {
            font-family: var(--font-display);
            font-size: 19px;
            font-weight: 700;
            color: #fff;
            letter-spacing: -.02em;
        }

        .header-brand__name em {
            font-style: italic;
            color: var(--gold-mid);
        }

        .header-brand__tagline {
            font-family: var(--font-body);
            font-size: 9px;
            font-weight: 500;
            letter-spacing: .18em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .35);
            margin-top: 1px;
        }

        /* Header divider + breadcrumb */
        .header-sep {
            width: 1px;
            height: 22px;
            background: rgba(255, 255, 255, .12);
            margin: 0 20px;
            flex-shrink: 0;
        }

        .header-section-label {
            font-family: var(--font-body);
            font-size: 13px;
            font-weight: 500;
            color: rgba(255, 255, 255, .45);
            letter-spacing: .01em;
        }

        /* Back to shop link */
        .header-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 500;
            color: rgba(255, 255, 255, .4);
            text-decoration: none;
            letter-spacing: .04em;
            text-transform: uppercase;
            transition: color .16s;
            margin-left: 4px;
        }

        .header-back:hover {
            color: rgba(255, 255, 255, .75);
        }

        .header-back svg {
            width: 12px;
            height: 12px;
        }

        /* Member identity — right side */
        .header-member {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-member__name {
            font-size: 13px;
            font-weight: 500;
            color: rgba(255, 255, 255, .6);
            max-width: 160px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
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
            font-size: 14px;
            font-weight: 700;
            flex-shrink: 0;
            border: 2px solid rgba(255, 255, 255, .15);
        }

        /* ─── SHELL ────────────────────────────────────────────────────── */
        .shell {
            display: grid;
            grid-template-columns: var(--nav-w) 1fr;
            min-height: calc(100vh - 64px);
            max-width: 1340px;
            margin: 0 auto;
            padding: 36px 28px 80px;
            gap: 36px;
            align-items: start;
        }

        @media (max-width: 860px) {
            .shell {
                grid-template-columns: 1fr;
                padding: 20px 16px 60px;
                gap: 0;
            }

            .account-nav {
                display: none;
            }

            .mobile-nav {
                display: flex;
            }
        }

        /* ─── SIDEBAR NAV ──────────────────────────────────────────────── */
        .account-nav {
            position: sticky;
            top: 84px;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-xs);
        }

        .account-nav__header {
            padding: 22px 22px 18px;
            border-bottom: 1px solid var(--border-soft);
            background: linear-gradient(160deg, var(--ink) 0%, #1e1b3a 100%);
        }

        .account-nav__name {
            font-family: var(--font-display);
            font-size: 17px;
            color: #fff;
            margin-bottom: 3px;
        }

        .account-nav__email {
            font-size: 12px;
            color: rgba(255, 255, 255, .45);
            font-weight: 400;
        }

        .account-nav__links {
            padding: 8px 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 11px 22px;
            font-size: 13.5px;
            font-weight: 500;
            color: var(--ink-soft);
            text-decoration: none;
            transition: var(--transition);
            border-left: 2.5px solid transparent;
            position: relative;
        }

        .nav-link:hover {
            color: var(--ink);
            background: var(--paper);
        }

        .nav-link.active {
            color: var(--ink);
            font-weight: 600;
            border-left-color: var(--gold);
            background: var(--gold-light);
        }

        .nav-link svg {
            width: 15px;
            height: 15px;
            flex-shrink: 0;
            opacity: .55;
        }

        .nav-link.active svg {
            opacity: 1;
        }

        .nav-link__badge {
            margin-left: auto;
            background: var(--gold);
            color: var(--ink);
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 100px;
        }

        /* nav footer — edition label */
        .account-nav__footer {
            padding: 14px 22px;
            border-top: 1px solid var(--border-soft);
            background: var(--paper);
        }

        .account-nav__footer-label {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--ink-muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .account-nav__footer-label::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--gold);
        }

        /* ─── MOBILE NAV STRIP ─────────────────────────────────────────── */
        .mobile-nav {
            display: none;
            gap: 6px;
            overflow-x: auto;
            padding: 16px 16px 0;
            scrollbar-width: none;
            max-width: 1340px;
            margin: 0 auto;
        }

        .mobile-nav::-webkit-scrollbar {
            display: none;
        }

        .mobile-nav-link {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 15px;
            border-radius: 100px;
            border: 1px solid var(--border);
            background: var(--white);
            font-size: 12.5px;
            font-weight: 500;
            color: var(--ink-soft);
            text-decoration: none;
            white-space: nowrap;
            transition: var(--transition);
        }

        .mobile-nav-link svg {
            width: 13px;
            height: 13px;
        }
        .mobile-nav-link.active {
            background: var(--ink);
            color: #fff;
            border-color: var(--ink);
        }

        /* ─── PAGE CONTENT ─────────────────────────────────────────────── */
        .page-content {
            min-width: 0;
        }

        /* ─── SHARED CARD ──────────────────────────────────────────────── */
        .card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-xs);
        }

        .card__header {
            padding: 18px 24px 16px;
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
            padding: 12px 24px;
            border-top: 1px solid var(--border-soft);
            background: var(--paper);
        }

        /* ─── STATUS BADGES ────────────────────────────────────────────── */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            padding: 3px 10px;
            border-radius: 100px;
        }
        .badge::before {
            content: '';
            width: 5px;
            height: 5px;
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

        .badge--processing {
            background: var(--navy-light);
            color: var(--navy);
        }

        /* ─── EMPTY STATE ──────────────────────────────────────────────── */
        .empty-state {
            text-align: center;
            padding: 64px 24px;
        }

        .empty-state__icon {
            font-size: 44px;
            margin-bottom: 16px;
            opacity: .45;
        }
        .empty-state__title {
            font-family: var(--font-display);
            font-size: 22px;
            color: var(--ink);
            margin-bottom: 6px;
        }
        .empty-state__sub {
            font-size: 14px;
            color: var(--ink-muted);
            margin-bottom: 22px;
        }

        /* ─── BUTTONS ──────────────────────────────────────────────────── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 18px;
            border-radius: var(--radius-sm);
            font-family: var(--font-body);
            font-size: 13.5px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: var(--transition);
            border: none;
            line-height: 1;
            letter-spacing: .01em;
        }
        .btn--primary {
            background: var(--ink);
            color: #fff;
        }

        .btn--primary:hover {
            background: #2a2730;
        }

        .btn--gold {
            background: var(--gold);
            color: var(--ink);
        }

        .btn--gold:hover {
            background: #a07609;
        }

        .btn--ghost {
            background: transparent;
            color: var(--ink-soft);
            border: 1px solid var(--border);
        }

        .btn--ghost:hover {
            background: var(--paper);
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
            padding: 6px 13px;
            font-size: 12px;
        }

        .btn--icon {
            padding: 7px;
            border-radius: var(--radius-xs);
        }

        /* ─── PAGE HEADING ─────────────────────────────────────────────── */
        .page-heading {
            margin-bottom: 28px;
        }

        .page-heading__eyebrow {
            font-size: 10.5px;
            font-weight: 600;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: var(--gold);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .page-heading__eyebrow::before {
            content: '';
            width: 20px;
            height: 1.5px;
            background: var(--gold);
            border-radius: 2px;
        }
        .page-heading__title {
            font-family: var(--font-display);
            font-size: clamp(26px, 3.5vw, 36px);
            line-height: 1.1;
            margin-bottom: 5px;
            letter-spacing: -.02em;
        }
        .page-heading__sub {
            font-size: 14px;
            color: var(--ink-muted);
            font-weight: 400;
        }

        /* ─── MODAL ────────────────────────────────────────────────────── */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(13, 13, 15, .55);
            backdrop-filter: blur(4px);
            z-index: 300;
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
            max-width: 492px;
            overflow: hidden;
            transform: translateY(14px);
            transition: transform .24s cubic-bezier(.32, .72, 0, 1);
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
            background: linear-gradient(160deg, var(--ink) 0%, #1e1b3a 100%);
        }
        .modal__title {
            font-family: var(--font-display);
            font-size: 21px;
            line-height: 1.2;
            color: #fff;
        }
        .modal__close {
            background: rgba(255, 255, 255, .1);
            border: none;
            cursor: pointer;
            color: rgba(255, 255, 255, .6);
            padding: 5px 7px;
            border-radius: 6px;
            line-height: 1;
            font-size: 16px;
            transition: var(--transition);
            flex-shrink: 0;
        }

        .modal__close:hover {
            color: #fff;
            background: rgba(255, 255, 255, .18);
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
            background: var(--paper);
        }

        /* ─── STEP INDICATOR ───────────────────────────────────────────── */
        .steps {
            display: flex;
            align-items: center;
            gap: 0;
            margin-bottom: 24px;
        }
        .step {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11.5px;
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
            font-size: 10px;
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
            margin: 0 10px;
        }

        /* ─── DECORATIVE RULE ──────────────────────────────────────────── */
        .press-rule {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--ink-muted);
            font-size: 11px;
            letter-spacing: .1em;
            text-transform: uppercase;
            font-weight: 600;
            margin: 28px 0 16px;
        }

        .press-rule::before,
        .press-rule::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }
    </style>
</head>
<body>

<!-- ═══════════════════════════════ HEADER ═══════════════════════════════ -->
<header class="site-header">

    <!-- Brand lockup -->
    <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/subscriptions" class="header-brand">
        <div class="header-brand__icon" aria-hidden="true">
            <span class="header-brand__icon-inner"></span>
        </div>
        <div class="header-brand__wordmark">
            <span class="header-brand__name">Press<em>Stack</em></span>
            <span class="header-brand__tagline">Publishing Platform</span>
        </div>
    </a>

    <div class="header-sep"></div>

    <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/subscriptions" class="header-back">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Back to Shop
    </a>

    <div class="header-sep"></div>

    <span class="header-section-label">My Account</span>

    <!-- Member identity -->
    <div class="header-member">
        <span class="header-member__name"><?= htmlspecialchars($member->name ?? $member->email ?? '') ?></span>
        <div class="header-member__avatar" title="<?= htmlspecialchars($member->email ?? '') ?>">
            <?= strtoupper(substr($member->name ?? $member->email ?? 'M', 0, 1)) ?>
        </div>
    </div>
</header>

<!-- Mobile nav strip -->
<div style="background:var(--white); border-bottom:1px solid var(--border-soft);">
    <nav class="mobile-nav">
        <?php $t = $active_tab ?? 'overview'; ?>

        <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/subscriptions/onetime/account"
           class="mobile-nav-link <?= $t === 'overview' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="7" height="7"/>
                <rect x="14" y="3" width="7" height="7"/>
                <rect x="3" y="14" width="7" height="7"/>
                <rect x="14" y="14" width="7" height="7"/>
            </svg>
            Overview
        </a>

        <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/subscriptions/onetime/account/subscriptions"
           class="mobile-nav-link <?= $t === 'subscriptions' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
            Subscriptions
        </a>

        <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/subscriptions/onetime/account/orders"
           class="mobile-nav-link <?= $t === 'orders' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/>
                <line x1="3" y1="6" x2="21" y2="6"/>
                <path d="M16 10a4 4 0 01-8 0"/>
            </svg>
            Orders
        </a>

        <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/subscriptions/onetime/account/billing"
           class="mobile-nav-link <?= $t === 'billing' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="1" y="4" width="22" height="16" rx="2"/>
                <line x1="1" y1="10" x2="23" y2="10"/>
            </svg>
            Billing
        </a>
    </nav>
</div>

<!-- ═══════════════════════════════ SHELL ════════════════════════════════ -->
<div class="shell">

    <!-- ── Sidebar nav ───────────────────────────────────────────────── -->
    <aside class="account-nav">
        <div class="account-nav__header">
            <div class="account-nav__name"><?= htmlspecialchars($member->name ?? 'Account') ?></div>
            <div class="account-nav__email"><?= htmlspecialchars($member->email ?? '') ?></div>
        </div>

        <nav class="account-nav__links">
            <?php $t = $active_tab ?? 'overview'; ?>

            <a href="/press-stack/account"
               class="nav-link <?= $t === 'overview' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"/>
                    <rect x="14" y="3" width="7" height="7"/>
                    <rect x="3" y="14" width="7" height="7"/>
                    <rect x="14" y="14" width="7" height="7"/>
                </svg>
                Overview
            </a>

            <a href="/press-stack/account/subscriptions"
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

            <a href="/press-stack/account/orders"
               class="nav-link <?= $t === 'orders' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/>
                    <line x1="3" y1="6" x2="21" y2="6"/>
                    <path d="M16 10a4 4 0 01-8 0"/>
                </svg>
                Orders
            </a>

            <a href="/press-stack/account/billing"
               class="nav-link <?= $t === 'billing' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="1" y="4" width="22" height="16" rx="2"/>
                    <line x1="1" y1="10" x2="23" y2="10"/>
                </svg>
                Billing
            </a>
        </nav>

        <div class="account-nav__footer">
            <span class="account-nav__footer-label">PressStack Member</span>
        </div>
    </aside>