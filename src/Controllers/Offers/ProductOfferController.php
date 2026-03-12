<?php

namespace App\Controllers\Offers;

use App\Actions\Offers\BulkDeleteOffers;
use App\Actions\Offers\BulkPublishOffers;
use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Framework\Support\SiteContext;
use App\Models\ProductOffer;
use App\Requests\BulkActionRequest;
use App\Requests\Offers\CreateProductOfferRequest;
use App\Requests\Offers\UpdateProductOfferRequest;
use App\Resources\OfferResource;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteriaParser;
use App\Search\SearchEngine;
use App\Services\Analytics\OfferAnalyticsService;
use App\Services\Offers\ProductOfferService;
use Exception;

class ProductOfferController extends Controller
{
    public function __construct(
        private readonly ProductOfferService   $offerService,
        private readonly OfferAnalyticsService $analyticsService,
        private readonly BulkPublishOffers     $bulkPublishOffers,
        private readonly BulkDeleteOffers      $bulkDeleteOffers,
    )
    {
        parent::__construct();
    }

    public function index(Request $request, int $productId, string $siteName)
    {
        $criteria = SearchCriteriaParser::fromRequest($request, $siteName);
        $configuration = SearchConfigurationFactory::create('product_offer');
        $engine = new SearchEngine($configuration);

        $queryBuilder = ProductOffer::where('product_id', $productId)->with(['merchant']);
        $result = $engine->search($queryBuilder, $criteria);

        $collection = new PaginatedResourceCollection($result, OfferResource::class);

        return $this->resourceResponse([
            'success' => true,
            'offers' => $collection->toArray(),
        ]);
    }

    public function categoryOffers(int $categoryId, string $siteName): JsonResponse
    {
        try {
            $offers = $this->offerService->getActiveOffersForCategory($categoryId);

            return $this->resourceResponse([
                'success' => true,
                'items' => $offers->toArray(),
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(int $productId, CreateProductOfferRequest $request, string $siteName): JsonResponse
    {
        try {
            $siteId = SiteContext::getId();
            $data = array_merge($request->validated(), ['product_id' => $productId]);
            $data['site_id'] = $siteId;
            $offer = $this->offerService->createOffer($data);

            if ($request->has('region_set_ids')) {
                $value = $request->get('region_set_ids');

                $ids = is_string($value)
                    ? json_decode($value, true)
                    : ($value ?? []);
                $offer->regionSets(true)->sync(array_map('intval', $ids));
                $offer->load(['regionSets']);
            }

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Offer created successfully',
                'offer' => $offer->toArray(),
            ], 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(int $productId, int $offerId, UpdateProductOfferRequest $request, string $siteName): JsonResponse
    {
        try {
            $siteId = SiteContext::getId();
            $data = $request->validated();
            $data['site_id'] = $siteId;
            unset($data['region_set_ids']);
            $offer = $this->offerService->updateOffer($offerId, $data);

            if (!$offer) {
                return $this->jsonResponse(['message' => 'Offer not found'], 404);
            }

            if ($request->has('region_set_ids')) {
                $value = $request->get('region_set_ids');

                $ids = is_string($value)
                    ? json_decode($value, true)
                    : ($value ?? []);
                $offer->regionSets(true)->sync(array_map('intval', $ids));

                $offer->load(['regionSets']);
            }

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Offer updated successfully',
                'offer' => $offer->toArray(),
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function destroy(int $productId, int $offerId, string $siteName): JsonResponse
    {
        try {
            $deleted = $this->offerService->deleteOffer($offerId);

            if (!$deleted) {
                return $this->jsonResponse(['message' => 'Offer not found'], 404);
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Offer deleted successfully',
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function publish(int $productId, int $offerId, string $siteName): JsonResponse
    {
        try {
            $userId = auth()->id() ?? 1;
            $offer = $this->offerService->publish($offerId, $userId);

            if (!$offer) {
                return $this->resourceResponse(['message' => 'Offer cannot be published'], 400);
            }

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Offer published successfully',
                'offer' => $offer->toArray(),
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function reject(int $productId, int $offerId, Request $request, string $siteName): JsonResponse
    {
        try {
            $userId = auth()->id() ?? 1;
            $reason = $request->input('reason');

            if (!$reason) {
                return $this->errorResponse('Rejection reason is required', 422);
            }

            $offer = $this->offerService->reject($offerId, $userId, $reason);

            if (!$offer) {
                return $this->resourceResponse(['message' => 'Offer cannot be rejected'], 400);
            }

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Offer rejected successfully',
                'offer' => $offer->toArray(),
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function allOffers(Request $request, string $siteName): JsonResponse
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request, $siteName);
            $configuration = SearchConfigurationFactory::create('product_offer');
            $engine = new SearchEngine($configuration);

            $queryBuilder = ProductOffer::with(['merchant', 'product']);
            $result = $engine->search($queryBuilder, $criteria);

            $collection = new PaginatedResourceCollection($result, OfferResource::class);

            return $this->resourceResponse([
                'success' => true,
                'offers' => $collection->toArray(),
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getStatistics(Request $request, string $siteName): JsonResponse
    {
        try {
            $siteId = SiteContext::getId();
            $stats = $this->analyticsService->getAllOfferStatistics($siteId);

            return $this->resourceResponse([
                'success' => true,
                'stats' => $stats,
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function trackClick(int $productId, int $offerId, Request $request): JsonResponse
    {
        try {
            $action = $request->input('action', 'view');
            $memberId = MemberAuth::id();
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent();

            $this->offerService->trackClick($offerId, $memberId, $action, $ipAddress, $userAgent);

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Click tracked successfully',
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // =========================================================================
    // Bulk actions
    // =========================================================================

    /**
     * POST /api/offers/bulk/publish
     *
     * Body: { "ids": [1, 2, 3] }
     */
    public function bulkPublish(Request $request, string $siteName): JsonResponse
    {
        try {
            $ids = $request->input('ids');

            if (!is_array($ids) || empty($ids)) {
                return $this->errorResponse('ids must be a non-empty array', 422);
            }

            $userId = auth()->id() ?? 1;
            $result = $this->bulkPublishOffers->handle(array_map('intval', $ids), $userId);

            return $this->resourceResponse([
                'success' => true,
                'message' => sprintf('%d offer(s) published successfully', count($result['published'])),
                'published' => $result['published'],
                'failed' => $result['failed'],
                'total' => $result['total'],
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/offers/bulk
     *
     * Body: { "ids": [1, 2, 3] }
     */
    public function bulkDelete(BulkActionRequest $request, string $siteName): JsonResponse
    {
        try {
            $ids = $request->input('ids');

            if (!is_array($ids) || empty($ids)) {
                return $this->errorResponse('ids must be a non-empty array', 422);
            }

            $result = $this->bulkDeleteOffers->handle(array_map('intval', $ids));

            return $this->resourceResponse([
                'success' => true,
                'message' => sprintf('%d offer(s) deleted successfully', count($result['deleted'])),
                'deleted' => $result['deleted'],
                'failed' => $result['failed'],
                'total' => $result['total'],
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}