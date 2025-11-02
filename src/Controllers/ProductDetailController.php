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
        $product = Product::with(['specifications', 'availableMerchants', 'priceHistory', 'activeVariants', 'category', 'brand', 'availableMerchants.merchant'])
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

        $merchantsArray = array_map(function($m) use ($product) {
            $variant = null;
            if ($m['variant_id']) {
                $variant = $product->activeVariants->firstWhere('id', $m['variant_id']);
            }
            return [
                'id' => $m['id'],
                'merchant_id' => $m['merchant_id'],
                'name' => $m['merchant']['name'] ?? $m['name'],
                'url' => $m['url'],
                'variant_id' => $m['variant_id'],
                'effective_price' => $m['effective_price'],
                'effective_sale_price' => $m['effective_sale_price'],
                'is_available' => $m['is_available'],
                'discount_percentage' => $m['discount_percentage']
            ];
        }, $product->availableMerchants->toArray() ?? []);

        // Prepare price history timeline data
        $priceHistoryTimeline = [];
        if ($product->priceHistory && $product->priceHistory->count() > 0) {
            $priceHistory = $product->priceHistory->toArray();

            foreach (array_slice($priceHistory, 0, 10) as $index => $history) {
                $historyPrice = $history['sale_price'] ?? $history['price'];
                $isIncrease = false;
                $isDecrease = false;
                $change = 0;
                $prevPrice = null;

                if ($index > 0) {
                    $prevPrice = $priceHistory[$index - 1]['sale_price'] ?? $priceHistory[$index - 1]['price'];
                    $change = $historyPrice - $prevPrice;
                    $isIncrease = $change > 0;
                    $isDecrease = $change < 0;
                }

                $priceHistoryTimeline[] = [
                    'date' => $history['recorded_at'],
                    'price' => $historyPrice,
                    'isIncrease' => $isIncrease,
                    'isDecrease' => $isDecrease,
                    'change' => $change,
                    'merchant' => $history['merchant'] ?? null,
                ];
            }
        }

        return $this->view('products.detail', [
            'product' => $product,
            'relatedProducts' => $relatedProducts,
            'recentlyViewed' => $recentlyViewed,
            'isInWishlist' => $isInWishlist,
            'structuredData' => $this->productService->generateStructuredData($product),
            'reviewStats' => $reviewStats,
            'canReview' => ['can_review' => true],
            'reviewData' => $reviewData,
            'merchantsArray' => $merchantsArray,
            'menu' => $this->getMenu(),
            'priceHistoryTimeline' => $priceHistoryTimeline,
        ]);
    }

    protected function getMenu(): array
    {
        return [];
    }
}