<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Address;
use App\Models\Member;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class AddressControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Member $testMember;
    private Member $otherMember;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testMember = $this->createMember();
        $this->otherMember = $this->createMember();
    }

    public function testIndexReturnsAddressesList()
    {
        $this->createAddress([
            'member_id' => $this->testMember->id,
        ]);

        $response = $this->get("/api/addresses?member_id={$this->testMember->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('items', $data);
        $this->assertCount(1, $data['items']);
    }

    public function testIndexRequiresMemberId()
    {
        $response = $this->get("/api/addresses");

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Member ID is required', $data['error']);
    }

    public function testIndexReturnsEmptyArrayWhenNoAddresses()
    {
        $response = $this->get("/api/addresses?member_id={$this->testMember->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('items', $data);
        $this->assertCount(0, $data['items']);
    }

    public function testStoreCreatesNewAddress()
    {
        $addressData = [
            'member_id' => $this->testMember->id,
            'type' => 'shipping',
            'label' => 'Home',
            'address_line_1' => '123 Main St',
            'city' => 'Springfield',
            'state' => 'IL',
            'postcode' => '62701',
            'country' => 'US',
        ];

        $response = $this->post('/api/addresses', $addressData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('address', $data);
        $this->assertEquals('Home', $data['address']['label']);

        // Verify in database
        $address = Address::where('member_id', $this->testMember->id)->first();
        $this->assertNotNull($address);
        $this->assertEquals('123 Main St', $address->address_line_1);
    }

    public function testStoreFailsWithMissingRequiredFields()
    {
        $addressData = [
            'member_id' => $this->testMember->id,
            'type' => 'shipping',
            // Missing required fields
        ];

        $response = $this->post('/api/addresses', $addressData);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testStoreFailsWithInvalidType()
    {
        $addressData = [
            'member_id' => $this->testMember->id,
            'type' => 'invalid_type',
            'address_line_1' => '123 Main St',
            'city' => 'City',
            'postcode' => '12345',
            'country' => 'US',
        ];

        $response = $this->post('/api/addresses', $addressData);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testStoreFailsWithMissingMemberId()
    {
        $addressData = [
            'type' => 'shipping',
            'address_line_1' => '123 Main St',
            'city' => 'City',
            'postcode' => '12345',
            'country' => 'US',
        ];

        $response = $this->post('/api/addresses', $addressData);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testUpdateModifiesAddress()
    {
        $address = $this->createAddress(['member_id' => $this->testMember->id]);

        $updateData = [
            'address_line_1' => '456 Oak Ave',
            'city' => 'New City',
        ];

        $response = $this->put("/api/addresses/{$address->id}", $updateData);

        $this->assertEquals(200, $response->getStatusCode());

        // Verify in database
        $updatedAddress = Address::find($address->id);
        $this->assertEquals('456 Oak Ave', $updatedAddress->address_line_1);
        $this->assertEquals('New City', $updatedAddress->city);
    }

    public function testUpdateReturns404ForNonExistentAddress()
    {
        $updateData = [
            'address_line_1' => '456 Oak Ave',
        ];

        $response = $this->put("/api/addresses/99999", $updateData);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUpdateFailsWithInvalidData()
    {
        $address = $this->createAddress(['member_id' => $this->testMember->id]);

        $updateData = [
            'type' => 'invalid_type',
        ];

        $response = $this->put("/api/addresses/{$address->id}", $updateData);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testDestroyDeletesAddress()
    {
        $address = $this->createAddress(['member_id' => $this->testMember->id]);

        $response = $this->delete("/api/addresses/{$address->id}");

        $this->assertEquals(200, $response->getStatusCode());

        // Verify deleted
        $deletedAddress = Address::find($address->id);
        $this->assertNull($deletedAddress);
    }

    public function testDestroyReturns404ForNonExistentAddress()
    {
        $response = $this->delete("/api/addresses/99999");

        $this->assertEquals(404, $response->getStatusCode());
    }


    public function testSetDefaultUpdatesDefaultFlag()
    {
        $address1 = $this->createAddress(['member_id' => $this->testMember->id]);

        $address2 = $this->createAddress(['member_id' => $this->testMember->id]);

        $response = $this->post("/api/addresses/{$address2->id}/set-default", [
            'member_id' => $this->testMember->id
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        // Verify updates
        $address1Fresh = Address::find($address1->id);
        $address2Fresh = Address::find($address2->id);

        $this->assertFalse($address1Fresh->is_default);
        $this->assertTrue($address2Fresh->is_default);
    }

    public function testSetDefaultRequiresMemberId()
    {
        $address = $this->createAddress(['member_id' => $this->testMember->id]);

        $response = $this->post("/api/addresses/{$address->id}/set-default", []);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Member ID is required', $data['error']);
    }

    public function testSetDefaultFailsForWrongMember()
    {
        $address = $this->createAddress([ 'member_id' => $this->testMember->id]);

        // Try to set default using other member's ID
        $response = $this->post("/api/addresses/{$address->id}/set-default", [
            'member_id' => $this->otherMember->id
        ]);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testSetDefaultFailsForNonExistentAddress()
    {
        $response = $this->post("/api/addresses/99999/set-default", [
            'member_id' => $this->testMember->id
        ]);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testGetMemberAddressesReturnsFilteredList()
    {
        $this->createAddress(['member_id' => $this->testMember->id]);
        $this->createAddress(['member_id' => $this->testMember->id, 'type' => 'billing']);
        $this->createAddress(['member_id' => $this->testMember->id, 'type' => 'both']);

        // Test shipping addresses
        $response = $this->get("/api/members/{$this->testMember->id}/addresses?type=shipping");
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data['items']); // shipping and both

        // Test billing addresses
        $response = $this->get("/api/members/{$this->testMember->id}/addresses?type=billing");
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data['items']); // billing and both
    }

    public function testGetMemberAddressesReturnsAllWhenNoTypeFilter()
    {
        $this->createAddress(['member_id' => $this->testMember->id]);

        $this->createAddress(['member_id' => $this->testMember->id, 'type' => 'billing']);

        $response = $this->get("/api/members/{$this->testMember->id}/addresses");
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data['items']);
    }

    public function testGetMemberAddressesOnlyReturnsMemberAddresses()
    {
        $this->createAddress(['member_id' => $this->testMember->id]);
        $this->createAddress(['member_id' => $this->otherMember->id]);

        $response = $this->get("/api/members/{$this->testMember->id}/addresses");
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['items']);
        $this->assertEquals($this->testMember->id, $data['items'][0]['member_id']);
    }

    public function testStoreSetFirstAddressAsDefault()
    {
        $addressData = [
            'member_id' => $this->testMember->id,
            'type' => 'shipping',
            'address_line_1' => '123 Main St',
            'city' => 'City',
            'postcode' => '12345',
            'country' => 'US',
        ];

        $response = $this->post('/api/addresses', $addressData);

        $this->assertEquals(201, $response->getStatusCode());

        // First address should be default
        $address = Address::where('member_id', $this->testMember->id)->first();
        $this->assertTrue($address->is_default);
    }

    public function testStoreDoesNotSetDefaultWhenOtherAddressesExist()
    {
        // Create first address (will be default)
        $this->createAddress(['member_id' => $this->testMember->id, 'is_default' => true]);

        $addressData = [
            'member_id' => $this->testMember->id,
            'type' => 'shipping',
            'address_line_1' => '456 Oak Ave',
            'city' => 'Town',
            'postcode' => '67890',
            'country' => 'US',
        ];

        $response = $this->post('/api/addresses', $addressData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        // Second address should not be default
        $this->assertFalse($data['address']['is_default']);
    }

    public function testUpdateCanChangeAddressType()
    {
        $address = $this->createAddress(['member_id' => $this->testMember->id]);

        $updateData = [
            'type' => 'both',
        ];

        $response = $this->put("/api/addresses/{$address->id}", $updateData);

        $this->assertEquals(200, $response->getStatusCode());

        $updatedAddress = Address::find($address->id);
        $this->assertEquals('both', $updatedAddress->type);
    }

    public function testDestroyWithDefaultAddressDoesNotSetAnotherDefault()
    {
        $address1 = $this->createAddress(['member_id' => $this->testMember->id]);

        $address2 = $this->createAddress(['member_id' => $this->testMember->id]);

        $response = $this->delete("/api/addresses/{$address1->id}");

        $this->assertEquals(200, $response->getStatusCode());

        // Verify address2 is still not default
        $address2Fresh = Address::find($address2->id);
        $this->assertFalse($address2Fresh->is_default);
    }

}