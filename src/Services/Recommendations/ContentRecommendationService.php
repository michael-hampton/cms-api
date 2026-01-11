<?php

namespace App\Services\Recommendations;

use App\Framework\Support\Collection;
use App\Models\Member;
use App\Repositories\Recommendations\ContentRecommendationRepository;
use App\Repositories\Recommendations\TrendingContentRepository;

class ContentRecommendationService
{
    public function __construct(
        private ContentRecommendationRepository $recommendationRepository,
        private TrendingContentRepository       $trendingRepository
    )
    {
    }

    public function getRecommendedForMember(Member $member, int $siteId, int $limit = 6): Collection
    {
        // Update preferences based on recent activity
        $this->recommendationRepository->updatePreferencesFromActivity($member->id, $siteId);

        // Get personalized recommendations
        return $this->recommendationRepository->getRecommendedPages($member->id, $siteId, $limit);
    }

    public function getTrendingContent(int $siteId, int $limit = 6): Collection
    {
        return $this->trendingRepository->getTrendingPages($siteId, $limit);
    }

    public function getTrendingConversations(int $siteId, int $limit = 6): Collection
    {
        return $this->trendingRepository->getTrendingConversations($siteId, $limit);
    }

    public function updateTrendingScores(int $siteId): void
    {
        $this->trendingRepository->calculateTrendingScores($siteId);
    }
}