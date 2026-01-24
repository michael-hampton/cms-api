<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Merchant;
use App\Models\MerchantUrl;
use App\Models\Model;
use App\Models\Site;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MerchantControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexUsesSearchInfrastructure()
    {
        $this->createMerchant();
        $this->createMerchant();

        $response = $this->getForSite('/api/merchants');

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('pagination', $data);
    }

    public function testStoreWithLogo()
    {
        $data = [
            'name' => 'Test Merchant',
            'slug' => 'test-merchant',
            'is_active' => true,
        ];

        $files = [
            'logo' => $this->createUploadedFile('logo.png', 'image/png')
        ];

        $response = $this->postForSite('/api/merchants', $data, $files);

        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertArrayHasKey('merchant', $responseData['data']);
        $this->assertNotEmpty($responseData['data']['merchant']['logo']);
    }

    public function testCreateMerchantWithUrls()
    {
        $data = [
            'name' => 'Test Merchant',
            'slug' => 'test-merchant',
            'urls' => [
                ['url' => 'https://primary.com', 'is_primary' => true, 'label' => 'Main'],
                ['url' => 'https://secondary.com', 'is_primary' => false, 'label' => 'Alt'],
            ]
        ];

        $response = $this->postForSite('/api/merchants', $data);

        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());

        $merchantId = $responseData['data']['merchant']['id'];
        $merchant = Merchant::with(['urls'])->find($merchantId);

        $this->assertCount(2, $merchant->urls);
    }

    public function testCreateMerchantWithSites()
    {
        $site1 = $this->createSite();
        $site2 = $this->createSite();

        $data = [
            'name' => 'Test Merchant',
            'slug' => 'test-merchant',
            'site_ids' => [$site1->id, $site2->id]
        ];

        $response = $this->postForSite('/api/merchants', $data);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());

        $merchantId = $responseData['data']['merchant']['id'];
        $merchant = Merchant::with(['sites'])->find($merchantId);

        $this->assertCount(2, $merchant->sites);
    }

    protected function createSite(array $attributes = []): Model
    {
        return Site::create(array_merge([
            'name' => 'Test Site',
            'url' => 'test',
            'slug' => 'test-site',
        ], $attributes));
    }

    public function testShowMerchant()
    {
        $merchant = $this->createMerchant();

        $response = $this->getForSite("/api/merchants/{$merchant->id}");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('merchant', $data['data']);
        $this->assertEquals($merchant->id, $data['data']['merchant']['id']);
    }

    public function testShowReturns404WhenMerchantNotFound()
    {
        $response = $this->getForSite('/api/merchants/9999');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Merchant not found', $data['data']['message']);
    }

    public function testUpdateMerchant()
    {
        $merchant = $this->createMerchant(['name' => 'Old Name']);

        $response = $this->putForSite("/api/merchants/{$merchant->id}", [
            'name' => 'New Name',
        ]);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('New Name', $data['data']['merchant']['name']);
    }

    public function testUpdateMerchantReturns404WhenNotFound()
    {
        $response = $this->putForSite('/api/merchants/9999', [
            'name' => 'Test'
        ]);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDeleteMerchant()
    {
        $merchant = $this->createMerchant();

        $response = $this->deleteForSite("/api/merchants/{$merchant->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseMissing('merchants', ['id' => $merchant->id]);
    }

    public function testDeleteMerchantReturns404WhenNotFound()
    {
        $response = $this->deleteForSite('/api/merchants/9999');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testToggleMerchantStatus()
    {
        $merchant = $this->createMerchant(['is_active' => true]);

        $response = $this->postForSite("/api/merchants/{$merchant->id}/toggle-status");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());

        $updated = Merchant::find($merchant->id);
        $this->assertFalse($updated->is_active);
    }

    public function testBulkUpdateMerchantStatus()
    {
        $merchant1 = $this->createMerchant(['is_active' => true]);
        $merchant2 = $this->createMerchant(['is_active' => true]);
        $merchant3 = $this->createMerchant(['is_active' => true]);

        $response = $this->postForSite('/api/merchants/bulk-update-status', [
            'ids' => [$merchant1->id, $merchant2->id],
            'is_active' => false
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $this->assertFalse(Merchant::find($merchant1->id)->is_active);
        $this->assertFalse(Merchant::find($merchant2->id)->is_active);
        $this->assertTrue(Merchant::find($merchant3->id)->is_active);
    }

    public function testBulkDeleteMerchant()
    {
        $merchant1 = $this->createMerchant();
        $merchant2 = $this->createMerchant();

        $this->createMerchantUrl(['merchant_id' => $merchant1->id]);
        $this->createMerchantUrl(['merchant_id' => $merchant2->id]);

        $response = $this->postForSite('/api/merchants/bulk-delete', [
            'ids' => [$merchant1->id, $merchant2->id]
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $this->assertDatabaseMissing('merchants', ['id' => $merchant1->id]);
        $this->assertDatabaseMissing('merchants', ['id' => $merchant2->id]);
        $this->assertEquals(0, MerchantUrl::where('merchant_id', $merchant1->id)->count());
    }

    public function testGetActiveMerchants()
    {
        $this->createMerchant(['is_active' => true]);
        $this->createMerchant(['is_active' => false]);
        $this->createMerchant(['is_active' => true]);

        $response = $this->getForSite('/api/merchants/active');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(2, $data['items']);
    }

    public function testSearchMerchantByName()
    {
        $this->createMerchant(['name' => 'Amazon Store']);
        $this->createMerchant(['name' => 'eBay Market']);

        $response = $this->getForSite('/api/merchants?search=amazon');

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertGreaterThan(0, count($data['items']));
    }

    public function testUpdateMerchantUrls()
    {
        $merchant = $this->createMerchant();

        $this->createMerchantUrl(['merchant_id' => $merchant->id, 'url' => 'https://old.com']);

        $response = $this->putForSite("/api/merchants/{$merchant->id}", [
            'name' => 'Updated',
            'urls' => [
                ['url' => 'https://new1.com', 'is_primary' => true],
                ['url' => 'https://new2.com', 'is_primary' => false],
            ]
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $urls = MerchantUrl::where('merchant_id', $merchant->id)->get();
        $this->assertCount(2, $urls);
        $this->assertDatabaseMissing('merchant_urls', [
            'merchant_id' => $merchant->id,
            'url' => 'https://old.com'
        ]);
    }
}