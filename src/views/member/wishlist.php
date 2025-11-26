<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist - Member Dashboard</title>
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
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .dashboard-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 2rem;
            margin-top: 2rem;
            margin-bottom: 3rem;
        }

        /* Sidebar Navigation */
        .dashboard-sidebar {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            height: fit-content;
            position: sticky;
            top: 2rem;
        }

        .sidebar-nav {
            list-style: none;
        }

        .sidebar-nav li {
            margin-bottom: 0.5rem;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 0.5rem;
            transition: all 0.3s;
        }

        .sidebar-nav a:hover {
            background: var(--bg-light);
            color: var(--primary-color);
        }

        .sidebar-nav a.active {
            background: var(--primary-color);
            color: white;
        }

        /* Page Header */
        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 0.75rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
        }

        /* Wishlist Actions Bar */
        .wishlist-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .items-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .items-info svg {
            color: var(--primary-color);
        }

        .action-buttons {
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
            text-decoration: none;
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

        /* Wishlist Grid */
        .wishlist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
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

        .discount-badge {
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

        @media (max-width: 968px) {
            .dashboard-layout {
                grid-template-columns: 1fr;
            }

            .dashboard-sidebar {
                position: static;
            }

            .wishlist-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 1rem;
            }

            .wishlist-actions {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }

            .action-buttons {
                width: 100%;
            }

            .btn {
                flex: 1;
            }
        }
    </style>
</head>
<body>

@include('member._header')

<div class="container">
        <!-- Main Content -->
        <main>
            <div class="page-header">
                <h1 class="page-title">My Wishlist</h1>
                <p class="page-subtitle">Save your favorite items for later</p>
            </div>

            <div id="wishlist-container">
                <div class="wishlist-actions">
                    <div class="items-info">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                        </svg>
                        <div>
                            <h2 style="font-size: 1.25rem; font-weight: 600;"><span id="items-count">0</span> Items</h2>
                        </div>
                    </div>
                    <div class="action-buttons">
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

                <div id="wishlist-grid" class="wishlist-grid"></div>

                <div id="empty-wishlist" class="empty-wishlist" style="display: none;">
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
            </div>
        </main>
    </div>

<!-- Toast -->
<div id="toast" class="toast"></div>

<script>
    const SITE = '<?= $site->slug ?? 'default' ?>';
    const API_BASE = '/api/' + SITE;

    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.className = `toast ${type} show`;
        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }

    function formatCurrency(amount) {
        return '£' + parseFloat(amount).toFixed(2);
    }

    async function loadWishlist() {
        try {
            const response = await fetch(`${API_BASE}/wishlist`);
            const data = await response.json();

            if (!data.items || data.items.length === 0) {
                document.getElementById('empty-wishlist').style.display = 'block';
                document.getElementById('wishlist-container').style.display = 'none';
                return;
            }

            renderWishlist(data.items);
            document.getElementById('items-count').textContent = data.items.length;
        } catch (error) {
            console.error('Error loading wishlist:', error);
            showToast('Failed to load wishlist', 'error');
        }
    }

    function renderWishlist(items) {
        const grid = document.getElementById('wishlist-grid');
        grid.innerHTML = items.map(item => {
            const hasDiscount = item.discount_percentage > 0;
            const isInStock = true;

            return `
                    <div class="product-card" data-product-id="${item.product_id}">
                        <div class="product-image-wrapper">
                            <img src="${item.product_image || '/images/placeholder.jpg'}" alt="${item.product_name}" class="product-image">
                            ${hasDiscount ? `<span class="discount-badge">${item.discount_percentage}% OFF</span>` : ''}
                            <button class="remove-wishlist-btn" onclick="removeFromWishlist(${item.product_id})" title="Remove from wishlist">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="product-info">
                            <a href="/shop/details/${item.product_slug}" class="product-name">${item.product_name}</a>
                            <div class="product-price">
                                <span class="current-price">${formatCurrency(item.price)}</span>
                                ${hasDiscount ? `<span class="original-price">${formatCurrency(item.original_price)}</span>` : ''}
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
    }

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
                showToast(data.message || 'Failed to remove item', 'error');
            }
        } catch (error) {
            console.error('Error removing from wishlist:', error);
            showToast('Failed to remove item', 'error');
        }
    }

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
                showToast('Added to cart successfully');
            } else {
                showToast(data.message || 'Failed to add to cart', 'error');
            }
        } catch (error) {
            console.error('Error adding to cart:', error);
            showToast('Failed to add to cart', 'error');
        }
    }

    async function addAllToCart() {
        const response = await fetch(`${API_BASE}/wishlist`);
        const wishlistData = await response.json();


        if (!wishlistData.items || wishlistData.items.length === 0) {
            showToast('No items in wishlist', 'error');
            return;
        }

        //const availableItems = wishlistData.items.filter(item => item.in_stock);
        const availableItems = wishlistData.items;

        console.log('Available items:', availableItems);

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
        }

        if (failCount > 0) {
            showToast(`Failed to add ${failCount} item(s)`, 'error');
        }
    }

    // Initialize
    loadWishlist();
</script>
</body>
</html>