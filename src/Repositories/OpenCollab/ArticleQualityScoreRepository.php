<?php

namespace App\Repositories\OpenCollab;

use App\Models\ArticleQualityScore;
use App\Repositories\Repository;

class ArticleQualityScoreRepository extends Repository
{
    /**
     * Creates or updates the score for a page.
     */
    public function upsert(int $pageId, float $score): ArticleQualityScore
    {
        $existing = $this->findByPageId($pageId);

        if ($existing) {
            $existing->update([
                'readability_score' => $score,
                'last_calculated_at' => date('Y-m-d H:i:s'),
            ]);
            return $existing;
        }

        return $this->create([
            'article_id' => $pageId,
            'readability_score' => $score,
            'last_calculated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function findByPageId(int $pageId): ?ArticleQualityScore
    {
        /** @var ArticleQualityScore|null */
        return ArticleQualityScore::where('article_id', $pageId)->first();
    }

    protected function getModelClass(): string
    {
        return ArticleQualityScore::class;
    }
}