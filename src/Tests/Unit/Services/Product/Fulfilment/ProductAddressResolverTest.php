<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Product\Fulfilment;

use App\Models\Order;
use App\Repositories\Members\AddressRepository;
use App\Services\Product\Fulfilment\ProductAddressResolver;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class ProductAddressResolverTest extends TestCase
{
    private AddressRepository&MockInterface $addressRepository;
    private ProductAddressResolver $resolver;

    public function test_it_resolves_from_shipping_address_when_present(): void
    {
        $shipping = $this->validAddress([
            'type' => 'shipping',
            'address_line_1' => '10 Shipping Street',
        ]);

        $billing = $this->validAddress([
            'type' => 'billing',
            'address_line_1' => '1 Billing Road',
        ]);

        $order = $this->mockOrder(memberId: 1);

        $this->addressRepository
            ->shouldReceive('getAddressesForMember')
            ->andReturn(collect([$billing, $shipping])); // order shouldn't matter now

        $result = $this->resolver->resolve($order);

        $this->assertSame('10 Shipping Street', $result['address_line_1']);
    }

    private function validAddress(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'address_line_1' => '10 Downing Street',
            'address_line_2' => null,
            'city' => 'Westminster',
            'postcode' => 'SW1A 2AA',
            'country' => 'GB',
            'type' => 'billing'
        ], $overrides);
    }

    /**
     * @return Order&MockInterface
     */
    private function mockOrder(int $memberId, int $id = 1): Order&MockInterface
    {
        $order = Mockery::mock(Order::class)->makePartial();
        $order->member_id = $memberId;
        $order->id = $id;

        return $order;
    }

    public function test_it_falls_back_to_billing_when_no_shipping_exists(): void
    {
        $billing = $this->validAddress([
            'type' => 'billing',
            'address_line_1' => '1 Billing Road',
        ]);

        $order = $this->mockOrder(memberId: 2);

        $this->addressRepository
            ->shouldReceive('getAddressesForMember')
            ->andReturn(collect([$billing]));

        $result = $this->resolver->resolve($order);

        $this->assertSame('1 Billing Road', $result['address_line_1']);
    }

    public function test_it_falls_back_to_billing_when_shipping_is_incomplete(): void
    {
        $shipping = [
            'type' => 'shipping',
            'address_line_1' => '5 Broken Lane', // missing required fields
        ];

        $billing = $this->validAddress([
            'type' => 'billing',
            'address_line_1' => '2 Billing Close',
        ]);

        $order = $this->mockOrder(memberId: 3);

        $this->addressRepository
            ->shouldReceive('getAddressesForMember')
            ->andReturn(collect([$shipping, $billing]));

        $result = $this->resolver->resolve($order);

        $this->assertSame('2 Billing Close', $result['address_line_1']);
    }

    public function test_it_accepts_address_objects_that_can_be_converted_to_array(): void
    {
        $address = $this->validAddress();

        $addressObject = new class($address) {
            public function __construct(private array $data)
            {
            }

            public function toArray(): array
            {
                return $this->data;
            }
        };

        $order = $this->mockOrder(memberId: 3);

        $this->addressRepository
            ->shouldReceive('getAddressesForMember')
            ->andReturn(collect([$addressObject]));

        $result = $this->resolver->resolve($order);

        $this->assertSame('10 Downing Street', $result['address_line_1']);
    }


    public function test_it_throws_when_no_valid_address_exists(): void
    {
        $order = $this->mockOrder(memberId: 4, id: 99);

        $this->addressRepository
            ->shouldReceive('getAddressesForMember')
            ->andReturn(collect([['foo' => 'bar']]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no valid delivery address found');

        $this->resolver->resolve($order);
    }

    public function test_it_throws_when_required_field_is_missing(): void
    {
        $address = $this->validAddress();
        unset($address['postcode']);

        $order = $this->mockOrder(memberId: 5, id: 42);

        $this->addressRepository
            ->shouldReceive('getAddressesForMember')
            ->andReturn(collect([$address]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot fulfil order #42: no valid delivery address found');

        $this->resolver->resolve($order);
    }

    public function test_it_builds_full_name_correctly(): void
    {
        $address = $this->validAddress([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $order = $this->mockOrder(memberId: 6);

        $this->addressRepository
            ->shouldReceive('getAddressesForMember')
            ->andReturn(collect([$address]));

        $result = $this->resolver->resolve($order);

        $this->assertSame('John Doe', $result['full_name']);
    }

    public function test_it_returns_null_for_address_line_2_when_missing(): void
    {
        $address = $this->validAddress();
        unset($address['address_line_2']);

        $order = $this->mockOrder(memberId: 7);

        $this->addressRepository
            ->shouldReceive('getAddressesForMember')
            ->andReturn(collect([$address]));

        $result = $this->resolver->resolve($order);

        $this->assertNull($result['address_line_2']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        $this->addressRepository = Mockery::mock(AddressRepository::class);
        $this->resolver = new ProductAddressResolver($this->addressRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @param array|string|null $shippingAddress
     * @param array|string|null $billingAddress
     * @return Order&MockInterface
     */
    private function orderWithAddresses(
        array|string|null $shippingAddress = null,
        array|string|null $billingAddress = null,
    ): Order&MockInterface
    {
        $order = Mockery::mock(Order::class);
        $order->shouldReceive('__get')->with('shipping_address')->andReturn($shippingAddress)->byDefault();
        $order->shouldReceive('__get')->with('billing_address')->andReturn($billingAddress)->byDefault();

        return $order;
    }
}