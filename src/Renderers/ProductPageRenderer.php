<?php

namespace App\Renderers;

use App\Framework\Http\Request;
use App\Models\Page;
use App\Services\Url\UrlResolutionResult;

class ProductPageRenderer extends BasePageRenderer
{
    public function __construct(
        protected readonly Request $request,
        private readonly ProductService $productService
    ) {
        parent::__construct($request);
    }

    protected function renderPage(Page $page, UrlResolutionResult $result): mixed
    {
        $product = $this->productService->getProduct($page->product_id);

        $data = array_merge($this->getBaseViewData($page, $result), [
            'product' => $product,
            'variants' => $this->productService->getVariants($product->id),
            'reviews' => $this->productService->getReviews($product->id),
            'related_products' => $this->productService->getRelatedProducts($product->id),
            'json_ld' => $this->productService->getStructuredData($product),
        ]);

        return view($page->template ?: 'products.show', $data);
    }
}