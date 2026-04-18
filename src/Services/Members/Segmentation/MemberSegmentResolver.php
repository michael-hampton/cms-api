<?php

namespace App\Services\Members\Segmentation;

use App\Framework\Support\Collection;
use App\Models\Segment;

/**
 * Resolves which segments a member profile belongs to.
 *
 * Reads active segments + their rules from the DB.
 * Delegates rule evaluation to SegmentRuleEvaluator (pure, injectable).
 *
 * Returns segment keys only — no side effects.
 */
final class MemberSegmentResolver
{
    public function __construct(
        private readonly SegmentRuleEvaluator $evaluator,
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
        $segments = Segment::with(['rules' => fn($q) => $q->orderBy('sort_order')])
            ->where('is_active', true)
            ->get();

        $matched = [];

        foreach ($segments as $segment) {
            if ($this->evaluator->matches($profile, $segment->rules)) {
                $matched[] = $segment->key;
            }
        }

        return $matched;
    }
}