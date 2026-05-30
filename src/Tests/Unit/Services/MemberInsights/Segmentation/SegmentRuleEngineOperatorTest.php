<?php

namespace App\Tests\Unit\Services\MemberInsights\Segmentation;

use App\Enums\Member\SegmentRuleBoolean;
use App\Models\Segment;
use App\Models\Subscription;
use App\Services\MemberInsights\Segmentation\SegmentRuleEngine;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Ticket 12 — comprehensive operator coverage for SegmentRuleEngine.
 *
 * Every operator defined in SubscriptionRuleOperator has at least:
 *   - A passing case
 *   - A failing case
 *   - An edge / boundary case
 */
class SegmentRuleEngineOperatorTest extends TestCase
{
    private \DateTimeImmutable $now;
    private SegmentRuleEngine $engine;

    // =========================================================================
    // equals
    // =========================================================================

    public function test_equals_passes_on_string_match(): void
    {
        $this->assertTrue($this->eval('payment_type', 'equals', 'direct_debit', ['payment_type' => 'direct_debit']));
    }

    public function test_equals_fails_on_string_mismatch(): void
    {
        $this->assertFalse($this->eval('payment_type', 'equals', 'direct_debit', ['payment_type' => 'card']));
    }

    public function test_equals_passes_on_numeric_loose_match(): void
    {
        // "10" == 10 is true in PHP loose comparison — rule engine uses ==
        $this->assertTrue($this->eval('price', 'equals', 10, ['price' => '10']));
    }

    public function test_equals_fails_on_null_actual(): void
    {
        $this->assertFalse($this->eval('payment_type', 'equals', 'direct_debit', []));
    }

    // =========================================================================
    // not_equals
    // =========================================================================

    public function test_not_equals_passes_when_values_differ(): void
    {
        $this->assertTrue($this->eval('status', 'not_equals', 'cancelled', ['status' => 'active']));
    }

    public function test_not_equals_fails_when_values_match(): void
    {
        $this->assertFalse($this->eval('status', 'not_equals', 'active', ['status' => 'active']));
    }

    // =========================================================================
    // greater_than
    // =========================================================================

    public function test_greater_than_passes_when_actual_exceeds_expected(): void
    {
        $this->assertTrue($this->eval('price', 'greater_than', 30, ['price' => 50]));
    }

    public function test_greater_than_fails_on_equal(): void
    {
        $this->assertFalse($this->eval('price', 'greater_than', 50, ['price' => 50]));
    }

    public function test_greater_than_fails_when_actual_is_less(): void
    {
        $this->assertFalse($this->eval('price', 'greater_than', 50, ['price' => 30]));
    }

    public function test_greater_than_fails_on_non_numeric(): void
    {
        $this->assertFalse($this->eval('price', 'greater_than', 30, ['price' => 'abc']));
    }

    // =========================================================================
    // less_than
    // =========================================================================

    public function test_less_than_passes_when_actual_is_below(): void
    {
        $this->assertTrue($this->eval('price', 'less_than', 50, ['price' => 30]));
    }

    public function test_less_than_fails_on_equal(): void
    {
        $this->assertFalse($this->eval('price', 'less_than', 30, ['price' => 30]));
    }

    public function test_less_than_fails_when_actual_exceeds(): void
    {
        $this->assertFalse($this->eval('price', 'less_than', 30, ['price' => 50]));
    }

    // =========================================================================
    // between
    // =========================================================================

    public function test_between_passes_within_numeric_range(): void
    {
        $this->assertTrue($this->eval('price', 'between', [10, 50], ['price' => 30]));
    }

    public function test_between_passes_on_lower_boundary(): void
    {
        $this->assertTrue($this->eval('price', 'between', [10, 50], ['price' => 10]));
    }

    public function test_between_passes_on_upper_boundary(): void
    {
        $this->assertTrue($this->eval('price', 'between', [10, 50], ['price' => 50]));
    }

    public function test_between_fails_below_range(): void
    {
        $this->assertFalse($this->eval('price', 'between', [10, 50], ['price' => 9]));
    }

    public function test_between_fails_above_range(): void
    {
        $this->assertFalse($this->eval('price', 'between', [10, 50], ['price' => 51]));
    }

    public function test_between_fails_with_non_array_expected(): void
    {
        $this->assertFalse($this->eval('price', 'between', 30, ['price' => 30]));
    }

    public function test_between_passes_on_date_range(): void
    {
        // now = 2026-01-01; range 2025-12-01 to 2026-02-01
        $this->assertTrue($this->eval(
            'renewal_date', 'between', ['2025-12-01', '2026-02-01'],
            ['renewal_date' => '2026-01-15']
        ));
    }

    public function test_between_fails_outside_date_range(): void
    {
        $this->assertFalse($this->eval(
            'renewal_date', 'between', ['2025-12-01', '2025-12-31'],
            ['renewal_date' => '2026-01-15']
        ));
    }

    // =========================================================================
    // contains
    // =========================================================================

    public function test_contains_passes_when_item_in_array(): void
    {
        $this->assertTrue($this->eval('tags', 'contains', 'at_risk', ['tags' => ['renewal', 'at_risk']]));
    }

    public function test_contains_fails_when_item_absent(): void
    {
        $this->assertFalse($this->eval('tags', 'contains', 'at_risk', ['tags' => ['renewal']]));
    }

