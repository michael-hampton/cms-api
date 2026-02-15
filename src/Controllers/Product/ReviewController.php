<?php

namespace App\Controllers\Product;

use App\Controllers\Controller;
use App\Framework\Http\Request;
use App\Services\Reviews\ReviewService;

class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewService $reviewService
    ) {
        parent::__construct();
    }

    public function index(Request $request, int $productId)
    {
        $page = (int)$request->input('page', 1);
        $perPage = (int)$request->input('per_page', 10);

        $data = $this->reviewService->getProductReviews($productId, $page, $perPage);

        return $this->resourceResponse($data);
    }

    public function store(Request $request, int $productId)
    {
        $data = [
            'rating' => $request->input('rating'),
            'title' => $request->input('title'),
            'comment' => $request->input('comment'),
        ];

        $result = $this->reviewService->createReview($productId, $data);

        $statusCode = $result->success ? 200 : 400;
        return $this->resourceResponse($result->toArray(), $statusCode);
    }

    public function update(Request $request, int $reviewId)
    {
        $data = [
            'rating' => $request->input('rating'),
            'title' => $request->input('title'),
            'comment' => $request->input('comment'),
        ];

        $result = $this->reviewService->updateReview($reviewId, $data);

        $statusCode = $result['success'] ? 200 : 400;
        return $this->resourceResponse($result, $statusCode);
    }

    public function destroy(int $reviewId)
    {
        $result = $this->reviewService->deleteReview($reviewId);

        $statusCode = $result['success'] ? 200 : 400;
        return $this->resourceResponse($result, $statusCode);
    }

    public function markHelpful(Request $request, int $reviewId)
    {
        $isHelpful = $request->input('is_helpful', true);

        $result = $this->reviewService->markReviewHelpful($reviewId, (bool)$isHelpful);

        return $this->resourceResponse($result);
    }

    public function statistics(int $productId)
    {
        $statistics = $this->reviewService->getReviewStatistics($productId);

        return $this->resourceResponse($statistics);
    }

    public function canReview(int $productId)
    {
        $result = $this->reviewService->canUserReview($productId);

        return $this->resourceResponse($result);
    }
}