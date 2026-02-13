<?php

namespace App\Tests\Unit\Services\Billing\Preorder;

use App\Models\Product;
use App\Repositories\Billing\OrderItemRepository;
use App\Services\Billing\Preorder\PhysicalProductAvailabilityPolicy;
use Mockery;
use PHPUnit\Framework\TestCase;

class PhysicalProductAvailabilityPolicyTest extends TestCase
{
    private OrderItemRepository $orderItemRepository;

    public function setUp(): void
    {
        $this->orderItemRepository = Mockery::mock(OrderItemRepository::class);
        parent::setUp();

    }

    public function test_can_purchase_when_in_stock(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->stock_quantity = 10;
        $product->id = 1;
        $product->preorder_enabled = false;
        $product->preorder_restock_date = null;

        $this->setPendingPreorderQuantity();

        $policy = new PhysicalProductAvailabilityPolicy($product, $this->orderItemRepository);

        $this->assertTrue($policy->canPurchase());
        $this->assertFalse($policy->isPreOrder());
        $this->assertEquals('In Stock', $policy->getAvailabilityMessage());
        $this->assertNull($policy->getExpectedShipDate());
    }

    private function setPendingPreorderQuantity()
    {
        return $this->orderItemRepository->shouldReceive('getPendingPreorderQuantity')->atLeast()->once()->andReturn(0);
    }

    public function test_can_purchase_when_preorder_enabled_with_date(): void
    {
        $restockDate = new \DateTime('+7 days');

        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->stock_quantity = 0;
        $product->preorder_enabled = true;
        $product->preorder_restock_date = $restockDate;

        $this->setPendingPreorderQuantity();

        $policy = new PhysicalProductAvailabilityPolicy($product, $this->orderItemRepository);

        $this->assertTrue($policy->canPurchase());
        $this->assertTrue($policy->isPreOrder());
        $this->assertStringContainsString('Available for Pre-order', $policy->getAvailabilityMessage());
        $this->assertEquals(
            $restockDate->format('Y-m-d H:i:s'),
            $policy->getExpectedShipDate()->format('Y-m-d H:i:s')
        );
    }

    public function test_cannot_purchase_when_out_of_stock_no_preorder(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->stock_quantity = 0;
        $product->preorder_enabled = false;
        $product->preorder_restock_date = null;

        $policy = new PhysicalProductAvailabilityPolicy($product, $this->orderItemRepository);

        $this->setPendingPreorderQuantity();

        $this->assertFalse($policy->canPurchase());
        $this->assertFalse($policy->isPreOrder());
        $this->assertEquals('Out of Stock', $policy->getAvailabilityMessage());
    }

    public function test_cannot_purchase_when_preorder_enabled_but_no_date(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->stock_quantity = 0;
        $product->preorder_enabled = true;
        $product->preorder_restock_date = null;

        $this->setPendingPreorderQuantity();

        $policy = new PhysicalProductAvailabilityPolicy($product, $this->orderItemRepository);

        $this->assertFalse($policy->canPurchase());
        $this->assertFalse($policy->isPreOrder());
    }

    public function test_is_pre_release_always_false_for_physical_products(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->stock_quantity = 0;
        $product->preorder_enabled = true;
        $product->preorder_restock_date = new \DateTime('+7 days');

        $policy = new PhysicalProductAvailabilityPolicy($product);

        $this->assertFalse($policy->isPreRelease());
    }

    public function test_availability_message_includes_formatted_date(): void
    {
        $restockDate = new \DateTime('2026-03-15');

        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->stock_quantity = 0;
        $product->preorder_enabled = true;
        $product->preorder_restock_date = $restockDate;

        $this->setPendingPreorderQuantity();

        $policy = new PhysicalProductAvailabilityPolicy($product, $this->orderItemRepository);

        $message = $policy->getAvailabilityMessage();
        $this->assertStringContainsString('Mar 15, 2026', $message);
    }

    public function test_expected_ship_date_null_when_in_stock(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->stock_quantity = 5;
        $product->preorder_enabled = false;
        $product->preorder_restock_date = null;

        $policy = new PhysicalProductAvailabilityPolicy($product, $this->orderItemRepository);

        $this->setPendingPreorderQuantity();

        $this->assertNull($policy->getExpectedShipDate());
    }

    public function test_transitions_from_preorder_to_in_stock(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;

        // Initially preorder
        $product->stock_quantity = 0;
        $product->preorder_enabled = true;
        $product->preorder_restock_date = new \DateTime('+7 days');

        $this->setPendingPreorderQuantity();

        $policy = new PhysicalProductAvailabilityPolicy($product, $this->orderItemRepository);
        $this->assertTrue($policy->isPreOrder());

        // Stock arrives
        $product->stock_quantity = 10;

        $policyAfterRestock = new PhysicalProductAvailabilityPolicy($product, $this->orderItemRepository);
        $this->assertFalse($policyAfterRestock->isPreOrder());
        $this->assertEquals('In Stock', $policyAfterRestock->getAvailabilityMessage());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

}