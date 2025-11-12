<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product->name) ?> - Product Detail</title>
    <meta name="description" content="<?= htmlspecialchars($product->meta_description ?? $product->description) ?>">
    @css('product-detail.css')
    <?php if (isset($structuredData)): ?>
        <script type="application/ld+json">
            <?= json_encode($structuredData) ?>

        </script>
    <?php endif; ?>
    <style>
        .product-images-gallery {
            display: flex;
            gap: 1rem;
            flex-direction: column;
        }

        .variant-price-info {
            margin-top: 0.75rem;
            display: flex;
            align-items: baseline;
            gap: 0.75rem;
        }

        .variant-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: #000;
        }

        .variant-sale-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: #ef4444;
        }

        .variant-original-price {
            font-size: 1rem;
            color: #999;
            text-decoration: line-through;
        }

        .merchant-comparison {
            margin: 3rem 0;
            padding: 2rem;
            background: #f9f9f9;
            border-radius: 12px;
        }

        .merchant-comparison-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 2rem;
            text-align: center;
            color: #000;
        }

        .merchants-list {
            display: grid;
            gap: 1.5rem;
            max-width: 900px;
            margin: 0 auto;
        }

        .merchant-card {
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
            gap: 2rem;
            padding: 1.5rem 2rem;
            background: white;
            border: 2px solid #e5e5e5;
            border-radius: 12px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .merchant-card:hover {
            border-color: #000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .merchant-card.merchant-unavailable {
            opacity: 0.6;
            filter: grayscale(50%);
        }

        .merchant-card.merchant-unavailable:hover {
            transform: none;
        }

        .merchant-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .merchant-logo {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f5f5f5;
            border-radius: 8px;
            font-weight: 700;
            color: #666;
            font-size: 1.25rem;
        }

        .merchant-details {
            flex: 1;
        }

        .merchant-name {
            font-weight: 700;
            font-size: 1.125rem;
            color: #000;
            margin-bottom: 0.25rem;
        }

        .merchant-shipping {
            font-size: 0.875rem;
            color: #10b981;
            font-weight: 500;
        }

        .merchant-pricing {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.5rem;
        }

        .merchant-price-container {
            display: flex;
            align-items: baseline;
            gap: 0.75rem;
        }

        .merchant-price {
            font-size: 2rem;
            font-weight: 700;
            color: #000;
        }

        .merchant-price.sale-price {
            color: #ef4444;
        }

        .merchant-original-price {
            font-size: 1.125rem;
            color: #999;
            text-decoration: line-through;
        }

        .merchant-savings {
            font-size: 0.875rem;
            color: #10b981;
            font-weight: 600;
            background: #d1fae5;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
        }

        .merchant-action {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .merchant-link {
            padding: 0.875rem 2rem;
            background: #000;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
        }

        .merchant-link:hover {
            background: #333;
            transform: translateX(4px);
        }

        .merchant-link svg {
            width: 16px;
            height: 16px;
        }

        .merchant-unavailable-badge {
            padding: 0.5rem 1rem;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .best-price-badge {
            position: absolute;
            top: -12px;
            left: 1.5rem;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 0.375rem 1rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }

        .merchant-card-wrapper {
            position: relative;
        }

        @media (max-width: 768px) {
            .merchant-card {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .merchant-info {
                flex-direction: column;
                align-items: flex-start;
            }

            .merchant-pricing {
                align-items: flex-start;
            }

            .merchant-action {
                width: 100%;
            }

            .merchant-link {
                width: 100%;
                justify-content: center;
            }
        }

        .thumbnail-images {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .thumbnail {
            cursor: pointer;
            border: 2px solid transparent;
            transition: border-color 0.3s;
            aspect-ratio: 1;
            overflow: hidden;
        }

        .thumbnail:hover {
            border-color: #ddd;
        }

        .thumbnail.active {
            border-color: #000;
        }

        .thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-variants {
            margin: 2rem 0;
            padding: 1.5rem;
            background: #f9f9f9;
            border-radius: 8px;
        }

        .variant-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #000;
        }

        .variants-list {
            display: grid;
            gap: 1rem;
        }

        .variant-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: white;
            border: 2px solid #e5e5e5;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .variant-item:hover {
            border-color: #000;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .variant-item.selected {
            border-color: #000;
            background: #f0f0f0;
        }

        .variant-info {
            flex: 1;
        }

        .variant-sku {
            font-size: 0.875rem;
            color: #666;
            margin-bottom: 0.5rem;
            font-family: 'Courier New', monospace;
        }

        .variant-attributes {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .variant-attribute-badge {
            padding: 0.25rem 0.75rem;
            background: #e5e5e5;
            border-radius: 4px;
            font-size: 0.875rem;
        }

        .variant-attribute-badge strong {
            color: #000;
        }

        .variant-price-modifier {
            font-size: 1rem;
            font-weight: 700;
            color: #10b981;
        }

        .variant-price-modifier:has(.-) {
            color: #ef4444;
        }

        .variant-select-btn {
            padding: 0.75rem 2rem;
            background: #000;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .variant-select-btn:hover {
            background: #333;
            transform: translateY(-2px);
        }

        .variant-item.selected .variant-select-btn {
            background: #10b981;
        }

        .product-specifications {
            margin: 2rem 0;
            padding: 0;
        }

        .spec-category {
            margin-bottom: 2.5rem;
        }

        .spec-category-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 3px solid #000;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .spec-list {
            display: grid;
            gap: 0;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            overflow: hidden;
        }

        .spec-item {
            display: grid;
            grid-template-columns: 200px 1fr;
            padding: 0;
            background: white;
            border-bottom: 1px solid #e5e5e5;
            transition: background-color 0.2s;
        }

        .spec-item:last-child {
            border-bottom: none;
        }

        .spec-item:hover {
            background: #f8f8f8;
        }

        .spec-key {
            font-weight: 600;
            padding: 1rem 1.5rem;
            background: #f9f9f9;
            border-right: 1px solid #e5e5e5;
            color: #333;
        }

        .spec-value {
            padding: 1rem 1.5rem;
            color: #666;
        }

        @media (max-width: 768px) {
            .spec-item {
                grid-template-columns: 1fr;
            }

            .spec-key {
                border-right: none;
                border-bottom: 1px solid #e5e5e5;
                padding: 0.75rem 1rem;
            }

            .spec-value {
                padding: 0.75rem 1rem;
            }
        }

        .merchant-comparison {
            margin: 2rem 0;
            border-top: 1px solid #ddd;
            padding-top: 2rem;
        }

        .merchant-comparison-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        .merchants-list {
            display: grid;
            gap: 1rem;
        }

        .merchant-card {
            display: grid;
            grid-template-columns: 1fr auto auto;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border: 1px solid #ddd;
            background: white;
        }

        .merchant-name {
            font-weight: 600;
        }

        .merchant-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: #000;
        }

        .merchant-link {
            padding: 0.5rem 1.5rem;
            background: #000;
            color: white;
            text-decoration: none;
            transition: background 0.3s;
        }

        .merchant-link:hover {
            background: #333;
        }

        .merchant-unavailable {
            opacity: 0.5;
        }

        .price-history {
            margin: 0;
            padding: 2rem 0;
        }

        .price-history-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 2rem;
            text-align: center;
        }

        .price-indicator {
            margin: 2rem 0;
            padding: 1rem;
            background: #f9f9f9;
            border-radius: 8px;
        }

        .price-indicator-bar {
            position: relative;
            height: 12px;
            background: linear-gradient(to right, #10b981, #fbbf24, #ef4444);
            border-radius: 6px;
            margin-bottom: 0.5rem;
        }

        .price-marker {
            position: absolute;
            top: -8px;
            transform: translateX(-50%);
            width: 4px;
            height: 28px;
            background: #000;
            border-radius: 2px;
        }

        .price-marker-label {
            position: absolute;
            top: -24px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.75rem;
            font-weight: 700;
            white-space: nowrap;
            background: #000;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
        }

        .price-indicator-labels {
            display: flex;
            justify-content: space-between;
            font-size: 0.875rem;
            color: #666;
            font-weight: 600;
        }

        .price-rating {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.5rem;
            margin: 1.5rem 0;
            border-radius: 8px;
            font-weight: 600;
        }

        .price-rating svg {
            flex-shrink: 0;
        }

        .price-rating-excellent {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .price-rating-good {
            background: #dbeafe;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }

        .price-rating-high {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .price-rating-average {
            background: #f3f4f6;
            color: #374151;
            border-left: 4px solid #6b7280;
        }

        .price-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .price-stat-card {
            padding: 1.5rem;
            background: white;
            border: 2px solid #e5e5e5;
            border-radius: 12px;
            text-align: center;
            transition: all 0.3s;
        }

        .price-stat-card:hover {
            border-color: #000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-4px);
        }

        .price-stat-icon {
            margin-bottom: 0.75rem;
        }

        .price-stat-icon svg {
            width: 32px;
            height: 32px;
        }

        .price-stat-label {
            font-size: 0.875rem;
            color: #666;
            margin-bottom: 0.5rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .price-stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #000;
            margin-bottom: 0.25rem;
        }

        .price-stat-change {
            font-size: 0.8125rem;
            color: #666;
            font-weight: 500;
        }

        .price-history-timeline {
            margin-top: 3rem;
            padding: 2rem;
            background: #f9f9f9;
            border-radius: 12px;
        }

        .timeline-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 2rem;
            text-align: center;
        }

        .timeline {
            position: relative;
            padding-left: 2rem;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 7px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e5e5e5;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 2rem;
            display: flex;
            gap: 1rem;
        }

        .timeline-item:last-child {
            padding-bottom: 0;
        }

        .timeline-marker {
            position: absolute;
            left: -2rem;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: white;
            border: 3px solid #e5e5e5;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.75rem;
            z-index: 1;
        }

        .timeline-marker.increase {
            border-color: #ef4444;
            color: #ef4444;
        }

        .timeline-marker.decrease {
            border-color: #10b981;
            color: #10b981;
        }

        .timeline-marker.neutral {
            border-color: #6b7280;
            color: #6b7280;
        }

        .timeline-content {
            flex: 1;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            border: 1px solid #e5e5e5;
        }

        .timeline-date {
            font-size: 0.875rem;
            color: #666;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .timeline-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: #000;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .price-change {
            font-size: 0.875rem;
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
        }

        .price-change.increase {
            background: #fee2e2;
            color: #991b1b;
        }

        .price-change.decrease {
            background: #d1fae5;
            color: #065f46;
        }

        .timeline-merchant {
            font-size: 0.8125rem;
            color: #666;
            margin-top: 0.25rem;
            font-style: italic;
        }

        @media (max-width: 768px) {
            .price-stats-grid {
                grid-template-columns: 1fr;
            }

            .variant-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .variant-select-btn {
                width: 100%;
            }
        }

        .tabs-container {
            margin: 2rem 0;
        }

        .tabs-nav {
            display: flex;
            border-bottom: 2px solid #ddd;
            gap: 2rem;
        }

        .tab-button {
            padding: 1rem 0;
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            margin-bottom: -2px;
        }

        .tab-button:hover {
            color: #666;
        }

        .tab-button.active {
            border-bottom-color: #000;
        }

        .tab-content {
            display: none;
            padding: 2rem 0;
        }

        .tab-content.active {
            display: block;
        }

        .voucher-section {
            margin: 1.5rem 0;
            padding: 1rem;
            background: #f0f9ff;
            border-left: 4px solid #0ea5e9;
        }

        .voucher-title {
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .vouchers-list {
            display: grid;
            gap: 0.5rem;
        }
    </style>
</head>
<body>
<div class="page-wrapper">
    <!-- Header -->
    <header class="site-header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <a href="/">YourStore</a>
                </div>
                <nav class="main-nav">
                    <a href="/">Home</a>
                    <a href="/products">Shop</a>
                    <a href="/about">About</a>
                    <a href="/contact">Contact</a>
                </nav>
                <div class="header-actions">
                    <button class="icon-btn" id="wishlist-btn">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                        </svg>
                        <span class="badge" id="wishlist-count">0</span>
                    </button>
                    <button class="icon-btn" id="cart-btn">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                        <span class="badge" id="cart-count">0</span>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <div class="container">
            <a href="/">Home</a>
            <span class="separator">/</span>
            <a href="/products">Products</a>
            <span class="separator">/</span>
            <span><?= htmlspecialchars($product->name) ?></span>
        </div>
    </div>

    <!-- Product Detail -->
    <main class="main-content">
        <div class="container">
            <div class="product-detail">
                <!-- Product Images -->
                <div class="product-images-gallery">
                    <div class="main-image">
                        <img src="<?= htmlspecialchars($product->main_image_url ?? $product->image_url ?? '/images/placeholder.jpg') ?>"
                             alt="<?= htmlspecialchars($product->name) ?>"
                             id="main-product-image">
                        <?php if ($product->discount_percentage > 0): ?>
                            <div class="badge-sale">-<?= $product->discount_percentage ?>%</div>
                        <?php endif; ?>
                    </div>

                    <?php if ($product->images && $product->images->count() > 1): ?>
                        <div class="thumbnail-images">
                            <?php foreach ($product->images as $index => $image): ?>
                                <div class="thumbnail <?= $index === 0 ? 'active' : '' ?>"
                                     data-image="<?= htmlspecialchars($image->url) ?>">
                                    <img src="<?= htmlspecialchars($image->url) ?>"
                                         alt="<?= htmlspecialchars($image->alt ?? $product->name) ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Product Info -->
                <div class="product-info">
                    <h1 class="product-title"><?= htmlspecialchars($product->name) ?></h1>

                    <div class="product-meta">
                        <?php if ($product->brand): ?>
                            <span class="brand">Brand: <strong><?= htmlspecialchars($product->brand->name) ?></strong></span>
                        <?php endif; ?>
                        <?php if ($product->category): ?>
                            <span class="category">Category: <strong><?= htmlspecialchars($product->category->name) ?></strong></span>
                        <?php endif; ?>
                    </div>

                    <div class="product-price">
                        <?php if ($product->sale_price && $product->sale_price < $product->price): ?>
                            <span class="price-sale">$<?= number_format($product->sale_price, 2) ?></span>
                            <span class="price-original">$<?= number_format($product->price, 2) ?></span>
                        <?php else: ?>
                            <span class="price-current">$<?= number_format($product->price, 2) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Vouchers Section -->
                    <?php if (!empty($product->activeVouchers) && count($product->activeVouchers) > 0): ?>
                        <div class="voucher-section">
                            <div class="voucher-title">🎁 Available Vouchers</div>
                            <div class="vouchers-list">
                                <?php foreach ($product->activeVouchers as $voucher): ?>
                                    <div class="deal-voucher">
                                        <span class="voucher-label"><?= htmlspecialchars($voucher->title) ?></span>
                                        <span class="voucher-code"><?= htmlspecialchars($voucher->code) ?></span>
                                        <button class="voucher-copy-btn"
                                                data-code="<?= htmlspecialchars($voucher->code) ?>">
                                            Copy
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Product Variants -->
                    <?php if ($product->activeVariants && $product->activeVariants->count() > 0): ?>
                        <div class="product-variants">
                            <h3 class="variant-title">Available Options</h3>
                            <div class="variants-list">
                                <?php foreach ($product->activeVariants as $variant): ?>
                                    <div class="variant-item" data-variant-id="<?= $variant->id ?>">
                                        <div class="variant-info">
                                            <div class="variant-sku">SKU: <?= htmlspecialchars($variant->sku) ?></div>
                                            <?php if (!empty($variant->attributes) && is_array($variant->attributes)): ?>
                                                <div class="variant-attributes">
                                                    <?php foreach ($variant->attributes as $key => $value): ?>
                                                        <span class="variant-attribute-badge">
                                        <strong><?= htmlspecialchars(ucfirst($key)) ?>:</strong>
                                        <?= htmlspecialchars($value) ?>
                                    </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="variant-price-info">
                                                <?php if (!empty($variant->sale_price) && $variant->sale_price < $variant->price): ?>
                                                    <span class="variant-sale-price">$<?= number_format($variant->sale_price, 2) ?></span>
                                                    <span class="variant-original-price">$<?= number_format($variant->price, 2) ?></span>
                                                <?php else: ?>
                                                    <span class="variant-price">$<?= number_format($variant->price, 2) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <button class="variant-select-btn"
                                                data-variant-id="<?= $variant->id ?>"
                                                data-price="<?= $variant->price ?>"
                                                data-sale-price="<?= $variant->sale_price ?? '' ?>">
                                            Select
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="product-description">
                        <p><?= nl2br(htmlspecialchars($product->description)) ?></p>
                    </div>

                    <div class="product-stock">
                        <?php if ($product->in_stock ?? true): ?>
                            <span class="stock-status in-stock">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                                In Stock
                            </span>
                        <?php else: ?>
                            <span class="stock-status out-of-stock">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="15" y1="9" x2="9" y2="15"></line>
                                    <line x1="9" y1="9" x2="15" y2="15"></line>
                                </svg>
                                Out of Stock
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="product-actions">
                        <div class="quantity-selector">
                            <button type="button" class="qty-btn" id="qty-decrease">-</button>
                            <input type="number" id="quantity" value="1" min="1" max="99" readonly>
                            <button type="button" class="qty-btn" id="qty-increase">+</button>
                        </div>

                        <button class="btn btn-primary btn-add-to-cart" id="add-to-cart-btn"
                                data-product-id="<?= $product->id ?>">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <circle cx="9" cy="21" r="1"></circle>
                                <circle cx="20" cy="21" r="1"></circle>
                                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                            </svg>
                            Add to Cart
                        </button>

                        <button class="btn btn-wishlist <?= $isInWishlist ? 'active' : '' ?>"
                                id="toggle-wishlist-btn"
                                data-product-id="<?= $product->id ?>"
                                data-in-wishlist="<?= $isInWishlist ? 'true' : 'false' ?>">
                            <svg width="20" height="20" viewBox="0 0 24 24"
                                 fill="<?= $isInWishlist ? 'currentColor' : 'none' ?>" stroke="currentColor">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                            </svg>
                            <?= $isInWishlist ? 'Remove from Wishlist' : 'Add to Wishlist' ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tabs Section -->
            <div class="tabs-container">
                <div class="tabs-nav">
                    <button class="tab-button active" data-tab="description">Description</button>
                    <?php if ($product->specifications && $product->specifications->count() > 0): ?>
                        <button class="tab-button" data-tab="specifications">Specifications</button>
                    <?php endif; ?>
                    <?php if ($product->availableMerchants && $product->availableMerchants->count() > 0): ?>
                        <button class="tab-button" data-tab="merchants">Where to Buy</button>
                    <?php endif; ?>
                    <?php if ($product->priceHistory && $product->priceHistory->count() > 0): ?>
                        <button class="tab-button" data-tab="price-history">Price History</button>
                    <?php endif; ?>
                    <button class="tab-button" data-tab="reviews">Reviews (<?= $reviewStats['total_reviews'] ?>)
                    </button>
                </div>

                <div class="tab-content active" id="description-tab">
                    <div class="product-description">
                        <?= nl2br(htmlspecialchars($product->description)) ?>
                    </div>
                </div>

                <?php if ($product->specifications && $product->specifications->count() > 0): ?>
                    <div class="tab-content" id="specifications-tab">
                        <div class="product-specifications">
                            <?php
                            $specsByCategory = [];
                            foreach ($product->specifications as $spec) {
                                $category = $spec->category ?? 'General';
                                if (!isset($specsByCategory[$category])) {
                                    $specsByCategory[$category] = [];
                                }
                                $specsByCategory[$category][] = $spec;
                            }
                            ?>

                            <?php foreach ($specsByCategory as $category => $specs): ?>
                                <div class="spec-category">
                                    <h3 class="spec-category-title"><?= htmlspecialchars($category) ?></h3>
                                    <div class="spec-list">
                                        <?php foreach ($specs as $spec): ?>
                                            <div class="spec-item">
                                                <span class="spec-key"><?= htmlspecialchars($spec->key) ?></span>
                                                <span class="spec-value"><?= htmlspecialchars($spec->value) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($product->availableMerchants && $product->availableMerchants->count() > 0): ?>
                    <div class="tab-content" id="merchants-tab">
                        <div class="merchant-comparison">
                            <h2 class="merchant-comparison-title">Compare Prices from Trusted Retailers</h2>
                            <div class="merchants-list">
                                <?php
                                $merchants = $product->availableMerchants->toArray();
                                $lowestPrice = min(array_column($merchants, 'price'));

                                foreach ($product->availableMerchants as $merchant):
                                    $isLowest = $merchant->price <= $lowestPrice * 1.01; // Within 1% of lowest
                                    $discount = $merchant->sale_price ? round((($merchant->price - $merchant->sale_price) / $merchant->price) * 100) : 0;
                                    ?>
                                    <div class="merchant-card-wrapper">
                                        <?php if ($isLowest && $merchant->is_available): ?>
                                            <div class="best-price-badge">🏆 Best Price</div>
                                        <?php endif; ?>

                                        <div class="merchant-card <?= !$merchant->is_available ? 'merchant-unavailable' : '' ?>">
                                            <div class="merchant-info">
                                                <div class="merchant-logo">
                                                    <?= strtoupper(substr($merchant->merchant->name, 0, 1)) ?>
                                                </div>
                                                <div class="merchant-details">
                                                    <div class="merchant-name"><?= htmlspecialchars($merchant->merchant->name) ?></div>
                                                    <?php if ($merchant->is_available): ?>
                                                        <div class="merchant-shipping">✓ In Stock</div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <div class="merchant-action">
                                                <div class="merchant-pricing">
                                                    <div class="merchant-price-container">
                                                        <?php if (!empty($merchant->sale_price) && $merchant->sale_price < $merchant->price): ?>
                                                            <span class="merchant-price sale-price">$<?= number_format($merchant->sale_price, 2) ?></span>
                                                            <span class="merchant-original-price">$<?= number_format($merchant->price, 2) ?></span>
                                                        <?php else: ?>
                                                            <span class="merchant-price">$<?= number_format($merchant->price, 2) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if ($discount > 0): ?>
                                                        <span class="merchant-savings">Save <?= $discount ?>%</span>
                                                    <?php endif; ?>
                                                </div>

                                                <?php if ($merchant->is_available): ?>
                                                    <a href="<?= htmlspecialchars($merchant->url) ?>"
                                                       class="merchant-link"
                                                       target="_blank"
                                                       rel="noopener noreferrer">
                                                        Buy Now
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="M5 12h14M12 5l7 7-7 7"/>
                                                        </svg>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="merchant-unavailable-badge">Out of Stock</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($product->priceHistory && $product->priceHistory->count() > 0): ?>
                    <div class="tab-content" id="price-history-tab">
                        <div class="price-history">
                            <h2 class="price-history-title">Price Tracking</h2>

                            <?php
                            $prices = array_map(fn($h) => $h['sale_price'] ?? $h['price'], $product->priceHistory->toArray());
                            $lowestPrice = min($prices);
                            $highestPrice = max($prices);
                            $currentPrice = $product->sale_price ?? $product->price;
                            $avgPrice = array_sum($prices) / count($prices);

                            // Calculate if current price is good
                            $priceRating = 'average';
                            if ($currentPrice <= $lowestPrice * 1.05) {
                                $priceRating = 'excellent';
                            } elseif ($currentPrice <= $avgPrice) {
                                $priceRating = 'good';
                            } elseif ($currentPrice >= $highestPrice * 0.95) {
                                $priceRating = 'high';
                            }
                            ?>

                            <div class="price-indicator">
                                <div class="price-indicator-bar">
                                    <div class="price-marker" style="left: <?= (($currentPrice - $lowestPrice) / ($highestPrice - $lowestPrice)) * 100 ?>%">
                                        <span class="price-marker-label">Current</span>
                                    </div>
                                </div>
                                <div class="price-indicator-labels">
                                    <span>Low</span>
                                    <span>High</span>
                                </div>
                            </div>

                            <div class="price-rating price-rating-<?= $priceRating ?>">
                                <?php if ($priceRating === 'excellent'): ?>
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                    </svg>
                                    Excellent Price! This is near the lowest price we've tracked.
                                <?php elseif ($priceRating === 'good'): ?>
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                    Good Deal! This price is below average.
                                <?php elseif ($priceRating === 'high'): ?>
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                                        <line x1="12" y1="9" x2="12" y2="13"></line>
                                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                                    </svg>
                                    High Price. Consider waiting for a better deal.
                                <?php else: ?>
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="12" y1="16" x2="12" y2="12"></line>
                                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                                    </svg>
                                    Average Price
                                <?php endif; ?>
                            </div>

                            <div class="price-stats-grid">
                                <div class="price-stat-card">
                                    <div class="price-stat-icon" style="color: #10b981;">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                                        </svg>
                                    </div>
                                    <div class="price-stat-label">Current Price</div>
                                    <div class="price-stat-value">$<?= number_format($currentPrice, 2) ?></div>
                                </div>
                                <div class="price-stat-card">
                                    <div class="price-stat-icon" style="color: #0ea5e9;">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M12 20V10"></path>
                                            <path d="M18 20V4"></path>
                                            <path d="M6 20v-4"></path>
                                        </svg>
                                    </div>
                                    <div class="price-stat-label">Lowest Price</div>
                                    <div class="price-stat-value" style="color: #10b981;">$<?= number_format($lowestPrice, 2) ?></div>
                                    <div class="price-stat-change">
                                        <?php if ($currentPrice > $lowestPrice): ?>
                                            +$<?= number_format($currentPrice - $lowestPrice, 2) ?> from lowest
                                        <?php else: ?>
                                            Lowest price now!
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="price-stat-card">
                                    <div class="price-stat-icon" style="color: #ef4444;">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M12 20V10"></path>
                                            <path d="M18 20V4"></path>
                                            <path d="M6 20v-4"></path>
                                        </svg>
                                    </div>
                                    <div class="price-stat-label">Highest Price</div>
                                    <div class="price-stat-value" style="color: #ef4444;">$<?= number_format($highestPrice, 2) ?></div>
                                    <div class="price-stat-change">
                                        <?php if ($currentPrice < $highestPrice): ?>
                                            -$<?= number_format($highestPrice - $currentPrice, 2) ?> from highest
                                        <?php else: ?>
                                            Highest price now
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="price-stat-card">
                                    <div class="price-stat-icon" style="color: #8b5cf6;">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="12" y1="1" x2="12" y2="23"></line>
                                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                        </svg>
                                    </div>
                                    <div class="price-stat-label">Average Price</div>
                                    <div class="price-stat-value">$<?= number_format($avgPrice, 2) ?></div>
                                    <div class="price-stat-change">
                                        <?php if ($currentPrice < $avgPrice): ?>
                                            $<?= number_format($avgPrice - $currentPrice, 2) ?> below average
                                        <?php else: ?>
                                            $<?= number_format($currentPrice - $avgPrice, 2) ?> above average
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="price-history-timeline">
                                <h3 class="timeline-title">Price Changes</h3>
                                <div class="timeline">
                                    <?php $priceHistory = $product->priceHistory->toArray(); ?>
                                    <?php foreach (array_slice($priceHistory, 0, 10) as $index => $history): ?>
                                        <?php
                                        $historyPrice = $history['sale_price'] ?? $history['price'];
                                        $isIncrease = $index > 0 && $historyPrice > ($priceHistory[$index - 1]['sale_price'] ?? $priceHistory[$index - 1]['price']);
                                        $isDecrease = $index > 0 && $historyPrice < ($priceHistory[$index - 1]['sale_price'] ?? $priceHistory[$index - 1]['price']);
                                        ?>

                                        <div class="timeline-item">
                                            <div class="timeline-marker <?= $isIncrease ? 'increase' : ($isDecrease ? 'decrease' : 'neutral') ?>">
                                                <?php if ($isIncrease): ?>
                                                    ↑
                                                <?php elseif ($isDecrease): ?>
                                                    ↓
                                                <?php else: ?>
                                                    •
                                                <?php endif; ?>
                                            </div>
                                            <div class="timeline-content">
                                                <div class="timeline-date">
                                                    <?= $history['recorded_at']->format('M d, Y') ?> ?>
                                                </div>
                                                <div class="timeline-price">
                                                    $<?= number_format($historyPrice, 2) ?>
                                                    <?php if ($index > 0): ?>
                                                        <?php
                                                        $prevPrice = $priceHistory[$index - 1]['sale_price'] ?? $priceHistory[$index - 1]['price'];
                                                        $change = $historyPrice - $prevPrice;
                                                        ?>
                                                        <?php if ($change != 0): ?>
                                                            <span class="price-change <?= $change > 0 ? 'increase' : 'decrease' ?>">
                                            <?= $change > 0 ? '+' : '' ?>$<?= number_format(abs($change), 2) ?>
                                        </span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (isset($history->merchant_id) && $history->merchant): ?>
                                                    <div class="timeline-merchant">
                                                        via <?= htmlspecialchars($history->merchant->name) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="tab-content" id="reviews-tab">
                    <!-- Reviews Section (existing code) -->
                    <section class="product-reviews">
                        <div class="reviews-summary">
                            <h2 class="section-title">Customer Reviews</h2>

                            <div class="rating-overview">
                                <div class="average-rating">
                                    <div class="rating-number"><?= number_format($reviewStats['average_rating'], 1) ?></div>
                                    <div class="stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <svg class="star <?= $i <= round($reviewStats['average_rating']) ? 'filled' : '' ?>"
                                                 width="24" height="24" viewBox="0 0 24 24">
                                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                            </svg>
                                        <?php endfor; ?>
                                    </div>
                                    <div class="rating-count"><?= $reviewStats['total_reviews'] ?> reviews</div>
                                </div>

                                <div class="rating-breakdown">
                                    <?php foreach ([5, 4, 3, 2, 1] as $rating): ?>
                                        <div class="rating-bar-row">
                                            <span class="rating-label"><?= $rating ?> stars</span>
                                            <div class="rating-bar">
                                                <div class="rating-bar-fill"
                                                     style="width: <?= $reviewStats['rating_percentages'][$rating] ?>%"></div>
                                            </div>
                                            <span class="rating-count"><?= $reviewStats['rating_breakdown'][$rating] ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <?php if ($canReview['can_review']): ?>
                                <button class="btn btn-primary" id="write-review-btn">Write a Review</button>
                            <?php else: ?>
                                <p class="review-notice"><?= htmlspecialchars($canReview['reason']) ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- Review Form Modal -->
                        <div id="review-modal" class="modal" style="display: none;">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h3>Write a Review</h3>
                                    <button class="modal-close" id="close-review-modal">&times;</button>
                                </div>
                                <form id="review-form">
                                    <div class="form-group">
                                        <label>Rating *</label>
                                        <div class="star-rating-input">
                                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                                <input type="radio" id="star<?= $i ?>" name="rating" value="<?= $i ?>"
                                                       required>
                                                <label for="star<?= $i ?>">
                                                    <svg width="32" height="32" viewBox="0 0 24 24">
                                                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                                    </svg>
                                                </label>
                                            <?php endfor; ?>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="review-title">Review Title</label>
                                        <input type="text" id="review-title" name="title"
                                               placeholder="Sum up your experience" maxlength="200">
                                    </div>

                                    <div class="form-group">
                                        <label for="review-comment">Your Review *</label>
                                        <textarea id="review-comment" name="comment" rows="6"
                                                  placeholder="Share your thoughts about this product"
                                                  required></textarea>
                                    </div>

                                    <div class="modal-actions">
                                        <button type="button" class="btn btn-secondary" id="cancel-review">Cancel
                                        </button>
                                        <button type="submit" class="btn btn-primary">Submit Review</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Reviews List -->
                        <div class="reviews-list" id="reviews-list">
                            <?php if (empty($reviewData['reviews'])): ?>
                                <p class="no-reviews">No reviews yet. Be the first to review this product!</p>
                            <?php else: ?>
                                <?php foreach ($reviewData['reviews'] as $review): ?>
                                    <div class="review-item" data-review-id="<?= $review['id'] ?>">
                                        <div class="review-header">
                                            <div class="review-author">
                                                <strong><?= htmlspecialchars($review['author_name']) ?></strong>
                                                <?php if ($review['is_verified_purchase']): ?>
                                                    <span class="verified-badge">
                                                        <svg width="16" height="16" viewBox="0 0 24 24"
                                                             fill="currentColor">
                                                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                                        </svg>
                                                        Verified Purchase
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="review-date"><?= htmlspecialchars($review['formatted_date']) ?></div>
                                        </div>

                                        <div class="review-rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <svg class="star <?= $i <= $review['rating'] ? 'filled' : '' ?>"
                                                     width="16" height="16" viewBox="0 0 24 24">
                                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                                </svg>
                                            <?php endfor; ?>
                                        </div>

                                        <?php if (!empty($review['title'])): ?>
                                            <h4 class="review-title"><?= htmlspecialchars($review['title']) ?></h4>
                                        <?php endif; ?>

                                        <p class="review-comment"><?= nl2br(htmlspecialchars($review['comment'])) ?></p>

                                        <div class="review-actions">
                                            <button class="btn-helpful" data-review-id="<?= $review['id'] ?>"
                                                    data-helpful="true">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                                     stroke="currentColor">
                                                    <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                                                </svg>
                                                Helpful (<span
                                                        class="helpful-count"><?= $review['helpful_count'] ?></span>)
                                            </button>
                                            <button class="btn-helpful" data-review-id="<?= $review['id'] ?>"
                                                    data-helpful="false">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                                     stroke="currentColor">
                                                    <path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"></path>
                                                </svg>
                                                Not Helpful (<span
                                                        class="unhelpful-count"><?= $review['unhelpful_count'] ?></span>)
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <!-- Pagination -->
                                <?php if ($reviewData['pagination']['last_page'] > 1): ?>
                                    <div class="reviews-pagination" id="reviews-pagination">
                                        <?php if ($reviewData['pagination']['current_page'] > 1): ?>
                                            <button class="btn btn-secondary"
                                                    data-page="<?= $reviewData['pagination']['current_page'] - 1 ?>">
                                                Previous
                                            </button>
                                        <?php endif; ?>

                                        <?php for ($i = 1; $i <= $reviewData['pagination']['last_page']; $i++): ?>
                                            <button class="btn <?= $i === $reviewData['pagination']['current_page'] ? 'btn-primary' : 'btn-secondary' ?>"
                                                    data-page="<?= $i ?>">
                                                <?= $i ?>
                                            </button>
                                        <?php endfor; ?>

                                        <?php if ($reviewData['pagination']['current_page'] < $reviewData['pagination']['last_page']): ?>
                                            <button class="btn btn-secondary"
                                                    data-page="<?= $reviewData['pagination']['current_page'] + 1 ?>">
                                                Next
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
            </div>

            <!-- Related Products -->
            <?php if (!empty($relatedProducts) && count($relatedProducts) > 0): ?>
                <section class="related-products">
                    <h2 class="section-title">Related Products</h2>
                    <div class="products-grid">
                        <?php foreach ($relatedProducts as $relatedProduct): ?>
                            <div class="product-card">
                                <a href="/products/<?= htmlspecialchars($relatedProduct->slug) ?>"
                                   class="product-image">
                                    <img src="<?= htmlspecialchars($relatedProduct->image ?? '/images/placeholder.jpg') ?>"
                                         alt="<?= htmlspecialchars($relatedProduct->name) ?>">
                                    <?php if ($relatedProduct->discount_percentage > 0): ?>
                                        <span class="badge-sale">-<?= $relatedProduct->discount_percentage ?>%</span>
                                    <?php endif; ?>
                                </a>
                                <div class="product-content">
                                    <h3 class="product-name">
                                        <a href="/products/<?= htmlspecialchars($relatedProduct->slug) ?>">
                                            <?= htmlspecialchars($relatedProduct->name) ?>
                                        </a>
                                    </h3>
                                    <div class="product-price">
                                        <?php if ($relatedProduct->sale_price && $relatedProduct->sale_price < $relatedProduct->price): ?>
                                            <span class="price-sale">$<?= number_format($relatedProduct->sale_price, 2) ?></span>
                                            <span class="price-original">$<?= number_format($relatedProduct->price, 2) ?></span>
                                        <?php else: ?>
                                            <span class="price-current">$<?= number_format($relatedProduct->price, 2) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <button class="btn btn-sm btn-add-to-cart"
                                            data-product-id="<?= $relatedProduct->id ?>">
                                        Add to Cart
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- Recently Viewed -->
            <?php if (!empty($recentlyViewed) && count($recentlyViewed) > 1): ?>
                <section class="recently-viewed">
                    <h2 class="section-title">Recently Viewed</h2>
                    <div class="products-grid">
                        <?php foreach ($recentlyViewed as $viewedProduct): ?>
                            <?php if ($viewedProduct->id !== $product->id): ?>
                                <div class="product-card">
                                    <a href="/products/<?= htmlspecialchars($viewedProduct->slug) ?>"
                                       class="product-image">
                                        <img src="<?= htmlspecialchars($viewedProduct->image_url ?? '/images/placeholder.jpg') ?>"
                                             alt="<?= htmlspecialchars($viewedProduct->name) ?>">
                                    </a>
                                    <div class="product-content">
                                        <h3 class="product-name">
                                            <a href="/products/<?= htmlspecialchars($viewedProduct->slug) ?>">
                                                <?= htmlspecialchars($viewedProduct->name) ?>
                                            </a>
                                        </h3>
                                        <div class="product-price">
                                            <span class="price-current">$<?= number_format($viewedProduct->price, 2) ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="site-footer">
        <div class="container">
            <p>&copy; 2025 YourStore. All rights reserved.</p>
        </div>
    </footer>
</div>

<!-- Toast Notification -->
<div id="toast" class="toast"></div>

<?php
$merchants = json_encode(array_map(function($m) {

    return [
        'id' => $m['id'],
        'merchant_id' => $m['merchant_id'],
        'name' => $m['merchant']['name'] ?? $m['name'],
        'url' => $m['url'],
        'variant_id' => $m['variant_id'],
        'effective_price' => $m['effective_price'],
        'effective_sale_price' => $m['effective_sale_price'],
        'is_available' => $m['is_available'],
        'discount_percentage' => $m['discount_percentage']
    ];
}, $product->availableMerchants->toArray() ?? []));
?>



<script>
    const SITE = 'test-mike';
    const PRODUCT_ID = '<?= $product->id ?>';

    // Store merchant data for variants
    const merchantData = <?php echo $merchants ?>;

    // Image Gallery
    document.querySelectorAll('.thumbnail').forEach(thumbnail => {
        thumbnail.addEventListener('click', function () {
            const imageUrl = this.dataset.image;
            document.getElementById('main-product-image').src = imageUrl;

            document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
        });
    });

    // Tabs functionality
    document.querySelectorAll('.tab-button').forEach(button => {
        button.addEventListener('click', function () {
            const tabId = this.dataset.tab;

            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

            this.classList.add('active');
            document.getElementById(tabId + '-tab').classList.add('active');
        });
    });

    // Variant selection
    const selectedVariant = { id: null };
    const basePrice = <?= $product->price ?>;
    const baseSalePrice = <?= $product->sale_price ?? 0 ?>;

    function updateMerchantPrices(variantId) {
        const merchantsList = document.querySelector('.merchants-list');
        if (!merchantsList) return;

        // Clear current merchants
        merchantsList.innerHTML = '';

        console.log('Updating merchants for variant:', variantId);
        console.log('Merchant data:', merchantData);

        // Filter merchants for this variant (or null variant_id for general listings)
        const relevantMerchants = merchantData.filter(m =>
            m.variant_id === null || m.variant_id == variantId
        );

        if (relevantMerchants.length === 0) {
            merchantsList.innerHTML = '<p style="padding: 2rem; text-align: center; color: #666;">No merchants available for this variant.</p>';
            return;
        }

        // Find lowest price
        const lowestPrice = Math.min(...relevantMerchants.map(m => m.effective_sale_price || m.effective_price));

        // Render merchants
        relevantMerchants.forEach(merchant => {
            const effectivePrice = merchant.effective_sale_price || merchant.effective_price;
            const isLowest = effectivePrice <= lowestPrice * 1.01; // Within 1% of lowest
            const discount = merchant.effective_sale_price
                ? Math.round(((merchant.effective_price - merchant.effective_sale_price) / merchant.effective_price) * 100)
                : 0;

            // Create wrapper
            const merchantWrapper = document.createElement('div');
            merchantWrapper.className = 'merchant-card-wrapper';

            // Add best price badge if applicable
            if (isLowest && merchant.is_available) {
                const badge = document.createElement('div');
                badge.className = 'best-price-badge';
                badge.textContent = '🏆 Best Price';
                merchantWrapper.appendChild(badge);
            }

            // Create merchant card
            const merchantCard = document.createElement('div');
            merchantCard.className = 'merchant-card' + (!merchant.is_available ? ' merchant-unavailable' : '');

            // Build merchant info section
            const merchantInfo = `
            <div class="merchant-info">
                <div class="merchant-logo">
                    ${merchant.name.charAt(0).toUpperCase()}
                </div>
                <div class="merchant-details">
                    <div class="merchant-name">${merchant.name}</div>
                    ${merchant.is_available ? '<div class="merchant-shipping">✓ In Stock</div>' : ''}
                </div>
            </div>
        `;

            // Build pricing section
            const pricingHtml = merchant.effective_sale_price && merchant.effective_sale_price < merchant.effective_price
                ? `<div class="merchant-price-container">
                    <span class="merchant-price sale-price">$${merchant.effective_sale_price.toFixed(2)}</span>
                    <span class="merchant-original-price">$${merchant.effective_price.toFixed(2)}</span>
               </div>`
                : `<div class="merchant-price-container">
                    <span class="merchant-price">$${merchant.effective_price.toFixed(2)}</span>
               </div>`;

            const savingsHtml = discount > 0
                ? `<span class="merchant-savings">Save ${discount}%</span>`
                : '';

            // Build action section
            const actionHtml = merchant.is_available
                ? `<a href="${merchant.url}" class="merchant-link" target="_blank" rel="noopener noreferrer">
                    Buy Now
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
               </a>`
                : `<span class="merchant-unavailable-badge">Out of Stock</span>`;

            // Assemble the card
            merchantCard.innerHTML = `
            ${merchantInfo}
            <div class="merchant-action">
                <div class="merchant-pricing">
                    ${pricingHtml}
                    ${savingsHtml}
                </div>
                ${actionHtml}
            </div>
        `;

            merchantWrapper.appendChild(merchantCard);
            merchantsList.appendChild(merchantWrapper);
        });
    }

    document.querySelectorAll('.variant-select-btn').forEach(button => {
        button.addEventListener('click', function() {
            const variantId = parseInt(this.dataset.variantId);
            const variantPrice = parseFloat(this.dataset.price);
            const variantSalePrice = this.dataset.salePrice ? parseFloat(this.dataset.salePrice) : null;

            // Deselect all variants
            document.querySelectorAll('.variant-item').forEach(item => {
                item.classList.remove('selected');
            });

            // Select this variant
            this.closest('.variant-item').classList.add('selected');
            selectedVariant.id = variantId;

            // Update displayed price in the main product info section
            const productPriceContainer = document.querySelector('.product-price');

            if (variantSalePrice && variantSalePrice < variantPrice) {
                // Has sale price
                productPriceContainer.innerHTML = `
                <span class="price-sale">$${variantSalePrice.toFixed(2)}</span>
                <span class="price-original">$${variantPrice.toFixed(2)}</span>
            `;
            } else {
                // Regular price only
                productPriceContainer.innerHTML = `
                <span class="price-current">$${variantPrice.toFixed(2)}</span>
            `;
            }

            // NEW: Update price alert button with variant info
            const priceAlertBtn = document.querySelector('.price-alert-trigger');
            if (priceAlertBtn) {
                const currentPrice = variantSalePrice && variantSalePrice < variantPrice ? variantSalePrice : variantPrice;
                priceAlertBtn.setAttribute('onclick', `openPriceAlert(<?= $product->id ?>, ${variantId}, null, ${currentPrice})`);
            }

            // Update merchant prices for this variant
            updateMerchantPrices(variantId);

            console.log('Selected variant:', variantId, 'Price:', variantPrice, 'Sale Price:', variantSalePrice);
        });
    });

    // Voucher copy functionality
    document.querySelectorAll('.voucher-copy-btn').forEach(button => {
        button.addEventListener('click', function () {
            const code = this.dataset.code;

            navigator.clipboard.writeText(code).then(() => {
                const originalText = this.textContent;
                this.textContent = 'Copied!';
                this.style.background = '#10b981';

                setTimeout(() => {
                    this.textContent = originalText;
                    this.style.background = '';
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy:', err);
            });
        });
    });
</script>

@js('product-reviews.js')
@js('product-detail.js')

@include('components/price-alert')

@css('price-alert.css')
@js('price-alert.js')
</body>
</html>