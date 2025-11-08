<?php

namespace App\Tests\Unit\Models;

use App\Models\Address;
use App\Models\Member;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class AddressModelTest extends RepositoryTestCase
{
    use CreatesTestData;
    private Member $testMember;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testMember = $this->createMember();
    }

    public function test_address_can_be_created(): void
    {
        // Act
        $address = Address::create([
            'member_id' => $this->testMember->id,
            'type' => 'shipping',
            'address_line_1' => '123 Main St',
            'city' => 'City',
            'postcode' => '12345',
            'country' => 'US',
        ]);

        // Assert
        $this->assertNotNull($address->id);
        $this->assertEquals('123 Main St', $address->address_line_1);
    }

    public function test_get_formatted_attribute_returns_formatted_address(): void
    {
        // Arrange
        $address = Address::create([
            'member_id' => $this->testMember->id,
            'type' => 'shipping',
            'address_line_1' => '123 Main St',
            'address_line_2' => 'Apt 4',
            'city' => 'Springfield',
            'state' => 'IL',
            'postcode' => '62701',
            'country' => 'US',
        ]);

        // Act
        $formatted = $address->getFormattedAttribute();

        // Assert
        $this->assertStringContainsString('123 Main St', $formatted);
        $this->assertStringContainsString('Springfield', $formatted);
        $this->assertStringContainsString('62701', $formatted);
    }

    public function test_to_array_includes_formatted_address(): void
    {
        // Arrange
        $address = Address::create([
            'member_id' => $this->testMember->id,
            'type' => 'shipping',
            'address_line_1' => '123 Main St',
            'city' => 'City',
            'postcode' => '12345',
            'country' => 'US',
        ]);

        // Act
        $array = $address->toArray();

        // Assert
        $this->assertArrayHasKey('formatted', $array);
        $this->assertIsString($array['formatted']);
    }

    public function test_scope_for_member_filters_by_member(): void
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

        Address::create([
            'member_id' => $this->testMember->id,
            'type' => 'shipping',
            'address_line_1' => '123 Main St',
            'city' => 'City',
            'postcode' => '12345',
            'country' => 'US',
        ]);

        Address::create([
            'member_id' => $otherMember->id,
            'type' => 'shipping',
            'address_line_1' => '456 Oak Ave',
            'city' => 'Town',
            'postcode' => '67890',
            'country' => 'US',
        ]);

        // Act
        $addresses = Address::forMember($this->testMember->id)->get();

        // Assert
        $this->assertCount(1, $addresses);
        $this->assertEquals($this->testMember->id, $addresses->first()->member_id);
    }

    public function test_scope_default_filters_by_default_flag(): void
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

        Address::create([
            'member_id' => $this->testMember->id,
            'type' => 'shipping',
            'address_line_1' => '456 Oak Ave',
            'city' => 'Town',
            'postcode' => '67890',
            'country' => 'US',
            'is_default' => false,
        ]);

        // Act
        $addresses = Address::default()->get();

        // Assert
        $this->assertCount(1, $addresses);
        $this->assertTrue($addresses->first()->is_default);
    }

    public function test_scope_shipping_filters_shipping_addresses(): void
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
        $addresses = Address::shipping()->get();

        // Assert
        $this->assertCount(2, $addresses); // shipping and both
    }

    public function test_scope_billing_filters_billing_addresses(): void
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
        $addresses = Address::billing()->get();

        // Assert
        $this->assertCount(2, $addresses); // billing and both
    }
}