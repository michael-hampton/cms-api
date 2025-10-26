<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Product;
use App\Models\Voucher;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class VoucherControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testIndexReturnsVouchersList()
    {
        // Create test vouchers
        Voucher::create([
            'code' => 'TEST10',
            'name' => 'Test Voucher 1',
            'type' => 'percentage',
            'value' => 10,
            'status' => 'active',
            'site_id' => $this->siteId
        ]);

        Voucher::create([
            'code' => 'TEST20',
            'name' => 'Test Voucher 2',
            'type' => 'fixed',
            'value' => 20,
            'status' => 'active',
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite('/api/vouchers');

        $this->assertResponseOk($response);
        $this->assertJsonResponse($response);

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('items', $data);
        $this->assertGreaterThanOrEqual(2, count($data['items']));
    }

    public function testStoreCreatesNewVoucher()
    {
        $voucherData = [
            'code' => 'NEWVOUCHER',
            'name' => 'New Test Voucher',
            'description' => 'A test voucher',
            'type' => 'percentage',
            'value' => 15,
            'minimum_order_value' => 50,
            'maximum_discount' => 100,
            'usage_limit' => 100,
            'status' => 'active'
        ];

        $response = $this->postForSite('/api/vouchers', $voucherData);

        $this->assertResponseStatus(201, $response);
        $this->assertJsonResponse($response);

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('voucher', $data['data']);
        $this->assertEquals('NEWVOUCHER', $data['data']['voucher']['code']);
        $this->assertEquals('New Test Voucher', $data['data']['voucher']['name']);
    }

    public function testStoreNormalizesCodeToUppercase()
    {
        $voucherData = [
            'code' => 'lowercase',
            'name' => 'Test Voucher',
            'type' => 'percentage',
            'value' => 10,
            'status' => 'active'
        ];

        $response = $this->postForSite('/api/vouchers', $voucherData);

        $this->assertResponseStatus(201, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('LOWERCASE', $data['data']['voucher']['code']);
    }

    public function testStoreValidatesRequiredFields()
    {
        $voucherData = [
            'name' => 'Test Voucher'
            // Missing required fields
        ];

        $response = $this->postForSite('/api/vouchers', $voucherData);

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
    }

    public function testStoreValidatesDuplicateCode()
    {
        // Create first voucher
        Voucher::create([
            'code' => 'DUPLICATE',
            'name' => 'First Voucher',
            'type' => 'percentage',
            'value' => 10,
            'site_id' => $this->siteId
        ]);

        // Try to create second with same code
        $voucherData = [
            'code' => 'DUPLICATE',
            'name' => 'Second Voucher',
            'type' => 'percentage',
            'value' => 15
        ];

        $response = $this->postForSite('/api/vouchers', $voucherData);

        $this->assertResponseStatus(422, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Validation failed', $data['error']);
    }

    public function testStoreValidatesPercentageValue()
    {
        $voucherData = [
            'code' => 'INVALID',
            'name' => 'Invalid Voucher',
            'type' => 'percentage',
            'value' => 150, // Invalid: > 100
        ];

        $response = $this->postForSite('/api/vouchers', $voucherData);

        $this->assertResponseStatus(422, $response);
    }

    public function testStoreValidatesDateRange()
    {
        $voucherData = [
            'code' => 'DATETEST',
            'name' => 'Date Test',
            'type' => 'percentage',
            'value' => 10,
            'starts_at' => '2025-12-31',
            'expires_at' => '2025-01-01' // Before start date
        ];

        $response = $this->postForSite('/api/vouchers', $voucherData);

        $this->assertResponseStatus(422, $response);
    }

    public function testShowReturnsVoucher()
    {
        $voucher = Voucher::create([
            'code' => 'SHOW123',
            'name' => 'Show Test',
            'type' => 'percentage',
            'value' => 10,
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite('/api/vouchers/' . $voucher->id);

        $this->assertResponseOk($response);
        $this->assertJsonResponse($response);

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('voucher', $data['data']);
        $this->assertEquals('SHOW123', $data['data']['voucher']['code']);
    }

    public function testShowReturns404ForNonExistentVoucher()
    {
        $response = $this->getForSite('/api/vouchers/99999');

        $this->assertResponseStatus(404, $response);
    }

    public function testUpdateModifiesVoucher()
    {
        $voucher = Voucher::create([
            'code' => 'UPDATE123',
            'name' => 'Original Name',
            'type' => 'percentage',
            'value' => 10,
            'site_id' => $this->siteId
        ]);

        $updateData = [
            'code' => 'UPDATE123',
            'name' => 'Updated Name',
            'type' => 'percentage',
            'value' => 15,
            'description' => 'Updated description'
        ];

        $response = $this->putForSite('/api/vouchers/' . $voucher->id, $updateData);

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Updated Name', $data['data']['voucher']['name']);
        $this->assertEquals(15, $data['data']['voucher']['value']);
    }

    public function testUpdateReturns404ForNonExistentVoucher()
    {
        $updateData = [
            'code' => 'TEST',
            'name' => 'Test',
            'type' => 'percentage',
            'value' => 10
        ];

        $response = $this->putForSite('/api/vouchers/99999', $updateData);

        $this->assertResponseStatus(404, $response);
    }

    public function testDestroyDeletesVoucher()
    {
        $voucher = Voucher::create([
            'code' => 'DELETE123',
            'name' => 'Delete Test',
            'type' => 'percentage',
            'value' => 10,
            'usage_count' => 0,
            'site_id' => $this->siteId
        ]);

        $response = $this->deleteForSite('/api/vouchers/' . $voucher->id);

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Voucher deleted successfully', $data['data']['message']);

        // Verify voucher is deleted
        $deletedVoucher = Voucher::find($voucher->id);
        $this->assertNull($deletedVoucher);
    }

    public function testDestroyPreventsDeleteWithUsage()
    {
        $voucher = $this->createVoucher(['usage_count' => 5]);

        $response = $this->deleteForSite('/api/vouchers/' . $voucher->id);

        $this->assertResponseStatus(400, $response);
    }

    public function testCheckDeleteReturnsStatus()
    {
        $voucher = $this->createVoucher();

        $response = $this->getForSite('/api/vouchers/' . $voucher->id . '/check-delete');

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('can_delete', $data['data']);
        $this->assertTrue($data['data']['can_delete']);
    }

    public function testAlternativesReturnsOtherVouchers()
    {
        $voucher1 = $this->createVoucher();
        $this->createVoucher();
        $this->createVoucher();

        $response = $this->getForSite('/api/vouchers/' . $voucher1->id . '/alternatives');

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('vouchers', $data['data']);
        $this->assertGreaterThanOrEqual(2, count($data['data']['vouchers']));
    }

    public function testDuplicateCreatesNewVoucher()
    {
        $original = $this->createVoucher();

        $response = $this->postForSite('/api/vouchers/' . $original->id . '/duplicate');

        $this->assertResponseStatus(201, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('voucher', $data['data']);
        $this->assertStringContainsString('(Copy)', $data['data']['voucher']['name']);
        $this->assertEquals('inactive', $data['data']['voucher']['status']);
        $this->assertEquals(0, $data['data']['voucher']['usage_count']);
    }

    public function testDuplicateWithCustomCode()
    {
        $original = $this->createVoucher();

        $response = $this->postForSite('/api/vouchers/' . $original->id . '/duplicate', [
            'code' => 'CUSTOM'
        ]);

        $this->assertResponseStatus(201, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('CUSTOM', $data['data']['voucher']['code']);
    }

    public function testValidateVoucherSuccess()
    {
        $this->createVoucher([ 'code' => 'VALID10', 'value' => 10, 'status' => 'active']);

        $response = $this->postForSite('/api/vouchers/validate', [
            'code' => 'VALID10',
            'order_value' => 100
        ]);

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['data']['valid']);
        $this->assertEquals(10, $data['data']['discount']);
        $this->assertArrayHasKey('voucher_id', $data['data']);
    }

    public function testValidateVoucherNotFound()
    {
        $response = $this->postForSite('/api/vouchers/validate', [
            'code' => 'NOTFOUND',
            'order_value' => 100
        ]);

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['data']['valid']);
        $this->assertEquals('Voucher not found', $data['data']['message']);
    }

    public function testValidateVoucherBelowMinimum()
    {
       $this->createVoucher(['minimum_order_value' => 50, 'code' => 'MINIMUM50', 'value' => 10, 'status' => 'active']);;

        $response = $this->postForSite('/api/vouchers/validate', [
            'code' => 'MINIMUM50',
            'order_value' => 30
        ]);

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['data']['valid']);
        $this->assertStringContainsString('Minimum order value', $data['data']['message']);
    }

    public function testApplyVoucher()
    {
        $voucher = $this->createVoucher();

        $response = $this->postForSite('/api/vouchers/' . $voucher->id . '/apply');

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Voucher applied successfully', $data['data']['message']);

        // Verify usage count increased
        $updatedVoucher = Voucher::find($voucher->id);
        $this->assertEquals(1, $updatedVoucher->usage_count);
    }

    public function testActiveReturnsOnlyActiveVouchers()
    {
       $this->createVoucher(['status' => 'active']);
       $this->createVoucher(['status' => 'inactive']);

        $response = $this->getForSite('/api/vouchers/active');

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('vouchers', $data['data']);

        foreach ($data['data']['vouchers'] as $voucher) {
            $this->assertEquals('active', $voucher['status']);
        }
    }

    public function testUnauthenticatedRequestFails()
    {
        $response = $this->getForSiteUnauthenticated('/api/vouchers');

        $this->assertResponseStatus(401, $response);
    }

    public function testCreateVoucherWithProducts()
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        $voucherData = [
            'code' => 'PRODUCTS10',
            'name' => 'Product Specific Voucher',
            'type' => 'percentage',
            'value' => 10,
            'status' => 'active',
            'product_ids' => [$product1->id, $product2->id]
        ];

        $response = $this->postForSite('/api/vouchers', $voucherData);

        $this->assertResponseStatus(201, $response);

        $data = json_decode($response->getContent(), true);
        $voucherId = $data['data']['voucher']['id'];

        $voucher = Voucher::find($voucherId);
        $this->assertCount(2, $voucher->products);
    }

    public function testValidateVoucherForProduct()
    {
        $product = $this->createProduct(['price' => 100]);

        $voucher = $this->createVoucher(['code' => 'PRODUCT10', 'status' => 'active']);

        $voucher->products(true)->attach($product->id);

        $response = $this->postForSite('/api/vouchers/validate', [
            'code' => 'PRODUCT10',
            'order_value' => 100,
            'product_id' => $product->id
        ]);

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['data']['valid']);
    }

    public function testValidateVoucherNotApplicableToProduct()
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        $voucher = $this->createVoucher(['code' => 'SPECIFIC', 'status' => 'active']);;

        $voucher->products(true)->attach($product1->id); // Only linked to product1

        $response = $this->postForSite('/api/vouchers/validate', [
            'code' => 'SPECIFIC',
            'order_value' => 100,
            'product_id' => $product2->id // Trying with product2
        ]);

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['data']['valid']);
        $this->assertStringContainsString('not applicable', $data['data']['message']);
    }

    public function testDuplicateVoucherCopiesProductLinks()
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        $original = $this->createVoucher();

        $original->products(true)->attach([$product1->id, $product2->id]);

        $response = $this->postForSite("/api/vouchers/{$original->id}/duplicate");

        $this->assertResponseStatus(201, $response);

        $data = json_decode($response->getContent(), true);
        $newVoucherId = $data['data']['voucher']['id'];

        $newVoucher = Voucher::find($newVoucherId);
        $this->assertCount(2, $newVoucher->products);
    }
}