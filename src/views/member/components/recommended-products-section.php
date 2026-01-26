<?php
/**
 * @var array $recommendedProducts
 */

if (empty($recommendedProducts)) {
    return;
}
?>

<style>
    .products-section {
        background: white;
        border-radius: 1rem;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
        margin-top: 1.5rem;
    }

    .product-card {
        background: white;
        border: 2px solid #e5e7eb;
        border-radius: 0.75rem;
        overflow: hidden;
        transition: all 0.3s ease;
        display: flex;
        flex-direction: column;
        text-decoration: none;
        color: inherit;
    }

    .product-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        border-color: #667eea;
    }

    .product-image-container {
        position: relative;
        width: 100%;
        padding-top: 75%; /* 4:3 aspect ratio */
        background: #f8f9fa;
        overflow: hidden;
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
        top: 0.75rem;
        right: 0.75rem;
        background: #ef4444;
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 0.5rem;
        font-size: 0.75rem;
        font-weight: 700;
    }

    .product-content {
        padding: 1.25rem;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .product-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: #1a202c;
        margin-bottom: 0.5rem;
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .product-description {
        font-size: 0.875rem;
        color: #6b7280;
        margin-bottom: 1rem;
        line-height: 1.5;
        flex: 1;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .product-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 1rem;
        border-top: 1px solid #e5e7eb;
    }

    .product-price {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .price-current {
        font-size: 1.5rem;
        font-weight: 700;
        color: #667eea;
    }

    .price-original {
        font-size: 0.875rem;
        color: #9ca3af;
        text-decoration: line-through;
    }

    .product-cta {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 0.5rem;
        font-weight: 600;
        font-size: 0.875rem;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .product-cta:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    @media (max-width: 768px) {
        .products-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="products-section">
    <h2 class="section-title">Recommended for You</h2>
    <p style="color: #6b7280; margin-bottom: 1rem;">Curated products based on your interests</p>

    <div class="products-grid">
        <?php foreach ($recommendedProducts as $product): ?>
            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/shop/details/<?= htmlspecialchars($product['slug']) ?>"
               class="product-card">
                <div class="product-image-container">
                    <?php if (!empty($product['image'])): ?>
                        <img src="<?= htmlspecialchars($product['image']) ?>"
                             alt="<?= htmlspecialchars($product['name']) ?>"
                             class="product-image"
                             loading="lazy">
                    <?php else: ?>
                        <div class="product-image"
                             style="display: flex; align-items: center; justify-content: center; background: #e5e7eb; color: #9ca3af;">
                            📦
                        </div>
                    <?php endif; ?>

                    <?php if ($product['has_discount']): ?>
                        <div class="product-badge">
                            -<?= $product['discount_percentage'] ?>%
                        </div>
                    <?php endif; ?>
                </div>

                <div class="product-content">
                    <h3 class="product-title"><?= htmlspecialchars($product['name']) ?></h3>

                    <?php if (!empty($product['description'])): ?>
                        <p class="product-description"><?= htmlspecialchars($product['description']) ?></p>
                    <?php endif; ?>

                    <div class="product-footer">
                        <div class="product-price">
                            <?php if ($product['has_discount']): ?>
                                <span class="price-current">
                                    $<?= number_format($product['sale_price'], 2) ?>
                                </span>
                                <span class="price-original">
                                    $<?= number_format($product['price'], 2) ?>
                                </span>
                            <?php else: ?>
                                <span class="price-current">
                                    $<?= number_format($product['price'], 2) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <button class="product-cta"
                                onclick="event.preventDefault(); window.location.href='<?= \App\Framework\Support\SiteContext::slug() ?>/shop/details/<?= htmlspecialchars($product['slug']) ?>'">
                            Buy Now →
                        </button>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>