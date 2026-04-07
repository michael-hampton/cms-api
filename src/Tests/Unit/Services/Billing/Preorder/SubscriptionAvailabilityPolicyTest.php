<?php

namespace App\Tests\Unit\Services\Billing\Preorder;

use App\Models\IssueDelivery;
use App\Models\SubscriptionPlan;
use App\Services\Billing\Preorder\IssueAvailabilityPolicy;
use App\Services\Billing\Preorder\SubscriptionAvailabilityPolicy;
use Mockery;
use PHPUnit\Framework\TestCase;

class SubscriptionAvailabilityPolicyTest extends TestCase
{
    public function test_digital_subscription_can_purchase_when_no_release_date(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->release_date = null;
        $plan->pre_release_enabled = false;
        $plan->print_shipping_required = false;

        $policy = new SubscriptionAvailabilityPolicy($plan);

        $this->assertTrue($policy->canPurchase());
        $this->assertFalse($policy->isPreRelease());
        $this->assertFalse($policy->isPreOrder());
        $this->assertEquals('Available Now', $policy->getAvailabilityMessage());
    }

    // ========================================
    // DIGITAL SUBSCRIPTION TESTS
    // ========================================

    public function test_digital_subscription_can_purchase_when_release_date_in_past(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->release_date = new \DateTime('-7 days');
        $plan->pre_release_enabled = false;
        $plan->print_shipping_required = false;

        $policy = new SubscriptionAvailabilityPolicy($plan);

        $this->assertTrue($policy->canPurchase());
        $this->assertFalse($policy->isPreRelease());
        $this->assertEquals('Available Now', $policy->getAvailabilityMessage());
    }

    public function test_digital_subscription_can_purchase_when_pre_release_enabled(): void
    {
        $releaseDate = new \DateTime('+14 days');

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->release_date = $releaseDate;
        $plan->pre_release_enabled = true;
        $plan->print_shipping_required = false;

        $policy = new SubscriptionAvailabilityPolicy($plan);

        $this->assertTrue($policy->canPurchase());
        $this->assertTrue($policy->isPreRelease());
        $this->assertStringContainsString('Available for Pre-order', $policy->getAvailabilityMessage());
        $this->assertStringContainsString('Launches', $policy->getAvailabilityMessage());
    }

    public function test_digital_subscription_cannot_purchase_when_future_release_and_pre_release_disabled(): void
    {
        $releaseDate = new \DateTime('+14 days');

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->release_date = $releaseDate;
        $plan->pre_release_enabled = false;
        $plan->print_shipping_required = false;

        $policy = new SubscriptionAvailabilityPolicy($plan);

        $this->assertFalse($policy->canPurchase());
        $this->assertFalse($policy->isPreRelease());
        $this->assertStringContainsString('Available from', $policy->getAvailabilityMessage());
    }

    public function test_digital_subscription_expected_ship_date_always_null(): void
    {
        $releaseDate = new \DateTime('+14 days');

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->release_date = $releaseDate;
        $plan->pre_release_enabled = true;
        $plan->print_shipping_required = false;

        $policy = new SubscriptionAvailabilityPolicy($plan);

        $this->assertNull($policy->getExpectedShipDate());
    }

    public function test_print_subscription_cannot_purchase_when_plan_not_released(): void
    {
        $releaseDate = new \DateTime('+14 days');

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->release_date = $releaseDate;
        $plan->pre_release_enabled = false;
        $plan->print_shipping_required = true;

        $policy = new SubscriptionAvailabilityPolicy($plan);

        $this->assertFalse($policy->canPurchase());
    }

    // ========================================
    // PRINT SUBSCRIPTION TESTS - Plan Level
    // ========================================

    public function test_print_subscription_cannot_purchase_when_no_issues_scheduled(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->release_date = null;
        $plan->print_shipping_required = true;
        $plan->shouldReceive('getNextIssue')->andReturn(null);

        $policy = new SubscriptionAvailabilityPolicy($plan);

        $this->assertFalse($policy->canPurchase());
        $this->assertEquals('No issues available', $policy->getAvailabilityMessage());
    }

