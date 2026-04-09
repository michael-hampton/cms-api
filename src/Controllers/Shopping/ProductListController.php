<?php

namespace App\Controllers\Shopping;

use App\Controllers\Controller;
use App\Enums\Boost\BoostContext;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Session\Session;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Models\Product;
use App\Models\ProductImpression;
use App\Repositories\Adverts\Boost\BoostRepository;
use App\Repositories\Cms\BrandRepository;
use App\Repositories\Cms\CategoryRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Product\ProductSpecificationGroupRepository;
use App\Repositories\Product\ProductViewRepository;
use App\Repositories\ReviewRepository;
use App\Repositories\Shopping\CartRepository;
use App\Repositories\Shopping\WishlistRepository;
use App\Search\SearchCriteria;
use App\Services\Adverts\Boost\BoostEventService;
use App\Services\Adverts\Boost\BoostRankingService;
use App\Services\Cms\MenuRenderer;
use App\Services\Currency\CurrencyResolver;
use App\Services\Product\BuildProductCardService;
use App\Services\Product\FilterInputSanitiser;
use App\Services\Product\ProductService;

class ProductListController extends Controller
{
    public function __construct(
        private readonly ProductService                      $productService,
        private readonly ProductRepository                   $productRepository,
        private readonly ReviewRepository                    $reviewRepository,
        private readonly ProductViewRepository               $productViewRepository,
        private readonly CategoryRepository                  $categoryRepository,
        private readonly BrandRepository                     $brandRepository,
        private readonly ProductSpecificationGroupRepository $specRepository,
        private readonly BoostRankingService                 $boostRankingService,
        private readonly BoostEventService                   $boostEventService,
        private readonly BoostRepository                     $boostRepository,
        private readonly CurrencyResolver                    $currencyResolver,
        private readonly FilterInputSanitiser                $inputSanitiser,
        private readonly WishlistRepository $wishlistRepository,
        private readonly CartRepository     $cartRepository,
    )
    {
        parent::__construct();
    }

    public function index(Request $request)
    {
        $siteId = SiteContext::getId();
        $userId = MemberAuth::id() ?? null;
        $sessionId = Session::get('cart_session_id');

        $categories = $this->categoryRepository->getAllWithProductCounts($siteId);
        $brands = $this->brandRepository->getAllWithProductCounts($siteId);
        $specificationGroups = $this->specRepository->getAllWithCounts($siteId);

        $menu = $this->resolveHeaderMenu($siteId);

        $currencyCode = $this->currencyResolver->resolveUpperCase();
        $currencySymbol = $this->currencyResolver->symbol($currencyCode);

        // Resolve cart and wishlist state once on the server so the page
        // doesn't need extra API calls just to seed the initial badge counts
        // and button active-states.  The view renders these into INITIAL_DATA
        // which products.js picks up via seedInitialState().
        $wishlistProductIds = $this->wishlistRepository
            ->getProductIdsBySessionOrUser($userId, $sessionId);

        $cartProductIds = $this->cartRepository
            ->findBySessionOrUser($userId, $sessionId)
            ->pluck('product_id')
            ->all();

        return $this->view('products.index', [
            'categories' => $categories->toArray(),
            'brands' => $brands->toArray(),
            'menu' => $menu,
            'menuRenderer' => new MenuRenderer(),
            'specificationGroups' => $specificationGroups->toArray(),
            'currencyCode' => $currencyCode,
            'currencySymbol' => $currencySymbol,
            // Passed to the view for INITIAL_DATA injection
            'wishlistCount' => count($wishlistProductIds),
            'wishlistProductIds' => $wishlistProductIds,
            'cartCount' => $this->cartRepository->getCountBySessionOrUser($userId, $sessionId),
            'cartProductIds' => $cartProductIds,
        ]);
    }