    public function test_contains_fails_when_actual_is_not_array(): void
    {
        $this->assertFalse($this->eval('tags', 'contains', 'at_risk', ['tags' => 'at_risk']));
    }

    public function test_contains_fails_on_empty_array(): void
    {
        $this->assertFalse($this->eval('tags', 'contains', 'at_risk', ['tags' => []]));
    }

    // =========================================================================
    // in
    // =========================================================================

    public function test_in_passes_when_actual_is_in_list(): void
    {
        $this->assertTrue($this->eval('payment_type', 'in', ['direct_debit', 'invoice'], ['payment_type' => 'invoice']));
    }

    public function test_in_fails_when_actual_not_in_list(): void
    {
        $this->assertFalse($this->eval('payment_type', 'in', ['direct_debit', 'invoice'], ['payment_type' => 'card']));
    }

    public function test_in_fails_when_expected_is_not_array(): void
    {
        $this->assertFalse($this->eval('payment_type', 'in', 'direct_debit', ['payment_type' => 'direct_debit']));
    }

    // =========================================================================
    // not_in
    // =========================================================================

    public function test_not_in_passes_when_actual_absent_from_list(): void
    {
        $this->assertTrue($this->eval('payment_type', 'not_in', ['direct_debit', 'invoice'], ['payment_type' => 'card']));
    }

    public function test_not_in_fails_when_actual_in_list(): void
    {
        $this->assertFalse($this->eval('payment_type', 'not_in', ['direct_debit'], ['payment_type' => 'direct_debit']));
    }

    // =========================================================================
    // before
    // =========================================================================

    public function test_before_passes_when_actual_is_earlier(): void
    {
        // now = 2026-01-01; actual is 2025-06-01 which is before 2025-12-01
        $this->assertTrue($this->eval('renewal_date', 'before', '2025-12-01', ['renewal_date' => '2025-06-01']));
    }

    public function test_before_fails_when_actual_is_later(): void
    {
        $this->assertFalse($this->eval('renewal_date', 'before', '2025-06-01', ['renewal_date' => '2025-12-01']));
    }

    public function test_before_fails_on_unparseable_date(): void
    {
        $this->assertFalse($this->eval('renewal_date', 'before', '2025-12-01', ['renewal_date' => 'not-a-date']));
    }

    // =========================================================================
    // after
    // =========================================================================

    public function test_after_passes_when_actual_is_later(): void
    {
        $this->assertTrue($this->eval('renewal_date', 'after', '2025-06-01', ['renewal_date' => '2025-12-01']));
    }

    public function test_after_fails_when_actual_is_earlier(): void
    {
        $this->assertFalse($this->eval('renewal_date', 'after', '2025-12-01', ['renewal_date' => '2025-06-01']));
    }

    // =========================================================================
    // within_next_days
    // =========================================================================

    public function test_within_next_days_passes_on_day_zero(): void
    {
        // now = 2026-01-01; actual = today → within 0 days is exactly on boundary
        $this->assertTrue($this->eval('renewal_date', 'within_next_days', 0, ['renewal_date' => '2026-01-01']));
    }

    public function test_within_next_days_passes_near_ceiling(): void
    {
        // window = 30 days from 2026-01-01 → ceiling = 2026-01-31
        $this->assertTrue($this->eval('renewal_date', 'within_next_days', 30, ['renewal_date' => '2026-01-31']));
    }

    public function test_within_next_days_fails_one_day_over_ceiling(): void
    {
        $this->assertFalse($this->eval('renewal_date', 'within_next_days', 30, ['renewal_date' => '2026-02-01']));
    }

    public function test_within_next_days_fails_for_past_date(): void
    {
        $this->assertFalse($this->eval('renewal_date', 'within_next_days', 30, ['renewal_date' => '2025-12-31']));
    }

    public function test_within_next_days_fails_on_negative_window(): void
    {
        $this->assertFalse($this->eval('renewal_date', 'within_next_days', -1, ['renewal_date' => '2026-01-01']));
    }

    // =========================================================================
    // Unknown operator — fail safe
    // =========================================================================

    public function test_unknown_operator_fails_safe(): void
    {
        $this->assertFalse($this->eval('price', 'TOTALLY_UNKNOWN', 10, ['price' => 10]));
    }

    // =========================================================================
    // Missing / null field — fail safe
    // =========================================================================

    public function test_missing_field_fails_safe(): void
    {
        $this->assertFalse($this->eval('nonexistent', 'equals', 'foo', []));
    }

    public function test_null_field_value_fails_safe_for_numeric_ops(): void
    {
        $this->assertFalse($this->eval('price', 'greater_than', 0, ['price' => null]));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Convenience wrapper: evaluate a single rule against a flat data array.
     */
    private function eval(string $field, string $operator, mixed $value, array $data): bool
    {
        $rule           = new \stdClass();
        $rule->field    = $field;
        $rule->operator = $operator;
        $rule->value    = $value;
        $rule->boolean  = SegmentRuleBoolean::AND;
        $rule->sort_order = 0;

        $rules = collect([$rule]);

        $sub = Mockery::mock(Subscription::class)->makePartial();
        $sub->allows('toArray')->andReturn($data);

        $segment = Mockery::mock(Segment::class)->makePartial();
        $segment->rules = $rules;

        return $this->engine->matches($sub, $segment);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->now    = new \DateTimeImmutable('2026-01-01 00:00:00', new \DateTimeZone('UTC'));
        $this->engine = new SegmentRuleEngine($this->now);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}