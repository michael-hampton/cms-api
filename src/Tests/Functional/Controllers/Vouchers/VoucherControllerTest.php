<?php

namespace App\Tests\Functional\Controllers\Vouchers;

use App\Models\Voucher;
use App\Models\VoucherRedemption;
use App\Tests\Functional\Controllers\FunctionalTestCase;
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

        $response = $this->postForSite('/api/vouchers/' . $voucher->id . '/apply', ['discount_amount' => 10.00]);

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Voucher applied successfully', $data['data']['message']);

        // Verify usage count increased
        $updatedVoucher = Voucher::find($voucher->id);
        $this->assertEquals(1, $updatedVoucher->usage_count);
    }

    public function testApplyVoucherForMember()
    {
        $member = $this->createMember();

        $voucher = $this->createVoucher();

        // Add user_id and discount_amount to request
        $response = $this->postForSite('/api/vouchers/' . $voucher->id . '/apply', [
            'user_id' => $member->id,
            'discount_amount' => 10.00
        ]);

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Voucher applied successfully', $data['data']['message']);

        // Verify usage count increased
        $updatedVoucher = Voucher::find($voucher->id);
        $this->assertEquals(1, $updatedVoucher->usage_count);

        // Verify redemption was created
        $redemption = VoucherRedemption::where('voucher_id', $voucher->id)
            ->where('member_id', $member->id)
            ->first();
        $this->assertNotNull($redemption);
        $this->assertEquals(10.00, $redemption->discount_amount);
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

    public function testCreateVoucherWithCategories()
    {
        $category1 = $this->createCategory();
        $category2 = $this->createCategory();

        $voucherData = [
            'code' => 'CATEGORY10',
            'name' => 'Category Specific Voucher',
            'type' => 'percentage',
            'value' => 10,
            'status' => 'active',
            'category_ids' => [$category1->id, $category2->id]
        ];

        $response = $this->postForSite('/api/vouchers', $voucherData);

        $this->assertResponseStatus(201, $response);

        $data = json_decode($response->getContent(), true);
        $voucherId = $data['data']['voucher']['id'];

        $voucher = Voucher::find($voucherId);
        $this->assertCount(2, $voucher->categories);
    }

    public function testCreateVoucherWithBrands()
    {
        $brand1 = $this->createBrand();
        $brand2 = $this->createBrand();

        $voucherData = [
            'code' => 'BRAND10',
            'name' => 'Brand Specific Voucher',
            'type' => 'percentage',
            'value' => 10,
            'status' => 'active',
            'brand_ids' => [$brand1->id, $brand2->id]
        ];

        $response = $this->postForSite('/api/vouchers', $voucherData);

        $this->assertResponseStatus(201, $response);

        $data = json_decode($response->getContent(), true);
        $voucherId = $data['data']['voucher']['id'];

        $voucher = Voucher::find($voucherId);
        $this->assertCount(2, $voucher->brands);
    }

    public function testValidateVoucherForProductInCategory()
    {
        $category = $this->createCategory();
        $product = $this->createProduct(['price' => 100, 'category_id' => $category->id]);

        $voucher = $this->createVoucher(['code' => 'CATEGORY10', 'status' => 'active']);
        $voucher->categories(true)->attach($category->id);

        $response = $this->postForSite('/api/vouchers/validate', [
            'code' => 'CATEGORY10',
            'order_value' => 100,
            'product_id' => $product->id
        ]);

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['data']['valid']);
    }

    public function testValidateVoucherForProductWithBrand()
    {
        $brand = $this->createBrand();
        $product = $this->createProduct(['price' => 100, 'brand_id' => $brand->id]);

        $voucher = $this->createVoucher(['code' => 'BRAND10', 'status' => 'active']);
        $voucher->brands(true)->attach($brand->id);

        $response = $this->postForSite('/api/vouchers/validate', [
            'code' => 'BRAND10',
            'order_value' => 100,
            'product_id' => $product->id
        ]);

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['data']['valid']);
    }

    public function testValidateVoucherNotApplicableToProductCategory()
    {
        $category1 = $this->createCategory();
        $category2 = $this->createCategory();

        $product = $this->createProduct(['category_id' => $category2->id]);

        $voucher = $this->createVoucher(['code' => 'SPECIFIC', 'status' => 'active']);
        $voucher->categories(true)->attach($category1->id); // Only linked to category1

        $response = $this->postForSite('/api/vouchers/validate', [
            'code' => 'SPECIFIC',
            'order_value' => 100,
            'product_id' => $product->id // Product in category2
        ]);

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['data']['valid']);
        $this->assertStringContainsString('not applicable', $data['data']['message']);
    }

    public function testDuplicateVoucherCopiesCategoryLinks()
    {
        $category1 = $this->createCategory();
        $category2 = $this->createCategory();

        $original = $this->createVoucher();
        $original->categories(true)->attach([$category1->id, $category2->id]);

        $response = $this->postForSite("/api/vouchers/{$original->id}/duplicate");

        $this->assertResponseStatus(201, $response);

        $data = json_decode($response->getContent(), true);
        $newVoucherId = $data['data']['voucher']['id'];

        $newVoucher = Voucher::find($newVoucherId);
        $this->assertCount(2, $newVoucher->categories);
    }

    public function testDuplicateVoucherCopiesBrandLinks()
    {
        $brand1 = $this->createBrand();
        $brand2 = $this->createBrand();

        $original = $this->createVoucher();
        $original->brands(true)->attach([$brand1->id, $brand2->id]);

        $response = $this->postForSite("/api/vouchers/{$original->id}/duplicate");

        $this->assertResponseStatus(201, $response);

        $data = json_decode($response->getContent(), true);
        $newVoucherId = $data['data']['voucher']['id'];

        $newVoucher = Voucher::find($newVoucherId);
        $this->assertCount(2, $newVoucher->brands);
    }

    public function testUpdateVoucherCategories()
    {
        $category1 = $this->createCategory();
        $category2 = $this->createCategory();
        $category3 = $this->createCategory();

        $voucher = $this->createVoucher();
        $voucher->categories(true)->attach([$category1->id, $category2->id]);

        $updateData = [
            'code' => $voucher->code,
            'name' => $voucher->name,
            'type' => $voucher->type,
            'value' => $voucher->value,
            'category_ids' => [$category2->id, $category3->id] // Replace with new categories
        ];

        $response = $this->putForSite('/api/vouchers/' . $voucher->id, $updateData);

        $this->assertResponseOk($response);

        $voucher = Voucher::find($voucher->id);
        $categoryIds = $voucher->categories()->pluck('id')->toArray();

        $this->assertCount(2, $categoryIds);
        $this->assertContains($category2->id, $categoryIds);
        $this->assertContains($category3->id, $categoryIds);
        $this->assertNotContains($category1->id, $categoryIds);
    }

    public function testUpdateVoucherBrands()
    {
        $brand1 = $this->createBrand();
        $brand2 = $this->createBrand();
        $brand3 = $this->createBrand();

        $voucher = $this->createVoucher();
        $voucher->brands(true)->attach([$brand1->id, $brand2->id]);

        $updateData = [
            'code' => $voucher->code,
            'name' => $voucher->name,
            'type' => $voucher->type,
            'value' => $voucher->value,
            'brand_ids' => [$brand2->id, $brand3->id] // Replace with new brands
        ];

        $response = $this->putForSite('/api/vouchers/' . $voucher->id, $updateData);

        $this->assertResponseOk($response);

        $voucher = Voucher::find($voucher->id);
        $brandIds = $voucher->brands()->pluck('id')->toArray();

        $this->assertCount(2, $brandIds);
        $this->assertContains($brand2->id, $brandIds);
        $this->assertContains($brand3->id, $brandIds);
        $this->assertNotContains($brand1->id, $brandIds);
    }

    public function testBulkUpdateStatusSuccessfully(): void
    {
        $voucher1 = $this->createVoucher(['status' => 'inactive']);
        $voucher2 = $this->createVoucher(['status' => 'inactive']);

        $response = $this->postForSite('/api/vouchers/bulk-status', [
            'ids' => [$voucher1->id, $voucher2->id],
            'status' => 'active'
        ]);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['result']['updated']);

        // Verify in database
        $this->assertEquals('active', Voucher::find($voucher1->id)->status);
        $this->assertEquals('active', Voucher::find($voucher2->id)->status);
    }

    public function testBulkUpdateStatusValidation(): void
    {
        $response = $this->postForSite('/api/vouchers/bulk-status', [
            'ids' => [1, 2],
            'status' => 'invalid_status'
        ]);

        $this->assertResponseStatus(422, $response);
    }

    public function testBulkDeleteSuccessfully(): void
    {
        $voucher1 = $this->createVoucher(['usage_count' => 0]);
        $voucher2 = $this->createVoucher(['usage_count' => 0]);

        $response = $this->postForSite('/api/vouchers/bulk-delete', [
            'ids' => [$voucher1->id, $voucher2->id]
        ]);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['result']['deleted']);

        // Verify deletion
        $this->assertNull(Voucher::find($voucher1->id));
        $this->assertNull(Voucher::find($voucher2->id));
    }

    public function testBulkDeleteFailsWhenUsageExists(): void
    {
        $voucher1 = $this->createVoucher(['usage_count' => 0]);
        $voucher2 = $this->createVoucher(['usage_count' => 5]);

        $response = $this->postForSite('/api/vouchers/bulk-delete', [
            'ids' => [$voucher1->id, $voucher2->id]
        ]);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['result']['deleted']);
        $this->assertCount(1, $data['result']['failed']);
        $this->assertStringContainsString('used', $data['result']['failed'][0]['reason']);

        // Verify voucher1 deleted, voucher2 still exists
        $this->assertNull(Voucher::find($voucher1->id));
        $this->assertNotNull(Voucher::find($voucher2->id));
    }

    public function testApplyVoucherWithUserId()
    {
        $voucher = $this->createVoucher(['status' => 'active']);
        $user = $this->createMember();

        $response = $this->postForSite('/api/vouchers/' . $voucher->id . '/apply', [
            'user_id' => $user->id,
            'discount_amount' => 15.50
        ]);

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Voucher applied successfully', $data['data']['message']);

        // Verify usage count increased
        $updatedVoucher = Voucher::find($voucher->id);
        $this->assertEquals(1, $updatedVoucher->usage_count);

        // Verify redemption was created
        $redemption = VoucherRedemption::where('voucher_id', $voucher->id)
            ->where('member_id', $user->id)
            ->first();
        $this->assertNotNull($redemption);
        $this->assertEquals(15.50, $redemption->discount_amount);
    }

    public function testApplyVoucherWithoutUserId()
    {
        $voucher = $this->createVoucher(['status' => 'active']);

        $response = $this->postForSite('/api/vouchers/' . $voucher->id . '/apply', [
            'discount_amount' => 10.00
        ]);

        $this->assertResponseOk($response);

        // Verify redemption was created without user_id
        $redemption = VoucherRedemption::where('voucher_id', $voucher->id)
            ->whereNull('member_id')
            ->first();
        $this->assertNotNull($redemption);
    }

    public function testApplyVoucherWithOrderId()
    {
        $voucher = $this->createVoucher(['status' => 'active']);
        $user = $this->createMember();
        $order = $this->createOrder();

        $response = $this->postForSite('/api/vouchers/' . $voucher->id . '/apply', [
            'user_id' => $user->id,
            'discount_amount' => 20.00,
            'order_id' => $order->id
        ]);

        $this->assertResponseOk($response);

        // Verify redemption was created with order_id
        $redemption = VoucherRedemption::where('voucher_id', $voucher->id)
            ->where('order_id', $order->id)
            ->first();
        $this->assertNotNull($redemption);
    }

    public function testValidateVoucherChecksPerUserLimit()
    {
        $voucher = $this->createVoucher([
            'code' => 'LIMITED2',
            'status' => 'active',
            'per_user_limit' => 2
        ]);

        $user = $this->createMember();

        // Use voucher twice
        VoucherRedemption::create([
            'voucher_id' => $voucher->id,
            'member_id' => $user->id,
            'discount_amount' => 10.00,
            'redeemed_at' => date('Y-m-d H:i:s')
        ]);
        VoucherRedemption::create([
            'voucher_id' => $voucher->id,
            'member_id' => $user->id,
            'discount_amount' => 10.00,
            'redeemed_at' => date('Y-m-d H:i:s')
        ]);

        // Try to use it a third time
        $response = $this->postForSite('/api/vouchers/validate', [
            'code' => 'LIMITED2',
            'order_value' => 100,
            'user_id' => $user->id
        ]);

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['data']['valid']);
        $this->assertStringContainsString('already used', $data['data']['message']);
    }

    public function testValidateVoucherAllowsUsageWithinPerUserLimit()
    {
        $voucher = $this->createVoucher([
            'code' => 'LIMITED',
            'status' => 'active',
            'per_user_limit' => 3
        ]);
        $user = $this->createMember();

        // Use voucher once
        VoucherRedemption::create([
            'voucher_id' => $voucher->id,
            'member_id' => $user->id,
            'discount_amount' => 10.00,
            'redeemed_at' => date('Y-m-d H:i:s')
        ]);

        // Try to use it again (should work)
        $response = $this->postForSite('/api/vouchers/validate', [
            'code' => 'LIMITED',
            'order_value' => 100,
            'member_id' => $user->id
        ]);

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['data']['valid']);
    }

    public function testGetRedemptions()
    {
        $voucher = $this->createVoucher();
        $user1 = $this->createMember();
        $user2 = $this->createMember();

        VoucherRedemption::create([
            'voucher_id' => $voucher->id,
            'member_id' => $user1->id,
            'discount_amount' => 10.00,
            'redeemed_at' => date('Y-m-d H:i:s')
        ]);
        VoucherRedemption::create([
            'voucher_id' => $voucher->id,
            'member_id' => $user2->id,
            'discount_amount' => 15.00,
            'redeemed_at' => date('Y-m-d H:i:s')
        ]);

        $response = $this->getForSite('/api/vouchers/' . $voucher->id . '/redemptions');

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('redemptions', $data['data']);
        $this->assertCount(2, $data['data']['redemptions']);
    }
}