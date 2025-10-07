<?php
// App/Controllers/ProductListController.php

namespace App\Controllers;

use App\Framework\Http\Request;
use App\Search\SearchCriteria;
use App\Services\ProductService;
use App\Models\Category;
use App\Models\Brand;

class ProductListController extends Controller
{
    public function __construct(
        private readonly ProductService $productService
    ) {
        parent::__construct();
    }

    public function index(Request $request)
    {
        // Get filter data for dropdowns
        $categories = Category::orderBy('name')->get();
        $brands = Brand::orderBy('name')->get();

        return $this->view('products.index', [
            'categories' => $categories,
            'brands' => $brands,
            'menu' => $this->getMenu(),
        ]);
    }

    public function search(Request $request)
    {
        $criteria = new SearchCriteria(
            page: $request->input('page', 1),
            perPage: $request->input('per_page', 12),
            searchQuery: $request->input('q'),
            filters: [
                'category_id' => $request->input('category_id'),
                'brand' => $request->input('brand_id'),
                'on_sale' => $request->input('on_sale'),
            ],
            sortBy: $request->input('sort_by', 'created_at'),
            sortOrder: $request->input('sort_order', 'desc')
        );

        // Apply price range filter manually if needed
        $minPrice = $request->input('min_price');
        $maxPrice = $request->input('max_price');

        $result = $this->productService->search($criteria);

        // Filter by price range if provided
        if ($minPrice !== null || $maxPrice !== null) {
            $data = $result->getData();
            $data = array_filter($data, function($product) use ($minPrice, $maxPrice) {
                $price = $product->sale_price ?? $product->price;

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

        return $this->jsonResponse($result->toArray());
    }

    protected function getMenu(): array
    {
        // Return your menu structure
        return [];
    }
}