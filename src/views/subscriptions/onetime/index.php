<?php
/**
 * View: subscriptions/onetime/index.php
 *
 * Variables injected by SubscriptionController::index():
 *   $plans                – paginated SubscriptionPlan[]
 *   $bundles              – active SubscriptionBundle[] with savings data
 *   $pagination           – ['current_page', 'total_pages', 'total', 'per_page']
 *   $filters              – active filter values
 *   $available_sites      – Site[]
 *   $available_categories – [['name', 'icon', 'color']]
 *   $available_tags       – string[]
 *   $price_range          – ['min', 'max']
 *   $sort_options         – SortOption[]
 */

use App\Framework\Support\SiteContext;

$selectedCategories = !empty($filters['categories'])
        ? (is_array($filters['categories']) ? $filters['categories'] : explode(',', $filters['categories']))
        : [];

$selectedTags = !empty($filters['tags'])
        ? (is_array($filters['tags']) ? $filters['tags'] : explode(',', $filters['tags']))
        : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscriptions — PressStack</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Instrument+Sans:wght@400;500;600;700&display=swap"
          rel="stylesheet">
    @css('_pressstack-shared.css')
    <style>

        /* ── Page header ─────────────────────────────────────────── */
        .page-header {
            background: var(--white);
            border-bottom: 1px solid var(--border);
            padding: 40px 0 0;
        }

        .page-header__inner {
            max-width: 1340px;
            margin: 0 auto;
            padding: 0 24px 32px;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 24px;
            flex-wrap: wrap;
        }

        .page-header__eyebrow {
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: var(--gold);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .page-header__eyebrow::before {
            content: '';
            display: inline-block;
            width: 14px;
            height: 1.5px;
            background: var(--gold);
            border-radius: 2px;
        }

        .page-header__title {
            font-family: var(--font-display);
            font-size: clamp(28px, 4vw, 44px);
            line-height: 1.05;
            letter-spacing: -.02em;
            color: var(--ink);
        }

        .page-header__sub {
            color: var(--ink-muted);
            margin-top: 7px;
            font-size: 14px;
        }

        /* ── Category tile carousel ──────────────────────────────── */
        .cat-carousel-wrap {
            position: relative;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 28px;
        }

        .cat-carousel {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding: 4px 0 10px;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            flex: 1;
        }

        .cat-carousel::-webkit-scrollbar {
            display: none;
        }

        .cat-tile {
            flex: 0 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 7px;
            padding: 13px 16px 11px;
            background: var(--white);
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition);
            border: 1.5px solid var(--border);
            min-width: 80px;
        }

        .cat-tile:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-sm);
            border-color: var(--ink-muted);
        }

        .cat-tile.selected {
            border-color: var(--gold);
            background: var(--gold-light);
        }

        .cat-tile__icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .cat-tile__name {
            font-size: 11px;
            font-weight: 600;
            color: var(--ink-soft);
            text-align: center;
            white-space: nowrap;
        }

        .cat-tile.selected .cat-tile__name {
            color: var(--gold);
        }

        .cat-nav-btn {
            flex-shrink: 0;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 1.5px solid var(--border);
            background: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            color: var(--ink);
        }

        .cat-nav-btn:hover {
            border-color: var(--ink);
            background: var(--ink);
            color: #fff;
        }

        /* ── Mini Cart ───────────────────────────────────────────── */
        .cart-badge {
            position: fixed;
            top: 2rem;
            right: 2rem;
            background: white;
            border-radius: 1rem;
            padding: 1rem 1.5rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, .1);
            cursor: pointer;
            transition: all .3s;
            z-index: 1000;
        }

        .cart-badge:hover {
            transform: scale(1.05);
        }

        .cart-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .cart-icon {
            position: relative;
        }

        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .75rem;
            font-weight: 700;
        }

        .cart-total {
            font-weight: 700;
            color: #2563eb;
            font-size: 1.125rem;
        }

        .mini-cart {
            position: fixed;
            top: 0;
            right: -400px;
            width: 400px;
            height: 100vh;
            background: white;
            box-shadow: 0 10px 25px rgba(0, 0, 0, .1);
            transition: right .3s;
            z-index: 1001;
            display: flex;
            flex-direction: column;
        }

        .mini-cart.open {
            right: 0;
        }

        .mini-cart-header {
            padding: 1.5rem;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .mini-cart-header h3 {
            font-size: 1.25rem;
        }

        .close-cart {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #64748b;
        }

        .mini-cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 1rem 1.5rem;
        }

        /* ── Cart item ── */
        .cart-item {
            padding: 1rem 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-item-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .cart-item-name {
            font-weight: 600;
            font-size: 0.9rem;
            flex: 1;
        }

        .cart-item-remove {
            background: none;
            border: none;
            cursor: pointer;
            color: #94a3b8;
            padding: 2px;
            line-height: 0;
            border-radius: 4px;
            transition: color 0.2s, background 0.2s;
            flex-shrink: 0;
        }

        .cart-item-remove:hover {
            color: #ef4444;
            background: #fef2f2;
        }

        .cart-item-details {
            font-size: .8rem;
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        .cart-item-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cart-item-price {
            font-weight: 700;
            color: #2563eb;
            font-size: 0.95rem;
        }

        /* ── Quantity controls ── */
        .qty-controls {
            display: flex;
            align-items: center;
            gap: 0;
            border: 1.5px solid #e2e8f0;
            border-radius: 6px;
            overflow: hidden;
        }

        .qty-btn {
            background: #f8fafc;
            border: none;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            color: #475569;
            transition: background 0.15s, color 0.15s;
            line-height: 1;
        }

        .qty-btn:hover:not(:disabled) {
            background: #e2e8f0;
            color: #1e293b;
        }

        .qty-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .qty-value {
            min-width: 28px;
            text-align: center;
            font-size: 0.875rem;
            font-weight: 600;
            color: #1e293b;
            padding: 0 4px;
        }

        .mini-cart-footer {
            padding: 1.5rem;
            border-top: 2px solid #e2e8f0;
        }

        .cart-total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            font-size: 1.125rem;
            font-weight: 700;
        }

        /* ── Clear cart button ── */
        .clear-cart-btn {
            background: none;
            border: 1.5px solid #e2e8f0;
            border-radius: 6px;
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: #94a3b8;
            cursor: pointer;
            width: 100%;
            margin-bottom: 0.75rem;
            transition: all 0.2s;
        }

        .clear-cart-btn:hover {
            border-color: #ef4444;
            color: #ef4444;
            background: #fef2f2;
        }

        .cart-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, .5);
            display: none;
            z-index: 1000;
        }

        .cart-overlay.show {
            display: block;
        }

        /* ── Plan card ─────────────────────────────────────────── */
        .plan-card__cover {
            width: 100%;
            aspect-ratio: 4/3;
            overflow: hidden;
            border-radius: var(--radius-sm) var(--radius-sm) 0 0;
        }

        .plan-card__cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.3s ease;
        }

        .plan-card:hover .plan-card__cover img {
            transform: scale(1.04);
        }

        .plan-card__features {
            list-style: none;
            margin: 0.5rem 0 0.75rem;
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }

        .plan-card__features li {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.8rem;
            color: var(--text-secondary, #64748b);
        }

        .plan-card__features li svg {
            flex-shrink: 0;
            stroke: #10b981;
        }

        .plan-card__features-more {
            font-size: 0.75rem;
            color: #94a3b8;
            font-style: italic;
        }

        .plan-card__release {
            font-size: 0.78rem;
            font-weight: 600;
            color: #7c3aed;
            background: #f5f3ff;
            border-radius: 4px;
            padding: 2px 8px;
            display: inline-block;
            margin-bottom: 0.4rem;
        }

        /* ── Cart button states ─────────────────────────────────── */
        /*
         * .plan-card__btn--cart has three visual states:
         *   default   — normal cart icon
         *   --loading — spinner emoji, disabled
         *   --in-cart — green tint, tick icon, indicates item is in cart
         */
        .plan-card__btn--cart {
            transition: background 0.2s, color 0.2s, border-color 0.2s;
        }

        .plan-card__btn--cart.is-in-cart {
            background: #d1fae5 !important;
            color: #065f46 !important;
            border-color: #6ee7b7 !important;
        }

        .plan-card__btn--cart.is-loading {
            opacity: 0.6;
        }

        /* ── Search clear button ─────────────────────────────────── */
        .search-clear-btn {
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--ink-muted, #94a3b8);
            display: none;
            padding: 2px;
            line-height: 0;
            border-radius: 50%;
            transition: color 0.15s;
        }

        .search-clear-btn:hover {
            color: var(--ink, #1e293b);
        }

        .sidebar__search input {
            padding-right: 28px;
        }

        /* ── Mobile filter bar ───────────────────────────────────── */
        .mobile-filter-bar {
            display: none;
            position: sticky;
            top: 0;
            z-index: 200;
            background: var(--white);
            border-bottom: 1px solid var(--border);
            padding: 10px 16px;
            gap: 10px;
            align-items: center;
        }

        .mobile-filter-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 9px 16px;
            background: var(--white);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 600;
            color: var(--ink);
            cursor: pointer;
            transition: var(--transition);
            flex-shrink: 0;
        }

        .mobile-filter-toggle:hover,
        .mobile-filter-toggle.has-active {
            border-color: var(--gold);
            color: var(--gold);
        }

        .mobile-filter-toggle__count {
            background: var(--gold);
            color: #fff;
            border-radius: 100px;
            font-size: 11px;
            font-weight: 700;
            padding: 1px 7px;
            display: none;
        }

        .mobile-filter-toggle__count.visible {
            display: inline-block;
        }

        .mobile-search-wrap {
            flex: 1;
            position: relative;
            display: flex;
            align-items: center;
        }

        .mobile-search-wrap svg {
            position: absolute;
            left: 10px;
            color: var(--ink-muted);
            pointer-events: none;
        }

        .mobile-search-input {
            width: 100%;
            padding: 9px 32px 9px 34px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-family: var(--font-body, inherit);
            color: var(--ink);
            background: var(--surface, #f8fafc);
            transition: border-color 0.2s;
        }

        .mobile-search-input:focus {
            outline: none;
            border-color: var(--gold);
            background: var(--white);
        }

        .mobile-search-clear {
            position: absolute;
            right: 8px;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--ink-muted);
            display: none;
            padding: 2px;
            line-height: 0;
        }

        .mobile-search-clear:hover {
            color: var(--ink);
        }

        /* ── Filter drawer ───────────────────────────────────────── */
        .filter-drawer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 85dvh;
            background: var(--white);
            border-radius: 20px 20px 0 0;
            box-shadow: 0 -8px 40px rgba(0, 0, 0, .18);
            z-index: 500;
            display: flex;
            flex-direction: column;
            transform: translateY(100%);
            transition: transform 0.32s cubic-bezier(.4, 0, .2, 1);
        }

        .filter-drawer--open {
            transform: translateY(0);
        }

        .filter-drawer__handle {
            width: 40px;
            height: 4px;
            background: var(--border);
            border-radius: 2px;
            margin: 12px auto 0;
            flex-shrink: 0;
        }

        .filter-drawer__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px 12px;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }

        .filter-drawer__title {
            font-size: 15px;
            font-weight: 700;
            color: var(--ink);
        }

        .filter-drawer__close {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--ink-muted);
            line-height: 0;
            padding: 4px;
        }

        .filter-drawer__body {
            flex: 1;
            overflow-y: auto;
            padding: 0 20px 16px;
            -webkit-overflow-scrolling: touch;
        }

        .filter-drawer__body .sidebar__body {
            padding: 0;
        }

        .filter-drawer__footer {
            padding: 12px 20px;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 10px;
            flex-shrink: 0;
        }

        .filter-drawer__footer .filter-btn {
            flex: 1;
        }

        .filter-drawer-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .45);
            z-index: 499;
            display: none;
            opacity: 0;
            transition: opacity 0.25s;
        }

        .filter-drawer-overlay.show {
            display: block;
            opacity: 1;
        }

        /* ── Responsive ──────────────────────────────────────────── */
        @media (max-width: 768px) {
            .mobile-filter-bar {
                display: flex;
            }

            .sidebar {
                display: none !important;
            }

            .mini-cart {
                width: 100%;
                right: -100%;
            }

            .cart-badge {
                bottom: 1rem;
                top: auto;
                right: 1rem;
            }
        }

        @media (min-width: 769px) {
            .filter-drawer,
            .filter-drawer-overlay,
            .mobile-filter-bar {
                display: none !important;
            }
        }
    </style>
