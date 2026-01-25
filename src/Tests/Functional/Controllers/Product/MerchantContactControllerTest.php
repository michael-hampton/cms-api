<?php

namespace App\Tests\Functional\Controllers\Product;

use App\Models\MerchantContact;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MerchantContactControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexReturnsAllContacts()
    {
        $this->createMerchantContact(['name' => 'Contact 1']);
        $this->createMerchantContact(['name' => 'Contact 2']);

        $response = $this->getForSite('/api/merchant-contacts');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertGreaterThanOrEqual(2, count($data['items']));
    }

    public function testStoreCreatesContact()
    {
        $merchant = $this->createMerchant();

        $contactData = [
            'merchant_id' => $merchant->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+1234567890',
            'role' => 'Sales Manager',
            'slug' => 'john-doe',
        ];

        $response = $this->postForSite('/api/merchant-contacts', $contactData);
        $responseData = json_decode($response->getContent(), true);

//        echo '<pre>';
//        print_r($responseData);
//        die;

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertArrayHasKey('contact', $responseData['data']);
        $this->assertEquals('John Doe', $responseData['data']['contact']['name']);
        $this->assertEquals('john@example.com', $responseData['data']['contact']['email']);
    }

    public function testStoreContactValidatesRequiredFields()
    {
        $response = $this->postForSite('/api/merchant-contacts', []);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testStoreContactValidatesEmailFormat()
    {
        $merchant = $this->createMerchant();

        $response = $this->postForSite('/api/merchant-contacts', [
            'merchant_id' => $merchant->id,
            'name' => 'John Doe',
            'email' => 'invalid-email'
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testShowContactReturnsContact()
    {
        $contact = $this->createMerchantContact();

        $response = $this->getForSite("/api/merchant-contacts/{$contact->id}");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('contact', $data['data']);
        $this->assertEquals($contact->id, $data['data']['contact']['id']);
    }

    public function testShowContactReturns404WhenNotFound()
    {
        $response = $this->getForSite('/api/merchant-contacts/9999');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUpdateModifiesContact()
    {
        $contact = $this->createMerchantContact(['name' => 'Old Name']);

        $response = $this->putForSite("/api/merchant-contacts/{$contact->id}", [
            'name' => 'New Name',
            'email' => 'newemail@example.com'
        ]);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('New Name', $data['data']['contact']['name']);

        $updated = MerchantContact::find($contact->id);
        $this->assertEquals('New Name', $updated->name);
    }

    public function testUpdateContactReturns404WhenNotFound()
    {
        $response = $this->putForSite('/api/merchant-contacts/9999', [
            'name' => 'Test'
        ]);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDeleteRemovesContact()
    {
        $contact = $this->createMerchantContact();

        $response = $this->deleteForSite("/api/merchant-contacts/{$contact->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseMissing('merchant_contacts', ['id' => $contact->id]);
    }

    public function testDeleteContactReturns404WhenNotFound()
    {
        $response = $this->deleteForSite('/api/merchant-contacts/9999');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGetByMerchantReturnsContactsForSpecificMerchant()
    {
        $merchant1 = $this->createMerchant();
        $merchant2 = $this->createMerchant();

        $this->createMerchantContact(['merchant_id' => $merchant1->id, 'name' => 'Contact 1']);
        $this->createMerchantContact(['merchant_id' => $merchant1->id, 'name' => 'Contact 2']);
        $this->createMerchantContact(['merchant_id' => $merchant2->id, 'name' => 'Contact 3']);

        $response = $this->getForSite("/api/merchants/{$merchant1->id}/contacts");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(2, $data['items']);
    }
}