    public function test_print_subscription_can_purchase_when_next_issue_in_stock(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->release_date = null;
        $plan->print_shipping_required = true;

        $nextIssue = Mockery::mock(IssueDelivery::class)->makePartial();
        $nextIssue->stock_quantity = 100;
        $nextIssue->issue_number = 5;

        $issuePolicy = Mockery::mock(IssueAvailabilityPolicy::class);
        $issuePolicy->shouldReceive('canPurchase')->andReturn(true);
        $issuePolicy->shouldReceive('isPreOrder')->andReturn(false);
        $issuePolicy->shouldReceive('getAvailabilityMessage')->andReturn('Issue #5 - In Stock');

        $nextIssue->shouldReceive('availabilityPolicy')->andReturn($issuePolicy);
        $plan->shouldReceive('getNextIssue')->andReturn($nextIssue);

        $policy = new SubscriptionAvailabilityPolicy($plan);

        $this->assertTrue($policy->canPurchase());
        $this->assertFalse($policy->isPreOrder());
        $this->assertEquals('Issue #5 - In Stock', $policy->getAvailabilityMessage());
    }

    // ========================================
    // PRINT SUBSCRIPTION TESTS - With Issues
    // ========================================

    public function test_print_subscription_can_purchase_when_next_issue_is_preorder(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->release_date = null;
        $plan->print_shipping_required = true;

        $restockDate = new \DateTime('+7 days');

        $nextIssue = Mockery::mock(IssueDelivery::class)->makePartial();
        $nextIssue->stock_quantity = 0;
        $nextIssue->preorder_enabled = true;
        $nextIssue->restock_date = $restockDate;
        $nextIssue->issue_number = 6;

        $issuePolicy = Mockery::mock(IssueAvailabilityPolicy::class);
        $issuePolicy->shouldReceive('canPurchase')->andReturn(true);
        $issuePolicy->shouldReceive('isPreOrder')->andReturn(true);
        $issuePolicy->shouldReceive('getAvailabilityMessage')->andReturn('Issue #6 - Pre-order');
        $issuePolicy->shouldReceive('getExpectedShipDate')->andReturn($restockDate);

        $nextIssue->shouldReceive('availabilityPolicy')->andReturn($issuePolicy);
        $plan->shouldReceive('getNextIssue')->andReturn($nextIssue);

        $policy = new SubscriptionAvailabilityPolicy($plan);

        $this->assertTrue($policy->canPurchase());
        $this->assertTrue($policy->isPreOrder());
        $this->assertEquals($restockDate, $policy->getExpectedShipDate());
    }

    public function test_print_subscription_pre_release_when_plan_not_released(): void
    {
        $releaseDate = new \DateTime('+14 days');

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->release_date = $releaseDate;
        $plan->pre_release_enabled = true;
        $plan->print_shipping_required = true;

        $nextIssue = Mockery::mock(IssueDelivery::class)->makePartial();
        $nextIssue->stock_quantity = 100;

        $issuePolicy = Mockery::mock(IssueAvailabilityPolicy::class);
        $issuePolicy->shouldReceive('canPurchase')->andReturn(true);

        $nextIssue->shouldReceive('availabilityPolicy')->andReturn($issuePolicy);
        $plan->shouldReceive('getNextIssue')->andReturn($nextIssue);

        $policy = new SubscriptionAvailabilityPolicy($plan);

        $this->assertTrue($policy->canPurchase());
        $this->assertTrue($policy->isPreRelease());
        $this->assertEquals(
            $releaseDate->getTimestamp(),
            $policy->getExpectedShipDate()?->getTimestamp()
        );
    }

    public function test_print_subscription_pre_release_when_next_issue_not_on_sale(): void
    {
        $onSaleDate = new \DateTime('+21 days');

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->release_date = null; // Plan already released
        $plan->print_shipping_required = true;

        $nextIssue = Mockery::mock(IssueDelivery::class)->makePartial();
        $nextIssue->on_sale_date = $onSaleDate;
        $nextIssue->stock_quantity = 100;

        $issuePolicy = Mockery::mock(IssueAvailabilityPolicy::class);
        $issuePolicy->shouldReceive('canPurchase')->andReturn(true);
        $issuePolicy->shouldReceive('isPreRelease')->andReturn(true);
        $issuePolicy->shouldReceive('getExpectedShipDate')->andReturn($onSaleDate);

        $nextIssue->shouldReceive('availabilityPolicy')->andReturn($issuePolicy);
        $plan->shouldReceive('getNextIssue')->andReturn($nextIssue);

        $policy = new SubscriptionAvailabilityPolicy($plan);

        $this->assertTrue($policy->isPreRelease());
        $this->assertEquals($onSaleDate, $policy->getExpectedShipDate());
    }

