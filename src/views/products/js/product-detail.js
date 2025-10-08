(function() {
    'use strict';

    // State
    const state = {
        quantity: 1,
        productId: null,
        isInWishlist: false,
        cartCount: 0,
        wishlistCount: 0
    };

    // DOM Elements
    const elements = {
        qtyDecrease: document.getElementById('qty-decrease'),
        qtyIncrease: document.getElementById('qty-increase'),
        quantityInput: document.getElementById('quantity'),
        addToCartBtn: document.getElementById('add-to-cart-btn'),
        toggleWishlistBtn: document.getElementById('toggle-wishlist-btn'),
        cartCount: document.getElementById('cart-count'),
        wishlistCount: document.getElementById('wishlist-count'),
        toast: document.getElementById('toast'),
        relatedAddToCartBtns: document.querySelectorAll('.related-products .btn-add-to-cart')
    };

    // Initialize
    function init() {
        if (elements.addToCartBtn) {
            state.productId = parseInt(elements.addToCartBtn.dataset.productId);
        }

        if (elements.toggleWishlistBtn) {
            state.isInWishlist = elements.toggleWishlistBtn.dataset.inWishlist === 'true';
        }

        attachEventListeners();
        updateCounts();
    }

    // Event Listeners
    function attachEventListeners() {
        // Quantity controls
        if (elements.qtyDecrease) {
            elements.qtyDecrease.addEventListener('click', decreaseQuantity);
        }

        if (elements.qtyIncrease) {
            elements.qtyIncrease.addEventListener('click', increaseQuantity);
        }

        // Add to cart
        if (elements.addToCartBtn) {
            elements.addToCartBtn.addEventListener('click', handleAddToCart);
        }

        // Toggle wishlist
        if (elements.toggleWishlistBtn) {
            elements.toggleWishlistBtn.addEventListener('click', handleToggleWishlist);
        }

        // Related products add to cart
        elements.relatedAddToCartBtns.forEach(btn => {
            btn.addEventListener('click', handleRelatedAddToCart);
        });
    }

    // Decrease quantity
    function decreaseQuantity() {
        if (state.quantity > 1) {
            state.quantity--;
            updateQuantityDisplay();
        }
    }

    // Increase quantity
    function increaseQuantity() {
        if (state.quantity < 99) {
            state.quantity++;
            updateQuantityDisplay();
        }
    }

    // Update quantity display
    function updateQuantityDisplay() {
        if (elements.quantityInput) {
            elements.quantityInput.value = state.quantity;
        }
    }

    // Handle add to cart
    async function handleAddToCart() {
        const btn = elements.addToCartBtn;
        const originalContent = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <circle cx="12" cy="12" r="10"></circle>
            </svg>
            Adding...
        `;

        try {
            const response = await fetch('/api/cart/add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    product_id: state.productId,
                    quantity: state.quantity
                })
            });

            const data = await response.json();

            if (data.success) {
                showToast(data.message, 'success');
                state.cartCount = data.count;
                updateCartCount();

                // Reset quantity to 1
                state.quantity = 1;
                updateQuantityDisplay();
            } else {
                showToast(data.message, 'error');
            }
        } catch (error) {
            console.error('Error adding to cart:', error);
            showToast('Failed to add item to cart', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalContent;
        }
    }

    // Handle toggle wishlist
    async function handleToggleWishlist() {
        const btn = elements.toggleWishlistBtn;
        const svg = btn.querySelector('svg');
        const originalContent = btn.innerHTML;

        btn.disabled = true;

        try {
            const url = state.isInWishlist
                ? `/api/wishlist/remove/${state.productId}`
                : '/api/wishlist/add';

            const response = await fetch(url, {
                method: state.isInWishlist ? 'DELETE' : 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: state.isInWishlist ? null : JSON.stringify({ product_id: state.productId })
            });

            const data = await response.json();

            if (data.success) {
                state.isInWishlist = !state.isInWishlist;
                btn.classList.toggle('active');

                // Update SVG fill
                if (svg) {
                    svg.setAttribute('fill', state.isInWishlist ? 'currentColor' : 'none');
                }

                // Update button text
                const textNode = btn.childNodes[btn.childNodes.length - 1];
                textNode.textContent = state.isInWishlist
                    ? ' Remove from Wishlist'
                    : ' Add to Wishlist';

                showToast(data.message, 'success');
                state.wishlistCount = data.count;
                updateWishlistCount();
            } else {
                showToast(data.message, 'error');
            }
        } catch (error) {
            console.error('Error toggling wishlist:', error);
            showToast('An error occurred', 'error');
        } finally {
            btn.disabled = false;
        }
    }

    // Handle related product add to cart
    async function handleRelatedAddToCart(e) {
        const btn = e.currentTarget;
        const productId = btn.dataset.productId;
        const originalText = btn.textContent;

        btn.disabled = true;
        btn.textContent = 'Adding...';

        try {
            const response = await fetch('/api/cart/add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    product_id: productId,
                    quantity: 1
                })
            });

            const data = await response.json();

            if (data.success) {
                showToast(data.message, 'success');
                state.cartCount = data.count;
                updateCartCount();
            } else {
                showToast(data.message, 'error');
            }
        } catch (error) {
            console.error('Error adding to cart:', error);
            showToast('Failed to add item to cart', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    // Update counts
    async function updateCounts() {
        try {
            // Get cart count
            const cartResponse = await fetch('/api/cart');
            const cartData = await cartResponse.json();
            state.cartCount = cartData.count || 0;
            updateCartCount();

            // Get wishlist count
            const wishlistResponse = await fetch('/api/wishlist');
            const wishlistData = await wishlistResponse.json();
            state.wishlistCount = wishlistData.count || 0;
            updateWishlistCount();
        } catch (error) {
            console.error('Error updating counts:', error);
        }
    }

    // Update cart count display
    function updateCartCount() {
        if (elements.cartCount) {
            elements.cartCount.textContent = state.cartCount;
            elements.cartCount.style.display = state.cartCount > 0 ? 'block' : 'none';
        }
    }

    // Update wishlist count display
    function updateWishlistCount() {
        if (elements.wishlistCount) {
            elements.wishlistCount.textContent = state.wishlistCount;
            elements.wishlistCount.style.display = state.wishlistCount > 0 ? 'block' : 'none';
        }
    }

    // Show toast notification
    function showToast(message, type = 'info') {
        if (elements.toast) {
            elements.toast.textContent = message;
            elements.toast.className = `toast ${type} show`;

            setTimeout(() => {
                elements.toast.classList.remove('show');
            }, 3000);
        }
    }

    // Start the app
    init();
})();