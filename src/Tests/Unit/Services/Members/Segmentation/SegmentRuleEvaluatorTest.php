<?php

namespace App\Tests\Unit\Services\Members\Segmentation;


use App\Enums\Member\SegmentRuleBoolean;
use App\Enums\Member\SegmentRuleOperator;
use App\Framework\Support\Collection;
use App\Services\MemberInsights\Segmentation\SegmentRuleEvaluator;
use PHPUnit\Framework\TestCase;

class SegmentRuleEvaluatorTest extends TestCase
{
    private SegmentRuleEvaluator $evaluator;

    public function test_empty_rules_returns_false(): void
    {
        $result = $this->evaluator->matches(['scores' => ['activity_score' => 90]], new Collection());

        $this->assertFalse($result);
    }

    // =========================================================================
    // Empty rules
    // =========================================================================

    public function test_greater_than_matches_when_actual_exceeds_target(): void
    {
        $rules = $this->rules([
            ['field' => 'scores.activity_score', 'operator' => '>', 'value' => 80],
        ]);

        $this->assertTrue($this->evaluator->matches(['scores' => ['activity_score' => 90]], $rules));
    }

    // =========================================================================
    // Numeric operators
    // =========================================================================

    private function rules(array $definitions): Collection
    {
        return collect($definitions)->map(function (array $def) {
            $rule = new \stdClass();
            $rule->field = $def['field'];
            $rule->operator = SegmentRuleOperator::from($def['operator']);
            $rule->value = $def['value'];
            $rule->boolean = SegmentRuleBoolean::from($def['boolean'] ?? 'AND');
            return $rule;
        });
    }

    public function test_greater_than_does_not_match_when_equal(): void
    {
        $rules = $this->rules([
            ['field' => 'scores.activity_score', 'operator' => '>', 'value' => 80],
        ]);

        $this->assertFalse($this->evaluator->matches(['scores' => ['activity_score' => 80]], $rules));
    }

    public function test_less_than_matches_when_actual_is_below_target(): void
    {
        $rules = $this->rules([
            ['field' => 'trends.7d_change', 'operator' => '<', 'value' => -20],
        ]);

        $this->assertTrue($this->evaluator->matches(['trends' => ['7d_change' => -25]], $rules));
    }

    public function test_less_than_does_not_match_on_equal(): void
    {
        $rules = $this->rules([
            ['field' => 'trends.7d_change', 'operator' => '<', 'value' => -20],
        ]);

        $this->assertFalse($this->evaluator->matches(['trends' => ['7d_change' => -20]], $rules));
    }

    public function test_equals_matches_string(): void
    {
        $rules = $this->rules([
            ['field' => 'behaviour.profile_type', 'operator' => '=', 'value' => 'engaged_contributor'],
        ]);

        $this->assertTrue($this->evaluator->matches(['behaviour' => ['profile_type' => 'engaged_contributor']], $rules));
    }

    public function test_not_equals_matches_when_values_differ(): void
    {
        $rules = $this->rules([
            ['field' => 'behaviour.profile_type', 'operator' => '!=', 'value' => 'browsing_heavy'],
        ]);

        $this->assertTrue($this->evaluator->matches(['behaviour' => ['profile_type' => 'engaged_contributor']], $rules));
    }

    // =========================================================================
    // IN / CONTAINS operators
    // =========================================================================

    public function test_in_operator_matches_when_actual_is_in_list(): void
    {
        $rules = $this->rules([
            ['field' => 'behaviour.profile_type', 'operator' => 'IN', 'value' => ['browsing_heavy', 'reactive_user']],
        ]);

        $this->assertTrue($this->evaluator->matches(['behaviour' => ['profile_type' => 'reactive_user']], $rules));
    }

    public function test_in_operator_does_not_match_when_actual_absent(): void
    {
        $rules = $this->rules([
            ['field' => 'behaviour.profile_type', 'operator' => 'IN', 'value' => ['browsing_heavy']],
        ]);

        $this->assertFalse($this->evaluator->matches(['behaviour' => ['profile_type' => 'engaged_contributor']], $rules));
    }