    public function test_print_subscription_both_plan_pre_release_and_issue_preorder(): void
    {
        $planReleaseDate = new \DateTime('+30 days');
        $issueRestockDate = new \DateTime('+35 days');

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->release_date = $planReleaseDate;
        $plan->pre_release_enabled = true;
        $plan->print_shipping_required = true;

        $nextIssue = Mockery::mock(IssueDelivery::class)->makePartial();
        $nextIssue->stock_quantity = 0;
        $nextIssue->preorder_enabled = true;
        $nextIssue->restock_date = $issueRestockDate;

        $issuePolicy = Mockery::mock(IssueAvailabilityPolicy::class);
        $issuePolicy->shouldReceive('canPurchase')->andReturn(true);
        $issuePolicy->shouldReceive('isPreOrder')->andReturn(true);

        $nextIssue->shouldReceive('availabilityPolicy')->andReturn($issuePolicy);
        $plan->shouldReceive('getNextIssue')->andReturn($nextIssue);

        $policy = new SubscriptionAvailabilityPolicy($plan);

        $this->assertTrue($policy->canPurchase());
        $this->assertTrue($policy->isPreRelease()); // Plan level
        $this->assertTrue($policy->isPreOrder()); // Issue level
        // Plan release takes precedence for ship date
        $this->assertEquals(
            $planReleaseDate->getTimestamp(),
            $policy->getExpectedShipDate()?->getTimestamp()
        );
    }

    // ========================================
    // COMBINED SCENARIOS
    // ========================================

    public function test_is_pre_order_false_for_digital_subscriptions(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->release_date = new \DateTime('+7 days');
        $plan->pre_release_enabled = true;
        $plan->print_shipping_required = false;

        $policy = new SubscriptionAvailabilityPolicy($plan);

        $this->assertFalse($policy->isPreOrder());
    }

    public function test_availability_message_includes_formatted_date_for_pre_release(): void
    {
        $releaseDate = now_datetime()->addMonths(1);

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->release_date = $releaseDate;
        $plan->pre_release_enabled = true;
        $plan->print_shipping_required = false;

        $policy = new SubscriptionAvailabilityPolicy($plan);

        $message = $policy->getAvailabilityMessage();
        $this->assertStringContainsString($releaseDate->format('M j, Y'), $message);
    }

    // ========================================
    // DATE FORMATTING TESTS
    // ========================================

    public function test_availability_message_includes_formatted_date_when_not_available(): void
    {
        $releaseDate = new \DateTime('2026-05-15');

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->release_date = $releaseDate;
        $plan->pre_release_enabled = false;
        $plan->print_shipping_required = false;

        $policy = new SubscriptionAvailabilityPolicy($plan);

        $message = $policy->getAvailabilityMessage();
        $this->assertStringContainsString('Available from May 15, 2026', $message);
    }

    public function test_transitions_from_pre_release_to_available(): void
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();

        // Initially pre-release
        $plan->release_date = new \DateTime('+1 day');
        $plan->pre_release_enabled = true;
        $plan->print_shipping_required = false;

        $policy = new SubscriptionAvailabilityPolicy($plan);
        $this->assertTrue($policy->isPreRelease());

        // Release date passes
        $plan->release_date = new \DateTime('-1 day');

        $policyAfterRelease = new SubscriptionAvailabilityPolicy($plan);
        $this->assertFalse($policyAfterRelease->isPreRelease());
        $this->assertEquals('Available Now', $policyAfterRelease->getAvailabilityMessage());
    }

    // ========================================
    // STATE TRANSITION TESTS
    // ========================================

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}