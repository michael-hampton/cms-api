<?php

namespace App\Controllers\Offers;

use App\Actions\Offers\BulkDeleteBundles;
use App\Actions\Offers\BulkPublishBundles;
use App\Controllers\Controller;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Framework\Support\SiteContext;
use App\Models\ProductOfferBundle;
use App\Requests\BulkActionRequest;
use App\Requests\Offers\StoreProductOfferBundleRequest;
use App\Requests\Offers\UpdateProductOfferBundleRequest;
use App\Resources\ProductOfferBundleResource;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteriaParser;
use App\Search\SearchEngine;
use App\Services\Offers\ProductOfferBundleService;
use Exception;

class ProductOfferBundleController extends Controller
{
    public function __construct(
        private readonly ProductOfferBundleService $bundleService,
        private readonly BulkPublishBundles        $bulkPublishBundles,
        private readonly BulkDeleteBundles         $bulkDeleteBundles,
    )
    {
        parent::__construct();
    }

    public function index(Request $request, string $site): JsonResponse
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request, $site);
            $configuration = SearchConfigurationFactory::create('product_offer_bundle');
            $engine = new SearchEngine($configuration);

            $queryBuilder = ProductOfferBundle::with(['items', 'items.productOffer', 'items.productOffer.product', 'regionSets']);
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

    public function store(StoreProductOfferBundleRequest $request, string $site): JsonResponse
    {
        try {
            $siteId = SiteContext::getId();
            $data = $request->validated();
            $data['site_id'] = $siteId;

            $bundle = $this->bundleService->createBundle($data);

            if ($request->has('region_set_ids')) {
                $value = $request->get('region_set_ids');

                $ids = is_string($value)
                    ? json_decode($value, true)
                    : ($value ?? []);
                $bundle->regionSets(true)->sync(array_map('intval', $ids));
                $bundle->load(['regionSets']);
            }

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Bundle created successfully',
                'bundle' => $bundle->toArray(),
            ], 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function show(int $bundleId, string $site): JsonResponse
    {
        try {
            $bundle = $this->bundleService->getBundle($bundleId);

            if (!$bundle) {
                return $this->jsonResponse(['message' => 'Bundle not found'], 404);
            }

            return $this->resourceResponse([
                'success' => true,
                'bundle' => $bundle->toArray(),
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(int $bundleId, UpdateProductOfferBundleRequest $request, string $site): JsonResponse
    {
        try {
            $siteId = SiteContext::getId();
            $data = $request->validated();
            $data['site_id'] = $siteId;
            unset($data['region_set_ids']);
            $bundle = $this->bundleService->updateBundle($bundleId, $data);

            if (!$bundle) {
                return $this->jsonResponse(['message' => 'Bundle not found'], 404);
            }

            if ($request->has('region_set_ids')) {
                $value = $request->get('region_set_ids');

                $ids = is_string($value)
                    ? json_decode($value, true)
                    : ($value ?? []);
                $bundle->regionSets(true)->sync(array_map('intval', $ids));

                $bundle->load(['regionSets']);
            }

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Bundle updated successfully',
                'bundle' => $bundle->toArray(),
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function destroy(int $bundleId, string $site): JsonResponse
    {
        try {
            $deleted = $this->bundleService->deleteBundle($bundleId);

            if (!$deleted) {
                return $this->jsonResponse(['message' => 'Bundle not found'], 404);
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Bundle deleted successfully',
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function publish(int $bundleId, string $site): JsonResponse
    {
        try {
            $userId = auth()->id() ?? 1;
            $bundle = $this->bundleService->publish($bundleId, $userId);

            if (!$bundle) {
                return $this->resourceResponse(['message' => 'Bundle cannot be published'], 400);
            }

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Bundle published successfully',
                'bundle' => $bundle->toArray(),
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function reject(int $bundleId, Request $request, string $site): JsonResponse
    {
        try {
            $userId = auth()->id() ?? 1;
            $reason = $request->input('reason');

            if (!$reason) {
                return $this->errorResponse('Rejection reason is required', 422);
            }

            $bundle = $this->bundleService->reject($bundleId, $userId, $reason);

            if (!$bundle) {
                return $this->resourceResponse(['message' => 'Bundle cannot be rejected'], 400);
            }

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Bundle rejected successfully',
                'bundle' => $bundle->toArray(),
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // =========================================================================
    // Bulk actions
    // =========================================================================

    /**
     * POST /api/bundles/bulk/publish
     *
     * Body: { "ids": [1, 2, 3] }
     */
    public function bulkPublish(Request $request, string $site): JsonResponse
    {
        try {
            $ids = $request->input('ids');

            if (!is_array($ids) || empty($ids)) {
                return $this->errorResponse('ids must be a non-empty array', 422);
            }

            $userId = auth()->id() ?? 1;
            $result = $this->bulkPublishBundles->handle(array_map('intval', $ids), $userId);

            return $this->resourceResponse([
                'success' => true,
                'message' => sprintf('%d bundle(s) published successfully', count($result['published'])),
                'published' => $result['published'],
                'failed' => $result['failed'],
                'total' => $result['total'],
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/bundles/bulk
     *
     * Body: { "ids": [1, 2, 3] }
     */
    public function bulkDelete(BulkActionRequest $request, string $site): JsonResponse
    {
        try {
            $ids = $request->input('ids');

            if (!is_array($ids) || empty($ids)) {
                return $this->errorResponse('ids must be a non-empty array', 422);
            }

            $result = $this->bulkDeleteBundles->handle(array_map('intval', $ids));

            return $this->resourceResponse([
                'success' => true,
                'message' => sprintf('%d bundle(s) deleted successfully', count($result['deleted'])),
                'deleted' => $result['deleted'],
                'failed' => $result['failed'],
                'total' => $result['total'],
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}