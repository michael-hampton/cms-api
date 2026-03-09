<?php

namespace App\Controllers\Shopping;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\Menu;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\ProductOfferBundle;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Product\ProductViewRepository;
use App\Services\Cms\MenuRenderer;
use App\Services\Currency\CurrencyResolver;
use App\Services\Product\ProductSchemaService;
use App\Services\Product\ProductService;
use App\Services\Reviews\ReviewService;
use App\Services\Shopping\WishlistService;
use Exception;

class ProductDetailController extends Controller
{
    public function __construct(
        private readonly ProductService       $productService,
        private readonly WishlistService      $wishlistService,
        private readonly ProductRepository     $productRepository,
        private readonly ProductViewRepository $productViewRepository,
        private readonly ProductSchemaService $productSchemaService,
        private readonly ReviewService        $reviewService,
        private readonly CurrencyResolver     $currencyResolver,
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

        $menu = Menu::where('is_active', true)
            ->where('site_id', SiteContext::getId())
            ->where('menu_type', 'header')
            ->with(['items'])
            ->first();

        $this->productService->trackView($product);

        $member = MemberAuth::getMember();

        $relatedProducts = $this->productService->getRelatedProducts($product, 8);
        $recentlyViewed = MemberAuth::check() ? $this->productViewRepository->getViewedProductsByMember($member->id, 6) : [];

        $user = MemberAuth::getMember();
        $isInWishlist = $this->wishlistService->isInWishlist($user, $product);

        $reviewData = $this->reviewService->getProductReviews($product->id, 1, 5);
        $reviewStats = $this->reviewService->getReviewStatistics($product->id);
        $canReview = $this->reviewService->canUserReview($product->id);

        // Currency is resolved from the site context; individual product prices
        // are stored in the site's base currency, but regional overrides (e.g.
        // GBP vs USD) come from the site config rather than the product itself.
        $currencyCode = $this->currencyResolver->resolveUpperCase();
        $currencySymbol = $this->currencyResolver->symbol($currencyCode);

        $merchantsArray = array_map(function ($m) use ($product, $currencySymbol, $currencyCode) {
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
                'discount_percentage' => $m['discount_percentage'],
                'currencyCode' => $currencyCode,
                'currencySymbol' => $currencySymbol,
            ];
        }, $product->availableMerchants->toArray() ?? []);

        $priceHistoryTimeline = [];
        if ($product->priceHistory && $product->priceHistory->count() > 0) {
            $priceHistory = $product->priceHistory->toArray();

            foreach (array_slice($priceHistory, 0, 10) as $index => $history) {
                $historyPrice = $history['sale_price'] ?? $history['price'];
                $isIncrease = false;
                $isDecrease = false;
                $change = 0;

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
            'structuredData' => $this->productSchemaService->generateStructuredData($product),
            'reviewStats' => $reviewStats,
            'canReview' => ['can_review' => true],
            'reviewData' => $reviewData,
            'merchantsArray' => $merchantsArray,
            'menu' => $menu,
            'menuRenderer' => new MenuRenderer(),
            'priceHistoryTimeline' => $priceHistoryTimeline,
            'member' => $user,
            'currencyCode' => $currencyCode,
            'currencySymbol' => $currencySymbol,
        ]);
    }

    public function getProductOffers(int $productId, string $siteName): JsonResponse
    {
        try {
            $currencyCode = $this->currencyResolver->resolveUpperCase();
            $currencySymbol = $this->currencyResolver->symbol($currencyCode);

            $offers = ProductOffer::with(['merchant'])
                ->where('product_id', $productId)
                ->where('status', 'published')
                ->where('is_active', true)
                ->orderBy('sale_price', 'asc')
                ->get();

            return $this->jsonResponse([
                'success' => true,
                'offers' => $offers->toArray(),
                'currency_code' => $currencyCode,
                'currency_symbol' => $currencySymbol,
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getProductBundles(int $productId, string $siteName): JsonResponse
    {
        try {
            $bundles = ProductOfferBundle::with([
                'items.product',
                'items.productOffer.product'
            ])
                ->whereHas('items', function ($query) use ($productId) {
                    $query->where('product_id', $productId)
                        ->orWhereHas('productOffer', function ($q) use ($productId) {
                            $q->where('product_id', $productId);
                        });
                })
                ->where('status', 'published')
                ->where('is_active', true)
                ->get();

            return $this->jsonResponse([
                'success' => true,
                'bundles' => $bundles->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    protected function getMenu(): array
    {
        return [];
    }
}