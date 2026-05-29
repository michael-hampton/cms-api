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
 * Tests for NestedSegmentRuleEvaluator.
 *
 * Covers:
 *   - Legacy flat rule evaluation (backward compatibility)
 *   - Nested AND groups
 *   - Nested OR groups
 *   - Mixed AND/OR across parent/child groups
 *   - Empty groups
 */
class NestedSegmentRuleEvaluatorTest extends TestCase
{
    private NestedSegmentRuleEvaluator $evaluator;

    // =========================================================================
    // Legacy flat rules (backward compat)
    // =========================================================================

    public function test_it_evaluates_legacy_flat_rules_when_no_groups(): void
    {
        $rules   = collect([
            $this->makeRule('scores.activity_score', SegmentRuleOperator::GREATER_THAN, 40, SegmentRuleBoolean::AND),
        ]);
        $segment = $this->makeSegment($rules, collect());

        $this->assertTrue($this->evaluator->evaluate(['scores' => ['activity_score' => 50]], $segment));
    }

    public function test_legacy_flat_rules_and_chain_fails_on_one_mismatch(): void
    {
        $rules = collect([
            $this->makeRule('scores.activity_score', SegmentRuleOperator::GREATER_THAN, 40, SegmentRuleBoolean::AND),
            $this->makeRule('summary.total_actions',  SegmentRuleOperator::LESS_THAN,    3,  SegmentRuleBoolean::AND),
        ]);
        $segment = $this->makeSegment($rules, collect());

        $profile = ['scores' => ['activity_score' => 50], 'summary' => ['total_actions' => 10]];

        $this->assertFalse($this->evaluator->evaluate($profile, $segment));
    }

    public function test_it_supports_legacy_rules(): void
    {
        $rules = collect([
            $this->makeRule('status', SegmentRuleOperator::EQUALS, 'active', SegmentRuleBoolean::AND),
        ]);
        $segment = $this->makeSegment($rules, collect());

        $this->assertTrue($this->evaluator->evaluate(['status' => 'active'], $segment));
    }

    // =========================================================================
    // Nested AND
    // =========================================================================

    public function test_it_evaluates_nested_and_group(): void
    {
        // Root group (AND):
        //   rule: payment_type = direct_debit
        //   child group (AND):
        //     rule: price > 10
        //     rule: status = active

        $childGroup = $this->makeGroup(
            SegmentRuleBoolean::AND,
            collect([
                $this->makeRule('price',  SegmentRuleOperator::GREATER_THAN, 10,       SegmentRuleBoolean::AND),
                $this->makeRule('status', SegmentRuleOperator::EQUALS,      'active', SegmentRuleBoolean::AND),
            ]),
            collect()
        );

        $rootGroup = $this->makeGroup(
            SegmentRuleBoolean::AND,
            collect([
                $this->makeRule('payment_type', SegmentRuleOperator::EQUALS, 'direct_debit', SegmentRuleBoolean::AND),
            ]),
            collect([$childGroup])
        );

        $segment = $this->makeSegment(collect(), collect([$rootGroup]));

        $data = ['payment_type' => 'direct_debit', 'price' => 50, 'status' => 'active'];
        $this->assertTrue($this->evaluator->evaluate($data, $segment));
    }

    public function test_nested_and_fails_when_child_fails(): void
    {
        $childGroup = $this->makeGroup(
            SegmentRuleBoolean::AND,
            collect([
                $this->makeRule('price', SegmentRuleOperator::GREATER_THAN, 100, SegmentRuleBoolean::AND),
            ]),
            collect()
        );

        $rootGroup = $this->makeGroup(
            SegmentRuleBoolean::AND,
            collect([
                $this->makeRule('payment_type', SegmentRuleOperator::EQUALS, 'direct_debit', SegmentRuleBoolean::AND),
            ]),
            collect([$childGroup])
        );

        $segment = $this->makeSegment(collect(), collect([$rootGroup]));

        $data = ['payment_type' => 'direct_debit', 'price' => 50];
        $this->assertFalse($this->evaluator->evaluate($data, $segment));
    }

    // =========================================================================
    // Nested OR
    // =========================================================================

    public function test_it_evaluates_nested_or_group(): void
    {
        // Root group (AND):
        //   rule: status = active
        //   child group (OR):
        //     rule: payment_type = direct_debit
        //     rule: payment_type = invoice

        $childGroup = $this->makeGroup(
            SegmentRuleBoolean::OR,
            collect([
                $this->makeRule('payment_type', SegmentRuleOperator::EQUALS, 'direct_debit', SegmentRuleBoolean::AND),
                $this->makeRule('payment_type', SegmentRuleOperator::EQUALS, 'invoice',      SegmentRuleBoolean::OR),
            ]),
            collect()
        );

        $rootGroup = $this->makeGroup(
            SegmentRuleBoolean::AND,
            collect([
                $this->makeRule('status', SegmentRuleOperator::EQUALS, 'active', SegmentRuleBoolean::AND),
            ]),
            collect([$childGroup])
        );

        $segment = $this->makeSegment(collect(), collect([$rootGroup]));

        // payment_type = invoice satisfies the OR child group
        $data = ['status' => 'active', 'payment_type' => 'invoice'];
        $this->assertTrue($this->evaluator->evaluate($data, $segment));
    }

