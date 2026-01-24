<?php

namespace App\Controllers;

use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Repositories\Product\MerchantProductFeedRepository;
use App\Requests\CreateMerchantProductFeedRequest;
use App\Requests\UpdateMerchantProductFeedRequest;
use App\Services\Product\MerchantProductFeedService;
use Exception;

class MerchantProductFeedController extends Controller
{
    protected MerchantProductFeedService $feedService;
    private MerchantProductFeedRepository $feedRepository;

    public function __construct(
        MerchantProductFeedService    $feedService,
        MerchantProductFeedRepository $feedRepository
    )
    {
        $this->feedService = $feedService;
        $this->feedRepository = $feedRepository;
        parent::__construct();
    }

    public function index(int $merchantId, string $siteName): JsonResponse
    {
        try {
            $feeds = $this->feedRepository->getByMerchant($merchantId);

            return $this->resourceResponse([
                'success' => true,
                'items' => $feeds->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(CreateMerchantProductFeedRequest $request, int $merchantId, string $siteName): JsonResponse
    {
        try {
            $data = array_merge($request->validated(), ['merchant_id' => $merchantId]);
            $feed = $this->feedService->createFeed($data);

            return $this->jsonResponse([
                'message' => 'Feed created successfully',
                'feed' => $feed->toArray()
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

    public function show(int $merchantId, int $feedId): JsonResponse
    {
        $feed = $this->feedService->getFeed($feedId);

        if (!$feed || $feed->merchant_id !== $merchantId) {
            return $this->jsonResponse([
                'message' => 'Feed not found'
            ], 404);
        }

        return $this->jsonResponse(['feed' => $feed]);
    }

    public function update(UpdateMerchantProductFeedRequest $request, int $merchantId, int $feedId, string $siteName): JsonResponse
    {
        try {
            $updated = $this->feedService->updateFeed($feedId, $request->validated());

            if (!$updated || $updated->merchant_id !== $merchantId) {
                return $this->jsonResponse([
                    'message' => 'Feed not found'
                ], 404);
            }

            return $this->jsonResponse([
                'message' => 'Feed updated successfully',
                'feed' => $updated
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

    public function destroy(int $merchantId, int $feedId, string $siteName): JsonResponse
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

    public function fetch(int $merchantId, int $feedId, string $siteName): JsonResponse
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

    public function download(int $merchantId, int $feedId, string $siteName): JsonResponse
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