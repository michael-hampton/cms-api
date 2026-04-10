<?php

namespace App\Services\OpenCollab;

use App\Models\ArticleQualityScore;
use App\Repositories\OpenCollab\ArticleQualityScoreRepository;

/**
 * Orchestrates readability analysis and result persistence.
 *
 * Called after every article save (draft or publish).
 * Non-critical: failures here must never block the save operation.
 * Callers should wrap calls in try/catch and log, not rethrow.
 */
class ReadabilityService
{
    public function __construct(
        private readonly ReadabilityAnalyser           $analyser,
        private readonly ArticleQualityScoreRepository $scoreRepository,
    )
    {
    }

    /**
     * Calculates and persists the readability score for a page.
     * Returns the updated/created score model.
     */
    public function scoreArticle(int $pageId, string $rawContent): ArticleQualityScore
    {
        $score = $this->analyser->analyse($rawContent);

        return $this->scoreRepository->upsert($pageId, $score);
    }

    /**
     * Returns the stored score for a page, or null if not yet scored.
     */
    public function getScore(int $pageId): ?ArticleQualityScore
    {
        return $this->scoreRepository->findByPageId($pageId);
    }
}