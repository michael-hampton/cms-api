<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist - YourStore</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #2563eb;
            --primary-dark: #1e40af;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --border-color: #e2e8f0;
            --bg-light: #f8fafc;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: var(--text-primary);
            background-color: var(--bg-light);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header */
        .site-header {
            background: white;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
        }

        .logo a {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
        }

        .main-nav {
            display: flex;
            gap: 2rem;
        }

        .main-nav a {
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .main-nav a:hover,
        .main-nav a.active {
            color: var(--primary-color);
        }

        .header-actions {
            display: flex;
            gap: 1rem;
        }

        .icon-btn {
            position: relative;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: background-color 0.3s;
        }

        .icon-btn:hover {
            background-color: var(--bg-light);
        }

        .badge {
            position: absolute;
            top: 0;
            right: 0;
            background: var(--danger-color);
            color: white;
            font-size: 0.75rem;
            padding: 0.125rem 0.375rem;
            border-radius: 1rem;
            font-weight: 600;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 3rem 0;
            margin-bottom: 2rem;
            color: white;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            font-size: 1.125rem;
            opacity: 0.9;
        }

        .breadcrumb {
            display: flex;
            gap: 0.5rem;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.875rem;
            margin-top: 1rem;
        }

        .breadcrumb a {
            color: white;
            text-decoration: none;
        }

        /* Wishlist Grid */
        .wishlist-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: var(--shadow);
        }

        .items-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .items-info svg {
            color: var(--primary-color);
        }

        .items-info h2 {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .wishlist-actions {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-outline {
            background: white;
            color: var(--danger-color);
            border: 2px solid var(--danger-color);
        }

        .btn-outline:hover {
            background: var(--danger-color);
            color: white;
        }

        .wishlist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        /* Product Card */
        .product-card {
            background: white;
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s;
            position: relative;
        }

        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .product-image-wrapper {
            position: relative;
            padding-top: 100%;
            overflow: hidden;
            background: var(--bg-light);
        }

        .product-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: var(--danger-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .product-badge.out-of-stock {
            background: var(--secondary-color);
        }

        .remove-wishlist-btn {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: white;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: var(--shadow);
            transition: all 0.3s;
        }

        .remove-wishlist-btn:hover {
            background: var(--danger-color);
            color: white;
            transform: scale(1.1);
        }

        .remove-wishlist-btn svg {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }

        .product-info {
            padding: 1.25rem;
        }

        .product-name {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            text-decoration: none;
            display: block;
            transition: color 0.3s;
        }

        .product-name:hover {
            color: var(--primary-color);
        }

        .product-price {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .current-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .original-price {
            font-size: 1rem;
            color: var(--text-secondary);
            text-decoration: line-through;
        }

        .discount-badge {
            background: var(--success-color);
            color: white;
            padding: 0.125rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .stock-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }

        .stock-status.in-stock {
            color: var(--success-color);
        }

        .stock-status.out-of-stock {
            color: var(--danger-color);
        }

        .stock-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
        }

        .product-actions {
            display: flex;
            gap: 0.5rem;
        }

        .add-to-cart-btn {
            flex: 1;
            padding: 0.75rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .add-to-cart-btn:hover:not(:disabled) {
            background: var(--primary-dark);
        }

        .add-to-cart-btn:disabled {
            background: var(--secondary-color);
            cursor: not-allowed;
            opacity: 0.6;
        }

        .view-btn {
            padding: 0.75rem;
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .view-btn:hover {
            background: var(--primary-color);
            color: white;
        }

        /* Empty State */
        .empty-wishlist {
            background: white;
            border-radius: 0.75rem;
            padding: 4rem 2rem;
            text-align: center;
            box-shadow: var(--shadow);
        }

        .empty-wishlist svg {
            width: 80px;
            height: 80px;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .empty-wishlist h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .empty-wishlist p {
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }

        /* Loading State */
        .loading-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 4rem 2rem;
        }

        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid var(--border-color);
            border-top-color: var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            box-shadow: var(--shadow-lg);
            display: none;
            align-items: center;
            gap: 1rem;
            z-index: 1000;
            animation: slideIn 0.3s ease-out;
        }

        .toast.show {
            display: flex;
        }

        .toast.success {
            border-left: 4px solid var(--success-color);
        }

        .toast.error {
            border-left: 4px solid var(--danger-color);
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Footer */
        .site-footer {
            background: white;
            padding: 2rem 0;
            text-align: center;
            color: var(--text-secondary);
            margin-top: 4rem;
            box-shadow: var(--shadow);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .wishlist-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 1rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .wishlist-header {
                flex-direction: column;
                gap: 1rem;
            }

            .wishlist-actions {
                width: 100%;
            }

            .btn {
                flex: 1;
            }

            .main-nav {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .wishlist-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .page-title {
                font-size: 2rem;
            }

            .wishlist-header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }

            .wishlist-actions {
                width: 100%;
            }

            .btn {
                flex: 1;
            }

            .main-nav {
                display: none;
            }

            .product-card {
                font-size: 0.875rem;
            }

            .product-name {
                font-size: 0.9rem;
            }

            .current-price {
                font-size: 1.1rem;
            }
        }

        @media (max-width: 480px) {
            .wishlist-grid {
                grid-template-columns: 1fr;
            }

            .product-actions {
                flex-direction: column;
                gap: 0.5rem;
            }

            .view-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<!-- Header -->
<header class="site-header">
    <div class="container">
        <div class="header-content">
            <div class="logo">
                <a href="/">YourStore</a>
            </div>
            <nav class="main-nav">
                <a href="/">Home</a>
                <a href="/shop">Shop</a>
                <a href="/wishlist" class="active">Wishlist</a>
                <a href="/contact">Contact</a>
            </nav>
            <div class="header-actions">
                <button class="icon-btn">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                    </svg>
                    <span class="badge" id="wishlist-count">0</span>
                </button>
                <button class="icon-btn" onclick="window.location.href='/cart'">
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

<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <h1 class="page-title">My Wishlist</h1>
        <p class="page-subtitle">Save your favorite items for later</p>
        <div class="breadcrumb">
            <a href="/">Home</a>
            <span>/</span>
            <span>Wishlist</span>
        </div>
    </div>
</div>

<!-- Main Content -->
<!-- Main Content -->
<main>
    <div class="container">
        <?php if (empty($items) || count($items) === 0): ?>
            <div id="empty-container" class="empty-wishlist">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                </svg>
                <h3>Your wishlist is empty</h3>
                <p>Start adding products you love!</p>
                <button class="btn btn-primary" onclick="window.location.href='/shop'" style="margin: 0 auto;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                    Browse Products
                </button>
            </div>
        <?php else: ?>
            <div id="wishlist-container">
                <div class="wishlist-header">
                    <div class="items-info">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                        </svg>
                        <div>
                            <h2><span id="items-count"><?= count($items) ?></span> Items in Wishlist</h2>
                        </div>
                    </div>
                    <div class="wishlist-actions">
                        <button class="btn btn-primary" onclick="addAllToCart()">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <circle cx="9" cy="21" r="1"></circle>
                                <circle cx="20" cy="21" r="1"></circle>
                                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                            </svg>
                            Add All to Cart
                        </button>
                    </div>
                </div>

                <div id="wishlist-grid" class="wishlist-grid">
                    <?php foreach ($items as $item): ?>
                        <?php
                        $hasDiscount = $item['discount_percentage'] > 0;
                        $isInStock = $item['in_stock'];
                        ?>
                        <div class="product-card" data-product-id="<?= $item['product_id'] ?>">
                            <div class="product-image-wrapper">
                                <img src="<?= htmlspecialchars($item['product_image'] ?? '/images/placeholder.jpg') ?>"
                                     alt="<?= htmlspecialchars($item['product_name']) ?>"
                                     class="product-image">
                                <?php if ($hasDiscount): ?>
                                    <span class="product-badge"><?= $item['discount_percentage'] ?>% OFF</span>
                                <?php endif; ?>
                                <?php if (!$isInStock): ?>
                                    <span class="product-badge out-of-stock">Out of Stock</span>
                                <?php endif; ?>
                                <button class="remove-wishlist-btn" onclick="removeFromWishlist(<?= $item['product_id'] ?>)" title="Remove from wishlist">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                    </svg>
                                </button>
                            </div>
                            <div class="product-info">
                                <a href="/shop/details/<?= htmlspecialchars($item['product_slug']) ?>" class="product-name"><?= htmlspecialchars($item['product_name']) ?></a>
                                <div class="product-price">
                                    <span class="current-price">$<?= number_format($item['price'], 2) ?></span>
                                    <?php if ($hasDiscount): ?>
                                        <span class="original-price">$<?= number_format($item['original_price'], 2) ?></span>
                                        <span class="discount-badge">-<?= $item['discount_percentage'] ?>%</span>
                                    <?php endif; ?>
                                </div>
                                <div class="stock-status <?= $isInStock ? 'in-stock' : 'out-of-stock' ?>">
                                    <span class="stock-dot"></span>
                                    <span><?= $isInStock ? 'In Stock' : 'Out of Stock' ?></span>
                                </div>
                                <div class="product-actions">
                                    <button class="add-to-cart-btn" onclick="addToCart(<?= $item['product_id'] ?>)" <?= !$isInStock ? 'disabled' : '' ?>>
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <circle cx="9" cy="21" r="1"></circle>
                                            <circle cx="20" cy="21" r="1"></circle>
                                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                                        </svg>
                                        <?= $isInStock ? 'Add to Cart' : 'Unavailable' ?>
                                    </button>
                                    <button class="view-btn" onclick="window.location.href='/shop/details/<?= htmlspecialchars($item['product_slug']) ?>'" title="View details">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Footer -->
<footer class="site-footer">
    <div class="container">
        <p>&copy; 2025 YourStore. All rights reserved.</p>
    </div>
</footer>

<!-- Toast -->
<div id="toast" class="toast"></div>

<script>
    const SITE = 'test-mike';
    const API_BASE = '/api/' + SITE;

    let wishlistData = null;

    // Show toast notification
    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.className = `toast ${type} show`;
        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }

    // Format currency
    function formatCurrency(amount) {
        return '$' + parseFloat(amount).toFixed(2);
    }

    // Load wishlist data
    async function loadWishlist() {
        const loading = document.getElementById('loading-container');
        const empty = document.getElementById('empty-container');
        const wishlistContainer = document.getElementById('wishlist-container');

        loading.style.display = 'flex';
        empty.style.display = 'none';
        wishlistContainer.style.display = 'none';

        try {
            const response = await fetch(`${API_BASE}/wishlist`);
            wishlistData = await response.json();

            if (!wishlistData.items || wishlistData.items.length === 0) {
                loading.style.display = 'none';
                empty.style.display = 'block';
                updateWishlistCount(0);
                return;
            }

            renderWishlist();
            loading.style.display = 'none';
            wishlistContainer.style.display = 'block';
        } catch (error) {
            console.error('Error loading wishlist:', error);
            showToast('Failed to load wishlist', 'error');
            loading.style.display = 'none';
            empty.style.display = 'block';
        }
    }

    // Render wishlist items
    function renderWishlist() {
        const grid = document.getElementById('wishlist-grid');
        grid.innerHTML = wishlistData.items.map(item => {
            const hasDiscount = item.discount_percentage > 0;
            const isInStock = item.in_stock;

            return `
                    <div class="product-card" data-product-id="${item.product_id}">
                        <div class="product-image-wrapper">
                            <img src="${item.product_image || '/images/placeholder.jpg'}" alt="${item.product_name}" class="product-image">
                            ${hasDiscount ? `<span class="product-badge">${item.discount_percentage}% OFF</span>` : ''}
                            ${!isInStock ? '<span class="product-badge out-of-stock">Out of Stock</span>' : ''}
                            <button class="remove-wishlist-btn" onclick="removeFromWishlist(${item.product_id})" title="Remove from wishlist">
                                <svg viewBox="0 0 24 24">
                                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="product-info">
                            <a href="/shop/details/${item.product_slug}" class="product-name">${item.product_name}</a>
                            <div class="product-price">
                                <span class="current-price">${formatCurrency(item.price)}</span>
                                ${hasDiscount ? `
                                    <span class="original-price">${formatCurrency(item.original_price)}</span>
                                    <span class="discount-badge">-${item.discount_percentage}%</span>
                                ` : ''}
                            </div>
                            <div class="stock-status ${isInStock ? 'in-stock' : 'out-of-stock'}">
                                <span class="stock-dot"></span>
                                <span>${isInStock ? 'In Stock' : 'Out of Stock'}</span>
                            </div>
                            <div class="product-actions">
                                <button class="add-to-cart-btn" onclick="addToCart(${item.product_id})" ${!isInStock ? 'disabled' : ''}>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <circle cx="9" cy="21" r="1"></circle>
                                        <circle cx="20" cy="21" r="1"></circle>
                                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                                    </svg>
                                    ${isInStock ? 'Add to Cart' : 'Unavailable'}
                                </button>
                                <button class="view-btn" onclick="window.location.href='/shop/details/${item.product_slug}'" title="View details">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
        }).join('');

        updateWishlistCount(wishlistData.count);
        document.getElementById('items-count').textContent = wishlistData.items.length;
    }

    // Remove from wishlist
    async function removeFromWishlist(productId) {
        try {
            const response = await fetch(`${API_BASE}/wishlist/${productId}`, {
                method: 'DELETE'
            });

            const data = await response.json();

            if (data.success) {
                await loadWishlist();
                showToast('Removed from wishlist');
            } else {
                showToast(data.data.message || 'Failed to remove item', 'error');
            }
        } catch (error) {
            console.error('Error removing from wishlist:', error);
            showToast('Failed to remove item', 'error');
        }
    }

    // Add to cart
    async function addToCart(productId) {
        try {
            const response = await fetch(`${API_BASE}/cart`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    product_id: productId,
                    quantity: 1
                })
            });

            const data = await response.json();

            if (data.success) {
                updateCartCount(data.data.count);
                showToast('Added to cart successfully');
            } else {
                showToast(data.data.message || 'Failed to add to cart', 'error');
            }
        } catch (error) {
            console.error('Error adding to cart:', error);
            showToast('Failed to add to cart', 'error');
        }
    }

    // Add all to cart
    async function addAllToCart() {
        if (!wishlistData || !wishlistData.items.length) return;

        const availableItems = wishlistData.items.filter(item => item.in_stock);

        if (availableItems.length === 0) {
            showToast('No items available to add to cart', 'error');
            return;
        }

        let successCount = 0;
        let failCount = 0;

        for (const item of availableItems) {
            try {
                const response = await fetch(`${API_BASE}/cart`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        product_id: item.product_id,
                        quantity: 1
                    })
                });

                const data = await response.json();

                if (data.success) {
                    successCount++;
                } else {
                    failCount++;
                }
            } catch (error) {
                failCount++;
                console.error('Error adding item to cart:', error);
            }
        }

        if (successCount > 0) {
            showToast(`Added ${successCount} item(s) to cart`);
            loadCartCount();
        }

        if (failCount > 0) {
            showToast(`Failed to add ${failCount} item(s)`, 'error');
        }
    }

    // Update wishlist count
    function updateWishlistCount(count) {
        document.getElementById('wishlist-count').textContent = count;
    }

    // Update cart count
    function updateCartCount(count) {
        document.getElementById('cart-count').textContent = count;
    }

    // Load cart count
    async function loadCartCount() {
        try {
            const response = await fetch(`${API_BASE}/cart`);
            const data = await response.json();
            updateCartCount(data.count || 0);
        } catch (error) {
            console.error('Error loading cart count:', error);
        }
    }
</script>
</body>
</html>
