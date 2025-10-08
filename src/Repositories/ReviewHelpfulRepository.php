<?php

namespace App\Repositories;

use App\Models\Model;
use App\Models\ReviewHelpful;

class ReviewHelpfulRepository extends Repository
{
    protected function getModelClass(): string
    {
        return ReviewHelpful::class;
    }

    public function hasUserVoted(int $reviewId, ?int $userId, string $sessionId): bool
    {
        $query = $this->model->query()
            ->where('review_id', $reviewId);

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('session_id', $sessionId);
        }

        return $query->exists();
    }

    public function getUserVote(int $reviewId, ?int $userId, string $sessionId): ?Model
    {
        $query = $this->model->query()
            ->where('review_id', $reviewId);

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('session_id', $sessionId);
        }

        return $query->first();
    }
}