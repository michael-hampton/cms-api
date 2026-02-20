<?php
/**
 * View: subscriptions/deals/index.php
 */
$selectedCategories = !empty($filters['categories'])
        ? (is_array($filters['categories']) ? $filters['categories'] : explode(',', $filters['categories']))
        : [];
$selectedTags = !empty($filters['tags'])
        ? (is_array($filters['tags']) ? $filters['tags'] : explode(',', $filters['tags']))
        : [];
?>

<?php
/**
 * View: subscriptions/deals/index.php
 *
 * Variables injected by SubscriptionDealsController::index():
 *   $plans           – on_sale plans only, paginated
 *   $bundles         – active SubscriptionBundle[] with savings data
 *   $pagination
 *   $filters
 *   $available_sites
 *   $available_categories
 *   $available_tags
 *   $price_range
 *   $sort_options
 *   $stripe_key
 */

// Biggest single saving across all bundles — used in hero
$biggestSaving = 0;
$biggestBundle = null;
foreach ($bundles as $b) {
    if ($b['savings_amount'] > $biggestSaving) {
        $biggestSaving = $b['savings_amount'];
        $biggestBundle = $b;
    }
}
$totalDeals = count($plans) + count($bundles);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deals &amp; Bundles</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap"
          rel="stylesheet">
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --ink: #0d0d0d;
            --ink-soft: #3d3d3d;
            --ink-muted: #888;
            --surface: #f5f3ef;
            --white: #fff;
            --border: #e0ddd7;
            --fire: #ff3d00;
            --fire-dark: #c62800;
            --fire-glow: rgba(255, 61, 0, .12);
            --gold: #f5a623;
            --gold-light: rgba(245, 166, 35, .15);
            --teal: #006d77;
            --teal-light: #e3f4f4;
            --ink-2: #1a1a2e;
            --border-soft: #f0eee8;
            --accent: #2d6a4f;
            --accent-light: #d8f3dc;
            --save: #e63946;
            --save-light: #fff0f1;
            --bundle: #1d3557;
            --radius: 12px;
            --radius-sm: 8px;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, .06);
            --shadow: 0 4px 16px rgba(0, 0, 0, .08);
            --shadow-lg: 0 8px 32px rgba(0, 0, 0, .12);
            --font-display: 'DM Serif Display', Georgia, serif;
            --font-body: 'DM Sans', system-ui, sans-serif;
            --transition: all .2s cubic-bezier(.4, 0, .2, 1);
        }

        body {
            font-family: var(--font-body);
            background: var(--surface);
            color: var(--ink);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Hero ─────────────────────────────────────────────────── */
        .deals-hero {
            background: linear-gradient(135deg, #1d3557 0%, #2d6a4f 100%);
            color: #fff;
            padding: 48px 0 40px;
        }

        .deals-hero__inner {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            flex-wrap: wrap;
        }

        .deals-hero__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            background: rgba(255, 255, 255, .15);
            padding: 4px 12px;
            border-radius: 100px;
            margin-bottom: 12px;
        }

        .deals-hero__title {
            font-family: var(--font-display);
            font-size: clamp(28px, 4vw, 44px);
            line-height: 1.1;
            margin-bottom: 8px;
        }

        .deals-hero__sub {
            font-size: 15px;
            opacity: .8;
        }

        .deals-hero__back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: rgba(255, 255, 255, .75);
            font-size: 13px;
            text-decoration: none;
            transition: var(--transition);
        }

        .deals-hero__back:hover {
            color: #fff;
        }

        /* ── Layout ───────────────────────────────────────────────── */
        .layout {
            /*max-width: 1280px; */
            /*margin: 0 auto; */
            padding: 32px 24px;
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 32px;
            align-items: start;
        }

        @media (max-width: 900px) {
            .layout {
                grid-template-columns: 1fr;
            }

            .sidebar {
                display: none;
            }
        }

        /* ── Sidebar ──────────────────────────────────────────────── */
        .sidebar {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px;
            position: sticky;
            top: 24px;
        }

        .sidebar__section {
            margin-bottom: 24px;
        }

        .sidebar__section:last-child {
            margin-bottom: 0;
        }

        .sidebar__label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--ink-muted);
            margin-bottom: 10px;
        }

        .sidebar__search {
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 8px 12px;
            background: var(--surface);
            transition: var(--transition);
        }

        .sidebar__search:focus-within {
            border-color: var(--save);
            box-shadow: 0 0 0 3px var(--save-light);
        }

        .sidebar__search input {
            border: none;
            background: none;
            outline: none;
            font-family: var(--font-body);
            font-size: 14px;
            color: var(--ink);
            width: 100%;
        }

        .sidebar__search svg {
            flex-shrink: 0;
            color: var(--ink-muted);
        }

        .filter-select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-family: var(--font-body);
            font-size: 14px;
            color: var(--ink);
            background: var(--surface);
            cursor: pointer;
            outline: none;
        }

        .filter-select:focus {
            border-color: var(--save);
            box-shadow: 0 0 0 3px var(--save-light);
        }

        .price-range-row {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .price-range-row input {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-family: var(--font-body);
            font-size: 14px;
            color: var(--ink);
            background: var(--surface);
            outline: none;
        }

        .price-range-row input:focus {
            border-color: var(--save);
        }

        .price-range-row span {
            color: var(--ink-muted);
            font-size: 13px;
            flex-shrink: 0;
        }

        .category-pill {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 7px 12px;
            border-radius: 100px;
            border: 1px solid var(--border);
            background: var(--white);
            cursor: pointer;
            font-size: 13px;
            font-family: var(--font-body);
            color: var(--ink-soft);
            transition: var(--transition);
            width: 100%;
            text-align: left;
            margin-bottom: 4px;
        }

        .category-pill:hover {
            border-color: var(--ink);
            color: var(--ink);
        }

        .category-pill.active {
            font-weight: 600;
            color: var(--ink);
            border-color: var(--ink);
            background: var(--surface);
        }

        .category-pill__dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .tag-list {
            display: flex;
            flex-direction: column;
            gap: 4px;
            max-height: 220px;
            overflow-y: auto;
        }

        .tag-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition);
            font-size: 13px;
            color: var(--ink-soft);
        }

        .tag-item:hover {
            background: var(--surface);
            color: var(--ink);
        }

        .tag-item input[type="checkbox"] {
            width: 15px;
            height: 15px;
            cursor: pointer;
            accent-color: var(--save);
            flex-shrink: 0;
        }

        .tag-item.checked {
            color: var(--ink);
            font-weight: 500;
        }

        .filter-btn {
            width: 100%;
            padding: 10px;
            background: var(--save);
            color: #fff;
            border: none;
            border-radius: var(--radius-sm);
            font-family: var(--font-body);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .filter-btn:hover {
            background: #c1121f;
        }

        .filter-btn--clear {
            background: var(--white);
            color: var(--ink-soft);
            border: 1px solid var(--border);
            margin-bottom: 8px;
        }

        .filter-btn--clear:hover {
            background: var(--surface);
            color: var(--ink);
        }

        /* Active chips */
        .active-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 16px;
        }

        .active-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--save);
            color: #fff;
            padding: 4px 10px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 500;
        }

        .active-chip button {
            background: none;
            border: none;
            color: #fff;
            cursor: pointer;
            font-size: 14px;
            line-height: 1;
            padding: 0;
            opacity: .7;
        }

        .active-chip button:hover {
            opacity: 1;
        }

        /* ── Main ─────────────────────────────────────────────────── */
        .main {
            min-width: 0;
        }

        /* ── Bundles carousel ────────────────────────────────────────── */
        .bundles-section {
            margin-bottom: 36px;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            gap: 16px;
        }

        .section-header__left {
            display: flex;
            align-items: baseline;
            gap: 12px;
        }

        .section-title {
            font-family: var(--font-display);
            font-size: 22px;
            color: var(--ink);
        }

        .section-link {
            font-size: 13px;
            color: var(--save);
            text-decoration: none;
            font-weight: 500;
        }

        .section-link:hover {
            text-decoration: underline;
        }

        .carousel-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .carousel-arrow {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            border: 1.5px solid var(--border);
            background: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            flex-shrink: 0;
            color: var(--ink);
        }

        .carousel-arrow:hover:not(:disabled) {
            border-color: var(--save);
            background: var(--save);
            color: #fff;
        }

        .carousel-arrow:disabled {
            opacity: .3;
            cursor: default;
        }

        .carousel-dots {
            display: flex;
            gap: 5px;
            align-items: center;
        }

        .carousel-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--border);
            border: none;
            padding: 0;
            cursor: pointer;
            transition: var(--transition);
        }

        .carousel-dot.active {
            background: var(--save);
            width: 20px;
            border-radius: 3px;
        }

        .carousel-viewport {
            overflow: hidden;
            border-radius: var(--radius);
            -webkit-mask-image: linear-gradient(to right, transparent 0%, black 3%, black 97%, transparent 100%);
            mask-image: linear-gradient(to right, transparent 0%, black 3%, black 97%, transparent 100%);
        }

        .carousel-track {
            display: flex;
            gap: 16px;
            transition: transform .45s cubic-bezier(.4, 0, .2, 1);
            will-change: transform;
            cursor: grab;
            user-select: none;
        }

        .carousel-track.is-dragging {
            cursor: grabbing;
            transition: none;
        }

        .bundle-slide {
            flex: 0 0 calc(33.333% - 11px);
            min-width: 0;
        }

        @media (max-width: 1100px) {
            .bundle-slide {
                flex: 0 0 calc(50% - 8px);
            }
        }

        @media (max-width: 640px) {
            .bundle-slide {
                flex: 0 0 85%;
            }
        }

        .carousel-progress {
            height: 2px;
            background: var(--border);
            border-radius: 1px;
            margin-top: 16px;
            overflow: hidden;
        }

        .carousel-progress__fill {
            height: 100%;
            background: var(--save);
            border-radius: 1px;
            transition: width .45s cubic-bezier(.4, 0, .2, 1);
        }

        /* Bundle card */
        .bundle-card {
            background: var(--bundle);
            color: #fff;
            border-radius: var(--radius);
            padding: 24px;
            position: relative;
            overflow: hidden;
            transition: transform .2s ease, box-shadow .2s ease;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .bundle-card::before {
            content: '';
            position: absolute;
            top: -40px;
            right: -40px;
            width: 160px;
            height: 160px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .06);
            pointer-events: none;
        }

        .bundle-card::after {
            content: '';
            position: absolute;
            bottom: -60px;
            left: -20px;
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .04);
            pointer-events: none;
        }

        .bundle-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .bundle-card__badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--save);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 100px;
            margin-bottom: 14px;
            align-self: flex-start;
        }

        .bundle-card__name {
            font-family: var(--font-display);
            font-size: 20px;
            line-height: 1.2;
            margin-bottom: 6px;
        }

        .bundle-card__desc {
            font-size: 13px;
            opacity: .75;
            margin-bottom: 16px;
            line-height: 1.5;
        }

        .bundle-card__plans {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 20px;
        }

        .bundle-card__plan-tag {
            background: rgba(255, 255, 255, .12);
            border: 1px solid rgba(255, 255, 255, .2);
            border-radius: 100px;
            padding: 3px 10px;
            font-size: 12px;
            font-weight: 500;
        }

        .bundle-card__pricing {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            margin-top: auto;
        }

        .bundle-card__price {
            font-size: 28px;
            font-weight: 700;
            font-family: var(--font-display);
            line-height: 1;
        }

        .bundle-card__was {
            font-size: 14px;
            opacity: .6;
            text-decoration: line-through;
            margin-bottom: 3px;
        }

        .bundle-card__cta {
            margin-left: auto;
            background: rgba(255, 255, 255, .15);
            border: 1.5px solid rgba(255, 255, 255, .35);
            color: #fff;
            padding: 8px 16px;
            border-radius: 100px;
            font-size: 13px;
            font-weight: 600;
            font-family: var(--font-body);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            flex-shrink: 0;
        }

        .bundle-card__cta:hover {
            background: rgba(255, 255, 255, .28);
        }

        /* Toolbar */
        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            gap: 12px;
            flex-wrap: wrap;
        }

        .toolbar__count {
            font-size: 14px;
            color: var(--ink-soft);
        }

        .toolbar__count strong {
            color: var(--ink);
        }

        .toolbar__sort {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: var(--ink-soft);
        }

        .toolbar__sort select {
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 6px 10px;
            font-family: var(--font-body);
            font-size: 13px;
            color: var(--ink);
            background: var(--white);
            outline: none;
            cursor: pointer;
        }

        /* Plans grid */
        .plans-grid {
            display: grid;
            grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .plan-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            transition: var(--transition);
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .plan-card:hover {
            border-color: var(--save);
            box-shadow: var(--shadow);
            transform: translateY(-2px);
        }

        .plan-card__badge {
            position: absolute;
            top: 14px;
            right: 0;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .05em;
            padding: 4px 12px 4px 10px;
            border-radius: 2px 0 0 2px;
            z-index: 2;
            color: #fff;
        }

        .plan-card__badge::after {
            content: '';
            position: absolute;
            right: 0;
            top: 100%;
            width: 0;
            height: 0;
        }

        .plan-card__badge--sale {
            background: var(--save);
        }

        .plan-card__badge--sale::after {
            border-top: 4px solid #a61220;
        }

        .plan-card__badge--offer {
            background: #7c3aed;
        }

        .plan-card__badge--offer::after {
            border-top: 4px solid #4c1d95;
        }

        .plan-card__image {
            width: 100%;
            height: 140px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: 700;
            color: #fff;
            background: linear-gradient(135deg, #1d3557 0%, #e63946 100%);
            flex-shrink: 0;
        }

        .plan-card__body {
            padding: 16px 18px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .plan-card__site {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--ink-muted);
            margin-bottom: 4px;
        }

        .plan-card__name {
            font-family: var(--font-display);
            font-size: 17px;
            line-height: 1.25;
            color: var(--ink);
            margin-bottom: 8px;
        }

        .plan-card__meta {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-bottom: 10px;
        }

        .meta-pill {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 100px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--ink-soft);
        }

        .meta-pill--digital {
            background: #dbeafe;
            color: #1e40af;
            border-color: #bfdbfe;
        }

        .meta-pill--print {
            background: #fce7f3;
            color: #9f1239;
            border-color: #fbcfe8;
        }

        .meta-pill--category {
            background: var(--surface);
            color: var(--ink-soft);
        }

        .meta-pill--tag {
            background: #e0e7ff;
            color: #3730a3;
            border-color: #c7d2fe;
        }

        .plan-card__desc {
            font-size: 13px;
            color: var(--ink-soft);
            line-height: 1.5;
            margin-bottom: 12px;
            flex: 1;
        }

        .plan-card__pricing {
            padding: 12px 0;
            border-top: 1px solid var(--border-soft);
            margin-bottom: 12px;
            display: flex;
            align-items: flex-end;
            gap: 8px;
        }

        .plan-card__price {
            font-size: 24px;
            font-weight: 700;
            color: var(--save);
            line-height: 1;
        }

        .plan-card__price-was {
            font-size: 13px;
            color: var(--ink-muted);
            text-decoration: line-through;
            margin-bottom: 2px;
        }

        .plan-card__price-period {
            font-size: 12px;
            color: var(--ink-muted);
            margin-bottom: 2px;
        }

        .plan-card__price-note {
            font-size: 11px;
            color: var(--save);
            font-weight: 600;
        }

        .plan-card__from {
            font-size: 12px;
            color: var(--ink-muted);
            margin-bottom: 2px;
        }

        .plan-card__btn {
            display: block;
            width: 100%;
            padding: 11px;
            background: var(--save);
            color: #fff;
            border: none;
            border-radius: var(--radius-sm);
            font-family: var(--font-body);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
            text-decoration: none;
        }

        .plan-card__btn:hover {
            background: #c1121f;
        }

        /* Empty / loading */
        .empty-state {
            text-align: center;
            padding: 80px 24px;
            color: var(--ink-soft);
        }

        .empty-state__icon {
            font-size: 48px;
            margin-bottom: 16px;
        }

        .empty-state__title {
            font-family: var(--font-display);
            font-size: 22px;
            color: var(--ink);
            margin-bottom: 8px;
        }

        .loading-overlay {
            pointer-events: none;
            transition: opacity .2s;
        }

        .loading-overlay.is-loading {
            opacity: 0.45;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 40px;
            align-items: center;
            flex-wrap: wrap;
        }

        .pagination__btn {
            min-width: 36px;
            height: 36px;
            padding: 0 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--white);
            cursor: pointer;
            font-family: var(--font-body);
            font-size: 14px;
            color: var(--ink);
            text-decoration: none;
            transition: var(--transition);
        }

        .pagination__btn:hover:not(.disabled):not(.active) {
            border-color: var(--save);
        }

        .pagination__btn.active {
            background: var(--save);
            color: #fff;
            border-color: var(--save);
        }

        .pagination__btn.disabled {
            opacity: .4;
            pointer-events: none;
            cursor: default;
        }

        .pagination__ellipsis {
            padding: 0 4px;
            color: var(--ink-muted);
            display: flex;
            align-items: center;
        }

        /* ── Ticker tape ─────────────────────────────────────────── */
        .ticker {
            background: var(--fire);
            color: #fff;
            padding: 9px 0;
            overflow: hidden;
            position: relative;
        }

        .ticker__track {
            display: flex;
            gap: 0;
            animation: ticker 28s linear infinite;
            width: max-content;
        }

        .ticker__item {
            white-space: nowrap;
            font-family: var(--font-display);
            font-size: 12px;
            letter-spacing: .12em;
            text-transform: uppercase;
            padding: 0 40px;
        }

        .ticker__item::before {
            content: '★  ';
        }

        @keyframes ticker {
            from {
                transform: translateX(0);
            }
            to {
                transform: translateX(-50%);
            }
        }

        /* ── Hero ────────────────────────────────────────────────── */
        .hero {
            background: var(--ink);
            color: #fff;
            padding: 72px 24px 64px;
            position: relative;
            overflow: hidden;
        }

        /* Grid noise texture overlay */
        .hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: repeating-linear-gradient(0deg, transparent, transparent 39px, rgba(255, 255, 255, .03) 40px),
            repeating-linear-gradient(90deg, transparent, transparent 39px, rgba(255, 255, 255, .03) 40px);
            pointer-events: none;
        }

        /* Blurred fire orb */
        .hero::after {
            content: '';
            position: absolute;
            right: -100px;
            top: -100px;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255, 61, 0, .35) 0%, transparent 70%);
            pointer-events: none;
            animation: pulse 6s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.1);
                opacity: .7;
            }
        }

        .hero__inner {
            max-width: 1280px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .hero__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--fire);
            color: #fff;
            font-family: var(--font-display);
            font-size: 11px;
            letter-spacing: .12em;
            text-transform: uppercase;
            padding: 6px 14px;
            border-radius: 100px;
            margin-bottom: 24px;
        }

        .hero__eyebrow::before {
            content: '🔥';
            font-size: 14px;
        }

        .hero__title {
            font-family: var(--font-display);
            font-size: clamp(48px, 8vw, 96px);
            line-height: .95;
            letter-spacing: -.02em;
            margin-bottom: 20px;
        }

        .hero__title em {
            font-style: italic;
            color: var(--gold);
            -webkit-text-fill-color: transparent;
            background: linear-gradient(135deg, var(--gold), #ffcd6b);
            -webkit-background-clip: text;
            background-clip: text;
        }

        .hero__sub {
            font-size: 18px;
            color: rgba(255, 255, 255, .65);
            max-width: 480px;
            line-height: 1.6;
            font-weight: 300;
            margin-bottom: 40px;
        }

        .hero__stats {
            display: flex;
            gap: 40px;
            flex-wrap: wrap;
        }

        .hero__stat-num {
            font-family: var(--font-display);
            font-size: 36px;
            line-height: 1;
            color: #fff;
        }

        .hero__stat-label {
            font-size: 13px;
            color: rgba(255, 255, 255, .5);
            margin-top: 4px;
        }

        /* ── Biggest bundle spotlight ────────────────────────────── */
        <?php if ($biggestBundle): ?>
        .spotlight {
            background: var(--white);
            border-bottom: 1px solid var(--border);
            padding: 0;
            overflow: hidden;
        }

        .spotlight__inner {
            max-width: 1280px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 420px;
            min-height: 220px;
        }

        @media (max-width: 800px) {
            .spotlight__inner {
                grid-template-columns: 1fr;
            }

            .spotlight__visual {
                display: none;
            }
        }

        .spotlight__content {
            padding: 40px 48px 40px 32px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .spotlight__label {
            font-family: var(--font-display);
            font-size: 11px;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--fire);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .spotlight__label::before {
            content: '';
            display: block;
            width: 20px;
            height: 2px;
            background: var(--fire);
        }

        .spotlight__name {
            font-family: var(--font-display);
            font-size: clamp(22px, 3vw, 32px);
            line-height: 1.1;
            margin-bottom: 8px;
        }

        .spotlight__desc {
            font-size: 14px;
            color: var(--ink-soft);
            max-width: 400px;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .spotlight__pricing {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .spotlight__price {
            font-family: var(--font-display);
            font-size: 38px;
            line-height: 1;
            color: var(--ink);
        }

        .spotlight__was {
            font-size: 16px;
            color: var(--ink-muted);
            text-decoration: line-through;
        }

        .spotlight__save-badge {
            background: var(--fire-glow);
            border: 1px solid var(--fire);
            color: var(--fire);
            font-family: var(--font-display);
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .06em;
            padding: 5px 12px;
            border-radius: 100px;
        }

        .spotlight__cta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--fire);
            color: #fff;
            padding: 12px 24px;
            border-radius: 100px;
            font-family: var(--font-display);
            font-size: 14px;
            font-weight: 700;
            letter-spacing: .04em;
            text-decoration: none;
            transition: var(--transition);
            margin-top: 20px;
            align-self: flex-start;
        }

        .spotlight__cta:hover {
            background: var(--fire-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px var(--fire-glow);
        }

        .spotlight__visual {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 60%, #0f3460 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .spotlight__visual::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at 30% 70%, rgba(255, 61, 0, .2) 0%, transparent 60%),
            radial-gradient(ellipse at 80% 20%, rgba(245, 166, 35, .15) 0%, transparent 50%);
        }

        .spotlight__visual-text {
            position: relative;
            text-align: center;
            color: #fff;
        }

        .spotlight__visual-save {
            font-family: var(--font-display);
            font-size: 80px;
            line-height: 1;
            letter-spacing: -.03em;
            color: var(--gold);
            -webkit-text-fill-color: transparent;
            background: linear-gradient(135deg, var(--gold), #ffcd6b);
            -webkit-background-clip: text;
            background-clip: text;
        }

        .spotlight__visual-label {
            font-size: 13px;
            letter-spacing: .1em;
            text-transform: uppercase;
            opacity: .7;
            margin-top: 4px;
            font-family: var(--font-display);
        }

        <?php endif; ?>
    </style>
</head>
<body>

<!-- Ticker tape -->
<div class="ticker" aria-hidden="true">
    <div class="ticker__track">
        <?php for ($i = 0; $i < 2; $i++): ?>
            <span class="ticker__item">Limited time deals</span>
            <span class="ticker__item">Bundle and save</span>
            <span class="ticker__item">Exclusive subscriber savings</span>
            <span class="ticker__item">More for less</span>
            <span class="ticker__item">Deals ending soon</span>
            <span class="ticker__item">Save up to <?= $biggestBundle ? $biggestBundle['discount_percentage'] : 30 ?>%</span>
            <span class="ticker__item">Subscribe today</span>
            <span class="ticker__item">Limited time deals</span>
        <?php endfor; ?>
    </div>
</div>

<!-- Hero -->
<section class="hero">
    <div class="hero__inner">
        <div class="hero__eyebrow">Live deals — updated daily</div>
        <h1 class="hero__title">
            Deals &amp;<br><em>Bundles</em>
        </h1>
        <p class="hero__sub">
            The best prices on the publications you love — sale subscriptions and exclusive bundles in one place.
        </p>
        <div class="hero__stats">
            <div>
                <div class="hero__stat-num"><?= $totalDeals ?>+</div>
                <div class="hero__stat-label">Live deals</div>
            </div>
            <?php if (!empty($bundles)): ?>
                <div>
                    <div class="hero__stat-num"><?= count($bundles) ?></div>
                    <div class="hero__stat-label">Bundle<?= count($bundles) !== 1 ? 's' : '' ?> available</div>
                </div>
            <?php endif; ?>
            <?php if ($biggestBundle): ?>
                <div>
                    <div class="hero__stat-num">£<?= number_format($biggestBundle['savings_amount'], 0) ?></div>
                    <div class="hero__stat-label">Biggest saving</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($biggestBundle): ?>
    <!-- Spotlight — biggest bundle -->
    <div class="spotlight">
        <div class="spotlight__inner">
            <div class="spotlight__content">
                <div class="spotlight__label">Featured bundle</div>
                <h2 class="spotlight__name"><?= htmlspecialchars($biggestBundle['name']) ?></h2>
                <?php if ($biggestBundle['description']): ?>
                    <p class="spotlight__desc"><?= htmlspecialchars($biggestBundle['description']) ?></p>
                <?php endif; ?>
                <div class="spotlight__pricing">
                    <div class="spotlight__price">£<?= number_format($biggestBundle['bundle_price'], 2) ?></div>
                    <div class="spotlight__was">£<?= number_format($biggestBundle['total_price'], 2) ?></div>
                    <div class="spotlight__save-badge">SAVE <?= $biggestBundle['discount_percentage'] ?>%</div>
                </div>
                <a href="<?= url('/subscriptions/bundles/' . $biggestBundle['slug']) ?>" class="spotlight__cta">
                    Get this bundle →
                </a>
            </div>
            <div class="spotlight__visual">
                <div class="spotlight__visual-text">
                    <div class="spotlight__visual-save">-<?= $biggestBundle['discount_percentage'] ?>%</div>
                    <div class="spotlight__visual-label">You save
                        £<?= number_format($biggestBundle['savings_amount'], 2) ?></div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<a href="<?= url('/subscriptions') ?>" class="back-link">← All subscriptions</a>

<header class="deals-hero">
    <div class="deals-hero__inner">
        <div>
            <div class="deals-hero__eyebrow">🔥 Limited time</div>
            <h1 class="deals-hero__title">Deals &amp; Bundles</h1>
            <p class="deals-hero__sub" id="hero-sub">
                <?= number_format($pagination['total']) ?> discounted
                subscription<?= $pagination['total'] !== 1 ? 's' : '' ?> available right now
            </p>
        </div>
        <a href="<?= url('/subscriptions') ?>" class="deals-hero__back">← Back to all subscriptions</a>
    </div>
</header>

<div class="layout">

    <!-- Sidebar -->
    <aside class="sidebar">
        <form id="filter-form">

            <div class="sidebar__section">
                <div class="sidebar__label">Search</div>
                <label class="sidebar__search">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input type="text" id="search" name="search"
                           value="<?= htmlspecialchars($filters['search'] ?? '') ?>" placeholder="Publication name…">
                </label>
            </div>

            <?php if (!empty($available_sites)): ?>
                <div class="sidebar__section">
                    <div class="sidebar__label">Publication</div>
                    <select name="site_id" class="filter-select">
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
                <select name="delivery_type" class="filter-select">
                    <option value="">Print &amp; Digital</option>
                    <option value="digital" <?= ($filters['delivery_type'] ?? '') === 'digital' ? 'selected' : '' ?>>
                        Digital only
                    </option>
                    <option value="print" <?= ($filters['delivery_type'] ?? '') === 'print' ? 'selected' : '' ?>>Print
                        only
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
                    <input type="number" name="price_min" value="<?= htmlspecialchars($filters['price_min'] ?? '') ?>"
                           placeholder="£<?= $price_range['min'] ?? 0 ?>" step="0.01" min="0">
                    <span>–</span>
                    <input type="number" name="price_max" value="<?= htmlspecialchars($filters['price_max'] ?? '') ?>"
                           placeholder="£<?= $price_range['max'] ?? 999 ?>" step="0.01" min="0">
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
    </aside>

    <!-- Main -->
    <main class="main">

        <div class="active-chips" id="active-chips"></div>

        <!-- Bundles -->
        <?php if (!empty($bundles)): ?>
            <section class="bundles-section" data-carousel>
                <div class="section-header">
                    <div class="section-header__left">
                        <h2 class="section-title">Bundle deals</h2>
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
                                            <div class="bundle-card__was">Was
                                                £<?= number_format($bundle['total_price'], 2) ?></div>
                                            <div class="bundle-card__price">
                                                £<?= number_format($bundle['bundle_price'], 2) ?></div>
                                        </div>
                                        <button data-delivery_type="" class="bundle-card__cta"
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
                <strong><?= number_format($pagination['total']) ?></strong> deals
            </div>
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

        <!-- Plans -->
        <div id="plans-wrap">
            <?php if (empty($plans)): ?>
                <div class="empty-state">
                    <div class="empty-state__icon">🏷️</div>
                    <div class="empty-state__title">No deals found</div>
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
                        $displayPrice = $hasSale ? $salePrice : ($plan->price ?? 0);
                        $letter = strtoupper(substr($plan->name, 0, 1));
                        $detailUrl = url('/subscriptions/' . $plan->id);
                        ?>
                        <article class="plan-card">
                            <?php if ($hasSale): ?>
                                <div class="plan-card__badge plan-card__badge--sale">SAVE <?= $savingPct ?>%</div>
                            <?php else: ?>
                                <div class="plan-card__badge plan-card__badge--offer">On sale</div>
                            <?php endif; ?>

                            <div class="plan-card__image"><?= $letter ?></div>

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
                                        <span class="meta-pill meta-pill--category"><?= htmlspecialchars(ucfirst($cat)) ?></span>
                                    <?php endforeach; ?>
                                    <?php foreach (array_slice((array)($plan->tags ?? []), 0, 2) as $tag): ?>
                                        <span class="meta-pill meta-pill--tag"><?= htmlspecialchars(ucwords(str_replace('-', ' ', $tag))) ?></span>
                                    <?php endforeach; ?>
                                </div>

                                <?php if (!empty($plan->description)): ?>
                                    <p class="plan-card__desc">
                                        <?= htmlspecialchars(mb_substr($plan->description, 0, 110)) ?><?= mb_strlen($plan->description) > 110 ? '…' : '' ?>
                                    </p>
                                <?php endif; ?>

                                <div class="plan-card__pricing">
                                    <div>
                                        <div class="plan-card__from">from</div>
                                        <?php if ($hasSale && $originalPrice): ?>
                                            <div class="plan-card__price-was">
                                                £<?= number_format($originalPrice, 2) ?></div>
                                        <?php endif; ?>
                                        <div class="plan-card__price">£<?= number_format($displayPrice, 2) ?></div>
                                    </div>
                                    <div>
                                        <div class="plan-card__price-period">
                                            / <?= htmlspecialchars($plan->billing_period ?? 'month') ?></div>
                                        <div class="plan-card__price-note">🔥 Sale price</div>
                                    </div>
                                </div>

                                <div style="display:flex; gap:8px;">
                                    <a href="<?= $detailUrl ?>"
                                       class="plan-card__btn <?= $hasSale ? 'plan-card__btn--sale' : '' ?>"
                                       style="flex:1;">
                                        <?= $hasSale ? '🔥 View deal' : 'View details' ?>
                                    </a>
                                    <button data-delivery_type="<?= $plan->delivery_type === 'digital' || $plan->hasDigitalOption() ? 'digital' : 'print' ?>"
                                            class="plan-card__btn"
                                            style="flex-shrink:0; width:auto; padding:11px 14px; background:var(--surface); color:var(--ink); border:1px solid var(--border);"
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
                        <button class="pagination__btn <?= $pagination['current_page'] <= 1 ? 'disabled' : '' ?>"
                                data-page="<?= $pagination['current_page'] - 1 ?>">←
                        </button>
                        <?php
                        $start = max(1, $pagination['current_page'] - 2);
                        $end = min($pagination['total_pages'], $pagination['current_page'] + 2);
                        if ($start > 1): ?>
                            <button class="pagination__btn" data-page="1">1</button><?php endif; ?>
                        <?php if ($start > 2): ?><span class="pagination__ellipsis">…</span><?php endif; ?>
                        <?php for ($p = $start; $p <= $end; $p++): ?>
                            <button class="pagination__btn <?= $p === $pagination['current_page'] ? 'active' : '' ?>"
                                    data-page="<?= $p ?>"><?= $p ?></button>
                        <?php endfor; ?>
                        <?php if ($end < $pagination['total_pages'] - 1): ?><span
                                class="pagination__ellipsis">…</span><?php endif; ?>
                        <?php if ($end < $pagination['total_pages']): ?>
                            <button class="pagination__btn"
                                    data-page="<?= $pagination['total_pages'] ?>"><?= $pagination['total_pages'] ?></button><?php endif; ?>
                        <button class="pagination__btn <?= $pagination['current_page'] >= $pagination['total_pages'] ? 'disabled' : '' ?>"
                                data-page="<?= $pagination['current_page'] + 1 ?>">→
                        </button>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </main>
</div>

<script>
    const FORM = document.getElementById('filter-form');
    const SORT_SELECT = document.getElementById('sort-select');
    const PLANS_WRAP = document.getElementById('plans-wrap');

    let selectedCategories = new Set(<?= json_encode($selectedCategories) ?>);
    let selectedTags = new Set(<?= json_encode($selectedTags) ?>);
    let currentSort = <?= json_encode($filters['sort'] ?? '') ?>;
    let isLoading = false;

    function escHtml(s) {
        if (!s) return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function deliveryBadges(type) {
        const b = [];
        if (type === 'digital' || type === 'both') b.push('<span class="meta-pill meta-pill--digital">📱 Digital</span>');
        if (type === 'print' || type === 'both') b.push('<span class="meta-pill meta-pill--print">📰 Print</span>');
        return b.join('');
    }

    function renderPlanCard(plan) {
        const price = parseFloat(plan.has_sale ? plan.sale_price : plan.price) || 0;
        const wasLine = (plan.has_sale && plan.original_price)
            ? `<div class="plan-card__price-was">£${parseFloat(plan.original_price).toFixed(2)}</div>` : '';
        const badge = (plan.has_sale && plan.savings_pct)
            ? `<div class="plan-card__badge plan-card__badge--sale">SAVE ${plan.savings_pct}%</div>`
            : `<div class="plan-card__badge plan-card__badge--offer">On sale</div>`;
        const site = plan.site_name ? `<div class="plan-card__site">${escHtml(plan.site_name)}</div>` : '';
        const desc = plan.description ? `<p class="plan-card__desc">${escHtml(plan.description.substring(0, 110))}${plan.description.length > 110 ? '…' : ''}</p>` : '';
        const catPills = (plan.categories || []).slice(0, 2).map(c => `<span class="meta-pill meta-pill--category">${escHtml(c.charAt(0).toUpperCase() + c.slice(1))}</span>`).join('');
        const tagPills = (plan.tags || []).slice(0, 2).map(t => `<span class="meta-pill meta-pill--tag">${escHtml(t.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase()))}</span>`).join('');

        return `
    <article class="plan-card">
        ${badge}
        <div class="plan-card__image">${escHtml((plan.name || '?')[0].toUpperCase())}</div>
        <div class="plan-card__body">
            ${site}
            <div class="plan-card__name">${escHtml(plan.name)}</div>
            <div class="plan-card__meta">
                ${deliveryBadges(plan.delivery_type)}
                ${catPills}${tagPills}
            </div>
            ${desc}
            <div class="plan-card__pricing">
                <div>
                    <div class="plan-card__from">from</div>
                    ${wasLine}
                    <div class="plan-card__price">£${price.toFixed(2)}</div>
                </div>
                <div>
                    <div class="plan-card__price-period">/ ${escHtml(plan.billing_period || 'month')}</div>
                    <div class="plan-card__price-note">🔥 Sale price</div>
                </div>
            </div>
            <div style="display:flex; gap:8px;">
    <a href="${escHtml(plan.detail_url)}" class="${btnClass}" style="flex:1;">${btnLabel}</a>
    <button class="plan-card__btn" data-delivery-type="${plan.delivery_type}"
            style="flex-shrink:0;width:auto;padding:11px 14px;background:var(--surface);color:var(--ink);border:1px solid var(--border);"
            title="Add to cart"
<button onclick="addToCart('plan', plan.id, this)">
</div>
        </div>
    </article>`;
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

    function renderActiveChips() {
        const chips = [];
        const fd = new FormData(FORM);
        const labels = {
            search: 'Search',
            site_id: 'Publication',
            delivery_type: 'Delivery',
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
    }

    window.removeChip = k => {
        const el = FORM.querySelector(`[name="${k}"]`);
        if (el) el.value = '';
        fetchDeals(1);
    };
    window.removeCategory = c => {
        selectedCategories.delete(c);
        syncCategoryUI();
        fetchDeals(1);
    };
    window.removeTag = t => {
        selectedTags.delete(t);
        const cb = FORM.querySelector(`input[name="tags[]"][value="${t}"]`);
        if (cb) cb.checked = false;
        fetchDeals(1);
    };

    async function fetchDeals(page = 1) {
        if (isLoading) return;
        isLoading = true;
        PLANS_WRAP.classList.add('is-loading');

        const params = buildParams(page);
        history.replaceState(null, '', '?' + params.toString());
        renderActiveChips();

        try {
            const res = await fetch('/subscriptions/onetime/deals/search?' + params.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            const data = await res.json();
            if (!data.success) return;

            const result = data.data

            const countEl = document.getElementById('results-count');
            if (countEl) countEl.innerHTML = `Showing <strong>${result.plans.length}</strong> of <strong>${result.pagination.total.toLocaleString()}</strong> deals`;

            const heroSub = document.getElementById('hero-sub');
            if (heroSub) heroSub.textContent = `${result.pagination.total.toLocaleString()} discounted subscription${result.pagination.total !== 1 ? 's' : ''} available right now`;

            PLANS_WRAP.querySelector('.plans-grid,.empty-state')?.remove();
            document.getElementById('pagination')?.remove();

            if (result.plans.length === 0) {
                PLANS_WRAP.insertAdjacentHTML('afterbegin', `<div class="empty-state"><div class="empty-state__icon">🏷️</div><div class="empty-state__title">No deals found</div><p>Try adjusting your filters.</p></div>`);
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
                if (p > 0) fetchDeals(p);
            });
        });
    }

    window.toggleCategory = function (cat) {
        selectedCategories.has(cat) ? selectedCategories.delete(cat) : selectedCategories.add(cat);
        syncCategoryUI();
        fetchDeals(1);
    };

    function syncCategoryUI() {
        document.querySelectorAll('[data-category]').forEach(el => {
            el.classList.toggle('active', selectedCategories.has(el.dataset.category));
        });
    }

    document.querySelectorAll('#tag-list input[type="checkbox"]').forEach(cb => {
        cb.addEventListener('change', () => {
            cb.checked ? selectedTags.add(cb.value) : selectedTags.delete(cb.value);
            cb.closest('.tag-item').classList.toggle('checked', cb.checked);
            fetchDeals(1);
        });
    });

    let searchTimer;
    document.getElementById('search').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => fetchDeals(1), 450);
    });
    FORM.querySelectorAll('select').forEach(s => s.addEventListener('change', () => fetchDeals(1)));
    FORM.querySelectorAll('input[name="price_min"],input[name="price_max"]').forEach(i => i.addEventListener('change', () => fetchDeals(1)));
    FORM.addEventListener('submit', e => {
        e.preventDefault();
        fetchDeals(1);
    });
    SORT_SELECT.addEventListener('change', () => {
        currentSort = SORT_SELECT.value;
        fetchDeals(1);
    });

    window.clearFilters = function () {
        FORM.querySelectorAll('input[type="text"],input[type="number"]').forEach(i => i.value = '');
        FORM.querySelectorAll('select').forEach(s => s.value = '');
        FORM.querySelectorAll('input[type="checkbox"]').forEach(c => {
            c.checked = false;
            c.closest('.tag-item')?.classList.remove('checked');
        });
        selectedCategories.clear();
        selectedTags.clear();
        syncCategoryUI();
        fetchDeals(1);
    };

    // ── Bundle carousel ────────────────────────────────────────────
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

        function visibleCount() {
            return Math.round(viewport.offsetWidth / (slides[0]?.offsetWidth || viewport.offsetWidth));
        }

        function maxIndex() {
            return Math.max(0, slides.length - visibleCount());
        }

        function buildDots() {
            dotsEl.innerHTML = '';
            for (let i = 0; i <= maxIndex(); i++) {
                const d = document.createElement('button');
                d.className = 'carousel-dot' + (i === current ? ' active' : '');
                d.setAttribute('aria-label', `Go to bundle ${i + 1}`);
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

        function startAuto() {
            clearInterval(autoTimer);
            autoTimer = setInterval(() => goTo(current >= maxIndex() ? 0 : current + 1), 4500);
        }

        function stopAuto() {
            clearInterval(autoTimer);
        }

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

        function onDragStart(x) {
            isDragging = true;
            dragStartX = x;
            dragDelta = 0;
            track.classList.add('is-dragging');
            stopAuto();
        }

        function onDragMove(x) {
            if (!isDragging) return;
            dragDelta = x - dragStartX;
            const b = current * ((slides[0]?.offsetWidth || 0) + 16);
            track.style.transform = `translateX(${-b + dragDelta}px)`;
        }

        function onDragEnd() {
            if (!isDragging) return;
            isDragging = false;
            track.classList.remove('is-dragging');
            if (dragDelta < -60) goTo(current + 1);
            else if (dragDelta > 60) goTo(current - 1);
            else updateUI();
            startAuto();
        }

        track.addEventListener('mousedown', e => onDragStart(e.clientX));
        window.addEventListener('mousemove', e => {
            if (isDragging) onDragMove(e.clientX);
        });
        window.addEventListener('mouseup', () => onDragEnd());
        track.addEventListener('touchstart', e => onDragStart(e.touches[0].clientX), {passive: true});
        track.addEventListener('touchmove', e => onDragMove(e.touches[0].clientX), {passive: true});
        track.addEventListener('touchend', () => onDragEnd());
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

    // ── Cart ───────────────────────────────────────────────────────
    async function addToCart(type, id, btn, deliveryType) {
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '⏳ Adding…';
        const url = type === 'plan' ? '/cart/subscription' : '/cart/add-bundle'

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                body: JSON.stringify({
                    type: type,
                    bundle_id: id,
                    plan_id: id,
                    quantity: 1,
                    delivery_type: btn.dataset.delivery_type
                })
            });
            const data = await res.json();

            if (data.success) {
                btn.innerHTML = '✓ Added!';
                btn.style.background = 'var(--accent)';

                // Update cart count in header if you have one
                const cartCount = document.querySelector('[data-cart-count]');
                if (cartCount && data.cart_count != null) cartCount.textContent = data.cart_count;

                setTimeout(() => {
                    btn.innerHTML = original;
                    btn.style.background = '';
                    btn.disabled = false;
                }, 2000);
            } else {
                btn.innerHTML = '✗ Failed';
                btn.style.background = 'var(--save)';
                setTimeout(() => {
                    btn.innerHTML = original;
                    btn.style.background = '';
                    btn.disabled = false;
                }, 2000);
            }
        } catch (e) {
            console.error('Cart error', e);
            btn.innerHTML = '✗ Error';
            setTimeout(() => {
                btn.innerHTML = original;
                btn.style.background = '';
                btn.disabled = false;
            }, 2000);
        }
    }

    renderActiveChips();
    syncCategoryUI();
    bindPagination();
</script>
</body>
</html>