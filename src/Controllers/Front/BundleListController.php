<?php

namespace App\Controllers\Front;

use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Models\ProductOfferBundle;
use App\Services\Offers\ProductOfferBundleService;
use Exception;

class BundleListController extends Controller
{
    public function __construct(
        private readonly ProductOfferBundleService $bundleService
    )
    {
        parent::__construct();
    }

    /**
     * Display bundles listing page
     */
    public function indexPage(): mixed
    {
        return $this->view('bundles/index');
    }

    /**
     * Display single bundle detail page
     */
    public function showPage(int $bundleId): mixed
    {
        try {
            $bundle = ProductOfferBundle::with([
                'items.product',
                'items.product.merchant',
                'items.productOffer.product',
                'items.productOffer.merchant'
            ])
                ->where('id', $bundleId)
                ->where('status', 'published')
                ->where('is_active', true)
                ->first();

            if (!$bundle) {
                return $this->errorResponse('does not exist', 404);
            }

            return $this->view('bundles/detail', [
                'bundle' => $bundle
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Search bundles
     * GET /api/bundles/search
     */
    public function searchBundles(Request $request): JsonResponse
    {
        try {
            $filters = [
                'search' => $request->get('q') ?? $request->get('search'),
                'status' => 'published',
                'is_active' => true,
                'category' => $request->get('category'),
                'min_savings' => $request->get('min_savings'),
                'min_price' => $request->get('min_price'),
                'max_price' => $request->get('max_price'),
                'min_discount' => $request->get('min_discount'),
                'merchant_type' => $request->get('merchant_type'),
                'sort_by' => $request->get('sort_by', 'created_at'),
                'sort_order' => $request->get('sort_order', 'desc'),
                'per_page' => $request->get('per_page', 20),
                'page' => $request->get('page', 1),
            ];

            $results = $this->bundleService->getBundlesForWeb($filters);

            return $this->resourceResponse([
                'success' => true,
                'bundles' => $results
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }


}