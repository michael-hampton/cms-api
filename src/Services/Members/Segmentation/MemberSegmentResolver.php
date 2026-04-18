<?php

namespace App\Services\Members\Segmentation;

use App\Framework\Support\Collection;
use App\Models\Segment;
use App\Repositories\Members\SegmentRepository;

/**
 * Resolves which segments a member profile belongs to.
 *
 * Reads active segments + their rules from the DB.
 * Delegates rule evaluation to SegmentRuleEvaluator (pure, injectable).
 *
 * Returns segment keys only — no side effects.
 */
class MemberSegmentResolver
{
    public function __construct(
        private readonly SegmentRuleEvaluator $evaluator,
        private readonly SegmentRepository $segmentRepository,
    )
    {
    }

    /**
     * @param array<string, mixed> $profile The member's profile snapshot data
     * @return string[]  Matched segment keys, e.g. ['churning', 'lurker']
     */
    public function resolve(array $profile): array
    {
        /** @var Collection<Segment> $segments */
        $segments = $this->segmentRepository->getActiveWithRules();

        $matched = [];

        foreach ($segments as $segment) {
            if ($this->evaluator->matches($profile, $segment->rules)) {
                $matched[] = $segment->key;
            }
        }

        return $matched;
    }
}
