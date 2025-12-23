<!-- Page Products -->
<?php if ($page->products && count($page->products) > 0): ?>
    <div class="page-products-section">
        <h2 class="section-title">Related Products</h2>
        <div class="products-grid">
            <?php foreach ($page->products as $product): ?>
                <?php include __DIR__ . '/../../views/components/product-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <style>
        .page-products-section {
            margin-top: 3rem;
            padding-top: 3rem;
            border-top: 1px solid #e5e7eb;
        }
        .page-products-section .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: #1e293b;
        }
        /* Reuse product grid styles */
        .page-products-section .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
        }
    </style>
    @js('product-interactions.js')
    @css('products.css')
<?php endif; ?>

