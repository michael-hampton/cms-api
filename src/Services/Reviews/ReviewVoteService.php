<?php

namespace App\Services\Reviews;

use App\DTO\Reviews\ReviewActionContext;
use App\DTO\Reviews\ReviewResult;
use App\Framework\Database\Database;
use App\Repositories\ReviewHelpfulRepository;
use App\Repositories\ReviewRepository;

class ReviewVoteService
{
    public function __construct(
        private readonly Database                $database,
        private readonly ReviewRepository        $reviewRepository,
        private readonly ReviewHelpfulRepository $reviewHelpfulRepository
    )
    {
    }

    public function markReviewHelpful(
        int                 $reviewId,
        bool                $isHelpful,
        ReviewActionContext $context
    ): ReviewResult
    {
        $review = $this->reviewRepository->find($reviewId);

        if (!$review) {
            return ReviewResult::failure('Review not found');
        }

        return $this->database->transaction(function () use ($reviewId, $isHelpful, $context, $review) {
            $existingVote = $this->reviewHelpfulRepository->getUserVote(
                $reviewId,
                $context->memberId,
                $context->sessionId
            );

            if ($existingVote) {
                return $this->handleExistingVote($existingVote, $isHelpful, $review);
            }

            return $this->createNewVote($reviewId, $isHelpful, $context, $review);
        });
    }

    private function handleExistingVote($existingVote, bool $isHelpful, $review): ReviewResult
    {
        if ($existingVote->is_helpful === $isHelpful) {
            // Remove vote
            if ($isHelpful) {
                $review->update(['helpful_count' => max(0, $review->helpful_count - 1)]);
            } else {
                $review->update(['unhelpful_count' => max(0, $review->unhelpful_count - 1)]);
            }

            $this->reviewHelpfulRepository->delete($existingVote->id);

            return ReviewResult::success(
                'Vote removed',
                null,
                [
                    'helpful_count' => $review->helpful_count,
                    'unhelpful_count' => $review->unhelpful_count
                ]
            );
        } else {
            // Change vote
            if ($isHelpful) {
                $review->update([
                    'helpful_count' => $review->helpful_count + 1,
                    'unhelpful_count' => max(0, $review->unhelpful_count - 1)
                ]);
            } else {
                $review->update([
                    'helpful_count' => max(0, $review->helpful_count - 1),
                    'unhelpful_count' => $review->unhelpful_count + 1
                ]);
            }

            $this->reviewHelpfulRepository->update($existingVote->id, [
                'is_helpful' => $isHelpful
            ]);

            return ReviewResult::success(
                'Vote updated',
                null,
                [
                    'helpful_count' => $review->helpful_count,
                    'unhelpful_count' => $review->unhelpful_count
                ]
            );
        }
    }

    private function createNewVote(int $reviewId, bool $isHelpful, ReviewActionContext $context, $review): ReviewResult
    {
        $this->reviewHelpfulRepository->create([
            'review_id' => $reviewId,
            'user_id' => $context->memberId,
            'session_id' => $context->sessionId,
            'is_helpful' => $isHelpful,
            'site_id' => $review->site_id,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s')
        ]);

        if ($isHelpful) {
            $this->reviewRepository->incrementHelpfulCount($reviewId);
        } else {
            $this->reviewRepository->incrementUnhelpfulCount($reviewId);
        }

        $review = $this->reviewRepository->find($reviewId);

        return ReviewResult::success(
            'Thank you for your feedback',
            null,
            [
                'helpful_count' => $review->helpful_count,
                'unhelpful_count' => $review->unhelpful_count
            ]
        );
    }
}