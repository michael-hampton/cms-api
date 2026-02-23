<?php
/**
 * View: subscriptions/deals/index.php
 *
 * Variables injected by SubscriptionDealsController::index():
 *   $plans               – on_sale plans only, paginated
 *   $bundles             – active SubscriptionBundle[] with savings data
 *   $pagination
 *   $filters
 *   $available_sites
 *   $available_categories
 *   $available_tags
 *   $price_range
 *   $sort_options
 *   $stripe_key
 */

$selectedCategories = !empty($filters['categories'])
        ? (is_array($filters['categories']) ? $filters['categories'] : explode(',', $filters['categories']))
        : [];

$selectedTags = !empty($filters['tags'])
        ? (is_array($filters['tags']) ? $filters['tags'] : explode(',', $filters['tags']))
        : [];

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
    <title>Deals &amp; Bundles — PressStack</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Instrument+Sans:wght@400;500;600;700&display=swap"
          rel="stylesheet">
    @css('_pressstack-shared.css')
    <style>

        /* ── Ticker tape ─────────────────────────────────────────── */
        .ticker {
            background: var(--fire);
            color: #fff;
            padding: 8px 0;
            overflow: hidden;
        }
        .ticker__track {
            display: flex;
            width: max-content;
            animation: ticker 30s linear infinite;
        }
        .ticker__item {
            white-space: nowrap;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .14em;
            text-transform: uppercase;
            padding: 0 36px;
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
        .deals-hero {
            background: var(--ink);
            color: #fff;
            padding: 72px 24px 64px;
            position: relative;
            overflow: hidden;
        }

        /* Grid overlay */
        .deals-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: repeating-linear-gradient(0deg, transparent, transparent 39px, rgba(255, 255, 255, .025) 40px),
            repeating-linear-gradient(90deg, transparent, transparent 39px, rgba(255, 255, 255, .025) 40px);
            pointer-events: none;
        }

        /* Fire orb */
        .deals-hero::after {
            content: '';
            position: absolute;
            right: -120px;
            top: -120px;
            width: 560px;
            height: 560px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(214, 48, 49, .3) 0%, transparent 70%);
            pointer-events: none;
            animation: orb-pulse 7s ease-in-out infinite;
        }

        @keyframes orb-pulse {
            0%, 100% {
                transform: scale(1);
                opacity: 1
            }
            50% {
                transform: scale(1.12);
                opacity: .7
            }
        }

        .deals-hero__inner {
            max-width: 1340px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .deals-hero__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--fire);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            padding: 6px 14px;
            border-radius: 100px;
            margin-bottom: 22px;
        }

        .deals-hero__title {
            font-family: var(--font-display);
            font-size: clamp(52px, 8vw, 96px);
            line-height: .95;
            letter-spacing: -.025em;
            margin-bottom: 18px;
        }

        .deals-hero__title em {
            font-style: italic;
            background: linear-gradient(135deg, var(--gold) 0%, var(--gold-mid) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .deals-hero__sub {
            font-size: 17px;
            color: rgba(255, 255, 255, .6);
            max-width: 440px;
            line-height: 1.65;
            font-weight: 400;
            margin-bottom: 44px;
        }

        .deals-hero__stats {
            display: flex;
            gap: 44px;
            flex-wrap: wrap;
        }

        .deals-hero__stat-num {
            font-family: var(--font-display);
            font-size: 38px;
            line-height: 1;
            color: #fff;
            letter-spacing: -.02em;
        }

        .deals-hero__stat-label {
            font-size: 12px;
            color: rgba(255, 255, 255, .45);
            margin-top: 5px;
            letter-spacing: .04em;
        }

        /* ── Spotlight ───────────────────────────────────────────── */
        <?php if ($biggestBundle): ?>
        .spotlight {
            background: var(--white);
            border-bottom: 1px solid var(--border);
            overflow: hidden;
        }
        .spotlight__inner {
            max-width: 1340px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 380px;
            min-height: 200px;
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
            padding: 44px 48px 44px 32px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .spotlight__eyebrow {
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .13em;
            text-transform: uppercase;
            color: var(--fire);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .spotlight__eyebrow::before {
            content: '';
            display: block;
            width: 18px;
            height: 2px;
            background: var(--fire);
            border-radius: 2px;
        }
        .spotlight__name {
            font-family: var(--font-display);
            font-size: clamp(22px, 3vw, 34px);
            line-height: 1.1;
            color: var(--ink);
            margin-bottom: 8px;
        }
        .spotlight__desc {
            font-size: 14px;
            color: var(--ink-soft);
            max-width: 420px;
            line-height: 1.65;
            margin-bottom: 22px;
        }
        .spotlight__pricing {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .spotlight__price {
            font-family: var(--font-display);
            font-size: 40px;
            line-height: 1;
            color: var(--ink);
            letter-spacing: -.025em;
        }

        .spotlight__was {
            font-size: 16px;
            color: var(--ink-muted);
            text-decoration: line-through;
        }

        .spotlight__save {
            background: var(--fire-glow);
            border: 1px solid var(--fire);
            color: var(--fire);
            font-size: 12.5px;
            font-weight: 700;
            letter-spacing: .07em;
            padding: 5px 13px;
            border-radius: 100px;
        }
        .spotlight__cta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--fire);
            color: #fff;
            padding: 12px 26px;
            border-radius: 100px;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: .04em;
            text-decoration: none;
            transition: var(--transition);
            margin-top: 22px;
            align-self: flex-start;
        }

        .spotlight__cta:hover {
            background: var(--fire-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px var(--fire-glow);
        }
        .spotlight__visual {
            background: linear-gradient(140deg, var(--ink) 0%, #16213e 100%);
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
            background: radial-gradient(ellipse at 30% 70%, rgba(214, 48, 49, .22) 0%, transparent 60%),
            radial-gradient(ellipse at 80% 20%, rgba(184, 134, 11, .16) 0%, transparent 55%);
        }

        .spotlight__visual-inner {
            position: relative;
            text-align: center;
        }

        .spotlight__visual-pct {
            font-family: var(--font-display);
            font-size: 88px;
            line-height: 1;
            letter-spacing: -.04em;
            background: linear-gradient(135deg, var(--gold) 0%, var(--gold-mid) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .spotlight__visual-label {
            font-size: 12px;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, .45);
            margin-top: 4px;
        }
        <?php endif; ?>
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
           class="header-nav-link">Publications</a>
        <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/subscriptions/deals"
           class="header-nav-link header-nav-link--deals active">🔥 Deals</a>
    </nav>

    <div class="header-right">
        <a href="/cart" class="header-cart-btn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="9" cy="21" r="1"/>
                <circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            Cart
            <span class="header-cart-badge" data-cart-count>0</span>
        </a>
        <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/subscriptions/onetime/account"
           class="header-account-btn">
            <?= strtoupper(substr(auth()->user()?->name ?? 'A', 0, 1)) ?>
        </a>
    </div>
</header>

<!-- Ticker -->
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
        <?php endfor; ?>
    </div>
</div>

<!-- Hero -->
<section class="deals-hero">
    <div class="deals-hero__inner">
        <div class="deals-hero__eyebrow">🔥 Live deals — updated daily</div>
        <h1 class="deals-hero__title">Deals &amp;<br><em>Bundles</em></h1>
        <p class="deals-hero__sub" id="hero-sub">
            The best prices on the publications you love — sale subscriptions and exclusive bundles in one place.
        </p>
        <div class="deals-hero__stats">
            <div>
                <div class="deals-hero__stat-num"><?= $totalDeals ?>+</div>
                <div class="deals-hero__stat-label">Live deals</div>
            </div>
            <?php if (!empty($bundles)): ?>
                <div>
                    <div class="deals-hero__stat-num"><?= count($bundles) ?></div>
                    <div class="deals-hero__stat-label">Bundle<?= count($bundles) !== 1 ? 's' : '' ?> available</div>
                </div>
            <?php endif; ?>
            <?php if ($biggestBundle): ?>
                <div>
                    <div class="deals-hero__stat-num">£<?= number_format($biggestBundle['savings_amount'], 0) ?></div>
                    <div class="deals-hero__stat-label">Biggest saving</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Spotlight — biggest bundle -->
<?php if ($biggestBundle): ?>
    <div class="spotlight">
        <div class="spotlight__inner">
            <div class="spotlight__content">
                <div class="spotlight__eyebrow">Featured bundle</div>
                <h2 class="spotlight__name"><?= htmlspecialchars($biggestBundle['name']) ?></h2>
                <?php if ($biggestBundle['description']): ?>
                    <p class="spotlight__desc"><?= htmlspecialchars($biggestBundle['description']) ?></p>
                <?php endif; ?>
                <div class="spotlight__pricing">
                    <div class="spotlight__price">£<?= number_format($biggestBundle['bundle_price'], 2) ?></div>
                    <div class="spotlight__was">£<?= number_format($biggestBundle['total_price'], 2) ?></div>
                    <div class="spotlight__save">SAVE <?= $biggestBundle['discount_percentage'] ?>%</div>
                </div>
                <a href="<?= url('/subscriptions/bundles/' . $biggestBundle['slug']) ?>" class="spotlight__cta">
                    Get this bundle →
                </a>
            </div>
            <div class="spotlight__visual">
                <div class="spotlight__visual-inner">
                    <div class="spotlight__visual-pct">-<?= $biggestBundle['discount_percentage'] ?>%</div>
                    <div class="spotlight__visual-label">You save
                        £<?= number_format($biggestBundle['savings_amount'], 2) ?></div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Layout -->
<div class="layout">

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar__head">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="4" y1="6" x2="20" y2="6"/>
                <line x1="4" y1="12" x2="14" y2="12"/>
                <line x1="4" y1="18" x2="10" y2="18"/>
            </svg>
            Filter deals
        </div>
        <div class="sidebar__body">
            <form id="filter-form">

                <div class="sidebar__section">
                    <div class="sidebar__label">Search</div>
                    <label class="sidebar__search">
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
                        <input type="number" name="price_min"
                               value="<?= htmlspecialchars($filters['price_min'] ?? '') ?>"
                               placeholder="£<?= $price_range['min'] ?? 0 ?>" step="0.01" min="0">
                        <span>–</span>
                        <input type="number" name="price_max"
                               value="<?= htmlspecialchars($filters['price_max'] ?? '') ?>"
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
        </div>
    </aside>

    <!-- Main -->
    <main class="main" style="min-width:0;">

        <div class="active-chips" id="active-chips"></div>

        <!-- Bundles carousel -->
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
                                        <button class="bundle-card__cta"
                                                data-delivery_type=""
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
                                        <div class="plan-card__price <?= $hasSale ? 'plan-card__price--sale' : '' ?>">
                                            £<?= number_format($displayPrice, 2) ?>
                                        </div>
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

<script>
    const FORM = document.getElementById('filter-form');
    const SORT_SELECT = document.getElementById('sort-select');
    const PLANS_WRAP = document.getElementById('plans-wrap');

    let selectedCategories = new Set(<?= json_encode($selectedCategories) ?>);
    let selectedTags = new Set(<?= json_encode($selectedTags) ?>);
    let currentSort = <?= json_encode($filters['sort'] ?? '') ?>;
    let isLoading = false;

    function escHtml(s) {
        return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function deliveryPills(type) {
        const b = [];
        if (type === 'digital' || type === 'both') b.push('<span class="meta-pill meta-pill--digital">📱 Digital</span>');
        if (type === 'print' || type === 'both') b.push('<span class="meta-pill meta-pill--print">📰 Print</span>');
        return b.join('');
    }

    function renderPlanCard(plan) {
        const price = parseFloat(plan.has_sale ? plan.sale_price : plan.price) || 0;
        const wasLine = (plan.has_sale && plan.original_price)
            ? `<div class="plan-card__price-was">£${parseFloat(plan.original_price).toFixed(2)}</div>` : '';
        const priceClass = plan.has_sale ? 'plan-card__price plan-card__price--sale' : 'plan-card__price';
        const badge = (plan.has_sale && plan.savings_pct)
            ? `<div class="plan-card__badge plan-card__badge--sale">SAVE ${plan.savings_pct}%</div>`
            : `<div class="plan-card__badge plan-card__badge--offer">On sale</div>`;
        const btnClass = plan.has_sale ? 'plan-card__btn plan-card__btn--sale' : 'plan-card__btn';
        const btnLabel = plan.has_sale ? '🔥 View deal' : 'View details';
        const desc = plan.description
            ? `<p class="plan-card__desc">${escHtml(plan.description.substring(0, 110))}${plan.description.length > 110 ? '…' : ''}</p>` : '';
        const site = plan.site_name ? `<div class="plan-card__site">${escHtml(plan.site_name)}</div>` : '';
        const tagPills = (plan.tags || []).slice(0, 2).map(t =>
            `<span class="meta-pill meta-pill--tag">${escHtml(t.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase()))}</span>`).join('');
        const cartDt = escHtml(plan.delivery_type || 'digital');

        return `
        <article class="plan-card">
            ${badge}
            <div class="plan-card__image">${escHtml((plan.name || '?')[0].toUpperCase())}</div>
            <div class="plan-card__body">
                ${site}
                <div class="plan-card__name">${escHtml(plan.name)}</div>
                <div class="plan-card__meta">${deliveryPills(plan.delivery_type)}${tagPills}</div>
                ${desc}
                <div class="plan-card__pricing">
                    <div>
                        <div class="plan-card__from">from</div>
                        ${wasLine}
                        <div class="${priceClass}">£${price.toFixed(2)}</div>
                    </div>
                    <div>
                        <div class="plan-card__price-period">/ ${escHtml(plan.billing_period || 'month')}</div>
                        <div class="plan-card__price-note">🔥 Sale price</div>
                    </div>
                </div>
                <div style="display:flex;gap:8px;">
                    <a href="${escHtml(plan.detail_url)}" class="${btnClass}" style="flex:1;">${btnLabel}</a>
                    <button class="plan-card__btn plan-card__btn--cart"
                            data-delivery_type="${cartDt}"
                            title="Add to cart"
                            onclick="addToCart('plan',${plan.id},this)">🛒</button>
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
        const chips = [], fd = new FormData(FORM);
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
                headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}
            });
            const data = await res.json();
            if (!data.success) return;
            const result = data.data;
            const countEl = document.getElementById('results-count');
            if (countEl) countEl.innerHTML = `Showing <strong>${result.plans.length}</strong> of <strong>${result.pagination.total.toLocaleString()}</strong> deals`;
            const heroSub = document.getElementById('hero-sub');
            if (heroSub) heroSub.textContent = `${result.pagination.total.toLocaleString()} discounted subscription${result.pagination.total !== 1 ? 's' : ''} available right now`;
            PLANS_WRAP.querySelector('.plans-grid, .empty-state')?.remove();
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

    window.toggleCategory = cat => {
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
        fetchDeals(1);
    };

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
                const badge = document.querySelector('[data-cart-count]');
                if (badge && data.cart_count != null) badge.textContent = data.cart_count;
                setTimeout(() => {
                    btn.innerHTML = original;
                    btn.style.background = '';
                    btn.style.color = '';
                    btn.disabled = false;
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

    /* ── Carousel (shared with index) ──────────────────────────── */
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

    syncCategoryUI();
    renderActiveChips();
    bindPagination();
</script>
</body>
</html>