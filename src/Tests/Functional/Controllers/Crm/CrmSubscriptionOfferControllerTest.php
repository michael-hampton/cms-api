<?php

namespace App\Tests\Functional\Controllers\Crm;

use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPricing;
use App\Models\Voucher;
use App\Models\VoucherSubscriptionPlan;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

/**
 * Functional tests for GET /api/crm/subscription-offers.
 *
 * Uses the real framework test infrastructure (FunctionalTestCase + CreatesTestData).
 * No Laravel-isms: no actingAsAdmin(), no assertJsonFragment(), no assertJsonStructure().
 */
class CrmSubscriptionOfferControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    // =========================================================================
    // Authentication
    // =========================================================================

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->unauthenticate();

        $response = $this->getForSite('/api/crm/subscription-offers');

        $this->assertResponseStatus(401, $response);
    }

    // =========================================================================
    // Happy path — response shape
    // =========================================================================

    public function test_returns_200_with_expected_top_level_keys(): void
    {
        $plan = $this->createActivePlan();
        $this->createPricingTier(['plan_id' => $plan->id, 'price' => 120.00, 'sale_price' => 99.00]);

        $response = $this->getForSite('/api/crm/subscription-offers');

        $this->assertResponseStatus(200, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertIsArray($data['data']);
    }

    public function test_offer_row_has_expected_fields(): void
    {
        $plan = $this->createActivePlan();
        $this->createPricingTier(['plan_id' => $plan->id, 'price' => 120.00, 'sale_price' => 99.00]);

        $response = $this->getForSite('/api/crm/subscription-offers');

        $data = json_decode($response->getContent(), true);
        $this->assertNotEmpty($data['data'], 'Expected at least one offer row');

        $row = $data['data'][0];
        foreach (['id', 'plan_id', 'plan_name', 'pricing_id', 'offer_type', 'original_price', 'offer_price', 'saving_amount', 'saving_percentage'] as $key) {
            $this->assertArrayHasKey($key, $row, "Offer row missing key: {$key}");
        }
    }

    public function test_pagination_block_has_expected_fields(): void
    {
        $response = $this->getForSite('/api/crm/subscription-offers');

        $data = json_decode($response->getContent(), true);
        foreach (['total', 'per_page', 'current_page', 'last_page'] as $key) {
            $this->assertArrayHasKey($key, $data['pagination'], "Pagination missing key: {$key}");
        }
    }

    // =========================================================================
    // Offer type derivation
    // =========================================================================

    public function test_print_discount_tier_produces_print_offer(): void
    {
        $plan = $this->createActivePlan();
        $this->createPricingTier(['plan_id' => $plan->id, 'price' => 120.00, 'sale_price' => 99.00]);

        $response = $this->getForSite('/api/crm/subscription-offers');
        $data     = json_decode($response->getContent(), true);

        $types = array_column($data['data'], 'offer_type');
        $this->assertContains('print', $types);
    }

    public function test_digital_discount_tier_produces_digital_offer(): void
    {
        $plan = $this->createActivePlan();
        $this->createPricingTier(['plan_id' => $plan->id, 'digital_price' => 75.00, 'digital_sale_price' => 60.00]);

        $response = $this->getForSite('/api/crm/subscription-offers');
        $data     = json_decode($response->getContent(), true);

        $types = array_column($data['data'], 'offer_type');
        $this->assertContains('digital', $types);
    }

    public function test_intro_pricing_tier_produces_intro_offer(): void
    {
        $plan = $this->createActivePlan();
        $this->createPricingTier(['plan_id' => $plan->id, 'price' => 100.00, 'intro_price' => 1.00, 'intro_cycles' => 3]);

        $response = $this->getForSite('/api/crm/subscription-offers');
        $data     = json_decode($response->getContent(), true);

        $types = array_column($data['data'], 'offer_type');
        $this->assertContains('intro', $types);
    }

    public function test_intro_offer_row_contains_intro_cycles(): void
    {
        $plan = $this->createActivePlan();
        $this->createPricingTier(['plan_id' => $plan->id, 'price' => 100.00, 'intro_price' => 1.00, 'intro_cycles' => 3]);

        $response = $this->getForSite('/api/crm/subscription-offers');
        $data     = json_decode($response->getContent(), true);

        $introOffers = array_filter($data['data'], fn($r) => $r['offer_type'] === 'intro');
        $this->assertNotEmpty($introOffers);
        $row = array_values($introOffers)[0];
        $this->assertEquals(3, $row['intro_cycles']);
        $this->assertEquals(1.00, $row['offer_price']);
    }

    public function test_plan_with_linked_voucher_produces_voucher_offer(): void
    {
        $plan    = $this->createActivePlan();
        $voucher = $this->createVoucher(['applies_to_subscriptions' => true]);
        $this->attachVoucherToPlan($plan, $voucher);
        $this->createPricingTier(['plan_id' => $plan->id, 'price' => 120.00]);

        $response = $this->getForSite('/api/crm/subscription-offers');
        $data     = json_decode($response->getContent(), true);

        $types = array_column($data['data'], 'offer_type');
        $this->assertContains('voucher', $types);
    }

    public function test_voucher_offer_row_contains_voucher_code(): void
    {
        $plan    = $this->createActivePlan();
        $voucher = $this->createVoucher(['applies_to_subscriptions' => true, 'code' => 'SAVE20']);
        $this->attachVoucherToPlan($plan, $voucher);
        $this->createPricingTier(['plan_id' => $plan->id, 'price' => 120.00]);

        $response = $this->getForSite('/api/crm/subscription-offers');
        $data     = json_decode($response->getContent(), true);

        $voucherOffers = array_filter($data['data'], fn($r) => $r['offer_type'] === 'voucher');
        $this->assertNotEmpty($voucherOffers);
        $row = array_values($voucherOffers)[0];
        $this->assertEquals('SAVE20', $row['voucher_code']);
    }

    // =========================================================================
    // Saving calculations
    // =========================================================================

    public function test_saving_amount_and_percentage_are_calculated_correctly(): void
    {
        $plan = $this->createActivePlan();
        $this->createPricingTier(['plan_id' => $plan->id, 'price' => 120.00, 'sale_price' => 99.00]);

        $response = $this->getForSite('/api/crm/subscription-offers');
        $data     = json_decode($response->getContent(), true);

        $printOffers = array_filter($data['data'], fn($r) => $r['offer_type'] === 'print');
        $this->assertNotEmpty($printOffers);
        $row = array_values($printOffers)[0];

        $this->assertEquals(21.00, $row['saving_amount']);
        $this->assertEquals(18,    $row['saving_percentage']); // (120-99)/120 * 100 = 17.5 → 18
    }

    // =========================================================================
    // Inactive tier exclusion
    // =========================================================================

    public function test_inactive_pricing_tiers_are_excluded(): void
    {
        $plan = $this->createActivePlan();
        SubscriptionPlanPricing::create([
            'plan_id'    => $plan->id,
            'site_id'    => $this->siteId,
            'price'      => 120.00,
            'sale_price' => 99.00,
            'is_active'  => false,
            'is_default' => true,
            'sort_order' => 1,
            'currency'   => 'GBP',
            'duration_months' => 12,
            'issue_count' => 1,
            'label' => 'test',
            'period_description' => 'test',
        ]);

        $response = $this->getForSite('/api/crm/subscription-offers');
        $data     = json_decode($response->getContent(), true);

        $this->assertEmpty($data['data']);
    }

    // =========================================================================
    // Filter: search
    // =========================================================================

    public function test_search_filter_returns_matching_plan_only(): void
    {
        $matching    = $this->createActivePlan('Premium Annual');
        $nonMatching = $this->createActivePlan('Basic Monthly');

        $this->createPricingTier(['plan_id' => $matching->id, 'price' => 120.00, 'sale_price' => 99.00]);
        $this->createPricingTier(['plan_id' => $nonMatching->id, 'price' => 10.00, 'sale_price' => 8.00]);

        $response = $this->getForSite('/api/crm/subscription-offers?search=Premium');
        $data     = json_decode($response->getContent(), true);

        $planNames = array_unique(array_column($data['data'], 'plan_name'));
        $this->assertContains('Premium Annual', $planNames);
        $this->assertNotContains('Basic Monthly', $planNames);
    }

    // =========================================================================
    // Filter: site_id
    // =========================================================================

    public function test_site_filter_excludes_other_sites(): void
    {
        $otherSite = $this->createSite();

        $planOnThis = $this->createActivePlan('On This Site');

        $planOnOther = SubscriptionPlan::create([
            'site_id'     => $otherSite->id,
            'name'        => 'On Other Site',
            'slug'        => 'on-other-site-' . uniqid(),
            'price'       => 100.00,
            'currency'    => 'GBP',
            'is_active'   => true,
            'is_featured' => false,
            'sort_order'  => 1,
            'plan_type'   => 'recurring',
        ]);

        $this->createPricingTier([
            'plan_id'    => $planOnThis->id,
            'site_id'    => $this->siteId,
            'price'      => 120.00,
            'sale_price' => 99.00,
        ]);

        $this->createPricingTier([
            'plan_id'    => $planOnOther->id,
            'site_id'    => $otherSite->id,
            'price'      => 120.00,
            'sale_price' => 99.00,
        ]);

        $response = $this->getForSite("/api/crm/subscription-offers?site_id={$this->siteId}");
        $data = json_decode($response->getContent(), true);

        $planNames = array_column($data['data'], 'plan_name');

        $this->assertContains('On This Site', $planNames);
        $this->assertNotContains('On Other Site', $planNames);
    }

    // =========================================================================
    // Filter: offer_type
    // =========================================================================

    public function test_offer_type_filter_returns_only_matching_type(): void
    {
        $plan = $this->createActivePlan();

        $this->createPricingTier([
            'plan_id'            => $plan->id,
            'price'              => 120.00,
            'sale_price'         => 99.00,
            'digital_price'      => 75.00,
            'digital_sale_price' => 60.00,
        ]);

        $response = $this->getForSite('/api/crm/subscription-offers?offer_type=print');
        $data = json_decode($response->getContent(), true);

        $types = array_unique(array_column($data['data'], 'offer_type'));

        $this->assertSame(['print'], array_values($types));
    }

    public function test_invalid_offer_type_returns_422(): void
    {
        $response = $this->getForSite('/api/crm/subscription-offers?offer_type=garbage');
        $data     = json_decode($response->getContent(), true);

        $this->assertResponseStatus(422, $response);
        $this->assertFalse($data['success']);
    }

    // =========================================================================
    // Empty results
    // =========================================================================

    public function test_empty_results_return_valid_paginated_response(): void
    {
        $response = $this->getForSite('/api/crm/subscription-offers?search=xyznonexistent_abc');
        $data     = json_decode($response->getContent(), true);

        $this->assertResponseStatus(200, $response);
        $this->assertTrue($data['success']);
        $this->assertSame([], $data['data']);
        $this->assertSame(0, $data['pagination']['total']);
        $this->assertSame(1, $data['pagination']['current_page']);
    }

    // =========================================================================
    // Pagination
    // =========================================================================

    public function test_results_are_paginated(): void
    {
        $plan = $this->createActivePlan();

        for ($i = 1; $i <= 5; $i++) {
            $this->createPricingTier([
                'plan_id'         => $plan->id,
                'site_id'         => $this->siteId,
                'price'           => 100.00 + $i,
                'sale_price'      => 80.00 + $i,
                'is_active'       => true,
                'is_default'      => $i === 1,
                'sort_order'      => $i,
                'currency'        => 'GBP',
                'duration_months' => $i,
                'issue_count'     => $i,
                'label'           => "Tier {$i}",
                'period_description' => "Tier {$i}",
            ]);
        }

        $response = $this->getForSite('/api/crm/subscription-offers?per_page=2&page=1');
        $data = json_decode($response->getContent(), true);

        $this->assertResponseStatus(200, $response);
        $this->assertCount(2, $data['data']);
        $this->assertSame(5, $data['pagination']['total']);
        $this->assertSame(3, $data['pagination']['last_page']);
    }

    public function test_min_offer_price_filter_excludes_cheaper_offers(): void
    {
        $cheapPlan = $this->createActivePlan('Cheap Plan');
        $expensivePlan = $this->createActivePlan('Expensive Plan');

        $this->createPricingTier([
            'plan_id' => $cheapPlan->id,
            'price' => 100.00,
            'sale_price' => 40.00,
            'duration_months' => 1,
        ]);

        $this->createPricingTier([
            'plan_id' => $expensivePlan->id,
            'price' => 200.00,
            'sale_price' => 120.00,
            'duration_months' => 1,
        ]);

        $response = $this->getForSite('/api/crm/subscription-offers?min_price=100');
        $data = json_decode($response->getContent(), true);

        $this->assertResponseStatus(200, $response);

        $planNames = array_column($data['data'], 'plan_name');

        $this->assertContains('Expensive Plan', $planNames);
        $this->assertNotContains('Cheap Plan', $planNames);
    }

    public function test_max_offer_price_filter_excludes_more_expensive_offers(): void
    {
        $cheapPlan = $this->createActivePlan('Cheap Plan');
        $expensivePlan = $this->createActivePlan('Expensive Plan');

        $this->createPricingTier([
            'plan_id' => $cheapPlan->id,
            'price' => 100.00,
            'sale_price' => 40.00,
            'duration_months' => 1,
        ]);

        $this->createPricingTier([
            'plan_id' => $expensivePlan->id,
            'price' => 200.00,
            'sale_price' => 120.00,
            'duration_months' => 1,
        ]);

        $response = $this->getForSite('/api/crm/subscription-offers?max_price=100');
        $data = json_decode($response->getContent(), true);

        $this->assertResponseStatus(200, $response);

        $planNames = array_column($data['data'], 'plan_name');

        $this->assertContains('Cheap Plan', $planNames);
        $this->assertNotContains('Expensive Plan', $planNames);
    }

    public function test_offer_price_range_filter_returns_offers_inside_range(): void
    {
        $cheapPlan = $this->createActivePlan('Cheap Plan');
        $middlePlan = $this->createActivePlan('Middle Plan');
        $expensivePlan = $this->createActivePlan('Expensive Plan');

        $this->createPricingTier([
            'plan_id' => $cheapPlan->id,
            'price' => 100.00,
            'sale_price' => 40.00,
            'duration_months' => 1,
        ]);

        $this->createPricingTier([
            'plan_id' => $middlePlan->id,
            'price' => 150.00,
            'sale_price' => 90.00,
            'duration_months' => 1,
        ]);

        $this->createPricingTier([
            'plan_id' => $expensivePlan->id,
            'price' => 200.00,
            'sale_price' => 150.00,
            'duration_months' => 1,
        ]);

        $response = $this->getForSite('/api/crm/subscription-offers?min_price=80&max_price=100');
        $data = json_decode($response->getContent(), true);

        $this->assertResponseStatus(200, $response);

        $planNames = array_column($data['data'], 'plan_name');

        $this->assertContains('Middle Plan', $planNames);
        $this->assertNotContains('Cheap Plan', $planNames);
        $this->assertNotContains('Expensive Plan', $planNames);
    }

    public function test_has_intro_pricing_true_returns_only_intro_pricing_tiers(): void
    {
        $introPlan = $this->createActivePlan('Intro Plan');
        $normalPlan = $this->createActivePlan('Normal Plan');

        $this->createPricingTier([
            'plan_id' => $introPlan->id,
            'price' => 100.00,
            'intro_price' => 1.00,
            'intro_cycles' => 3,
            'duration_months' => 1,
        ]);

        $this->createPricingTier([
            'plan_id' => $normalPlan->id,
            'price' => 100.00,
            'sale_price' => 80.00,
            'duration_months' => 1,
        ]);

        $response = $this->getForSite('/api/crm/subscription-offers?has_intro_pricing=true');
        $data = json_decode($response->getContent(), true);

        $planNames = array_column($data['data'], 'plan_name');

        $this->assertContains('Intro Plan', $planNames);
        $this->assertNotContains('Normal Plan', $planNames);
    }

    public function test_has_intro_pricing_false_excludes_intro_pricing_tiers(): void
    {
        $introPlan = $this->createActivePlan('Intro Plan');
        $normalPlan = $this->createActivePlan('Normal Plan');

        $this->createPricingTier([
            'plan_id' => $introPlan->id,
            'price' => 100.00,
            'intro_price' => 1.00,
            'intro_cycles' => 3,
            'duration_months' => 1,
        ]);

        $this->createPricingTier([
            'plan_id' => $normalPlan->id,
            'price' => 100.00,
            'sale_price' => 80.00,
            'duration_months' => 1,
        ]);

        $response = $this->getForSite('/api/crm/subscription-offers?has_intro_pricing=false');
        $data = json_decode($response->getContent(), true);

        $planNames = array_column($data['data'], 'plan_name');

        $this->assertContains('Normal Plan', $planNames);
        $this->assertNotContains('Intro Plan', $planNames);
    }

    public function test_has_active_discounts_true_returns_discounted_tiers(): void
    {
        $discountPlan = $this->createActivePlan('Discount Plan');
        $normalPlan = $this->createActivePlan('Normal Plan');

        $this->createPricingTier([
            'plan_id' => $discountPlan->id,
            'price' => 100.00,
            'sale_price' => 80.00,
            'duration_months' => 1,
        ]);

        $this->createPricingTier([
            'plan_id' => $normalPlan->id,
            'price' => 100.00,
            'sale_price' => null,
            'duration_months' => 1,
        ]);

        $response = $this->getForSite('/api/crm/subscription-offers?has_discount=true');
        $data = json_decode($response->getContent(), true);

        $planNames = array_column($data['data'], 'plan_name');

        $this->assertContains('Discount Plan', $planNames);
        $this->assertNotContains('Normal Plan', $planNames);
    }

    public function test_has_active_discounts_false_excludes_discounted_tiers(): void
    {
        $discountPlan = $this->createActivePlan('Discount Plan');
        $introPlan = $this->createActivePlan('Intro Only Plan');

        $this->createPricingTier([
            'plan_id' => $discountPlan->id,
            'price' => 100.00,
            'sale_price' => 80.00,
            'digital_price' => null,
            'digital_sale_price' => 0,
            'duration_months' => 1,
        ]);

        $this->createPricingTier([
            'plan_id' => $introPlan->id,
            'price' => 100.00,
            'sale_price' => null,
            'digital_price' => null,
            'digital_sale_price' => 0,
            'intro_price' => 1.00,
            'intro_cycles' => 3,
            'duration_months' => 1,
        ]);

        $response = $this->getForSite('/api/crm/subscription-offers?has_discount=false');
        $data = json_decode($response->getContent(), true);

        $this->assertResponseStatus(200, $response);

        $planNames = array_column($data['data'], 'plan_name');

        $this->assertContains('Intro Only Plan', $planNames);
        $this->assertNotContains('Discount Plan', $planNames);
    }

    public function test_plan_filter_returns_only_selected_plan(): void
    {
        $matchingPlan = $this->createActivePlan('Matching Plan');
        $otherPlan = $this->createActivePlan('Other Plan');

        $this->createPricingTier([
            'plan_id' => $matchingPlan->id,
            'price' => 100.00,
            'sale_price' => 80.00,
            'duration_months' => 1,
        ]);

        $this->createPricingTier([
            'plan_id' => $otherPlan->id,
            'price' => 100.00,
            'sale_price' => 80.00,
            'duration_months' => 1,
        ]);

        $response = $this->getForSite("/api/crm/subscription-offers?plan_id={$matchingPlan->id}");
        $data = json_decode($response->getContent(), true);

        $planNames = array_unique(array_column($data['data'], 'plan_name'));

        $this->assertSame(['Matching Plan'], array_values($planNames));
    }

    public function test_has_voucher_true_returns_only_plans_with_vouchers(): void
    {
        $voucherPlan = $this->createActivePlan('Voucher Plan');
        $normalPlan = $this->createActivePlan('Normal Plan');

        $voucher = $this->createVoucher([
            'applies_to_subscriptions' => true,
            'code' => 'SAVE20',
        ]);

        $this->attachVoucherToPlan($voucherPlan, $voucher);

        $this->createPricingTier([
            'plan_id' => $voucherPlan->id,
            'price' => 100.00,
            'duration_months' => 1,
        ]);

        $this->createPricingTier([
            'plan_id' => $normalPlan->id,
            'price' => 100.00,
            'sale_price' => 80.00,
            'duration_months' => 1,
        ]);

        $response = $this->getForSite('/api/crm/subscription-offers?has_voucher=true');
        $data = json_decode($response->getContent(), true);

        $planNames = array_column($data['data'], 'plan_name');

        $this->assertContains('Voucher Plan', $planNames);
        $this->assertNotContains('Normal Plan', $planNames);
    }

    public function test_has_voucher_false_excludes_plans_with_vouchers(): void
    {
        $voucherPlan = $this->createActivePlan('Voucher Plan');
        $normalPlan = $this->createActivePlan('Normal Plan');

        $voucher = $this->createVoucher([
            'applies_to_subscriptions' => true,
            'code' => 'SAVE20',
        ]);

        $this->attachVoucherToPlan($voucherPlan, $voucher);

        $this->createPricingTier([
            'plan_id' => $voucherPlan->id,
            'price' => 100.00,
            'duration_months' => 1,
        ]);

        $this->createPricingTier([
            'plan_id' => $normalPlan->id,
            'price' => 100.00,
            'sale_price' => 80.00,
            'duration_months' => 1,
        ]);

        $response = $this->getForSite('/api/crm/subscription-offers?has_voucher=false');
        $data = json_decode($response->getContent(), true);

        $planNames = array_column($data['data'], 'plan_name');

        $this->assertContains('Normal Plan', $planNames);
        $this->assertNotContains('Voucher Plan', $planNames);
    }

    public function test_voucher_offer_type_filter_returns_only_voucher_offers(): void
    {
        $plan = $this->createActivePlan('Voucher Plan');

        $voucher = $this->createVoucher([
            'applies_to_subscriptions' => true,
            'code' => 'SAVE20',
        ]);

        $this->attachVoucherToPlan($plan, $voucher);

        $this->createPricingTier([
            'plan_id' => $plan->id,
            'price' => 100.00,
            'sale_price' => 80.00,
            'duration_months' => 1,
        ]);

        $response = $this->getForSite('/api/crm/subscription-offers?offer_type=voucher');
        $data = json_decode($response->getContent(), true);

        $types = array_unique(array_column($data['data'], 'offer_type'));

        $this->assertSame(['voucher'], array_values($types));
    }


    // =========================================================================
    // Factory helpers
    // =========================================================================

    private function createActivePlan(string $name = 'Test Plan'): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'site_id'    => $this->siteId,
            'name'       => $name,
            'slug'       => strtolower(str_replace(' ', '-', $name)) . '-' . uniqid(),
            'price'      => 100.00,
            'currency'   => 'GBP',
            'is_active'  => true,
            'is_featured' => false,
            'sort_order' => 1,
            'plan_type'  => 'recurring',
        ]);
    }

    private function createPrintDiscountTier(SubscriptionPlan $plan, float $price, float $salePrice): SubscriptionPlanPricing
    {
        return SubscriptionPlanPricing::create([
            'plan_id'    => $plan->id,
            'site_id'    => $this->siteId,
            'price'      => $price,
            'sale_price' => $salePrice,
            'is_active'  => true,
            'is_default' => true,
            'sort_order' => 1,
            'currency'   => 'GBP',
        ]);
    }

    private function createDigitalDiscountTier(SubscriptionPlan $plan, float $digitalPrice, float $digitalSalePrice): SubscriptionPlanPricing
    {
        return SubscriptionPlanPricing::create([
            'plan_id'            => $plan->id,
            'site_id'            => $this->siteId,
            'price'              => 100.00,
            'digital_price'      => $digitalPrice,
            'digital_sale_price' => $digitalSalePrice,
            'is_active'          => true,
            'is_default'         => true,
            'sort_order'         => 1,
            'currency'           => 'GBP',
        ]);
    }

    private function createIntroPricingTier(SubscriptionPlan $plan, float $price, float $introPrice, int $introCycles): SubscriptionPlanPricing
    {
        return SubscriptionPlanPricing::create([
            'plan_id'      => $plan->id,
            'site_id'      => $this->siteId,
            'price'        => $price,
            'intro_price'  => $introPrice,
            'intro_cycles' => $introCycles,
            'is_active'    => true,
            'is_default'   => true,
            'sort_order'   => 1,
            'currency'     => 'GBP',
        ]);
    }

    private function createBasicTier(SubscriptionPlan $plan, float $price): SubscriptionPlanPricing
    {
        return SubscriptionPlanPricing::create([
            'plan_id'    => $plan->id,
            'site_id'    => $this->siteId,
            'price'      => $price,
            'is_active'  => true,
            'is_default' => true,
            'sort_order' => 1,
            'currency'   => 'GBP',
        ]);
    }

    private function attachVoucherToPlan(SubscriptionPlan $plan, Voucher $voucher): void
    {
        VoucherSubscriptionPlan::create([
            'voucher_id' => $voucher->id,
            'subscription_plan_id' => $plan->id
        ]);
    }
}