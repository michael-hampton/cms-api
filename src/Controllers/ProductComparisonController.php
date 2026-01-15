<?php

namespace App\Controllers;

use App\Framework\Http\Request;
use App\Services\Product\ProductComparisonService;

class ProductComparisonController extends Controller
{
    public function __construct(
        private readonly ProductComparisonService $comparisonService
    )
    {
        parent::__construct();
    }

    /**
     * Compare products
     * GET /compare?ids=1,2,3
     */
    public function compare(Request $request)
    {
        $idsParam = $request->query('ids');

        if (!$idsParam) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Product IDs are required'
            ], 400);
        }

        $productIds = array_filter(array_map('intval', explode(',', $idsParam)));

        if (count($productIds) < 2) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'At least 2 products required for comparison'
            ], 400);
        }

        if (count($productIds) > 4) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Maximum 4 products can be compared'
            ], 400);
        }

        try {
            $comparison = $this->comparisonService->compareProducts($productIds);

            if (!$comparison['comparable']) {
                return $this->resourceResponse([
                    'success' => false,
                    'message' => $comparison['reason']
                ], 400);
            }

            return $this->resourceResponse([
                'success' => true,
                'data' => $comparison
            ]);

        } catch (\InvalidArgumentException $e) {
            return $this->resourceResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'An error occurred while comparing products'
            ], 500);
        }
    }

    /**
     * Show comparison page
     * GET /compare-view?ids=1,2,3
     */
    public function index(Request $request)
    {
        $idsParam = $request->query('ids');

        if (!$idsParam) {
            return $this->redirectResponse('/shop');
        }

        $productIds = array_filter(array_map('intval', explode(',', $idsParam)));

        if (count($productIds) < 2 || count($productIds) > 4) {
            return $this->redirectResponse('/shop');
        }

        try {
            $comparison = $this->comparisonService->compareProducts($productIds);

            if (!$comparison['comparable']) {
                return $this->redirectResponse('/shop');
            }

            return $this->view('products.compare', [
                'comparison' => $comparison
            ]);

        } catch (\Exception $e) {
            return $this->redirectResponse('/shop');
        }
    }
}