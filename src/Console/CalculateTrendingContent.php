<?php

namespace App\Console;

use App\Models\Site;
use App\Services\Recommendations\ContentRecommendationService;

class CalculateTrendingContent
{
    public function __construct(
        private ContentRecommendationService $contentRecommendationService
    )
    {
    }

    public function handle(): void
    {
        $sites = Site::where('is_active', true)->get();

        foreach ($sites as $site) {
            try {
                $this->contentRecommendationService->updateTrendingScores($site->id);
                echo "Updated trending scores for site: {$site->name}\n";
            } catch (\Exception $e) {
                echo "Failed to update trending for site {$site->name}: {$e->getMessage()}\n";
            }
        }
    }
}