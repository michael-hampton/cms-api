<?php

namespace App\Controllers\Product;

use App\Controllers\Controller;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Repositories\Product\MerchantProductFeedRepository;
use App\Requests\Merchant\CreateMerchantProductFeedRequest;
use App\Requests\Merchant\UpdateMerchantProductFeedRequest;
use App\Resources\MerchantProductFeedResource;
use App\Search\SearchCriteriaParser;
use App\Services\Product\MerchantProductFeedService;
use Exception;

class MerchantProductFeedController extends Controller
{
    public function __construct(
        private readonly MerchantProductFeedService    $feedService,
        private readonly MerchantProductFeedRepository $feedRepository
    )
    {
        parent::__construct();
    }

    public function index(Request $request, int $merchantId, string $site): JsonResponse
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request, $site);
            $criteria->addFilter('merchant_id', $merchantId);

            $result = $this->feedRepository->search($criteria);
            $collection = new PaginatedResourceCollection($result, MerchantProductFeedResource::class);

            return $this->resourceResponse($collection->toArray());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(CreateMerchantProductFeedRequest $request, int $merchantId, string $site): JsonResponse
    {
        try {
            $data = array_merge($request->validated(), ['merchant_id' => $merchantId]);
            $feed = $this->feedService->createFeed($data);

            return $this->jsonResponse([
                'message' => 'Feed created successfully',
                'feed' => MerchantProductFeedResource::make($feed)->toArray(),
            ], 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function show(int $merchantId, int $feedId): JsonResponse
    {
        try {
            $feed = $this->feedService->getFeed($feedId);

            if (!$feed || $feed->merchant_id !== $merchantId) {
                return $this->jsonResponse([
                    'message' => 'Feed not found'
                ], 404);
            }

            return $this->resourceResponse([
                'feed' => MerchantProductFeedResource::make($feed)->toArray(),
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(UpdateMerchantProductFeedRequest $request, int $merchantId, int $feedId, string $site): JsonResponse
    {
        try {
            $updated = $this->feedService->updateFeed($feedId, $request->validated());

            if (!$updated || $updated->merchant_id !== $merchantId) {
                return $this->jsonResponse([
                    'message' => 'Feed not found'
                ], 404);
            }

            return $this->resourceResponse([
                'message' => 'Feed updated successfully',
                'feed' => MerchantProductFeedResource::make($updated)->toArray(),
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function destroy(int $merchantId, int $feedId, string $site): JsonResponse
    {
        $feed = $this->feedService->getFeed($feedId);

        if (!$feed || $feed->merchant_id !== $merchantId) {
            return $this->jsonResponse([
                'message' => 'Feed not found'
            ], 404);
        }

        $deleted = $this->feedService->deleteFeed($feedId);

        return $this->jsonResponse([
            'message' => 'Feed deleted successfully'
        ], 200);
    }

    public function fetch(int $merchantId, int $feedId, string $site): JsonResponse
    {
        try {
            $feed = $this->feedService->getFeed($feedId);

            if (!$feed || $feed->merchant_id !== $merchantId) {
                return $this->jsonResponse([
                    'message' => 'Feed not found'
                ], 404);
            }

            $result = $this->feedService->fetchFeed($feedId);

            return $this->jsonResponse([
                'message' => 'Feed fetch initiated',
                'feed' => $result
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function download(int $merchantId, int $feedId, string $site): JsonResponse
    {
        try {
            $feed = $this->feedService->getFeed($feedId);

            if (!$feed || $feed->merchant_id !== $merchantId) {
                return $this->jsonResponse([
                    'message' => 'Feed not found'
                ], 404);
            }

            $data = $this->feedService->downloadFeedData($feedId);

            return $this->jsonResponse([
                'success' => true,
                'data' => $data
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}