    public function test_contains_operator_matches_when_expected_value_is_in_array(): void
    {
        $rules = $this->rules([
            ['field' => 'flags', 'operator' => 'CONTAINS', 'value' => 'lurker_profile'],
        ]);

        $this->assertTrue($this->evaluator->matches(['flags' => ['lurker_profile', 'high_activity']], $rules));
    }

    public function test_contains_operator_does_not_match_when_value_absent(): void
    {
        $rules = $this->rules([
            ['field' => 'flags', 'operator' => 'CONTAINS', 'value' => 'lurker_profile'],
        ]);

        $this->assertFalse($this->evaluator->matches(['flags' => ['high_activity']], $rules));
    }

    // =========================================================================
    // Nested dot-notation fields
    // =========================================================================

    public function test_evaluates_deeply_nested_field(): void
    {
        $rules = $this->rules([
            ['field' => 'counters.comment_count', 'operator' => '>', 'value' => 10],
        ]);

        $this->assertTrue($this->evaluator->matches(['counters' => ['comment_count' => 15]], $rules));
    }

    public function test_missing_field_does_not_throw_and_returns_false(): void
    {
        $rules = $this->rules([
            ['field' => 'scores.nonexistent_field', 'operator' => '>', 'value' => 50],
        ]);

        // null > 50 is false — should not throw
        $this->assertFalse($this->evaluator->matches([], $rules));
    }

    // =========================================================================
    // AND / OR boolean combining
    // =========================================================================

    public function test_and_combination_requires_all_rules_to_pass(): void
    {
        $rules = $this->rules([
            ['field' => 'scores.activity_score', 'operator' => '>', 'value' => 40, 'boolean' => 'AND'],
            ['field' => 'summary.total_actions', 'operator' => '<', 'value' => 3, 'boolean' => 'AND'],
        ]);

        // activity_score=50 passes; total_actions=10 fails → overall false
        $profile = ['scores' => ['activity_score' => 50], 'summary' => ['total_actions' => 10]];
        $this->assertFalse($this->evaluator->matches($profile, $rules));
    }

    public function test_and_combination_matches_when_all_rules_pass(): void
    {
        $rules = $this->rules([
            ['field' => 'scores.activity_score', 'operator' => '>', 'value' => 40, 'boolean' => 'AND'],
            ['field' => 'summary.total_actions', 'operator' => '<', 'value' => 3, 'boolean' => 'AND'],
        ]);

        $profile = ['scores' => ['activity_score' => 50], 'summary' => ['total_actions' => 1]];
        $this->assertTrue($this->evaluator->matches($profile, $rules));
    }

    public function test_or_combination_matches_when_only_one_rule_passes(): void
    {
        $rules = $this->rules([
            ['field' => 'scores.activity_score', 'operator' => '>', 'value' => 80, 'boolean' => 'AND'],
            ['field' => 'trends.7d_change', 'operator' => '<', 'value' => -20, 'boolean' => 'OR'],
        ]);

        // activity_score=50 fails, 7d_change=-30 passes → OR makes it true
        $profile = ['scores' => ['activity_score' => 50], 'trends' => ['7d_change' => -30]];
        $this->assertTrue($this->evaluator->matches($profile, $rules));
    }

    public function test_or_combination_fails_when_all_rules_fail(): void
    {
        $rules = $this->rules([
            ['field' => 'scores.activity_score', 'operator' => '>', 'value' => 80, 'boolean' => 'AND'],
            ['field' => 'trends.7d_change', 'operator' => '<', 'value' => -20, 'boolean' => 'OR'],
        ]);

        $profile = ['scores' => ['activity_score' => 50], 'trends' => ['7d_change' => 5]];
        $this->assertFalse($this->evaluator->matches($profile, $rules));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new SegmentRuleEvaluator();
    }
}
