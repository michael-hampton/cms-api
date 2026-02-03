<?php

namespace App\Tests\Unit\Services\Billing\Orders;

use App\Models\Address;
use App\Models\Member;
use App\Repositories\Members\AddressRepository;
use App\Services\Billing\Order\OrderAddressResolver;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class OrderAddressResolverTest extends TestCase
{
    private $addressRepository;
    private OrderAddressResolver $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->addressRepository = m::mock(AddressRepository::class);
        $this->service = new OrderAddressResolver($this->addressRepository);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    // ===================================================================
    // resolveAddresses() with member addresses Tests
    // ===================================================================

    public function testResolveAddressesWithMemberAndShippingAddressData(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->id = 1;

        $siteId = 1;

        $data = [
            'shipping_address' => [
                'address_line_1' => '123 Main St',
                'city' => 'New York',
                'postcode' => '10001',
                'country' => 'US'
            ]
        ];

        $mockAddress = m::mock(Address::class)->makePartial();
        $mockAddress->id = 10;

        $this->addressRepository->shouldReceive('createAddressForMember')
            ->once()
            ->with(1, m::on(function ($addressData) {
                return $addressData['address_line_1'] === '123 Main St'
                    && $addressData['type'] === 'shipping'
                    && $addressData['label'] === 'Order Address';
            }), $siteId)
            ->andReturn($mockAddress);

        $this->service->resolveAddresses($data, $member, $siteId);

        $this->assertEquals(10, $data['shipping_address_id']);
        $this->assertArrayNotHasKey('shipping_address', $data);
    }

    public function testResolveAddressesWithMemberAndBillingAddressData(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->id = 1;

        $siteId = 1;

        $data = [
            'billing_address' => [
                'address_line_1' => '456 Oak Ave',
                'city' => 'Boston',
                'postcode' => '02101',
                'country' => 'US'
            ]
        ];

        $mockAddress = m::mock(Address::class)->makePartial();
        $mockAddress->id = 20;

        $this->addressRepository->shouldReceive('createAddressForMember')
            ->once()
            ->with(1, m::on(function ($addressData) {
                return $addressData['address_line_1'] === '456 Oak Ave'
                    && $addressData['type'] === 'billing'
                    && $addressData['label'] === 'Order Billing Address';
            }), $siteId)
            ->andReturn($mockAddress);

        $this->service->resolveAddresses($data, $member, $siteId);

        $this->assertEquals(20, $data['billing_address_id']);
        $this->assertArrayNotHasKey('billing_address', $data);
    }

    public function testResolveAddressesWithMemberAndBothAddresses(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->id = 1;

        $siteId = 1;

        $data = [
            'shipping_address' => [
                'address_line_1' => '123 Main St',
                'city' => 'New York',
                'postcode' => '10001',
                'country' => 'US'
            ],
            'billing_address' => [
                'address_line_1' => '456 Oak Ave',
                'city' => 'Boston',
                'postcode' => '02101',
                'country' => 'US'
            ]
        ];

        $mockShippingAddress = m::mock(Address::class)->makePartial();
        $mockShippingAddress->id = 10;

        $mockBillingAddress = m::mock(Address::class)->makePartial();
        $mockBillingAddress->id = 20;

        $this->addressRepository->shouldReceive('createAddressForMember')
            ->once()
            ->with(1, m::on(fn($a) => $a['type'] === 'shipping'), $siteId)
            ->andReturn($mockShippingAddress);

        $this->addressRepository->shouldReceive('createAddressForMember')
            ->once()
            ->with(1, m::on(fn($a) => $a['type'] === 'billing'), $siteId)
            ->andReturn($mockBillingAddress);

        $this->service->resolveAddresses($data, $member, $siteId);

        $this->assertEquals(10, $data['shipping_address_id']);
        $this->assertEquals(20, $data['billing_address_id']);
        $this->assertArrayNotHasKey('shipping_address', $data);
        $this->assertArrayNotHasKey('billing_address', $data);
    }

    // ===================================================================
    // resolveAddresses() with guest addresses Tests
    // ===================================================================

    public function testResolveAddressesWithGuestConvertsToJson(): void
    {
        $member = null; // Guest
        $siteId = 1;

        $data = [
            'shipping_address' => [
                'address_line_1' => '123 Main St',
                'city' => 'New York',
                'postcode' => '10001',
                'country' => 'US'
            ]
        ];

        $mockBillingAddress = m::mock(Address::class)->makePartial();
        $mockBillingAddress->id = 10;

        // Should NOT create address for guest
        $this->addressRepository->shouldReceive('createGuestAddress')
            ->once()
            ->with(m::on(fn($a) => $a['type'] === 'shipping' && $a['is_guest'] === true), $siteId)
            ->andReturn($mockBillingAddress);

        $result = $this->service->resolveAddresses($data, $member, $siteId);

        $this->assertEquals(10, $result['shipping_address_id']);
    }

    public function testResolveAddressesWithGuestBillingAddress(): void
    {
        $member = null;
        $siteId = 1;

        $data = [
            'billing_address' => [
                'address_line_1' => '456 Oak Ave',
                'city' => 'Boston',
                'postcode' => '02101',
                'country' => 'US'
            ]
        ];

        $mockAddress = m::mock(Address::class)->makePartial();
        $mockAddress->id = 10;

        $this->addressRepository->shouldReceive('createGuestAddress')
            ->once()
            ->with(m::on(fn($a) => $a['type'] === 'billing'), $siteId)
            ->andReturn($mockAddress);

        $this->addressRepository->shouldNotReceive('createAddressForMember');

        $result = $this->service->resolveAddresses($data, $member, $siteId);

        $this->assertEquals(10, $result['billing_address_id']);

    }

    public function testResolveAddressesWithGuestBothAddresses(): void
    {
        $member = null;
        $siteId = 1;

        $data = [
            'shipping_address' => ['address_line_1' => '123 Main St', 'city' => 'NYC'],
            'billing_address' => ['address_line_1' => '456 Oak Ave', 'city' => 'Boston']
        ];

        $mockBillingAddress = m::mock(Address::class)->makePartial();
        $mockBillingAddress->id = 10;

        $mockShippingAddress = m::mock(Address::class)->makePartial();
        $mockShippingAddress->id = 11;

        // Should NOT create address for guest
        $this->addressRepository->shouldReceive('createGuestAddress')
            ->once()
            ->with(m::on(fn($a) => $a['type'] === 'shipping'), $siteId)
            ->andReturn($mockShippingAddress);

        $this->addressRepository->shouldReceive('createGuestAddress')
            ->once()
            ->with(m::on(fn($a) => $a['type'] === 'billing'), $siteId)
            ->andReturn($mockBillingAddress);

        $this->addressRepository->shouldNotReceive('createAddressForMember');

        $result = $this->service->resolveAddresses($data, $member, $siteId);

        $this->assertEquals(11, $result['shipping_address_id']);
        $this->assertEquals(10, $result['billing_address_id']);
    }

    // ===================================================================
    // resolveAddresses() with address IDs Tests
    // ===================================================================

    public function testResolveAddressesWithShippingAddressId(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->id = 1;

        $siteId = 1;

        $data = [
            'shipping_address_id' => 10
        ];

        $mockAddress = m::mock(Address::class)->makePartial();
        $mockAddress->id = 10;
        $mockAddress->member_id = 1;

        $this->addressRepository->shouldReceive('find')
            ->once()
            ->with(10)
            ->andReturn($mockAddress);

        $this->service->resolveAddresses($data, $member, $siteId);

        $this->assertEquals(10, $data['shipping_address_id']);
    }

    public function testResolveAddressesWithBillingAddressId(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->id = 1;

        $siteId = 1;

        $data = [
            'billing_address_id' => 20
        ];

        $mockAddress = m::mock(Address::class)->makePartial();
        $mockAddress->id = 20;
        $mockAddress->member_id = 1;

        $this->addressRepository->shouldReceive('find')
            ->once()
            ->with(20)
            ->andReturn($mockAddress);

        $this->service->resolveAddresses($data, $member, $siteId);

        $this->assertEquals(20, $data['billing_address_id']);
    }

    public function testResolveAddressesWithBothAddressIds(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->id = 1;

        $siteId = 1;

        $data = [
            'shipping_address_id' => 10,
            'billing_address_id' => 20
        ];

        $mockShippingAddress = m::mock(Address::class)->makePartial();
        $mockShippingAddress->id = 10;
        $mockShippingAddress->member_id = 1;

        $mockBillingAddress = m::mock(Address::class)->makePartial();
        $mockBillingAddress->id = 20;
        $mockBillingAddress->member_id = 1;

        $this->addressRepository->shouldReceive('find')
            ->with(10)
            ->once()
            ->andReturn($mockShippingAddress);

        $this->addressRepository->shouldReceive('find')
            ->with(20)
            ->once()
            ->andReturn($mockBillingAddress);

        $this->service->resolveAddresses($data, $member, $siteId);

        $this->assertEquals(10, $data['shipping_address_id']);
        $this->assertEquals(20, $data['billing_address_id']);
    }

    // ===================================================================
    // validateAddressBelongsToMember() Tests
    // ===================================================================

    public function testValidateAddressBelongsToMemberThrowsForInvalidShippingAddress(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->id = 1;

        $siteId = 1;

        $data = [
            'shipping_address_id' => 10
        ];

        $mockAddress = m::mock(Address::class)->makePartial();
        $mockAddress->id = 10;
        $mockAddress->member_id = 2; // Different member!

        $this->addressRepository->shouldReceive('find')
            ->once()
            ->with(10)
            ->andReturn($mockAddress);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid shipping address');

        $this->service->resolveAddresses($data, $member, $siteId);
    }

    public function testValidateAddressBelongsToMemberThrowsForInvalidBillingAddress(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->id = 1;

        $siteId = 1;

        $data = [
            'billing_address_id' => 20
        ];

        $mockAddress = m::mock(Address::class)->makePartial();
        $mockAddress->id = 20;
        $mockAddress->member_id = 99; // Wrong member!

        $this->addressRepository->shouldReceive('find')
            ->once()
            ->with(20)
            ->andReturn($mockAddress);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid billing address');

        $this->service->resolveAddresses($data, $member, $siteId);
    }

    public function testValidateAddressBelongsToMemberThrowsWhenAddressNotFound(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->id = 1;

        $siteId = 1;

        $data = [
            'shipping_address_id' => 999
        ];

        $this->addressRepository->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid shipping address');

        $this->service->resolveAddresses($data, $member, $siteId);
    }

    public function testValidateAddressBelongsToMemberDoesNotThrowForGuest(): void
    {
        $member = null; // Guest
        $siteId = 1;

        $data = [
            'shipping_address_id' => 10
        ];

        // Should not validate or throw for guest
        $this->addressRepository->shouldNotReceive('find');

        // Should not throw
        $this->service->resolveAddresses($data, $member, $siteId);

        $this->assertEquals(10, $data['shipping_address_id']);
    }

    // ===================================================================
    // Edge Cases Tests
    // ===================================================================

    public function testResolveAddressesWithEmptyShippingAddress(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->id = 1;

        $siteId = 1;

        $data = [
            'shipping_address' => []
        ];

        // Should not create address for empty data
        $this->addressRepository->shouldNotReceive('createAddressForMember');

        $this->service->resolveAddresses($data, $member, $siteId);

        // Empty array should be removed/ignored
        $this->assertArrayNotHasKey('shipping_address_id', $data);
    }

    public function testResolveAddressesWithNullValues(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->id = 1;

        $siteId = 1;

        $data = [
            'shipping_address' => ['address_line_1' => '', 'city' => null]
        ];

        // Empty/null values should be filtered out
        $this->addressRepository->shouldNotReceive('createAddressForMember');

        $this->service->resolveAddresses($data, $member, $siteId);

        $this->assertArrayNotHasKey('shipping_address_id', $data);
    }

    public function testResolveAddressesPreservesOtherDataFields(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->id = 1;

        $siteId = 1;

        $data = [
            'order_number' => 'ORD-123',
            'total' => 100.00,
            'status' => 'pending',
            'shipping_address' => [
                'address_line_1' => '123 Main St',
                'city' => 'NYC'
            ]
        ];

        $mockAddress = m::mock(Address::class)->makePartial();
        $mockAddress->id = 10;

        $this->addressRepository->shouldReceive('createAddressForMember')
            ->once()
            ->andReturn($mockAddress);

        $this->service->resolveAddresses($data, $member, $siteId);

        // Other fields should be preserved
        $this->assertEquals('ORD-123', $data['order_number']);
        $this->assertEquals(100.00, $data['total']);
        $this->assertEquals('pending', $data['status']);
        $this->assertEquals(10, $data['shipping_address_id']);
    }

    public function testResolveAddressesHandlesMixedAddressTypes(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->id = 1;

        $siteId = 1;

        // Mix of ID and data
        $data = [
            'shipping_address_id' => 10,
            'billing_address' => [
                'address_line_1' => '456 Oak Ave',
                'city' => 'Boston'
            ]
        ];

        $mockShippingAddress = m::mock(Address::class)->makePartial();
        $mockShippingAddress->id = 10;
        $mockShippingAddress->member_id = 1;

        $mockBillingAddress = m::mock(Address::class)->makePartial();
        $mockBillingAddress->id = 20;

        $this->addressRepository->shouldReceive('find')
            ->once()
            ->with(10)
            ->andReturn($mockShippingAddress);

        $this->addressRepository->shouldReceive('createAddressForMember')
            ->once()
            ->andReturn($mockBillingAddress);

        $this->service->resolveAddresses($data, $member, $siteId);

        $this->assertEquals(10, $data['shipping_address_id']);
        $this->assertEquals(20, $data['billing_address_id']);
    }
}