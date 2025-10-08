<?php

namespace App\Controllers;

use App\Framework\Http\Request;
use App\Services\ProductService;
use App\Services\WishlistService;

class ProductDetailController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly WishlistService $wishlistService
    ) {
        parent::__construct();
    }

    public function show(Request $request, string $slug)
    {
        $product = $this->productService->getProduct($slug);

        if (!$product) {
            return $this->view('errors.404', [
                'message' => 'Product not found'
            ]);
        }

        $this->productService->trackView($product);

        $relatedProducts = $this->productService->getRelatedProducts($product, 8);
        $recentlyViewed = $this->productService->getRecentlyViewedProducts(6);

        $user = auth()->user();
        $isInWishlist = $this->wishlistService->isInWishlist($user, $product);

        return $this->view('products.detail', [
            'product' => $product,
            'relatedProducts' => $relatedProducts,
            'recentlyViewed' => $recentlyViewed,
            'isInWishlist' => $isInWishlist,
            'structuredData' => $this->productService->generateStructuredData($product),
            'menu' => $this->getMenu(),
        ]);
    }

    protected function getMenu(): array
    {
        return [];
    }
}