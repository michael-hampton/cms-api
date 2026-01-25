<?php

namespace App\Controllers\Product;

use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Services\Product\ProductMatchingService;

class ProductMatchingController extends Controller
{
    private ProductMatchingService $matchingService;

    public function __construct(ProductMatchingService $matchingService)
    {
        $this->matchingService = $matchingService;
        parent::__construct();
    }

    /**
     * Find product matches based on name and brand
     */
    public function findMatches(Request $request): JsonResponse
    {
        $productName = $request->input('product_name');
        $brand = $request->input('brand');
        $siteId = SiteContext::getId();

        if (empty($productName)) {
            return $this->errorResponse('Product name is required', 400);
        }

        $matches = $this->matchingService->findMatches($productName, $brand, $siteId);

        return $this->resourceResponse([
            'matches' => array_map(function ($match) {
                return [
                    'id' => $match['product']->id,
                    'name' => $match['product']->name,
                    'brand' => $match['product']->brand,
                    'price' => $match['product']->price,
                    'sale_price' => $match['product']->sale_price,
                    'image' => $match['product']->main_image_url,
                    'similarity' => round($match['similarity'] * 100, 1),
                    'confidence' => $match['confidence']
                ];
            }, $matches),
            'has_matches' => count($matches) > 0,
            'best_match' => !empty($matches) ? [
                'id' => $matches[0]['product']->id,
                'name' => $matches[0]['product']->name,
                'brand' => $matches[0]['product']->brand,
                'confidence' => $matches[0]['confidence'],
                'similarity' => round($matches[0]['similarity'] * 100, 1)
            ] : null
        ]);
    }
}