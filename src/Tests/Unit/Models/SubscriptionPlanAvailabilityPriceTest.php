<?php

namespace App\Tests\Unit\Models;

use App\Enums\Subscriptions\IssueDeliveryStatus;
use App\Enums\Subscriptions\SubscriptionType;
use App\Models\IssueDelivery;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanPricing;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class SubscriptionPlanAvailabilityPriceTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_lowest_price_uses_cheapest_available_format_when_both_are_available(): void
    {
        $plan = $this->createHybridPlan();

        $this->createPricingTier($plan, 7.99, 4.99);
        $this->createIssue($plan, 10);

        $price = $plan->getLowestEffectivePrice();

        $this->assertFalse($price['is_out_of_stock']);
        $this->assertSame(4.99, $price['min']);
        $this->assertSame(SubscriptionType::DIGITAL->value, $price['delivery_type']);
        $this->assertTrue($price['show_from_prefix']);
    }

    public function test_lowest_price_uses_print_price_when_digital_is_out_of_stock(): void
    {
        $plan = $this->createHybridPlan();

        $this->createPricingTier($plan, 7.99, 4.99);
        $this->createIssue($plan, 10, ['digital_in_stock' => false]);

        $price = $plan->getLowestEffectivePrice();

        $this->assertFalse($price['is_out_of_stock']);
        $this->assertSame(7.99, $price['min']);
        $this->assertSame(SubscriptionType::PRINTED->value, $price['delivery_type']);
        $this->assertFalse($price['show_from_prefix']);
    }

    public function test_lowest_price_uses_digital_price_when_print_is_out_of_stock(): void
    {
        $plan = $this->createHybridPlan();

        $this->createPricingTier($plan, 7.99, 4.99);
        $this->createIssue($plan, 0, ['digital_in_stock' => true]);

        $price = $plan->getLowestEffectivePrice();

        $this->assertFalse($price['is_out_of_stock']);
        $this->assertSame(4.99, $price['min']);
        $this->assertSame(SubscriptionType::DIGITAL->value, $price['delivery_type']);
        $this->assertFalse($price['show_from_prefix']);
    }

    public function test_lowest_price_marks_plan_out_of_stock_when_no_format_is_available(): void
    {
        $plan = $this->createHybridPlan();

        $this->createPricingTier($plan, 7.99, 4.99);
        $this->createIssue($plan, 0, ['digital_in_stock' => false]);

        $price = $plan->getLowestEffectivePrice();

        $this->assertTrue($price['is_out_of_stock']);
        $this->assertNull($price['min']);
        $this->assertNull($price['delivery_type']);
        $this->assertFalse($price['show_from_prefix']);
    }

    public function test_available_delivery_options_only_include_in_stock_formats(): void
    {
        $plan = $this->createHybridPlan();

        $this->createIssue($plan, 5, ['digital_in_stock' => false]);

        $this->assertSame(
            [SubscriptionType::PRINTED->value],
            $plan->getAvailableDeliveryOptions()
        );
    }

    private function createHybridPlan(): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Magazine Direct Test Plan',
            'slug' => 'magazine-direct-test-' . uniqid(),
            'price' => 7.99,
            'currency' => 'GBP',
            'billing_period' => 'lifetime',
            'plan_type' => 'onetime',
            'digital_download_url' => 'digital-test-file',
            'print_shipping_required' => true,
            'is_active' => true,
        ]);
    }

    private function createPricingTier(SubscriptionPlan $plan, float $printPrice, float $digitalPrice): SubscriptionPlanPricing
    {
        return SubscriptionPlanPricing::create([
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'price' => $printPrice,
            'digital_price' => $digitalPrice,
            'currency' => 'GBP',
            'label' => 'Single Issue',
            'period_description' => 'One issue',
            'issue_count' => 1,
            'duration_months' => 0,
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    private function createIssue(SubscriptionPlan $plan, int $stockQuantity, array $metadata = []): IssueDelivery
    {
        return IssueDelivery::create([
            'site_id' => $this->siteId,
            'subscription_plan_id' => $plan->id,
            'issue_number' => 1,
            'issue_title' => 'Availability Test Issue',
            'on_sale_date' => now_datetime()->modify('-1 day'),
            'status' => IssueDeliveryStatus::ACTIVE->value,
            'stock_quantity' => $stockQuantity,
            'metadata' => $metadata,
        ]);
    }
}
