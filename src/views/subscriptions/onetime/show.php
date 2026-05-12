<?php
/*
 * View: subscriptions/onetime/show.php
 *
 * JS refactored to match index.php class/state architecture:
 *   - CartService  — owns cart state + all API calls
 *   - MiniCartUI   — renders the cart sidebar, syncs button states
 *   - ShowPageApp  — composition root; wires delivery/duration UI + reviews
 *
 * Mini cart now matches index.php: qty controls, remove per-item, clear cart.
 */

use App\Framework\Support\SiteContext;

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
            --red-light: #fef2f2;
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

        /* ── Page shell ───────────────────────────────────────────────── */
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

        /* ── Two-column layout ────────────────────────────────────────── */
        .plan-layout {
            display: grid;
            grid-template-columns: 260px 1fr;
            gap: 2rem;
            align-items: start;
            margin-bottom: 2.5rem;
        }

        /* LEFT */
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

        /* RIGHT */
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

        /* ── Card ─────────────────────────────────────────────────────── */
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

        /* ── Delivery tabs ────────────────────────────────────────────── */
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

        /* ── Duration options ─────────────────────────────────────────── */
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

        /* ── Add to cart button ───────────────────────────────────────── */
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

        .btn-add-cart:disabled {
            opacity: .6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* ── Features card ────────────────────────────────────────────── */
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

        /* ── Reviews ──────────────────────────────────────────────────── */
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

        /* ── Write review form ────────────────────────────────────────── */
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

        /* ── Buttons ──────────────────────────────────────────────────── */
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

        /* ── Cart badge ───────────────────────────────────────────────── */
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

        /* ── Mini cart ────────────────────────────────────────────────── */
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

        /* ── Cart item (matches index.php) ────────────────────────────── */
        .cart-item {
            padding: .875rem 0;
            border-bottom: 1px solid var(--border);
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-item-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: .5rem;
            margin-bottom: .5rem;
        }

        .cart-item-name {
            font-weight: 600;
            font-size: .9rem;
            flex: 1;
        }

        .cart-item-remove {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--ink-muted);
            padding: 2px;
            line-height: 0;
            border-radius: 4px;
            transition: color .2s, background .2s;
            flex-shrink: 0;
        }

        .cart-item-remove:hover {
            color: var(--red);
            background: var(--red-light);
        }

        .cart-item-details {
            font-size: .8rem;
            color: var(--ink-muted);
            margin-bottom: .5rem;
        }

        .cart-item-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cart-item-price {
            font-weight: 700;
            color: var(--blue);
            font-size: .95rem;
        }

        /* ── Quantity controls (matches index.php) ────────────────────── */
        .qty-controls {
            display: flex;
            align-items: center;
            border: 1.5px solid var(--border);
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
            transition: background .15s, color .15s;
            line-height: 1;
        }

        .qty-btn:hover:not(:disabled) {
            background: var(--border);
            color: var(--ink);
        }

        .qty-btn:disabled {
            opacity: .4;
            cursor: not-allowed;
        }

        .qty-value {
            min-width: 28px;
            text-align: center;
            font-size: .875rem;
            font-weight: 600;
            color: var(--ink);
            padding: 0 4px;
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

        /* ── Clear cart button (matches index.php) ────────────────────── */
        .clear-cart-btn {
            background: none;
            border: 1.5px solid var(--border);
            border-radius: 6px;
            padding: .5rem 1rem;
            font-size: .8rem;
            font-weight: 600;
            color: var(--ink-muted);
            cursor: pointer;
            width: 100%;
            margin-bottom: .75rem;
            transition: all .2s;
        }

        .clear-cart-btn:hover {
            border-color: var(--red);
            color: var(--red);
            background: var(--red-light);
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

        /* ── Toast ────────────────────────────────────────────────────── */
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

        /* ── Trial banner ─────────────────────────────────────────────── */
        .trial-banner {
            display: flex;
            align-items: flex-start;
            gap: .875rem;
            background: #f0fdf4;
            border: 1.5px solid #6ee7b7;
            border-radius: var(--radius);
            padding: 1rem 1.1rem;
            margin-bottom: 1.25rem;
        }

        .trial-banner__icon {
            font-size: 1.5rem;
            line-height: 1;
            flex-shrink: 0;
            margin-top: .1rem;
        }

        .trial-banner__title {
            font-weight: 700;
            font-size: .95rem;
            color: #065f46;
        }

        .trial-banner__body {
            font-size: .8rem;
            color: #047857;
            margin-top: .2rem;
            line-height: 1.6;
        }

        /* ── Responsive ───────────────────────────────────────────────── */
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
        <a href="/press-stack">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
            Back to Shop
        </a>
    </div>

    <?php $coverImage = $plan->print_image_url ?? $plan->digital_image_url ?? null; ?>

    <!-- ── Two-column layout ─────────────────────────────────────────── -->
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

                <!-- ── Trial banner ───────────────────────────────────── -->
                <?php if ($plan->hasTrial()): ?>
                    <div class="trial-banner" role="note" aria-label="Free trial information">
                        <span class="trial-banner__icon" aria-hidden="true">🎁</span>
                        <div>
                            <div class="trial-banner__title">
                                <?= $plan->trial_days ?>-day free trial
                            </div>
                            <div class="trial-banner__body">
                                Try <?= htmlspecialchars($plan->name) ?> risk-free.
                                No charge until
                                <strong><?= (new DateTimeImmutable())->modify("+{$plan->trial_days} days")->format('F j, Y') ?></strong>.
                                Cancel any time during the trial at no cost.
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

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

                        $actualPrice = $pricing->getEffectivePrintPrice();

                        $originalPrice = (float)($pricing->price ?? 0);

                        $baseDigitalPrice = is_numeric($pricing->digital_price)
                                ? (float)$pricing->digital_price
                                : (float)($pricing->price ?? 0);

                        ?>
                        <div class="duration-option<?= $index === 0 ? ' selected' : '' ?>"
                             data-plan="<?= $plan->id ?>">

                            <input type="radio"
                                   name="duration_<?= $plan->id ?>"
                                   value="<?= $pricing->duration_months ?>"
                                   data-pricing-id="<?= $pricing->id ?>"

                                   data-base-print="<?= (float)($pricing->price ?? 0) ?>"
                                   data-base-digital="<?= $baseDigitalPrice ?>"

                                   data-eff-print="<?= $pricing->getEffectivePrintPrice() ?>"
                                   data-eff-digital="<?= $pricing->getEffectiveDigitalPrice() ?>"

                                   data-issues="<?= $pricing->issue_count ?>"
                                    <?= $index === 0 ? 'checked' : '' ?>>

                            <div class="duration-option__left">
                                <div class="duration-option__radio"></div>

                                <div class="duration-option__info">
                                    <div class="duration-option__label">
                                        <?= htmlspecialchars($pricing->label) ?>
                                    </div>

                                    <div class="duration-option__period">
                                        <?= htmlspecialchars($pricing->period_description) ?>
                                    </div>
                                </div>
                            </div>

                            <div class="duration-option__right">

                                <?php if ($pricing->hasDiscount()): ?>
                                    <div class="duration-option__was">
                                        <?= $currencySymbol ?><?= number_format($originalPrice, 2) ?>
                                    </div>
                                <?php endif; ?>

                                <div class="duration-option__price">
                                    <?= $currencySymbol ?><?= number_format($actualPrice, 2) ?>
                                </div>

                                <?php if ($pricing->issue_count > 0): ?>
                                    <div class="duration-option__per-issue">
                                        <?= $currencySymbol ?><?= number_format($pricing->getPricePerIssue(), 2) ?>
                                        /issue
                                    </div>
                                <?php endif; ?>

                                <?php if ($pricing->getSavingsText()): ?>
                                    <div class="save-badge">
                                        <?= $pricing->getSavingsText() ?>
                                    </div>
                                <?php endif; ?>

                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button class="btn-add-cart" id="btn-add-cart" onclick="window.showPage.addToCart()">
                    <?php if ($plan->hasTrial()): ?>
                        Start <?= $plan->trial_days ?>-Day Free Trial
                    <?php else: ?>
                        Add to Cart
                    <?php endif; ?>
                </button>
            </div>

        </div><!-- /.plan-right -->
    </div><!-- /.plan-layout -->

    <!-- ── What's Included ──────────────────────────────────────────── -->
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

    <!-- ══════════════════════════════════════════════════════════════════
         REVIEWS — DMCC Act 2024 compliant
         ══════════════════════════════════════════════════════════════════ -->
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

        <div id="reviews-dynamic-container">
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
                        <div class="reviews-score__count">
                            <?= number_format($totalReviews) ?> review<?= $totalReviews !== 1 ? 's' : '' ?>
                        </div>
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
                                <button class="helpful-btn"
                                        onclick="window.showPage.voteHelpful(<?= (int)$review['id'] ?>, true, this)"
                                        aria-label="Mark as helpful">
                                    👍 <?= (int)($review['helpful_count'] ?? 0) ?>
                                </button>
                                <button class="helpful-btn"
                                        onclick="window.showPage.voteHelpful(<?= (int)$review['id'] ?>, false, this)"
                                        aria-label="Mark as not helpful">
                                    👎 <?= (int)($review['unhelpful_count'] ?? 0) ?>
                                </button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <nav class="review-pagination" id="review-pagination">
                    <?php if (!empty($pagination) && ($pagination['total_pages'] ?? 1) > 1): ?>
                        <?php $cur = $pagination['current_page'];
                        $tot = $pagination['total_pages']; ?>
                        <button class="review-pagination__btn <?= $cur <= 1 ? 'disabled' : '' ?>"
                                onclick="window.showPage.loadReviews(<?= $cur - 1 ?>)">←
                        </button>
                        <?php for ($p = 1; $p <= $tot; $p++): ?>
                            <button class="review-pagination__btn <?= $p === $cur ? 'active' : '' ?>"
                                    onclick="window.showPage.loadReviews(<?= $p ?>)"><?= $p ?></button>
                        <?php endfor; ?>
                        <button class="review-pagination__btn <?= $cur >= $tot ? 'disabled' : '' ?>"
                                onclick="window.showPage.loadReviews(<?= $cur + 1 ?>)">→
                        </button>
                    <?php endif; ?>
                </nav>

            <?php else: ?>
                <div class="review-list" id="review-list"></div>
                <div class="reviews-empty" id="reviews-empty-placeholder">
                    <div class="reviews-empty__icon">⭐</div>
                    <p>No reviews yet. Be the first to share your experience.</p>
                </div>
                <nav class="review-pagination" id="review-pagination"></nav>
            <?php endif; ?>
        </div>

        <div class="write-review-section">
            <h3>Write a Review</h3>

            <?php if ($canReview['can_review'] ?? false): ?>
                <div class="form-submission-notice" role="note">
                    By submitting a review, you confirm it reflects your genuine, personal experience
                    with this subscription. We do not offer incentives for reviews. Fabricated or
                    misleading reviews may be removed in accordance with the Digital Markets,
                    Competition and Consumers Act 2024.
                </div>

                <form class="review-form" id="review-form" onsubmit="window.showPage.submitReview(event)">
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
    <!-- ── END REVIEWS ─────────────────────────────────────────────────── -->

</div><!-- /.page -->

<!-- ── Cart Badge ──────────────────────────────────────────────────── -->
<div class="cart-badge" onclick="window.showPage.cart.open()">
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

<!-- ── Mini Cart ────────────────────────────────────────────────────── -->
<div class="mini-cart" id="mini-cart">
    <div class="mini-cart-header">
        <h3>Your Cart (<span id="cart-count">0</span>)</h3>
        <button class="close-cart" onclick="window.showPage.cart.close()" aria-label="Close cart">×</button>
    </div>
    <div class="mini-cart-items" id="cart-items">
        <p style="text-align:center;color:var(--ink-muted);padding:2rem;">Your cart is empty</p>
    </div>
    <div class="mini-cart-footer">
        <div class="cart-total-row">
            <span>Total:</span>
            <span id="mini-cart-total">£0.00</span>
        </div>
        <button class="clear-cart-btn" id="clear-cart-btn" onclick="window.showPage.cart.clear()" style="display:none;">
            🗑 Clear cart
        </button>
        <button class="btn btn-primary" style="width:100%;" onclick="window.showPage.cart.checkout()">
            Proceed to Checkout
        </button>
    </div>
</div>

<div class="cart-overlay" id="cart-overlay" onclick="window.showPage.cart.close()"></div>
<div class="toast" id="toast" role="alert" aria-live="polite"></div>

<script>
    // ── Bootstrap constants ───────────────────────────────────────────────
    const API_BASE = '/api/<?= SiteContext::slug() ?? 'default' ?>';
    const SITE = '<?= SiteContext::slug() ?? 'default' ?>';
    const PLAN_ID = <?= (int)$plan->id ?>;
    const CURRENCY_SYMBOL = '<?= $currencySymbol ?>';

    // ── Utilities ─────────────────────────────────────────────────────────
    function escHtml(s) {
        return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.className = `toast ${type} show`;
        setTimeout(() => toast.classList.remove('show'), 3500);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CartService
    // Identical contract to index.php: owns state + API, notifies listeners.
    // ═══════════════════════════════════════════════════════════════════════
    class CartService {
        constructor(apiBase) {
            this.apiBase = apiBase;
            this._data = {items: [], total: 0, count: 0};
            this._listeners = [];
        }

        get items() {
            return this._data.items || [];
        }

        get total() {
            return this._data.total || 0;
        }

        get count() {
            return this._data.count || 0;
        }

        /** Set of plan IDs in the cart — used by MiniCartUI to tint the add button */
        get planIds() {
            return new Set(
                this.items
                    .filter(i => i.subscription_plan_id)
                    .map(i => String(i.subscription_plan_id))
            );
        }

        subscribe(fn) {
            this._listeners.push(fn);
        }

        _notify() {
            this._listeners.forEach(fn => fn(this));
        }

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
         * Generic add — used by the show-page addToCart with full payload.
         * The caller builds the body; this method just POSTs and reloads.
         */
        async addSubscription(payload) {
            try {
                const res = await fetch(`${this.apiBase}/cart/subscription`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                if (data.success) {
                    await this.load();
                    return {success: true};
                }
                return {success: false, message: data.message};
            } catch (e) {
                console.error('Add to cart error:', e);
                return {success: false, message: 'An error occurred'};
            }
        }

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
    // Mirrors index.php: single _render() triggered by CartService subscription,
    // full qty controls, per-item remove, clear cart button.
    // ═══════════════════════════════════════════════════════════════════════
    class MiniCartUI {
        constructor(cartService) {
            this.cartService = cartService;
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

        async removeItem(itemId) {
            await this.cartService.removeItem(itemId);
        }

        async updateQuantity(itemId, quantity) {
            if (quantity < 1) {
                await this.cartService.removeItem(itemId);
            } else {
                await this.cartService.updateQuantity(itemId, quantity);
            }
        }

        async clear() {
            await this.cartService.clear();
        }

        // ── Private: single render path ───────────────────────────────────
        _render() {
            this._renderHeader();
            this._renderItems();
            this._renderFooter();
            this._syncAddButton();
        }

        _renderHeader() {
            const count = this.cartService.count;
            document.getElementById('cart-count').textContent = count;
            document.getElementById('cart-total').textContent = CURRENCY_SYMBOL + this.cartService.total.toFixed(2);

            const badge = document.getElementById('header-cart-count');
            badge.textContent = count;
            badge.style.display = count > 0 ? 'flex' : 'none';
        }

        _renderItems() {
            const container = document.getElementById('cart-items');
            const items = this.cartService.items;

            if (!items.length) {
                container.innerHTML = '<p style="text-align:center;color:var(--ink-muted);padding:2rem;">Your cart is empty</p>';
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
                                onclick="window.showPage.cart.removeItem(${itemId})"
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
                                    onclick="window.showPage.cart.updateQuantity(${itemId}, ${qty - 1})"
                                    ${qty <= 1 ? 'disabled' : ''}
                                    aria-label="Decrease quantity">−</button>
                            <span class="qty-value">${qty}</span>
                            <button class="qty-btn"
                                    onclick="window.showPage.cart.updateQuantity(${itemId}, ${qty + 1})"
                                    aria-label="Increase quantity">+</button>
                        </div>
                        <div class="cart-item-price">${price}</div>
                    </div>
                </div>`;
            }).join('');
        }

        _renderFooter() {
            document.getElementById('mini-cart-total').textContent = CURRENCY_SYMBOL + this.cartService.total.toFixed(2);

            const clearBtn = document.getElementById('clear-cart-btn');
            if (clearBtn) clearBtn.style.display = this.cartService.count > 0 ? 'block' : 'none';
        }

        /**
         * Tint the "Add to Cart" / "Start Trial" button green when this plan
         * is already in the cart — consistent with the index.php card button
         * behaviour, adapted to a single full-width button.
         */
        _syncAddButton() {
            const btn = document.getElementById('btn-add-cart');
            if (!btn) return;
            const inCart = this.cartService.planIds.has(String(PLAN_ID));
            btn.style.background = inCart ? '#059669' : '';
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // DeliveryUI
    // Self-contained: owns delivery-tab and duration-option interactions
    // and the pricing display update logic.
    // ═══════════════════════════════════════════════════════════════════════
    class DeliveryUI {
        constructor(planId) {
            this.planId = planId;
            this._bindEvents();
            this._syncSelectedState();
            this._updatePricingDisplay();
        }

        getSelections() {
            const durationRadio = document.querySelector(`input[name="duration_${this.planId}"]:checked`);
            const deliveryRadio = document.querySelector(`input[name="delivery_${this.planId}"]:checked`);
            return {durationRadio, deliveryRadio};
        }

        _bindEvents() {
            document.querySelectorAll(`.duration-option[data-plan="${this.planId}"]`).forEach(opt => {
                opt.addEventListener('click', () => {
                    document.querySelectorAll(`.duration-option[data-plan="${this.planId}"]`)
                        .forEach(o => o.classList.remove('selected'));
                    opt.classList.add('selected');
                    opt.querySelector('input[type="radio"]').checked = true;
                    this._updatePricingDisplay();
                });
            });

            document.querySelectorAll(`.delivery-tab[data-plan="${this.planId}"]`).forEach(tab => {
                tab.addEventListener('click', () => {
                    document.querySelectorAll(`.delivery-tab[data-plan="${this.planId}"]`)
                        .forEach(o => o.classList.remove('selected'));
                    tab.classList.add('selected');
                    tab.querySelector('input[type="radio"]').checked = true;
                    this._updatePricingDisplay();
                });
            });
        }

        _syncSelectedState() {
            document.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
                const parent = radio.closest('.duration-option') || radio.closest('.delivery-tab');
                if (parent) parent.classList.add('selected');
            });
        }

        _updatePricingDisplay() {
            const {deliveryRadio} = this.getSelections();
            const isDigital = deliveryRadio?.value === 'digital';

            document.querySelectorAll(`.duration-option[data-plan="${this.planId}"]`).forEach(opt => {
                const radio = opt.querySelector('input[type="radio"]');
                const priceEl = opt.querySelector('.duration-option__price');
                const wasEl = opt.querySelector('.duration-option__was');

                // 1. Determine which values to use based on delivery type
                const basePrice = isDigital ? parseFloat(radio.dataset.baseDigital) : parseFloat(radio.dataset.basePrint);
                const effPrice = isDigital ? parseFloat(radio.dataset.effDigital) : parseFloat(radio.dataset.effPrint);

                // 2. Update the main display price (always the effective price)
                if (priceEl) {
                    priceEl.textContent = CURRENCY_SYMBOL + effPrice.toFixed(2);
                }

                // 3. Handle the "Was" price (the strikethrough)
                if (wasEl) {
                    // Only show if the effective price is actually lower than the base price
                    if (effPrice < basePrice && basePrice > 0) {
                        wasEl.textContent = CURRENCY_SYMBOL + basePrice.toFixed(2);
                        wasEl.style.display = 'block';
                    } else {
                        wasEl.textContent = '';
                        wasEl.style.display = 'none';
                    }
                }

                // 4. Optional: Update "Save Badge" visibility if it exists
                const saveBadge = opt.querySelector('.save-badge');
                if (saveBadge) {
                    saveBadge.style.display = (effPrice < basePrice) ? 'inline-block' : 'none';
                }
            });
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ReviewManager
    // Self-contained: owns review loading, submission, helpful votes,
    // and star rating input UX.
    // ═══════════════════════════════════════════════════════════════════════
    class ReviewManager {
        constructor(planId, apiBase) {
            this.planId = planId;
            this.apiBase = apiBase;
            this._initStarRatingInput();
        }

        async loadReviews(page) {
            try {
                const res = await fetch(`${this.apiBase}/plans/${this.planId}/reviews?page=${page}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                });

                const data = await res.json();
                if (!data.success) return;

                const r = data.data;
                const container = document.getElementById('reviews-dynamic-container');
                const emptyPlaceholder = document.getElementById('reviews-empty-placeholder');
                const paginationContainer = document.getElementById('review-pagination');

                // --- 1. Update Stats & List ---
                if (r.reviews.length > 0) {
                    let summary = document.querySelector('.reviews-summary');
                    if (!summary) {
                        container.insertAdjacentHTML('afterbegin', this._renderSummary(r.stats));
                    } else {
                        summary.outerHTML = this._renderSummary(r.stats);
                    }

                    const list = document.getElementById('review-list');
                    if (list) {
                        list.innerHTML = r.reviews.map(rev => this._renderReviewCard(rev)).join('');
                        if (emptyPlaceholder) emptyPlaceholder.style.display = 'none';
                    }
                }

                // --- 2. Update Pagination ---
                if (paginationContainer) {
                    paginationContainer.innerHTML = this._renderPagination(r.pagination, page);
                }

                // --- 3. FIX: Handle the Form Visibility ---
                // We look for the write-review-section to see if we should swap the form for a message
                const writeSection = document.querySelector('.write-review-section');
                if (writeSection && r.can_review_data) {
                    const status = r.can_review_data; // This needs to be returned by your Controller

                    if (!status.can_review) {
                        // User just reviewed, so replace the form with the "already reviewed" notice
                        writeSection.innerHTML = `
                    <h3>Write a Review</h3>
                    <div class="review-login-prompt">
                        <p>${status.reason || 'You have already reviewed this plan.'}</p>
                    </div>
                `;
                    }
                }

                // --- 4. UI Clean up ---
                const submitBtn = document.getElementById('review-submit-btn');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Submit Review';
                }

            } catch (err) {
                console.error('Review load error:', err);
            }
        }

        _renderSummary(stats) {
            const avg = parseFloat(stats.average_rating || 0).toFixed(1);
            const total = stats.total_reviews || 0;
            const percentages = stats.rating_percentages || {5: 0, 4: 0, 3: 0, 2: 0, 1: 0};

            let barsHtml = '';
            [5, 4, 3, 2, 1].forEach(num => {
                const pct = percentages[num] || 0;
                barsHtml += `
            <div class="rating-bar-row">
                <span class="rating-bar-label">${num}★</span>
                <div class="rating-bar-track">
                    <div class="rating-bar-fill" style="width:${pct}%"></div>
                </div>
                <span class="rating-bar-pct">${pct}%</span>
            </div>`;
            });

            return `
        <div class="reviews-summary">
            <div class="reviews-score">
                <div class="reviews-score__avg">${avg}</div>
                <div class="reviews-score__stars">
                    ${[1, 2, 3, 4, 5].map(s => `<span>${s <= Math.round(avg) ? '★' : '☆'}</span>`).join('')}
                </div>
                <div class="reviews-score__count">${total} review${total !== 1 ? 's' : ''}</div>
            </div>
            <div class="rating-bars">${barsHtml}</div>
        </div>`;
        }

        async submitReview(e) {
            e.preventDefault();
            const form = document.getElementById('review-form');
            const btn = document.getElementById('review-submit-btn');
            const rating = form.querySelector('input[name="rating"]:checked')?.value;

            if (!rating) {
                showToast('Please select a star rating', 'error');
                return;
            }

            const payload = {
                plan_id: this.planId,
                rating: parseInt(rating),
                title: form.querySelector('[name="title"]').value.trim(),
                comment: form.querySelector('[name="comment"]').value.trim(),
            };

            btn.disabled = true;
            btn.textContent = 'Submitting…';

            try {
                const res = await fetch(`${this.apiBase}/plans/${this.planId}/reviews`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                if (data.success) {
                    showToast('Review submitted — thank you!', 'success');
                    form.reset();
                    document.querySelectorAll('.star-rating-input label').forEach(l => l.classList.remove('selected'));
                    setTimeout(() => this.loadReviews(1), 1200);
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

        async voteHelpful(reviewId, isHelpful, btn) {
            try {
                const res = await fetch(`${this.apiBase}/reviews/${reviewId}/helpful`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                    body: JSON.stringify({is_helpful: isHelpful}),
                });
                const data = await res.json();
                if (data.success) {
                    btn.classList.add('voted');
                    const btns = btn.closest('.review-card').querySelectorAll('.helpful-btn');
                    if (data.helpful_count != null) btns[0].innerHTML = `👍 ${data.helpful_count}`;
                    if (data.unhelpful_count != null) btns[1].innerHTML = `👎 ${data.unhelpful_count}`;
                }
            } catch (e) {
                console.error('Vote error:', e);
            }
        }

        // ── Private ───────────────────────────────────────────────────────
        _initStarRatingInput() {
            const labels = document.querySelectorAll('.star-rating-input label');
            labels.forEach((label, i) => {
                label.addEventListener('mouseover', () => labels.forEach((l, j) => l.classList.toggle('selected', j <= i)));
                label.addEventListener('mouseout', () => {
                    const checked = document.querySelector('.star-rating-input input:checked');
                    const checkedIdx = checked ? parseInt(checked.value) - 1 : -1;
                    labels.forEach((l, j) => l.classList.toggle('selected', j <= checkedIdx));
                });
                label.addEventListener('click', () => labels.forEach((l, j) => l.classList.toggle('selected', j <= i)));
            });
        }

        _renderReviewCard(review) {
            const stars = [1, 2, 3, 4, 5].map(s => `<span class="star ${s <= review.rating ? 'filled' : ''}" aria-hidden="true">★</span>`).join('');
            const verified = review.is_verified_purchase ? '<span class="review-card__verified">✓ Verified</span>' : '';
            const title = review.title ? `<div class="review-card__title">${escHtml(review.title)}</div>` : '';
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
                    <button class="helpful-btn" onclick="window.showPage.voteHelpful(${review.id}, true, this)">👍 ${review.helpful_count || 0}</button>
                    <button class="helpful-btn" onclick="window.showPage.voteHelpful(${review.id}, false, this)">👎 ${review.unhelpful_count || 0}</button>
                </div>
            </article>`;
        }

        _renderPagination(pagination, currentPage) {
            if (!pagination || pagination.total_pages <= 1) return '';
            const tot = pagination.total_pages;
            let html = `<button class="review-pagination__btn ${currentPage <= 1 ? 'disabled' : ''}" onclick="window.showPage.loadReviews(${currentPage - 1})">←</button>`;
            for (let p = 1; p <= tot; p++) {
                html += `<button class="review-pagination__btn ${p === currentPage ? 'active' : ''}" onclick="window.showPage.loadReviews(${p})">${p}</button>`;
            }
            html += `<button class="review-pagination__btn ${currentPage >= tot ? 'disabled' : ''}" onclick="window.showPage.loadReviews(${currentPage + 1})">→</button>`;
            return html;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ShowPageApp  —  composition root
    // Wires CartService + MiniCartUI + DeliveryUI + ReviewManager.
    // Exposes a minimal public API for inline onclick handlers in the template.
    // ═══════════════════════════════════════════════════════════════════════
    class ShowPageApp {
        constructor() {
            const cartService = new CartService(API_BASE);
            this.cart = new MiniCartUI(cartService);
            this._cartService = cartService;
            this._delivery = new DeliveryUI(PLAN_ID);
            this._reviews = new ReviewManager(PLAN_ID, API_BASE);

            // Boot
            cartService.load();
        }

        /**
         * Builds the show-page-specific payload (pricing_id, issues, etc.)
         * and delegates the actual POST to CartService.
         */
        async addToCart() {
            const {durationRadio, deliveryRadio} = this._delivery.getSelections();

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

            const btn = document.getElementById('btn-add-cart');
            const originalLabel = btn.textContent;
            btn.disabled = true;
            btn.textContent = '⏳ Adding…';

            const result = await this._cartService.addSubscription({
                plan_id: PLAN_ID,
                pricing_id: parseInt(durationRadio.dataset.pricingId),
                delivery_type: deliveryType,
                duration_months: parseInt(durationRadio.value),
                price: price,
                issues: parseInt(durationRadio.dataset.issues),
            });

            btn.disabled = false;
            btn.textContent = originalLabel;

            if (result.success) {
                this.cart.open();
                showToast('Added to cart!', 'success');
            } else {
                showToast(result.message || 'Failed to add to cart', 'error');
            }
        }

        // ── Review proxy methods (for inline onclick handlers) ────────────
        loadReviews(page) {
            this._reviews.loadReviews(page);
        }

        submitReview(e) {
            this._reviews.submitReview(e);
        }

        voteHelpful(reviewId, isHelpful, btn) {
            this._reviews.voteHelpful(reviewId, isHelpful, btn);
        }
    }

    // ── Bootstrap ─────────────────────────────────────────────────────────
    window.showPage = new ShowPageApp();
</script>
</body>
</html>