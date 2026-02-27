<?php

namespace App\Services\Reviews;

use App\DTO\Reviews\CreateReviewDTO;
use App\DTO\Reviews\ReviewActionContext;
use App\DTO\Reviews\ReviewResult;
use App\DTO\Reviews\UpdateReviewDTO;
use App\Framework\Authorization\MemberAuthWrapper;
use App\Framework\Support\SiteContext;
use Exception;

class ReviewService
{
    public function __construct(
        private readonly ReviewCommandService $commandService,
        private readonly ReviewQueryService   $queryService,
        private readonly ReviewVoteService    $voteService,
        private readonly MemberAuthWrapper $authService,
    ) {}

    // ─── Product reviews (backwards compatible) ────────────────────────────

    public function getProductReviews(int $productId, int $page = 1, int $perPage = 10): array
    {
        $reviews = $this->queryService->getPaginatedProductReviews($productId, $page, $perPage);
        $summary = $this->queryService->getReviewSummary($productId);
        return array_merge($reviews, $summary->toArray());
    }

    public function createReview(int $productId, array $data, ?int $siteId = null): ReviewResult
    {
        $userId = $this->authService->memberId();
        $siteId = $siteId ?? SiteContext::getId();

        if (!$userId) {
            throw new Exception('You must be logged in to submit a review');
        }

        $dto = CreateReviewDTO::fromArray(
            array_merge($data, ['product_id' => $productId]),
            $userId,
            $siteId
        );

        return $this->commandService->createReview($dto, $userId);
    }

    // ─── Plan reviews ──────────────────────────────────────────────────────

    public function getPlanReviews(int $planId, int $page = 1, int $perPage = 10): array
    {
        $reviews = $this->queryService->getPaginatedPlanReviews($planId, $page, $perPage);
        $summary = $this->queryService->getPlanReviewSummary($planId);
        return array_merge($reviews, $summary->toArray());
    }

    public function createPlanReview(int $planId, array $data, ?int $siteId = null): ReviewResult
    {
        $userId = $this->authService->memberId();
        $siteId = $siteId ?? SiteContext::getId();

        if (!$userId) {
            throw new Exception('You must be logged in to submit a review');
        }

        $dto = CreateReviewDTO::fromArray(
            array_merge($data, ['plan_id' => $planId]),
            $userId,
            $siteId
        );

        return $this->commandService->createReview($dto, $userId);
    }

    public function canUserReviewPlan(int $planId): array
    {
        return $this->queryService->canUserReviewPlan($planId, $this->authService->memberId());
    }

    public function getPlanReviewSummary(int $planId): array
    {
        return $this->queryService->getPlanReviewSummary($planId)->toArray();
    }

    // ─── Shared methods ────────────────────────────────────────────────────

    public function updateReview(int $reviewId, array $data): array
    {
        $userId = $this->authService->memberId();

        if (!$userId) {
            return ['success' => false, 'message' => 'You must be logged in'];
        }

        $dto = UpdateReviewDTO::fromArray($data);
        $result = $this->commandService->updateReview($reviewId, $dto, $userId);
        return $result->toArray();
    }

    public function deleteReview(int $reviewId): array
    {
        $userId = $this->authService->memberId();

        if (!$userId) {
            return ['success' => false, 'message' => 'You must be logged in'];
        }

        $result = $this->commandService->deleteReview($reviewId, $userId);
        return $result->toArray();
    }

    public function markReviewHelpful(int $reviewId, bool $isHelpful, ?int $siteId = null): array
    {
        $context = ReviewActionContext::fromAuth(
            $this->authService->memberId(),
            $this->getSessionId(),
            $siteId ?? SiteContext::getId()
        );

        $result = $this->voteService->markReviewHelpful($reviewId, $isHelpful, $context);
        return $result->toArray();
    }

    public function getReviewStatistics(int $productId): array
    {
        return $this->queryService->getReviewSummary($productId)->toArray();
    }

    public function canUserReview(int $productId): array
    {
        return $this->queryService->canUserReview($productId, $this->authService->memberId());
    }

    // ─── Private ──────────────────────────────────────────────────────────

    private function getSessionId(): string
    {
        if (!isset($_SESSION['review_session_id'])) {
            $_SESSION['review_session_id'] = uniqid('review_', true);
        }
        return $_SESSION['review_session_id'];
    }
}