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
            padding: 1.5rem;
        }

        .cart-item {
            padding: 1rem 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .cart-item-name {
            font-weight: 600;
            margin-bottom: .5rem;
        }

        .cart-item-details {
            font-size: .875rem;
            color: #64748b;
            margin-bottom: .5rem;
        }

        .cart-item-price {
            font-weight: 600;
            color: #2563eb;
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

        /* ── Plan card image ─────────────────────────────────────── */
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

        /* ── SEARCH CLEAR BUTTON ─────────────────────────────────── */
        /*
         * The sidebar search label is already position:relative in the shared
         * stylesheet (it wraps the SVG icon + input). The clear button is
         * injected by JS and sits at the trailing edge of the input.
         */
        .search-clear-btn {
            /*position: absolute;*/
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--ink-muted, #94a3b8);
            display: none; /* shown by JS when input has value */
            padding: 2px;
            line-height: 0;
            border-radius: 50%;
            transition: color 0.15s;
        }

        .search-clear-btn:hover {
            color: var(--ink, #1e293b);
        }

        /* Nudge the input so text doesn't slide under the ×  */
        .sidebar__search input {
            padding-right: 28px;
        }

        /* ── MOBILE FILTER TOGGLE + DRAWER ───────────────────────── */
        /*
         * On mobile (<= 768 px) the sidebar is hidden by default. A sticky
         * toolbar button opens a full-width bottom-sheet drawer containing
         * the sidebar content. An overlay dismisses it.
         *
         * The .layout grid breakpoint is inherited from _pressstack-shared.css.
         * We simply keep .sidebar display:none on small screens and reveal it
         * as a fixed drawer when .filter-drawer--open is present on <body>.
         */
        .mobile-filter-bar {
            display: none; /* shown only on mobile */
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

        /* Active-filter count badge on the toggle button */
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

        /* Inline mobile search — full width, sits right of the filter toggle */
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

        /* The drawer itself */
        .filter-drawer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 85dvh; /* leaves a peek of content behind */
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

        /* The drawer body houses .sidebar__body content, reuse its styles */
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

        /* Overlay behind the drawer */
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

        /* ── Responsive breakpoints ──────────────────────────────── */
        @media (max-width: 768px) {
            .mobile-filter-bar {
                display: flex;
            }

            /* Hide desktop sidebar — its form is cloned into the drawer */
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
            /* Ensure drawer never shows on desktop even if class is present */
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
        <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/subscriptions"
           class="header-nav-link active">Publications</a>
        <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/subscriptions/deals"
           class="header-nav-link header-nav-link--deals">🔥 Deals</a>
    </nav>

    <div class="header-right">
        <div class="cart-badge" onclick="openMiniCart()">
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
        <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/subscriptions/onetime/account"
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

<!-- ── Mobile filter bar (hidden on desktop via CSS) ──────────────── -->
<div class="mobile-filter-bar" id="mobile-filter-bar">
    <button class="mobile-filter-toggle" id="mobile-filter-toggle" onclick="openFilterDrawer()">
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
<div class="filter-drawer-overlay" id="filter-drawer-overlay" onclick="closeFilterDrawer()"></div>

<!-- ── Filter drawer ──────────────────────────────────────────────── -->
<div class="filter-drawer" id="filter-drawer" role="dialog" aria-modal="true" aria-label="Filters">
    <div class="filter-drawer__handle"></div>
    <div class="filter-drawer__header">
        <span class="filter-drawer__title">Filter publications</span>
        <button class="filter-drawer__close" onclick="closeFilterDrawer()" aria-label="Close filters">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
    </div>
    <!--
        The sidebar form content is cloned into here by JS (initFilterDrawer).
        We do NOT duplicate PHP markup — a single form is the source of truth,
        and the clone stays in sync via the syncDrawerToSidebar helper.
    -->
    <div class="filter-drawer__body" id="filter-drawer-body"></div>
    <div class="filter-drawer__footer">
        <button type="button" class="filter-btn filter-btn--clear" onclick="drawerClearFilters()">Clear</button>
        <button type="button" class="filter-btn" onclick="drawerApplyFilters()">Apply filters</button>
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
                    <!--
                        position:relative is already on .sidebar__search in the
                        shared sheet. The clear button is injected by JS.
                    -->
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
                                    onclick="toggleCategory('<?= htmlspecialchars($cat['name']) ?>')">
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

                <button type="button" class="filter-btn filter-btn--clear" onclick="clearFilters()">Clear all filters
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
                             onclick="toggleCategory('<?= htmlspecialchars($cat['name']) ?>')">
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
                                                onclick="event.preventDefault(); addToCart('bundle', <?= $bundle['id'] ?>, this)">
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
                        foreach ($plan->pricingTiers as $tier) {
                            if ($tier->sale_price && $tier->sale_price < $tier->price) {
                                $hasSale = true;
                                $salePrice = $tier->sale_price;
                                $originalPrice = $tier->price;
                                $savingPct = (int)round((($tier->price - $tier->sale_price) / $tier->price) * 100);
                                break;
                            }
                        }
                        $isLimitedOffer = $plan->end_date && $plan->end_date->diffInDays(now()) <= 30;
                        $displayPrice = $hasSale ? $salePrice : ($plan->price ?? 0);
                        $letter = strtoupper(substr($plan->name, 0, 1));
                        $detailUrl = url('/press-stack/' . $plan->id);
                        ?>
                        <article class="plan-card">
                            <?php if ($plan->is_featured): ?>
                                <div class="plan-card__badge plan-card__badge--featured">⭐ Featured</div>
                            <?php elseif ($hasSale): ?>
                                <div class="plan-card__badge plan-card__badge--sale">SAVE <?= $savingPct ?>%</div>
                            <?php elseif ($isLimitedOffer): ?>
                                <div class="plan-card__badge plan-card__badge--offer">Limited offer</div>
                            <?php endif; ?>

                            <?php $coverImage = $plan->print_image_url ?? $plan->digital_image_url ?? null; ?>
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
                                <div class="plan-card__site"><?= htmlspecialchars($plan->site->name ?? $plan->site_name ?? '') ?></div>
                                <div class="plan-card__name"><?= htmlspecialchars($plan->name) ?></div>

                                <div class="plan-card__meta">
                                    <?php if ($plan->delivery_type === 'digital' || $plan->hasDigitalOption()): ?>
                                        <span class="meta-pill meta-pill--digital">📱 Digital</span>
                                    <?php endif; ?>
                                    <?php if ($plan->delivery_type === 'print' || $plan->hasPrintOption()): ?>
                                        <span class="meta-pill meta-pill--print">📰 Print</span>
                                    <?php endif; ?>
                                    <?php foreach (array_slice((array)($plan->categories ?? []), 0, 2) as $cat): ?>
                                        <span class="meta-pill meta-pill--tag"><?= htmlspecialchars(ucfirst($cat)) ?></span>
                                    <?php endforeach; ?>
                                    <?php foreach (array_slice((array)($plan->tags ?? []), 0, 2) as $tag): ?>
                                        <span class="meta-pill meta-pill--tag"><?= htmlspecialchars(ucwords(str_replace('-', ' ', $tag))) ?></span>
                                    <?php endforeach; ?>
                                </div>

                                <?php if ($plan->release_date && $plan->release_date > new DateTime()): ?>
                                    <div class="plan-card__release">
                                        🗓 Coming <?= $plan->release_date->format('j M Y') ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($plan->description)): ?>
                                    <p class="plan-card__desc">
                                        <?= htmlspecialchars(mb_substr($plan->description, 0, 110)) ?><?= mb_strlen($plan->description) > 110 ? '…' : '' ?>
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
                                            <li class="plan-card__features-more">+<?= count($plan->features) - 3 ?>
                                                more
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                <?php endif; ?>

                                <div class="plan-card__pricing">
                                    <div>
                                        <div class="plan-card__from">from</div>
                                        <?php if ($hasSale && $originalPrice): ?>
                                            <div class="plan-card__price-was"><?= $currencySymbol ?><?= number_format($originalPrice, 2) ?></div>
                                        <?php endif; ?>
                                        <div class="plan-card__price <?= $hasSale ? 'plan-card__price--sale' : '' ?>">
                                            <?= $currencySymbol ?><?= number_format($displayPrice, 2) ?>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="plan-card__price-period">
                                            / <?= htmlspecialchars($plan->billing_period ?? 'month') ?></div>
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
                                            data-delivery_type="<?= $plan->delivery_type === 'digital' || $plan->hasDigitalOption() ? 'digital' : 'print' ?>"
                                            title="Add to cart"
                                            onclick="addToCart('plan', <?= $plan->id ?>, this)">
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

<div class="mini-cart" id="mini-cart">
    <div class="mini-cart-header">
        <h3>Your Cart (<span id="cart-count">0</span>)</h3>
        <button class="close-cart" onclick="closeMiniCart()" aria-label="Close cart">×</button>
    </div>
    <div class="mini-cart-items" id="cart-items">
        <p style="text-align:center; color:#64748b; padding:2rem;">Your cart is empty</p>
    </div>
    <div class="mini-cart-footer">
        <div class="cart-total-row">
            <span>Total:</span>
            <span id="mini-cart-total">£0.00</span>
        </div>
        <button class="btn btn-primary" onclick="goToCheckout()">Proceed to Checkout</button>
    </div>
</div>

<div class="cart-overlay" id="cart-overlay" onclick="closeMiniCart()"></div>
<div class="toast" id="toast" role="alert" aria-live="polite"></div>

<script>
    const FORM = document.getElementById('filter-form');
    const SORT_SELECT = document.getElementById('sort-select');
    const PLANS_WRAP = document.getElementById('plans-wrap');
    let cartData = {items: [], total: 0, count: 0};
    const SITE = 'press-stack';
    const API_BASE = '/api/press-stack';
    CURRENCY_SYMBOL = '<?= $currencySymbol ?>';

    let selectedCategories = new Set(<?= json_encode($selectedCategories) ?>);
    let selectedTags = new Set(<?= json_encode($selectedTags) ?>);
    let currentSort = <?= json_encode($filters['sort'] ?? '') ?>;
    let isLoading = false;

    /* ── Escape helper ──────────────────────────────────────── */
    function escHtml(s) {
        return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    /* ── Cart ───────────────────────────────────────────────── */
    async function loadCart() {
        try {
            const r = await fetch(`${API_BASE}/cart`);
            cartData = await r.json();
            updateCartDisplay();
        } catch (e) {
            console.error('Cart load error:', e);
        }
    }

    function updateCartDisplay() {
        const count = cartData.count || 0;
        document.getElementById('cart-count').textContent = count;
        document.getElementById('cart-total').textContent = CURRENCY_SYMBOL + +(cartData.total || 0).toFixed(2);
        document.getElementById('mini-cart-total').textContent = CURRENCY_SYMBOL + +(cartData.total || 0).toFixed(2);
        const badge = document.getElementById('header-cart-count');
        badge.textContent = count;
        badge.style.display = count > 0 ? 'flex' : 'none';

        const container = document.getElementById('cart-items');
        if (!cartData.items?.length) {
            container.innerHTML = '<p style="text-align:center;color:#64748b;padding:2rem;">Your cart is empty</p>';
            return;
        }
        container.innerHTML = cartData.items.map(item => `
        <div class="cart-item">
            <div class="cart-item-name">${item.product_name || item.options?.plan_name || 'Subscription'}</div>
            <div class="cart-item-details">${item.options?.delivery_type || 'Print'} • ${item.options?.duration_months || 12} months</div>
            <div class="cart-item-price">${CURRENCY_SYMBOL}${(item.price || 0).toFixed(2)}</div>
        </div>`).join('');
    }

    function openMiniCart() {
        document.getElementById('mini-cart').classList.add('open');
        document.getElementById('cart-overlay').classList.add('show');
    }

    function closeMiniCart() {
        document.getElementById('mini-cart').classList.remove('open');
        document.getElementById('cart-overlay').classList.remove('show');
    }

    function goToCheckout() {
        window.location.href = '/' + SITE + '/checkout?type=subscription';
    }

    /* ── Card / pagination renderers (unchanged) ────────────── */
    function deliveryPills(plan) {
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

    function renderBadge(plan) {
        if (plan.is_featured && plan.savings_pct) return `<div class="plan-card__badge plan-card__badge--featured">⭐ Featured</div>`;
        if (plan.has_sale && plan.savings_pct) return `<div class="plan-card__badge plan-card__badge--sale">SAVE ${plan.savings_pct}%</div>`;
        if (plan.is_limited_offer) return `<div class="plan-card__badge plan-card__badge--offer">Limited offer</div>`;
        return '';
    }

    function renderPlanCard(plan) {
        const price = parseFloat(plan.has_sale ? plan.sale_price : plan.price) || 0;
        const wasLine = (plan.has_sale && plan.original_price) ? `<div class="plan-card__price-was">${CURRENCY_SYMBOL}${parseFloat(plan.original_price).toFixed(2)}</div>` : '';
        const saleNote = plan.has_sale ? `<div class="plan-card__price-note">🔥 Sale price</div>` : '';
        const btnClass = plan.has_sale ? 'plan-card__btn plan-card__btn--sale' : 'plan-card__btn';
        const btnLabel = plan.has_sale ? '🔥 View deal' : 'View details';
        const priceClass = plan.has_sale ? 'plan-card__price plan-card__price--sale' : 'plan-card__price';
        const desc = plan.description ? `<p class="plan-card__desc">${escHtml(plan.description.substring(0, 110))}${plan.description.length > 110 ? '…' : ''}</p>` : '';
        const featuresHtml = (plan.features && plan.features.length)
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
        return `<article class="plan-card">${renderBadge(plan)}${coverHtml}<div class="plan-card__body">${site}<div class="plan-card__name">${escHtml(plan.name)}</div><div class="plan-card__meta">${deliveryPills(plan)}${catPills}${tagPills}</div>${releaseHtml}${desc}${featuresHtml}<div class="plan-card__pricing"><div><div class="plan-card__from">from</div>${wasLine}<div class="${priceClass}">${CURRENCY_SYMBOL}${price.toFixed(2)}</div></div><div><div class="plan-card__price-period">/ ${escHtml(plan.billing_period || 'month')}</div>${saleNote}</div></div><div style="display:flex;gap:8px;"><a href="${escHtml(plan.detail_url)}" class="${btnClass}" style="flex:1;">${btnLabel}</a><button class="plan-card__btn plan-card__btn--cart" data-delivery_type="${cartDt}" title="Add to cart" onclick="addToCart('plan',${plan.id},this)">🛒</button></div></div></article>`;
    }

    function renderPagination(p) {
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

    /* ── Params / chips ─────────────────────────────────────── */
    function buildParams(page) {
        const fd = new FormData(FORM), p = new URLSearchParams();
        for (const [k, v] of fd.entries()) {
            if (!v || k === 'categories[]' || k === 'tags[]') continue;
            p.set(k, v);
        }
        selectedCategories.forEach(c => p.append('categories[]', c));
        selectedTags.forEach(t => p.append('tags[]', t));
        p.set('page', page);
        if (currentSort) p.set('sort', currentSort);
        return p;
    }

    function countActiveFilters() {
        const fd = new FormData(FORM);
        let n = 0;
        for (const [k, v] of fd.entries()) {
            if (v && k !== 'categories[]' && k !== 'tags[]') n++;
        }
        return n + selectedCategories.size + selectedTags.size;
    }

    function updateMobileFilterBadge() {
        const n = countActiveFilters();
        const countEl = document.getElementById('mobile-filter-count');
        const toggleEl = document.getElementById('mobile-filter-toggle');
        if (!countEl || !toggleEl) return;
        countEl.textContent = n;
        countEl.classList.toggle('visible', n > 0);
        toggleEl.classList.toggle('has-active', n > 0);
    }

    function renderActiveChips() {
        const chips = [], fd = new FormData(FORM);
        const labels = {
            search: 'Search',
            site_id: 'Publication',
            delivery_type: 'Delivery',
            special_filter: 'Offers',
            price_min: 'Min £',
            price_max: 'Max £'
        };
        for (const [k, v] of fd.entries()) {
            if (!v || !labels[k]) continue;
            chips.push(`<div class="active-chip">${labels[k]}: ${escHtml(v)}<button onclick="removeChip('${k}')">×</button></div>`);
        }
        selectedCategories.forEach(c => chips.push(`<div class="active-chip">📂 ${escHtml(c)}<button onclick="removeCategory('${escHtml(c)}')">×</button></div>`));
        selectedTags.forEach(t => chips.push(`<div class="active-chip">🏷 ${escHtml(t.replace(/-/g, ' '))}<button onclick="removeTag('${escHtml(t)}')">×</button></div>`));
        document.getElementById('active-chips').innerHTML = chips.join('');
        updateMobileFilterBadge();
    }

    window.removeChip = k => {
        const el = FORM.querySelector(`[name="${k}"]`);
        if (el) el.value = '';
        fetchPlans(1);
    };
    window.removeCategory = c => {
        selectedCategories.delete(c);
        syncCategoryUI();
        fetchPlans(1);
    };
    window.removeTag = t => {
        selectedTags.delete(t);
        const cb = FORM.querySelector(`input[name="tags[]"][value="${t}"]`);
        if (cb) cb.checked = false;
        fetchPlans(1);
    };

    /* ── Fetch ──────────────────────────────────────────────── */
    async function fetchPlans(page = 1) {
        if (isLoading) return;
        isLoading = true;
        PLANS_WRAP.classList.add('is-loading');
        const params = buildParams(page);
        history.replaceState(null, '', '?' + params.toString());
        renderActiveChips();
        try {
            const res = await fetch('/subscriptions/onetime/search?' + params.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            const data = await res.json();
            if (!data.success) return;
            const result = data.data;
            const countEl = document.getElementById('results-count');
            if (countEl) countEl.innerHTML = `Showing <strong>${result.plans.length}</strong> of <strong>${result.pagination.total.toLocaleString()}</strong> subscriptions`;
            PLANS_WRAP.querySelector('.plans-grid, .empty-state')?.remove();
            document.getElementById('pagination')?.remove();
            if (result.plans.length === 0) {
                PLANS_WRAP.insertAdjacentHTML('afterbegin', `<div class="empty-state"><div class="empty-state__icon">📭</div><div class="empty-state__title">No subscriptions found</div><p>Try adjusting your filters.</p></div>`);
            } else {
                PLANS_WRAP.insertAdjacentHTML('afterbegin', `<div class="plans-grid">${result.plans.map(renderPlanCard).join('')}</div>`);
                PLANS_WRAP.insertAdjacentHTML('beforeend', renderPagination(result.pagination));
                bindPagination();
            }
        } catch (e) {
            console.error(e);
        } finally {
            isLoading = false;
            PLANS_WRAP.classList.remove('is-loading');
        }
    }

    function bindPagination() {
        document.querySelectorAll('#pagination .pagination__btn:not(.disabled)').forEach(btn => {
            btn.addEventListener('click', () => {
                const p = parseInt(btn.dataset.page);
                if (p > 0) fetchPlans(p);
            });
        });
    }

    /* ── Category / tag toggles ─────────────────────────────── */
    window.toggleCategory = cat => {
        selectedCategories.has(cat) ? selectedCategories.delete(cat) : selectedCategories.add(cat);
        syncCategoryUI();
        fetchPlans(1);
    };

    function syncCategoryUI() {
        document.querySelectorAll('[data-category]').forEach(el => {
            const on = selectedCategories.has(el.dataset.category);
            el.classList.toggle('active', on);
            el.classList.toggle('selected', on);
        });
    }

    document.querySelectorAll('#tag-list input[type="checkbox"]').forEach(cb => {
        cb.addEventListener('change', () => {
            cb.checked ? selectedTags.add(cb.value) : selectedTags.delete(cb.value);
            cb.closest('.tag-item').classList.toggle('checked', cb.checked);
            fetchPlans(1);
        });
    });

    /* ── Desktop inputs ─────────────────────────────────────── */
    let searchTimer;
    document.getElementById('search').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => fetchPlans(1), 450);
    });
    FORM.querySelectorAll('select').forEach(s => s.addEventListener('change', () => fetchPlans(1)));
    FORM.querySelectorAll('input[name="price_min"],input[name="price_max"]').forEach(i => i.addEventListener('change', () => fetchPlans(1)));
    FORM.addEventListener('submit', e => {
        e.preventDefault();
        fetchPlans(1);
    });
    SORT_SELECT.addEventListener('change', () => {
        currentSort = SORT_SELECT.value;
        fetchPlans(1);
    });

    window.clearFilters = () => {
        FORM.querySelectorAll('input[type="text"],input[type="number"]').forEach(i => i.value = '');
        FORM.querySelectorAll('select').forEach(s => s.value = '');
        FORM.querySelectorAll('input[type="checkbox"]').forEach(c => {
            c.checked = false;
            c.closest('.tag-item')?.classList.remove('checked');
        });
        selectedCategories.clear();
        selectedTags.clear();
        syncCategoryUI();
        fetchPlans(1);
    };

    /* ── SEARCH CLEAR BUTTON (desktop sidebar) ──────────────── */
    (function initSidebarSearchClear() {
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
            clearTimeout(searchTimer);
            fetchPlans(1);
            input.focus();
        });
    })();

    /* ── MOBILE SEARCH (mirrors desktop search field) ───────── */
    (function initMobileSearch() {
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
            // Keep the desktop (form) field in sync so buildParams() picks it up
            desktopInput.value = mobileInput.value;
            clearTimeout(timer);
            timer = setTimeout(() => fetchPlans(1), 450);
        });

        clearBtn.addEventListener('click', () => {
            mobileInput.value = '';
            desktopInput.value = '';
            syncClear();
            fetchPlans(1);
            mobileInput.focus();
        });
    })();

    /* ── MOBILE FILTER DRAWER ───────────────────────────────── */
    /*
     * Strategy: deep-clone the sidebar__body content into the drawer on first
     * open. The clone contains a separate <form> whose inputs we read when
     * "Apply" is pressed in the drawer — at that point we copy the values back
     * to the canonical #filter-form and call fetchPlans().
     *
     * We never modify the canonical form from the drawer directly to keep a
     * single source of truth and avoid double-event-listener issues.
     */
    let drawerInitialised = false;
    let drawerForm = null;   // the cloned form inside the drawer

    function openFilterDrawer() {
        if (!drawerInitialised) buildDrawer();
        syncDrawerFromSidebar();

        document.getElementById('filter-drawer').classList.add('filter-drawer--open');
        document.getElementById('filter-drawer-overlay').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeFilterDrawer() {
        document.getElementById('filter-drawer').classList.remove('filter-drawer--open');
        document.getElementById('filter-drawer-overlay').classList.remove('show');
        document.body.style.overflow = '';
    }

    function buildDrawer() {
        const body = document.getElementById('filter-drawer-body');
        const sidebarEl = document.querySelector('#desktop-sidebar .sidebar__body');

        // Clone the sidebar body (which contains the canonical form)
        const clone = sidebarEl.cloneNode(true);

        // The clone has a second copy of #filter-form — give it a unique id
        // so it doesn't conflict, and remove the duplicate submit / clear buttons
        // (the drawer has its own footer buttons).
        const clonedForm = clone.querySelector('form');
        if (clonedForm) {
            clonedForm.id = 'drawer-filter-form';
            clonedForm.querySelectorAll('.filter-btn').forEach(btn => btn.remove());
            drawerForm = clonedForm;
        }

        // Remove IDs from cloned inputs to avoid duplicates; use name only
        clone.querySelectorAll('[id]').forEach(el => {
            el.removeAttribute('id');
        });

        body.appendChild(clone);

        // Category pills inside the drawer should use the same toggleCategory()
        clone.querySelectorAll('[data-category]').forEach(el => {
            el.onclick = null; // remove cloned inline handler
            el.addEventListener('click', () => toggleCategory(el.dataset.category));
        });

        // Tag checkboxes
        clone.querySelectorAll('input[type="checkbox"][name="tags[]"]').forEach(cb => {
            cb.addEventListener('change', () => {
                cb.checked ? selectedTags.add(cb.value) : selectedTags.delete(cb.value);
                cb.closest('.tag-item')?.classList.toggle('checked', cb.checked);
                // Don't auto-fetch; user presses Apply
            });
        });

        drawerInitialised = true;
    }

    /** Copy current canonical form values into the drawer form */
    function syncDrawerFromSidebar() {
        if (!drawerForm) return;
        const fd = new FormData(FORM);

        drawerForm.querySelectorAll('input[type="text"],input[type="number"]').forEach(el => {
            const v = fd.get(el.name);
            if (v !== null) el.value = v;
        });

        drawerForm.querySelectorAll('select').forEach(el => {
            const v = fd.get(el.name);
            if (v !== null) el.value = v;
        });

        // Sync category pills
        drawerForm.closest('.filter-drawer__body')?.querySelectorAll('[data-category]').forEach(el => {
            const on = selectedCategories.has(el.dataset.category);
            el.classList.toggle('active', on);
            el.classList.toggle('selected', on);
        });

        // Sync tag checkboxes
        drawerForm.querySelectorAll('input[type="checkbox"][name="tags[]"]').forEach(cb => {
            cb.checked = selectedTags.has(cb.value);
            cb.closest('.tag-item')?.classList.toggle('checked', cb.checked);
        });
    }

    /** Copy drawer form values back to the canonical form and fetch */
    window.drawerApplyFilters = function () {
        if (!drawerForm) return;

        const copyField = name => {
            const src = drawerForm.querySelector(`[name="${name}"]`);
            const dest = FORM.querySelector(`[name="${name}"]`);
            if (src && dest) dest.value = src.value;
        };

        ['search', 'special_filter', 'site_id', 'delivery_type', 'price_min', 'price_max'].forEach(copyField);

        // Tags: drawerForm checkboxes drive selectedTags (via the change
        // listeners wired in buildDrawer); nothing extra needed here.

        closeFilterDrawer();
        fetchPlans(1);
    };

    window.drawerClearFilters = function () {
        if (drawerForm) {
            drawerForm.querySelectorAll('input[type="text"],input[type="number"]').forEach(i => i.value = '');
            drawerForm.querySelectorAll('select').forEach(s => s.value = '');
            drawerForm.querySelectorAll('input[type="checkbox"]').forEach(c => {
                c.checked = false;
                c.closest('.tag-item')?.classList.remove('checked');
            });
        }
        selectedCategories.clear();
        selectedTags.clear();
        syncCategoryUI();
        closeFilterDrawer();
        clearFilters();   // also resets canonical form + fetches
    };

    /* Touch swipe-down to close the drawer */
    (function () {
        const drawer = document.getElementById('filter-drawer');
        let touchY = 0;
        drawer.addEventListener('touchstart', e => {
            touchY = e.touches[0].clientY;
        }, {passive: true});
        drawer.addEventListener('touchend', e => {
            if (e.changedTouches[0].clientY - touchY > 60) closeFilterDrawer();
        }, {passive: true});
    })();

    /* ── Add to cart ────────────────────────────────────────── */
    async function addToCart(type, id, btn) {
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '⏳';
        const endpoint = type === 'plan' ? '/cart/subscription' : '/cart/add-bundle';
        try {
            const res = await fetch(endpoint, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                body: JSON.stringify({
                    type,
                    bundle_id: id,
                    plan_id: id,
                    quantity: 1,
                    delivery_type: btn.dataset.delivery_type
                })
            });
            const data = await res.json();
            if (data.success) {
                btn.innerHTML = '✓';
                btn.style.background = 'var(--green-light)';
                btn.style.color = 'var(--green)';
                setTimeout(() => {
                    btn.innerHTML = original;
                    btn.style.background = '';
                    btn.style.color = '';
                    btn.disabled = false;
                    loadCart();
                }, 2000);
            } else {
                btn.innerHTML = '✗';
                btn.style.background = 'var(--red-light)';
                setTimeout(() => {
                    btn.innerHTML = original;
                    btn.style.background = '';
                    btn.disabled = false;
                }, 2000);
            }
        } catch (e) {
            console.error(e);
            btn.innerHTML = '✗';
            setTimeout(() => {
                btn.innerHTML = original;
                btn.style.background = '';
                btn.disabled = false;
            }, 2000);
        }
    }

    /* ── Bundle carousel (unchanged) ───────────────────────── */
    document.querySelectorAll('[data-carousel]').forEach(section => {
        const track = section.querySelector('[data-track]');
        const viewport = section.querySelector('.carousel-viewport');
        const dotsEl = section.querySelector('[data-dots]');
        const prevBtn = section.querySelector('[data-prev]');
        const nextBtn = section.querySelector('[data-next]');
        const progress = section.querySelector('[data-progress]');
        if (!track) return;

        const slides = Array.from(track.children);
        let current = 0, autoTimer = null, isDragging = false, dragStartX = 0, dragDelta = 0;

        const visibleCount = () => Math.round(viewport.offsetWidth / (slides[0]?.offsetWidth || viewport.offsetWidth));
        const maxIndex = () => Math.max(0, slides.length - visibleCount());

        function buildDots() {
            dotsEl.innerHTML = '';
            for (let i = 0; i <= maxIndex(); i++) {
                const d = document.createElement('button');
                d.className = 'carousel-dot' + (i === current ? ' active' : '');
                d.addEventListener('click', () => goTo(i));
                dotsEl.appendChild(d);
            }
        }

        function updateUI() {
            const w = slides[0]?.offsetWidth || 0;
            track.style.transform = `translateX(-${current * (w + 16)}px)`;
            prevBtn.disabled = current === 0;
            nextBtn.disabled = current >= maxIndex();
            dotsEl.querySelectorAll('.carousel-dot').forEach((d, i) => d.classList.toggle('active', i === current));
            progress.style.width = (maxIndex() === 0 ? 100 : (current / maxIndex()) * 100) + '%';
        }

        function goTo(i) {
            current = Math.max(0, Math.min(i, maxIndex()));
            updateUI();
        }

        const startAuto = () => {
            clearInterval(autoTimer);
            autoTimer = setInterval(() => goTo(current >= maxIndex() ? 0 : current + 1), 4500);
        };
        const stopAuto = () => clearInterval(autoTimer);

        section.addEventListener('mouseenter', stopAuto);
        section.addEventListener('mouseleave', startAuto);
        prevBtn.addEventListener('click', () => {
            stopAuto();
            goTo(current - 1);
            startAuto();
        });
        nextBtn.addEventListener('click', () => {
            stopAuto();
            goTo(current + 1);
            startAuto();
        });

        const onDragStart = x => {
            isDragging = true;
            dragStartX = x;
            dragDelta = 0;
            track.classList.add('is-dragging');
            stopAuto();
        };
        const onDragMove = x => {
            if (!isDragging) return;
            dragDelta = x - dragStartX;
            track.style.transform = `translateX(${-(current * ((slides[0]?.offsetWidth || 0) + 16)) + dragDelta}px)`;
        };
        const onDragEnd = () => {
            if (!isDragging) return;
            isDragging = false;
            track.classList.remove('is-dragging');
            if (dragDelta < -60) goTo(current + 1); else if (dragDelta > 60) goTo(current - 1); else updateUI();
            startAuto();
        };

        track.addEventListener('mousedown', e => onDragStart(e.clientX));
        window.addEventListener('mousemove', e => {
            if (isDragging) onDragMove(e.clientX);
        });
        window.addEventListener('mouseup', onDragEnd);
        track.addEventListener('touchstart', e => onDragStart(e.touches[0].clientX), {passive: true});
        track.addEventListener('touchmove', e => onDragMove(e.touches[0].clientX), {passive: true});
        track.addEventListener('touchend', onDragEnd);
        track.querySelectorAll('a').forEach(a => a.addEventListener('click', e => {
            if (Math.abs(dragDelta) > 8) e.preventDefault();
        }));

        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                current = Math.min(current, maxIndex());
                buildDots();
                updateUI();
            }, 150);
        });

        buildDots();
        updateUI();
        startAuto();
    });

    /* ── Init ───────────────────────────────────────────────── */
    syncCategoryUI();
    renderActiveChips();
    bindPagination();
    loadCart();
</script>
</body>
</html>