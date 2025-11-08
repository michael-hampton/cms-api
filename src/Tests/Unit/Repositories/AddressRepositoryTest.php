<?php

namespace App\Tests\Unit\Repositories;

use App\Models\Address;
use App\Models\Member;
use App\Repositories\AddressRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

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
        $address1 = Address::create([
            'member_id' => $this->testMember->id,
            'type' => 'shipping',
            'address_line_1' => '123 Main St',
            'city' => 'City',
            'postcode' => '12345',
            'country' => 'US',
            'is_default' => true,
        ]);

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
        Address::create([
            'member_id' => $this->testMember->id,
            'type' => 'shipping',
            'address_line_1' => '123 Main St',
            'city' => 'City',
            'postcode' => '12345',
            'country' => 'US',
        ]);

        Address::create([
            'member_id' => $this->testMember->id,
            'type' => 'billing',
            'address_line_1' => '456 Oak Ave',
            'city' => 'Town',
            'postcode' => '67890',
            'country' => 'US',
        ]);

        Address::create([
            'member_id' => $this->testMember->id,
            'type' => 'both',
            'address_line_1' => '789 Pine Rd',
            'city' => 'Village',
            'postcode' => '11111',
            'country' => 'US',
        ]);

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
        Address::create([
            'member_id' => $this->testMember->id,
            'type' => 'billing',
            'address_line_1' => '456 Oak Ave',
            'city' => 'Town',
            'postcode' => '67890',
            'country' => 'US',
        ]);

        Address::create([
            'member_id' => $this->testMember->id,
            'type' => 'both',
            'address_line_1' => '789 Pine Rd',
            'city' => 'Village',
            'postcode' => '11111',
            'country' => 'US',
        ]);

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
        // Arrange
        Address::create([
            'member_id' => $this->testMember->id,
            'type' => 'shipping',
            'address_line_1' => '123 Main St',
            'city' => 'City',
            'postcode' => '12345',
            'country' => 'US',
            'is_default' => false,
        ]);

        $defaultAddress = Address::create([
            'member_id' => $this->testMember->id,
            'type' => 'shipping',
            'address_line_1' => '456 Oak Ave',
            'city' => 'Town',
            'postcode' => '67890',
            'country' => 'US',
            'is_default' => true,
        ]);

        // Act
        $address = $this->repository->getDefaultShippingAddress($this->testMember->id);

        // Assert
        $this->assertNotNull($address);
        $this->assertEquals($defaultAddress->id, $address->id);
    }

    public function test_get_default_billing_address_returns_correct_address(): void
    {
        // Arrange
        $defaultAddress = Address::create([
            'member_id' => $this->testMember->id,
            'type' => 'billing',
            'address_line_1' => '456 Oak Ave',
            'city' => 'Town',
            'postcode' => '67890',
            'country' => 'US',
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
        $address1 = Address::create([
            'member_id' => $this->testMember->id,
            'type' => 'shipping',
            'address_line_1' => '123 Main St',
            'city' => 'City',
            'postcode' => '12345',
            'country' => 'US',
            'is_default' => true,
        ]);

        $address2 = Address::create([
            'member_id' => $this->testMember->id,
            'type' => 'shipping',
            'address_line_1' => '456 Oak Ave',
            'city' => 'Town',
            'postcode' => '67890',
            'country' => 'US',
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
        $otherMember = Member::create([
            'site_id' => $this->siteId,
            'email' => 'other@example.com',
            'password' => password_hash('password', PASSWORD_DEFAULT),
            'first_name' => 'Other',
            'last_name' => 'User',
            'is_active' => true,
        ]);

        $address = Address::create([
            'member_id' => $otherMember->id,
            'type' => 'shipping',
            'address_line_1' => '123 Main St',
            'city' => 'City',
            'postcode' => '12345',
            'country' => 'US',
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
        ]);

        // Assert
        $this->assertTrue($address->is_default);
    }

    public function test_create_address_for_member_does_not_set_default_when_others_exist(): void
    {
        // Arrange
        Address::create([
            'member_id' => $this->testMember->id,
            'type' => 'shipping',
            'address_line_1' => '123 Main St',
            'city' => 'City',
            'postcode' => '12345',
            'country' => 'US',
            'is_default' => true,
        ]);

        // Act
        $address = $this->repository->createAddressForMember($this->testMember->id, [
            'type' => 'shipping',
            'address_line_1' => '456 Oak Ave',
            'city' => 'Town',
            'postcode' => '67890',
            'country' => 'US',
        ]);

        // Assert
        $this->assertFalse($address->is_default);
    }
}