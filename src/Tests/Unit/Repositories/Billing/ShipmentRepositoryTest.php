<?php

namespace App\Tests\Unit\Repositories\Billing;

use App\Models\Shipment;
use App\Repositories\Billing\ShipmentRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class ShipmentRepositoryTest extends FunctionalTestCase
{
    use CreatesTestData;

    private ShipmentRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ShipmentRepository();
    }

    protected function createShipment(array $overrides = []): Shipment
    {
        $merchant = $this->createMerchant();
        $order = $this->createOrder();

        return Shipment::create(array_merge([
            'order_id' => $order->id,
            'checkout_id' => 'chk-test-' . uniqid(),
            'merchant_id' => $merchant->id,
            'shipping_cost' => 10.00,
            'country' => 'US',
            'status' => 'pending',
            'site_id' => $this->siteId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    public function test_find_by_order_id_returns_shipment(): void
    {
        $order = $this->createOrder();

        $shipment = $this->createShipment(['order_id' => $order->id]);

        $found = $this->repository->findByOrderId($order->id);

        $this->assertNotNull($found);
        $this->assertEquals($order->id, $found->order_id);
        $this->assertEquals(10.00, $found->shipping_cost);
    }

    public function test_find_by_order_id_returns_null_when_not_found(): void
    {
        $found = $this->repository->findByOrderId(88888);
        $this->assertNull($found);
    }

    public function test_get_by_checkout_id_returns_all_shipments_for_checkout(): void
    {
        $checkoutId = 'chk-multi-' . uniqid();
        $order1 = $this->createOrder();
        $order2 = $this->createOrder();
        $order3 = $this->createOrder();
        $order4 = $this->createOrder();
        $merchant1 = $this->createMerchant();
        $merchant2 = $this->createMerchant();
        $merchant3 = $this->createMerchant();

        $this->createShipment(['checkout_id' => $checkoutId, 'order_id' => $order1->id, 'merchant_id' => $merchant1->id]);
        $this->createShipment(['checkout_id' => $checkoutId, 'order_id' => $order2->id, 'merchant_id' => $merchant2->id]);
        $this->createShipment(['checkout_id' => $checkoutId, 'order_id' => $order3->id, 'merchant_id' => null]); // system

        // Different checkout — should NOT appear
        $this->createShipment(['checkout_id' => 'chk-other', 'order_id' => $order4->id, 'merchant_id' => $merchant3->id]);

        $shipments = $this->repository->getByCheckoutId($checkoutId);

        $this->assertCount(3, $shipments);
    }

    public function test_get_by_checkout_id_returns_empty_for_unknown_checkout(): void
    {
        $shipments = $this->repository->getByCheckoutId('chk-nonexistent');
        $this->assertCount(0, $shipments);
    }

    public function test_get_by_merchant_id_returns_merchant_shipments(): void
    {
        $order1 = $this->createOrder();
        $order2 = $this->createOrder();
        $order3 = $this->createOrder();
        $merchantA = $this->createMerchant();
        $merchantB = $this->createMerchant();

        $this->createShipment(['merchant_id' => $merchantA->id, 'order_id' => $order1->id]);
        $this->createShipment(['merchant_id' => $merchantA->id, 'order_id' => $order2->id]);
        $this->createShipment(['merchant_id' => $merchantB->id, 'order_id' => $order3->id]);

        $shipments = $this->repository->getByMerchantId($merchantA->id);

        $this->assertCount(2, $shipments);
        foreach ($shipments as $s) {
            $this->assertEquals($merchantA->id, $s->merchant_id);
        }
    }

    public function test_get_by_merchant_id_returns_empty_for_unknown_merchant(): void
    {
        $shipments = $this->repository->getByMerchantId(99999);
        $this->assertCount(0, $shipments);
    }

    public function test_create_persists_shipment(): void
    {
        $order = $this->createOrder();
        $merchant = $this->createMerchant();

        $shipment = $this->repository->create([
            'order_id' => $order->id,
            'checkout_id' => 'chk-create-test',
            'merchant_id' => $merchant->id,
            'shipping_cost' => 15.50,
            'country' => 'GB',
            'status' => 'pending',
            'site_id' => $this->siteId,
        ]);

        $this->assertNotNull($shipment->id);
        $this->assertEquals($order->id, $shipment->order_id);
        $this->assertEquals(15.50, $shipment->shipping_cost);
        $this->assertEquals('GB', $shipment->country);
    }
}