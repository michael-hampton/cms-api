<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Product\Fulfilment;

use App\DTO\Subscriptions\FulfilmentDecisionContext;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Territory;
use App\Services\Product\Fulfilment\PostcodeOnlyRegionResolver;
use App\Services\Product\Fulfilment\ProductAddressResolver;
use App\Services\Product\Fulfilment\ProductFulfilmentDecisionService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class ProductFulfilmentDecisionServiceTest extends TestCase
{
    private ProductAddressResolver&MockInterface $addressResolver;
    private PostcodeOnlyRegionResolver&MockInterface $regionResolver;
    private ProductFulfilmentDecisionService $service;

    public function test_it_returns_a_context_with_territory_when_postcode_maps_to_one(): void
    {
        $order = $this->makeOrder(1);
        $orderLine = $this->makeOrderLine(10);
        $territory = Mockery::mock(Territory::class)->makePartial();
        $territory->id = 7;

        $resolvedAddress = $this->resolvedAddress('SW1A 2AA');

        $this->addressResolver->shouldReceive('resolve')->once()->with($order)->andReturn($resolvedAddress);
        $this->regionResolver->shouldReceive('resolve')->once()->with('SW1A 2AA')->andReturn($territory);

        $context = $this->service->decide($order, $orderLine);

        $this->assertInstanceOf(FulfilmentDecisionContext::class, $context);
        $this->assertSame($territory, $context->territory);
        $this->assertSame(7, $context->territoryId());
        $this->assertSame($resolvedAddress['snapshot'], $context->addressSnapshot);
    }

    private function makeOrder(int $id): Order&MockInterface
    {
        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = $id;
        return $order;
    }

    private function makeOrderLine(int $id): OrderItem&MockInterface
    {
        $line = Mockery::mock(OrderItem::class)->makePartial();
        $line->id = $id;
        return $line;
    }

    private function resolvedAddress(string $postcode): array
    {
        $snapshot = [
            'first_name' => 'Test',
            'last_name' => 'User',
            'address_line_1' => '1 Test Street',
            'city' => 'London',
            'postcode' => $postcode,
            'country' => 'GB',
        ];

        return array_merge($snapshot, ['snapshot' => $snapshot]);
    }

    public function test_it_returns_a_context_with_null_territory_when_postcode_has_no_mapping(): void
    {
        $order = $this->makeOrder(2);
        $orderLine = $this->makeOrderLine(20);

        $resolvedAddress = $this->resolvedAddress('XX99 9XX');

        $this->addressResolver->shouldReceive('resolve')->once()->with($order)->andReturn($resolvedAddress);
        $this->regionResolver->shouldReceive('resolve')->once()->with('XX99 9XX')->andReturn(null);

        $context = $this->service->decide($order, $orderLine);

        $this->assertNull($context->territory);
        $this->assertNull($context->territoryId());
    }

    public function test_it_embeds_order_and_line_ids_in_channel_metadata(): void
    {
        $order = $this->makeOrder(5);
        $orderLine = $this->makeOrderLine(50);

        $this->addressResolver->shouldReceive('resolve')->andReturn($this->resolvedAddress('EC1A 1BB'));
        $this->regionResolver->shouldReceive('resolve')->andReturn(null);

        $context = $this->service->decide($order, $orderLine);

        $this->assertSame(5, $context->channelMetadata['order_id']);
        $this->assertSame(50, $context->channelMetadata['order_line_id']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function test_it_propagates_address_resolution_failure(): void
    {
        $order = $this->makeOrder(3);
        $orderLine = $this->makeOrderLine(30);

        $this->addressResolver
            ->shouldReceive('resolve')
            ->once()
            ->andThrow(new \RuntimeException('no valid delivery address found'));

        $this->regionResolver->shouldNotReceive('resolve');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no valid delivery address found');

        $this->service->decide($order, $orderLine);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->addressResolver = Mockery::mock(ProductAddressResolver::class);
        $this->regionResolver = Mockery::mock(PostcodeOnlyRegionResolver::class);

        $this->service = new ProductFulfilmentDecisionService(
            $this->addressResolver,
            $this->regionResolver,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}