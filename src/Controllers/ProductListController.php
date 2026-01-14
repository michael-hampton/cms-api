<?php
// App/Controllers/ProductListController.php

namespace App\Controllers;

use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Product;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Product\ProductSpecificationGroupRepository;
use App\Search\SearchCriteria;
use App\Services\Cms\MenuRenderer;
use App\Services\Product\BuildProductCardService;
use App\Services\Product\ProductService;

class ProductListController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly ProductRepository $productRepository,
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

        // Filter by price range if provided
        if (!empty($minPrice) || !empty($maxPrice)) {
            $data = $result->getData();

            $data = array_filter($data, function ($product) use ($minPrice, $maxPrice) {

                $price = $product['sale_price'] > 0 ? $product['sale_price'] : $product['price'];

                if ($minPrice !== null && $price < $minPrice) {
                    return false;
                }

                if ($maxPrice !== null && $price > $maxPrice) {
                    return false;
                }

                return true;
            });

            $result->setData(array_values($data));
        }

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