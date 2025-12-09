// Shared product interaction functionality
(function() {
    'use strict';

    function attachProductListeners() {
        // Add to cart
        document.querySelectorAll('.btn-add-to-cart').forEach(btn => {
            if (!btn.hasListener) {
                btn.addEventListener('click', handleAddToCart);
                btn.hasListener = true;
            }
        });

        // Wishlist toggle
        /*document.querySelectorAll('.btn-wishlist').forEach(btn => {
            if (!btn.hasListener) {
                btn.addEventListener('click', handleToggleWishlist);
                btn.hasListener = true;
            }
        });*/
    }

    async function handleAddToCart(e) {
        const btn = e.currentTarget;
        const productId = btn.dataset.productId;
        const originalText = btn.textContent;

        btn.disabled = true;
        btn.textContent = 'Adding...';

        try {
            const site = window.SITE || 'default';
            const response = await fetch(`/api/${site}/cart/add`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: productId, quantity: 1 })
            });

            const data = await response.json();

            if (data.success) {
                showToast(data.message, 'success');
                updateCartCount(data.count);
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

    async function handleToggleWishlist(e) {
        const btn = e.currentTarget;
        console.log('btn', btn)
        const productId = btn.dataset.productId;
        const isInWishlist = btn.classList.contains('active');

        alert(productId + ' ' + isInWishlist)

        btn.disabled = true;

        try {
            const site = window.SITE || 'default';
            const url = isInWishlist
                ? `/api/${site}/wishlist/remove/${productId}`
                : `/api/${site}/wishlist/add`;

            const response = await fetch(url, {
                method: isInWishlist ? 'DELETE' : 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: isInWishlist ? null : JSON.stringify({ product_id: productId })
            });

            const data = await response.json();

            if (data.success) {
                btn.classList.toggle('active');
                showToast(data.message, 'success');
                updateWishlistCount(data.count);
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

    function updateCartCount(count) {
        const el = document.getElementById('cart-count');
        if (el) {
            el.textContent = count;
            el.style.display = count > 0 ? 'block' : 'none';
        }
    }

    function updateWishlistCount(count) {
        const el = document.getElementById('wishlist-count');
        if (el) {
            el.textContent = count;
            el.style.display = count > 0 ? 'block' : 'none';
        }
    }

    function showToast(message, type = 'info') {
        let toast = document.getElementById('toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'toast';
            toast.className = 'toast';
            document.body.appendChild(toast);
        }

        toast.textContent = message;
        toast.className = `toast ${type} show`;

        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }

    // Initialize on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attachProductListeners);
    } else {
        attachProductListeners();
    }

    // Export for dynamic content
    window.attachProductListeners = attachProductListeners;
})();