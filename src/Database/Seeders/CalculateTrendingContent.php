<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\Site;
use App\Repositories\Recommendations\ContentRecommendationRepository;
use App\Repositories\Recommendations\TrendingContentRepository;
use App\Services\Recommendations\ContentRecommendationService;

class CalculateTrendingContent extends Seeder
{

    public function run(): void
    {
        $contentRecommendationService = new ContentRecommendationService(
            new ContentRecommendationRepository(),
            new TrendingContentRepository()
        );

        $sites = Site::where('is_active', true)->get();

        foreach ($sites as $site) {
            try {
                $contentRecommendationService->updateTrendingScores($site->id);
                echo "Updated trending scores for site: {$site->name}\n";
            } catch (\Exception $e) {
                echo "Failed to update trending for site {$site->name}: {$e->getMessage()}\n";
            }
        }
    }
}