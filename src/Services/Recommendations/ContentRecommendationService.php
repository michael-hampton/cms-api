<?php

namespace App\Services\Recommendations;

use App\Framework\Support\Collection;
use App\Models\Member;
use App\Models\Page;
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

    /**
     * Recirculation recommendations for a public page: related by shared
     * categories/tags, falling back to trending when nothing related is found.
     */
    public function forPage(Page $page, int $siteId, int $limit = 4): Collection
    {
        $related = $this->recommendationRepository->getRelatedForPage($page, $siteId, $limit);

        if ($related->count() > 0) {
            return $related;
        }

        return $this->trendingRepository->getTrendingPages($siteId, $limit)
            ->filter(static fn ($candidate): bool => (int) ($candidate->id ?? 0) !== (int) $page->id)
            ->take($limit)
            ->values();
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

    public function getLatestContent(int $siteId, int $limit, ?Member $member = null)
    {
        return $this->recommendationRepository->getRecentlyViewedArticles($siteId, $limit);
    }
}