</head>
<body>

<!-- Site header -->
<header class="site-header">
    <a href="/" class="header-brand">
        <div class="header-brand__icon">
            <div class="header-brand__icon-lines">
                <span></span><span></span><span></span>
            </div>
        </div>
        <div class="header-brand__wordmark">
            <div class="header-brand__name">Press<em>Stack</em></div>
            <div class="header-brand__tagline">Publishing Platform</div>
        </div>
    </a>

    <div class="header-sep"></div>

    <nav class="header-nav">
        <a href="/<?= SiteContext::slug() ?>/subscriptions"
           class="header-nav-link active">Publications</a>
        <a href="/<?= SiteContext::slug() ?>/subscriptions/deals"
           class="header-nav-link header-nav-link--deals">🔥 Deals</a>
    </nav>

    <div class="header-right">
        <div class="cart-badge" onclick="window.shop.cart.open()">
            <div class="cart-info">
                <div class="cart-icon">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="9" cy="21" r="1"/>
                        <circle cx="20" cy="21" r="1"/>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                    </svg>
                    <span class="cart-count" id="header-cart-count" style="display:none;">0</span>
                </div>
                <span class="cart-total" id="cart-total">£0.00</span>
            </div>
        </div>
        <a href="/<?= SiteContext::slug() ?>/subscriptions/onetime/account"
           class="header-account-btn">
            <?= strtoupper(substr(auth()->user()?->name ?? 'A', 0, 1)) ?>
        </a>
    </div>
</header>

<!-- Page header -->
<div class="page-header">
    <div class="page-header__inner">
        <div>
            <div class="page-header__eyebrow">PressStack</div>
            <h1 class="page-header__title">Subscriptions</h1>
            <p class="page-header__sub">
                <?= number_format($pagination['total']) ?> publication<?= $pagination['total'] !== 1 ? 's' : '' ?>
                available
            </p>
        </div>
        <a href="<?= url('/subscriptions/deals') ?>" class="btn btn--fire btn--pill">
            🔥 View all deals &amp; bundles
        </a>
    </div>
</div>

<!-- ── Mobile filter bar ──────────────────────────────────────────── -->
<div class="mobile-filter-bar" id="mobile-filter-bar">
    <button class="mobile-filter-toggle" id="mobile-filter-toggle" onclick="window.shop.filters.openDrawer()">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="4" y1="6" x2="20" y2="6"/>
            <line x1="4" y1="12" x2="14" y2="12"/>
            <line x1="4" y1="18" x2="10" y2="18"/>
        </svg>
        Filters
        <span class="mobile-filter-toggle__count" id="mobile-filter-count"></span>
    </button>

    <div class="mobile-search-wrap">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"/>
            <path d="m21 21-4.35-4.35"/>
        </svg>
        <input
                type="text"
                id="mobile-search"
                class="mobile-search-input"
                placeholder="Publication name…"
                value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
                autocomplete="off"
        >
        <button class="mobile-search-clear" id="mobile-search-clear" aria-label="Clear search">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
    </div>
</div>

<!-- ── Filter drawer overlay ──────────────────────────────────────── -->
<div class="filter-drawer-overlay" id="filter-drawer-overlay" onclick="window.shop.filters.closeDrawer()"></div>

<!-- ── Filter drawer ──────────────────────────────────────────────── -->
<div class="filter-drawer" id="filter-drawer" role="dialog" aria-modal="true" aria-label="Filters">
    <div class="filter-drawer__handle"></div>
    <div class="filter-drawer__header">
        <span class="filter-drawer__title">Filter publications</span>
        <button class="filter-drawer__close" onclick="window.shop.filters.closeDrawer()" aria-label="Close filters">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
    </div>
    <div class="filter-drawer__body" id="filter-drawer-body"></div>
    <div class="filter-drawer__footer">
        <button type="button" class="filter-btn filter-btn--clear" onclick="window.shop.filters.drawerClear()">Clear
        </button>
        <button type="button" class="filter-btn" onclick="window.shop.filters.drawerApply()">Apply filters</button>
    </div>
</div>

