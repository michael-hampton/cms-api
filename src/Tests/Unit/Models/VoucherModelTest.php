<?php

namespace App\Tests\Unit\Models;

use App\Models\Subscription;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
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

    public function test_has_been_used_by_user_returns_true_when_user_redeemed()
    {
        $voucher = Voucher::create([
            'code' => 'TEST10',
            'name' => 'Test',
            'type' => 'percentage',
            'value' => 10,
            'status' => 'active',
            'site_id' => $this->siteId
        ]);

        $user = $this->createMember();

        VoucherRedemption::create([
            'voucher_id' => $voucher->id,
            'member_id' => $user->id,
            'discount_amount' => 10.00,
            'redeemed_at' => date('Y-m-d H:i:s')
        ]);

        $this->assertTrue($voucher->hasBeenUsedByUser($user->id));
    }

    public function test_has_been_used_by_user_returns_false_when_user_not_redeemed()
    {
        $voucher = Voucher::create([
            'code' => 'TEST10',
            'name' => 'Test',
            'type' => 'percentage',
            'value' => 10,
            'status' => 'active',
            'site_id' => $this->siteId
        ]);

        $user = $this->createUser();

        $this->assertFalse($voucher->hasBeenUsedByUser($user->id));
    }

    public function test_has_been_used_by_user_returns_false_when_user_id_is_null()
    {
        $voucher = Voucher::create([
            'code' => 'TEST10',
            'name' => 'Test',
            'type' => 'percentage',
            'value' => 10,
            'status' => 'active',
            'site_id' => $this->siteId
        ]);

        $this->assertFalse($voucher->hasBeenUsedByUser(null));
    }

    public function test_get_user_usage_count_returns_correct_count()
    {
        $voucher = Voucher::create([
            'code' => 'TEST10',
            'name' => 'Test',
            'type' => 'percentage',
            'value' => 10,
            'status' => 'active',
            'site_id' => $this->siteId
        ]);

        $user = $this->createMember();

        // Create 3 redemptions
        for ($i = 0; $i < 3; $i++) {
            VoucherRedemption::create([
                'voucher_id' => $voucher->id,
                'member_id' => $user->id,
                'discount_amount' => 10.00,
                'redeemed_at' => date('Y-m-d H:i:s')
            ]);
        }

        $this->assertEquals(3, $voucher->getUserUsageCount($user->id));
    }

    public function test_get_user_usage_count_returns_zero_when_no_redemptions()
    {
        $voucher = Voucher::create([
            'code' => 'TEST10',
            'name' => 'Test',
            'type' => 'percentage',
            'value' => 10,
            'status' => 'active',
            'site_id' => $this->siteId
        ]);

        $user = $this->createMember();

        $this->assertEquals(0, $voucher->getUserUsageCount($user->id));
    }

    public function test_get_user_usage_count_returns_zero_when_user_id_is_null()
    {
        $voucher = Voucher::create([
            'code' => 'TEST10',
            'name' => 'Test',
            'type' => 'percentage',
            'value' => 10,
            'status' => 'active',
            'site_id' => $this->siteId
        ]);

        $this->assertEquals(0, $voucher->getUserUsageCount(null));
    }

    public function test_get_user_usage_count_only_counts_specific_user()
    {
        $voucher = Voucher::create([
            'code' => 'TEST10',
            'name' => 'Test',
            'type' => 'percentage',
            'value' => 10,
            'status' => 'active',
            'site_id' => $this->siteId
        ]);

        $user1 = $this->createMember();
        $user2 = $this->createMember();

        // User 1 uses twice
        VoucherRedemption::create([
            'voucher_id' => $voucher->id,
            'member_id' => $user1->id,
            'discount_amount' => 10.00,
            'redeemed_at' => date('Y-m-d H:i:s')
        ]);
        VoucherRedemption::create([
            'voucher_id' => $voucher->id,
            'member_id' => $user1->id,
            'discount_amount' => 10.00,
            'redeemed_at' => date('Y-m-d H:i:s')
        ]);

        // User 2 uses once
        VoucherRedemption::create([
            'voucher_id' => $voucher->id,
            'member_id' => $user2->id,
            'discount_amount' => 10.00,
            'redeemed_at' => date('Y-m-d H:i:s')
        ]);

        $this->assertEquals(2, $voucher->getUserUsageCount($user1->id));
        $this->assertEquals(1, $voucher->getUserUsageCount($user2->id));
    }

    public function test_voucher_applies_to_subscriptions()
    {
        $voucher = Voucher::create([
            'code' => 'SUB10',
            'name' => 'Subscription Discount',
            'type' => 'percentage',
            'value' => 10,
            'status' => 'active',
            'site_id' => $this->siteId,
            'applies_to_subscriptions' => true
        ]);

        $this->assertTrue($voucher->appliesToSubscriptions());
    }

    public function test_voucher_does_not_apply_to_subscriptions_by_default()
    {
        $voucher = Voucher::create([
            'code' => 'PRODUCT10',
            'name' => 'Product Discount',
            'type' => 'percentage',
            'value' => 10,
            'status' => 'active',
            'site_id' => $this->siteId
        ]);

        $this->assertFalse($voucher->appliesToSubscriptions());
    }

    public function test_is_applicable_to_subscription_plan()
    {
        $plan = $this->createSubscriptionPlan();

        $voucher = Voucher::create([
            'code' => 'SUB10',
            'name' => 'Subscription Discount',
            'type' => 'percentage',
            'value' => 10,
            'status' => 'active',
            'site_id' => $this->siteId,
            'applies_to_subscriptions' => true,
            'subscription_plan_ids' => [$plan->id]
        ]);

        $this->assertTrue($voucher->isApplicableToSubscriptionPlan($plan->id));
    }

    public function test_is_not_applicable_to_subscription_plan_when_plan_not_in_list()
    {
        $plan1 = $this->createSubscriptionPlan();
        $plan2 = $this->createSubscriptionPlan();

        $voucher = Voucher::create([
            'code' => 'SUB10',
            'name' => 'Subscription Discount',
            'type' => 'percentage',
            'value' => 10,
            'status' => 'active',
            'site_id' => $this->siteId,
            'applies_to_subscriptions' => true,
            'subscription_plan_ids' => [$plan1->id]
        ]);

        $this->assertFalse($voucher->isApplicableToSubscriptionPlan($plan2->id));
    }

    public function test_is_applicable_to_all_subscription_plans_when_empty_list()
    {
        $plan = $this->createSubscriptionPlan();

        $voucher = Voucher::create([
            'code' => 'SUB10',
            'name' => 'Subscription Discount',
            'type' => 'percentage',
            'value' => 10,
            'status' => 'active',
            'site_id' => $this->siteId,
            'applies_to_subscriptions' => true,
            'subscription_plan_ids' => []
        ]);

        $this->assertTrue($voucher->isApplicableToSubscriptionPlan($plan->id));
    }

    public function test_is_not_applicable_when_does_not_apply_to_subscriptions()
    {
        $plan = $this->createSubscriptionPlan();

        $voucher = Voucher::create([
            'code' => 'PRODUCT10',
            'name' => 'Product Discount',
            'type' => 'percentage',
            'value' => 10,
            'status' => 'active',
            'site_id' => $this->siteId,
            'applies_to_subscriptions' => false
        ]);

        $this->assertFalse($voucher->isApplicableToSubscriptionPlan($plan->id));
    }

    public function test_calculate_subscription_discount_percentage()
    {
        $voucher = Voucher::create([
            'code' => 'SUB20',
            'name' => 'Subscription Discount',
            'type' => 'percentage',
            'value' => 20,
            'status' => 'active',
            'site_id' => $this->siteId,
            'applies_to_subscriptions' => true
        ]);

        $discount = $voucher->calculateSubscriptionDiscount(100.00);

        $this->assertEquals(20.00, $discount);
    }

    public function test_calculate_subscription_discount_fixed()
    {
        $voucher = Voucher::create([
            'code' => 'SUB15',
            'name' => 'Subscription Discount',
            'type' => 'fixed',
            'value' => 15,
            'status' => 'active',
            'site_id' => $this->siteId,
            'applies_to_subscriptions' => true
        ]);

        $discount = $voucher->calculateSubscriptionDiscount(100.00);

        $this->assertEquals(15.00, $discount);
    }

    public function test_calculate_subscription_discount_respects_maximum()
    {
        $voucher = Voucher::create([
            'code' => 'SUB50',
            'name' => 'Subscription Discount',
            'type' => 'percentage',
            'value' => 50,
            'maximum_discount' => 20,
            'status' => 'active',
            'site_id' => $this->siteId,
            'applies_to_subscriptions' => true
        ]);

        $discount = $voucher->calculateSubscriptionDiscount(100.00);

        $this->assertEquals(20.00, $discount);
    }

    public function test_subscriptions_relationship()
    {
        $member = $this->createMember();
        $plan = $this->createSubscriptionPlan();

        $voucher = Voucher::create([
            'code' => 'SUB10',
            'name' => 'Subscription Discount',
            'type' => 'percentage',
            'value' => 10,
            'status' => 'active',
            'site_id' => $this->siteId,
            'applies_to_subscriptions' => true
        ]);

        $subscription1 = Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD',
            'voucher_id' => $voucher->id,
            'discount_amount' => 2.99
        ]);

        $subscription2 = Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD',
            'voucher_id' => $voucher->id,
            'discount_amount' => 2.99
        ]);

        $subscriptions = $voucher->subscriptions();

        $this->assertCount(2, $subscriptions);
        $this->assertTrue($subscriptions->contains('id', $subscription1->id));
        $this->assertTrue($subscriptions->contains('id', $subscription2->id));
    }

    public function test_stripe_coupon_id_is_nullable()
    {
        $voucher = Voucher::create([
            'code' => 'SUB10',
            'name' => 'Subscription Discount',
            'type' => 'percentage',
            'value' => 10,
            'status' => 'active',
            'site_id' => $this->siteId,
            'applies_to_subscriptions' => true,
            'stripe_coupon_id' => null
        ]);

        $this->assertNull($voucher->stripe_coupon_id);
    }

    public function test_stripe_coupon_id_can_be_set()
    {
        $voucher = Voucher::create([
            'code' => 'SUB10',
            'name' => 'Subscription Discount',
            'type' => 'percentage',
            'value' => 10,
            'status' => 'active',
            'site_id' => $this->siteId,
            'applies_to_subscriptions' => true,
            'stripe_coupon_id' => 'coupon_test123'
        ]);

        $this->assertEquals('coupon_test123', $voucher->stripe_coupon_id);
    }

    public function test_duration_in_months_is_nullable()
    {
        $voucher = Voucher::create([
            'code' => 'SUB10',
            'name' => 'Subscription Discount',
            'type' => 'percentage',
            'value' => 10,
            'status' => 'active',
            'site_id' => $this->siteId,
            'applies_to_subscriptions' => true,
            'duration_in_months' => null
        ]);

        $this->assertNull($voucher->duration_in_months);
    }

    public function test_duration_in_months_can_be_set()
    {
        $voucher = Voucher::create([
            'code' => 'SUB10',
            'name' => 'Subscription Discount',
            'type' => 'percentage',
            'value' => 10,
            'status' => 'active',
            'site_id' => $this->siteId,
            'applies_to_subscriptions' => true,
            'duration_in_months' => 3
        ]);

        $this->assertEquals(3, $voucher->duration_in_months);
    }
}