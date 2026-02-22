<?php

namespace App\Tests\Unit\Models;

use App\Models\Member;
use App\Models\RewardDefinition;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

/**
 * Unit tests for RewardDefinition pure business logic.
 *
 * No database required. All tests exercise code paths that resolve
 * without touching the DB:
 *   - checkCriteria() guard clauses (null/empty criteria)
 *   - checkCriteria() with the 'signup' type (always returns 1, no DB)
 *   - compareValues() via all six operators
 *   - formatCriterion() string output for all known types and operators
 *
 * Criteria types that call DB relationships (badges_earned, points_earned,
 * comments_count, subscriptions_count, orders_completed, member_days) are
 * covered in RewardDefinitionFunctionalTest using a real database.
 *
 * Known design issues surfaced by reading the model (not fixed here —
 * tests document current behaviour):
 *   - getCurrentValue() uses static Model calls directly (infrastructure
 *     leakage — should be injected collaborators).
 *   - formatCriterion() is presentation logic and does not belong on
 *     a domain model.
 */
class RewardDefinitionTest extends FunctionalTestCase
{
    use CreatesTestData;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function test_check_criteria_returns_false_when_criteria_is_null(): void
    {
        $definition = $this->makeDefinition(['criteria' => null]);

        $result = $definition->checkCriteria($this->makeMember());

        $this->assertFalse($result);
    }

    private function makeDefinition(array $attributes = []): RewardDefinition
    {
        $definition = new RewardDefinition();
        $definition->fill($attributes);

        return $definition;
    }

    // -------------------------------------------------------------------------
    // checkCriteria — guard clauses
    // -------------------------------------------------------------------------

    /**
     * Real Member instance. checkCriteria() accepts Member — for the 'signup'
     * criterion the member object is never actually read, but the type must
     * be satisfied.
     */
    private function makeMember(): Member
    {
        $member = new Member();
        $member->fill(['id' => 1]);

        return $member;
    }

    public function test_check_criteria_returns_false_when_criteria_is_empty_array(): void
    {
        $definition = $this->makeDefinition(['criteria' => []]);

        $result = $definition->checkCriteria($this->makeMember());

        $this->assertFalse($result);
    }

