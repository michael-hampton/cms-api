<?php

namespace App\Tests\Unit\Services\MemberInsights\Segmentation;

use App\Enums\Member\SegmentRuleBoolean;
use App\Enums\Member\SegmentRuleOperator;
use App\Framework\Support\Collection;
use App\Services\MemberInsights\Segmentation\SegmentRuleEvaluator;
use PHPUnit\Framework\TestCase;

/**
 * Ticket 12 — regression guard for legacy member segmentation.
 *
 * These tests verify the original SegmentRuleEvaluator (member segments) still
 * works identically after the subscription segmentation engine was introduced.
 * They should stay green on every merge touching the segmentation layer.
 */
class LegacyMemberSegmentRegressionTest extends TestCase
{
    private SegmentRuleEvaluator $evaluator;

    // =========================================================================
    // Basic operator parity
    // =========================================================================

    public function test_greater_than_still_works(): void
    {
        $rules = $this->rules([
            ['field' => 'scores.activity_score', 'operator' => '>', 'value' => 80],
        ]);

        $this->assertTrue($this->evaluator->matches(['scores' => ['activity_score' => 90]], $rules));
        $this->assertFalse($this->evaluator->matches(['scores' => ['activity_score' => 80]], $rules));
    }

    public function test_less_than_still_works(): void
    {
        $rules = $this->rules([
            ['field' => 'trends.7d_change', 'operator' => '<', 'value' => -20],
        ]);

        $this->assertTrue($this->evaluator->matches(['trends' => ['7d_change' => -25]], $rules));
        $this->assertFalse($this->evaluator->matches(['trends' => ['7d_change' => -20]], $rules));
    }

    public function test_equals_still_works(): void
    {
        $rules = $this->rules([
            ['field' => 'behaviour.profile_type', 'operator' => '=', 'value' => 'engaged_contributor'],
        ]);

        $this->assertTrue($this->evaluator->matches(
            ['behaviour' => ['profile_type' => 'engaged_contributor']], $rules
        ));
    }

    public function test_not_equals_still_works(): void
    {
        $rules = $this->rules([
            ['field' => 'behaviour.profile_type', 'operator' => '!=', 'value' => 'browsing_heavy'],
        ]);

        $this->assertTrue($this->evaluator->matches(
            ['behaviour' => ['profile_type' => 'engaged_contributor']], $rules
        ));
    }

    public function test_in_operator_still_works(): void
    {
        $rules = $this->rules([
            ['field' => 'behaviour.profile_type', 'operator' => 'IN', 'value' => ['browsing_heavy', 'reactive_user']],
        ]);

        $this->assertTrue($this->evaluator->matches(
            ['behaviour' => ['profile_type' => 'reactive_user']], $rules
        ));
    }

    public function test_contains_operator_still_works(): void
    {
        $rules = $this->rules([
            ['field' => 'flags', 'operator' => 'CONTAINS', 'value' => 'lurker_profile'],
        ]);

        $this->assertTrue($this->evaluator->matches(
            ['flags' => ['lurker_profile', 'high_activity']], $rules
        ));
    }

    // =========================================================================
    // Boolean combining parity
    // =========================================================================

    public function test_and_chain_still_requires_all_rules(): void
    {
        $rules = $this->rules([
            ['field' => 'scores.activity_score', 'operator' => '>', 'value' => 40, 'boolean' => 'AND'],
            ['field' => 'summary.total_actions',  'operator' => '<', 'value' => 3,  'boolean' => 'AND'],
        ]);

        $this->assertTrue($this->evaluator->matches(
            ['scores' => ['activity_score' => 50], 'summary' => ['total_actions' => 1]], $rules
        ));
        $this->assertFalse($this->evaluator->matches(
            ['scores' => ['activity_score' => 50], 'summary' => ['total_actions' => 10]], $rules
        ));
    }

    public function test_or_chain_still_passes_on_single_match(): void
    {
        $rules = $this->rules([
            ['field' => 'scores.activity_score', 'operator' => '>', 'value' => 80, 'boolean' => 'AND'],
            ['field' => 'trends.7d_change',      'operator' => '<', 'value' => -20, 'boolean' => 'OR'],
        ]);

        // activity_score fails, 7d_change passes → OR = true
        $this->assertTrue($this->evaluator->matches(
            ['scores' => ['activity_score' => 50], 'trends' => ['7d_change' => -30]], $rules
        ));
    }

    // =========================================================================
    // Edge cases still handled
    // =========================================================================

    public function test_empty_rules_still_return_false(): void
    {
        $this->assertFalse($this->evaluator->matches(['scores' => ['activity_score' => 90]], new Collection()));
    }

    public function test_missing_field_still_returns_false(): void
    {
        $rules = $this->rules([
            ['field' => 'scores.nonexistent', 'operator' => '>', 'value' => 50],
        ]);

        $this->assertFalse($this->evaluator->matches([], $rules));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function rules(array $definitions): Collection
    {
        return collect($definitions)->map(function (array $def) {
            $rule           = new \stdClass();
            $rule->field    = $def['field'];
            $rule->operator = SegmentRuleOperator::from($def['operator']);
            $rule->value    = $def['value'];
            $rule->boolean  = SegmentRuleBoolean::from($def['boolean'] ?? 'AND');

            return $rule;
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new SegmentRuleEvaluator();
    }
}