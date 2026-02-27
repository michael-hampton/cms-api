<?php
/*
 * View: subscriptions/onetime/show.php
 *
 * Variables injected by SubscriptionController::show():
 *   $plan            – SubscriptionPlan with pricingTiers, features
 *   $reviews         – array from ReviewService::getPlanReviews()
 *                      keys: reviews[], pagination, average_rating,
 *                            total_reviews, rating_breakdown, rating_percentages
 *   $canReview       – array: ['can_review' => bool, 'reason' => ?string]
 *   $isAuthenticated – bool
 */

foreach ($plan->pricingTiers as $tier) {
    $tier->effective_print_price = $tier->sale_price ?? $tier->price;
    $tier->effective_digital_price = $tier->digital_sale_price ?? $tier->digital_price ?? $tier->sale_price ?? $tier->price;
    $tier->has_print_sale = $tier->sale_price && $tier->sale_price < $tier->price;
    $tier->has_digital_sale = $tier->digital_sale_price && $tier->digital_sale_price < ($tier->digital_price ?? $tier->price);
}

$averageRating = $reviewData['average_rating'] ?? 0;
$totalReviews = $reviewData['total_reviews'] ?? 0;
$breakdown = $reviewData['rating_breakdown'] ?? [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
$percentages = $reviewData['rating_percentages'] ?? [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
$reviewList = $reviewData['reviews'] ?? [];
$pagination = $reviewData['pagination'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($plan->name) ?> - Subscribe</title>
    <script src="https://js.stripe.com/v3/"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #2563eb;
            --primary-dark: #1e40af;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --border-color: #e2e8f0;
            --bg-light: #f8fafc;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --shadow: 0 1px 3px rgba(0, 0, 0, .1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, .1);
            --gold: #f59e0b;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: var(--text-primary);
            background: var(--bg-light);
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem 20px;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: .5rem;
            margin-bottom: 2rem;
            color: var(--text-secondary);
            font-size: .95rem;
        }

        .breadcrumb a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* ── Plan header ──────────────────────────────────────────── */
        .plan-header {
            background: white;
            padding: 3rem 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .plan-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .plan-description {
            font-size: 1.125rem;
            color: var(--text-secondary);
            line-height: 1.8;
        }

        /* ── Cards ────────────────────────────────────────────────── */
        .plan-card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }
        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }

        /* ── Duration options ─────────────────────────────────────── */
        .duration-option {
            border: 2px solid var(--border-color);
            border-radius: .75rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all .3s;
            position: relative;
        }

        .duration-option:hover {
            border-color: var(--primary-color);
            transform: translateX(5px);
        }

        .duration-option.selected {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, .05);
        }

        .duration-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .duration-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: .75rem;
        }

        .duration-label {
            font-weight: 600;
            font-size: 1.125rem;
        }

        .duration-price {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .duration-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: .95rem;
            color: var(--text-secondary);
            flex-wrap: wrap;
            gap: 1rem;
        }

        .savings-badge {
            background: var(--danger-color);
            color: white;
            padding: .375rem .75rem;
            border-radius: .375rem;
            font-size: .8rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .original-price {
            text-decoration: line-through;
            color: var(--text-secondary);
            font-size: 1rem;
            margin-left: .5rem;
        }

        .price-per-issue {
            font-size: .875rem;
            color: var(--text-secondary);
        }

        /* ── Delivery ─────────────────────────────────────────────── */
        .delivery-option {
            border: 2px solid var(--border-color);
            border-radius: .75rem;
            padding: 1.25rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all .3s;
            display: flex;
            align-items: start;
            gap: 1rem;
        }

        .delivery-option:hover {
            border-color: var(--primary-color);
        }

        .delivery-option.selected {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, .05);
        }

        .delivery-option input[type="radio"] {
            margin-top: .25rem;
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .delivery-label {
            font-weight: 600;
            font-size: 1.125rem;
            display: block;
            margin-bottom: .5rem;
        }

        .delivery-desc {
            font-size: .95rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* ── Features ─────────────────────────────────────────────── */
        .features-list {
            list-style: none;
            margin-bottom: 2rem;
        }

        .features-list li {
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .features-list li:last-child {
            border-bottom: none;
        }

        .check-icon {
            width: 24px;
            height: 24px;
            background: var(--success-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .check-icon svg {
            width: 14px;
            height: 14px;
            stroke: white;
            stroke-width: 3;
        }

        /* ── Buttons ──────────────────────────────────────────────── */
        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            border: none;
            border-radius: .75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .3s;
            text-decoration: none;
            text-align: center;
            font-size: 1.125rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            width: 100%;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .btn-secondary:hover {
            background: var(--bg-light);
        }

        .btn-sm {
            padding: .5rem 1rem;
            font-size: .875rem;
            border-radius: .5rem;
        }

        /* ── Cart ─────────────────────────────────────────────────── */
        .cart-badge {
            position: fixed;
            top: 2rem;
            right: 2rem;
            background: white;
            border-radius: 1rem;
            padding: 1rem 1.5rem;
            box-shadow: var(--shadow-lg);
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
            background: var(--danger-color);
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
            color: var(--primary-color);
            font-size: 1.125rem;
        }

        /* mini-cart & overlay (unchanged from original) */
        .mini-cart {
            position: fixed;
            top: 0;
            right: -400px;
            width: 400px;
            height: 100vh;
            background: white;
            box-shadow: var(--shadow-lg);
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
            border-bottom: 2px solid var(--border-color);
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
            color: var(--text-secondary);
        }

        .mini-cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
        }

        .cart-item {
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .cart-item-name {
            font-weight: 600;
            margin-bottom: .5rem;
        }

        .cart-item-details {
            font-size: .875rem;
            color: var(--text-secondary);
            margin-bottom: .5rem;
        }

        .cart-item-price {
            font-weight: 600;
            color: var(--primary-color);
        }

        .mini-cart-footer {
            padding: 1.5rem;
            border-top: 2px solid var(--border-color);
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

        /* ── Toast ────────────────────────────────────────────────── */
        .toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: white;
            padding: 1rem 1.5rem;
            border-radius: .75rem;
            box-shadow: var(--shadow-lg);
            display: none;
            align-items: center;
            gap: 1rem;
            z-index: 10000;
        }

        .toast.show {
            display: flex;
            animation: slideIn .3s ease-out;
        }

        .toast.success {
            border-left: 4px solid var(--success-color);
        }

        .toast.error {
            border-left: 4px solid var(--danger-color);
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* ════════════════════════════════════════════════════════════
           REVIEWS SECTION
           ════════════════════════════════════════════════════════════ */

        /* ── DMCC compliance notice ───────────────────────────────── */
        .reviews-compliance-notice {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: .5rem;
            padding: .875rem 1rem;
            margin-bottom: 1.75rem;
            font-size: .8rem;
            color: #0c4a6e;
            line-height: 1.6;
            display: flex;
            gap: .625rem;
            align-items: flex-start;
        }

        .reviews-compliance-notice svg {
            flex-shrink: 0;
            margin-top: 1px;
        }

        /* ── Summary strip ────────────────────────────────────────── */
        .reviews-summary {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 2rem;
            align-items: start;
            margin-bottom: 2rem;
        }

        .reviews-score {
            text-align: center;
            min-width: 120px;
        }

        .reviews-score__avg {
            font-size: 3.5rem;
            font-weight: 700;
            line-height: 1;
            color: var(--text-primary);
        }

        .reviews-score__stars {
            display: flex;
            justify-content: center;
            gap: 2px;
            margin: .4rem 0 .3rem;
        }

        .reviews-score__count {
            font-size: .8rem;
            color: var(--text-secondary);
        }

        /* ── Rating bars ──────────────────────────────────────────── */
        .rating-bars {
            flex: 1;
        }

        .rating-bar-row {
            display: grid;
            grid-template-columns: 1.5rem 1fr 2.5rem;
            align-items: center;
            gap: .6rem;
            margin-bottom: .45rem;
        }

        .rating-bar-label {
            font-size: .8rem;
            color: var(--text-secondary);
            text-align: right;
        }

        .rating-bar-track {
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }

        .rating-bar-fill {
            height: 100%;
            background: var(--gold);
            border-radius: 4px;
            transition: width .4s ease;
        }

        .rating-bar-pct {
            font-size: .75rem;
            color: var(--text-secondary);
        }

        /* ── Individual review card ───────────────────────────────── */
        .review-list {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            margin-top: 1.75rem;
        }

        .review-card {
            padding: 1.25rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .review-card:last-child {
            border-bottom: none;
        }

        .review-card__header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: .6rem;
            flex-wrap: wrap;
        }

        .review-card__meta {
            display: flex;
            align-items: center;
            gap: .75rem;
            flex-wrap: wrap;
        }

        .review-card__author {
            font-weight: 600;
            font-size: .95rem;
        }

        .review-card__date {
            font-size: .8rem;
            color: var(--text-secondary);
        }

        .review-card__verified {
            font-size: .7rem;
            font-weight: 600;
            letter-spacing: .04em;
            text-transform: uppercase;
            background: #d1fae5;
            color: #065f46;
            padding: 2px 8px;
            border-radius: 100px;
        }

        .review-card__title {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: .4rem;
        }

        .review-card__comment {
            font-size: .95rem;
            color: var(--text-secondary);
            line-height: 1.65;
        }

        .review-card__helpful {
            display: flex;
            align-items: center;
            gap: .5rem;
            margin-top: .875rem;
            font-size: .8rem;
            color: var(--text-secondary);
        }

        .helpful-btn {
            background: none;
            border: 1px solid var(--border-color);
            border-radius: .375rem;
            padding: 3px 10px;
            font-size: .75rem;
            cursor: pointer;
            transition: all .2s;
            color: var(--text-secondary);
        }

        .helpful-btn:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .helpful-btn.voted {
            background: #eff6ff;
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        /* Star components */
        .stars {
            display: inline-flex;
            gap: 1px;
        }

        .star {
            color: #d1d5db;
            font-size: .9rem;
            line-height: 1;
        }

        .star.filled {
            color: var(--gold);
        }

        /* ── Write review form ────────────────────────────────────── */
        .write-review-section {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid var(--border-color);
        }

        .write-review-section h3 {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1.25rem;
        }

        .review-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .form-group label {
            display: block;
            font-size: .875rem;
            font-weight: 600;
            margin-bottom: .4rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: .75rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: .5rem;
            font-family: inherit;
            font-size: .95rem;
            color: var(--text-primary);
            transition: border-color .2s;
            outline: none;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: var(--primary-color);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        /* Star rating input */
        .star-rating-input {
            display: flex;
            gap: .375rem;
        }

        .star-rating-input input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
        }

        .star-rating-input label {
            font-size: 1.75rem;
            cursor: pointer;
            color: #d1d5db;
            transition: color .15s;
        }

        .star-rating-input label:hover,
        .star-rating-input label.selected,
        .star-rating-input input:checked ~ label {
            color: var(--gold);
        }

        /* Review form notice */
        .form-submission-notice {
            background: #fafaf9;
            border: 1px solid #d6d3d1;
            border-radius: .5rem;
            padding: .875rem 1rem;
            font-size: .8rem;
            color: #57534e;
            line-height: 1.65;
            margin-bottom: 1rem;
        }

        /* Unauthenticated CTA */
        .review-login-prompt {
            text-align: center;
            padding: 2rem;
            background: var(--bg-light);
            border-radius: .75rem;
            border: 1px dashed var(--border-color);
            margin-top: 1.5rem;
        }

        .review-login-prompt p {
            margin-bottom: 1rem;
            color: var(--text-secondary);
        }

        /* No reviews empty state */
        .reviews-empty {
            text-align: center;
            padding: 2.5rem;
            color: var(--text-secondary);
            font-size: .95rem;
        }

        .reviews-empty__icon {
            font-size: 2.5rem;
            margin-bottom: .75rem;
        }

        /* ── Review pagination ────────────────────────────────────── */
        .review-pagination {
            display: flex;
            justify-content: center;
            gap: .5rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .review-pagination__btn {
            min-width: 34px;
            height: 34px;
            padding: 0 .75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border-color);
            border-radius: .375rem;
            background: white;
            font-size: .875rem;
            color: var(--text-primary);
            cursor: pointer;
            transition: all .2s;
        }

        .review-pagination__btn:hover:not(.active):not(.disabled) {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .review-pagination__btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            pointer-events: none;
        }

        .review-pagination__btn.disabled {
            opacity: .35;
            pointer-events: none;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .plan-title {
                font-size: 2rem;
            }

            .reviews-summary {
                grid-template-columns: 1fr;
            }

            .mini-cart {
                width: 100%;
                right: -100%;
            }

            .cart-badge {
                bottom: 2rem;
                top: auto;
            }
        }
    </style>
</head>
<body>
<div class="container">

    <div class="breadcrumb">
        <a href="/subscriptions">← Back to Shop</a>
    </div>

    <!-- ── Plan Header ─────────────────────────────────────────────────── -->
    <div class="plan-header">
        <h1 class="plan-title"><?= htmlspecialchars($plan->name) ?></h1>
        <?php if ($plan->description): ?>
            <p class="plan-description"><?= htmlspecialchars($plan->description) ?></p>
        <?php endif; ?>
    </div>

    <!-- ── Duration & Delivery (unchanged from original) ──────────────── -->
    <div class="plan-card">
        <h2 class="section-title">Choose Your Subscription</h2>

        <div class="duration-options">
            <?php foreach ($plan->pricingTiers as $index => $pricing):
                $actualPrice = $pricing->sale_price && $pricing->sale_price < $pricing->price ? $pricing->sale_price : $pricing->price;
                $originalPrice = $pricing->price;
                ?>
                <div class="duration-option" data-plan="<?= $plan->id ?>">
                    <input type="radio" name="duration_<?= $plan->id ?>" value="<?= $pricing->duration_months ?>"
                           data-pricing-id="<?= $pricing->id ?>"
                           data-price="<?= $pricing->price ?>"
                           data-digital="<?= $pricing->digital_price ?? 0 ?>"
                           data-original-price="<?= $pricing->sale_price ?? $pricing->price ?>"
                           data-original-digital="<?= $pricing->digital_sale_price ?? $pricing->digital_price ?>"
                           data-issues="<?= $pricing->issue_count ?>"
                            <?= $index === 0 ? 'checked' : '' ?>>

                    <div class="duration-header">
                        <span class="duration-label"><?= htmlspecialchars($pricing->label) ?></span>
                        <div>
                            <span class="duration-price">£<?= number_format($actualPrice, 2) ?></span>
                            <?php if ($pricing->hasDiscount()): ?>
                                <span class="original-price">£<?= number_format($originalPrice, 2) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="duration-details">
                        <span class="duration-period"><?= htmlspecialchars($pricing->period_description) ?></span>
                        <?php if ($pricing->issue_count > 0): ?>
                            <span class="price-per-issue">£<?= number_format($pricing->getPricePerIssue(), 2) ?> per issue</span>
                        <?php endif; ?>
                        <?php if ($pricing->getSavingsText()): ?>
                            <span class="savings-badge"><?= $pricing->getSavingsText() ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php
        $deliveryOptions = $plan->getDeliveryOptions();
        $hasMultipleOptions = count($deliveryOptions) > 1;
        ?>

        <?php if ($hasMultipleOptions): ?>
            <h2 class="section-title">Delivery Type</h2>
            <div class="delivery-options">
                <?php if ($plan->hasDigitalOption()): ?>
                    <div class="delivery-option" data-plan="<?= $plan->id ?>">
                        <input type="radio" name="delivery_<?= $plan->id ?>" value="digital" checked>
                        <div class="delivery-content">
                            <span class="delivery-label">Digital Edition</span>
                            <p class="delivery-desc">Instant access to digital content. Download and read on any
                                device.</p>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($plan->hasPrintOption()): ?>
                    <div class="delivery-option" data-plan="<?= $plan->id ?>">
                        <input type="radio" name="delivery_<?= $plan->id ?>"
                               value="print" <?= !$plan->hasDigitalOption() ? 'checked' : '' ?>>
                        <div class="delivery-content">
                            <span class="delivery-label">Print Edition</span>
                            <p class="delivery-desc">Physical magazine delivered to your doorstep. Shipping
                                included.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <input type="radio" name="delivery_<?= $plan->id ?>" value="<?= $deliveryOptions[0] ?>" checked
                   style="display:none;">
        <?php endif; ?>

        <button class="btn btn-primary" onclick="addToCart(<?= $plan->id ?>)">Add to Cart</button>
    </div>

    <!-- ── Features ───────────────────────────────────────────────────── -->
    <?php if (!empty($plan->features)): ?>
        <div class="plan-card">
            <h2 class="section-title">What's Included</h2>
            <ul class="features-list">
                <?php foreach ($plan->features as $feature): ?>
                    <li>
                        <div class="check-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                        </div>
                        <span><?= htmlspecialchars($feature) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- ════════════════════════════════════════════════════════════════════
         REVIEWS SECTION — DMCC Act 2024 compliant
         ════════════════════════════════════════════════════════════════════ -->
    <div class="plan-card" id="reviews-section">
        <h2 class="section-title">Customer Reviews</h2>

        <!--
            DMCC Act 2024 compliance notice.
            The Digital Markets, Competition and Consumers Act 2024 (Chapter 7,
            ss.234–237) prohibits commissioning fake reviews, publishing reviews
            without reasonable steps to verify genuineness, and misrepresenting
            the source of reviews.

            This notice informs consumers how reviews are collected and verified.
        -->
        <div class="reviews-compliance-notice" role="note" aria-label="Review transparency notice">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <span>
                Reviews are submitted by customers who have purchased this subscription.
                We verify that each review comes from a genuine account and do not edit, suppress,
                or incentivise reviews. All ratings shown reflect authentic customer experiences.
                <a href="/reviews-policy" style="color:inherit; text-decoration:underline;">Learn about our review policy</a>.
            </span>
        </div>

        <!-- Summary -->
        <?php if ($totalReviews > 0): ?>
            <div class="reviews-summary">
                <!-- Overall score -->
                <div class="reviews-score">
                    <div class="reviews-score__avg"><?= number_format($averageRating, 1) ?></div>
                    <div class="reviews-score__stars"
                         aria-label="<?= number_format($averageRating, 1) ?> out of 5 stars">
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                            <span aria-hidden="true"><?= $s <= round($averageRating) ? '★' : '☆' ?></span>
                        <?php endfor; ?>
                    </div>
                    <div class="reviews-score__count">
                        <?= number_format($totalReviews) ?> review<?= $totalReviews !== 1 ? 's' : '' ?>
                    </div>
                </div>

                <!-- Breakdown bars -->
                <div class="rating-bars" aria-label="Rating breakdown">
                    <?php foreach ([5, 4, 3, 2, 1] as $r): ?>
                        <div class="rating-bar-row">
                            <span class="rating-bar-label" aria-hidden="true"><?= $r ?>★</span>
                            <div class="rating-bar-track" role="progressbar"
                                 aria-valuenow="<?= $percentages[$r] ?? 0 ?>"
                                 aria-valuemin="0" aria-valuemax="100"
                                 aria-label="<?= $r ?> star — <?= $percentages[$r] ?? 0 ?>%">
                                <div class="rating-bar-fill" style="width:<?= $percentages[$r] ?? 0 ?>%"></div>
                            </div>
                            <span class="rating-bar-pct"><?= $percentages[$r] ?? 0 ?>%</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Review list -->
            <div class="review-list" id="review-list">
                <?php foreach ($reviewList as $review): ?>
                    <article class="review-card" data-review-id="<?= (int)$review['id'] ?>">
                        <div class="review-card__header">
                            <div class="review-card__meta">
                                <span class="review-card__author"><?= htmlspecialchars($review['author_name'] ?? 'Anonymous') ?></span>
                                <?php if ($review['is_verified_purchase']): ?>
                                    <span class="review-card__verified"
                                          title="This review was submitted by a verified purchaser">✓ Verified</span>
                                <?php endif; ?>
                                <span class="review-card__date"><?= htmlspecialchars($review['formatted_date'] ?? '') ?></span>
                            </div>
                            <div class="stars" aria-label="<?= (int)$review['rating'] ?> out of 5 stars">
                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                    <span class="star <?= $s <= $review['rating'] ? 'filled' : '' ?>"
                                          aria-hidden="true">★</span>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <?php if (!empty($review['title'])): ?>
                            <div class="review-card__title"><?= htmlspecialchars($review['title']) ?></div>
                        <?php endif; ?>
                        <div class="review-card__comment"><?= htmlspecialchars($review['comment']) ?></div>
                        <div class="review-card__helpful">
                            <span>Helpful?</span>
                            <button class="helpful-btn" onclick="voteHelpful(<?= (int)$review['id'] ?>, true, this)"
                                    aria-label="Mark as helpful">
                                👍 <?= (int)($review['helpful_count'] ?? 0) ?>
                            </button>
                            <button class="helpful-btn" onclick="voteHelpful(<?= (int)$review['id'] ?>, false, this)"
                                    aria-label="Mark as not helpful">
                                👎 <?= (int)($review['unhelpful_count'] ?? 0) ?>
                            </button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if (!empty($pagination) && ($pagination['total_pages'] ?? 1) > 1): ?>
                <nav class="review-pagination" aria-label="Review pages" id="review-pagination">
                    <?php
                    $cur = $pagination['current_page'];
                    $tot = $pagination['total_pages'];
                    ?>
                    <button class="review-pagination__btn <?= $cur <= 1 ? 'disabled' : '' ?>"
                            onclick="loadReviews(<?= $cur - 1 ?>)" aria-label="Previous page">←
                    </button>
                    <?php for ($p = 1; $p <= $tot; $p++): ?>
                        <button class="review-pagination__btn <?= $p === $cur ? 'active' : '' ?>"
                                onclick="loadReviews(<?= $p ?>)" <?= $p === $cur ? 'aria-current="page"' : '' ?>>
                            <?= $p ?>
                        </button>
                    <?php endfor; ?>
                    <button class="review-pagination__btn <?= $cur >= $tot ? 'disabled' : '' ?>"
                            onclick="loadReviews(<?= $cur + 1 ?>)" aria-label="Next page">→
                    </button>
                </nav>
            <?php endif; ?>

        <?php else: ?>
            <div class="reviews-empty">
                <div class="reviews-empty__icon">⭐</div>
                <p>No reviews yet. Be the first to share your experience.</p>
            </div>
        <?php endif; ?>

        <!-- ── Write a review ──────────────────────────────────────────── -->
        <div class="write-review-section">
            <h3>Write a Review</h3>

            <?php if ($canReview['can_review'] ?? false): ?>
                <div class="form-submission-notice" role="note">
                    By submitting a review, you confirm it reflects your genuine, personal experience
                    with this subscription. We do not offer incentives for reviews. Fabricated or
                    misleading reviews may be removed and, where required, reported in accordance
                    with the Digital Markets, Competition and Consumers Act 2024.
                </div>

                <form class="review-form" id="review-form" onsubmit="submitReview(event)">
                    <input type="hidden" name="plan_id" value="<?= (int)$plan->id ?>">

                    <div class="form-group">
                        <label for="review-rating">Your Rating <span aria-hidden="true">*</span></label>
                        <div class="star-rating-input" role="radiogroup" aria-label="Rating" id="star-rating-group">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <input type="radio" name="rating" id="star-<?= $i ?>" value="<?= $i ?>" required>
                                <label for="star-<?= $i ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>"
                                       aria-label="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">★</label>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="review-title">Review Title</label>
                        <input type="text" id="review-title" name="title"
                               maxlength="120" placeholder="Summarise your experience">
                    </div>

                    <div class="form-group">
                        <label for="review-comment">Your Review <span aria-hidden="true">*</span></label>
                        <textarea id="review-comment" name="comment" required
                                  minlength="10" maxlength="2000"
                                  placeholder="Tell others about your experience with this subscription…"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" id="review-submit-btn"
                            style="max-width:240px;">Submit Review
                    </button>
                </form>

            <?php elseif (!$isAuthenticated): ?>
                <div class="review-login-prompt">
                    <p>You need to be logged in to leave a review.</p>
                    <a href="/login?redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>"
                       class="btn btn-secondary btn-sm">Log in to review</a>
                </div>
            <?php else: ?>
                <div class="review-login-prompt">
                    <p><?= htmlspecialchars($canReview['reason'] ?? 'You cannot review this subscription.') ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- ── END REVIEWS ──────────────────────────────────────────────────── -->

</div><!-- /.container -->

<!-- Cart Badge -->
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

<!-- Mini Cart -->
<div class="mini-cart" id="mini-cart">
    <div class="mini-cart-header">
        <h3>Your Cart (<span id="cart-count">0</span>)</h3>
        <button class="close-cart" onclick="closeMiniCart()" aria-label="Close cart">×</button>
    </div>
    <div class="mini-cart-items" id="cart-items">
        <p style="text-align:center; color:var(--text-secondary); padding:2rem;">Your cart is empty</p>
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
    const API_BASE = '/api/<?= \App\Framework\Support\SiteContext::slug() ?? 'default' ?>';
    const SITE = '<?= \App\Framework\Support\SiteContext::slug() ?? 'default' ?>';
    const PLAN_ID = <?= (int)$plan->id ?>;
    let cartData = {items: [], total: 0, count: 0};

    // ─── Initialise ──────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', () => {
        loadCart();
        initializeSelections();
        initStarRatingInput();
    });

    // ─── Duration / delivery selection (unchanged from original) ─────────

    function initializeSelections() {
        document.querySelectorAll('.duration-option').forEach(option => {
            option.addEventListener('click', function () {
                document.querySelectorAll(`.duration-option[data-plan="${PLAN_ID}"]`)
                    .forEach(o => o.classList.remove('selected'));
                this.classList.add('selected');
                this.querySelector('input[type="radio"]').checked = true;
                updatePricingDisplay();
            });
        });

        document.querySelectorAll('.delivery-option').forEach(option => {
            option.addEventListener('click', function () {
                document.querySelectorAll(`.delivery-option[data-plan="${PLAN_ID}"]`)
                    .forEach(o => o.classList.remove('selected'));
                this.classList.add('selected');
                this.querySelector('input[type="radio"]').checked = true;
                updatePricingDisplay();
            });
        });

        document.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
            const p = radio.closest('.duration-option') || radio.closest('.delivery-option');
            if (p) p.classList.add('selected');
        });
    }

    function updatePricingDisplay() {
        const deliveryRadio = document.querySelector(`input[name="delivery_${PLAN_ID}"]:checked`);
        const isDigital = deliveryRadio && deliveryRadio.value === 'digital';

        document.querySelectorAll(`.duration-option[data-plan="${PLAN_ID}"]`).forEach(opt => {
            const radio = opt.querySelector('input[type="radio"]');
            const priceEl = opt.querySelector('.duration-price');
            const origEl = opt.querySelector('.original-price');
            const dPrice = parseFloat(radio.dataset.digital);
            const pPrice = parseFloat(radio.dataset.price);
            const dSale = parseFloat(radio.dataset.originalDigital);
            const pSale = parseFloat(radio.dataset.originalPrice);

            if (isDigital && dPrice > 0) {
                priceEl.textContent = '£' + dSale.toFixed(2);
                if (origEl && dSale < dPrice) origEl.textContent = '£' + dPrice.toFixed(2);
            } else {
                priceEl.textContent = '£' + pSale.toFixed(2);
                if (origEl && pSale < pPrice) origEl.textContent = '£' + pPrice.toFixed(2);
            }
        });
    }

    // ─── Cart ─────────────────────────────────────────────────────────────

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
        document.getElementById('cart-total').textContent = '£' + (cartData.total || 0).toFixed(2);
        document.getElementById('mini-cart-total').textContent = '£' + (cartData.total || 0).toFixed(2);
        const badge = document.getElementById('header-cart-count');
        badge.textContent = count;
        badge.style.display = count > 0 ? 'flex' : 'none';

        const container = document.getElementById('cart-items');
        if (!cartData.items?.length) {
            container.innerHTML = '<p style="text-align:center;color:var(--text-secondary);padding:2rem;">Your cart is empty</p>';
            return;
        }
        container.innerHTML = cartData.items.map(item => `
            <div class="cart-item">
                <div class="cart-item-name">${item.product_name || item.options?.plan_name || 'Subscription'}</div>
                <div class="cart-item-details">${item.options?.delivery_type || 'Print'} • ${item.options?.duration_months || 12} months</div>
                <div class="cart-item-price">£${(item.price || 0).toFixed(2)}</div>
            </div>`).join('');
    }

    async function addToCart(planId) {
        const durationRadio = document.querySelector(`input[name="duration_${planId}"]:checked`);
        const deliveryRadio = document.querySelector(`input[name="delivery_${planId}"]:checked`);

        if (!durationRadio) {
            showToast('Please select a subscription duration', 'error');
            return;
        }
        if (!deliveryRadio) {
            showToast('Please select a delivery type', 'error');
            return;
        }

        const deliveryType = deliveryRadio.value;
        const digitalPrice = parseFloat(durationRadio.dataset.digital);
        const printPrice = parseFloat(durationRadio.dataset.price);
        const price = (deliveryType === 'digital' && digitalPrice > 0) ? digitalPrice : printPrice;

        try {
            const res = await fetch(`${API_BASE}/cart/subscription`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    plan_id: planId,
                    pricing_id: parseInt(durationRadio.dataset.pricingId),
                    delivery_type: deliveryType,
                    duration_months: parseInt(durationRadio.value),
                    price: parseFloat(price),
                    issues: parseInt(durationRadio.dataset.issues),
                }),
            });
            const result = await res.json();
            if (result.success) {
                cartData = result;
                updateCartDisplay();
                openMiniCart();
                showToast('Added to cart!', 'success');
            } else {
                showToast(result.message || 'Failed to add to cart', 'error');
            }
        } catch (e) {
            showToast('An error occurred', 'error');
        }
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

    // ─── Star rating input ────────────────────────────────────────────────

    function initStarRatingInput() {
        const labels = document.querySelectorAll('.star-rating-input label');
        labels.forEach((label, i) => {
            label.addEventListener('mouseover', () => {
                labels.forEach((l, j) => l.classList.toggle('selected', j <= i));
            });
            label.addEventListener('mouseout', () => {
                const checked = document.querySelector('.star-rating-input input:checked');
                const checkedIdx = checked ? parseInt(checked.value) - 1 : -1;
                labels.forEach((l, j) => l.classList.toggle('selected', j <= checkedIdx));
            });
            label.addEventListener('click', () => {
                labels.forEach((l, j) => l.classList.toggle('selected', j <= i));
            });
        });
    }

    // ─── Submit review ────────────────────────────────────────────────────

    async function submitReview(e) {
        e.preventDefault();
        const form = document.getElementById('review-form');
        const btn = document.getElementById('review-submit-btn');
        const rating = form.querySelector('input[name="rating"]:checked')?.value;

        if (!rating) {
            showToast('Please select a star rating', 'error');
            return;
        }

        const payload = {
            plan_id: PLAN_ID,
            rating: parseInt(rating),
            title: form.querySelector('[name="title"]').value.trim(),
            comment: form.querySelector('[name="comment"]').value.trim(),
        };

        btn.disabled = true;
        btn.textContent = 'Submitting…';

        try {
            const res = await fetch(`${API_BASE}/plans/${PLAN_ID}/reviews`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                body: JSON.stringify(payload),
            });
            const data = await res.json();

            if (data.success) {
                showToast('Review submitted — thank you!', 'success');
                form.reset();
                document.querySelectorAll('.star-rating-input label').forEach(l => l.classList.remove('selected'));
                // Reload review section to show the new review
                setTimeout(() => loadReviews(1), 1200);
            } else {
                showToast(data.message || 'Could not submit your review', 'error');
                btn.disabled = false;
                btn.textContent = 'Submit Review';
            }
        } catch (err) {
            showToast('Network error — please try again', 'error');
            btn.disabled = false;
            btn.textContent = 'Submit Review';
        }
    }

    // ─── Load reviews (AJAX pagination) ──────────────────────────────────

    async function loadReviews(page) {
        try {
            const res = await fetch(`${API_BASE}/plans/${PLAN_ID}/reviews?page=${page}`, {
                headers: {'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json'},
            });
            const data = await res.json();
            if (!data.success) return;

            const r = data.data;
            // Re-render review list
            const list = document.getElementById('review-list');
            if (list) {
                list.innerHTML = r.reviews.map(renderReviewCard).join('');
            }
            // Re-render pagination
            const pag = document.getElementById('review-pagination');
            if (pag) {
                pag.innerHTML = renderReviewPagination(r.pagination, page);
            }
            // Scroll to top of reviews
            document.getElementById('reviews-section').scrollIntoView({behavior: 'smooth', block: 'start'});
        } catch (err) {
            console.error('Review load error:', err);
        }
    }

    function renderReviewCard(review) {
        const stars = [1, 2, 3, 4, 5].map(s =>
            `<span class="star ${s <= review.rating ? 'filled' : ''}" aria-hidden="true">★</span>`
        ).join('');
        const verified = review.is_verified_purchase
            ? '<span class="review-card__verified" title="Verified purchaser">✓ Verified</span>'
            : '';
        const title = review.title
            ? `<div class="review-card__title">${escHtml(review.title)}</div>`
            : '';

        return `
        <article class="review-card" data-review-id="${review.id}">
            <div class="review-card__header">
                <div class="review-card__meta">
                    <span class="review-card__author">${escHtml(review.author_name || 'Anonymous')}</span>
                    ${verified}
                    <span class="review-card__date">${escHtml(review.formatted_date || '')}</span>
                </div>
                <div class="stars" aria-label="${review.rating} out of 5 stars">${stars}</div>
            </div>
            ${title}
            <div class="review-card__comment">${escHtml(review.comment)}</div>
            <div class="review-card__helpful">
                <span>Helpful?</span>
                <button class="helpful-btn" onclick="voteHelpful(${review.id}, true, this)" aria-label="Mark as helpful">
                    👍 ${review.helpful_count || 0}
                </button>
                <button class="helpful-btn" onclick="voteHelpful(${review.id}, false, this)" aria-label="Mark as not helpful">
                    👎 ${review.unhelpful_count || 0}
                </button>
            </div>
        </article>`;
    }

    function renderReviewPagination(pagination, currentPage) {
        if (!pagination || pagination.total_pages <= 1) return '';
        const {total_pages: tot} = pagination;
        let html = '';
        html += `<button class="review-pagination__btn ${currentPage <= 1 ? 'disabled' : ''}" onclick="loadReviews(${currentPage - 1})" aria-label="Previous page">←</button>`;
        for (let p = 1; p <= tot; p++) {
            html += `<button class="review-pagination__btn ${p === currentPage ? 'active' : ''}" onclick="loadReviews(${p})" ${p === currentPage ? 'aria-current="page"' : ''}>${p}</button>`;
        }
        html += `<button class="review-pagination__btn ${currentPage >= tot ? 'disabled' : ''}" onclick="loadReviews(${currentPage + 1})" aria-label="Next page">→</button>`;
        return html;
    }

    // ─── Vote helpful ─────────────────────────────────────────────────────

    async function voteHelpful(reviewId, isHelpful, btn) {
        try {
            const res = await fetch(`${API_BASE}/reviews/${reviewId}/helpful`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                body: JSON.stringify({is_helpful: isHelpful}),
            });
            const data = await res.json();
            if (data.success) {
                btn.classList.add('voted');
                // Update counts from response
                const card = btn.closest('.review-card');
                const btns = card.querySelectorAll('.helpful-btn');
                if (data.helpful_count != null) btns[0].innerHTML = `👍 ${data.helpful_count}`;
                if (data.unhelpful_count != null) btns[1].innerHTML = `👎 ${data.unhelpful_count}`;
            }
        } catch (e) {
            console.error('Vote error:', e);
        }
    }

    // ─── Toast ────────────────────────────────────────────────────────────

    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.className = `toast ${type} show`;
        setTimeout(() => toast.classList.remove('show'), 3500);
    }

    function escHtml(s) {
        return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
</script>
</body>
</html>