    public function search(Request $request)
    {
        $input = $this->inputSanitiser->sanitise($request->all());

        $filters = array_filter([
            'categories' => $input['category_ids'],
            'brands' => $input['brand_ids'],
            'specifications' => $input['spec_ids'],
            'on_sale' => $input['on_sale'] ?: null,
            'min_rating' => $input['min_rating'],
            'min_discount' => $input['min_discount'],
            'has_voucher' => $input['has_voucher'] ?: null,
        ], static fn($value) => $value !== null && $value !== [] && $value !== '');

        $criteria = new SearchCriteria(
            filters: $filters,
            sortBy: $input['sort_by'],
            sortOrder: $input['sort_order'],
            page: $input['page'],
            perPage: $input['per_page'],
            searchQuery: $input['search'],
        );

        $result = $this->productRepository->search($criteria);
        $boostContext = $request->input('boost_context', BoostContext::Listing->value);

        try {
            $rankedData = $this->boostRankingService->applyRanking(
                collect($result->getData()),
                $boostContext
            );

            $productIds = collect($result->getData())->pluck('id')->toArray();

            foreach ($rankedData ?? collect([]) as $item) {
                if (!empty($item['boost_id'])) {
                    $sessionHash = hash('sha256', $request->ip() . $request->header('User-Agent'));
                    $this->boostEventService->recordImpression(
                        $item['boost_id'],
                        $sessionHash,
                        ['context' => $boostContext]
                    );
                }
            }

            $now = now();
            $rows = array_map(fn($id) => [
                'product_id' => $id,
                'context' => $boostContext,
                'viewed_at' => $now,
            ], $productIds);

            ProductImpression::insert($rows);
            $result->setData($rankedData->all());
        } catch (\Exception $e) {
            Logger::error('Boost ranking failed in product search', ['error' => $e->getMessage()]);
        }

        // Price range filtering — applied after ranking so boosted items aren't disrupted
        if ($input['min_price'] !== null || $input['max_price'] !== null) {
            $filtered = collect($result->getData())->filter(function ($product) use ($input) {
                $price = ($product['sale_price'] ?? 0) > 0
                    ? $product['sale_price']
                    : $product['price'];

                return ($input['min_price'] === null || $price >= $input['min_price'])
                    && ($input['max_price'] === null || $price <= $input['max_price']);
            });

            $result->setData($filtered->values()->all());
        }

        $productIds = collect($result->getData())->pluck('id')->unique()->toArray();
        $topReviews = $this->reviewRepository->getTopReview($productIds)->keyBy('product_id');
        $rankedIds = collect($result->getData())
            ->filter(fn($item) => $item['is_boosted'] ?? false)
            ->pluck('id')
            ->flip()
            ->toArray();

        $formattedProducts = collect($result->toArray()['data'])->map(function ($product) use ($topReviews, $rankedIds) {
            $reviews = $product['approvedReviews'] ?? [];
            $merchants = $product['availableMerchants'] ?? [];

            $averageRating = count($reviews)
                ? array_sum(array_column($reviews, 'rating')) / count($reviews)
                : 0;

            $lowestMerchantPrice = count($merchants)
                ? min(array_map(
                    fn($m) => $m['effective_sale_price'] ?? $m['effective_price'],
                    $merchants
                ))
                : 0;

            $topReview = $topReviews->get($product['id']);

            return array_merge($product, [
                'average_rating' => $averageRating,
                'review_count' => count($reviews),
                'merchant_count' => count($merchants),
                'top_review' => $topReview?->toArray(),
                'lowest_merchant_price' => $lowestMerchantPrice,
                'is_boosted' => isset($rankedIds[$product['id']]),
            ]);
        });

        $result->setData($formattedProducts->toArray());

        return $this->resourceResponse($result->toArray());
    }

