<?php

namespace App\Controllers\Shopping;

use App\Controllers\Controller;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Resource\PaginatedResourceCollection;
use App\Framework\Support\SiteContext;
use App\Repositories\Shopping\GiftPromotionRepository;
use App\Requests\Promotions\GiftPromotionRequest;
use App\Resources\GiftPromotionResource;
use App\Search\SearchCriteriaParser;
use App\Services\Shopping\GiftPromotionService;
use Exception;

class GiftPromotionController extends Controller
{
    public function __construct(
        private readonly GiftPromotionService    $giftPromotionService,
        private readonly GiftPromotionRepository $giftPromotionRepository,
    )
    {
        parent::__construct();
    }

    public function index(Request $request, string $site): JsonResponse
    {
        try {
            $criteria = SearchCriteriaParser::fromRequest($request, $site);
            $result = $this->giftPromotionRepository->search($criteria);

            $collection = new PaginatedResourceCollection($result, GiftPromotionResource::class);

            return $this->resourceResponse($collection->toArray());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(GiftPromotionRequest $request, string $site): JsonResponse
    {
        try {
            $siteId = SiteContext::getId();
            $promotion = $this->giftPromotionService->create($siteId, $request->validated());

            return $this->jsonResponse(
                GiftPromotionResource::make($promotion)->toArray(), 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(int $id, GiftPromotionRequest $request, string $site): JsonResponse
    {
        try {
            $promotion = $this->giftPromotionService->update($id, $request->validated());

            return $this->jsonResponse(GiftPromotionResource::make($promotion)->toArray());
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function toggleActive(int $id, string $site): JsonResponse
    {
        try {
            $promotion = $this->giftPromotionService->toggleActive($id);

            return $this->jsonResponse(GiftPromotionResource::make($promotion)->toArray());
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function exclusions(int $id, string $site): JsonResponse
    {
        try {
            $promotion = $this->giftPromotionRepository->find($id);

            return $this->jsonResponse([
                'excluded_issue_ids' => $this->giftPromotionRepository->getExcludedIssueIds($id),
                'supports_exclusions' => $promotion->supportsIssueExclusions(),
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}