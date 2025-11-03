<?php

namespace App\Tests\Unit\Models;

use App\Models\Voucher;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class VoucherModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_is_valid_returns_true_for_active_voucher()
    {
        $voucher = Voucher::create([
            'code' => 'TEST10',
            'name' => 'Test',
            'type' => 'percentage',
            'value' => 10,
            'status' => 'active',
            'site_id' => $this->siteId
        ]);

        $this->assertTrue($voucher->isValid());
    }

    public function test_is_valid_returns_false_for_inactive_voucher()
    {
        $voucher = Voucher::create([
            'code' => 'TEST10',
            'name' => 'Test',
            'type' => 'percentage',
            'value' => 10,
            'status' => 'inactive',
            'site_id' => $this->siteId
        ]);

        $this->assertFalse($voucher->isValid());
    }

    public function test_is_valid_returns_false_for_expired_voucher()
    {
        $voucher = Voucher::create([
            'code' => 'TEST10',
            'name' => 'Test',
            'type' => 'percentage',
            'value' => 10,
            'status' => 'active',
            'expires_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'site_id' => $this->siteId
        ]);

        $this->assertFalse($voucher->isValid());
    }

    public function test_is_valid_returns_false_for_not_started_voucher()
    {
        $voucher = Voucher::create([
            'code' => 'TEST10',
            'name' => 'Test',
            'type' => 'percentage',
            'value' => 10,
            'status' => 'active',
            'starts_at' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'site_id' => $this->siteId
        ]);

        $this->assertFalse($voucher->isValid());
    }

    public function test_is_valid_returns_false_when_usage_limit_reached()
    {
        $voucher = Voucher::create([
            'code' => 'TEST10',
            'name' => 'Test',
            'type' => 'percentage',
            'value' => 10,
            'status' => 'active',
            'usage_limit' => 10,
            'usage_count' => 10,
            'site_id' => $this->siteId
        ]);

        $this->assertFalse($voucher->isValid());
    }

    public function test_is_expired_returns_true_when_past_expiry()
    {
        $voucher = Voucher::create([
            'code' => 'TEST10',
            'name' => 'Test',
            'type' => 'percentage',
            'value' => 10,
            'expires_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'site_id' => $this->siteId
        ]);

        $this->assertTrue($voucher->isExpired());
    }

    public function test_is_expired_returns_false_with_null_expiry()
    {
        $voucher = Voucher::create([
            'code' => 'TEST10',
            'name' => 'Test',
            'type' => 'percentage',
            'value' => 10,
            'expires_at' => null,
            'site_id' => $this->siteId
        ]);

        $this->assertFalse($voucher->isExpired());
    }

    public function test_calculate_discount_for_percentage_type()
    {
        $voucher = Voucher::create([
            'code' => 'TEST10',
            'name' => 'Test',
            'type' => 'percentage',
            'value' => 10,
            'site_id' => $this->siteId
        ]);

        $discount = $voucher->calculateDiscount(100);
        $this->assertEquals(10.0, $discount);
    }

    public function test_calculate_discount_for_fixed_type()
    {
        $voucher = Voucher::create([
            'code' => 'TEST10',
            'name' => 'Test',
            'type' => 'fixed',
            'value' => 15,
            'site_id' => $this->siteId
        ]);

        $discount = $voucher->calculateDiscount(100);
        $this->assertEquals(15.0, $discount);
    }

    public function test_calculate_discount_respects_maximum_discount()
    {
        $voucher = Voucher::create([
            'code' => 'TEST10',
            'name' => 'Test',
            'type' => 'percentage',
            'value' => 50,
            'maximum_discount' => 20,
            'site_id' => $this->siteId
        ]);

        $discount = $voucher->calculateDiscount(100);
        $this->assertEquals(20.0, $discount);
    }

    public function test_calculate_discount_returns_zero_below_minimum_order_value()
    {
        $voucher = Voucher::create([
            'code' => 'TEST10',
            'name' => 'Test',
            'type' => 'percentage',
            'value' => 10,
            'minimum_order_value' => 50,
            'site_id' => $this->siteId
        ]);

        $discount = $voucher->calculateDiscount(30);
        $this->assertEquals(0.0, $discount);
    }

    public function test_is_applicable_to_product_returns_true_with_no_restrictions()
    {
        $voucher = Voucher::create([
            'code' => 'TEST10',
            'name' => 'Test',
            'type' => 'percentage',
            'value' => 10,
            'site_id' => $this->siteId
        ]);

        $product = $this->createProduct();

        $this->assertTrue($voucher->isApplicableToProduct($product->id));
    }

    public function test_is_applicable_to_product_with_direct_product_link()
    {
        $voucher = Voucher::create([
            'code' => 'TEST10',
            'name' => 'Test',
            'type' => 'percentage',
            'value' => 10,
            'site_id' => $this->siteId
        ]);

        $product = $this->createProduct();
        $voucher->products(true)->attach($product->id);

        $this->assertTrue($voucher->isApplicableToProduct($product->id));
    }

    public function test_is_applicable_to_product_with_category_link()
    {
        $category = $this->createCategory();
        $product = $this->createProduct(['category_id' => $category->id]);

        $voucher = Voucher::create([
            'code' => 'TEST10',
            'name' => 'Test',
            'type' => 'percentage',
            'value' => 10,
            'site_id' => $this->siteId
        ]);

        $voucher->categories(true)->attach($category->id);

        $this->assertTrue($voucher->isApplicableToProduct($product->id));
    }

    public function test_is_applicable_to_product_with_brand_link()
    {
        $brand = $this->createBrand();
        $product = $this->createProduct(['brand_id' => $brand->id]);

        $voucher = Voucher::create([
            'code' => 'TEST10',
            'name' => 'Test',
            'type' => 'percentage',
            'value' => 10,
            'site_id' => $this->siteId
        ]);

        $voucher->brands(true)->attach($brand->id);

        $this->assertTrue($voucher->isApplicableToProduct($product->id));
    }

    public function test_is_applicable_to_product_returns_false_when_not_linked()
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        $voucher = Voucher::create([
            'code' => 'TEST10',
            'name' => 'Test',
            'type' => 'percentage',
            'value' => 10,
            'site_id' => $this->siteId
        ]);

        $voucher->products(true)->attach($product1->id);

        $this->assertFalse($voucher->isApplicableToProduct($product2->id));
    }
}