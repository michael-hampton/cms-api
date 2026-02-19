<?php

namespace App\Tests\Unit\Services\Billing;

use App\Models\Member;
use App\Services\Billing\TaxCalculatorService;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class TaxCalculatorServiceTest extends TestCase
{
    private $service;

    public function testCalculateOrderTaxWithValidRate(): void
    {
        $result = $this->service->calculateOrderTax(
            10000, // $100.00
            1000,  // $10.00
            'US',
            'CA',
            '90210'
        );

        $this->assertEquals(798, $result->taxCents); // 7.25% of $110
        $this->assertEquals(0.0725, $result->rate);
        $this->assertEquals('California', $result->jurisdiction);
        $this->assertEquals(11000, $result->taxableAmountCents);
    }

    public function testCalculateOrderTaxWithoutShipping(): void
    {
        $result = $this->service->calculateOrderTax(
            10000, // $100.00
            1000,  // $10.00
            'US',
            'FL',
            null
        );

        $this->assertEquals(600, $result->taxCents); // 6% of $100 (shipping not included)
        $this->assertEquals(10000, $result->taxableAmountCents);
    }

    public function testCalculateOrderTaxWithNoRate(): void
    {
        $result = $this->service->calculateOrderTax(
            10000,
            1000,
            'XX',
            null,
            null
        );

        $this->assertEquals(0, $result->taxCents);
        $this->assertEquals(0.00, $result->rate);
        $this->assertNull($result->jurisdiction);
    }

    public function testCalculateOrderTaxWithTaxExemptMember(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->tax_exempt = true;

        $result = $this->service->calculateOrderTax(
            10000,
            1000,
            'US',
            'CA',
            null,
            $member
        );

        $this->assertEquals(0, $result->taxCents);
        $this->assertTrue($result->exempt);
        $this->assertEquals('California', $result->jurisdiction);
    }

    public function testCalculateOrderTaxFallsBackToCountryDefault(): void
    {
        $result = $this->service->calculateOrderTax(
            10000,
            1000,
            'US',
            'ZZ', // Invalid state
            null
        );

        // Should use US DEFAULT rate (7%)
        $this->assertEquals(770, $result->taxCents); // 7% of $110
        $this->assertEquals(0.07, $result->rate);
        $this->assertEquals('United States', $result->jurisdiction);
    }

    public function testCalculateOrderTaxCanadaHST(): void
    {
        $result = $this->service->calculateOrderTax(
            10000, // $100.00
            1000,  // $10.00
            'CA',
            'ON',
            null
        );

        $this->assertEquals(1430, $result->taxCents); // 13% of $110
        $this->assertEquals(0.13, $result->rate);
        $this->assertEquals('Ontario (HST)', $result->jurisdiction);
    }

    public function testCalculateOrderTaxUKVAT(): void
    {
        $result = $this->service->calculateOrderTax(
            10000,
            1000,
            'GB',
            null,
            null
        );

        $this->assertEquals(2200, $result->taxCents); // 20% of $110
        $this->assertEquals(0.20, $result->rate);
        $this->assertEquals('United Kingdom (VAT)', $result->jurisdiction);
    }

    public function testCalculateOrderTaxAustraliaGST(): void
    {
        $result = $this->service->calculateOrderTax(
            10000,
            1000,
            'AU',
            null,
            null
        );

        $this->assertEquals(1100, $result->taxCents); // 10% of $110
        $this->assertEquals(0.10, $result->rate);
        $this->assertEquals('Australia (GST)', $result->jurisdiction);
    }

    public function testDistributeTaxToItemsProportionally(): void
    {
        $items = [
            ['subtotal_cents' => 5000, 'shipping_cents' => 500],  // $55 (50%)
            ['subtotal_cents' => 3000, 'shipping_cents' => 300],  // $33 (30%)
            ['subtotal_cents' => 2000, 'shipping_cents' => 200],  // $22 (20%)
        ];

        $result = $this->service->distributeTaxToItems($items, 1000); // $10 tax

        $this->assertEquals(500, $result[0]['tax_cents']); // ~50%
        $this->assertEquals(300, $result[1]['tax_cents']); // ~30%
        $this->assertEquals(200, $result[2]['tax_cents']); // ~20% (gets rounding adjustment)

        // Verify total
        $totalTax = array_sum(array_column($result, 'tax_cents'));
        $this->assertEquals(1000, $totalTax);
    }

    public function testDistributeTaxWithZeroBase(): void
    {
        $items = [
            ['subtotal_cents' => 0, 'shipping_cents' => 0],
        ];

        $result = $this->service->distributeTaxToItems($items, 1000);

        $this->assertEquals(0, $result[0]['tax_cents']);
    }

    public function testDistributeTaxWithZeroTax(): void
    {
        $items = [
            ['subtotal_cents' => 5000, 'shipping_cents' => 500],
        ];

        $result = $this->service->distributeTaxToItems($items, 0);

        $this->assertEquals(0, $result[0]['tax_cents']);
    }

    public function testCalculateCartTax(): void
    {
        $items = [
            ['subtotal_cents' => 5000, 'shipping_cents' => 500],
            ['subtotal_cents' => 3000, 'shipping_cents' => 300],
        ];

        $result = $this->service->calculateCartTax($items, 'US', 'CA');

        $this->assertEquals(638, $result->taxCents); // 7.25% of $88
    }

    public function testCalculateCartTaxWithMissingCents(): void
    {
        $items = [
            ['subtotal_cents' => 5000],  // No shipping
            ['shipping_cents' => 300],   // No subtotal
        ];

        $result = $this->service->calculateCartTax($items, 'US', 'TX');

        // Should handle missing fields gracefully
        $this->assertIsFloat($result->taxCents);
    }

    public function testValidateTaxExemptionValid(): void
    {
        $member = m::mock(Member::class);

        $result = $this->service->validateTaxExemption(
            $member,
            'EXEMPT123456',
            'CA'
        );

        $this->assertTrue($result);
    }

    public function testValidateTaxExemptionInvalid(): void
    {
        $member = m::mock(Member::class);

        $result = $this->service->validateTaxExemption(
            $member,
            'SHORT',
            'CA'
        );

        $this->assertFalse($result);
    }

    public function testValidateTaxExemptionEmpty(): void
    {
        $member = m::mock(Member::class);

        $result = $this->service->validateTaxExemption(
            $member,
            '',
            'CA'
        );

        $this->assertFalse($result);
    }

    public function testGetSupportedCountries(): void
    {
        $countries = $this->service->getSupportedCountries();

        $this->assertContains('US', $countries);
        $this->assertContains('CA', $countries);
        $this->assertContains('GB', $countries);
        $this->assertContains('AU', $countries);
    }

    public function testGetStatesForCountry(): void
    {
        $states = $this->service->getStatesForCountry('US');

        $this->assertContains('CA', $states);
        $this->assertContains('NY', $states);
        $this->assertContains('TX', $states);
        $this->assertNotContains('DEFAULT', $states);
    }

    public function testGetStatesForCountryWithNoStates(): void
    {
        $states = $this->service->getStatesForCountry('GB');

        $this->assertEmpty($states);
    }

    public function testGetStatesForInvalidCountry(): void
    {
        $states = $this->service->getStatesForCountry('XX');

        $this->assertEmpty($states);
    }

    public function testGetTaxRateInfo(): void
    {
        $info = $this->service->getTaxRateInfo('US', 'CA');

        $this->assertEquals(0.0725, $info->rate);
        $this->assertEquals(7.25, $info->ratePercentage);
        $this->assertEquals('California', $info->jurisdiction);
        $this->assertTrue($info->includesShipping);
    }

    public function testGetTaxRateInfoWithDefault(): void
    {
        $info = $this->service->getTaxRateInfo('US', 'ZZ');

        $this->assertEquals(0.07, $info->rate);
        $this->assertEquals(7.0, $info->ratePercentage);
        $this->assertEquals('United States', $info->jurisdiction);
    }

    public function testGetTaxRateInfoInvalidCountry(): void
    {
        $info = $this->service->getTaxRateInfo('XX');

        $this->assertNull($info);
    }

    public function testIsTaxExemptForNonProfit(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->tax_exempt = false;
        $member->organization_type = 'non_profit';

        $result = $this->service->calculateOrderTax(
            10000,
            1000,
            'US',
            'CA',
            null,
            $member
        );

        $this->assertEquals(0, $result->taxCents);
        $this->assertTrue($result->exempt);
    }

    public function testIsTaxExemptForEducational(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->tax_exempt = false;
        $member->organization_type = 'educational';

        $result = $this->service->calculateOrderTax(
            10000,
            1000,
            'US',
            'NY',
            null,
            $member
        );

        $this->assertEquals(0, $result->taxCents);
        $this->assertTrue($result->exempt);
    }

    public function testMultipleEUCountries(): void
    {
        $countries = [
            'DE' => 0.19,
            'FR' => 0.20,
            'IT' => 0.22,
            'ES' => 0.21,
            'NL' => 0.21
        ];

        foreach ($countries as $country => $expectedRate) {
            $result = $this->service->calculateOrderTax(
                10000,
                1000,
                $country,
                null,
                null
            );

            $this->assertEquals($expectedRate, $result->rate);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TaxCalculatorService();
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}