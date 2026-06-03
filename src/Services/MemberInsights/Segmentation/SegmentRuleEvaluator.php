<?php

namespace App\Services\MemberInsights\Segmentation;


use App\Enums\Member\SegmentRuleBoolean;
use App\Enums\Member\SegmentRuleOperator;
use App\Framework\Support\Collection;
use App\Models\SegmentRule;

/**
 * Evaluates a set of segment rules against a flat profile array.
 *
 * Stateless — no DB access, no side effects.
 * Accepts a Collection of SegmentRule models ordered by sort_order.
 *
 * Rule combining semantics:
 *   - The first rule always seeds the result.
 *   - Each subsequent rule's `boolean` value describes how it combines
 *     with the accumulator: AND narrows, OR broadens.
 */
class SegmentRuleEvaluator
{
    /**
     * @param array<string, mixed> $profile Nested profile array
     * @param Collection<SegmentRule> $rules
     */
    public function matches(array $profile, Collection $rules): bool
    {
        if ($rules->isEmpty()) {
            return false;
        }

        $result = null;

        foreach ($rules as $rule) {
            $actual = data_get($profile, $rule->field);

            $expected = $rule instanceof SegmentRule
                ? $rule->decodedValue()
                : SegmentRule::decodeValue($rule->value ?? null);

            $operator = $rule->operator instanceof SegmentRuleOperator
                ? $rule->operator
                : SegmentRuleOperator::from((string)$rule->operator);

            $match = $operator->compare($actual, $expected);

            if ($result === null) {
                $result = $match;
                continue;
            }

            $boolean = $rule->boolean instanceof SegmentRuleBoolean
                ? $rule->boolean
                : SegmentRuleBoolean::from((string)$rule->boolean);

            $result = $boolean === SegmentRuleBoolean::AND
                ? $result && $match
                : $result || $match;
        }

        return $result ?? false;
    }
}
