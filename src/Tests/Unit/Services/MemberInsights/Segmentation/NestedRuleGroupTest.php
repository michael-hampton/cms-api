<?php

namespace App\Tests\Unit\Services\MemberInsights\Segmentation;

use App\Enums\Member\SegmentRuleBoolean;
use App\Enums\Member\SegmentRuleOperator;
use App\Models\Segment;
use App\Models\SegmentRuleGroup;
use App\Services\MemberInsights\Segmentation\NestedSegmentRuleEvaluator;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Ticket 12 — comprehensive nested rule group coverage.
 */
class NestedRuleGroupTest extends TestCase
{
    private NestedSegmentRuleEvaluator $evaluator;

    // =========================================================================
    // Nested AND — all must pass
    // =========================================================================

    public function test_nested_and_passes_when_all_levels_pass(): void
    {
        // Root: status = active
        //   Child (AND): payment_type = direct_debit
        //     Grandchild (AND): price > 10
        $grandchild = $this->makeGroup(SegmentRuleBoolean::AND, collect([
            $this->rule('price', SegmentRuleOperator::GREATER_THAN, 10),
        ]), collect());

        $child = $this->makeGroup(SegmentRuleBoolean::AND, collect([
            $this->rule('payment_type', SegmentRuleOperator::EQUALS, 'direct_debit'),
        ]), collect([$grandchild]));

        $root = $this->makeGroup(SegmentRuleBoolean::AND, collect([
            $this->rule('status', SegmentRuleOperator::EQUALS, 'active'),
        ]), collect([$child]));

        $segment = $this->makeGroupedSegment([$root]);

        $this->assertTrue($this->evaluator->evaluate(
            ['status' => 'active', 'payment_type' => 'direct_debit', 'price' => 50],
            $segment
        ));
    }

    public function test_nested_and_fails_when_deepest_level_fails(): void
    {
        $grandchild = $this->makeGroup(SegmentRuleBoolean::AND, collect([
            $this->rule('price', SegmentRuleOperator::GREATER_THAN, 100),
        ]), collect());

        $child = $this->makeGroup(SegmentRuleBoolean::AND, collect([
            $this->rule('payment_type', SegmentRuleOperator::EQUALS, 'direct_debit'),
        ]), collect([$grandchild]));

        $root = $this->makeGroup(SegmentRuleBoolean::AND, collect([
            $this->rule('status', SegmentRuleOperator::EQUALS, 'active'),
        ]), collect([$child]));

        $segment = $this->makeGroupedSegment([$root]);

        // price = 5 fails grandchild
        $this->assertFalse($this->evaluator->evaluate(
            ['status' => 'active', 'payment_type' => 'direct_debit', 'price' => 5],
            $segment
        ));
    }

    // =========================================================================
    // Nested OR — any must pass
    // =========================================================================

    public function test_nested_or_passes_when_only_second_child_passes(): void
    {
        // Root (AND):
        //   Child1 (AND): payment_type = direct_debit   ← will fail
        //   Child2 (OR):  payment_type = invoice        ← will pass
        $child1 = $this->makeGroup(SegmentRuleBoolean::AND, collect([
            $this->rule('payment_type', SegmentRuleOperator::EQUALS, 'direct_debit'),
        ]), collect());

        $child2 = $this->makeGroup(SegmentRuleBoolean::OR, collect([
            $this->rule('payment_type', SegmentRuleOperator::EQUALS, 'invoice'),
        ]), collect());

        $root = $this->makeGroup(SegmentRuleBoolean::AND, collect(), collect([$child1, $child2]));

        $segment = $this->makeGroupedSegment([$root]);

        $this->assertTrue($this->evaluator->evaluate(['payment_type' => 'invoice'], $segment));
    }

    public function test_nested_or_fails_when_all_children_fail(): void
    {
        $child1 = $this->makeGroup(SegmentRuleBoolean::AND, collect([
            $this->rule('payment_type', SegmentRuleOperator::EQUALS, 'direct_debit'),
        ]), collect());

        $child2 = $this->makeGroup(SegmentRuleBoolean::OR, collect([
            $this->rule('payment_type', SegmentRuleOperator::EQUALS, 'invoice'),
        ]), collect());

        $root = $this->makeGroup(SegmentRuleBoolean::AND, collect(), collect([$child1, $child2]));

        $segment = $this->makeGroupedSegment([$root]);

        $this->assertFalse($this->evaluator->evaluate(['payment_type' => 'card'], $segment));
    }

