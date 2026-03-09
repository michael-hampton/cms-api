<?php

namespace App\Controllers\Offers;

use App\Controllers\Controller;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Product;
use App\Repositories\Cms\BrandRepository;
use App\Repositories\Cms\CategoryRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Product\ProductSpecificationGroupRepository;
use App\Repositories\Product\ProductViewRepository;
use App\Services\Cms\MenuRenderer;
use App\Services\Currency\CurrencyResolver;
use App\Services\Offers\DealAlertService;
use App\Services\Offers\DealsService;
use App\Services\Offers\PriceAlertService;
use App\Services\Offers\ProductOfferBundleService;
use App\Services\Offers\ProductOfferService;

class DealsController extends Controller
{
    public function __construct(
        private readonly DealsService $dealsService,
        private readonly PriceAlertService $priceAlertService,
        private readonly DealAlertService $dealAlertService,
        private readonly CategoryRepository $categoryRepository,
        private readonly BrandRepository $brandRepository,
        private readonly ProductRepository     $productRepository,
        private readonly ProductViewRepository     $productViewRepository,
        private readonly ProductOfferService       $offerService,
        private readonly ProductOfferBundleService $bundleService,
        private readonly CurrencyResolver          $currencyResolver,
    ) {
        parent::__construct();
    }

    public function index(Request $request)
    {
        // Get all categories
        $categories = Category::orderBy('name')->get();

        // Get product counts for each category using groupBy
        $categoryProducts = Product::select('category_id')
            ->groupBy('category_id')
            ->get();

        // Count products per category
        $categoryCounts = [];
        foreach ($categoryProducts as $product) {
            $categoryId = $product->category_id;
            if (!isset($categoryCounts[$categoryId])) {
                $categoryCounts[$categoryId] = 0;
            }
            $categoryCounts[$categoryId]++;
        }

        $offers = $this->offerService->getActiveOffers();
        $bundles = $this->bundleService->getActiveBundles();

        // Alternative approach using raw SQL if the above doesn't work
        // $db = Database::getInstance();
        // $stmt = $db->query('SELECT category_id, COUNT(*) as count FROM products GROUP BY category_id', []);
        // $results = $stmt->fetchAll();
        // $categoryCounts = [];
        // foreach ($results as $row) {
        //     $categoryCounts[$row['category_id']] = $row['count'];
        // }

        // Add counts to categories
        $categories = $categories->map(function ($category) use ($categoryCounts) {
            return (object)[
                'id' => $category->id,
                'name' => $category->name,
                'product_count' => $categoryCounts[$category->id] ?? 0
            ];
        });

        // Get all brands
        $brands = Brand::orderBy('name')->get();

        // Get product counts for each brand
        $brandProducts = Product::select('brand_id')
            ->groupBy('brand_id')
            ->get();

        // Count products per brand
        $brandCounts = [];
        foreach ($brandProducts as $product) {
            $brandId = $product->brand_id;
            if (!isset($brandCounts[$brandId])) {
                $brandCounts[$brandId] = 0;
            }
            $brandCounts[$brandId]++;
        }

        // Add counts to brands
        $brands = $brands->map(function ($brand) use ($brandCounts) {
            return (object)[
                'id' => $brand->id,
                'name' => $brand->name,
                'product_count' => $brandCounts[$brand->id] ?? 0
            ];
        });

        $menu = Menu::where('is_active', true)
            ->where('site_id', SiteContext::getId())
            ->where('menu_type', 'header')
            ->with(['items'])
            ->first();

        $siteId = SiteContext::getId();

        $deals = $this->dealsService->getTodaysDeals();

        // Get specification groups with counts
        $specRepository = app(ProductSpecificationGroupRepository::class);
        $specificationGroups = $specRepository->getAllWithCounts($siteId);

        $currencyCode = $this->currencyResolver->resolveUpperCase();
        $currencySymbol = $this->currencyResolver->symbol($currencyCode);

        return $this->view('deals.index', [
            'categories' => $categories->toArray(),
            'brands' => $brands->toArray(),
            'menu' => $menu,
            'menuRenderer' => new MenuRenderer(),
            'specificationGroups' => $specificationGroups->toArray(),
            'deals' => $deals,
            'todaysDeals' => $this->dealsService->getTodaysDeals(10),
            'offers' => $offers,
            'bundles' => $bundles,
            'currencyCode' => $currencyCode,
            'currencySymbol' => $currencySymbol,
        ]);
    }

    public function refresh()
    {
        $deals = $this->dealsService->refreshTodaysDeals();
        return $this->resourceResponse(['deals' => $deals]);
    }

    public function carousel()
    {
        $deals = $this->dealsService->getTodaysDeals(10);
        return $this->resourceResponse(['deals' => $deals]);
    }

    public function filtered(Request $request)
    {
        $filters = $request->all();
        $deals = $this->dealsService->getFilteredDeals($filters);
        return $this->resourceResponse($deals);
    }

    public function createPriceAlert(Request $request)
    {
        $data = $request->all();
        $result = $this->priceAlertService->createAlert($data);
        return $this->resourceResponse($result);
    }

