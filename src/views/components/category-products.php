<?php
/**
 * Category Products Component
 * Displays products belonging to the category
 *
 * @var array $products - Array of product objects
 * @var object $category - Category object
 */

$productCount = count($products ?? []);
$hasProducts = $productCount > 0;
?>

@css('category-products.css')

<section class="category-products-section">
    <div class="category-products-header">
        <div>
            <h2 class="category-products-title">Products in <?= htmlspecialchars($category->name ?? 'Category') ?></h2>
            <?php if ($hasProducts): ?>
                <p class="category-products-subtitle">Showing <?= $productCount ?>
                    product<?= $productCount !== 1 ? 's' : '' ?></p>
            <?php endif; ?>
        </div>
        <?php if ($hasProducts): ?>
            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/shop?category_ids=<?= $category->id ?>"
               class="view-all-products-btn">
                View All Products
            </a>
        <?php endif; ?>
    </div>

    <?php if ($hasProducts): ?>
        <div class="category-products-grid" id="category-products-grid">
            <?php foreach ($products as $product):
                ?>
                <div class="category-product-card" data-product-id="<?= $product['id'] ?>">
                    <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/shop/details/<?= htmlspecialchars($product['slug']) ?>"
                       class="category-product-image">
                        <img src="<?= htmlspecialchars($product['image'] ?? '/images/placeholder.jpg') ?>"
                             alt="<?= htmlspecialchars($product['name']) ?>">
                        <?php if ($product['discount_percentage'] > 0): ?>
                            <span class="category-product-badge-sale">-<?= $product['discount_percentage'] ?>%</span>
                        <?php endif; ?>
                    </a>

                    <div class="category-product-content">
                        <h3 class="category-product-name">
                            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/shop/details/<?= htmlspecialchars($product['slug']) ?>">
                                <?= htmlspecialchars($product['name']) ?>
                            </a>
                        </h3>

                        <?php if (isset($product['average_rating']) && $product['average_rating'] > 0): ?>
                            <div class="category-product-rating">
                                <div class="category-rating-stars">
                                    <?php
                                    $rating = $product['average_rating'];
                                    for ($i = 1; $i <= 5; $i++):
                                        $filled = $i <= floor($rating);
                                        ?>
                                        <svg class="category-rating-star <?= $filled ? 'filled' : 'empty' ?>"
                                             viewBox="0 0 24 24">
                                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                        </svg>
                                    <?php endfor; ?>
                                </div>
                                <span class="category-rating-count">(<?= $product['review_count'] ?? 0 ?>)</span>
                            </div>
                        <?php endif; ?>

                        <div class="category-product-price">
                            <?php if (isset($product['sale_price']) && $product['sale_price'] > 0 && $product['sale_price'] < $product['price']): ?>
                                <span class="category-price-sale">$<?= number_format($product['sale_price'], 2) ?></span>
                                <span class="category-price-original">$<?= number_format($product['price'], 2) ?></span>
                            <?php else: ?>
                                <span class="category-price-current">$<?= number_format($product['price'], 2) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="category-product-actions">
                            <button class="category-btn-add-to-cart"
                                    data-product-id="<?= $product['id'] ?>"
                                    onclick="handleCategoryProductAddToCart(event, <?= $product['id'] ?>)">
                                Add to Cart
                            </button>
                            <button class="category-btn-wishlist"
                                    data-product-id="<?= $product['id'] ?>"
                                    onclick="handleCategoryProductWishlist(event, <?= $product['id'] ?>)">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="category-products-empty">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
            <h3>No Products Yet</h3>
            <p>No products available in this category at the moment.</p>
        </div>
    <?php endif; ?>
</section>

<script>
    // Add to Cart Handler
    async function handleCategoryProductAddToCart(event, productId) {
        event.preventDefault();
        const btn = event.currentTarget;
        const originalText = btn.textContent;

        btn.disabled = true;
        btn.textContent = 'Adding...';

        try {
            const response = await fetch(`/api/${SITE}/cart/add`, {
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
                showCategoryToast(data.message || 'Added to cart!', 'success');
                // Update cart count if element exists
                const cartCount = document.getElementById('cart-count');
                if (cartCount && data.count) {
                    cartCount.textContent = data.count;
                    cartCount.style.display = 'block';
                }
            } else {
                showCategoryToast(data.message || 'Failed to add to cart', 'error');
            }
        } catch (error) {
            console.error('Error adding to cart:', error);
            showCategoryToast('Failed to add item to cart', 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    // Wishlist Handler
    async function handleCategoryProductWishlist(event, productId) {
        event.preventDefault();
        const btn = event.currentTarget;
        const isInWishlist = btn.classList.contains('active');

        btn.disabled = true;

        try {
            const url = isInWishlist
                ? `/api/${SITE}/wishlist/remove/${productId}`
                : `/api/${SITE}/wishlist/add`;

            const response = await fetch(url, {
                method: isInWishlist ? 'DELETE' : 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: isInWishlist ? null : JSON.stringify({product_id: productId})
            });

            const data = await response.json();

            if (data.success) {
                btn.classList.toggle('active');
                showCategoryToast(data.message, 'success');

                // Update wishlist count if element exists
                const wishlistCount = document.getElementById('wishlist-count');
                if (wishlistCount && data.count !== undefined) {
                    wishlistCount.textContent = data.count;
                    wishlistCount.style.display = data.count > 0 ? 'block' : 'none';
                }
            } else {
                showCategoryToast(data.message || 'Failed to update wishlist', 'error');
            }
        } catch (error) {
            console.error('Error toggling wishlist:', error);
            showCategoryToast('An error occurred', 'error');
        } finally {
            btn.disabled = false;
        }
    }

    // Toast notification
    function showCategoryToast(message, type = 'info') {
        // Check if toast element exists, if not create it
        let toast = document.getElementById('category-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'category-toast';
            toast.className = 'toast';
            document.body.appendChild(toast);
        }

        toast.textContent = message;
        toast.className = `toast ${type} show`;

        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }
</script>