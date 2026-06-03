<?php

namespace App\Services\MemberInsights\Segmentation;

use App\Enums\Member\SubscriptionRuleOperator;
use App\Enums\Member\SegmentRuleBoolean;
use App\Models\Segment;
use App\Models\SegmentRule;
use App\Models\Subscription;

/**
 * Evaluates a Subscription against the rules of a Segment.
 *
 * Design contract:
 *   - Stateless: no DB access, no side effects.
 *   - Accepts a Subscription model and a Segment model with rules loaded.
 *   - Returns true/false — never throws on invalid data (fails safe to false).
 *   - Rule combining follows the same AND/OR semantics as SegmentRuleEvaluator.
 *
 * Subscription data is accessed via $subscription->toArray() so rules can use
 * dot-notation paths into the subscription's attributes and any eager-loaded
 * relations.
 *
 * All date comparisons are relative to "now" at the time of evaluation.
 * Inject a DateTimeImmutable via the constructor for deterministic testing.
 */
class SegmentRuleEngine
{
    public function __construct(
        private readonly \DateTimeImmutable $now,
    ) {
    }

    /**
     * Returns true if the subscription matches all/any rules of the segment
     * according to the AND/OR boolean chain.
     */
    public function matches(Subscription $subscription, Segment $segment): bool
    {
        $rules = $segment->rules;

        if ($rules === null || $rules->isEmpty()) {
            return false;
        }

        $data = $subscription->toArray();

        $relations = method_exists($subscription, 'getRelations')
            ? $subscription->getRelations()
            : [];

        if (isset($relations['plan']) && !isset($data['plan'])) {
            $data['plan'] = $relations['plan']->toArray();
        }

        $result = null;

        foreach ($rules as $rule) {

            $actual = data_get($data, $rule->field);

            $match = $this->evaluate(
                $rule->operator,
                $actual,
                $rule instanceof SegmentRule
                    ? $rule->decodedValue()
                    : SegmentRule::decodeValue($rule->value ?? null)
            );

            if ($result === null) {
                $result = $match;
                continue;
            }

            $boolean = $rule->boolean instanceof SegmentRuleBoolean
                ? $rule->boolean
                : SegmentRuleBoolean::tryFrom((string) $rule->boolean);

            $result = ($boolean === SegmentRuleBoolean::AND)
                ? $result && $match
                : $result || $match;
        }

        return $result ?? false;
    }

    // -------------------------------------------------------------------------
    // Operator evaluation
    // -------------------------------------------------------------------------

    /**
     * Evaluate a single rule. Fails safe (returns false) on invalid input.
     *
     * @param  string|SubscriptionRuleOperator $operator
     * @param  mixed                           $actual
     * @param  mixed                           $expected
     */
    private function evaluate(mixed $operator, mixed $actual, mixed $expected): bool
    {
        $op = $this->resolveOperator($operator);

        if ($op === null) {
            // Unknown operator — fail safe.
            return false;
        }

        return match ($op) {
            SubscriptionRuleOperator::Equals         => $this->equals($actual, $expected),
            SubscriptionRuleOperator::NotEquals      => !$this->equals($actual, $expected),
            SubscriptionRuleOperator::GreaterThan    => is_numeric($actual) && is_numeric($expected) && $actual > $expected,
            SubscriptionRuleOperator::LessThan       => is_numeric($actual) && is_numeric($expected) && $actual < $expected,
            SubscriptionRuleOperator::Between        => $this->between($actual, $expected),
            SubscriptionRuleOperator::Contains       => is_array($actual) && in_array($expected, $actual, true),
            SubscriptionRuleOperator::In             => is_array($expected) && in_array($actual, $expected, true),
            SubscriptionRuleOperator::NotIn          => is_array($expected) && !in_array($actual, $expected, true),
            SubscriptionRuleOperator::Before         => $this->compareDates($actual, $expected) < 0,
            SubscriptionRuleOperator::After          => $this->compareDates($actual, $expected) > 0,
            SubscriptionRuleOperator::WithinNextDays => $this->withinNextDays($actual, $expected),
        };
    }

    private function equals(mixed $actual, mixed $expected): bool
    {
        // Loose equality for mixed types (e.g. "10" == 10 in rule context).
        return $actual == $expected;
    }

    /**
     * Between: $expected must be [min, max].
     */
    private function between(mixed $actual, mixed $expected): bool
    {
        if (!is_array($expected) || count($expected) !== 2) {
            return false;
        }

        [$min, $max] = $expected;

        if (is_numeric($actual) && is_numeric($min) && is_numeric($max)) {
            return $actual >= $min && $actual <= $max;
        }

        // Date between
        try {
            $actualDate = $this->parseDate($actual);
            $minDate    = $this->parseDate($min);
            $maxDate    = $this->parseDate($max);

            return $actualDate >= $minDate && $actualDate <= $maxDate;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Compare two date values. Returns negative/zero/positive like spaceship.
     * Fails safe by returning 0 on parse failure.
     */
    private function compareDates(mixed $actual, mixed $expected): int
    {
        try {
            $a = $this->parseDate($actual);
            $b = $this->parseDate($expected);

            return $a <=> $b;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * within_next_days: $expected is the number of days from now.
     * Returns true if $actual is between today and now + N days (inclusive).
     */
    private function withinNextDays(mixed $actual, mixed $expected): bool
    {
        if (!is_numeric($expected) || $expected < 0) {
            return false;
        }

        try {
            $date    = $this->parseDate($actual);
            $ceiling = $this->now->modify("+{$expected} days");

            return $date >= $this->now && $date <= $ceiling;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @throws \Exception if $value cannot be parsed as a date.
     */
    private function parseDate(mixed $value): \DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value)) {
            return new \DateTimeImmutable($value);
        }

        throw new \InvalidArgumentException("Cannot parse date from: " . gettype($value));
    }

    private function resolveOperator(mixed $operator): ?SubscriptionRuleOperator
    {
        if ($operator instanceof SubscriptionRuleOperator) {
            return $operator;
        }

        return match ((string) $operator) {
            '=', 'equals'                   => SubscriptionRuleOperator::Equals,
            '!=', '<>', 'not_equals'        => SubscriptionRuleOperator::NotEquals,
            '>', 'greater_than'             => SubscriptionRuleOperator::GreaterThan,
            '<', 'less_than'                => SubscriptionRuleOperator::LessThan,
            'between'                       => SubscriptionRuleOperator::Between,
            'contains'                      => SubscriptionRuleOperator::Contains,
            'in'                            => SubscriptionRuleOperator::In,
            'not_in'                        => SubscriptionRuleOperator::NotIn,
            'before'                        => SubscriptionRuleOperator::Before,
            'after'                         => SubscriptionRuleOperator::After,
            'within_next_days'              => SubscriptionRuleOperator::WithinNextDays,
            default                         => null,
        };
    }
}