    public function subscribeDealAlert(Request $request)
    {
        $data = $request->all();

        $result = $this->dealAlertService->subscribe($data);
        return $this->resourceResponse($result);
    }

    public function verifyDealAlert(Request $request)
    {
        $token = $request->query('token');
        $result = $this->dealAlertService->verify($token);

        if ($result['success']) {
            return $this->view('deal-alerts/verified', $result);
        }

        return $this->view('deal-alerts/error', $result);
    }

    public function unsubscribeDealAlert(Request $request)
    {
        $email = $request->input('email');
        $result = $this->dealAlertService->unsubscribe($email);
        return $this->resourceResponse($result);
    }

    public function getProductModal(Request $request, $id)
    {
        try {
            $product = $this->productRepository->find($id);

            if (!$product) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            // Track the view
            $userId = auth()->user()?->id ?? null;
            $sessionId = session_id() ?: $request->session()->getId();
            $ipAddress = $request->ip();

            // Only track if not recently viewed (within last 60 minutes)
            if (!$userId || !$this->productViewRepository->hasRecentView($id, $userId, 60)) {
                $this->productViewRepository->trackView($product, $userId, $sessionId, $ipAddress);
            }

            // Load all necessary relationships
            $product->load([
                'images',
                'activeVariants.images',
                'activeVariants.merchants.merchant',
                'availableMerchants.merchant',
                'brand',
                'category',
                'approvedReviews',
                'specifications.group'
            ]);

            // Get related products
            $relatedProducts = $this->productRepository->findRelated($product, 6);

            // Get similar items (from same category)
            $similarItems = Product::where('category_id', $product->category_id)
                ->where('id', '!=', $product->id)
                ->where('is_active', true)
                ->limit(6)
                ->get();

            // Format the response
            $productData = [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'price' => $product->price,
                'sale_price' => $product->sale_price,
                'brand' => [
                    'id' => $product->brand?->id,
                    'name' => $product->brand?->name
                ],
                'category' => [
                    'id' => $product->category?->id,
                    'name' => $product->category?->name
                ],
                'images' => $product->images->map(fn($img) => [
                    'url' => $img->url,
                    'alt' => $img->alt,
                    'is_primary' => $img->is_primary
                ])->toArray(),
                'variants' => $product->activeVariants->map(function ($variant) {
                    return [
                        'id' => $variant->id,
                        'name' => $variant->name,
                        'sku' => $variant->sku,
                        'price' => $variant->price,
                        'sale_price' => $variant->sale_price,
                        'attributes' => $variant->attributes,
                        'in_stock' => $variant->in_stock,
                        'stock_quantity' => $variant->stock_quantity,
                        'images' => $variant->images->map(fn($img) => [
                            'url' => $img->url,
                            'alt' => $img->alt
                        ])->toArray(),
                        'merchants' => $variant->merchants->map(function ($merchant) {
                            return [
                                'id' => $merchant->id,
                                'merchant_id' => $merchant->merchant_id,
                                'name' => $merchant->merchant?->name,
                                'url' => $merchant->url,
                                'price' => $merchant->effective_price,
                                'sale_price' => $merchant->effective_sale_price,
                                'is_available' => $merchant->is_available
                            ];
                        })->toArray()
                    ];
                })->toArray(),
                'merchants' => $product->availableMerchants->map(function ($merchant) {
                    return [
                        'id' => $merchant->id,
                        'merchant_id' => $merchant->merchant_id,
                        'name' => $merchant->merchant?->name,
                        'url' => $merchant->url,
                        'price' => $merchant->effective_price,
                        'sale_price' => $merchant->effective_sale_price,
                        'is_available' => $merchant->is_available,
                        'discount_percentage' => $merchant->discount_percentage,
                        'has_discount' => $merchant->has_discount
                    ];
                })->toArray(),
                'reviews' => $product->approvedReviews->map(fn($review) => [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'title' => $review->title,
                    'comment' => $review->comment,
                    'author_name' => $review->author_name,
                    'helpful_count' => $review->helpful_count,
                    'is_verified_purchase' => $review->is_verified_purchase
                ])->toArray(),
                'specifications' => $product->specifications->groupBy('category')->map(function ($specs, $category) {
                    return [
                        'category' => $category,
                        'items' => $specs->map(fn($spec) => [
                            'key' => $spec->key,
                            'value' => $spec->value
                        ])->toArray()
                    ];
                })->values()->toArray(),
                'average_rating' => $product->average_rating ?? 0,
                'review_count' => $product->approvedReviews->count(),
                'stock_quantity' => $product->stock_quantity,
                'in_stock' => $product->in_stock
            ];

            return $this->resourceResponse([
                'success' => true,
                'product' => $productData,
                'related_products' => $relatedProducts->map(fn($p) => $this->formatProductCard($p))->toArray(),
                'similar_items' => $similarItems->map(fn($p) => $this->formatProductCard($p))->toArray()
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Error fetching product details',
                'error' => $e->getMessage()
            ], 500);
        }
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
            'review_count' => $product->approvedReviews->count() ?? 0
        ];
    }
}