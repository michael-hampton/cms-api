<?php

namespace App\Tests\Unit\Models;

use App\Enums\Subscriptions\IssueDeliveryStatus;
use App\Models\IssueDelivery;
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

    private function makeDelivery(IssueDeliveryStatus $status): IssueDelivery
    {
        $delivery = new IssueDelivery();
        $delivery->status = $status->value;
        return $delivery;
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
        $delivery = new IssueDelivery();
        $delivery->on_sale_date = null;

        $this->assertSame('Scheduled', $delivery->calculateStatus());
    }

    public function test_calculate_delivery_progress_returns_scheduled_when_sale_date_in_future(): void
    {
        $delivery = new IssueDelivery();
        $delivery->on_sale_date = new \DateTime('+10 days');
        $delivery->estimated_delivery_date = new \DateTime('+15 days');

        $this->assertSame('Scheduled', $delivery->calculateStatus());
    }

    public function test_calculate_delivery_progress_returns_in_transit_between_sale_and_delivery(): void
    {
        $delivery = new IssueDelivery();
        $delivery->on_sale_date = new \DateTime('-2 days');
        $delivery->estimated_delivery_date = new \DateTime('+3 days');

        $this->assertSame('In Transit', $delivery->calculateStatus());
    }

    public function test_calculate_delivery_progress_returns_delivered_after_delivery_date(): void
    {
        $delivery = new IssueDelivery();
        $delivery->on_sale_date = new \DateTime('-10 days');
        $delivery->estimated_delivery_date = new \DateTime('-2 days');

        $this->assertSame('Delivered', $delivery->calculateStatus());
    }

    // =========================================================================
    // Delivery progress is independent of dispatch status
    // =========================================================================

    public function test_delivery_progress_uses_tracking_info_when_present(): void
    {
        $delivery = new IssueDelivery();
        $delivery->on_sale_date = new \DateTime('-5 days');
        $delivery->tracking_info = ['status' => 'Out for Delivery'];

        $this->assertSame('Out for Delivery', $delivery->calculateStatus());
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function test_failed_dispatch_does_not_change_delivery_progress(): void
    {
        $delivery = new IssueDelivery();
        $delivery->status = IssueDeliveryStatus::FAILED->value;
        $delivery->on_sale_date = new \DateTime('-2 days');
        $delivery->estimated_delivery_date = new \DateTime('+3 days');

        // Even though send failed, the date-based progress is still In Transit
        $this->assertSame('In Transit', $delivery->calculateStatus());
    }
}