<div class="layout">

    <!-- ── Sidebar (desktop) ──────────────────────────────────── -->
    <aside class="sidebar" id="desktop-sidebar">
        <div class="sidebar__head">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="4" y1="6" x2="20" y2="6"/>
                <line x1="4" y1="12" x2="14" y2="12"/>
                <line x1="4" y1="18" x2="10" y2="18"/>
            </svg>
            Filter publications
        </div>
        <div class="sidebar__body">
            <form id="filter-form">

                <div class="sidebar__section">
                    <div class="sidebar__label">Search</div>
                    <label class="sidebar__search" id="sidebar-search-wrap">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="m21 21-4.35-4.35"/>
                        </svg>
                        <input type="text" id="search" name="search"
                               value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
                               placeholder="Publication name…">
                    </label>
                </div>

                <div class="sidebar__section">
                    <div class="sidebar__label">Special offers</div>
                    <select name="special_filter" class="filter-select" id="special_filter">
                        <option value="">All subscriptions</option>
                        <option value="on_sale" <?= ($filters['special_filter'] ?? '') === 'on_sale' ? 'selected' : '' ?>>
                            On sale
                        </option>
                        <option value="limited_offer" <?= ($filters['special_filter'] ?? '') === 'limited_offer' ? 'selected' : '' ?>>
                            Limited time offers
                        </option>
                    </select>
                </div>

                <?php if (!empty($available_sites)): ?>
                    <div class="sidebar__section">
                        <div class="sidebar__label">Publication</div>
                        <select name="site_id" class="filter-select" id="site_id">
                            <option value="">All publications</option>
                            <?php foreach ($available_sites as $site): ?>
                                <option value="<?= $site->id ?>" <?= ($filters['site_id'] ?? '') == $site->id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($site->name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="sidebar__section">
                    <div class="sidebar__label">Delivery type</div>
                    <select name="delivery_type" class="filter-select" id="delivery_type">
                        <option value="">Print &amp; Digital</option>
                        <option value="digital" <?= ($filters['delivery_type'] ?? '') === 'digital' ? 'selected' : '' ?>>
                            Digital only
                        </option>
                        <option value="print" <?= ($filters['delivery_type'] ?? '') === 'print' ? 'selected' : '' ?>>
                            Print only
                        </option>
                    </select>
                </div>

                <?php if (!empty($available_categories)): ?>
                    <div class="sidebar__section">
                        <div class="sidebar__label">Category</div>
                        <?php foreach ($available_categories as $cat): ?>
                            <button type="button"
                                    class="category-pill <?= in_array($cat['name'], $selectedCategories) ? 'active' : '' ?>"
                                    data-category="<?= htmlspecialchars($cat['name']) ?>"
                                    onclick="window.shop.filters.toggleCategory('<?= htmlspecialchars($cat['name']) ?>')">
                                <span class="category-pill__dot"
                                      style="background:<?= htmlspecialchars($cat['color']) ?>"></span>
                                <?= htmlspecialchars($cat['icon'] . ' ' . $cat['name']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="sidebar__section">
                    <div class="sidebar__label">Price range</div>
                    <div class="price-range-row">
                        <input type="number" name="price_min" id="price_min"
                               value="<?= htmlspecialchars($filters['price_min'] ?? '') ?>"
                               placeholder="<?= $currencySymbol ?><?= $price_range['min'] ?? 0 ?>"
                               step="0.01" min="0">
                        <span>–</span>
                        <input type="number" name="price_max" id="price_max"
                               value="<?= htmlspecialchars($filters['price_max'] ?? '') ?>"
                               placeholder="<?= $currencySymbol ?><?= $price_range['max'] ?? 999 ?>"
                               step="0.01" min="0">
                    </div>
                </div>

                <?php if (!empty($available_tags)): ?>
                    <div class="sidebar__section">
                        <div class="sidebar__label">Tags</div>
                        <div class="tag-list" id="tag-list">
                            <?php foreach ($available_tags as $tag): ?>
                                <label class="tag-item <?= in_array($tag, $selectedTags) ? 'checked' : '' ?>">
                                    <input type="checkbox" name="tags[]" value="<?= htmlspecialchars($tag) ?>"
                                            <?= in_array($tag, $selectedTags) ? 'checked' : '' ?>>
                                    <?= htmlspecialchars(ucwords(str_replace('-', ' ', $tag))) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <button type="button" class="filter-btn filter-btn--clear" onclick="window.shop.filters.clear()">Clear
                    all filters
                </button>
                <button type="submit" class="filter-btn">Apply filters</button>
            </form>
        </div>
    </aside>

    <!-- ── Main ──────────────────────────────────────────────────── -->
    <main class="main" style="min-width:0;">

        <div class="active-chips" id="active-chips"></div>

        <!-- Category tiles carousel -->
        <?php if (!empty($available_categories)): ?>
            <div class="cat-carousel-wrap">
                <button class="cat-nav-btn"
                        onclick="document.getElementById('cat-carousel').scrollBy({left:-280,behavior:'smooth'})"
                        aria-label="Previous">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                         stroke-linecap="round">
                        <polyline points="15 18 9 12 15 6"/>
                    </svg>
                </button>
                <div class="cat-carousel" id="cat-carousel">
                    <?php foreach ($available_categories as $cat): ?>
                        <div class="cat-tile <?= in_array($cat['name'], $selectedCategories) ? 'selected' : '' ?>"
                             data-category="<?= htmlspecialchars($cat['name']) ?>"
                             onclick="window.shop.filters.toggleCategory('<?= htmlspecialchars($cat['name']) ?>')">
                            <div class="cat-tile__icon" style="background:<?= htmlspecialchars($cat['color']) ?>">
                                <?= $cat['icon'] ?>
                            </div>
                            <div class="cat-tile__name"><?= htmlspecialchars($cat['name']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button class="cat-nav-btn"
                        onclick="document.getElementById('cat-carousel').scrollBy({left:280,behavior:'smooth'})"
                        aria-label="Next">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                         stroke-linecap="round">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </button>
            </div>
        <?php endif; ?>

        <!-- Bundles carousel -->
        <?php if (!empty($bundles)): ?>
            <section class="bundles-section" data-carousel>
                <div class="section-header">
                    <div class="section-header__left">
                        <h2 class="section-title">Bundle deals</h2>
                        <a href="<?= url('/subscriptions/deals') ?>" class="section-link">See all →</a>
                    </div>
                    <div class="carousel-controls">
                        <button class="carousel-arrow" data-prev aria-label="Previous" disabled>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2.5" stroke-linecap="round">
                                <polyline points="15 18 9 12 15 6"/>
                            </svg>
                        </button>
                        <div class="carousel-dots" data-dots></div>
                        <button class="carousel-arrow" data-next aria-label="Next">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2.5" stroke-linecap="round">
                                <polyline points="9 18 15 12 9 6"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="carousel-viewport">
                    <div class="carousel-track" data-track>
                        <?php foreach ($bundles as $bundle): ?>
                            <div class="bundle-slide">
                                <a href="<?= url('/subscriptions/bundles/' . $bundle['slug']) ?>" class="bundle-card">
                                    <div class="bundle-card__badge">🔥 SAVE <?= $bundle['discount_percentage'] ?>%</div>
                                    <div class="bundle-card__name"><?= htmlspecialchars($bundle['name']) ?></div>
                                    <?php if ($bundle['description']): ?>
                                        <div class="bundle-card__desc"><?= htmlspecialchars($bundle['description']) ?></div>
                                    <?php endif; ?>
                                    <div class="bundle-card__plans">
                                        <?php foreach ($bundle['plans'] as $plan): ?>
                                            <span class="bundle-card__plan-tag">
                                                <?= $plan['delivery_type'] === 'digital' ? '📱' : '📰' ?>
                                                <?= htmlspecialchars($plan['name']) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="bundle-card__pricing">
                                        <div>
                                            <div class="bundle-card__was">
                                                Was <?= $currencySymbol ?><?= number_format($bundle['total_price'], 2) ?></div>
                                            <div class="bundle-card__price"><?= $currencySymbol ?><?= number_format($bundle['bundle_price'], 2) ?></div>
                                        </div>
                                        <button class="bundle-card__cta"
                                                data-delivery_type="<?= $bundle['delivery_type'] ?? 'print' ?>"
                                                onclick="event.preventDefault(); window.shop.cart.addItem('bundle', <?= $bundle['id'] ?>, this)">
                                            Add to cart
                                        </button>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="carousel-progress">
                    <div class="carousel-progress__fill" data-progress></div>
                </div>
            </section>
        <?php endif; ?>

        <!-- Toolbar -->
        <div class="toolbar">
            <div class="toolbar__count" id="results-count">
                Showing <strong><?= count($plans) ?></strong> of
                <strong><?= number_format($pagination['total']) ?></strong> subscriptions
            </div>
            <div class="toolbar__right">
                <div class="toolbar__sort">
                    <span>Sort:</span>
                    <select id="sort-select">
                        <?php foreach ($sort_options as $option): ?>
                            <option value="<?= $option->value ?>" <?= ($filters['sort'] ?? '') === $option->value ? 'selected' : '' ?>>
                                <?= $option->label() ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Plans -->
        <div id="plans-wrap">
            <?php if (empty($plans)): ?>
                <div class="empty-state">
                    <div class="empty-state__icon">📭</div>
                    <div class="empty-state__title">No subscriptions found</div>
                    <p>Try adjusting your filters to see more results.</p>
                </div>
            <?php else: ?>
                <div class="plans-grid">
                    <?php foreach ($plans as $plan):
                        $hasSale = false;
                        $salePrice = null;
                        $originalPrice = null;
                        $savingPct = null;
                        $tierId = null;
                        /*foreach ($plan->pricingTiers as $tier) {
                            if ($tier->sale_price && $tier->sale_price < $tier->price) {
                                $hasSale = true;
                                $salePrice = $tier->sale_price;
                                $originalPrice = $tier->price;
                                $savingPct = (int)round((($tier->price - $tier->sale_price) / $tier->price) * 100);
                                $tierId = $tier->id;
                                break;
                            }
                        }*/
                        $isLimitedOffer = $plan->end_date && $plan->end_date->diffInDays(now()) <= 30;
                        $displayPrice = $hasSale ? $salePrice : ($plan->price ?? 0);
                        $letter = strtoupper(substr($plan->name, 0, 1));
                        $detailUrl = url('/press-stack/' . $plan->slug);
                        ?>
                        <article class="plan-card">
                            <?php
                            $bestSale = $plan->getBestSale();
                            $hasSale = !empty($bestSale);

                            $tierPrice = $plan->getLowestEffectivePrice();
                            $displayPrice = $tierPrice['min'];
                            $tierId = $tierPrice['tier']->id;

                            $salePrice = $bestSale['sale'] ?? null;
                            $originalPrice = $bestSale['original'] ?? null;
                            $savingPct = $bestSale['savingPct'] ?? null;

                            $isLimitedOffer = $plan->end_date && $plan->end_date->diffInDays(now()) <= 30;

                            $letter = strtoupper(substr($plan->name, 0, 1));
                            $detailUrl = url('/press-stack/' . $plan->slug);

                            $coverImage = $plan->print_image_url ?? $plan->digital_image_url ?? null;
                            ?>

                            <?php if ($plan->is_featured): ?>
                                <div class="plan-card__badge plan-card__badge--featured">⭐ Featured</div>

                            <?php elseif ($hasSale): ?>
                                <div class="plan-card__badge plan-card__badge--sale">
                                    SAVE <?= $savingPct ?>%
                                </div>

                            <?php elseif ($isLimitedOffer): ?>
                                <div class="plan-card__badge plan-card__badge--offer">
                                    Limited offer
                                </div>
                            <?php endif; ?>


                            <?php if ($coverImage): ?>
                                <div class="plan-card__cover">
                                    <img src="<?= htmlspecialchars($coverImage) ?>"
                                         alt="<?= htmlspecialchars($plan->name) ?>"
                                         loading="lazy">
                                </div>
                            <?php else: ?>
                                <div class="plan-card__image"><?= $letter ?></div>
                            <?php endif; ?>


                            <div class="plan-card__body">

                                <div class="plan-card__site">
                                    <?= htmlspecialchars($plan->site->name ?? $plan->site_name ?? '') ?>
                                </div>

                                <div class="plan-card__name">
                                    <?= htmlspecialchars($plan->name) ?>
                                </div>


                                <div class="plan-card__meta">

                                    <?php if ($plan->hasDigitalOption()): ?>
                                        <span class="meta-pill meta-pill--digital">📱 Digital</span>
                                    <?php endif; ?>

                                    <?php if ($plan->hasPrintOption()): ?>
                                        <span class="meta-pill meta-pill--print">📰 Print</span>
                                    <?php endif; ?>

                                    <?php foreach (array_slice((array)($plan->categories ?? []), 0, 2) as $cat): ?>
                                        <span class="meta-pill meta-pill--tag">
                                            <?= htmlspecialchars(ucfirst($cat)) ?>
                                        </span>
                                    <?php endforeach; ?>

                                    <?php foreach (array_slice((array)($plan->tags ?? []), 0, 2) as $tag): ?>
                                        <span class="meta-pill meta-pill--tag">
                                            <?= htmlspecialchars(ucwords(str_replace('-', ' ', $tag))) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>


                                <?php if ($plan->release_date && $plan->release_date > new DateTime()): ?>
                                    <div class="plan-card__release">
                                        🗓 Coming <?= $plan->release_date->format('j M Y') ?>
                                    </div>
                                <?php endif; ?>


                                <?php if (!empty($plan->description)): ?>
                                    <p class="plan-card__desc">
                                        <?= htmlspecialchars(mb_substr($plan->description, 0, 110)) ?>
                                        <?= mb_strlen($plan->description) > 110 ? '…' : '' ?>
                                    </p>
                                <?php endif; ?>


                                <?php if ($plan->features): ?>
                                    <ul class="plan-card__features">

                                        <?php foreach (array_slice($plan->features, 0, 3) as $feature): ?>
                                            <li>
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                                                     stroke="currentColor" stroke-width="3">
                                                    <polyline points="20 6 9 17 4 12"/>
                                                </svg>
                                                <?= htmlspecialchars($feature) ?>
                                            </li>
                                        <?php endforeach; ?>

                                        <?php if (count($plan->features) > 3): ?>
                                            <li class="plan-card__features-more">
                                                +<?= count($plan->features) - 3 ?> more
                                            </li>
                                        <?php endif; ?>

                                    </ul>
                                <?php endif; ?>


                                <div class="plan-card__pricing">

                                    <div>
                                        <div class="plan-card__from">from</div>

                                        <?php if ($hasSale && $originalPrice): ?>
                                            <div class="plan-card__price-was">
                                                <?= $currencySymbol ?><?= number_format($originalPrice, 2) ?>
                                            </div>
                                        <?php endif; ?>

                                        <div class="plan-card__price <?= $hasSale ? 'plan-card__price--sale' : '' ?>">
                                            <?= $currencySymbol ?><?= number_format($displayPrice, 2) ?>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="plan-card__price-period">
                                            / <?= htmlspecialchars($plan->billing_period ?? 'month') ?>
                                        </div>

                                        <?php if ($hasSale): ?>
                                            <div class="plan-card__price-note">🔥 Sale price</div>
                                        <?php endif; ?>
                                    </div>

                                </div>


                                <div style="display:flex; gap:8px;">

                                    <a href="<?= $detailUrl ?>"
                                       class="plan-card__btn <?= $hasSale ? 'plan-card__btn--sale' : '' ?>"
                                       style="flex:1;">
                                        <?= $hasSale ? '🔥 View deal' : 'View details' ?>
                                    </a>

                                    <button class="plan-card__btn plan-card__btn--cart"
                                            data-plan-id="<?= $plan->id ?>"
                                            data-pricing-tier-id="<?= $tierId ?>"
                                            data-delivery_type="<?= $plan->delivery_type === 'digital' || $plan->hasDigitalOption() ? 'digital' : 'print' ?>"
                                            title="Add to cart"
                                            onclick="window.shop.cart.addItem('plan', <?= $plan->id ?>, this)">
                                        🛒
                                    </button>

                                </div>

                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($pagination['total_pages'] > 1): ?>
                    <nav class="pagination" id="pagination">
                        <?php
                        $cur = $pagination['current_page'];
                        $tot = $pagination['total_pages'];
                        $start = max(1, $cur - 2);
                        $end = min($tot, $cur + 2);
                        ?>
                        <button class="pagination__btn <?= $cur <= 1 ? 'disabled' : '' ?>" data-page="<?= $cur - 1 ?>">
                            ←
                        </button>
                        <?php if ($start > 1): ?>
                            <button class="pagination__btn" data-page="1">1</button><?php endif; ?>
                        <?php if ($start > 2): ?><span class="pagination__ellipsis">…</span><?php endif; ?>
                        <?php for ($p = $start; $p <= $end; $p++): ?>
                            <button class="pagination__btn <?= $p === $cur ? 'active' : '' ?>"
                                    data-page="<?= $p ?>"><?= $p ?></button>
                        <?php endfor; ?>
                        <?php if ($end < $tot - 1): ?><span class="pagination__ellipsis">…</span><?php endif; ?>
                        <?php if ($end < $tot): ?>
                            <button class="pagination__btn" data-page="<?= $tot ?>"><?= $tot ?></button><?php endif; ?>
                        <button class="pagination__btn <?= $cur >= $tot ? 'disabled' : '' ?>"
                                data-page="<?= $cur + 1 ?>">→
                        </button>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </main>
</div>

<!-- ── Mini Cart ─────────────────────────────────────────────────── -->
<div class="mini-cart" id="mini-cart">
    <div class="mini-cart-header">
        <h3>Your Cart (<span id="cart-count">0</span>)</h3>
        <button class="close-cart" onclick="window.shop.cart.close()" aria-label="Close cart">×</button>
    </div>
    <div class="mini-cart-items" id="cart-items">
        <p style="text-align:center; color:#64748b; padding:2rem;">Your cart is empty</p>
    </div>
    <div class="mini-cart-footer">
        <div class="cart-total-row">
            <span>Total:</span>
            <span id="mini-cart-total">£0.00</span>
        </div>
        <button class="clear-cart-btn" id="clear-cart-btn" onclick="window.shop.cart.clear()" style="display:none;">
            🗑 Clear cart
        </button>
        <button class="btn btn-primary" onclick="window.shop.cart.checkout()">Proceed to Checkout</button>
    </div>
</div>

<div class="cart-overlay" id="cart-overlay" onclick="window.shop.cart.close()"></div>
<div class="toast" id="toast" role="alert" aria-live="polite"></div>

<script>
    // ── Bootstrap constants ───────────────────────────────────────────────
    const SITE = 'press-stack';
    const API_BASE = '/api/press-stack';
    let CURRENCY_SYMBOL = '<?= $currencySymbol ?>';

    // ── Utilities ─────────────────────────────────────────────────────────
    function escHtml(s) {
        return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CartService
    // Owns all cart state and API interactions.
    // Notifies listeners after every mutation via _notify().
    // ═══════════════════════════════════════════════════════════════════════
    class CartService {
        constructor(apiBase) {
            this.apiBase = apiBase;
            this._data = {items: [], total: 0, count: 0};
            this._listeners = [];
        }

        // ── Derived state ────────────────────────────────────────────────
        get items() {
            return this._data.items || [];
        }

        get total() {
            console.log(this._data)
            return this._data.total || 0;
        }

        get count() {
            return this._data.count || 0;
        }

        /** Set of plan IDs currently in the cart (for button state sync) */
        get planIds() {
            return new Set(
                this.items
                    .filter(i => i.subscription_plan_id)
                    .map(i => String(i.subscription_plan_id))
            );
        }

        // ── Pub/sub ──────────────────────────────────────────────────────
        subscribe(fn) {
            this._listeners.push(fn);
        }

        _notify() {
            this._listeners.forEach(fn => fn(this));
        }

        // ── Remote calls ─────────────────────────────────────────────────
        async load() {
            try {
                const res = await fetch(`${this.apiBase}/cart`);
                this._data = await res.json();
                this._notify();
            } catch (e) {
                console.error('Cart load error:', e);
            }
        }

        /**
         * Add a plan or bundle to the cart.
         * Returns true on success, false on failure.
         */
        async addItem(type, id, deliveryType, pricingTierId = null) {
            const endpoint = type === 'plan' ? '/cart/subscription' : '/cart/add-bundle';
            try {
                const payload = {type, bundle_id: id, plan_id: id, quantity: 1, delivery_type: deliveryType};
                if (pricingTierId) {
                    payload.pricing_tier_id = pricingTierId;
                }
                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                if (data.success) {
                    await this.load();
                    return true;
                }
                return false;
            } catch (e) {
                console.error('Add to cart error:', e);
                return false;
            }
        }

        /**
         * Update quantity for a cart item.
         * Quantity of 0 removes the item.
         */
        async updateQuantity(itemId, quantity) {
            try {
                const res = await fetch(`${this.apiBase}/cart/${itemId}`, {
                    method: 'PUT',
                    headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                    body: JSON.stringify({quantity}),
                });
                const data = await res.json();
                if (data.success) {
                    await this.load();
                    return true;
                }
                return false;
            } catch (e) {
                console.error('Update quantity error:', e);
                return false;
            }
        }

        async removeItem(itemId) {
            try {
                const res = await fetch(`${this.apiBase}/cart/${itemId}`, {
                    method: 'DELETE',
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                });
                const data = await res.json();
                if (data.success) {
                    await this.load();
                    return true;
                }
                return false;
            } catch (e) {
                console.error('Remove item error:', e);
                return false;
            }
        }

        async clear() {
            try {
                const res = await fetch(`${this.apiBase}/cart/clear`, {
                    method: 'DELETE',
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                });
                const data = await res.json();
                if (data.success) {
                    await this.load();
                    return true;
                }
                return false;
            } catch (e) {
                console.error('Clear cart error:', e);
                return false;
            }
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // MiniCartUI
    // Renders the cart sidebar and keeps all button states in sync.
    // Depends on CartService for state; never fetches directly.
    // ═══════════════════════════════════════════════════════════════════════
    class MiniCartUI {
        constructor(cartService) {
            this.cartService = cartService;
            // Subscribe to cart changes — single render path
            this.cartService.subscribe(() => this._render());
        }

        open() {
            document.getElementById('mini-cart').classList.add('open');
            document.getElementById('cart-overlay').classList.add('show');
        }

        close() {
            document.getElementById('mini-cart').classList.remove('open');
            document.getElementById('cart-overlay').classList.remove('show');
        }

        checkout() {
            window.location.href = '/' + SITE + '/checkout?type=subscription';
        }

        // ── Add item (delegates to CartService, manages button state) ────
        async addItem(type, id, btn) {
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.classList.add('is-loading');
            btn.innerHTML = '⏳';

            const deliveryType = btn.dataset.delivery_type;
            const pricingTierId = btn.dataset.pricingTierId ? parseInt(btn.dataset.pricingTierId) : null;
            const success = await this.cartService.addItem(type, id, deliveryType, pricingTierId);

            btn.classList.remove('is-loading');

            if (success) {
                // Cart state already updated via subscription — buttons will
                // be synced by _syncCardButtons(). Give brief visual feedback.
                btn.innerHTML = '✓';
                setTimeout(() => {
                    // Restore label then let _syncCardButtons set final state
                    btn.innerHTML = original;
                    btn.disabled = false;
                    this._syncCardButtons();
                }, 1500);
            } else {
                btn.innerHTML = '✗';
                btn.style.background = 'var(--red-light)';
                setTimeout(() => {
                    btn.innerHTML = original;
                    btn.style.background = '';
                    btn.disabled = false;
                }, 2000);
            }
        }

        // ── Remove item ───────────────────────────────────────────────────
        async removeItem(itemId) {
            await this.cartService.removeItem(itemId);
            // Re-render triggered by CartService subscription
        }

        // ── Update quantity ───────────────────────────────────────────────
        async updateQuantity(itemId, quantity) {
            if (quantity < 1) {
                await this.cartService.removeItem(itemId);
            } else {
                await this.cartService.updateQuantity(itemId, quantity);
            }
        }

        // ── Clear cart ────────────────────────────────────────────────────
        async clear() {
            await this.cartService.clear();
        }

        // ── Private: render ───────────────────────────────────────────────
        _render() {
            this._renderHeader();
            this._renderItems();
            this._renderFooter();
            this._syncCardButtons();
        }

        _renderHeader() {
            const count = this.cartService.count;
            document.getElementById('cart-count').textContent = count;

            const badge = document.getElementById('header-cart-count');
            badge.textContent = count;
            badge.style.display = count > 0 ? 'flex' : 'none';

            document.getElementById('cart-total').textContent =
                CURRENCY_SYMBOL + this.cartService.total.toFixed(2);
        }

        _renderItems() {
            const container = document.getElementById('cart-items');
            const items = this.cartService.items;

            if (!items.length) {
                container.innerHTML = '<p style="text-align:center;color:#64748b;padding:2rem;">Your cart is empty</p>';
                return;
            }

            container.innerHTML = items.map(item => {
                const name = escHtml(item.product_name || item.options?.plan_name || 'Subscription');
                const details = escHtml(item.options?.delivery_type || 'Print') + ' • ' + (item.options?.duration_months || 12) + ' months';
                const price = CURRENCY_SYMBOL + (item.price || 0).toFixed(2);
                const qty = item.quantity || 1;
                const itemId = item.id;

                return `
                <div class="cart-item" data-item-id="${itemId}">
                    <div class="cart-item-top">
                        <div class="cart-item-name">${name}</div>
                        <button class="cart-item-remove"
                                onclick="window.shop.cart.removeItem(${itemId})"
                                aria-label="Remove ${name}">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>
                    <div class="cart-item-details">${details}</div>
                    <div class="cart-item-bottom">
                        <div class="qty-controls">
                            <button class="qty-btn"
                                    onclick="window.shop.cart.updateQuantity(${itemId}, ${qty - 1})"
                                    ${qty <= 1 ? 'disabled' : ''}
                                    aria-label="Decrease quantity">−</button>
                            <span class="qty-value">${qty}</span>
                            <button class="qty-btn"
                                    onclick="window.shop.cart.updateQuantity(${itemId}, ${qty + 1})"
                                    aria-label="Increase quantity">+</button>
                        </div>
                        <div class="cart-item-price">${price}</div>
                    </div>
                </div>`;
            }).join('');
        }

        _renderFooter() {
            document.getElementById('mini-cart-total').textContent =
                CURRENCY_SYMBOL + this.cartService.total.toFixed(2);

            const clearBtn = document.getElementById('clear-cart-btn');
            if (clearBtn) {
                clearBtn.style.display = this.cartService.count > 0 ? 'block' : 'none';
            }
        }

        /**
         * Sync the add-to-cart button state on every plan card so the user
         * can see at a glance which plans are already in their cart.
         */
        _syncCardButtons() {
            const inCart = this.cartService.planIds;
            document.querySelectorAll('[data-plan-id]').forEach(btn => {
                const planId = btn.dataset.planId;
                if (inCart.has(planId)) {
                    btn.classList.add('is-in-cart');
                    btn.title = 'Already in cart';
                    btn.innerHTML = '✓';
                } else {
                    btn.classList.remove('is-in-cart');
                    btn.title = 'Add to cart';
                    btn.innerHTML = '🛒';
                }
            });
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // FilterManager
    // Owns filter/search state, URL sync, chip rendering, and the mobile
    // filter drawer. Delegates fetching to PlanListing.
    // ═══════════════════════════════════════════════════════════════════════
    class FilterManager {
        constructor({form, sortSelect, onFetch}) {
            this.form = form;
            this.sortSelect = sortSelect;
            this.onFetch = onFetch;
            this.selectedCategories = new Set(<?= json_encode($selectedCategories) ?>);
            this.selectedTags = new Set(<?= json_encode($selectedTags) ?>);
            this.currentSort = <?= json_encode($filters['sort'] ?? '') ?>;

            // Mobile drawer state
            this._drawerInitialised = false;
            this._drawerForm = null;

            this._bindEvents();
        }

        // ── Public ────────────────────────────────────────────────────────
        toggleCategory(cat) {
            this.selectedCategories.has(cat)
                ? this.selectedCategories.delete(cat)
                : this.selectedCategories.add(cat);
            this._syncCategoryUI();
            this.onFetch(1);
        }

        clear() {
            this.form.querySelectorAll('input[type="text"],input[type="number"]').forEach(i => i.value = '');
            this.form.querySelectorAll('select').forEach(s => s.value = '');
            this.form.querySelectorAll('input[type="checkbox"]').forEach(c => {
                c.checked = false;
                c.closest('.tag-item')?.classList.remove('checked');
            });
            this.selectedCategories.clear();
            this.selectedTags.clear();
            this._syncCategoryUI();
            this.onFetch(1);
        }

        buildParams(page) {
            const fd = new FormData(this.form);
            const p = new URLSearchParams();
            for (const [k, v] of fd.entries()) {
                if (!v || k === 'categories[]' || k === 'tags[]') continue;
                p.set(k, v);
            }
            this.selectedCategories.forEach(c => p.append('categories[]', c));
            this.selectedTags.forEach(t => p.append('tags[]', t));
            p.set('page', page);
            if (this.currentSort) p.set('sort', this.currentSort);
            return p;
        }

        renderActiveChips() {
            const chips = [];
            const fd = new FormData(this.form);
            const labels = {
                search: 'Search',
                site_id: 'Publication',
                delivery_type: 'Delivery',
                special_filter: 'Offers',
                price_min: 'Min £',
                price_max: 'Max £',
            };
            for (const [k, v] of fd.entries()) {
                if (!v || !labels[k]) continue;
                chips.push(`<div class="active-chip">${labels[k]}: ${escHtml(v)}<button onclick="window.shop.filters._removeChip('${k}')">×</button></div>`);
            }
            this.selectedCategories.forEach(c =>
                chips.push(`<div class="active-chip">📂 ${escHtml(c)}<button onclick="window.shop.filters.removeCategory('${escHtml(c)}')">×</button></div>`)
            );
            this.selectedTags.forEach(t =>
                chips.push(`<div class="active-chip">🏷 ${escHtml(t.replace(/-/g, ' '))}<button onclick="window.shop.filters.removeTag('${escHtml(t)}')">×</button></div>`)
            );
            document.getElementById('active-chips').innerHTML = chips.join('');
            this._updateMobileFilterBadge();
        }

        removeCategory(cat) {
            this.selectedCategories.delete(cat);
            this._syncCategoryUI();
            this.onFetch(1);
        }

        removeTag(tag) {
            this.selectedTags.delete(tag);
            const cb = this.form.querySelector(`input[name="tags[]"][value="${tag}"]`);
            if (cb) cb.checked = false;
            this.onFetch(1);
        }

        // ── Mobile drawer ─────────────────────────────────────────────────
        openDrawer() {
            if (!this._drawerInitialised) this._buildDrawer();
            this._syncDrawerFromSidebar();
            document.getElementById('filter-drawer').classList.add('filter-drawer--open');
            document.getElementById('filter-drawer-overlay').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        closeDrawer() {
            document.getElementById('filter-drawer').classList.remove('filter-drawer--open');
            document.getElementById('filter-drawer-overlay').classList.remove('show');
            document.body.style.overflow = '';
        }

        drawerApply() {
            if (!this._drawerForm) return;
            ['search', 'special_filter', 'site_id', 'delivery_type', 'price_min', 'price_max'].forEach(name => {
                const src = this._drawerForm.querySelector(`[name="${name}"]`);
                const dest = this.form.querySelector(`[name="${name}"]`);
                if (src && dest) dest.value = src.value;
            });
            this.closeDrawer();
            this.onFetch(1);
        }

        drawerClear() {
            if (this._drawerForm) {
                this._drawerForm.querySelectorAll('input[type="text"],input[type="number"]').forEach(i => i.value = '');
                this._drawerForm.querySelectorAll('select').forEach(s => s.value = '');
                this._drawerForm.querySelectorAll('input[type="checkbox"]').forEach(c => {
                    c.checked = false;
                    c.closest('.tag-item')?.classList.remove('checked');
                });
            }
            this.selectedCategories.clear();
            this.selectedTags.clear();
            this._syncCategoryUI();
            this.closeDrawer();
            this.clear();
        }

        // ── Private ───────────────────────────────────────────────────────
        _removeChip(key) {
            const el = this.form.querySelector(`[name="${key}"]`);
            if (el) el.value = '';
            this.onFetch(1);
        }

        _bindEvents() {
            // Desktop search (debounced)
            let searchTimer;
            document.getElementById('search')?.addEventListener('input', () => {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => this.onFetch(1), 450);
            });

            // Desktop selects
            this.form.querySelectorAll('select').forEach(s => s.addEventListener('change', () => this.onFetch(1)));

            // Desktop price range
            this.form.querySelectorAll('input[name="price_min"],input[name="price_max"]').forEach(i =>
                i.addEventListener('change', () => this.onFetch(1))
            );

            // Desktop form submit
            this.form.addEventListener('submit', e => {
                e.preventDefault();
                this.onFetch(1);
            });

            // Sort
            this.sortSelect?.addEventListener('change', () => {
                this.currentSort = this.sortSelect.value;
                this.onFetch(1);
            });

            // Tag checkboxes
            this.form.querySelectorAll('#tag-list input[type="checkbox"]').forEach(cb => {
                cb.addEventListener('change', () => {
                    cb.checked ? this.selectedTags.add(cb.value) : this.selectedTags.delete(cb.value);
                    cb.closest('.tag-item')?.classList.toggle('checked', cb.checked);
                    this.onFetch(1);
                });
            });

            // Sidebar search clear button
            this._initSidebarSearchClear();

            // Mobile search
            this._initMobileSearch();

            // Drawer swipe-to-close
            const drawer = document.getElementById('filter-drawer');
            if (drawer) {
                let touchY = 0;
                drawer.addEventListener('touchstart', e => {
                    touchY = e.touches[0].clientY;
                }, {passive: true});
                drawer.addEventListener('touchend', e => {
                    if (e.changedTouches[0].clientY - touchY > 60) this.closeDrawer();
                }, {passive: true});
            }
        }

        _syncCategoryUI() {
            document.querySelectorAll('[data-category]').forEach(el => {
                const on = this.selectedCategories.has(el.dataset.category);
                el.classList.toggle('active', on);
                el.classList.toggle('selected', on);
            });
        }

        _countActiveFilters() {
            const fd = new FormData(this.form);
            let n = 0;
            for (const [k, v] of fd.entries()) {
                if (v && k !== 'categories[]' && k !== 'tags[]') n++;
            }
            return n + this.selectedCategories.size + this.selectedTags.size;
        }

        _updateMobileFilterBadge() {
            const n = this._countActiveFilters();
            const countEl = document.getElementById('mobile-filter-count');
            const toggleEl = document.getElementById('mobile-filter-toggle');
            if (!countEl || !toggleEl) return;
            countEl.textContent = n;
            countEl.classList.toggle('visible', n > 0);
            toggleEl.classList.toggle('has-active', n > 0);
        }

        _initSidebarSearchClear() {
            const input = document.getElementById('search');
            const wrapper = document.getElementById('sidebar-search-wrap');
            if (!input || !wrapper) return;

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'search-clear-btn';
            btn.setAttribute('aria-label', 'Clear search');
            btn.innerHTML = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`;
            wrapper.appendChild(btn);

            const sync = () => {
                btn.style.display = input.value ? 'block' : 'none';
            };
            input.addEventListener('input', sync);
            sync();

            btn.addEventListener('click', () => {
                input.value = '';
                sync();
                this.onFetch(1);
                input.focus();
            });
        }

        _initMobileSearch() {
            const mobileInput = document.getElementById('mobile-search');
            const clearBtn = document.getElementById('mobile-search-clear');
            const desktopInput = document.getElementById('search');
            if (!mobileInput) return;

            const syncClear = () => {
                clearBtn.style.display = mobileInput.value ? 'block' : 'none';
            };
            mobileInput.addEventListener('input', syncClear);
            syncClear();

            let timer;
            mobileInput.addEventListener('input', () => {
                desktopInput.value = mobileInput.value;
                clearTimeout(timer);
                timer = setTimeout(() => this.onFetch(1), 450);
            });

            clearBtn.addEventListener('click', () => {
                mobileInput.value = '';
                desktopInput.value = '';
                syncClear();
                this.onFetch(1);
                mobileInput.focus();
            });
        }

        _buildDrawer() {
            const body = document.getElementById('filter-drawer-body');
            const sidebarEl = document.querySelector('#desktop-sidebar .sidebar__body');
            const clone = sidebarEl.cloneNode(true);

            const clonedForm = clone.querySelector('form');
            if (clonedForm) {
                clonedForm.id = 'drawer-filter-form';
                clonedForm.querySelectorAll('.filter-btn').forEach(btn => btn.remove());
                this._drawerForm = clonedForm;
            }

            clone.querySelectorAll('[id]').forEach(el => el.removeAttribute('id'));
            body.appendChild(clone);

            clone.querySelectorAll('[data-category]').forEach(el => {
                el.onclick = null;
                el.addEventListener('click', () => this.toggleCategory(el.dataset.category));
            });

            clone.querySelectorAll('input[type="checkbox"][name="tags[]"]').forEach(cb => {
                cb.addEventListener('change', () => {
                    cb.checked ? this.selectedTags.add(cb.value) : this.selectedTags.delete(cb.value);
                    cb.closest('.tag-item')?.classList.toggle('checked', cb.checked);
                });
            });

            this._drawerInitialised = true;
        }

        _syncDrawerFromSidebar() {
            if (!this._drawerForm) return;
            const fd = new FormData(this.form);

            this._drawerForm.querySelectorAll('input[type="text"],input[type="number"]').forEach(el => {
                const v = fd.get(el.name);
                if (v !== null) el.value = v;
            });
            this._drawerForm.querySelectorAll('select').forEach(el => {
                const v = fd.get(el.name);
                if (v !== null) el.value = v;
            });

            this._drawerForm.closest('.filter-drawer__body')?.querySelectorAll('[data-category]').forEach(el => {
                const on = this.selectedCategories.has(el.dataset.category);
                el.classList.toggle('active', on);
                el.classList.toggle('selected', on);
            });

            this._drawerForm.querySelectorAll('input[type="checkbox"][name="tags[]"]').forEach(cb => {
                cb.checked = this.selectedTags.has(cb.value);
                cb.closest('.tag-item')?.classList.toggle('checked', cb.checked);
            });
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PlanListing
    // Owns the plan grid: fetching, rendering cards, and pagination.
    // ═══════════════════════════════════════════════════════════════════════
    class PlanListing {
        constructor({plansWrap, filterManager}) {
            this.plansWrap = plansWrap;
            this.filterManager = filterManager;
            this._isLoading = false;
        }

        async fetch(page = 1) {
            if (this._isLoading) return;
            this._isLoading = true;
            this.plansWrap.classList.add('is-loading');

            const params = this.filterManager.buildParams(page);
            history.replaceState(null, '', '?' + params.toString());
            this.filterManager.renderActiveChips();

            try {
                const res = await fetch('/subscriptions/onetime/search?' + params.toString(), {
                    headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
                });
                const data = await res.json();
                if (!data.success) return;

                const result = data.data;
                const countEl = document.getElementById('results-count');
                if (countEl) {
                    countEl.innerHTML = `Showing <strong>${result.plans.length}</strong> of <strong>${result.pagination.total.toLocaleString()}</strong> subscriptions`;
                }

                this.plansWrap.querySelector('.plans-grid, .empty-state')?.remove();
                document.getElementById('pagination')?.remove();

                if (result.plans.length === 0) {
                    this.plansWrap.insertAdjacentHTML('afterbegin', `<div class="empty-state"><div class="empty-state__icon">📭</div><div class="empty-state__title">No subscriptions found</div><p>Try adjusting your filters.</p></div>`);
                } else {
                    this.plansWrap.insertAdjacentHTML('afterbegin', `<div class="plans-grid">${result.plans.map(p => this._renderCard(p)).join('')}</div>`);
                    this.plansWrap.insertAdjacentHTML('beforeend', this._renderPagination(result.pagination));
                    this._bindPagination();
                }

                // Re-sync cart button states after new cards are injected
                window.shop?.cart?._syncCardButtons?.();

            } catch (e) {
                console.error(e);
            } finally {
                this._isLoading = false;
                this.plansWrap.classList.remove('is-loading');
            }
        }

        // ── Private: card rendering ───────────────────────────────────────
        _deliveryPills(plan) {
            const hasPrint = plan.print_shipping_required || plan.delivery_type === 'print' || plan.delivery_type === 'both';
            const hasDigital = !!plan.digital_download_url || plan.delivery_type === 'digital' || plan.delivery_type === 'both';
            const pills = [];
            if (hasPrint) pills.push(`<span class="meta-pill meta-pill--print">📰 Print</span>`);
            if (hasDigital) pills.push(`<span class="meta-pill meta-pill--digital">📱 Digital</span>`);
            if (!pills.length) {
                if (plan.delivery_type === 'print') pills.push(`<span class="meta-pill meta-pill--print">📰 Print</span>`);
                if (plan.delivery_type === 'digital') pills.push(`<span class="meta-pill meta-pill--digital">📱 Digital</span>`);
            }
            return pills.join('');
        }

        _renderBadge(plan) {
            if (plan.is_featured && plan.savings_pct) return `<div class="plan-card__badge plan-card__badge--featured">⭐ Featured</div>`;
            if (plan.has_sale && plan.savings_pct) return `<div class="plan-card__badge plan-card__badge--sale">SAVE ${plan.savings_pct}%</div>`;
            if (plan.is_limited_offer) return `<div class="plan-card__badge plan-card__badge--offer">Limited offer</div>`;
            return '';
        }

        _renderCard(plan) {
            const price = parseFloat(plan.has_sale ? plan.sale_price : plan.price) || 0;
            const wasLine = (plan.has_sale && plan.original_price) ? `<div class="plan-card__price-was">${CURRENCY_SYMBOL}${parseFloat(plan.original_price).toFixed(2)}</div>` : '';
            const saleNote = plan.has_sale ? `<div class="plan-card__price-note">🔥 Sale price</div>` : '';
            const btnClass = plan.has_sale ? 'plan-card__btn plan-card__btn--sale' : 'plan-card__btn';
            const btnLabel = plan.has_sale ? '🔥 View deal' : 'View details';
            const priceClass = plan.has_sale ? 'plan-card__price plan-card__price--sale' : 'plan-card__price';
            const desc = plan.description ? `<p class="plan-card__desc">${escHtml(plan.description.substring(0, 110))}${plan.description.length > 110 ? '…' : ''}</p>` : '';
            const featuresHtml = (plan.features?.length)
                ? `<ul class="plan-card__features">${plan.features.slice(0, 3).map(f => `<li><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>${escHtml(f)}</li>`).join('')}${plan.features.length > 3 ? `<li class="plan-card__features-more">+${plan.features.length - 3} more</li>` : ''}</ul>` : '';
            const releaseHtml = (plan.release_date && new Date(plan.release_date) > new Date())
                ? `<div class="plan-card__release">🗓 Coming ${new Date(plan.release_date).toLocaleDateString('en-GB', {
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric'
                })}</div>` : '';
            const site = plan.site_name ? `<div class="plan-card__site">${escHtml(plan.site_name)}</div>` : '';
            const catPills = (plan.categories || []).slice(0, 2).map(c => `<span class="meta-pill meta-pill--tag">${escHtml(c.charAt(0).toUpperCase() + c.slice(1))}</span>`).join('');
            const tagPills = (plan.tags || []).slice(0, 2).map(t => `<span class="meta-pill meta-pill--tag">${escHtml(t.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase()))}</span>`).join('');
            const cartDt = escHtml(plan.delivery_type || 'digital');
            const coverUrl = plan.print_image_url || plan.digital_image_url || null;
            const coverHtml = coverUrl
                ? `<div class="plan-card__cover"><img src="${escHtml(coverUrl)}" alt="${escHtml(plan.name)}" loading="lazy"></div>`
                : `<div class="plan-card__image">${escHtml((plan.name || '?')[0].toUpperCase())}</div>`;

            return `<article class="plan-card">${this._renderBadge(plan)}${coverHtml}<div class="plan-card__body">${site}<div class="plan-card__name">${escHtml(plan.name)}</div><div class="plan-card__meta">${this._deliveryPills(plan)}${catPills}${tagPills}</div>${releaseHtml}${desc}${featuresHtml}<div class="plan-card__pricing"><div><div class="plan-card__from">from</div>${wasLine}<div class="${priceClass}">${CURRENCY_SYMBOL}${price.toFixed(2)}</div></div><div><div class="plan-card__price-period">/ ${escHtml(plan.billing_period || 'month')}</div>${saleNote}</div></div><div style="display:flex;gap:8px;"><a href="${escHtml(plan.detail_url)}" class="${btnClass}" style="flex:1;">${btnLabel}</a><button class="plan-card__btn plan-card__btn--cart" data-plan-id="${plan.id}" data-delivery_type="${cartDt}" data-pricing-tier-id="${plan.pricing_tier_id || ''}" title="Add to cart" onclick="window.shop.cart.addItem('plan',${plan.id},this)">🛒</button></div></div></article>`;
        }

        _renderPagination(p) {
            if (p.total_pages <= 1) return '';
            const {current_page: cur, total_pages: tot} = p;
            const start = Math.max(1, cur - 2), end = Math.min(tot, cur + 2);
            let h = `<nav class="pagination" id="pagination">`;
            h += `<button class="pagination__btn ${cur <= 1 ? 'disabled' : ''}" data-page="${cur - 1}">←</button>`;
            if (start > 1) h += `<button class="pagination__btn" data-page="1">1</button>`;
            if (start > 2) h += `<span class="pagination__ellipsis">…</span>`;
            for (let i = start; i <= end; i++) h += `<button class="pagination__btn ${i === cur ? 'active' : ''}" data-page="${i}">${i}</button>`;
            if (end < tot - 1) h += `<span class="pagination__ellipsis">…</span>`;
            if (end < tot) h += `<button class="pagination__btn" data-page="${tot}">${tot}</button>`;
            h += `<button class="pagination__btn ${cur >= tot ? 'disabled' : ''}" data-page="${cur + 1}">→</button></nav>`;
            return h;
        }

        _bindPagination() {
            document.querySelectorAll('#pagination .pagination__btn:not(.disabled)').forEach(btn => {
                btn.addEventListener('click', () => {
                    const p = parseInt(btn.dataset.page);
                    if (p > 0) this.fetch(p);
                });
            });
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // BundleCarousel
    // Self-contained; no dependencies on other classes.
    // ═══════════════════════════════════════════════════════════════════════
    class BundleCarousel {
        constructor(section) {
            this.section = section;
            this.track = section.querySelector('[data-track]');
            this.viewport = section.querySelector('.carousel-viewport');
            this.dotsEl = section.querySelector('[data-dots]');
            this.prevBtn = section.querySelector('[data-prev]');
            this.nextBtn = section.querySelector('[data-next]');
            this.progress = section.querySelector('[data-progress]');
            if (!this.track) return;

            this.slides = Array.from(this.track.children);
            this.current = 0;
            this.autoTimer = null;
            this.isDragging = false;
            this.dragStartX = 0;
            this.dragDelta = 0;

            this._buildDots();
            this._updateUI();
            this._startAuto();
            this._bindEvents();
        }

        // ── Private ────────────────────────────────────────────────────────
        _visibleCount() {
            return Math.round(this.viewport.offsetWidth / (this.slides[0]?.offsetWidth || this.viewport.offsetWidth));
        }

        _maxIndex() {
            return Math.max(0, this.slides.length - this._visibleCount());
        }

        _buildDots() {
            this.dotsEl.innerHTML = '';
            for (let i = 0; i <= this._maxIndex(); i++) {
                const d = document.createElement('button');
                d.className = 'carousel-dot' + (i === this.current ? ' active' : '');
                d.addEventListener('click', () => this._goTo(i));
                this.dotsEl.appendChild(d);
            }
        }

        _updateUI() {
            const w = this.slides[0]?.offsetWidth || 0;
            this.track.style.transform = `translateX(-${this.current * (w + 16)}px)`;
            this.prevBtn.disabled = this.current === 0;
            this.nextBtn.disabled = this.current >= this._maxIndex();
            this.dotsEl.querySelectorAll('.carousel-dot').forEach((d, i) => d.classList.toggle('active', i === this.current));
            this.progress.style.width = (this._maxIndex() === 0 ? 100 : (this.current / this._maxIndex()) * 100) + '%';
        }

        _goTo(i) {
            this.current = Math.max(0, Math.min(i, this._maxIndex()));
            this._updateUI();
        }

        _startAuto() {
            clearInterval(this.autoTimer);
            this.autoTimer = setInterval(() => this._goTo(this.current >= this._maxIndex() ? 0 : this.current + 1), 4500);
        }

        _stopAuto() {
            clearInterval(this.autoTimer);
        }

        _onDragStart(x) {
            this.isDragging = true;
            this.dragStartX = x;
            this.dragDelta = 0;
            this.track.classList.add('is-dragging');
            this._stopAuto();
        }

        _onDragMove(x) {
            if (!this.isDragging) return;
            this.dragDelta = x - this.dragStartX;
            this.track.style.transform = `translateX(${-(this.current * ((this.slides[0]?.offsetWidth || 0) + 16)) + this.dragDelta}px)`;
        }

        _onDragEnd() {
            if (!this.isDragging) return;
            this.isDragging = false;
            this.track.classList.remove('is-dragging');
            if (this.dragDelta < -60) this._goTo(this.current + 1);
            else if (this.dragDelta > 60) this._goTo(this.current - 1);
            else this._updateUI();
            this._startAuto();
        }

        _bindEvents() {
            this.section.addEventListener('mouseenter', () => this._stopAuto());
            this.section.addEventListener('mouseleave', () => this._startAuto());
            this.prevBtn.addEventListener('click', () => {
                this._stopAuto();
                this._goTo(this.current - 1);
                this._startAuto();
            });
            this.nextBtn.addEventListener('click', () => {
                this._stopAuto();
                this._goTo(this.current + 1);
                this._startAuto();
            });

            this.track.addEventListener('mousedown', e => this._onDragStart(e.clientX));
            window.addEventListener('mousemove', e => {
                if (this.isDragging) this._onDragMove(e.clientX);
            });
            window.addEventListener('mouseup', () => this._onDragEnd());
            this.track.addEventListener('touchstart', e => this._onDragStart(e.touches[0].clientX), {passive: true});
            this.track.addEventListener('touchmove', e => this._onDragMove(e.touches[0].clientX), {passive: true});
            this.track.addEventListener('touchend', () => this._onDragEnd());
            this.track.querySelectorAll('a').forEach(a => a.addEventListener('click', e => {
                if (Math.abs(this.dragDelta) > 8) e.preventDefault();
            }));

            let resizeTimer;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => {
                    this.current = Math.min(this.current, this._maxIndex());
                    this._buildDots();
                    this._updateUI();
                }, 150);
            });
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ShopApp  —  top-level composition root
    // Wires all services/UI classes together and exposes a minimal public API.
    // ═══════════════════════════════════════════════════════════════════════
    class ShopApp {
        constructor() {
            const cartService = new CartService(API_BASE);
            const miniCartUI = new MiniCartUI(cartService);

            const filterManager = new FilterManager({
                form: document.getElementById('filter-form'),
                sortSelect: document.getElementById('sort-select'),
                onFetch: (page) => listing.fetch(page),
            });

            const listing = new PlanListing({
                plansWrap: document.getElementById('plans-wrap'),
                filterManager,
            });

            // Initialise bundle carousels
            document.querySelectorAll('[data-carousel]').forEach(el => new BundleCarousel(el));

            // Expose public API used by inline onclick handlers in the template
            this.cart = miniCartUI;
            this.filters = filterManager;
            this.listing = listing;

            // Boot
            filterManager._syncCategoryUI();
            filterManager.renderActiveChips();
            listing._bindPagination();
            cartService.load();
        }
    }

    // ── Bootstrap ─────────────────────────────────────────────────────────
    window.shop = new ShopApp();
</script>
</body>
</html>