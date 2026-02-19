<?php

namespace App\Tests\Unit\Services\Adverts\Boost;

use App\Enums\Boost\BoostableType;
use App\Enums\Boost\BoostContext;
use App\Services\Adverts\Boost\BoostPricingService;
use PHPUnit\Framework\TestCase;

class BoostPricingServiceTest extends TestCase
{
    private BoostPricingService $service;

    public function test_calculates_listing_product_price_for_seven_days(): void
    {
        $price = $this->service->calculate(
            BoostableType::Product->value,
            BoostContext::Listing->value,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-08'),
        );

        // 5.00 * 7 * 1.0 = 35.00
        $this->assertEquals(35.00, $price);
    }

    public function test_calculates_deals_offer_price_for_three_days(): void
    {
        $price = $this->service->calculate(
            BoostableType::Offer->value,
            BoostContext::Deals->value,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-04'),
        );

        // 8.00 * 3 * 1.2 = 28.80
        $this->assertEquals(28.80, $price);
    }

    public function test_applies_campaign_discount_percent(): void
    {
        $price = $this->service->calculate(
            BoostableType::Product->value,
            BoostContext::Listing->value,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-08'),
            ['discount_percent' => 50]
        );

        // 35.00 * 0.50 = 17.50
        $this->assertEquals(17.50, $price);
    }

    public function test_applies_campaign_fixed_price_override(): void
    {
        $price = $this->service->calculate(
            BoostableType::Product->value,
            BoostContext::Listing->value,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-08'),
            ['fixed_price' => 9.99]
        );

        $this->assertEquals(9.99, $price);
    }

    public function test_throws_on_zero_duration(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->calculate(
            BoostableType::Product->value,
            BoostContext::Listing->value,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-01'),
        );
    }

    public function test_throws_on_invalid_context(): void
    {
        $this->expectException(\ValueError::class);

        $this->service->calculate(
            BoostableType::Product->value,
            'homepage_hero',
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-08'),
        );
    }

    public function test_recommendations_context_cheaper_than_listing(): void
    {
        $listing = $this->service->calculate(
            BoostableType::Product->value,
            BoostContext::Listing->value,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-08'),
        );

        $recommendations = $this->service->calculate(
            BoostableType::Product->value,
            BoostContext::Recommendations->value,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-08'),
        );

        $this->assertLessThan($listing, $recommendations);
    }

    protected function setUp(): void
    {
        $this->service = new BoostPricingService();
    }
}