<?php

namespace App\Console;

use App\Framework\Console\Command;
use App\Framework\Console\ReportsCommandResult;
use App\Models\Site;
use App\Services\Recommendations\ContentRecommendationService;

class CalculateTrendingContent extends Command
{
    use ReportsCommandResult;

    const SUCCESS = 1;
    const FAILURE = 0;

    protected $signature = 'content:calculate-trending';
    public $description = 'Calculates trending scores for all active sites.';

    public function __construct(
        private readonly ContentRecommendationService $contentRecommendationService
    )
    {
    }

    public function handle(): int
    {
        $result = $this->createResult('content:calculate-trending');
        $sites = Site::where('is_active', true)->get();

        if ($sites->isEmpty()) {
            $this->info('No active sites found.');
            return self::SUCCESS;
        }

        foreach ($sites as $site) {
            try {
                $this->contentRecommendationService->updateTrendingScores($site->id);

                $result->incrementSucceeded();
                $result->addMessage("Updated trending scores for site: {$site->name}");
            } catch (\Throwable $e) {
                $this->reportFailure(
                    result: $result,
                    message: "Failed to update trending for site {$site->name}: {$e->getMessage()}",
                    context: ['site_id' => $site->id],
                    throwable: $e
                );
            }
        }

        $this->reportResult($result);
        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }
}