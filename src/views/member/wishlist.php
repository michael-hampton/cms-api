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
    const API_BASE = '/api/' + SITE_SLUG;

    class WishlistStore {
        constructor() {
            this.state = {
                items: [],
                loading: false,
                error: null,
                removingIds: new Set(),
                addingIds: new Set(),
            };
            this.listeners = [];
        }

        subscribe(listener) {
            this.listeners.push(listener);
            listener(this.state);
        }

        setState(patch) {
            this.state = {
                ...this.state,
                ...patch,
            };

            this.listeners.forEach(listener => listener(this.state));
        }

        setFlag(key, id, enabled) {
            const ids = new Set(this.state[key]);

            if (enabled) {
                ids.add(id);
            } else {
                ids.delete(id);
            }

            this.setState({[key]: ids});
        }
    }

    /* ─── UI COMPONENTS ─────────────────────────────────────── */

    /**
     * Component: Individual Wishlist Product Card
     */
    class WishlistItem {
        constructor(item, manager, options = {}) {
            this.data = item;
            this.manager = manager;
            this.options = options;
            this.el = null;
        }

        render() {
            const i = this.data;
            const hasDiscount = i.discount_percentage > 0;
            const isInStock = true; // Logic preserved from original
            const isRemoving = !!this.options.removing;
            const isAdding = !!this.options.adding;

            const cartBtnProps = {
                className: 'add-to-cart-btn',
                onclick: (e) => this.handleAddToCart(e),
                disabled: isAdding ? 'disabled' : undefined,
            };

            if (!isInStock) {
                cartBtnProps.disabled = 'disabled';
            }

            this.el = UI.el('div', {className: 'product-card', 'data-product-id': i.product_id}, [
                // Image Wrapper
                UI.el('div', {className: 'product-image-wrapper'}, [
                    UI.el('img', {
                        src: i.product_image || '/images/placeholder.jpg',
                        alt: i.product_name,
                        className: 'product-image'
                    }),
                    hasDiscount ? UI.el('span', {className: 'discount-badge'}, [`${i.discount_percentage}% OFF`]) : null,
                    UI.el('button', {
                        className: 'remove-wishlist-btn',
                        title: 'Remove from wishlist',
                        disabled: isRemoving,
                        onclick: () => this.handleRemove()
                    }, [
                        // Inline Heart/Remove Icon
                        UI.rawEl(`<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>`)
                    ])
                ]),

                // Product Info
                UI.el('div', {className: 'product-info'}, [
                    UI.el('a', {href: `/shop/details/${i.product_slug}`, className: 'product-name'}, [i.product_name]),

                    UI.el('div', {className: 'product-price'}, [
                        UI.el('span', {className: 'current-price'}, [this.manager.formatCurrency(i.price)]),
                        hasDiscount ? UI.el('span', {className: 'original-price'}, [this.manager.formatCurrency(i.original_price)]) : null
                    ]),

                    UI.el('div', {className: `stock-status ${isInStock ? 'in-stock' : 'out-of-stock'}`}, [
                        UI.el('span', {className: 'stock-dot'}),
                        UI.el('span', {}, [isInStock ? 'In Stock' : 'Out of Stock'])
                    ]),

                    // Actions
                    UI.el('div', {className: 'product-actions'}, [
                        UI.el('button', cartBtnProps, [
                            UI.rawEl('<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>'),
                            UI.el('span', {style: {marginLeft: '8px'}}, [isAdding ? 'Adding…' : (isInStock ? 'Add to Cart' : 'Unavailable')])
                        ]),
                        UI.el('button', {
                            className: 'view-btn',
                            title: 'View details',
                            onclick: () => window.location.href = `/shop/details/${i.product_slug}`
                        }, [
                            UI.rawEl(`<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>`)
                        ])
                    ])
                ])
            ]);

            return this.el;
        }

        async handleRemove() {
            const success = await this.manager.removeItem(this.data.product_id);
            if (success) {
                this.el.style.transition = 'all 0.3s ease';
                this.el.style.opacity = '0';
                this.el.style.transform = 'scale(0.9)';
                setTimeout(() => {
                    this.el.remove();
                    this.manager.updateCount();
                }, 300);
            }
        }

        async handleAddToCart(e) {
            const btn = e.currentTarget;
            const originalContent = btn.innerHTML;

            btn.disabled = true;
            btn.innerHTML = 'Adding...';

            const result = await this.manager.addToCartApi(this.data.product_id);

            btn.disabled = false;
            btn.innerHTML = originalContent;

            if (result.success) {
                UI.toast(result.message || 'Added to cart!', 'success');
            } else if (result.message) {
                UI.toast(result.message, 'error');
            }
        }
    }

    /* ─── APP ORCHESTRATOR ──────────────────────────────────── */

    class WishlistApp {
        constructor() {
            this.grid = document.getElementById('wishlist-grid');
            this.emptyState = document.getElementById('empty-wishlist');
            this.container = document.getElementById('wishlist-container');
            this.countLabel = document.getElementById('items-count');
            this.store = new WishlistStore();
            this.store.subscribe(state => this.render(state));
            this.init();
        }

        async init() {
            await this.loadWishlist();
        }

        async loadWishlist() {
            this.store.setState({loading: true, error: null});

            try {
                const res = await api(`${API_BASE}/wishlist`);
                this.store.setState({
                    items: res.items || [],
                    loading: false,
                });
            } catch (_) {
                this.store.setState({
                    loading: false,
                    error: 'Failed to load wishlist',
                });
                UI.toast('Failed to load wishlist', 'error');
            }
        }

        render(state) {
            if (state.loading || state.error) {
                return;
            }

            if (state.items.length === 0) {
                this.emptyState.style.display = 'block';
                this.container.style.display = 'none';
                return;
            }

            this.emptyState.style.display = 'none';
            this.container.style.display = 'block';

            const cards = state.items.map(item => new WishlistItem(item, this, {
                removing: state.removingIds.has(item.product_id),
                adding: state.addingIds.has(item.product_id),
            }).render());
            UI.render(this.grid, cards);
            this.updateCount();
        }

        updateCount() {
            const count = this.grid.querySelectorAll('.product-card').length;
            this.countLabel.textContent = count;
            if (count === 0) this.render();
        }

        async removeItem(productId) {
            try {
                this.store.setFlag('removingIds', productId, true);
                const res = await api(`${API_BASE}/wishlist/remove/${productId}`, {method: 'DELETE'});
                if (res.success) {
                    this.store.setState({
                        items: this.store.state.items.filter(item => item.product_id !== productId),
                    });
                    UI.toast('Removed from wishlist', 'success');
                    return true;
                }
                throw new Error(res.message);
            } catch (e) {
                UI.toast(e.message || 'Error removing item', 'error');
                return false;
            } finally {
                this.store.setFlag('removingIds', productId, false);
            }
        }

        async addToCartApi(productId) {
            try {
                this.store.setFlag('addingIds', productId, true);
                const res = await api(`${API_BASE}/cart/add`, {
                    method: 'POST',
                    body: JSON.stringify({product_id: productId, quantity: 1})
                });
                return {
                    success: res.success !== false,
                    message: res.message || 'Added to cart!',
                };
            } catch (error) {
                return {
                    success: false,
                    message: error.message || 'Failed to add item to cart',
                };
            } finally {
                this.store.setFlag('addingIds', productId, false);
            }
        }

        async addAllToCart() {
            if (!this.store.state.items.length) return;

            let successCount = 0;
            const btn = document.querySelector('.btn-primary');
            btn.disabled = true;

            for (const item of this.store.state.items) {
                const result = await this.addToCartApi(item.product_id);
                if (result.success) successCount++;
            }

            btn.disabled = false;
            if (successCount > 0) {
                UI.toast(`Added ${successCount} items to your cart`);
            }
        }

        formatCurrency(amount) {
            return '£' + parseFloat(amount).toFixed(2);
        }
    }

    /* ─── BOOTSTRAP ─────────────────────────────────────────── */

    document.addEventListener('DOMContentLoaded', () => {
        window.wishlistApp = new WishlistApp();
    });

    // Global hook for the "Add All" button in HTML
    function addAllToCart() {
        window.wishlistApp.addAllToCart();
    }
</script>
</body>
</html>
