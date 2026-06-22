<?php

namespace App\Tests\Unit\Frontend;

use PHPUnit\Framework\TestCase;

final class SubscriptionAvailabilityViewContractTest extends TestCase
{
    public function test_one_time_listing_uses_available_formats_and_safe_tier_access(): void
    {
        $source = $this->read('views/subscriptions/onetime/index.php');

        self::assertStringContainsString('getAvailableDeliveryOptions()', $source);
        self::assertStringContainsString("\$tierPrice['tier']->id ?? null", $source);
        self::assertStringContainsString("in_array('print', \$availableDeliveryOptions, true)", $source);
        self::assertStringContainsString('Out of Stock', $source);
    }

    public function test_one_time_detail_hides_unavailable_formats_and_disables_purchase(): void
    {
        $source = $this->read('views/subscriptions/onetime/show.php');

        self::assertStringContainsString('getAvailableDeliveryOptions()', $source);
        self::assertStringNotContainsString('$plan->getDeliveryOptions()', $source);
        self::assertStringContainsString("in_array('digital', \$deliveryOptions, true)", $source);
        self::assertStringContainsString("in_array('print', \$deliveryOptions, true)", $source);
        self::assertStringContainsString("\$isOutOfStock ? 'disabled' : ''", $source);
    }

    public function test_subscription_modal_does_not_fall_back_to_plan_price_when_out_of_stock(): void
    {
        $source = $this->read('views/components/subscription-modal.php');

        self::assertStringContainsString('getAvailableDeliveryOptions()', $source);
        self::assertStringContainsString('data-plan-out-of-stock', $source);
        self::assertStringContainsString("'disabled' => \$isOutOfStock", $source);
        self::assertStringNotContainsString("getLowestEffectivePrice()['min'] ?? \$plan->price", $source);
    }

    public function test_deals_listing_uses_availability_aware_price_and_formats(): void
    {
        $source = $this->read('views/subscriptions/deals/index.php');

        self::assertStringContainsString('getLowestEffectivePrice()', $source);
        self::assertStringContainsString('getAvailableDeliveryOptions()', $source);
        self::assertStringContainsString('data-pricing-tier-id', $source);
        self::assertStringContainsString('Out of Stock', $source);
    }

    private function read(string $relativePath): string
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/' . $relativePath);
        self::assertNotFalse($source);

        return $source;
    }
}
