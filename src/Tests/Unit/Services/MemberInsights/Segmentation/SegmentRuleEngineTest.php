<?php

namespace App\Tests\Unit\Services\MemberInsights\Segmentation;

use App\Enums\Member\SubscriptionRuleOperator;
use App\Models\Segment;
use App\Models\Subscription;
use App\Services\MemberInsights\Segmentation\SegmentRuleEngine;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class SegmentRuleEngineTest extends TestCase
{
    /** A fixed "now" for deterministic date tests */
    private \DateTimeImmutable $now;
    private SegmentRuleEngine $engine;

    // =========================================================================
    // Empty rules
    // =========================================================================

    public function test_empty_rules_returns_false(): void
    {
        $subscription = $this->makeSubscription([]);
        $segment      = $this->makeSegment([]);

        $this->assertFalse($this->engine->matches($subscription, $segment));
    }

    // =========================================================================
    // equals / not_equals
    // =========================================================================

    public function test_equals_matches_string(): void
    {
        $subscription = $this->makeSubscription(['payment_type' => 'direct_debit']);
        $segment      = $this->makeSegment([
            ['field' => 'payment_type', 'operator' => 'equals', 'value' => 'direct_debit'],
        ]);

        $this->assertTrue($this->engine->matches($subscription, $segment));
    }

    public function test_equals_does_not_match_different_string(): void
    {
        $subscription = $this->makeSubscription(['payment_type' => 'card']);
        $segment      = $this->makeSegment([
            ['field' => 'payment_type', 'operator' => 'equals', 'value' => 'direct_debit'],
        ]);

        $this->assertFalse($this->engine->matches($subscription, $segment));
    }

    public function test_not_equals_matches_when_values_differ(): void
    {
        $subscription = $this->makeSubscription(['status' => 'cancelled']);
        $segment      = $this->makeSegment([
            ['field' => 'status', 'operator' => 'not_equals', 'value' => 'active'],
        ]);

        $this->assertTrue($this->engine->matches($subscription, $segment));
    }

    public function test_not_equals_does_not_match_when_values_are_same(): void
    {
        $subscription = $this->makeSubscription(['status' => 'active']);
        $segment      = $this->makeSegment([
            ['field' => 'status', 'operator' => 'not_equals', 'value' => 'active'],
        ]);

        $this->assertFalse($this->engine->matches($subscription, $segment));
    }

    // =========================================================================
    // greater_than / less_than
    // =========================================================================

    public function test_greater_than_matches(): void
    {
        $subscription = $this->makeSubscription(['price' => 50]);
        $segment      = $this->makeSegment([
            ['field' => 'price', 'operator' => 'greater_than', 'value' => 30],
        ]);

        $this->assertTrue($this->engine->matches($subscription, $segment));
    }

    public function test_greater_than_does_not_match_equal(): void
    {
        $subscription = $this->makeSubscription(['price' => 30]);
        $segment      = $this->makeSegment([
            ['field' => 'price', 'operator' => 'greater_than', 'value' => 30],
        ]);

        $this->assertFalse($this->engine->matches($subscription, $segment));
    }

    public function test_less_than_matches(): void
    {
        $subscription = $this->makeSubscription(['price' => 10]);
        $segment      = $this->makeSegment([
            ['field' => 'price', 'operator' => 'less_than', 'value' => 20],
        ]);

        $this->assertTrue($this->engine->matches($subscription, $segment));
    }

    // =========================================================================
    // between
    // =========================================================================

    public function test_between_numeric_matches_when_in_range(): void
    {
        $subscription = $this->makeSubscription(['price' => 15]);
        $segment      = $this->makeSegment([
            ['field' => 'price', 'operator' => 'between', 'value' => [10, 20]],
        ]);

        $this->assertTrue($this->engine->matches($subscription, $segment));
    }

    public function test_between_numeric_does_not_match_outside_range(): void
    {
        $subscription = $this->makeSubscription(['price' => 25]);
        $segment      = $this->makeSegment([
            ['field' => 'price', 'operator' => 'between', 'value' => [10, 20]],
        ]);

        $this->assertFalse($this->engine->matches($subscription, $segment));
    }

    public function test_between_is_inclusive_on_boundaries(): void
    {
        $subscription = $this->makeSubscription(['price' => 10]);
        $segment      = $this->makeSegment([
            ['field' => 'price', 'operator' => 'between', 'value' => [10, 20]],
        ]);

        $this->assertTrue($this->engine->matches($subscription, $segment));
    }

    // =========================================================================
    // contains / in / not_in
    // =========================================================================

    public function test_contains_matches_when_value_is_in_array(): void
    {
        $subscription = $this->makeSubscription(['tags' => ['renewal', 'at_risk']]);
        $segment      = $this->makeSegment([
            ['field' => 'tags', 'operator' => 'contains', 'value' => 'at_risk'],
        ]);

        $this->assertTrue($this->engine->matches($subscription, $segment));
    }

    public function test_contains_does_not_match_absent_value(): void
    {
        $subscription = $this->makeSubscription(['tags' => ['renewal']]);
        $segment      = $this->makeSegment([
            ['field' => 'tags', 'operator' => 'contains', 'value' => 'at_risk'],
        ]);

        $this->assertFalse($this->engine->matches($subscription, $segment));
    }

    public function test_in_operator_matches_when_actual_in_list(): void
    {
        $subscription = $this->makeSubscription(['payment_type' => 'direct_debit']);
        $segment      = $this->makeSegment([
            ['field' => 'payment_type', 'operator' => 'in', 'value' => ['direct_debit', 'invoice']],
        ]);

        $this->assertTrue($this->engine->matches($subscription, $segment));
    }

    public function test_not_in_operator_matches_when_actual_not_in_list(): void
    {
        $subscription = $this->makeSubscription(['payment_type' => 'card']);
        $segment      = $this->makeSegment([
            ['field' => 'payment_type', 'operator' => 'not_in', 'value' => ['direct_debit', 'invoice']],
        ]);

        $this->assertTrue($this->engine->matches($subscription, $segment));
    }

    // =========================================================================
    // Date operators: before / after
    // =========================================================================

    public function test_before_matches_when_actual_date_is_earlier(): void
    {
        $subscription = $this->makeSubscription(['renewal_date' => '2025-01-01']);
        $segment      = $this->makeSegment([
            ['field' => 'renewal_date', 'operator' => 'before', 'value' => '2025-06-01'],
        ]);

        $this->assertTrue($this->engine->matches($subscription, $segment));
    }

    public function test_before_does_not_match_later_date(): void
    {
        $subscription = $this->makeSubscription(['renewal_date' => '2025-12-01']);
        $segment      = $this->makeSegment([
            ['field' => 'renewal_date', 'operator' => 'before', 'value' => '2025-06-01'],
        ]);

        $this->assertFalse($this->engine->matches($subscription, $segment));
    }

    public function test_after_matches_when_actual_date_is_later(): void
    {
        $subscription = $this->makeSubscription(['renewal_date' => '2025-12-01']);
        $segment      = $this->makeSegment([
            ['field' => 'renewal_date', 'operator' => 'after', 'value' => '2025-06-01'],
        ]);

        $this->assertTrue($this->engine->matches($subscription, $segment));
    }

    // =========================================================================
    // within_next_days
    // =========================================================================

    public function test_within_next_days_matches_date_in_window(): void
    {
        // now = 2026-01-01, window = 30 days → ceiling = 2026-01-31
        $subscription = $this->makeSubscription(['renewal_date' => '2026-01-14']);
        $segment      = $this->makeSegment([
            ['field' => 'renewal_date', 'operator' => 'within_next_days', 'value' => 30],
        ]);

        $this->assertTrue($this->engine->matches($subscription, $segment));
    }

    public function test_within_next_days_does_not_match_date_outside_window(): void
    {
        // now = 2026-01-01, window = 30 days → ceiling = 2026-01-31
        $subscription = $this->makeSubscription(['renewal_date' => '2026-03-01']);
        $segment      = $this->makeSegment([
            ['field' => 'renewal_date', 'operator' => 'within_next_days', 'value' => 30],
        ]);

        $this->assertFalse($this->engine->matches($subscription, $segment));
    }

    public function test_within_next_days_does_not_match_past_date(): void
    {
        // renewal_date is before now
        $subscription = $this->makeSubscription(['renewal_date' => '2025-12-01']);
        $segment      = $this->makeSegment([
            ['field' => 'renewal_date', 'operator' => 'within_next_days', 'value' => 30],
        ]);

        $this->assertFalse($this->engine->matches($subscription, $segment));
    }

    // =========================================================================
    // Invalid / missing fields
    // =========================================================================

    public function test_unknown_operator_fails_safe_to_false(): void
    {
        $subscription = $this->makeSubscription(['price' => 10]);
        $segment      = $this->makeSegment([
            ['field' => 'price', 'operator' => 'UNKNOWNOP', 'value' => 10],
        ]);

        $this->assertFalse($this->engine->matches($subscription, $segment));
    }

    public function test_missing_field_fails_safe_to_false(): void
    {
        $subscription = $this->makeSubscription([]);  // no 'price' key
        $segment      = $this->makeSegment([
            ['field' => 'price', 'operator' => 'greater_than', 'value' => 10],
        ]);

        $this->assertFalse($this->engine->matches($subscription, $segment));
    }

    // =========================================================================
    // AND / OR boolean combining
    // =========================================================================

    public function test_and_combination_fails_when_one_rule_fails(): void
    {
        $subscription = $this->makeSubscription(['payment_type' => 'direct_debit', 'price' => 5]);
        $segment      = $this->makeSegment([
            ['field' => 'payment_type', 'operator' => 'equals',       'value' => 'direct_debit', 'boolean' => 'AND'],
            ['field' => 'price',        'operator' => 'greater_than',  'value' => 10,             'boolean' => 'AND'],
        ]);

        $this->assertFalse($this->engine->matches($subscription, $segment));
    }

    public function test_and_combination_passes_when_all_rules_pass(): void
    {
        $subscription = $this->makeSubscription(['payment_type' => 'direct_debit', 'price' => 50]);
        $segment      = $this->makeSegment([
            ['field' => 'payment_type', 'operator' => 'equals',      'value' => 'direct_debit', 'boolean' => 'AND'],
            ['field' => 'price',        'operator' => 'greater_than', 'value' => 30,             'boolean' => 'AND'],
        ]);

        $this->assertTrue($this->engine->matches($subscription, $segment));
    }

    public function test_or_combination_passes_when_only_one_rule_passes(): void
    {
        $subscription = $this->makeSubscription(['payment_type' => 'card', 'price' => 50]);
        $segment      = $this->makeSegment([
            ['field' => 'payment_type', 'operator' => 'equals',      'value' => 'direct_debit', 'boolean' => 'AND'],
            ['field' => 'price',        'operator' => 'greater_than', 'value' => 30,             'boolean' => 'OR'],
        ]);

        $this->assertTrue($this->engine->matches($subscription, $segment));
    }

    public function test_or_combination_fails_when_all_rules_fail(): void
    {
        $subscription = $this->makeSubscription(['payment_type' => 'card', 'price' => 5]);
        $segment      = $this->makeSegment([
            ['field' => 'payment_type', 'operator' => 'equals',      'value' => 'direct_debit', 'boolean' => 'AND'],
            ['field' => 'price',        'operator' => 'greater_than', 'value' => 30,             'boolean' => 'OR'],
        ]);

        $this->assertFalse($this->engine->matches($subscription, $segment));
    }

    public function test_equals_matches_plan_field(): void
    {
        $subscription = $this->makeSubscription([
            'plan' => [
                'billing_period' => 'monthly',
            ],
        ]);

        $segment = $this->makeSegment([
            [
                'field' => 'plan.billing_period',
                'operator' => 'equals',
                'value' => 'monthly',
            ],
        ]);

        $this->assertTrue(
            $this->engine->matches($subscription, $segment)
        );
    }

    public function test_equals_does_not_match_different_plan_field(): void
    {
        $subscription = $this->makeSubscription([
            'plan' => [
                'billing_period' => 'annual',
            ],
        ]);

        $segment = $this->makeSegment([
            [
                'field' => 'plan.billing_period',
                'operator' => 'equals',
                'value' => 'monthly',
            ],
        ]);

        $this->assertFalse(
            $this->engine->matches($subscription, $segment)
        );
    }

    public function test_contains_matches_plan_categories(): void
    {
        $subscription = $this->makeSubscription([
            'plan' => [
                'categories' => [
                    'Digital Only',
                    'Monthly',
                ],
            ],
        ]);

        $segment = $this->makeSegment([
            [
                'field' => 'plan.categories',
                'operator' => 'contains',
                'value' => 'Monthly',
            ],
        ]);

        $this->assertTrue(
            $this->engine->matches($subscription, $segment)
        );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeSubscription(array $attributes): Subscription
    {
        $sub = Mockery::mock(Subscription::class)->makePartial();

        $sub->allows('toArray')->andReturn($attributes);

        $sub->plan = null;

        return $sub;
    }

    private function makeSegment(array $ruleDefs): Segment
    {
        $rules = collect($ruleDefs)->map(function (array $def) {
            $rule           = new \stdClass();
            $rule->field    = $def['field'];
            $rule->operator = SubscriptionRuleOperator::tryFrom($def['operator']) ?? $def['operator'];
            $rule->value    = $def['value'];
            $rule->boolean  = \App\Enums\Member\SegmentRuleBoolean::tryFrom($def['boolean'] ?? 'AND')
                ?? $def['boolean'] ?? 'AND';

            return $rule;
        });

        $segment = Mockery::mock(Segment::class)->makePartial();
        $segment->allows('getAttribute')->with('rules')->andReturn($rules);
        // Allow direct property access via __get
        $segment->rules = $rules;

        return $segment;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Fixed "now" = 2026-01-01 00:00:00 UTC for deterministic date tests.
        $this->now    = new \DateTimeImmutable('2026-01-01 00:00:00', new \DateTimeZone('UTC'));
        $this->engine = new SegmentRuleEngine($this->now);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}