    // =========================================================================
    // Mixed nesting
    // =========================================================================

    public function test_evaluates_mixed_groups_with_real_world_example(): void
    {
        // Mirrors the example from the ticket:
        //   status = active
        //   AND (
        //     payment_type = direct_debit
        //     OR payment_type = invoice
        //   )
        //   AND renewal_date within_next_days 30 — omitted here (date engine tested separately)

        $paymentGroup = $this->makeGroup(SegmentRuleBoolean::AND, collect([
            $this->rule('payment_type', SegmentRuleOperator::EQUALS, 'direct_debit'),
            $this->rule('payment_type', SegmentRuleOperator::EQUALS, 'invoice', SegmentRuleBoolean::OR),
        ]), collect());

        $root = $this->makeGroup(SegmentRuleBoolean::AND, collect([
            $this->rule('status', SegmentRuleOperator::EQUALS, 'active'),
        ]), collect([$paymentGroup]));

        $segment = $this->makeGroupedSegment([$root]);

        // active + invoice → passes
        $this->assertTrue($this->evaluator->evaluate(
            ['status' => 'active', 'payment_type' => 'invoice'],
            $segment
        ));

        // active + card → payment group fails
        $this->assertFalse($this->evaluator->evaluate(
            ['status' => 'active', 'payment_type' => 'card'],
            $segment
        ));

        // cancelled + direct_debit → root rule fails
        $this->assertFalse($this->evaluator->evaluate(
            ['status' => 'cancelled', 'payment_type' => 'direct_debit'],
            $segment
        ));
    }

    // =========================================================================
    // Legacy rules (backward compat)
    // =========================================================================

    public function test_supports_legacy_flat_rules_with_no_groups(): void
    {
        $rules = collect([
            $this->rule('status', SegmentRuleOperator::EQUALS, 'active'),
            $this->rule('price', SegmentRuleOperator::GREATER_THAN, 10, SegmentRuleBoolean::AND),
        ]);

        $segment = Mockery::mock(Segment::class)->makePartial();
        $segment->rules  = $rules;
        $segment->groups = collect();
        $segment->allows('relationLoaded')->with('groups')->andReturn(true);

        $this->assertTrue($this->evaluator->evaluate(
            ['status' => 'active', 'price' => 50],
            $segment
        ));
    }

    public function test_legacy_flat_rules_fail_correctly(): void
    {
        $rules = collect([
            $this->rule('status', SegmentRuleOperator::EQUALS, 'active'),
        ]);

        $segment = Mockery::mock(Segment::class)->makePartial();
        $segment->rules  = $rules;
        $segment->groups = collect();
        $segment->allows('relationLoaded')->with('groups')->andReturn(true);

        $this->assertFalse($this->evaluator->evaluate(['status' => 'cancelled'], $segment));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function rule(
        string $field,
        SegmentRuleOperator $operator,
        mixed $value,
        SegmentRuleBoolean $boolean = SegmentRuleBoolean::AND,
    ): object {
        $rule             = new \stdClass();
        $rule->field      = $field;
        $rule->operator   = $operator;
        $rule->value      = $value;
        $rule->boolean    = $boolean;
        $rule->sort_order = 0;

        return $rule;
    }

    private function makeGroup(
        SegmentRuleBoolean $boolean,
                           $rules,
                           $children,
    ): SegmentRuleGroup {
        $group             = Mockery::mock(SegmentRuleGroup::class)->makePartial();
        $group->boolean    = $boolean;
        $group->parent_id  = null;
        $group->sort_order = 0;
        $group->rules      = $rules;
        $group->children   = $children;

        return $group;
    }

    private function makeGroupedSegment(array $groups): Segment
    {
        $segment = Mockery::mock(Segment::class)->makePartial();
        $segment->rules  = collect();
        $segment->groups = collect($groups);
        $segment->allows('relationLoaded')->with('groups')->andReturn(true);

        return $segment;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new NestedSegmentRuleEvaluator();
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}