<style>

    /* Spotlight Card Styling (New/Featured) */
    .spotlight-scroll-wrapper {
        display: flex;
        overflow-x: auto;
        gap: 1.5rem;
        padding-bottom: 1rem;
        scrollbar-width: thin;
    }

    .spotlight-card {
        min-width: 240px;
        background: white;
        border-radius: 8px;
        overflow: hidden;
        transition: transform 0.3s ease;
    }

    .spotlight-card:hover {
        transform: translateY(-5px);
    }

    .spotlight-img-container {
        position: relative;
        overflow: hidden;
    }

    .spotlight-img-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .stock-tag {
        position: absolute;
        top: 8px;
        right: 8px;
        background: #10b981;
        color: white;
        font-size: 0.7rem;
        padding: 2px 8px;
        border-radius: 4px;
    }

    /* Container and Header */
    .product-spotlight-section {
        margin: 3rem 0;
        padding: 1.5rem;
        background: #f8fafc;
        border-radius: 16px;
    }

    .spotlight-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }

    .spotlight-title {
        font-size: 1.5rem;
        font-weight: 800;
        color: #1e293b;
        margin: 0;
    }

    /* Theme Variation Badges */
    .spotlight-badge {
        padding: 0.4rem 1rem;
        border-radius: 99px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .theme-new .spotlight-badge {
        background: #dcfce7;
        color: #166534;
    }

    .theme-featured .spotlight-badge {
        background: #fef3c7;
        color: #92400e;
    }

    /* Scrollable Grid */
    .spotlight-scroll-wrapper {
        display: flex;
        gap: 1.25rem;
        overflow-x: auto;
        padding: 0.5rem 0.5rem 1.5rem 0.5rem;
        scroll-snap-type: x mandatory;
        scrollbar-width: none; /* Firefox */
    }

    .spotlight-scroll-wrapper::-webkit-scrollbar {
        display: none; /* Chrome/Safari */
    }

    /* Product Card */
    .spotlight-card {
        flex: 0 0 260px;
        scroll-snap-align: start;
        background: #ffffff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid #e2e8f0;
    }

    .spotlight-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }

    .spotlight-img-container {
        position: relative;
        height: 200px;
        background: #f1f5f9;
    }

    .spotlight-img-container img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        padding: 1rem;
    }

    /* Pricing Overlay */
    .spotlight-pricing {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
        padding: 0; /* Remove the overlay padding */
        background: transparent; /* Remove the glass effect */
        border: none;
    }

    /* Content Area */
    .spotlight-info {
        padding: 1.25rem;
    }

    .spotlight-name {
        font-size: 1rem;
        font-weight: 600;
        color: #334155;
        margin: 0 0 1rem 0;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        height: 2.5rem;
    }

    .spotlight-link {
        display: block;
        text-align: center;
        background: #2563eb;
        color: white;
        text-decoration: none;
        padding: 0.6rem;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 600;
        transition: background 0.2s;
    }

    .spotlight-link:hover {
        background: #1d4ed8;
    }

    .current-price {
        font-size: 1.25rem;
        font-weight: 800;
        color: #111827;
    }

    .original-price {
        font-size: 0.875rem;
        text-decoration: line-through;
        color: #9ca3af;
    }

    /* 3. Add a Save Badge inside the info area */
    .save-badge {
        background: #fee2e2;
        color: #ef4444;
        font-size: 0.7rem;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 4px;
    }

    .spotlight-actions-overlay {
        position: absolute;
        top: 10px;
        right: 10px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .spotlight-card:hover .spotlight-actions-overlay {
        opacity: 1;
    }

    .spotlight-btn-wishlist {
        background: white;
        border: none;
        border-radius: 50%;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        color: #64748b;
        transition: all 0.2s;
    }

    .spotlight-btn-wishlist:hover {
        color: #ef4444;
        transform: scale(1.1);
    }

    .spotlight-btn-wishlist.active {
        background: #ef4444;
        color: white;
    }

    /* Add to Cart Button */
    .spotlight-btn-cart {
        width: 100%;
        background: #2563eb;
        color: white;
        border: none;
        padding: 10px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s, transform 0.1s;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-top: 10px;
    }

    .spotlight-btn-cart:hover {
        background: #1d4ed8;
    }

    .spotlight-btn-cart:active {
        transform: scale(0.98);
    }

    .spotlight-btn-cart:disabled {
        background: #94a3b8;
        cursor: not-allowed;
    }

    /* Name Link Styling */
    .spotlight-name a {
        text-decoration: none;
        color: inherit;
        transition: color 0.2s;
    }

    .spotlight-name a:hover {
        color: #2563eb;
    }
</style>

<div class="product-spotlight-section">
    <div class="spotlight-header">
        <h3 class="spotlight-title"><?= $title ?></h3>
        <div class="spotlight-badge"><?= $badgeText ?></div>
    </div>

    <div class="spotlight-scroll-wrapper">
        <?php foreach ($products as $product): ?>
            <div class="spotlight-card" data-product-id="<?= $product['id'] ?>">
                <div class="spotlight-img-container">
                    <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/shop/details/<?= $product['slug'] ?>"
                       class="spotlight-img-link">
                        <img src="<?= htmlspecialchars($product['image']) ?>"
                             alt="<?= htmlspecialchars($product['name']) ?>">
                    </a>

                    <div class="spotlight-actions-overlay">
                        <button class="spotlight-btn-wishlist"
                                onclick="handleCategoryProductWishlist(event, <?= $product['id'] ?>)"
                                title="Add to Wishlist">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="spotlight-info">
                    <div class="spotlight-pricing">
                        <span class="current-price">$<?= number_format($product['sale_price'] ?: $product['price'], 2) ?></span>
                        <?php if ($product['sale_price']): ?>
                            <span class="original-price">$<?= number_format($product['price'], 2) ?></span>
                        <?php endif; ?>
                    </div>

                    <h4 class="spotlight-name">
                        <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/shop/details/<?= $product['slug'] ?>"><?= htmlspecialchars($product['name']) ?></a>
                    </h4>

                    <div class="spotlight-button-group">
                        <button class="spotlight-btn-cart"
                                onclick="handleCategoryProductAddToCart(event, <?= $product['id'] ?>)">
                            Add to Cart
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>