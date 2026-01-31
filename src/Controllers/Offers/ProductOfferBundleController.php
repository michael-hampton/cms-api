<?php

namespace App\Controllers\Offers;

use App\Controllers\Controller;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Models\ProductOfferBundle;
use App\Resources\ProductOfferBundleResource;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteriaParser;
use App\Search\SearchEngine;
use App\Services\Offers\ProductOfferBundleService;
use Exception;

class ProductOfferBundleController extends Controller
{
    public function __construct(
        private readonly ProductOfferBundleService $bundleService
    )
    {
        parent::__construct();
    }

    public function index(Request $request, string $siteName): JsonResponse
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request, $siteName);
            $configuration = SearchConfigurationFactory::create('product_offer_bundle');
            $engine = new SearchEngine($configuration);

            $queryBuilder = ProductOfferBundle::with(['items', 'items.productOffer', 'items.productOffer.product']);
            $result = $engine->search($queryBuilder, $criteria);

            $collection = new PaginatedResourceCollection($result, ProductOfferBundleResource::class);

            return $this->resourceResponse([
                'success' => true,
                'bundles' => $collection->toArray(),
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(Request $request, string $siteName): JsonResponse
    {
        try {
            $data = $request->all();
            $bundle = $this->bundleService->createBundle($data);

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Bundle created successfully',
                'bundle' => $bundle->toArray()
            ], 201);
        } catch (ValidationException $e) {
            return $this->errorResponse(
                'Validation failed',
                422,
                $e->getErrors()
            );
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function show(int $bundleId, string $siteName): JsonResponse
    {
        try {
            $bundle = $this->bundleService->getBundle($bundleId);

            if (!$bundle) {
                return $this->jsonResponse([
                    'message' => 'Bundle not found'
                ], 404);
            }

            return $this->resourceResponse([
                'success' => true,
                'bundle' => $bundle->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(int $bundleId, Request $request, string $siteName): JsonResponse
    {
        try {
            $bundle = $this->bundleService->updateBundle($bundleId, $request->all());

            if (!$bundle) {
                return $this->jsonResponse([
                    'message' => 'Bundle not found'
                ], 404);
            }

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Bundle updated successfully',
                'bundle' => $bundle->toArray()
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse(
                'Validation failed',
                422,
                $e->getErrors()
            );
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function destroy(int $bundleId, string $siteName): JsonResponse
    {
        try {
            $deleted = $this->bundleService->deleteBundle($bundleId);

            if (!$deleted) {
                return $this->jsonResponse([
                    'message' => 'Bundle not found'
                ], 404);
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Bundle deleted successfully'
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function publish(int $bundleId, string $siteName): JsonResponse
    {
        try {
            $userId = auth()->id() ?? 1;
            $bundle = $this->bundleService->publish($bundleId, $userId);

            if (!$bundle) {
                return $this->resourceResponse([
                    'message' => 'Bundle cannot be published'
                ], 400);
            }

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Bundle published successfully',
                'bundle' => $bundle->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function reject(int $bundleId, Request $request, string $siteName): JsonResponse
    {
        try {
            $userId = auth()->id() ?? 1;
            $reason = $request->input('reason');

            if (!$reason) {
                return $this->errorResponse('Rejection reason is required', 422);
            }

            $bundle = $this->bundleService->reject($bundleId, $userId, $reason);

            if (!$bundle) {
                return $this->resourceResponse([
                    'message' => 'Bundle cannot be rejected'
                ], 400);
            }

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Bundle rejected successfully',
                'bundle' => $bundle->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Search bundles
     * GET /api/bundles/search
     */
    public function searchBundles(Request $request, string $siteName): JsonResponse
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

            return $this->jsonResponse([
                'success' => true,
                'bundles' => $results
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}