<?php
// App/Controllers/ProductListController.php

namespace App\Controllers;

use App\Framework\Http\Request;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Search\SearchCriteria;
use App\Services\ProductService;

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
            $product = Product::with(['activeVariants', 'availableMerchants', 'specifications', 'priceHistory'])->find($id);

            if (!$product) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            // Format variants for frontend
            $variants = [];
            foreach ($product->activeVariants as $variant) {
                $variants[] = [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'sku' => $variant->sku,
                    'price' => (float)$variant->price,
                    'sale_price' => (float)$variant->sale_price,
                    'final_price' => (float)$variant->final_price,
                    'discount_percentage' => (int)$variant->discount_percentage,
                    'in_stock' => true, // You can add stock logic here if you have it
                    'attributes' => $variant->attributes
                ];
            }

            // Format price history (last 90 days)
            $priceHistory = [];
            $cutoffDate = strtotime('-90 days');

            foreach ($product->priceHistory->toArray() as $history) {
                $recordedAt = $history['recorded_at'];
                if ($recordedAt instanceof \DateTime) {
                    $timestamp = $recordedAt->getTimestamp();
                } else {
                    $timestamp = strtotime($recordedAt);
                }

                // Only include last 90 days
                if ($timestamp >= $cutoffDate) {
                    $effectivePrice = $history['sale_price'] > 0 ? $history['sale_price'] : $history['price'];

                    $priceHistory[] = [
                        'date' => date('Y-m-d', $timestamp),
                        'price' => (float)$effectivePrice,
                        'regular_price' => (float)$history['price'],
                        'sale_price' => (float)$history['sale_price']
                    ];
                }
            }

            // Sort by date ascending
            usort($priceHistory, function ($a, $b) {
                return strtotime($a['date']) - strtotime($b['date']);
            });

            // If no price history, create a simple current price entry
            if (empty($priceHistory)) {
                $currentPrice = $product->sale_price > 0 ? $product->sale_price : $product->price;
                $priceHistory = [
                    [
                        'date' => date('Y-m-d', strtotime('-90 days')),
                        'price' => (float)$currentPrice
                    ],
                    [
                        'date' => date('Y-m-d'),
                        'price' => (float)$currentPrice
                    ]
                ];
            }

            // Get comparison data with category average
            $comparison = $this->getComparisonData($product);

            // Format specifications
            $specifications = [];
            foreach ($product->specifications->toArray() as $spec) {
                $specifications[] = [
                    'category' => $spec['category'],
                    'key' => $spec['key'],
                    'value' => $spec['value'],
                    'sort_order' => $spec['sort_order']
                ];

                // Sort by sort_order
                usort($specifications, function ($a, $b) {
                    return $a['sort_order'] - $b['sort_order'];
                });
            }

            // Get merchant information
            $merchants = [];
            $lowestMerchantPrice = null;
            foreach ($product->availableMerchants->toArray() as $merchant) {
                if ($merchant['is_available']) {
                    $effectivePrice = $merchant['effective_sale_price'] > 0
                        ? $merchant['effective_sale_price']
                        : $merchant['effective_price'];

                    $merchants[] = [
                        'id' => $merchant['merchant_id'],
                        'url' => $merchant['url'],
                        'price' => (float)$merchant['effective_price'],
                        'sale_price' => (float)$merchant['effective_sale_price'],
                        'discount_percentage' => (int)$merchant['discount_percentage'],
                        'has_discount' => (bool)$merchant['has_discount']
                    ];

                    if ($lowestMerchantPrice === null || $effectivePrice < $lowestMerchantPrice) {
                        $lowestMerchantPrice = $effectivePrice;
                    }
                }
            }

            // Calculate stock status (you may need to adjust this based on your stock tracking)
            $stockQuantity = $this->calculateStockQuantity($product);

            // Prepare final response
            $productData = [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'price' => (float)$product->price,
                'sale_price' => (float)$product->sale_price,
                'discount_percentage' => (int)$product->discount_percentage,
                'stock_quantity' => $stockQuantity,
                'variants' => $variants,
                'price_history' => $priceHistory,
                'comparison' => $comparison,
                'specifications' => $specifications,
                'merchants' => $merchants,
                'lowest_merchant_price' => $lowestMerchantPrice
            ];

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

    private function calculateStockQuantity($product)
    {
        // If you have a stock_quantity field on products table
        if (isset($product->stock_quantity)) {
            return (int)$product->stock_quantity;
        }

        // Otherwise, calculate from variants or merchants
        if (!empty($product->activeVariants)) {
            // If variants have stock, sum them up
            $totalStock = 0;
            foreach ($product->activeVariants as $variant) {
                if (isset($variant['stock_quantity'])) {
                    $totalStock += (int)$variant['stock_quantity'];
                }
            }
            if ($totalStock > 0) {
                return $totalStock;
            }
        }

        // If available merchants exist, assume in stock
        if (!empty($product->availableMerchants)) {
            $hasAvailable = false;
            foreach ($product->availableMerchants as $merchant) {
                if ($merchant['is_available']) {
                    $hasAvailable = true;
                    break;
                }
            }
            return $hasAvailable ? 50 : 0; // Return 50 as default "in stock" or 0
        }

        // Default to in stock with unknown quantity
        return 50;
    }

    private function getComparisonData($product)
    {
        try {
            $db = \App\Framework\Database\Database::getInstance();

            // Get average price in category
            $stmt = $db->query(
                'SELECT 
                AVG(CASE WHEN sale_price > 0 THEN sale_price ELSE price END) as avg_price,
                MIN(CASE WHEN sale_price > 0 THEN sale_price ELSE price END) as min_price,
                MAX(CASE WHEN sale_price > 0 THEN sale_price ELSE price END) as max_price,
                COUNT(*) as product_count
             FROM products 
             WHERE category_id = ? 
             AND id != ? 
             AND is_active = 1
             AND deleted_at IS NULL',
                [$product->category_id, $product->id]
            );

            $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

            $categoryAvg = (float)($stats['avg_price'] ?? $product->price);
            $currentPrice = $product->sale_price > 0 ? $product->sale_price : $product->price;

            // Calculate price difference percentage
            $priceDiff = 0;
            if ($categoryAvg > 0) {
                $priceDiff = (($currentPrice - $categoryAvg) / $categoryAvg) * 100;
            }

            $comparison = 'average';
            $priceText = 'Average Price';

            if ($priceDiff < -15) {
                $comparison = 'better';
                $priceText = number_format(abs($priceDiff), 0) . '% Below Average';
            } elseif ($priceDiff < -5) {
                $comparison = 'better';
                $priceText = number_format(abs($priceDiff), 0) . '% Lower';
            } elseif ($priceDiff > 15) {
                $comparison = 'worse';
                $priceText = number_format($priceDiff, 0) . '% Above Average';
            } elseif ($priceDiff > 5) {
                $comparison = 'worse';
                $priceText = number_format($priceDiff, 0) . '% Higher';
            }

            return [
                'price_comparison' => $comparison,
                'price_difference' => $priceText,
                'category_avg_price' => number_format($categoryAvg, 2),
                'category_min_price' => number_format($stats['min_price'] ?? 0, 2),
                'category_max_price' => number_format($stats['max_price'] ?? 0, 2),
                'products_in_category' => (int)($stats['product_count'] ?? 0),
                'discount_vs_regular' => $product->sale_price > 0
                    ? number_format((($product->price - $product->sale_price) / $product->price) * 100, 0) . '% off'
                    : null
            ];

        } catch (\Exception $e) {
            // Return basic data if queries fail
            $discountAmount = null;
            if ($product->sale_price > 0 && $product->price > 0) {
                $discountAmount = number_format((($product->price - $product->sale_price) / $product->price) * 100, 0) . '% off';
            }

            return [
                'price_comparison' => 'average',
                'price_difference' => 'N/A',
                'category_avg_price' => null,
                'discount_vs_regular' => $discountAmount
            ];
        }
    }

    private function getProductVariants($productId)
    {
        // This would get variants from a product_variants table
        // For now, return mock data - implement based on your schema
        return [
            ['id' => 1, 'name' => 'S', 'in_stock' => true],
            ['id' => 2, 'name' => 'M', 'in_stock' => true],
            ['id' => 3, 'name' => 'L', 'in_stock' => true],
            ['id' => 4, 'name' => 'XL', 'in_stock' => false],
        ];
    }

    private function getPriceHistory($productId, $days = 90)
    {
        // This would query a price_history table
        // For now, generate mock data - implement based on your schema
        $history = [];
        $basePrice = Product::find($productId)->price;
        $startDate = strtotime("-{$days} days");

        for ($i = 0; $i <= $days; $i += 7) {
            $date = date('Y-m-d', strtotime("+{$i} days", $startDate));
            // Simulate price fluctuations
            $variation = (rand(-10, 10) / 100) * $basePrice;
            $price = $basePrice + $variation;

            $history[] = [
                'date' => $date,
                'price' => round($price, 2)
            ];
        }

        return $history;
    }

    protected function getMenu(): array
    {
        // Return your menu structure
        return [];
    }
}