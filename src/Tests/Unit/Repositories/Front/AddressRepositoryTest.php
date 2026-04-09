<?php

namespace App\Tests\Unit\Repositories\Front;

use App\Models\Address;
use App\Models\Member;
use App\Repositories\Members\AddressRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class AddressRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private AddressRepository $repository;
    private Member $testMember;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new AddressRepository();

        $this->testMember = $this->createMember();
    }

    public function test_get_addresses_for_member_returns_addresses(): void
    {
        // Arrange
        $address1 = $this->createAddress([ 'member_id' => $this->testMember->id]);

        $address2 = Address::create([
            'member_id' => $this->testMember->id,
            'type' => 'billing',
            'address_line_1' => '456 Oak Ave',
            'city' => 'Town',
            'postcode' => '67890',
            'country' => 'US',
        ]);

        // Act
        $addresses = $this->repository->getAddressesForMember($this->testMember->id);

        // Assert
        $this->assertCount(2, $addresses);
        $this->assertEquals($address1->id, $addresses->first()->id); // Default first
    }

    public function test_get_shipping_addresses_for_member_filters_correctly(): void
    {
        // Arrange
        $this->createAddress([ 'member_id' => $this->testMember->id]);
        $this->createAddress([ 'member_id' => $this->testMember->id, 'type' => 'billing']);
        $this->createAddress([ 'member_id' => $this->testMember->id, 'type' => 'both']);

        // Act
        $addresses = $this->repository->getShippingAddressesForMember($this->testMember->id);

        // Assert
        $this->assertCount(2, $addresses); // shipping and both
        foreach ($addresses as $address) {
            $this->assertContains($address->type, ['shipping', 'both']);
        }
    }

    public function test_get_billing_addresses_for_member_filters_correctly(): void
    {
        // Arrange
        $this->createAddress([ 'member_id' => $this->testMember->id, 'type' => 'billing']);
        $this->createAddress([ 'member_id' => $this->testMember->id, 'type' => 'both']);

        // Act
        $addresses = $this->repository->getBillingAddressesForMember($this->testMember->id);

        // Assert
        $this->assertCount(2, $addresses); // billing and both
        foreach ($addresses as $address) {
            $this->assertContains($address->type, ['billing', 'both']);
        }
    }

    public function test_get_default_shipping_address_returns_correct_address(): void
    {
        $this->createAddress([ 'member_id' => $this->testMember->id, 'is_default' => false]);
        $defaultAddress = $this->createAddress([ 'member_id' => $this->testMember->id, 'is_default' => true]);

        // Act
        $address = $this->repository->getDefaultShippingAddress($this->testMember->id);

        // Assert
        $this->assertNotNull($address);
        $this->assertEquals($defaultAddress->id, $address->id);
    }

    public function test_get_default_billing_address_returns_correct_address(): void
    {
        // Arrange
        $defaultAddress = $this->createAddress([
            'member_id' => $this->testMember->id,
            'type' => 'billing',
            'is_default' => true,
        ]);

        // Act
        $address = $this->repository->getDefaultBillingAddress($this->testMember->id);

        // Assert
        $this->assertNotNull($address);
        $this->assertEquals($defaultAddress->id, $address->id);
    }

    public function test_set_default_address_updates_correctly(): void
    {
        // Arrange
        $address1 = $this->createAddress([
            'member_id' => $this->testMember->id,
            'type' => 'shipping',
            'is_default' => true,
        ]);

        $address2 = $this->createAddress([
            'member_id' => $this->testMember->id,
            'type' => 'shipping',
            'is_default' => false,
        ]);

        // Act
        $result = $this->repository->setDefaultAddress($address2->id, $this->testMember->id);

        // Assert
        $this->assertTrue($result);

        $address1Fresh = Address::find($address1->id);
        $address2Fresh = Address::find($address2->id);

        $this->assertFalse($address1Fresh->is_default);
        $this->assertTrue($address2Fresh->is_default);
    }

    public function test_set_default_address_fails_for_wrong_member(): void
    {
        // Arrange
        $otherMember = $this->createMember();

        $address = $this->createAddress([
            'member_id' => $otherMember->id,
        ]);

        // Act
        $result = $this->repository->setDefaultAddress($address->id, $this->testMember->id);

        // Assert
        $this->assertFalse($result);
    }

    public function test_create_address_for_member_sets_first_as_default(): void
    {
        // Act
        $address = $this->repository->createAddressForMember($this->testMember->id, [
            'type' => 'shipping',
            'address_line_1' => '123 Main St',
            'city' => 'City',
            'postcode' => '12345',
            'country' => 'US',
        ], $this->siteId);

        // Assert
        $this->assertTrue($address->is_default);
    }

    public function test_create_address_for_member_does_not_set_default_when_others_exist(): void
    {
        // Arrange
        $this->createAddress([
            'member_id' => $this->testMember->id,
            'is_default' => true,
        ]);

        // Act
        $address = $this->repository->createAddressForMember($this->testMember->id, [
            'type' => 'shipping',
            'address_line_1' => '456 Oak Ave',
            'city' => 'Town',
            'postcode' => '67890',
            'country' => 'US',
        ], $this->siteId);

        // Assert
        $this->assertFalse($address->is_default);
    }

    public function test_get_paginated_addresses_returns_correct_structure(): void
    {
        // Arrange
        $this->createAddress(['member_id' => $this->testMember->id, 'is_default' => true]);
        $this->createAddress(['member_id' => $this->testMember->id, 'is_default' => false]);
        $this->createAddress(['member_id' => $this->testMember->id, 'is_default' => false]);

        // Act
        $result = $this->repository->getPaginatedAddressesForMember($this->testMember->id, 1, 10);

        // Assert
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('per_page', $result);
        $this->assertArrayHasKey('current_page', $result);
        $this->assertArrayHasKey('last_page', $result);
    }

    public function test_get_paginated_addresses_returns_correct_total(): void
    {
        // Arrange
        $this->createAddress(['member_id' => $this->testMember->id]);
        $this->createAddress(['member_id' => $this->testMember->id]);
        $this->createAddress(['member_id' => $this->testMember->id]);

        // Act
        $result = $this->repository->getPaginatedAddressesForMember($this->testMember->id, 1, 10);

        // Assert
        $this->assertEquals(3, $result['total']);
        $this->assertCount(3, $result['data']);
    }

    public function test_get_paginated_addresses_paginates_correctly(): void
    {
        // Arrange — create 3 addresses, paginate 2 per page
        $this->createAddress(['member_id' => $this->testMember->id]);
        $this->createAddress(['member_id' => $this->testMember->id]);
        $this->createAddress(['member_id' => $this->testMember->id]);

        // Act
        $page1 = $this->repository->getPaginatedAddressesForMember($this->testMember->id, 1, 2);
        $page2 = $this->repository->getPaginatedAddressesForMember($this->testMember->id, 2, 2);

        // Assert
        $this->assertCount(2, $page1['data']);
        $this->assertCount(1, $page2['data']);
        $this->assertEquals(2, $page1['last_page']);
        $this->assertEquals(1, $page1['current_page']);
        $this->assertEquals(2, $page2['current_page']);
        $this->assertEquals(2, $page1['per_page']);
    }

    public function test_get_paginated_addresses_returns_default_first(): void
    {
        // Arrange
        $nonDefault = $this->createAddress(['member_id' => $this->testMember->id, 'is_default' => false]);
        $default = $this->createAddress(['member_id' => $this->testMember->id, 'is_default' => true]);

        // Act
        $result = $this->repository->getPaginatedAddressesForMember($this->testMember->id, 1, 10);

        // Assert — default address should be first in the result set
        $this->assertEquals($default->id, $result['data']->first()->id);
    }

    public function test_get_paginated_addresses_excludes_other_members(): void
    {
        // Arrange
        $otherMember = $this->createMember();
        $this->createAddress(['member_id' => $otherMember->id]);
        $this->createAddress(['member_id' => $this->testMember->id]);

        // Act
        $result = $this->repository->getPaginatedAddressesForMember($this->testMember->id, 1, 10);

        // Assert
        $this->assertEquals(1, $result['total']);
    }

    public function test_get_paginated_addresses_last_page_is_at_least_one_when_empty(): void
    {
        // Act — no addresses exist for the member
        $result = $this->repository->getPaginatedAddressesForMember($this->testMember->id, 1, 10);

        // Assert
        $this->assertEquals(0, $result['total']);
        $this->assertEquals(1, $result['last_page']);
    }

    public function test_create_guest_address_sets_site_id(): void
    {
        // Act
        $address = $this->repository->createGuestAddress([
            'type' => 'shipping',
            'address_line_1' => '10 Downing St',
            'city' => 'London',
            'postcode' => 'SW1A 2AA',
            'country' => 'GB',
        ], $this->siteId);

        // Assert
        $this->assertEquals($this->siteId, $address->site_id);
    }

    public function test_create_guest_address_sets_member_id_to_null(): void
    {
        // Act
        $address = $this->repository->createGuestAddress([
            'type' => 'shipping',
            'address_line_1' => '10 Downing St',
            'city' => 'London',
            'postcode' => 'SW1A 2AA',
            'country' => 'GB',
        ], $this->siteId);

        // Assert
        $this->assertNull($address->member_id);
    }

    public function test_create_guest_address_sets_is_guest_flag(): void
    {
        // Act
        $address = $this->repository->createGuestAddress([
            'type' => 'billing',
            'address_line_1' => '221B Baker St',
            'city' => 'London',
            'postcode' => 'NW1 6XE',
            'country' => 'GB',
        ], $this->siteId);

        // Assert
        $this->assertTrue((bool)$address->is_guest);
    }

    public function test_create_guest_address_persists_provided_fields(): void
    {
        // Arrange
        $data = [
            'type' => 'shipping',
            'address_line_1' => '1 Infinite Loop',
            'city' => 'Cupertino',
            'postcode' => '95014',
            'country' => 'US',
        ];

        // Act
        $address = $this->repository->createGuestAddress($data, $this->siteId);

        // Assert
        $this->assertEquals('1 Infinite Loop', $address->address_line_1);
        $this->assertEquals('Cupertino', $address->city);
        $this->assertEquals('95014', $address->postcode);
        $this->assertEquals('US', $address->country);
    }
}