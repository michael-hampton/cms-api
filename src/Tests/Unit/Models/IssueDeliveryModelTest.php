<?php

namespace App\Tests\Unit\Models;

use App\Enums\Subscriptions\IssueDeliveryStatus;
use App\Models\IssueDelivery;
use App\Models\SubscriptionPlan;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class IssueDeliveryModelTest extends FunctionalTestCase
{
    // =========================================================================
    // Status helpers use IssueDeliveryStatus (not the old IssueScheduleStatus)
    // =========================================================================

    public function test_is_active_returns_true_for_active_status(): void
    {
        $delivery = $this->makeDelivery(IssueDeliveryStatus::ACTIVE);
        $this->assertTrue($delivery->isActive());
    }

    public function test_is_active_returns_false_for_dispatched_status(): void
    {
        $delivery = $this->makeDelivery(IssueDeliveryStatus::DISPATCHED);
        $this->assertFalse($delivery->isActive());
    }

    public function test_is_dispatched(): void
    {
        $delivery = $this->makeDelivery(IssueDeliveryStatus::DISPATCHED);
        $this->assertTrue($delivery->isDispatched());
    }

    public function test_is_failed(): void
    {
        $delivery = $this->makeDelivery(IssueDeliveryStatus::FAILED);
        $this->assertTrue($delivery->isFailed());
    }

    // =========================================================================
    // Date-driven delivery progress is independent of dispatch status
    // =========================================================================

    public function test_is_cancelled(): void
    {
        $delivery = $this->makeDelivery(IssueDeliveryStatus::CANCELLED);
        $this->assertTrue($delivery->isCancelled());
    }

    public function test_calculate_delivery_progress_returns_scheduled_when_no_sale_date(): void
    {
        $delivery              = new IssueDelivery();
        $delivery->on_sale_date = null;

        $this->assertSame('Scheduled', $delivery->calculateStatus());
    }

    public function test_calculate_delivery_progress_returns_scheduled_when_sale_date_in_future(): void
    {
        $delivery                        = new IssueDelivery();
        $delivery->on_sale_date           = new \DateTime('+10 days');
        $delivery->estimated_delivery_date = new \DateTime('+15 days');

        $this->assertSame('Scheduled', $delivery->calculateStatus());
    }

    public function test_calculate_delivery_progress_returns_in_transit_between_sale_and_delivery(): void
    {
        $delivery                        = new IssueDelivery();
        $delivery->on_sale_date           = new \DateTime('-2 days');
        $delivery->estimated_delivery_date = new \DateTime('+3 days');

        $this->assertSame('In Transit', $delivery->calculateStatus());
    }

    public function test_calculate_delivery_progress_returns_delivered_after_delivery_date(): void
    {
        $delivery                        = new IssueDelivery();
        $delivery->on_sale_date           = new \DateTime('-10 days');
        $delivery->estimated_delivery_date = new \DateTime('-2 days');

        $this->assertSame('Delivered', $delivery->calculateStatus());
    }

    // =========================================================================
    // Delivery progress is independent of dispatch status
    // =========================================================================

    public function test_delivery_progress_uses_tracking_info_when_present(): void
    {
        $delivery               = new IssueDelivery();
        $delivery->on_sale_date  = new \DateTime('-5 days');
        $delivery->tracking_info = ['status' => 'Out for Delivery'];

        $this->assertSame('Out for Delivery', $delivery->calculateStatus());
    }

    public function test_failed_dispatch_does_not_change_delivery_progress(): void
    {
        $delivery                        = new IssueDelivery();
        $delivery->status                 = IssueDeliveryStatus::FAILED->value;
        $delivery->on_sale_date           = new \DateTime('-2 days');
        $delivery->estimated_delivery_date = new \DateTime('+3 days');

        $this->assertSame('In Transit', $delivery->calculateStatus());
    }

    // =========================================================================
    // Cover image helpers
    // =========================================================================

    public function test_has_own_cover_image_returns_false_when_null(): void
    {
        $delivery              = new IssueDelivery();
        $delivery->cover_image = null;

        $this->assertFalse($delivery->hasOwnCoverImage());
    }

    public function test_has_own_cover_image_returns_true_when_set(): void
    {
        $delivery              = new IssueDelivery();
        $delivery->cover_image = 'uploads/issue-covers/test.jpg';

        $this->assertTrue($delivery->hasOwnCoverImage());
    }

    public function test_get_cover_image_url_returns_own_image_when_present(): void
    {
        $delivery              = new IssueDelivery();
        $delivery->cover_image = 'uploads/issue-covers/test.jpg';

        $this->assertSame('uploads/issue-covers/test.jpg', $delivery->getCoverImageUrl());
    }

    public function test_get_cover_image_url_falls_back_to_plan_print_image(): void
    {
        $delivery              = new IssueDelivery();
        $delivery->cover_image = null;

        $plan                  = new SubscriptionPlan();
        $plan->print_image_url = 'uploads/plans/print.jpg';

        $this->assertSame('uploads/plans/print.jpg', $delivery->getCoverImageUrl($plan));
    }

    public function test_get_cover_image_url_falls_back_to_plan_digital_image_when_no_print(): void
    {
        $delivery              = new IssueDelivery();
        $delivery->cover_image = null;

        $plan                    = new SubscriptionPlan();
        $plan->print_image_url   = null;
        $plan->digital_image_url = 'uploads/plans/digital.jpg';

        $this->assertSame('uploads/plans/digital.jpg', $delivery->getCoverImageUrl($plan));
    }

    public function test_get_cover_image_url_returns_own_image_even_when_plan_also_has_image(): void
    {
        $delivery              = new IssueDelivery();
        $delivery->cover_image = 'uploads/issue-covers/special.jpg';

        $plan                  = new SubscriptionPlan();
        $plan->print_image_url = 'uploads/plans/print.jpg';

        // Issue-level image takes priority over plan-level image
        $this->assertSame('uploads/issue-covers/special.jpg', $delivery->getCoverImageUrl($plan));
    }

    public function test_get_cover_image_url_returns_null_when_no_image_anywhere(): void
    {
        $delivery              = new IssueDelivery();
        $delivery->cover_image = null;

        $plan                    = new SubscriptionPlan();
        $plan->print_image_url   = null;
        $plan->digital_image_url = null;

        $this->assertNull($delivery->getCoverImageUrl($plan));
        $this->assertNull($delivery->getCoverImageUrl());
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeDelivery(IssueDeliveryStatus $status): IssueDelivery
    {
        $delivery         = new IssueDelivery();
        $delivery->status = $status->value;
        return $delivery;
    }
}