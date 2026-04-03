<?php
/**
 * Site header component.
 *
 * @var string $activeNav Current active nav item slug (e.g. 'cart', 'shop').
 * @var int $cartCount Number of items in the cart.
 */
$activeNav = $activeNav ?? '';
$cartCount = $cartCount ?? 0;
?>
<header class="site-header">
    <div class="container">
        <div class="header-content">
            <div class="logo">
                <a href="/">YourStore</a>
            </div>
            <nav class="main-nav">
                <a href="/" <?= $activeNav === 'home' ? 'class="active"' : '' ?>>Home</a>
                <a href="/shop" <?= $activeNav === 'shop' ? 'class="active"' : '' ?>>Shop</a>
                <a href="/cart" <?= $activeNav === 'cart' ? 'class="active"' : '' ?>>Cart</a>
                <a href="/contact" <?= $activeNav === 'contact' ? 'class="active"' : '' ?>>Contact</a>
            </nav>
            <div class="header-actions">
                <button class="icon-btn" onclick="window.location.href='/wishlist'" aria-label="Wishlist">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                    </svg>
                    <span class="badge" id="wishlist-count"><?= (int)($wishlistCount ?? 0) ?></span>
                </button>
                <button class="icon-btn" onclick="window.location.href='/cart'" aria-label="Cart">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle cx="9" cy="21" r="1"></circle>
                        <circle cx="20" cy="21" r="1"></circle>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                    </svg>
                    <span class="badge" id="cart-count"><?= (int)($cartCount ?? 0) ?></span>
                </button>
            </div>
        </div>
    </div>
</header>