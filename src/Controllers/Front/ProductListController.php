<?php
// App/Controllers/ProductListController.php

namespace App\Controllers\Front;

use App\Controllers\Controller;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Product;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Product\ProductSpecificationGroupRepository;
use App\Repositories\Product\ProductViewRepository;
use App\Repositories\ReviewRepository;
use App\Search\SearchCriteria;
use App\Services\Cms\MenuRenderer;
use App\Services\Product\BuildProductCardService;
use App\Services\Product\ProductService;

class ProductListController extends Controller
{
    public function __construct(
        private readonly ProductService   $productService,
        private readonly ProductRepository $productRepository,
        private readonly ReviewRepository $reviewRepository,
        private readonly ProductViewRepository $productViewRepository,
    )
    {
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

        // Get specification groups with counts
        $specRepository = app(ProductSpecificationGroupRepository::class);
        $specificationGroups = $specRepository->getAllWithCounts($siteId);

        return $this->view('products.index', [
            'categories' => $categories->toArray(),
            'brands' => $brands->toArray(),
            'menu' => $menu,
            'menuRenderer' => new MenuRenderer(),
            'specificationGroups' => $specificationGroups->toArray(),
        ]);
    }

    public function search(Request $request)
    {
        $categoryIds = array_filter(explode(',', $request->input('category_ids', '')));
        $brandIds = array_filter(explode(',', $request->input('brand_ids', '')));
        $specIds = array_filter(explode(',', $request->input('spec_ids', '')));

        $filters = array_filter([
            'categories' => $categoryIds,
            'brands' => $brandIds,
            'specifications' => $specIds,
            'on_sale' => $request->input('on_sale'),
        ], static fn($value) => $value !== null && $value !== [] && $value !== '');

        $criteria = new SearchCriteria(
            filters: $filters,
            sortBy: $request->input('sort_by', 'created_at'),
            sortOrder: $request->input('sort_order', 'desc'),
            page: $request->input('page', 1),
            perPage: $request->input('per_page', 12),
            searchQuery: $request->input('q')
        );

        // Apply price range filter manually if needed
        $minPrice = $request->input('min_price');
        $maxPrice = $request->input('max_price');

        $result = $this->productRepository->search($criteria);

        /**
         * Price range filtering
         */
        if (!empty($minPrice) || !empty($maxPrice)) {
            $filtered = collect($result->getData())->filter(function ($product) use ($minPrice, $maxPrice) {

                $price = $product['sale_price'] > 0
                    ? $product['sale_price']
                    : $product['price'];

                return ($minPrice === null || $price >= $minPrice)
                    && ($maxPrice === null || $price <= $maxPrice);
            });

            $result->setData($filtered->all());
        }

        $productIds = collect($result->getData())->pluck('id')->unique()->toArray();
        $topReviews = $this->reviewRepository->getTopReview($productIds)->keyBy('product_id');

        /**
         * Enrich product data
         */
        $formattedProducts = collect($result->toArray()['data'])->map(function ($product) use ($topReviews) {

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

            return array_merge($product, [
                'average_rating' => $averageRating,
                'review_count' => count($reviews),
                'merchant_count' => count($merchants),
                'top_review' => $topReviews->get($product['id'])->toArray(),
                'lowest_merchant_price' => $lowestMerchantPrice,
            ]);
        });

        $result->setData($formattedProducts->toArray());

        return $this->resourceResponse($result->toArray());
    }

    public function getProductDetails(Request $request, $id)
    {
        try {


            // Prepare final response
            $productData = (new BuildProductCardService())->build($id);

            return $this->resourceResponse([
                'success' => true,
                'product' => $productData
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Error fetching product details',
                'error' => $e->getMessage()
            ], 500);
        }
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