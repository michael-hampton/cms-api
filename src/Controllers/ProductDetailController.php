<?php

namespace App\Controllers;

use App\Framework\Http\Request;
use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Services\ProductService;
use App\Services\ReviewService;
use App\Services\WishlistService;

class ProductDetailController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly WishlistService $wishlistService,
        private readonly ProductRepository $productRepository
    ) {
        parent::__construct();
    }

    public function show(Request $request, string $slug)
    {
        $product = Product::with(['specifications', 'availableMerchants', 'priceHistory', 'activeVariants', 'category', 'brand'])
            ->where('slug', $slug)
            ->first();

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

        // Get review data
        $reviewService = app(ReviewService::class);
        $reviewData = $reviewService->getProductReviews($product->id, 1, 5); // First 5 reviews
        $reviewStats = $reviewService->getReviewStatistics($product->id);
        $canReview = $reviewService->canUserReview($product->id);

        return $this->view('products.detail', [
            'product' => $product,
            'relatedProducts' => $relatedProducts,
            'recentlyViewed' => $recentlyViewed,
            'isInWishlist' => $isInWishlist,
            'structuredData' => $this->productService->generateStructuredData($product),
            'reviewStats' => $reviewStats,
            'canReview' => ['can_review' => true],
            'reviewData' => $reviewData,
            'menu' => $this->getMenu(),
        ]);
    }

    protected function getMenu(): array
    {
        return [];
    }
}