<?php

namespace App\Tests\Unit\Services\Shopping;

use App\Services\Shopping\MerchantShippingService;
use PHPUnit\Framework\TestCase;

class MerchantShippingServiceTest extends TestCase
{
    // --- calculatePerGroup ---

    public function testCalculatePerGroupReturnsShippingPerMerchant(): void
    {
        $service = new MerchantShippingService();

        $groups = [
            100 => [
                'merchant_id' => 100,
                'items' => [['price' => 30.00, 'quantity' => 1]],  // subtotal 30 < 100 threshold
            ],
            200 => [
                'merchant_id' => 200,
                'items' => [['price' => 150.00, 'quantity' => 1]],  // subtotal 150 ≥ 100 → free
            ],
        ];

        $result = $service->calculatePerGroup($groups, 'US');

        $this->assertEquals(10.00, $result[100]);  // US rate
        $this->assertEquals(0.00, $result[200]);  // free shipping
    }

    public function testCalculatePerGroupUsesCountryRate(): void
    {
        $service = new MerchantShippingService();

        $groups = [
            100 => [
                'merchant_id' => 100,
                'items' => [['price' => 50.00, 'quantity' => 1]],
            ],
        ];

        $this->assertEquals(15.00, $service->calculatePerGroup($groups, 'CA')[100]);
        $this->assertEquals(12.00, $service->calculatePerGroup($groups, 'GB')[100]);
        $this->assertEquals(20.00, $service->calculatePerGroup($groups, 'AU')[100]);
    }

    public function testCalculatePerGroupUsesDefaultRateForUnknownCountry(): void
    {
        $service = new MerchantShippingService();

        $groups = [
            100 => [
                'merchant_id' => 100,
                'items' => [['price' => 50.00, 'quantity' => 1]],
            ],
        ];

        $result = $service->calculatePerGroup($groups, 'ZZ');
        $this->assertEquals(10.00, $result[100]);
    }

    public function testCalculatePerGroupSystemBucketTreatedAsSingleShipment(): void
    {
        $service = new MerchantShippingService();

        $groups = [
            '__system__' => [
                'merchant_id' => null,
                'items' => [
                    ['price' => 20.00, 'quantity' => 1],
                    ['price' => 30.00, 'quantity' => 1],  // subtotal 50 < 100
                ],
            ],
        ];

        $result = $service->calculatePerGroup($groups, 'US');
        $this->assertEquals(10.00, $result['__system__']);
    }

    public function testCalculatePerGroupSystemBucketFreeShipping(): void
    {
        $service = new MerchantShippingService();

        $groups = [
            '__system__' => [
                'merchant_id' => null,
                'items' => [
                    ['price' => 60.00, 'quantity' => 1],
                    ['price' => 50.00, 'quantity' => 1],  // subtotal 110 ≥ 100
                ],
            ],
        ];

        $result = $service->calculatePerGroup($groups, 'US');
        $this->assertEquals(0.00, $result['__system__']);
    }

    public function testCalculatePerGroupEmptyGroupsReturnsEmpty(): void
    {
        $service = new MerchantShippingService();
        $result = $service->calculatePerGroup([], 'US');
        $this->assertCount(0, $result);
    }

    public function testCalculatePerGroupMultipleMerchantsMixedShipping(): void
    {
        $service = new MerchantShippingService();

        $groups = [
            10 => [
                'merchant_id' => 10,
                'items' => [['price' => 200.00, 'quantity' => 1]],  // free
            ],
            20 => [
                'merchant_id' => 20,
                'items' => [['price' => 40.00, 'quantity' => 2]],   // 80 < 100 → charged
            ],
            30 => [
                'merchant_id' => 30,
                'items' => [['price' => 10.00, 'quantity' => 1]],   // 10 < 100 → charged
            ],
        ];

        $result = $service->calculatePerGroup($groups, 'GB');

        $this->assertEquals(0.00, $result[10]);   // free
        $this->assertEquals(12.00, $result[20]);   // GB rate
        $this->assertEquals(12.00, $result[30]);   // GB rate
    }

    public function testCalculatePerGroupRespectsFreeShippingThresholdExactly(): void
    {
        $service = new MerchantShippingService();

        $groups = [
            100 => [
                'merchant_id' => 100,
                'items' => [['price' => 100.00, 'quantity' => 1]],  // exactly 100
            ],
        ];

        $result = $service->calculatePerGroup($groups, 'US');
        $this->assertEquals(0.00, $result[100]);
    }

    public function testCalculatePerGroupJustBelowThreshold(): void
    {
        $service = new MerchantShippingService();

        $groups = [
            100 => [
                'merchant_id' => 100,
                'items' => [['price' => 99.99, 'quantity' => 1]],
            ],
        ];

        $result = $service->calculatePerGroup($groups, 'US');
        $this->assertEquals(10.00, $result[100]);
    }

    // --- consolidation config ---

    public function testIsConsolidationEnabledReturnsFalseByDefault(): void
    {
        $service = new MerchantShippingService();
        $this->assertFalse($service->isConsolidationEnabled());
    }

    public function testIsConsolidationEnabledReturnsTrueWhenConfigured(): void
    {
        $service = new MerchantShippingService([
            'free_shipping_threshold' => 100.00,
            'default_rate' => 10.00,
            'consolidate_shipping' => true,
            'rates_by_country' => ['US' => 10.00],
        ]);

        $this->assertTrue($service->isConsolidationEnabled());
    }

    public function testGetConfigReturnsFullConfig(): void
    {
        $config = [
            'free_shipping_threshold' => 50.00,
            'default_rate' => 7.50,
            'consolidate_shipping' => true,
            'rates_by_country' => ['US' => 7.50, 'GB' => 9.00],
        ];

        $service = new MerchantShippingService($config);
        $this->assertEquals($config, $service->getConfig());
    }

    // --- consolidation does not cross merchant boundaries ---

    public function testConsolidationDoesNotMergeAcrossMerchants(): void
    {
        // Even with consolidation on, each merchant group is still independent
        $service = new MerchantShippingService([
            'free_shipping_threshold' => 100.00,
            'default_rate' => 10.00,
            'consolidate_shipping' => true,
            'rates_by_country' => ['US' => 10.00],
        ]);

        $groups = [
            100 => [
                'merchant_id' => 100,
                'items' => [['price' => 30.00, 'quantity' => 1]],
            ],
            200 => [
                'merchant_id' => 200,
                'items' => [['price' => 40.00, 'quantity' => 1]],
            ],
        ];

        $result = $service->calculatePerGroup($groups, 'US');

        // Neither group hits the threshold alone, both are charged separately
        $this->assertEquals(10.00, $result[100]);
        $this->assertEquals(10.00, $result[200]);
    }

    public function testConsolidationWithinMerchantStillProducesOneShipment(): void
    {
        $service = new MerchantShippingService([
            'free_shipping_threshold' => 100.00,
            'default_rate' => 10.00,
            'consolidate_shipping' => true,
            'rates_by_country' => ['US' => 10.00],
        ]);

        // Multiple items from same merchant, consolidated = one shipment calculation
        $groups = [
            100 => [
                'merchant_id' => 100,
                'items' => [
                    ['price' => 60.00, 'quantity' => 1],
                    ['price' => 50.00, 'quantity' => 1],  // combined 110 ≥ 100
                ],
            ],
        ];

        $result = $service->calculatePerGroup($groups, 'US');
        $this->assertEquals(0.00, $result[100]);  // free because combined subtotal ≥ threshold
    }
}