    public function test_nested_or_fails_when_all_options_fail(): void
    {
        $childGroup = $this->makeGroup(
            SegmentRuleBoolean::OR,
            collect([
                $this->makeRule('payment_type', SegmentRuleOperator::EQUALS, 'direct_debit', SegmentRuleBoolean::AND),
                $this->makeRule('payment_type', SegmentRuleOperator::EQUALS, 'invoice',      SegmentRuleBoolean::OR),
            ]),
            collect()
        );

        $rootGroup = $this->makeGroup(
            SegmentRuleBoolean::AND,
            collect([
                $this->makeRule('status', SegmentRuleOperator::EQUALS, 'active', SegmentRuleBoolean::AND),
            ]),
            collect([$childGroup])
        );

        $segment = $this->makeSegment(collect(), collect([$rootGroup]));

        // payment_type = card matches neither
        $data = ['status' => 'active', 'payment_type' => 'card'];
        $this->assertFalse($this->evaluator->evaluate($data, $segment));
    }

    // =========================================================================
    // Mixed groups
    // =========================================================================

    public function test_it_evaluates_mixed_groups(): void
    {
        // Root group 1 (AND): status = active AND price > 10
        // Root group 2 (OR):  payment_type = card

        $group1 = $this->makeGroup(
            SegmentRuleBoolean::AND,
            collect([
                $this->makeRule('status', SegmentRuleOperator::EQUALS,       'active', SegmentRuleBoolean::AND),
                $this->makeRule('price',  SegmentRuleOperator::GREATER_THAN,   10,       SegmentRuleBoolean::AND),
            ]),
            collect()
        );

        $group2 = $this->makeGroup(
            SegmentRuleBoolean::OR,
            collect([
                $this->makeRule('payment_type', SegmentRuleOperator::EQUALS, 'card', SegmentRuleBoolean::AND),
            ]),
            collect()
        );

        $segment = $this->makeSegment(collect(), collect([$group1, $group2]));

        // Group 1 fails (status = cancelled), group 2 passes via OR
        $data = ['status' => 'cancelled', 'price' => 5, 'payment_type' => 'card'];
        $this->assertTrue($this->evaluator->evaluate($data, $segment));
    }

    // =========================================================================
    // Edge cases
    // =========================================================================

    public function test_empty_segment_returns_false(): void
    {
        $segment = $this->makeSegment(collect(), collect());
        $this->assertFalse($this->evaluator->evaluate(['foo' => 'bar'], $segment));
    }

    public function test_empty_group_returns_false(): void
    {
        $rootGroup = $this->makeGroup(SegmentRuleBoolean::AND, collect(), collect());
        $segment   = $this->makeSegment(collect(), collect([$rootGroup]));

        $this->assertFalse($this->evaluator->evaluate(['foo' => 'bar'], $segment));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeRule(
        string $field,
        SegmentRuleOperator $operator,
        mixed $value,
        SegmentRuleBoolean $boolean,
    ): object {
        $rule           = new \stdClass();
        $rule->field    = $field;
        $rule->operator = $operator;
        $rule->value    = $value;
        $rule->boolean  = $boolean;
        $rule->sort_order = 0;

        return $rule;
    }

    private function makeGroup(
        SegmentRuleBoolean $boolean,
                           $rules,
                           $children,
        ?int $parentId = null,
    ): SegmentRuleGroup {
        $group = Mockery::mock(SegmentRuleGroup::class)->makePartial();

        $group->allows('getAttribute')
            ->with('boolean')
            ->andReturn($boolean);

        $group->allows('getAttribute')
            ->with('parent_id')
            ->andReturn($parentId);

        $group->allows('getAttribute')
            ->with('sort_order')
            ->andReturn(0);

        $group->allows('getAttribute')
            ->with('rules')
            ->andReturn($rules);

        $group->allows('getAttribute')
            ->with('children')
            ->andReturn($children);

        return $group;
    }

    private function makeSegment($flatRules, $groups): Segment
    {
        $segment = Mockery::mock(Segment::class)->makePartial();

        $segment->allows('relationLoaded')
            ->with('groups')
            ->andReturn($groups !== null);

        $segment->allows('getAttribute')
            ->with('rules')
            ->andReturn($flatRules);

        $segment->allows('getAttribute')
            ->with('groups')
            ->andReturn($groups);

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