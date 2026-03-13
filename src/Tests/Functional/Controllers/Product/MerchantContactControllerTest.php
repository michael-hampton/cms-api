<?php

namespace App\Tests\Functional\Controllers\Product;

use App\Models\MerchantContact;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MerchantContactControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    // =========================================================================
    // GET /api/merchant-contacts
    // =========================================================================

    public function testIndexReturnsAllContacts(): void
    {
        $this->createMerchantContact(['name' => 'Contact 1']);
        $this->createMerchantContact(['name' => 'Contact 2']);

        $response = $this->getForSite('/api/merchant-contacts');

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertGreaterThanOrEqual(2, count($data['data']));
    }

    // =========================================================================
    // POST /api/merchant-contacts — CreateMerchantContactRequest validation
    // =========================================================================

    public function testStoreCreatesContact(): void
    {
        $merchant = $this->createMerchant();

        $response = $this->postForSite('/api/merchant-contacts', [
            'merchant_id' => $merchant->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+1234567890',
            'role' => 'Sales Manager',
        ]);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertArrayHasKey('contact', $responseData['data']);
        $this->assertEquals('John Doe', $responseData['data']['contact']['name']);
        $this->assertEquals('john@example.com', $responseData['data']['contact']['email']);
    }

    public function testStoreValidatesRequiredName(): void
    {
        $merchant = $this->createMerchant();

        $response = $this->postForSite('/api/merchant-contacts', [
            'merchant_id' => $merchant->id,
            'email' => 'john@example.com',
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('name', $data['errors']);
    }

    public function testStoreValidatesRequiredEmail(): void
    {
        $merchant = $this->createMerchant();

        $response = $this->postForSite('/api/merchant-contacts', [
            'merchant_id' => $merchant->id,
            'name' => 'John Doe',
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('email', $data['errors']);
    }

    public function testStoreValidatesEmailFormat(): void
    {
        $merchant = $this->createMerchant();

        $response = $this->postForSite('/api/merchant-contacts', [
            'merchant_id' => $merchant->id,
            'name' => 'John Doe',
            'email' => 'not-a-valid-email',
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('email', $data['errors']);
    }

    public function testStoreValidatesRequiredMerchantId(): void
    {
        $response = $this->postForSite('/api/merchant-contacts', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('merchant_id', $data['errors']);
    }

    public function testStoreValidatesMerchantIdIsInteger(): void
    {
        $response = $this->postForSite('/api/merchant-contacts', [
            'merchant_id' => 'not-an-integer',
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('merchant_id', $data['errors']);
    }

    public function testStoreFailsWhenAllRequiredFieldsMissing(): void
    {
        $response = $this->postForSite('/api/merchant-contacts', []);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('name', $data['errors']);
        $this->assertArrayHasKey('email', $data['errors']);
        $this->assertArrayHasKey('merchant_id', $data['errors']);
    }

    // =========================================================================
    // GET /api/merchant-contacts/{id}
    // =========================================================================

    public function testShowContactReturnsContact(): void
    {
        $contact = $this->createMerchantContact();

        $response = $this->getForSite("/api/merchant-contacts/{$contact->id}");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('contact', $data);
        $this->assertEquals($contact->id, $data['contact']['id']);
    }

    public function testShowContactReturns404WhenNotFound(): void
    {
        $response = $this->getForSite('/api/merchant-contacts/9999');

        $this->assertEquals(404, $response->getStatusCode());
    }

    // =========================================================================
    // PUT /api/merchant-contacts/{id} — UpdateMerchantContactRequest validation
    // =========================================================================

    public function testUpdateModifiesContact(): void
    {
        $contact = $this->createMerchantContact(['name' => 'Old Name']);

        $response = $this->putForSite("/api/merchant-contacts/{$contact->id}", [
            'name' => 'New Name',
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('New Name', $data['contact']['name']);

        $updated = MerchantContact::find($contact->id);
        $this->assertEquals('New Name', $updated->name);
    }

    public function testUpdateValidatesRequiredName(): void
    {
        $contact = $this->createMerchantContact();

        // UpdateMerchantContactRequest requires name
        $response = $this->putForSite("/api/merchant-contacts/{$contact->id}", [
            'email' => 'newemail@example.com',
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('name', $data['errors']);
    }

    public function testUpdateContactReturns404WhenNotFound(): void
    {
        $response = $this->putForSite('/api/merchant-contacts/9999', [
            'name' => 'Test',
        ]);

        $this->assertEquals(404, $response->getStatusCode());
    }

    // =========================================================================
    // DELETE /api/merchant-contacts/{id}
    // =========================================================================

    public function testDeleteRemovesContact(): void
    {
        $contact = $this->createMerchantContact();

        $response = $this->deleteForSite("/api/merchant-contacts/{$contact->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseMissing('merchant_contacts', ['id' => $contact->id]);
    }

    public function testDeleteContactReturns404WhenNotFound(): void
    {
        $response = $this->deleteForSite('/api/merchant-contacts/9999');

        $this->assertEquals(404, $response->getStatusCode());
    }

    // =========================================================================
    // GET /api/merchants/{id}/contacts
    // =========================================================================

    public function testGetByMerchantReturnsContactsForSpecificMerchant(): void
    {
        $merchant1 = $this->createMerchant();
        $merchant2 = $this->createMerchant();

        $this->createMerchantContact(['merchant_id' => $merchant1->id, 'name' => 'Contact 1']);
        $this->createMerchantContact(['merchant_id' => $merchant1->id, 'name' => 'Contact 2']);
        $this->createMerchantContact(['merchant_id' => $merchant2->id, 'name' => 'Contact 3']);

        $response = $this->getForSite("/api/merchants/{$merchant1->id}/contacts");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(2, $data['data']);
    }
}