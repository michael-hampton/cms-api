<?php

namespace App\Tests\Unit\Services\Billing\Preorder;

use App\Models\IssueDelivery;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Services\Billing\Preorder\IssueAvailabilityPolicy;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class IssueAvailabilityPolicyTest extends FunctionalTestCase
{
    private IssueDeliveryRepository $issueDeliveryRepository;

    public function setUp(): void
    {
        parent::setUp();
        $this->issueDeliveryRepository = Mockery::mock(IssueDeliveryRepository::class);

    }

    public function test_can_purchase_when_in_stock(): void
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->stock_quantity = 100;
        $issue->id = 1;
        $issue->issue_number = 5;

        $policy = new IssueAvailabilityPolicy($issue, $this->issueDeliveryRepository);

        $this->setPendingPreorderQuantity();

        $this->assertTrue($policy->canPurchase());
        $this->assertFalse($policy->isPreOrder());
        $this->assertStringContainsString('In Stock', $policy->getAvailabilityMessage());
    }

    // ========================================
    // IN STOCK TESTS
    // ========================================

    private function setPendingPreorderQuantity()
    {
        $this->issueDeliveryRepository->shouldReceive('getPendingPreorderQuantity')
            ->atLeast()->once()
            ->andReturn(0);
    }

    public function test_cannot_purchase_when_out_of_stock_and_no_preorder(): void
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->stock_quantity = 0;
        $issue->id = 1;
        $issue->preorder_enabled = false;
        $issue->issue_number = 5;

        $policy = new IssueAvailabilityPolicy($issue, $this->issueDeliveryRepository);

        $this->setPendingPreorderQuantity();

        $this->assertFalse($policy->canPurchase());
        $this->assertFalse($policy->isPreOrder());
        $this->assertStringContainsString('Out of Stock', $policy->getAvailabilityMessage());
    }

    public function test_can_purchase_when_preorder_enabled(): void
    {
        $restockDate = new \DateTime('+7 days');

        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->stock_quantity = 0;
        $issue->id = 1;
        $issue->preorder_enabled = true;
        $issue->restock_date = $restockDate;
        $issue->issue_number = 6;

        $policy = new IssueAvailabilityPolicy($issue, $this->issueDeliveryRepository);

        $this->setPendingPreorderQuantity();

        $this->assertTrue($policy->canPurchase());
        $this->assertTrue($policy->isPreOrder());
        $this->assertEquals($restockDate->getTimestamp(), $policy->getExpectedShipDate()->getTimestamp());
    }

    // ========================================
    // PRE-ORDER TESTS
    // ========================================

    public function test_cannot_purchase_when_preorder_enabled_but_no_restock_date(): void
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->stock_quantity = 0;
        $issue->preorder_enabled = true;
        $issue->restock_date = null;

        $policy = new IssueAvailabilityPolicy($issue, $this->issueDeliveryRepository);

        $this->assertFalse($policy->canPurchase());
    }

    public function test_is_preorder_checks_pending_reservations(): void
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id = 1;
        $issue->stock_quantity = 10;
        $issue->preorder_enabled = true;
        $issue->restock_date = new \DateTime('+7 days');

        $repository = Mockery::mock(IssueDeliveryRepository::class);
        $repository->shouldReceive('getPendingPreorderQuantity')
            ->with(1)
            ->andReturn(15); // 15 pending, only 10 stock = preorder state

        $policy = new IssueAvailabilityPolicy($issue, $repository);

        $this->assertTrue($policy->isPreOrder());
    }

    public function test_is_not_preorder_when_stock_covers_pending(): void
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id = 1;
        $issue->stock_quantity = 20;
        $issue->preorder_enabled = true;
        $issue->restock_date = new \DateTime('+7 days');

        $repository = Mockery::mock(IssueDeliveryRepository::class);
        $repository->shouldReceive('getPendingPreorderQuantity')
            ->with(1)
            ->andReturn(5); // 5 pending, 20 stock = still in stock

        $policy = new IssueAvailabilityPolicy($issue, $repository);

        $this->assertFalse($policy->isPreOrder());
    }

    public function test_is_pre_release_when_on_sale_date_in_future(): void
    {
        $onSaleDate = new \DateTime('+14 days');

        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->on_sale_date = $onSaleDate;
        $issue->stock_quantity = 100;
        $issue->issue_number = 7;

        $policy = new IssueAvailabilityPolicy($issue, $this->issueDeliveryRepository);

        $this->assertTrue($policy->isPreRelease());
        $this->assertEquals($onSaleDate->getTimestamp(), $policy->getExpectedShipDate()->getTimestamp());
    }

    // ========================================
    // PRE-RELEASE TESTS
    // ========================================

    public function test_is_not_pre_release_when_on_sale_date_in_past(): void
    {
        $onSaleDate = new \DateTime('-7 days');

        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->on_sale_date = $onSaleDate;
        $issue->stock_quantity = 100;

        $policy = new IssueAvailabilityPolicy($issue, $this->issueDeliveryRepository);

        $this->assertFalse($policy->isPreRelease());
    }

    public function test_is_not_pre_release_when_no_on_sale_date(): void
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->on_sale_date = null;
        $issue->stock_quantity = 100;

        $policy = new IssueAvailabilityPolicy($issue, $this->issueDeliveryRepository);

        $this->assertFalse($policy->isPreRelease());
    }

    public function test_is_not_pre_release_when_out_of_stock(): void
    {
        $onSaleDate = new \DateTime('+14 days');

        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->on_sale_date = $onSaleDate;
        $issue->stock_quantity = 0;
        $issue->preorder_enabled = false;

        $policy = new IssueAvailabilityPolicy($issue, $this->issueDeliveryRepository);

        $this->assertFalse($policy->isPreRelease());
        $this->assertFalse($policy->canPurchase());
    }

    public function test_availability_message_for_in_stock(): void
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->stock_quantity = 50;
        $issue->issue_number = 5;

        $policy = new IssueAvailabilityPolicy($issue, $this->issueDeliveryRepository);

        $this->assertEquals('Issue #5 - In Stock', $policy->getAvailabilityMessage());
    }

    // ========================================
    // AVAILABILITY MESSAGES
    // ========================================

    public function test_availability_message_for_preorder_with_restock_date(): void
    {
        $restockDate = new \DateTime('2026-03-15');

        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id = 1;
        $issue->stock_quantity = 0;
        $issue->preorder_enabled = true;
        $issue->restock_date = $restockDate;
        $issue->issue_number = 6;

        $repository = Mockery::mock(IssueDeliveryRepository::class);
        $repository->shouldReceive('getPendingPreorderQuantity')->andReturn(0);

        $policy = new IssueAvailabilityPolicy($issue, $repository);

        $message = $policy->getAvailabilityMessage();
        $this->assertStringContainsString('Issue #6', $message);
        $this->assertStringContainsString('Pre-order', $message);
        $this->assertStringContainsString('Mar 15, 2026', $message);
    }

    public function test_availability_message_for_pre_release(): void
    {
        $onSaleDate = new \DateTime('+30 days'); // always in the future

        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->on_sale_date = $onSaleDate;
        $issue->stock_quantity = 100;
        $issue->issue_number = 7;

        $policy = new IssueAvailabilityPolicy($issue);

        $message = $policy->getAvailabilityMessage();
        $this->assertStringContainsString('Issue #7', $message);
        $this->assertStringContainsString('Pre-order', $message);
        $expectedDate = $onSaleDate->format('M j, Y');

        $this->assertStringContainsString($expectedDate, $message);
    }

    public function test_availability_message_for_out_of_stock(): void
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->stock_quantity = 0;
        $issue->preorder_enabled = false;
        $issue->id = 1;
        $issue->issue_number = 8;

        $policy = new IssueAvailabilityPolicy($issue, $this->issueDeliveryRepository);

        $this->setPendingPreorderQuantity();

        $this->assertEquals('Issue #8 - Out of Stock', $policy->getAvailabilityMessage());
    }

    public function test_expected_ship_date_for_pre_release(): void
    {
        $onSaleDate = new \DateTime('+14 days');

        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->on_sale_date = $onSaleDate;
        $issue->stock_quantity = 100;

        $policy = new IssueAvailabilityPolicy($issue, $this->issueDeliveryRepository);

        $this->assertEquals($onSaleDate->getTimestamp(), $policy->getExpectedShipDate()->getTimestamp());
    }

    // ========================================
    // EXPECTED SHIP DATE
    // ========================================

    public function test_expected_ship_date_for_preorder(): void
    {
        $restockDate = new \DateTime('+7 days');

        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->stock_quantity = 0;
        $issue->preorder_enabled = true;
        $issue->restock_date = $restockDate;
        $issue->id = 1;

        $repository = Mockery::mock(IssueDeliveryRepository::class);
        $repository->shouldReceive('getPendingPreorderQuantity')->andReturn(0);

        $policy = new IssueAvailabilityPolicy($issue, $repository);

        $this->assertEquals($restockDate->getTimestamp(), $policy->getExpectedShipDate()->getTimestamp());
    }

    public function test_expected_ship_date_null_when_in_stock_and_on_sale(): void
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->on_sale_date = new \DateTime('-7 days');
        $issue->stock_quantity = 100;
        $issue->id = 1;

        $policy = new IssueAvailabilityPolicy($issue, $this->issueDeliveryRepository);
        $this->setPendingPreorderQuantity();

        $this->assertNull($policy->getExpectedShipDate());
    }

    public function test_pre_release_takes_precedence_over_preorder_for_ship_date(): void
    {
        $onSaleDate = new \DateTime('+21 days');
        $restockDate = new \DateTime('+14 days');

        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->on_sale_date = $onSaleDate;
        $issue->stock_quantity = 0;
        $issue->preorder_enabled = true;
        $issue->restock_date = $restockDate;

        $policy = new IssueAvailabilityPolicy($issue, $this->issueDeliveryRepository);

        // Pre-release date (on_sale_date) should take precedence
        $this->assertTrue($policy->isPreRelease());
        $this->assertEquals($onSaleDate->getTimestamp(), $policy->getExpectedShipDate()->getTimestamp());
    }

    // ========================================
    // EDGE CASES
    // ========================================

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}