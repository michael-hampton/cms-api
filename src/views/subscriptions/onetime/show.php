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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600&display=swap"
          rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --blue: #2563eb;
            --blue-dark: #1e40af;
            --blue-light: #eff6ff;
            --green: #10b981;
            --red: #ef4444;
            --gold: #f59e0b;
            --border: #e2e8f0;
            --bg: #f8fafc;
            --ink: #1e293b;
            --ink-soft: #475569;
            --ink-muted: #94a3b8;
            --white: #ffffff;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, .08);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, .1);
            --shadow-lg: 0 10px 30px rgba(0, 0, 0, .12);
            --radius: .75rem;
            --radius-lg: 1rem;
        }

        body {
            font-family: 'DM Sans', -apple-system, sans-serif;
            background: var(--bg);
            color: var(--ink);
            line-height: 1.6;
        }

        /* ── Page shell ───────────────────────────────────────────── */
        .page {
            max-width: 1100px;
            margin: 0 auto;
            padding: 2rem 1.5rem 4rem;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: .5rem;
            margin-bottom: 2rem;
            font-size: .875rem;
            color: var(--ink-muted);
        }
        .breadcrumb a {
            color: var(--blue);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: .375rem;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* ── Two-column layout ────────────────────────────────────── */
        .plan-layout {
            display: grid;
            grid-template-columns: 260px 1fr;
            gap: 2rem;
            align-items: start;
            margin-bottom: 2.5rem;
        }

        /* LEFT COLUMN */
        .plan-left {
            position: sticky;
            top: 1.5rem;
        }

        .plan-cover {
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            margin-bottom: 1rem;
            background: var(--blue-light);
        }

        .plan-cover img {
            width: 100%;
            height: auto;
            display: block;
        }

        .plan-cover__placeholder {
            aspect-ratio: 3/4;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Playfair Display', serif;
            font-size: 4rem;
            font-weight: 700;
            color: var(--blue);
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
        }

        .trust-list {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .trust-item {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .75rem 1rem;
            font-size: .85rem;
            font-weight: 500;
            color: var(--ink-soft);
            border-bottom: 1px solid var(--border);
        }

        .trust-item:last-child {
            border-bottom: none;
        }

        .trust-item svg {
            flex-shrink: 0;
            color: var(--blue);
        }

        /* RIGHT COLUMN */
        .plan-right {
        }

        .plan-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.25rem;
            font-weight: 700;
            line-height: 1.15;
            margin-bottom: .75rem;
            color: var(--ink);
        }

        .plan-description {
            font-size: 1rem;
            color: var(--ink-soft);
            line-height: 1.75;
            margin-bottom: 1.5rem;
        }

        /* ── Card (white box) ─────────────────────────────────────── */
        .card {
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .card-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 1.25rem;
            padding-bottom: .875rem;
            border-bottom: 1px solid var(--border);
        }

        /* ── Delivery tabs ────────────────────────────────────────── */
        .delivery-tabs {
            display: flex;
            gap: .75rem;
            margin-bottom: 1.5rem;
        }

        .delivery-tab {
            flex: 1;
            border: 2px solid var(--border);
            border-radius: var(--radius);
            padding: .875rem 1rem;
            cursor: pointer;
            transition: all .2s;
            background: none;
            text-align: left;
            font-family: inherit;
        }

        .delivery-tab:hover {
            border-color: var(--blue);
        }

        .delivery-tab.selected {
            border-color: var(--blue);
            background: var(--blue-light);
        }

        .delivery-tab input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
        }

        .delivery-tab__name {
            font-weight: 600;
            font-size: .95rem;
            color: var(--ink);
            display: flex;
            align-items: center;
            gap: .5rem;
            margin-bottom: .2rem;
        }

        .delivery-tab__desc {
            font-size: .8rem;
            color: var(--ink-muted);
        }

        .delivery-tab.selected .delivery-tab__name {
            color: var(--blue);
        }

        /* ── Duration options ─────────────────────────────────────── */
        .duration-option {
            border: 2px solid var(--border);
            border-radius: var(--radius);
            padding: 1rem 1.25rem;
            margin-bottom: .75rem;
            cursor: pointer;
            transition: all .2s;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .duration-option:hover {
            border-color: var(--blue);
        }
        .duration-option.selected {
            border-color: var(--blue);
            background: var(--blue-light);
        }
        .duration-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .duration-option__radio {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 2px solid var(--border);
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all .2s;
        }

        .duration-option.selected .duration-option__radio {
            border-color: var(--blue);
            background: var(--blue);
        }

        .duration-option.selected .duration-option__radio::after {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: white;
        }

        .duration-option__left {
            display: flex;
            align-items: center;
            gap: .875rem;
            flex: 1;
        }

        .duration-option__info {
        }

        .duration-option__label {
            font-weight: 600;
            font-size: .95rem;
            color: var(--ink);
        }

        .duration-option__period {
            font-size: .8rem;
            color: var(--ink-muted);
            margin-top: .1rem;
        }

        .duration-option__right {
            text-align: right;
            flex-shrink: 0;
        }

        .duration-option__price {
            font-size: 1.375rem;
            font-weight: 700;
            color: var(--blue);
            line-height: 1;
        }

        .duration-option__was {
            font-size: .8rem;
            text-decoration: line-through;
            color: var(--ink-muted);
            margin-bottom: .1rem;
        }

        .duration-option__per-issue {
            font-size: .75rem;
            color: var(--ink-muted);
            margin-top: .2rem;
        }

        .save-badge {
            background: var(--red);
            color: white;
            font-size: .7rem;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: .03em;
            margin-top: .3rem;
            display: inline-block;
        }

        /* ── Add to cart button ───────────────────────────────────── */
        .btn-add-cart {
            width: 100%;
            padding: .95rem;
            background: var(--blue);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-family: inherit;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
            margin-top: 1.25rem;
        }

        .btn-add-cart:hover {
            background: var(--blue-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-add-cart:active {
            transform: translateY(0);
        }

        /* ── Features card ────────────────────────────────────────── */
        .features-list {
            list-style: none;
        }
        .features-list li {
            display: flex;
            align-items: center;
            gap: .875rem;
            padding: .75rem 0;
            border-bottom: 1px solid var(--border);
            font-size: .95rem;
            color: var(--ink-soft);
        }

        .features-list li:last-child {
            border-bottom: none;
        }
        .check-icon {
            width: 22px;
            height: 22px;
            background: var(--green);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .check-icon svg {
            width: 12px;
            height: 12px;
            stroke: white;
            stroke-width: 3;
        }

        /* ── Reviews ──────────────────────────────────────────────── */
        .reviews-compliance-notice {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: .5rem;
            padding: .875rem 1rem;
            margin-bottom: 1.5rem;
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

        .reviews-summary {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 2rem;
            align-items: start;
            margin-bottom: 1.5rem;
        }

        .reviews-score {
            text-align: center;
            min-width: 110px;
        }
        .reviews-score__avg {
            font-family: 'Playfair Display', serif;
            font-size: 3rem;
            font-weight: 700;
            line-height: 1;
            color: var(--ink);
        }
        .reviews-score__stars {
            display: flex;
            justify-content: center;
            gap: 2px;
            margin: .35rem 0 .25rem;
            color: var(--gold);
            font-size: 1rem;
        }

        .reviews-score__count {
            font-size: .8rem;
            color: var(--ink-muted);
        }

        .rating-bar-row {
            display: grid;
            grid-template-columns: 1.5rem 1fr 2.5rem;
            align-items: center;
            gap: .6rem;
            margin-bottom: .4rem;
        }

        .rating-bar-label {
            font-size: .8rem;
            color: var(--ink-muted);
            text-align: right;
        }
        .rating-bar-track {
            height: 7px;
            background: var(--border);
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
            color: var(--ink-muted);
        }

        .review-list {
            display: flex;
            flex-direction: column;
            margin-top: 1.5rem;
        }
        .review-card {
            padding: 1.25rem 0;
            border-bottom: 1px solid var(--border);
        }

        .review-card:last-child {
            border-bottom: none;
        }
        .review-card__header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: .5rem;
            flex-wrap: wrap;
        }
        .review-card__meta {
            display: flex;
            align-items: center;
            gap: .6rem;
            flex-wrap: wrap;
        }

        .review-card__author {
            font-weight: 600;
            font-size: .9rem;
        }

        .review-card__date {
            font-size: .8rem;
            color: var(--ink-muted);
        }
        .review-card__verified {
            font-size: .7rem;
            font-weight: 600;
            background: #d1fae5;
            color: #065f46;
            padding: 2px 8px;
            border-radius: 100px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .review-card__title {
            font-weight: 600;
            font-size: .95rem;
            margin-bottom: .3rem;
        }

        .review-card__comment {
            font-size: .9rem;
            color: var(--ink-soft);
            line-height: 1.65;
        }
        .review-card__helpful {
            display: flex;
            align-items: center;
            gap: .5rem;
            margin-top: .75rem;
            font-size: .8rem;
            color: var(--ink-muted);
        }
        .helpful-btn {
            background: none;
            border: 1px solid var(--border);
            border-radius: .375rem;
            padding: 3px 10px;
            font-size: .75rem;
            cursor: pointer;
            transition: all .2s;
            color: var(--ink-muted);
        }

        .helpful-btn:hover {
            border-color: var(--blue);
            color: var(--blue);
        }

        .helpful-btn.voted {
            background: var(--blue-light);
            border-color: var(--blue);
            color: var(--blue);
        }

        .stars {
            display: inline-flex;
            gap: 1px;
        }

        .star {
            color: var(--border);
            font-size: .9rem;
        }

        .star.filled {
            color: var(--gold);
        }

        /* ── Write review form ────────────────────────────────────── */
        .write-review-section {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
        }
        .write-review-section h3 {
            font-size: 1.1rem;
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
            font-size: .8rem;
            font-weight: 600;
            margin-bottom: .35rem;
            color: var(--ink-muted);
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: .7rem .9rem;
            border: 1.5px solid var(--border);
            border-radius: .5rem;
            font-family: inherit;
            font-size: .95rem;
            color: var(--ink);
            transition: border-color .2s;
            outline: none;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            border-color: var(--blue);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

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
        .review-login-prompt {
            text-align: center;
            padding: 1.75rem;
            background: var(--bg);
            border-radius: var(--radius);
            border: 1px dashed var(--border);
            margin-top: 1.5rem;
        }

        .review-login-prompt p {
            margin-bottom: 1rem;
            color: var(--ink-soft);
        }
        .reviews-empty {
            text-align: center;
            padding: 2rem;
            color: var(--ink-muted);
            font-size: .95rem;
        }

        .reviews-empty__icon {
            font-size: 2.5rem;
            margin-bottom: .75rem;
        }

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
            border: 1px solid var(--border);
            border-radius: .375rem;
            background: white;
            font-size: .875rem;
            color: var(--ink);
            cursor: pointer;
            transition: all .2s;
            font-family: inherit;
        }
        .review-pagination__btn:hover:not(.active):not(.disabled) {
            border-color: var(--blue);
            color: var(--blue);
        }
        .review-pagination__btn.active {
            background: var(--blue);
            color: white;
            border-color: var(--blue);
            pointer-events: none;
        }

        .review-pagination__btn.disabled {
            opacity: .35;
            pointer-events: none;
        }

        /* ── Buttons ──────────────────────────────────────────────── */
        .btn {
            display: inline-block;
            padding: .75rem 1.5rem;
            border: none;
            border-radius: var(--radius);
            font-family: inherit;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
            text-decoration: none;
            text-align: center;
            font-size: .95rem;
        }

        .btn-primary {
            background: var(--blue);
            color: white;
        }

        .btn-primary:hover {
            background: var(--blue-dark);
        }

        .btn-secondary {
            background: white;
            color: var(--blue);
            border: 1.5px solid var(--blue);
        }

        .btn-secondary:hover {
            background: var(--blue-light);
        }

        .btn-sm {
            padding: .5rem 1rem;
            font-size: .875rem;
        }

        /* ── Cart badge ───────────────────────────────────────────── */
        .cart-badge {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            background: white;
            border-radius: var(--radius-lg);
            padding: .875rem 1.25rem;
            box-shadow: var(--shadow-lg);
            cursor: pointer;
            transition: all .2s;
            z-index: 1000;
            border: 1px solid var(--border);
        }

        .cart-badge:hover {
            transform: scale(1.03);
        }

        .cart-info {
            display: flex;
            align-items: center;
            gap: .875rem;
        }

        .cart-icon {
            position: relative;
        }

        .cart-count {
            position: absolute;
            top: -7px;
            right: -7px;
            background: var(--red);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .7rem;
            font-weight: 700;
        }

        .cart-total {
            font-weight: 700;
            color: var(--blue);
            font-size: 1rem;
        }

        /* ── Mini cart ────────────────────────────────────────────── */
        .mini-cart {
            position: fixed;
            top: 0;
            right: -420px;
            width: 400px;
            height: 100vh;
            background: white;
            box-shadow: var(--shadow-lg);
            transition: right .3s ease;
            z-index: 1001;
            display: flex;
            flex-direction: column;
        }

        .mini-cart.open {
            right: 0;
        }

        .mini-cart-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .mini-cart-header h3 {
            font-size: 1.1rem;
            font-weight: 700;
        }

        .close-cart {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--ink-muted);
            line-height: 1;
        }

        .mini-cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 1.25rem;
        }

        .cart-item {
            padding: .875rem 0;
            border-bottom: 1px solid var(--border);
        }

        .cart-item-name {
            font-weight: 600;
            margin-bottom: .3rem;
            font-size: .95rem;
        }

        .cart-item-details {
            font-size: .85rem;
            color: var(--ink-muted);
            margin-bottom: .3rem;
        }

        .cart-item-price {
            font-weight: 600;
            color: var(--blue);
            font-size: .95rem;
        }

        .mini-cart-footer {
            padding: 1.25rem;
            border-top: 1px solid var(--border);
        }

        .cart-total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            font-size: 1rem;
            font-weight: 700;
        }

        .cart-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, .45);
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
            padding: .875rem 1.25rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            display: none;
            align-items: center;
            gap: 1rem;
            z-index: 10000;
            font-size: .9rem;
        }

        .toast.show {
            display: flex;
            animation: slideIn .3s ease-out;
        }

        .toast.success {
            border-left: 4px solid var(--green);
        }

        .toast.error {
            border-left: 4px solid var(--red);
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

        /* ── Responsive ───────────────────────────────────────────── */
        @media (max-width: 768px) {
            .plan-layout {
                grid-template-columns: 1fr;
            }

            .plan-left {
                position: static;
                display: grid;
                grid-template-columns: 180px 1fr;
                gap: 1rem;
                align-items: start;
            }

            .plan-title {
                font-size: 1.75rem;
            }

            .delivery-tabs {
                flex-direction: column;
            }

            .reviews-summary {
                grid-template-columns: 1fr;
            }

            .mini-cart {
                width: 100%;
                right: -100%;
            }

            .cart-badge {
                top: auto;
                bottom: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .plan-left {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="page">

    <div class="breadcrumb">
        <a href="/subscriptions">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
            Back to Shop
        </a>
    </div>

    <?php $coverImage = $plan->print_image_url ?? $plan->digital_image_url ?? null; ?>

    <!-- ── Two-column layout ──────────────────────────────────────────────── -->
    <div class="plan-layout">

        <!-- LEFT: cover image + trust badges -->
        <div class="plan-left">
            <div class="plan-cover">
                <?php if ($coverImage): ?>
                    <img src="<?= htmlspecialchars($coverImage) ?>" alt="<?= htmlspecialchars($plan->name) ?>">
                <?php else: ?>
                    <div class="plan-cover__placeholder">
                        <?= strtoupper(substr($plan->name, 0, 1)) ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="trust-list">
                <div class="trust-item">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 11 12 14 22 4"/>
                        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                    </svg>
                    Cancel any time
                </div>
                <div class="trust-item">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 8v4l3 3"/>
                    </svg>
                    Best price guarantee
                </div>
                <div class="trust-item">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="1" y="3" width="15" height="13" rx="2"/>
                        <path d="M16 8h4l3 5v3h-7V8z"/>
                        <circle cx="5.5" cy="18.5" r="2.5"/>
                        <circle cx="18.5" cy="18.5" r="2.5"/>
                    </svg>
                    No hidden costs
                </div>
                <div class="trust-item">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                    You're in control
                </div>
            </div>
        </div>

        <!-- RIGHT: title, description, subscription card -->
        <div class="plan-right">

            <h1 class="plan-title"><?= htmlspecialchars($plan->name) ?></h1>
            <?php if ($plan->description): ?>
                <p class="plan-description"><?= htmlspecialchars($plan->description) ?></p>
            <?php endif; ?>

            <!-- Subscription card -->
            <div class="card">
                <div class="card-title">Choose Your Subscription</div>

                <?php
                $deliveryOptions = $plan->getDeliveryOptions();
                $hasMultipleOptions = count($deliveryOptions) > 1;
                ?>

                <?php if ($hasMultipleOptions): ?>
                    <div class="delivery-tabs">
                        <?php if ($plan->hasDigitalOption()): ?>
                            <div class="delivery-tab selected" data-plan="<?= $plan->id ?>">
                                <input type="radio" name="delivery_<?= $plan->id ?>" value="digital" checked>
                                <div class="delivery-tab__name">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2">
                                        <rect x="5" y="2" width="14" height="20" rx="2"/>
                                        <line x1="12" y1="18" x2="12" y2="18"/>
                                    </svg>
                                    Digital
                                </div>
                                <div class="delivery-tab__desc">Instant digital access</div>
                            </div>
                        <?php endif; ?>
                        <?php if ($plan->hasPrintOption()): ?>
                            <div class="delivery-tab<?= !$plan->hasDigitalOption() ? ' selected' : '' ?>"
                                 data-plan="<?= $plan->id ?>">
                                <input type="radio" name="delivery_<?= $plan->id ?>"
                                       value="print"<?= !$plan->hasDigitalOption() ? ' checked' : '' ?>>
                                <div class="delivery-tab__name">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                         stroke-width="2">
                                        <path d="M4 22h16a2 2 0 0 0 2-2V7l-5-5H6a2 2 0 0 0-2 2v3"/>
                                        <path d="M14 2v4a2 2 0 0 0 2 2h4"/>
                                        <rect x="2" y="13" width="8" height="8" rx="1"/>
                                    </svg>
                                    Print
                                </div>
                                <div class="delivery-tab__desc">Delivered to your door</div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <input type="radio" name="delivery_<?= $plan->id ?>" value="<?= $deliveryOptions[0] ?>" checked
                           style="display:none;">
                <?php endif; ?>

                <div class="duration-options">
                    <?php foreach ($plan->pricingTiers as $index => $pricing):
                        $actualPrice = $pricing->sale_price && $pricing->sale_price < $pricing->price
                                ? $pricing->sale_price : $pricing->price;
                        $originalPrice = $pricing->price;
                        ?>
                        <div class="duration-option<?= $index === 0 ? ' selected' : '' ?>" data-plan="<?= $plan->id ?>">
                            <input type="radio" name="duration_<?= $plan->id ?>"
                                   value="<?= $pricing->duration_months ?>"
                                   data-pricing-id="<?= $pricing->id ?>"
                                   data-price="<?= $pricing->price ?>"
                                   data-digital="<?= $pricing->digital_price ?? 0 ?>"
                                   data-original-price="<?= $pricing->sale_price ?? $pricing->price ?>"
                                   data-original-digital="<?= $pricing->digital_sale_price ?? $pricing->digital_price ?>"
                                   data-issues="<?= $pricing->issue_count ?>"
                                    <?= $index === 0 ? 'checked' : '' ?>>

                            <div class="duration-option__left">
                                <div class="duration-option__radio"></div>
                                <div class="duration-option__info">
                                    <div class="duration-option__label"><?= htmlspecialchars($pricing->label) ?></div>
                                    <div class="duration-option__period"><?= htmlspecialchars($pricing->period_description) ?></div>
                                </div>
                            </div>

                            <div class="duration-option__right">
                                <?php if ($pricing->hasDiscount()): ?>
                                    <div class="duration-option__was"><?= $currencySymbol ?><?= number_format($originalPrice, 2) ?></div>
                                <?php endif; ?>
                                <div class="duration-option__price"><?= $currencySymbol ?><?= number_format($actualPrice, 2) ?></div>
                                <?php if ($pricing->issue_count > 0): ?>
                                    <div class="duration-option__per-issue">
                                        <?= $currencySymbol ?><?= number_format($pricing->getPricePerIssue(), 2) ?>
                                        /issue
                                    </div>
                                <?php endif; ?>
                                <?php if ($pricing->getSavingsText()): ?>
                                    <div class="save-badge"><?= $pricing->getSavingsText() ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button class="btn-add-cart" onclick="addToCart(<?= $plan->id ?>)">
                    Add to Cart
                </button>
            </div>
            <!-- /subscription card -->

        </div><!-- /.plan-right -->
    </div><!-- /.plan-layout -->

    <!-- ── What's Included ───────────────────────────────────────────────── -->
    <?php if (!empty($plan->features)): ?>
        <div class="card">
            <div class="card-title">What's Included</div>
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
         REVIEWS — DMCC Act 2024 compliant
         ════════════════════════════════════════════════════════════════════ -->
    <div class="card" id="reviews-section">
        <div class="card-title">Customer Reviews</div>

        <div class="reviews-compliance-notice" role="note">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <span>
                Reviews are submitted by customers who have purchased this subscription.
                We verify each review comes from a genuine account and do not edit, suppress,
                or incentivise reviews.
                <a href="/reviews-policy"
                   style="color:inherit;text-decoration:underline;">Learn about our review policy</a>.
            </span>
        </div>

        <?php if ($totalReviews > 0): ?>
            <div class="reviews-summary">
                <div class="reviews-score">
                    <div class="reviews-score__avg"><?= number_format($averageRating, 1) ?></div>
                    <div class="reviews-score__stars"
                         aria-label="<?= number_format($averageRating, 1) ?> out of 5 stars">
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                            <span aria-hidden="true"><?= $s <= round($averageRating) ? '★' : '☆' ?></span>
                        <?php endfor; ?>
                    </div>
                    <div class="reviews-score__count"><?= number_format($totalReviews) ?>
                        review<?= $totalReviews !== 1 ? 's' : '' ?></div>
                </div>

                <div class="rating-bars" aria-label="Rating breakdown">
                    <?php foreach ([5, 4, 3, 2, 1] as $r): ?>
                        <div class="rating-bar-row">
                            <span class="rating-bar-label" aria-hidden="true"><?= $r ?>★</span>
                            <div class="rating-bar-track" role="progressbar"
                                 aria-valuenow="<?= $percentages[$r] ?? 0 ?>" aria-valuemin="0" aria-valuemax="100">
                                <div class="rating-bar-fill" style="width:<?= $percentages[$r] ?? 0 ?>%"></div>
                            </div>
                            <span class="rating-bar-pct"><?= $percentages[$r] ?? 0 ?>%</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="review-list" id="review-list">
                <?php foreach ($reviewList as $review): ?>
                    <article class="review-card" data-review-id="<?= (int)$review['id'] ?>">
                        <div class="review-card__header">
                            <div class="review-card__meta">
                                <span class="review-card__author"><?= htmlspecialchars($review['author_name'] ?? 'Anonymous') ?></span>
                                <?php if ($review['is_verified_purchase']): ?>
                                    <span class="review-card__verified">✓ Verified</span>
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

            <?php if (!empty($pagination) && ($pagination['total_pages'] ?? 1) > 1): ?>
                <nav class="review-pagination" aria-label="Review pages" id="review-pagination">
                    <?php $cur = $pagination['current_page'];
                    $tot = $pagination['total_pages']; ?>
                    <button class="review-pagination__btn <?= $cur <= 1 ? 'disabled' : '' ?>"
                            onclick="loadReviews(<?= $cur - 1 ?>)" aria-label="Previous page">←
                    </button>
                    <?php for ($p = 1; $p <= $tot; $p++): ?>
                        <button class="review-pagination__btn <?= $p === $cur ? 'active' : '' ?>"
                                onclick="loadReviews(<?= $p ?>)" <?= $p === $cur ? 'aria-current="page"' : '' ?>><?= $p ?></button>
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
                    misleading reviews may be removed in accordance with the Digital Markets,
                    Competition and Consumers Act 2024.
                </div>

                <form class="review-form" id="review-form" onsubmit="submitReview(event)">
                    <input type="hidden" name="plan_id" value="<?= (int)$plan->id ?>">

                    <div class="form-group">
                        <label for="review-rating">Your Rating *</label>
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
                        <input type="text" id="review-title" name="title" maxlength="120"
                               placeholder="Summarise your experience">
                    </div>

                    <div class="form-group">
                        <label for="review-comment">Your Review *</label>
                        <textarea id="review-comment" name="comment" required minlength="10" maxlength="2000"
                                  placeholder="Tell others about your experience with this subscription…"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm" id="review-submit-btn"
                            style="align-self:flex-start;">Submit Review
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

</div><!-- /.page -->

<!-- Cart Badge -->
<div class="cart-badge" onclick="openMiniCart()">
    <div class="cart-info">
        <div class="cart-icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
        <p style="text-align:center;color:var(--ink-muted);padding:2rem;">Your cart is empty</p>
    </div>
    <div class="mini-cart-footer">
        <div class="cart-total-row">
            <span>Total:</span>
            <span id="mini-cart-total">£0.00</span>
        </div>
        <button class="btn btn-primary" style="width:100%;" onclick="goToCheckout()">Proceed to Checkout</button>
    </div>
</div>

<div class="cart-overlay" id="cart-overlay" onclick="closeMiniCart()"></div>
<div class="toast" id="toast" role="alert" aria-live="polite"></div>

<script>
    const API_BASE = '/api/<?= \App\Framework\Support\SiteContext::slug() ?? 'default' ?>';
    const SITE = '<?= \App\Framework\Support\SiteContext::slug() ?? 'default' ?>';
    const PLAN_ID = <?= (int)$plan->id ?>;
    let cartData = {items: [], total: 0, count: 0};
    const CURRENCY_SYMBOL = '<?= $currencySymbol ?>';

    document.addEventListener('DOMContentLoaded', () => {
        loadCart();
        initializeSelections();
        initStarRatingInput();
    });

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

        document.querySelectorAll('.delivery-tab').forEach(option => {
            option.addEventListener('click', function () {
                document.querySelectorAll(`.delivery-tab[data-plan="${PLAN_ID}"]`)
                    .forEach(o => o.classList.remove('selected'));
                this.classList.add('selected');
                this.querySelector('input[type="radio"]').checked = true;
                updatePricingDisplay();
            });
        });

        document.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
            const p = radio.closest('.duration-option') || radio.closest('.delivery-tab');
            if (p) p.classList.add('selected');
        });
    }

    function updatePricingDisplay() {
        const deliveryRadio = document.querySelector(`input[name="delivery_${PLAN_ID}"]:checked`);
        const isDigital = deliveryRadio && deliveryRadio.value === 'digital';

        document.querySelectorAll(`.duration-option[data-plan="${PLAN_ID}"]`).forEach(opt => {
            const radio = opt.querySelector('input[type="radio"]');
            const priceEl = opt.querySelector('.duration-option__price');
            const wasEl = opt.querySelector('.duration-option__was');
            const dPrice = parseFloat(radio.dataset.digital);
            const pPrice = parseFloat(radio.dataset.price);
            const dSale = parseFloat(radio.dataset.originalDigital);
            const pSale = parseFloat(radio.dataset.originalPrice);

            if (isDigital && dPrice > 0) {
                priceEl.textContent = CURRENCY_SYMBOL + dSale.toFixed(2);
                if (wasEl && dSale < dPrice) wasEl.textContent = CURRENCY_SYMBOL + dPrice.toFixed(2);
            } else {
                priceEl.textContent = CURRENCY_SYMBOL + pSale.toFixed(2);
                if (wasEl && pSale < pPrice) wasEl.textContent = CURRENCY_SYMBOL + pPrice.toFixed(2);
            }
        });
    }

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
        document.getElementById('cart-total').textContent = CURRENCY_SYMBOL + (cartData.total || 0).toFixed(2);
        document.getElementById('mini-cart-total').textContent = CURRENCY_SYMBOL + (cartData.total || 0).toFixed(2);
        const badge = document.getElementById('header-cart-count');
        badge.textContent = count;
        badge.style.display = count > 0 ? 'flex' : 'none';

        const container = document.getElementById('cart-items');
        if (!cartData.items?.length) {
            container.innerHTML = '<p style="text-align:center;color:var(--ink-muted);padding:2rem;">Your cart is empty</p>';
            return;
        }
        container.innerHTML = cartData.items.map(item => `
            <div class="cart-item">
                <div class="cart-item-name">${item.product_name || item.options?.plan_name || 'Subscription'}</div>
                <div class="cart-item-details">${item.options?.delivery_type || 'Print'} • ${item.options?.duration_months || 12} months</div>
                <div class="cart-item-price">${CURRENCY_SYMBOL}${(item.price || 0).toFixed(2)}</div>
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

    async function loadReviews(page) {
        try {
            const res = await fetch(`${API_BASE}/plans/${PLAN_ID}/reviews?page=${page}`, {
                headers: {'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json'},
            });
            const data = await res.json();
            if (!data.success) return;
            const r = data.data;
            const list = document.getElementById('review-list');
            if (list) list.innerHTML = r.reviews.map(renderReviewCard).join('');
            const pag = document.getElementById('review-pagination');
            if (pag) pag.innerHTML = renderReviewPagination(r.pagination, page);
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
            ? '<span class="review-card__verified">✓ Verified</span>' : '';
        const title = review.title
            ? `<div class="review-card__title">${escHtml(review.title)}</div>` : '';
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
                <button class="helpful-btn" onclick="voteHelpful(${review.id}, true, this)">👍 ${review.helpful_count || 0}</button>
                <button class="helpful-btn" onclick="voteHelpful(${review.id}, false, this)">👎 ${review.unhelpful_count || 0}</button>
            </div>
        </article>`;
    }

    function renderReviewPagination(pagination, currentPage) {
        if (!pagination || pagination.total_pages <= 1) return '';
        const {total_pages: tot} = pagination;
        let html = '';
        html += `<button class="review-pagination__btn ${currentPage <= 1 ? 'disabled' : ''}" onclick="loadReviews(${currentPage - 1})">←</button>`;
        for (let p = 1; p <= tot; p++) {
            html += `<button class="review-pagination__btn ${p === currentPage ? 'active' : ''}" onclick="loadReviews(${p})">${p}</button>`;
        }
        html += `<button class="review-pagination__btn ${currentPage >= tot ? 'disabled' : ''}" onclick="loadReviews(${currentPage + 1})">→</button>`;
        return html;
    }

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
                const card = btn.closest('.review-card');
                const btns = card.querySelectorAll('.helpful-btn');
                if (data.helpful_count != null) btns[0].innerHTML = `👍 ${data.helpful_count}`;
                if (data.unhelpful_count != null) btns[1].innerHTML = `👎 ${data.unhelpful_count}`;
            }
        } catch (e) {
            console.error('Vote error:', e);
        }
    }

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