<?php

namespace App\Services\MemberInsights\Newsletters\Recommendations;

use App\Enums\MemberInsights\Newsletters\NewsletterRelationType;
use App\Models\Member;
use App\Repositories\MemberInsights\Newsletters\NewsletterRelationRepository;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Services\MemberInsights\Newsletters\Suppression\SuppressionSet;

final class NewsletterRecommendationService
{
    private const DEFAULT_MAX_RESULTS = 5;

    public function __construct(
        private readonly NewsletterRelationRepository $relationRepository,
        private readonly NewsletterRepository         $newsletterRepository,
    )
    {
    }

    /**
     * Recommend newsletters for the member, excluding suppressed ones.
     *
     * @param Member $member
     * @param SuppressionSet $suppression Built by NewsletterSuppressionService.
     * @param int $siteId
     * @param int $maxResults Max recommendations to return (default 5).
     * @return RecommendationResult[]
     */
    public function recommend(
        Member         $member,
        SuppressionSet $suppression,
        int            $siteId,
        int            $maxResults = self::DEFAULT_MAX_RESULTS,
    ): array
    {
        if ($suppression->isEmpty()) {
            return $this->fallback($suppression, $siteId, $maxResults);
        }

        $relations = $this->relationRepository->findRelatedTo(
            newsletterIds: $suppression->ids(),
            siteId: $siteId,
        );

        // Score and deduplicate — one row per target newsletter, highest score wins.
        $scored = [];

        foreach ($relations as $relation) {
            $targetId = (int)$relation->related_newsletter_id;

            if ($suppression->contains($targetId)) {
                continue;
            }

            $relationType = $relation->relation_type instanceof NewsletterRelationType
                ? $relation->relation_type
                : NewsletterRelationType::from($relation->relation_type);

            $score = $relationType->score();

            if (!isset($scored[$targetId]) || $score > $scored[$targetId]['score']) {
                $scored[$targetId] = [
                    'newsletter' => $relation->relatedNewsletter,
                    'relation_type' => $relationType,
                    'score' => $score,
                    'source_newsletter_title' => $relation->sourceNewsletter?->title ?? '',
                ];
            }
        }

        if (empty($scored)) {
            return $this->fallback($suppression, $siteId, $maxResults);
        }

        // Sort descending by score — deterministic for identical scores via id.
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score'] ?: $a['newsletter']->id <=> $b['newsletter']->id
        );

        return array_map(
            fn(array $row) => new RecommendationResult(
                newsletter: $row['newsletter'],
                reason: $this->buildReason($row['relation_type'], $row['source_newsletter_title']),
                score: $row['score'],
            ),
            array_slice($scored, 0, $maxResults),
        );
    }

    // ── Private ───────────────────────────────────────────────────────────

    /**
     * Fallback when no relation-based candidates exist.
     *
     * Returns active newsletters the member is not subscribed to,
     * ordered by most recently active (last_sent desc). Deterministic,
     * no ranking involved.
     *
     * @return RecommendationResult[]
     */
    private function fallback(SuppressionSet $suppression, int $siteId, int $maxResults): array
    {
        $active = $this->newsletterRepository->getActive($siteId);

        return $active
            ->filter(fn($nl) => !$suppression->contains((int)$nl->id))
            ->sortByDesc('last_sent')
            ->take($maxResults)
            ->map(fn($nl) => new RecommendationResult(
                newsletter: $nl,
                reason: 'Popular newsletter',
                score: 0,
            ))
            ->values()
            ->all();
    }

    private function buildReason(NewsletterRelationType $type, string $sourceTitle): string
    {
        $base = $sourceTitle ? "Because you subscribe to {$sourceTitle}" : 'Recommended for you';

        return match ($type) {
            NewsletterRelationType::UpsellPremium => "{$base} — premium edition",
            NewsletterRelationType::SameBrand => "{$base} — same publisher",
            NewsletterRelationType::SameCategory => "{$base} — same topic",
            NewsletterRelationType::ComplementaryTopic => "{$base} — complementary reading",
        };
    }
}