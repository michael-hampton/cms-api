<?php

namespace App\Tests\Unit\Services\MemberInsights\Segmentation;

use App\Enums\Member\SegmentSubjectType;
use App\Services\MemberInsights\Segmentation\SegmentFieldRegistry;
use PHPUnit\Framework\TestCase;

class SegmentFieldRegistryTest extends TestCase
{
    private SegmentFieldRegistry $registry;

    // =========================================================================
    // Available fields
    // =========================================================================

    public function test_it_returns_available_member_fields(): void
    {
        $fields = $this->registry->getFields(SegmentSubjectType::Member);

        $this->assertNotEmpty($fields);
        $this->assertArrayHasKey('scores.activity_score', $fields);
    }

    public function test_it_returns_available_subscription_fields(): void
    {
        $fields = $this->registry->getFields(SegmentSubjectType::Subscription);

        $this->assertNotEmpty($fields);
        $this->assertArrayHasKey('subscription.renewal_date', $fields);
        $this->assertArrayHasKey('subscription.payment_type', $fields);
    }

    public function test_it_returns_available_plan_fields(): void
    {
        $fields = $this->registry->getFields(SegmentSubjectType::Plan);

        $this->assertNotEmpty($fields);
        $this->assertArrayHasKey('plan.price', $fields);
    }

    public function test_each_field_has_type_label_and_operators(): void
    {
        $fields = $this->registry->getFields(SegmentSubjectType::Subscription);

        foreach ($fields as $path => $definition) {
            $this->assertArrayHasKey('type', $definition, "Field {$path} missing 'type'");
            $this->assertArrayHasKey('label', $definition, "Field {$path} missing 'label'");
            $this->assertArrayHasKey('operators', $definition, "Field {$path} missing 'operators'");
            $this->assertNotEmpty($definition['operators'], "Field {$path} has no operators");
        }
    }

    // =========================================================================
    // Operator validation
    // =========================================================================

    public function test_it_returns_supported_operators_for_date_field(): void
    {
        $fields    = $this->registry->getFields(SegmentSubjectType::Subscription);
        $operators = $fields['subscription.renewal_date']['operators'];

        $this->assertContains('before', $operators);
        $this->assertContains('after', $operators);
        $this->assertContains('within_next_days', $operators);
    }

    public function test_it_returns_supported_operators_for_enum_field(): void
    {
        $fields    = $this->registry->getFields(SegmentSubjectType::Subscription);
        $operators = $fields['subscription.payment_type']['operators'];

        $this->assertContains('=', $operators);
        $this->assertContains('!=', $operators);
    }

    public function test_is_valid_operator_returns_true_for_known_operator(): void
    {
        $result = $this->registry->isValidOperator(
            SegmentSubjectType::Subscription,
            'subscription.renewal_date',
            'within_next_days'
        );

        $this->assertTrue($result);
    }

    public function test_is_valid_operator_returns_false_for_unknown_operator(): void
    {
        $result = $this->registry->isValidOperator(
            SegmentSubjectType::Subscription,
            'subscription.renewal_date',
            'INVALID_OP'
        );

        $this->assertFalse($result);
    }

    // =========================================================================
    // Unknown field rejection
    // =========================================================================

    public function test_it_rejects_unknown_fields_via_is_valid_field(): void
    {
        $this->assertFalse(
            $this->registry->isValidField(SegmentSubjectType::Subscription, 'nonexistent.field')
        );
    }

    public function test_is_valid_operator_throws_for_unknown_field(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not registered/');

        $this->registry->isValidOperator(
            SegmentSubjectType::Subscription,
            'does.not.exist',
            '='
        );
    }

    public function test_member_fields_are_not_present_in_subscription_registry(): void
    {
        $this->assertFalse(
            $this->registry->isValidField(SegmentSubjectType::Subscription, 'scores.activity_score')
        );
    }

    // =========================================================================
    // Setup
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new SegmentFieldRegistry();
    }
}