    public function test_check_criteria_returns_false_when_criteria_is_not_set(): void
    {
        $definition = new RewardDefinition();

        $result = $definition->checkCriteria($this->makeMember());

        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // checkCriteria — 'signup' type (no DB, returns constant 1)
    // -------------------------------------------------------------------------

    public function test_check_criteria_returns_true_for_signup_criterion(): void
    {
        $definition = $this->makeDefinition([
            'criteria' => [
                ['type' => 'signup', 'operator' => '>=', 'value' => 1],
            ],
        ]);

        $result = $definition->checkCriteria($this->makeMember());

        $this->assertTrue($result);
    }

    public function test_check_criteria_returns_false_when_any_criterion_fails(): void
    {
        // First criterion passes (signup >= 1), second fails (signup > 1 — value is 1, not > 1).
        $definition = $this->makeDefinition([
            'criteria' => [
                ['type' => 'signup', 'operator' => '>=', 'value' => 1],
                ['type' => 'signup', 'operator' => '>', 'value' => 1],
            ],
        ]);

        $result = $definition->checkCriteria($this->makeMember());

        $this->assertFalse($result);
    }

    public function test_check_criteria_returns_true_when_all_criteria_pass(): void
    {
        $definition = $this->makeDefinition([
            'criteria' => [
                ['type' => 'signup', 'operator' => '>=', 'value' => 1],
                ['type' => 'signup', 'operator' => '==', 'value' => 1],
            ],
        ]);

        $result = $definition->checkCriteria($this->makeMember());

        $this->assertTrue($result);
    }

    public function test_check_criteria_uses_default_operator_gte_when_operator_missing(): void
    {
        // No operator key — should default to '>=' per checkSingleCriterion.
        $definition = $this->makeDefinition([
            'criteria' => [
                ['type' => 'signup', 'value' => 1], // operator omitted
            ],
        ]);

        $result = $definition->checkCriteria($this->makeMember());

        // signup returns 1, default operator '>=' , value 1 → 1 >= 1 → true
        $this->assertTrue($result);
    }

    public function test_check_criteria_uses_default_value_zero_when_value_missing(): void
    {
        // No value key — should default to 0 per checkSingleCriterion.
        $definition = $this->makeDefinition([
            'criteria' => [
                ['type' => 'signup', 'operator' => '>='], // value omitted
            ],
        ]);

        $result = $definition->checkCriteria($this->makeMember());

        // signup returns 1, operator '>=', default value 0 → 1 >= 0 → true
        $this->assertTrue($result);
    }

    public function test_check_criteria_returns_false_for_unknown_criterion_type(): void
    {
        // Unknown types fall through to default => 0 in getCurrentValue().
        // 0 >= 1 is false.
        $definition = $this->makeDefinition([
            'criteria' => [
                ['type' => 'unknown_type', 'operator' => '>=', 'value' => 1],
            ],
        ]);

        $result = $definition->checkCriteria($this->makeMember());

        $this->assertFalse($result);
    }

    public function test_check_criteria_returns_true_for_unknown_type_when_value_is_zero(): void
    {
        // Unknown type returns 0; 0 >= 0 is true.
        $definition = $this->makeDefinition([
            'criteria' => [
                ['type' => 'unknown_type', 'operator' => '>=', 'value' => 0],
            ],
        ]);

        $result = $definition->checkCriteria($this->makeMember());

        $this->assertTrue($result);
    }

    // -------------------------------------------------------------------------
    // compareValues — all six operators (exercised via signup criteria)
    // -------------------------------------------------------------------------

    public function test_compare_values_gte_returns_true_when_equal(): void
    {
        $definition = $this->makeDefinition([
            'criteria' => [['type' => 'signup', 'operator' => '>=', 'value' => 1]],
        ]);

        $this->assertTrue($definition->checkCriteria($this->makeMember()));
    }

    public function test_compare_values_gte_returns_true_when_greater(): void
    {
        $definition = $this->makeDefinition([
            'criteria' => [['type' => 'signup', 'operator' => '>=', 'value' => 0]],
        ]);

        $this->assertTrue($definition->checkCriteria($this->makeMember()));
    }

    public function test_compare_values_gte_returns_false_when_less(): void
    {
        $definition = $this->makeDefinition([
            'criteria' => [['type' => 'signup', 'operator' => '>=', 'value' => 2]],
        ]);

        $this->assertFalse($definition->checkCriteria($this->makeMember()));
    }

    public function test_compare_values_gt_returns_true_when_greater(): void
    {
        $definition = $this->makeDefinition([
            'criteria' => [['type' => 'signup', 'operator' => '>', 'value' => 0]],
        ]);

        $this->assertTrue($definition->checkCriteria($this->makeMember()));
    }

    public function test_compare_values_gt_returns_false_when_equal(): void
    {
        $definition = $this->makeDefinition([
            'criteria' => [['type' => 'signup', 'operator' => '>', 'value' => 1]],
        ]);

        $this->assertFalse($definition->checkCriteria($this->makeMember()));
    }

    public function test_compare_values_lte_returns_true_when_equal(): void
    {
        $definition = $this->makeDefinition([
            'criteria' => [['type' => 'signup', 'operator' => '<=', 'value' => 1]],
        ]);

        $this->assertTrue($definition->checkCriteria($this->makeMember()));
    }

    public function test_compare_values_lte_returns_true_when_less(): void
    {
        $definition = $this->makeDefinition([
            'criteria' => [['type' => 'signup', 'operator' => '<=', 'value' => 2]],
        ]);

        $this->assertTrue($definition->checkCriteria($this->makeMember()));
    }

    public function test_compare_values_lte_returns_false_when_greater(): void
    {
        $definition = $this->makeDefinition([
            'criteria' => [['type' => 'signup', 'operator' => '<=', 'value' => 0]],
        ]);

        $this->assertFalse($definition->checkCriteria($this->makeMember()));
    }

    public function test_compare_values_lt_returns_true_when_less(): void
    {
        $definition = $this->makeDefinition([
            'criteria' => [['type' => 'signup', 'operator' => '<', 'value' => 2]],
        ]);

        $this->assertTrue($definition->checkCriteria($this->makeMember()));
    }

    public function test_compare_values_lt_returns_false_when_equal(): void
    {
        $definition = $this->makeDefinition([
            'criteria' => [['type' => 'signup', 'operator' => '<', 'value' => 1]],
        ]);

        $this->assertFalse($definition->checkCriteria($this->makeMember()));
    }

    public function test_compare_values_eq_returns_true_when_equal(): void
    {
        $definition = $this->makeDefinition([
            'criteria' => [['type' => 'signup', 'operator' => '==', 'value' => 1]],
        ]);

        $this->assertTrue($definition->checkCriteria($this->makeMember()));
    }

    public function test_compare_values_eq_returns_false_when_not_equal(): void
    {
        $definition = $this->makeDefinition([
            'criteria' => [['type' => 'signup', 'operator' => '==', 'value' => 0]],
        ]);

        $this->assertFalse($definition->checkCriteria($this->makeMember()));
    }

    public function test_compare_values_neq_returns_true_when_not_equal(): void
    {
        $definition = $this->makeDefinition([
            'criteria' => [['type' => 'signup', 'operator' => '!=', 'value' => 0]],
        ]);

        $this->assertTrue($definition->checkCriteria($this->makeMember()));
    }

    public function test_compare_values_neq_returns_false_when_equal(): void
    {
        $definition = $this->makeDefinition([
            'criteria' => [['type' => 'signup', 'operator' => '!=', 'value' => 1]],
        ]);

        $this->assertFalse($definition->checkCriteria($this->makeMember()));
    }

    public function test_compare_values_unknown_operator_returns_false(): void
    {
        $definition = $this->makeDefinition([
            'criteria' => [['type' => 'signup', 'operator' => '===', 'value' => 1]],
        ]);

        $this->assertFalse($definition->checkCriteria($this->makeMember()));
    }

    // -------------------------------------------------------------------------
    // formatCriterion — all known types and operators
    // -------------------------------------------------------------------------

    public function test_format_criterion_signup_ignores_operator_and_value(): void
    {
        $definition = $this->makeDefinition();

        $result = $definition->formatCriterion(['type' => 'signup', 'operator' => '>=', 'value' => 1]);

        $this->assertEquals('Sign up for an account', $result);
    }

    public function test_format_criterion_badges_earned_singular(): void
    {
        $definition = $this->makeDefinition();

        $result = $definition->formatCriterion(['type' => 'badges_earned', 'operator' => '>=', 'value' => 1]);

        $this->assertEquals('Earn at least 1 badge', $result);
    }

    public function test_format_criterion_badges_earned_plural(): void
    {
        $definition = $this->makeDefinition();

        $result = $definition->formatCriterion(['type' => 'badges_earned', 'operator' => '>=', 'value' => 5]);

        $this->assertEquals('Earn at least 5 badges', $result);
    }

    public function test_format_criterion_points_earned_always_plural_label(): void
    {
        $definition = $this->makeDefinition();

        $result = $definition->formatCriterion(['type' => 'points_earned', 'operator' => '>', 'value' => 100]);

        $this->assertEquals('Earn more than 100 points', $result);
    }

    public function test_format_criterion_comments_count_singular(): void
    {
        $definition = $this->makeDefinition();

        $result = $definition->formatCriterion(['type' => 'comments_count', 'operator' => '>=', 'value' => 1]);

        $this->assertEquals('Post at least 1 comment', $result);
    }

    public function test_format_criterion_comments_count_plural(): void
    {
        $definition = $this->makeDefinition();

        $result = $definition->formatCriterion(['type' => 'comments_count', 'operator' => '>=', 'value' => 3]);

        $this->assertEquals('Post at least 3 comments', $result);
    }

    public function test_format_criterion_orders_completed_singular(): void
    {
        $definition = $this->makeDefinition();

        $result = $definition->formatCriterion(['type' => 'orders_completed', 'operator' => '==', 'value' => 1]);

        $this->assertEquals('Complete exactly 1 order', $result);
    }

    public function test_format_criterion_orders_completed_plural(): void
    {
        $definition = $this->makeDefinition();

        $result = $definition->formatCriterion(['type' => 'orders_completed', 'operator' => '>=', 'value' => 10]);

        $this->assertEquals('Complete at least 10 orders', $result);
    }

    public function test_format_criterion_member_days_singular(): void
    {
        $definition = $this->makeDefinition();

        $result = $definition->formatCriterion(['type' => 'member_days', 'operator' => '>=', 'value' => 1]);

        $this->assertEquals('Be a member for at least 1 day', $result);
    }

    public function test_format_criterion_member_days_plural(): void
    {
        $definition = $this->makeDefinition();

        $result = $definition->formatCriterion(['type' => 'member_days', 'operator' => '>=', 'value' => 30]);

        $this->assertEquals('Be a member for at least 30 days', $result);
    }

    public function test_format_criterion_subscriptions_count_singular(): void
    {
        $definition = $this->makeDefinition();

        $result = $definition->formatCriterion(['type' => 'subscriptions_count', 'operator' => '>=', 'value' => 1]);

        $this->assertEquals('Have at least 1 active subscription', $result);
    }

    public function test_format_criterion_subscriptions_count_plural(): void
    {
        $definition = $this->makeDefinition();

        $result = $definition->formatCriterion(['type' => 'subscriptions_count', 'operator' => '>=', 'value' => 2]);

        $this->assertEquals('Have at least 2 active subscriptions', $result);
    }

    public function test_format_criterion_unknown_type_returns_fallback(): void
    {
        $definition = $this->makeDefinition();

        $result = $definition->formatCriterion(['type' => 'unknown_type', 'operator' => '>=', 'value' => 1]);

        $this->assertEquals('Complete required action', $result);
    }

    public function test_format_criterion_operator_more_than(): void
    {
        $definition = $this->makeDefinition();

        $result = $definition->formatCriterion(['type' => 'orders_completed', 'operator' => '>', 'value' => 5]);

        $this->assertEquals('Complete more than 5 orders', $result);
    }

    public function test_format_criterion_operator_at_most(): void
    {
        $definition = $this->makeDefinition();

        $result = $definition->formatCriterion(['type' => 'orders_completed', 'operator' => '<=', 'value' => 5]);

        $this->assertEquals('Complete at most 5 orders', $result);
    }

    public function test_format_criterion_operator_less_than(): void
    {
        $definition = $this->makeDefinition();

        $result = $definition->formatCriterion(['type' => 'orders_completed', 'operator' => '<', 'value' => 5]);

        $this->assertEquals('Complete less than 5 orders', $result);
    }

    public function test_format_criterion_operator_exactly(): void
    {
        $definition = $this->makeDefinition();

        $result = $definition->formatCriterion(['type' => 'orders_completed', 'operator' => '==', 'value' => 5]);

        $this->assertEquals('Complete exactly 5 orders', $result);
    }

    public function test_format_criterion_unknown_operator_produces_empty_operator_text(): void
    {
        $definition = $this->makeDefinition();

        // Unknown operator falls through to '' in the match — still produces a sentence.
        $result = $definition->formatCriterion(['type' => 'orders_completed', 'operator' => '===', 'value' => 1]);

        $this->assertEquals('Complete  1 order', $result);
    }

    public function test_format_criterion_uses_default_operator_when_missing(): void
    {
        $definition = $this->makeDefinition();

        $result = $definition->formatCriterion(['type' => 'orders_completed', 'value' => 3]);

        // Default operator is '>=' → 'at least'
        $this->assertEquals('Complete at least 3 orders', $result);
    }

    public function test_format_criterion_uses_default_value_zero_when_missing(): void
    {
        $definition = $this->makeDefinition();

        $result = $definition->formatCriterion(['type' => 'badges_earned', 'operator' => '>=']);

        // Default value is 0, $s(0) returns 's'
        $this->assertEquals('Earn at least 0 badges', $result);
    }
    // -------------------------------------------------------------------------
    // badges_earned
    // -------------------------------------------------------------------------

    public function test_badges_earned_returns_true_when_member_meets_threshold(): void
    {
        $member = $this->createMember();
        $this->createMemberBadge(['member_id' => $member->id]);
        $this->createMemberBadge(['member_id' => $member->id]);

        $definition = $this->createRewardDefinition([
            'criteria' => [['type' => 'badges_earned', 'operator' => '>=', 'value' => 2]],
        ]);

        $this->assertTrue($definition->checkCriteria($member));
    }

    public function test_badges_earned_returns_false_when_member_below_threshold(): void
    {
        $member = $this->createMember();
        $this->createMemberBadge(['member_id' => $member->id]);

        $definition = $this->createRewardDefinition([
            'criteria' => [['type' => 'badges_earned', 'operator' => '>=', 'value' => 2]],
        ]);

        $this->assertFalse($definition->checkCriteria($member));
    }

    public function test_badges_earned_returns_false_when_member_has_no_badges(): void
    {
        $member = $this->createMember();

        $definition = $this->createRewardDefinition([
            'criteria' => [['type' => 'badges_earned', 'operator' => '>=', 'value' => 1]],
        ]);

        $this->assertFalse($definition->checkCriteria($member));
    }

    // -------------------------------------------------------------------------
    // points_earned
    // -------------------------------------------------------------------------

    public function test_points_earned_returns_true_when_member_meets_threshold(): void
    {
        $member = $this->createMember();
        $this->createMemberPoint(['member_id' => $member->id, 'points' => 60]);
        $this->createMemberPoint(['member_id' => $member->id, 'points' => 40]);

        $definition = $this->createRewardDefinition([
            'criteria' => [['type' => 'points_earned', 'operator' => '>=', 'value' => 100]],
        ]);

        $this->assertTrue($definition->checkCriteria($member));
    }

    public function test_points_earned_returns_false_when_member_below_threshold(): void
    {
        $member = $this->createMember();
        $this->createMemberPoint(['member_id' => $member->id, 'points' => 50]);

        $definition = $this->createRewardDefinition([
            'criteria' => [['type' => 'points_earned', 'operator' => '>=', 'value' => 100]],
        ]);

        $this->assertFalse($definition->checkCriteria($member));
    }

    public function test_points_earned_returns_false_when_member_has_no_points(): void
    {
        $member = $this->createMember();

        $definition = $this->createRewardDefinition([
            'criteria' => [['type' => 'points_earned', 'operator' => '>=', 'value' => 1]],
        ]);

        $this->assertFalse($definition->checkCriteria($member));
    }

    // -------------------------------------------------------------------------
    // comments_count
    // -------------------------------------------------------------------------

    public function test_comments_count_returns_true_when_member_meets_threshold(): void
    {
        $member = $this->createMember();
        $this->createComment(['member_id' => $member->id]);
        $this->createComment(['member_id' => $member->id]);
        $this->createComment(['member_id' => $member->id]);

        $definition = $this->createRewardDefinition([
            'criteria' => [['type' => 'comments_count', 'operator' => '>=', 'value' => 3]],
        ]);

        $this->assertTrue($definition->checkCriteria($member));
    }

    public function test_comments_count_returns_false_when_member_below_threshold(): void
    {
        $member = $this->createMember();
        $this->createComment(['member_id' => $member->id]);

        $definition = $this->createRewardDefinition([
            'criteria' => [['type' => 'comments_count', 'operator' => '>=', 'value' => 3]],
        ]);

        $this->assertFalse($definition->checkCriteria($member));
    }

    public function test_comments_count_excludes_other_members_comments(): void
    {
        $member1 = $this->createMember();
        $member2 = $this->createMember();

        $this->createComment(['member_id' => $member2->id]);
        $this->createComment(['member_id' => $member2->id]);
        $this->createComment(['member_id' => $member2->id]);

        $definition = $this->createRewardDefinition([
            'criteria' => [['type' => 'comments_count', 'operator' => '>=', 'value' => 1]],
        ]);

        // member1 has zero comments — must not count member2's comments.
        $this->assertFalse($definition->checkCriteria($member1));
    }

    // -------------------------------------------------------------------------
    // subscriptions_count
    // -------------------------------------------------------------------------

    public function test_subscriptions_count_returns_true_when_member_has_active_subscription(): void
    {
        $member = $this->createMember();
        $this->createSubscription(['member_id' => $member->id, 'status' => 'active']);

        $definition = $this->createRewardDefinition([
            'criteria' => [['type' => 'subscriptions_count', 'operator' => '>=', 'value' => 1]],
        ]);

        $this->assertTrue($definition->checkCriteria($member));
    }

    public function test_subscriptions_count_excludes_inactive_subscriptions(): void
    {
        $member = $this->createMember();
        $this->createSubscription(['member_id' => $member->id, 'status' => 'cancelled']);
        $this->createSubscription(['member_id' => $member->id, 'status' => 'expired']);

        $definition = $this->createRewardDefinition([
            'criteria' => [['type' => 'subscriptions_count', 'operator' => '>=', 'value' => 1]],
        ]);

        $this->assertFalse($definition->checkCriteria($member));
    }

    public function test_subscriptions_count_returns_false_when_member_has_no_subscriptions(): void
    {
        $member = $this->createMember();

        $definition = $this->createRewardDefinition([
            'criteria' => [['type' => 'subscriptions_count', 'operator' => '>=', 'value' => 1]],
        ]);

        $this->assertFalse($definition->checkCriteria($member));
    }

    // -------------------------------------------------------------------------
    // orders_completed
    // -------------------------------------------------------------------------

    public function test_orders_completed_returns_true_when_member_meets_threshold(): void
    {
        $member = $this->createMember();

        $this->createOrder(['user_id' => $member->id, 'status' => 'completed']);
        $this->createOrder(['user_id' => $member->id, 'status' => 'completed']);

        $definition = $this->createRewardDefinition([
            'criteria' => [['type' => 'orders_completed', 'operator' => '>=', 'value' => 2]],
        ]);

        $this->assertTrue($definition->checkCriteria($member));
    }

    public function test_orders_completed_excludes_non_completed_orders(): void
    {
        $member = $this->createMember();

        $this->createOrder(['user_id' => $member->id, 'status' => 'pending']);
        $this->createOrder(['user_id' => $member->id, 'status' => 'cancelled']);

        $definition = $this->createRewardDefinition([
            'criteria' => [['type' => 'orders_completed', 'operator' => '>=', 'value' => 1]],
        ]);

        $this->assertFalse($definition->checkCriteria($member));
    }

    public function test_orders_completed_excludes_other_members_orders(): void
    {
        $member1 = $this->createMember();
        $member2 = $this->createMember();

        $this->createOrder(['user_id' => $member2->id, 'status' => 'completed']);
        $this->createOrder(['user_id' => $member2->id, 'status' => 'completed']);

        $definition = $this->createRewardDefinition([
            'criteria' => [['type' => 'orders_completed', 'operator' => '>=', 'value' => 1]],
        ]);

        $this->assertFalse($definition->checkCriteria($member1));
    }

    public function test_orders_completed_returns_false_when_member_has_no_orders(): void
    {
        $member = $this->createMember();

        $definition = $this->createRewardDefinition([
            'criteria' => [['type' => 'orders_completed', 'operator' => '>=', 'value' => 1]],
        ]);

        $this->assertFalse($definition->checkCriteria($member));
    }

    // -------------------------------------------------------------------------
    // member_days
    // -------------------------------------------------------------------------

    public function test_member_days_returns_true_for_long_standing_member(): void
    {
        $member = $this->createMember();
        $member->created_at = new \DateTime('-365 days');

        $definition = $this->createRewardDefinition([
            'criteria' => [['type' => 'member_days', 'operator' => '>=', 'value' => 30]],
        ]);

        $this->assertTrue($definition->checkCriteria($member));
    }

    public function test_member_days_returns_false_for_new_member(): void
    {
        $member = $this->createMember([
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);

        $definition = $this->createRewardDefinition([
            'criteria' => [['type' => 'member_days', 'operator' => '>=', 'value' => 30]],
        ]);

        $this->assertFalse($definition->checkCriteria($member));
    }

    public function test_member_days_returns_true_when_exactly_at_threshold(): void
    {
        $member = $this->createMember();
        $member->created_at = new \DateTime('-30 days');

        $definition = $this->createRewardDefinition([
            'criteria' => [['type' => 'member_days', 'operator' => '>=', 'value' => 30]],
        ]);

        $this->assertTrue($definition->checkCriteria($member));
    }

    // -------------------------------------------------------------------------
    // Multiple criteria — all must pass (AND logic)
    // -------------------------------------------------------------------------

    public function test_multiple_criteria_all_pass(): void
    {
        $member = $this->createMember();
        $member->created_at = new \DateTime('-60 days');
        $this->createMemberBadge(['member_id' => $member->id]);
        $this->createMemberPoint(['member_id' => $member->id, 'points' => 100]);

        $definition = $this->createRewardDefinition([
            'criteria' => [['type' => 'signup', 'operator' => '>=', 'value' => 1],
                ['type' => 'badges_earned', 'operator' => '>=', 'value' => 1],
                ['type' => 'points_earned', 'operator' => '>=', 'value' => 100],
                ['type' => 'member_days', 'operator' => '>=', 'value' => 30],
            ]]);

        $this->assertTrue($definition->checkCriteria($member));
    }


    public function test_multiple_criteria_fails_if_one_does_not_pass(): void
    {
        $member = $this->createMember([
            'created_at' => date('Y-m-d H:i:s', strtotime('-60 days')),
        ]);
        // No badges created — badges_earned criterion will fail.
        $this->createMemberPoint(['member_id' => $member->id, 'points' => 100]);

        $definition = $this->createRewardDefinition([
            'criteria' => [['type' => 'signup', 'operator' => '>=', 'value' => 1],
                ['type' => 'badges_earned', 'operator' => '>=', 'value' => 1],
                ['type' => 'points_earned', 'operator' => '>=', 'value' => 100],
            ]]);

        $this->assertFalse($definition->checkCriteria($member));
    }
}