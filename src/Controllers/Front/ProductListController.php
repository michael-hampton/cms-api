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
use App\Repositories\Product\ReviewRepository;
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
}