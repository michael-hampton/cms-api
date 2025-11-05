<?php
// App/Controllers/ProductListController.php

namespace App\Controllers;

use App\Framework\Http\Request;
use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Search\SearchCriteria;
use App\Services\ProductService;
use App\Models\Category;
use App\Models\Brand;

class ProductListController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
        private readonly ProductRepository $productRepository,
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

        // Alternative approach using raw SQL if the above doesn't work
        // $db = Database::getInstance();
        // $stmt = $db->query('SELECT category_id, COUNT(*) as count FROM products GROUP BY category_id', []);
        // $results = $stmt->fetchAll();
        // $categoryCounts = [];
        // foreach ($results as $row) {
        //     $categoryCounts[$row['category_id']] = $row['count'];
        // }

        // Add counts to categories
        $categories = $categories->map(function($category) use ($categoryCounts) {
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
        $brands = $brands->map(function($brand) use ($brandCounts) {
            return (object)[
                'id' => $brand->id,
                'name' => $brand->name,
                'product_count' => $brandCounts[$brand->id] ?? 0
            ];
        });

        return $this->view('products.index', [
            'categories' => $categories->toArray(),
            'brands' => $brands->toArray(),
            'menu' => $this->getMenu(),
        ]);
    }

    public function search(Request $request)
    {
        $categoryIds = array_filter(explode(',', $request->input('category_ids', '')));
        $brandIds = array_filter(explode(',', $request->input('brand_ids', '')));

        $criteria = new SearchCriteria(
            filters: [
                'category_ids' => $categoryIds,
                'brand_ids' => $brandIds,
                'on_sale' => $request->input('on_sale'),
            ],
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

            $data = array_filter($data, function($product) use ($minPrice, $maxPrice) {

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

    protected function getMenu(): array
    {
        // Return your menu structure
        return [];
    }
}