    public function getProductDetails(Request $request, mixed $id)
    {
        $safeId = $this->inputSanitiser->sanitiseId($id);

        if ($safeId === null) {
            return $this->jsonResponse(['success' => false, 'message' => 'Invalid product ID'], 422);
        }

        try {
            $productData = (new BuildProductCardService())->build($safeId);

            return $this->resourceResponse(['success' => true, 'product' => $productData]);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Error fetching product details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getProductModal(Request $request, mixed $id)
    {
        $safeId = $this->inputSanitiser->sanitiseId($id);

        if ($safeId === null) {
            return $this->jsonResponse(['success' => false, 'message' => 'Invalid product ID'], 422);
        }

        try {
            $product = $this->productRepository->find($safeId);

            if (!$product) {
                return $this->jsonResponse(['success' => false, 'message' => 'Product not found'], 404);
            }

            $this->trackProductView($request, $product, $safeId);
            $this->recordBoostImpression($request, $product);

            $product->load([
                'images',
                'activeVariants.images',
                'activeVariants.merchants.merchant',
                'availableMerchants.merchant',
                'brand',
                'category',
                'approvedReviews',
                'specifications.group',
            ]);

            $priceHistory = $this->productRepository->getPriceHistory($product->id)
                ->filter(fn($h) => $h->recorded_at >= now_datetime()->subDays(90))
                ->sortBy('recorded_at')
                ->map(fn($h) => ['price' => (float)$h->price, 'recorded_at' => $h->recorded_at])
                ->values()
                ->toArray();

            $relatedProducts = $this->productRepository->findRelated($product, 6);

            $similarItems = Product::where('category_id', $product->category_id)
                ->where('id', '!=', $product->id)
                ->where('is_active', true)
                ->limit(6)
                ->get();

            $productData = $this->formatProductModalData($product);
            $productData['price_history'] = $priceHistory;
            $productData['price_comparison'] = $this->buildPriceComparison($product);

            return $this->resourceResponse([
                'success' => true,
                'product' => $productData,
                'related_products' => $relatedProducts->map(fn($p) => $this->formatProductCard($p))->toArray(),
                'similar_items' => $similarItems->map(fn($p) => $this->formatProductCard($p))->toArray(),
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Error fetching product details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function resolveHeaderMenu(int $siteId)
    {
        // Ideally injected via MenuRepository — flagging for future extraction
        return \App\Models\Menu::where('is_active', true)
            ->where('site_id', $siteId)
            ->where('menu_type', 'header')
            ->with(['items'])
            ->first();
    }

    private function trackProductView(Request $request, mixed $product, int $id): void
    {
        $userId = auth()->user()?->id ?? null;
        $sessionId = session_id() ?: $request->session()->getId();
        $ipAddress = $request->ip();

        if (!$userId || !$this->productViewRepository->hasRecentView($id, $userId, 60)) {
            $this->productViewRepository->trackView($product, $userId, $sessionId, $ipAddress);
        }
    }

    private function recordBoostImpression(Request $request, mixed $product): void
    {
        try {
            $activeBoost = $this->boostRepository->findActiveForTarget('product', $product->id);

            if ($activeBoost) {
                $sessionHash = hash('sha256', $request->ip() . $request->header('User-Agent'));
                $this->boostEventService->recordImpression($activeBoost->id, $sessionHash);
            }
        } catch (\Exception $e) {
            Logger::error('Boost impression failed', ['error' => $e->getMessage()]);
        }
    }

    private function buildPriceComparison(mixed $product): ?array
    {
        $effectivePrice = ($product->sale_price && $product->sale_price > 0 && $product->sale_price < $product->price)
            ? (float)$product->sale_price
            : (float)$product->price;

        $categoryAvgResult = Product::where('category_id', $product->category_id)
            ->where('is_active', true)
            ->where('id', '!=', $product->id)
            ->selectRaw('AVG(COALESCE(NULLIF(sale_price, 0), price)) as avg_price, COUNT(*) as product_count')
            ->first();

        $categoryAvg = $categoryAvgResult ? round((float)$categoryAvgResult->avg_price, 2) : null;
        $productCount = $categoryAvgResult ? (int)$categoryAvgResult->product_count : 0;

        if (!$categoryAvg || $categoryAvg <= 0) {
            return null;
        }

        $diffPercent = round((($effectivePrice - $categoryAvg) / $categoryAvg) * 100);

        [$priceDifference, $priceComparisonLabel] = match (true) {
            $diffPercent < 0 => [abs($diffPercent) . '% below average', 'better'],
            $diffPercent > 0 => [$diffPercent . '% above average', 'worse'],
            default => ['At category average', 'average'],
        };

        $discountVsRegular = null;
        if ($product->sale_price && $product->sale_price > 0 && $product->price > $product->sale_price) {
            $savings = round((($product->price - $product->sale_price) / $product->price) * 100);
            $discountVsRegular = "Save {$savings}% off RRP";
        }

        return [
            'price_comparison' => $priceComparisonLabel,
            'price_difference' => $priceDifference,
            'category_avg_price' => $categoryAvg,
            'products_in_category' => $productCount,
            'discount_vs_regular' => $discountVsRegular,
        ];
    }

    private function formatProductModalData(mixed $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'price' => $product->price,
            'sale_price' => $product->sale_price,
            'brand' => ['id' => $product->brand?->id, 'name' => $product->brand?->name],
            'category' => ['id' => $product->category?->id, 'name' => $product->category?->name],
            'images' => $product->images->map(fn($img) => [
                'url' => $img->url,
                'alt' => $img->alt,
                'is_primary' => $img->is_primary,
            ])->toArray(),
            'variants' => $product->activeVariants->map(fn($variant) => [
                'id' => $variant->id,
                'name' => $variant->name,
                'sku' => $variant->sku,
                'price' => $variant->price,
                'sale_price' => $variant->sale_price,
                'attributes' => $variant->attributes,
                'in_stock' => $variant->in_stock,
                'stock_quantity' => $variant->stock_quantity,
                'images' => $variant->images->map(fn($img) => ['url' => $img->url, 'alt' => $img->alt])->toArray(),
                'merchants' => $variant->merchants->map(fn($m) => [
                    'id' => $m->id,
                    'merchant_id' => $m->merchant_id,
                    'name' => $m->merchant?->name,
                    'url' => $m->url,
                    'price' => $m->effective_price,
                    'sale_price' => $m->effective_sale_price,
                    'is_available' => $m->is_available,
                ])->toArray(),
            ])->toArray(),
            'merchants' => $product->availableMerchants->map(fn($m) => [
                'id' => $m->id,
                'merchant_id' => $m->merchant_id,
                'name' => $m->merchant?->name,
                'url' => $m->url,
                'price' => $m->effective_price,
                'sale_price' => $m->effective_sale_price,
                'is_available' => $m->is_available,
                'discount_percentage' => $m->discount_percentage,
                'has_discount' => $m->has_discount,
            ])->toArray(),
            'reviews' => $product->approvedReviews->map(fn($review) => [
                'id' => $review->id,
                'rating' => $review->rating,
                'title' => $review->title,
                'comment' => $review->comment,
                'author_name' => $review->author_name,
                'helpful_count' => $review->helpful_count,
                'is_verified_purchase' => $review->is_verified_purchase,
            ])->toArray(),
            'specifications' => $product->specifications->groupBy('category')->map(fn($specs, $category) => [
                'category' => $category,
                'items' => $specs->map(fn($spec) => ['key' => $spec->key, 'value' => $spec->value])->toArray(),
            ])->values()->toArray(),
            'merchant_price_stats' => (function () use ($product) {
                $prices = $product->availableMerchants
                    ->filter(fn($m) => $m->is_available)
                    ->map(fn($m) => $m->effective_sale_price ?: $m->effective_price)
                    ->filter()
                    ->values();

                if ($prices->isEmpty()) {
                    return null;
                }

                return [
                    'lowest' => round((float)$prices->min(), 2),
                    'highest' => round((float)$prices->max(), 2),
                    'average' => round((float)$prices->average(), 2),
                    'count' => $prices->count(),
                ];
            })(),
            'average_rating' => $product->average_rating ?? 0,
            'review_count' => $product->approvedReviews->count(),
            'stock_quantity' => $product->stock_quantity,
            'in_stock' => $product->in_stock,
        ];
    }

    private function formatProductCard($product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $product->price,
            'sale_price' => $product->sale_price,
            'image' => $product->main_image_url ?? $product->images->first()?->url ?? null,
            'brand' => $product->brand?->name,
            'average_rating' => $product->average_rating ?? 0,
            'review_count' => $product->approvedReviews->count() ?? 0,
        ];
    }
}