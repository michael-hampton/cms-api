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
    <title>Deals & Bundles</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Epilogue:ital,wght@0,300;0,400;0,500;0,600;1,300&display=swap"
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
            --radius: 16px;
            --radius-sm: 10px;
            --shadow: 0 4px 20px rgba(0, 0, 0, .08);
            --shadow-lg: 0 12px 48px rgba(0, 0, 0, .16);
            --font-display: 'Syne', system-ui, sans-serif;
            --font-body: 'Epilogue', system-ui, sans-serif;
            --transition: all .25s cubic-bezier(.4, 0, .2, 1);
        }

        body {
            font-family: var(--font-body);
            background: var(--surface);
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
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

        /* ── Page wrapper ────────────────────────────────────────── */
        .page {
            max-width: 1280px;
            margin: 0 auto;
            padding: 48px 24px;
        }

        /* ── Section chrome ──────────────────────────────────────── */
        .section-head {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            margin-bottom: 24px;
            gap: 16px;
        }

        .section-head__left {
            display: flex;
            align-items: baseline;
            gap: 12px;
        }

        .section-title {
            font-family: var(--font-display);
            font-size: 28px;
            letter-spacing: -.01em;
        }

        .section-count {
            font-size: 13px;
            color: var(--ink-muted);
            background: var(--border);
            padding: 3px 10px;
            border-radius: 100px;
        }

        .section-link {
            font-size: 13px;
            color: var(--teal);
            text-decoration: none;
            font-weight: 500;
        }

        .section-link:hover {
            text-decoration: underline;
        }

        /* ── Bundle cards — deals page variant ───────────────────── */
        .bundles-section {
            margin-bottom: 64px;
        }

        .bundles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }

        .bundle-card {
            border-radius: var(--radius);
            overflow: hidden;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            background: var(--ink-2);
            color: #fff;
            position: relative;
            transition: var(--transition);
        }

        .bundle-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .bundle-card__glow {
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at top right, rgba(255, 61, 0, .25) 0%, transparent 60%);
            pointer-events: none;
        }

        .bundle-card__body {
            padding: 28px 28px 0;
            position: relative;
            flex: 1;
        }

        .bundle-card__badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--fire);
            color: #fff;
            font-family: var(--font-display);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            padding: 4px 12px;
            border-radius: 100px;
            margin-bottom: 14px;
        }

        .bundle-card__name {
            font-family: var(--font-display);
            font-size: 22px;
            letter-spacing: -.01em;
            line-height: 1.15;
            margin-bottom: 10px;
        }

        .bundle-card__desc {
            font-size: 13px;
            color: rgba(255, 255, 255, .65);
            line-height: 1.6;
            font-weight: 300;
            margin-bottom: 18px;
        }

        .bundle-card__plans {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 24px;
        }

        .bundle-card__plan-tag {
            background: rgba(255, 255, 255, .08);
            border: 1px solid rgba(255, 255, 255, .14);
            border-radius: 100px;
            padding: 4px 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .bundle-card__footer {
            padding: 20px 28px;
            background: rgba(0, 0, 0, .25);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .bundle-card__price-block {
        }

        .bundle-card__was {
            font-size: 13px;
            color: rgba(255, 255, 255, .45);
            text-decoration: line-through;
        }

        .bundle-card__price {
            font-family: var(--font-display);
            font-size: 30px;
            line-height: 1.1;
        }

        .bundle-card__period {
            font-size: 12px;
            color: rgba(255, 255, 255, .5);
        }

        .bundle-card__btn {
            flex-shrink: 0;
            background: var(--fire);
            color: #fff;
            font-family: var(--font-display);
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .04em;
            padding: 10px 20px;
            border-radius: 100px;
            border: none;
            cursor: pointer;
            transition: var(--transition);
        }

        .bundle-card__btn:hover {
            background: var(--fire-dark);
        }

        /* ── "Why bundle" promo strip ────────────────────────────── */
        .promo-strip {
            background: linear-gradient(135deg, #006d77, #083d35);
            border-radius: var(--radius);
            padding: 32px 40px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 24px;
            margin-bottom: 64px;
            position: relative;
            overflow: hidden;
        }

        .promo-strip::before {
            content: '';
            position: absolute;
            top: -60px;
            right: -60px;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .04);
        }

        .promo-strip__item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            position: relative;
        }

        .promo-strip__icon {
            font-size: 28px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .promo-strip__label {
            font-family: var(--font-display);
            font-size: 15px;
            color: #fff;
            margin-bottom: 4px;
        }

        .promo-strip__text {
            font-size: 13px;
            color: rgba(255, 255, 255, .6);
            line-height: 1.5;
            font-weight: 300;
        }

        /* ── Toolbar ─────────────────────────────────────────────── */
        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            gap: 16px;
            flex-wrap: wrap;
        }

        .toolbar__filters {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .toolbar__filter-select {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 100px;
            font-family: var(--font-body);
            font-size: 13px;
            color: var(--ink);
            background: var(--white);
            outline: none;
            cursor: pointer;
            transition: var(--transition);
        }

        .toolbar__filter-select:focus {
            border-color: var(--fire);
        }

        .toolbar__sort {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--ink-muted);
        }

        .toolbar__sort select {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 100px;
            font-family: var(--font-body);
            font-size: 13px;
            color: var(--ink);
            background: var(--white);
            outline: none;
            cursor: pointer;
        }

        /* ── Plan cards — deals page: horizontal layout ──────────── */
        .plans-section {
        }

        .deals-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .deal-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
            gap: 24px;
            padding: 20px 24px;
            transition: var(--transition);
            text-decoration: none;
            color: inherit;
            position: relative;
            overflow: hidden;
        }

        .deal-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: var(--fire);
            opacity: 0;
            transition: var(--transition);
        }

        .deal-card:hover {
            border-color: var(--fire);
            box-shadow: 0 4px 20px var(--fire-glow);
            transform: translateX(4px);
        }

        .deal-card:hover::before {
            opacity: 1;
        }

        .deal-card__left {
            display: flex;
            align-items: center;
            gap: 20px;
            min-width: 0;
        }

        .deal-card__avatar {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--ink-2), #2d3561);
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .deal-card__info {
            min-width: 0;
        }

        .deal-card__site {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--ink-muted);
            margin-bottom: 3px;
        }

        .deal-card__name {
            font-family: var(--font-display);
            font-size: 17px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 4px;
        }

        .deal-card__meta {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .deal-card__type {
            font-size: 12px;
            color: var(--ink-soft);
            background: var(--surface);
            border: 1px solid var(--border);
            padding: 2px 8px;
            border-radius: 100px;
        }

        .deal-card__ends {
            font-size: 12px;
            color: var(--fire);
            font-weight: 600;
        }

        .deal-card__right {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-shrink: 0;
        }

        .deal-card__pricing {
            text-align: right;
        }

        .deal-card__was {
            font-size: 13px;
            color: var(--ink-muted);
            text-decoration: line-through;
        }

        .deal-card__price {
            font-family: var(--font-display);
            font-size: 24px;
            line-height: 1;
            color: var(--ink);
        }

        .deal-card__period {
            font-size: 12px;
            color: var(--ink-muted);
        }

        .deal-card__badge {
            background: var(--fire-glow);
            border: 1px solid var(--fire);
            color: var(--fire);
            font-family: var(--font-display);
            font-size: 12px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 100px;
            white-space: nowrap;
        }

        .deal-card__btn {
            background: var(--ink);
            color: #fff;
            border: none;
            border-radius: 100px;
            padding: 10px 20px;
            font-family: var(--font-display);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
            text-decoration: none;
            display: inline-block;
        }

        .deal-card__btn:hover {
            background: var(--fire);
        }

        @media (max-width: 700px) {
            .deal-card {
                grid-template-columns: 1fr;
            }

            .deal-card__right {
                justify-content: space-between;
            }
        }

        /* ── Empty / no deals ────────────────────────────────────── */
        .empty {
            text-align: center;
            padding: 80px 24px;
        }

        .empty__icon {
            font-size: 56px;
            margin-bottom: 16px;
        }

        .empty__title {
            font-family: var(--font-display);
            font-size: 26px;
            margin-bottom: 8px;
        }

        .empty__text {
            font-size: 15px;
            color: var(--ink-soft);
        }

        /* ── Pagination ──────────────────────────────────────────── */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 48px;
        }

        .pag-btn {
            min-width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--white);
            font-family: var(--font-body);
            font-size: 14px;
            color: var(--ink);
            text-decoration: none;
            transition: var(--transition);
            padding: 0 10px;
        }

        .pag-btn:hover {
            border-color: var(--fire);
            color: var(--fire);
        }

        .pag-btn.active {
            background: var(--fire);
            color: #fff;
            border-color: var(--fire);
        }

        .pag-btn.disabled {
            opacity: .4;
            pointer-events: none;
        }

        /* ── Back link ───────────────────────────────────────────── */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--ink-muted);
            text-decoration: none;
            padding: 12px 24px;
            transition: var(--transition);
        }

        .back-link:hover {
            color: var(--ink);
        }

        /* ── Animations ──────────────────────────────────────────── */
        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(24px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hero__eyebrow {
            animation: fadeUp .5s ease both;
        }

        .hero__title {
            animation: fadeUp .5s .1s ease both;
        }

        .hero__sub {
            animation: fadeUp .5s .2s ease both;
        }

        .hero__stats {
            animation: fadeUp .5s .3s ease both;
        }

        /* Counter animation */
        .count-up {
            display: inline-block;
        }
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

<div class="page">

    <!-- ── Bundles section ──────────────────────────────────────────────── -->
    <?php if (!empty($bundles)): ?>
        <section class="bundles-section">
            <div class="section-head">
                <div class="section-head__left">
                    <h2 class="section-title">Bundle deals</h2>
                    <span class="section-count"><?= count($bundles) ?></span>
                </div>
            </div>
            <div class="bundles-grid">
                <?php foreach ($bundles as $bundle): ?>
                    <a href="<?= url('/subscriptions/bundles/' . $bundle['slug']) ?>" class="bundle-card">
                        <div class="bundle-card__glow"></div>
                        <div class="bundle-card__body">
                            <div class="bundle-card__badge">
                                🔥 Save <?= $bundle['discount_percentage'] ?>%
                            </div>
                            <div class="bundle-card__name"><?= htmlspecialchars($bundle['name']) ?></div>
                            <?php if ($bundle['description']): ?>
                                <p class="bundle-card__desc"><?= htmlspecialchars($bundle['description']) ?></p>
                            <?php endif; ?>
                            <div class="bundle-card__plans">
                                <?php foreach ($bundle['plans'] as $plan): ?>
                                    <span class="bundle-card__plan-tag">
                                <?= $plan['delivery_type'] === 'digital' ? '📱' : '📰' ?>
                                <?= htmlspecialchars($plan['name']) ?>
                            </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="bundle-card__footer">
                            <div class="bundle-card__price-block">
                                <div class="bundle-card__was">Was £<?= number_format($bundle['total_price'], 2) ?></div>
                                <div class="bundle-card__price">£<?= number_format($bundle['bundle_price'], 2) ?></div>
                                <div class="bundle-card__period">per period</div>
                            </div>
                            <button class="bundle-card__btn"
                                    onclick="event.preventDefault(); addBundleToCart(<?= $bundle['id'] ?>)">
                                Add to cart
                            </button>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- ── Why bundle strip ─────────────────────────────────────────────── -->
    <div class="promo-strip">
        <div class="promo-strip__item">
            <div class="promo-strip__icon">💰</div>
            <div>
                <div class="promo-strip__label">Save more</div>
                <p class="promo-strip__text">Bundles are priced below the sum of individual subscriptions.</p>
            </div>
        </div>
        <div class="promo-strip__item">
            <div class="promo-strip__icon">📦</div>
            <div>
                <div class="promo-strip__label">One checkout</div>
                <p class="promo-strip__text">All publications managed in a single subscription.</p>
            </div>
        </div>
        <div class="promo-strip__item">
            <div class="promo-strip__icon">🔄</div>
            <div>
                <div class="promo-strip__label">Flexible billing</div>
                <p class="promo-strip__text">Auto-renewal with easy cancellation, always in your control.</p>
            </div>
        </div>
        <div class="promo-strip__item">
            <div class="promo-strip__icon">⚡</div>
            <div>
                <div class="promo-strip__label">Instant access</div>
                <p class="promo-strip__text">Digital subscriptions activate the moment your order completes.</p>
            </div>
        </div>
    </div>

    <!-- ── On-sale plans ─────────────────────────────────────────────────── -->
    <section class="plans-section">
        <div class="section-head">
            <div class="section-head__left">
                <h2 class="section-title">Sale subscriptions</h2>
                <span class="section-count"><?= number_format($pagination['total']) ?></span>
            </div>
            <div class="toolbar__sort">
                Sort:
                <select onchange="window.location.href=updateParam('sort',this.value)">
                    <?php foreach ($sort_options as $opt): ?>
                        <option value="<?= $opt->value ?>" <?= ($filters['sort'] ?? '') === $opt->value ? 'selected' : '' ?>>
                            <?= $opt->label() ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Quick filters -->
        <div class="toolbar">
            <form method="GET" class="toolbar__filters">
                <input type="hidden" name="special_filter" value="on_sale">
                <?php if (!empty($available_sites)): ?>
                    <select name="site_id" class="toolbar__filter-select" onchange="this.form.submit()">
                        <option value="">All publications</option>
                        <?php foreach ($available_sites as $site): ?>
                            <option value="<?= $site->id ?>" <?= ($filters['site_id'] ?? '') == $site->id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($site->name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <select name="delivery_type" class="toolbar__filter-select" onchange="this.form.submit()">
                    <option value="">Print & Digital</option>
                    <option value="digital" <?= ($filters['delivery_type'] ?? '') === 'digital' ? 'selected' : '' ?>>
                        Digital
                    </option>
                    <option value="print" <?= ($filters['delivery_type'] ?? '') === 'print' ? 'selected' : '' ?>>Print
                    </option>
                </select>
            </form>
        </div>

        <?php if (empty($plans)): ?>
            <div class="empty">
                <div class="empty__icon">🏷️</div>
                <h3 class="empty__title">No sale plans right now</h3>
                <p class="empty__text">Check back soon — new deals are added regularly. <a
                            href="<?= url('/subscriptions') ?>">Browse all subscriptions.</a></p>
            </div>
        <?php else: ?>

            <div class="deals-list">
                <?php foreach ($plans as $plan): ?>
                    <?php
                    $hasSale = false;
                    $salePrice = null;
                    $originalPrice = null;
                    $savingPct = null;
                    foreach (($plan->tiers ?? []) as $tier) {
                        if (isset($tier->sale_price) && $tier->sale_price < $tier->price) {
                            $hasSale = true;
                            $salePrice = $tier->sale_price;
                            $originalPrice = $tier->price;
                            $savingPct = (int)round((($tier->price - $tier->sale_price) / $tier->price) * 100);
                            break;
                        }
                    }
                    $displayPrice = $hasSale ? $salePrice : ($plan->tiers[0]->price ?? $plan->price ?? 0);
                    $emoji = match ($plan->delivery_type ?? 'print') {
                        'digital' => '📱',
                        'print' => '📰',
                        default => '📦',
                    };
                    ?>
                    <div class="deal-card">
                        <div class="deal-card__left">
                            <div class="deal-card__avatar"><?= $emoji ?></div>
                            <div class="deal-card__info">
                                <?php if (!empty($plan->site_name)): ?>
                                    <div class="deal-card__site"><?= htmlspecialchars($plan->site_name) ?></div>
                                <?php endif; ?>
                                <div class="deal-card__name"><?= htmlspecialchars($plan->name) ?></div>
                                <div class="deal-card__meta">
                                <span class="deal-card__type">
                                    <?= match ($plan->delivery_type ?? 'print') {
                                        'digital' => '📱 Digital',
                                        'print' => '📰 Print',
                                        default => '📦 Print + Digital',
                                    } ?>
                                </span>
                                    <?php if ($hasSale): ?>
                                        <span class="deal-card__ends">🔥 Sale price</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="deal-card__right">
                            <div class="deal-card__pricing">
                                <?php if ($hasSale && $originalPrice): ?>
                                    <div class="deal-card__was">£<?= number_format($originalPrice, 2) ?></div>
                                <?php endif; ?>
                                <div class="deal-card__price">£<?= number_format($displayPrice, 2) ?></div>
                                <div class="deal-card__period">
                                    / <?= htmlspecialchars($plan->billing_period ?? 'month') ?></div>
                            </div>
                            <?php if ($savingPct): ?>
                                <div class="deal-card__badge">SAVE <?= $savingPct ?>%</div>
                            <?php endif; ?>
                            <a href="<?= url('/subscriptions/' . $plan->slug . '/checkout') ?>" class="deal-card__btn">
                                Subscribe
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($pagination['total_pages'] > 1): ?>
                <nav class="pagination" aria-label="Pagination">
                    <a href="?<?= http_build_query(array_merge($filters, ['page' => $pagination['current_page'] - 1])) ?>"
                       class="pag-btn <?= $pagination['current_page'] <= 1 ? 'disabled' : '' ?>">←</a>
                    <?php
                    $start = max(1, $pagination['current_page'] - 2);
                    $end = min($pagination['total_pages'], $pagination['current_page'] + 2);
                    for ($p = $start; $p <= $end; $p++):
                        ?>
                        <a href="?<?= http_build_query(array_merge($filters, ['page' => $p])) ?>"
                           class="pag-btn <?= $p === $pagination['current_page'] ? 'active' : '' ?>"><?= $p ?></a>
                    <?php endfor; ?>
                    <a href="?<?= http_build_query(array_merge($filters, ['page' => $pagination['current_page'] + 1])) ?>"
                       class="pag-btn <?= $pagination['current_page'] >= $pagination['total_pages'] ? 'disabled' : '' ?>">→</a>
                </nav>
            <?php endif; ?>

        <?php endif; // plans empty ?>
    </section>

</div><!-- /page -->

<script>
    function updateParam(key, value) {
        const url = new URL(window.location);
        url.searchParams.set(key, value);
        return url.toString();
    }

    function addBundleToCart(bundleId) {
        fetch('/cart/add-bundle', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? ''
            },
            body: JSON.stringify({bundle_id: bundleId}),
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    window.location.href = '/cart';
                } else {
                    alert(data.message ?? 'Could not add bundle to cart.');
                }
            })
            .catch(() => alert('Something went wrong. Please try again.'));
    }
</script>

</body>
</html>