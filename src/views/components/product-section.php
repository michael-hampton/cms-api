<!-- Page Products / buying-guide product presentation -->
<?php
$productsDegraded = !empty($productsDegraded);
$productsEmpty = !empty($productsEmpty);
$productsSourceStub = !empty($productsSourceStub);
$hasProducts = $page->products && count($page->products) > 0;
?>
<?php if ($hasProducts): ?>
    <div class="page-products-section" data-products-source-stub="<?= $productsSourceStub ? 'true' : 'false' ?>">
        <h2 class="section-title">Recommended products</h2>
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
        .page-products-section .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
        }
    </style>
    @js('product-interactions.js')
    @css('products.css')
<?php elseif ($productsDegraded): ?>
    <div class="page-products-section page-products-section--degraded"
         data-degraded="true"
         data-products-source-stub="<?= $productsSourceStub ? 'true' : 'false' ?>"
         aria-label="Recommended products unavailable">
        <h2 class="section-title">Recommended products</h2>
        <p class="page-products-section__degraded-copy">
            Live product recommendations are unavailable right now. The rest of this buying guide is still available.
        </p>
    </div>
    <style>
        .page-products-section--degraded {
            margin-top: 3rem;
            padding-top: 3rem;
            border-top: 1px solid #e5e7eb;
        }
        .page-products-section--degraded .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: #1e293b;
        }
        .page-products-section__degraded-copy {
            color: #64748b;
            margin: 0;
        }
    </style>
<?php elseif ($productsEmpty): ?>
    <div class="page-products-section page-products-section--empty"
         data-empty="true"
         data-products-source-stub="<?= $productsSourceStub ? 'true' : 'false' ?>"
         aria-label="No recommended products">
        <h2 class="section-title">Recommended products</h2>
        <p class="page-products-section__empty-copy">
            No products have been added to this buying guide yet.
        </p>
    </div>
    <style>
        .page-products-section--empty {
            margin-top: 3rem;
            padding-top: 3rem;
            border-top: 1px solid #e5e7eb;
        }
        .page-products-section--empty .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: #1e293b;
        }
        .page-products-section__empty-copy {
            color: #64748b;
            margin: 0;
        }
    </style>
<?php endif; ?>
