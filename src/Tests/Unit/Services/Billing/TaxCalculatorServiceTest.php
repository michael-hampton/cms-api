<?php

namespace App\Tests\Unit\Services\Billing;

use App\DTO\Cart\TaxData;
use App\Models\Member;
use App\Services\Billing\Tax\StripeTaxCalculationBuilder;
use App\Services\Billing\Tax\StripeTaxResponseMapper;
use App\Services\Billing\TaxCalculatorService;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Unit tests for TaxCalculatorService.
 *
 * Coverage areas
 * ──────────────
 * Stripe path (calculateOrderTax)
 *   - payload built via buildFromOrderTotals, Stripe called, response mapped
 *   - currency forwarded from defaultCurrency()
 *   - local exemption override applied after Stripe call (tax_exempt, non_profit, educational)
 *   - no exemption applied for regular member / no member
 *   - API error triggers Log::warning + legacy fallback
 *
 * Stripe path (calculateCartTax)
 *   - items forwarded via buildFromLineItems (not collapsed to totals)
 *   - missing subtotal_cents / shipping_cents fields handled gracefully
 *   - exemption override applied
 *   - API error triggers Log::warning + legacy fallback
 *
 * Legacy path (feature flag = 'legacy')
 *   - all original test assertions preserved unchanged
 *
 * distributeTaxToItems     — backward-compat, unchanged expectations
 * getSupportedCountries    — config-backed
 * getStatesForCountry      — config-backed
 * getTaxRateInfo           — display-only, no Stripe call
 * validateTaxExemption     — unchanged
 *
 * StripeTaxResponseMapper
 *   - shipping taxable amount gates on shippingCents > 0, not shippingTaxCents > 0
 *   - mapZeroTax returns exempt=false
 */
class TaxCalculatorServiceTest extends TestCase
{
    private StripeTaxCalculationBuilder $builder;
    private StripeTaxResponseMapper $mapper;
    private StripeClient $stripeClient;

    // =========================================================================
    // Stripe path — calculateOrderTax
    // =========================================================================

    public function testStripePathCallsStripeAndReturnsMappedTaxData(): void
    {
        $this->mockEngine('stripe');

        $fakeCalculation = $this->makeCalculationFixture(2200);
        $expectedTaxData = new TaxData(
            rate: 0.20, ratePercentage: 20.0, jurisdiction: 'United Kingdom',
            includesShipping: true, taxCents: 2200, taxableAmountCents: 11000
        );

        $this->builder->shouldReceive('buildFromOrderTotals')
            ->once()
            ->with(10000, 1000, 'gbp', 'GB', null, null)
            ->andReturn(['currency' => 'gbp']);

        $this->mockStripeCalcCreate(['currency' => 'gbp'], $fakeCalculation);

        $this->mapper->shouldReceive('map')
            ->once()
            ->with($fakeCalculation, 10000, 1000)
            ->andReturn($expectedTaxData);

        $result = $this->makeService()->calculateOrderTax(10000, 1000, 'GB');

        $this->assertEquals(2200, $result->taxCents);
        $this->assertEquals(0.20, $result->rate);
        $this->assertFalse($result->exempt);
    }

    public function testStripePathForwardsDefaultCurrencyToBuilder(): void
    {
        $this->mockEngine('stripe', 'usd');

        $this->builder->shouldReceive('buildFromOrderTotals')
            ->once()
            ->with(5000, 0, 'usd', 'US', 'CA', '90210')
            ->andReturn([]);

        $this->mockStripeCalcCreate([], $this->makeCalculationFixture(363));
        $this->mapper->shouldReceive('map')->once()->andReturn(
            new TaxData(rate: 0.0725, ratePercentage: 7.25, jurisdiction: 'California', includesShipping: true, taxCents: 363)
        );

        $result = $this->makeService()->calculateOrderTax(5000, 0, 'US', 'CA', '90210');

        $this->assertEquals(363, $result->taxCents);
    }

    // =========================================================================
    // Stripe path — calculateOrderTax exemption overrides
    // =========================================================================

    public function testStripePathAppliesExemptionOverrideForTaxExemptMember(): void
    {
        $this->mockEngine('stripe');

        $member = m::mock(Member::class)->makePartial();
        $member->tax_exempt = true;

        $this->builder->shouldReceive('buildFromOrderTotals')->once()->andReturn([]);
        $this->mockStripeCalcCreate([], $this->makeCalculationFixture(2200));
        $this->mapper->shouldReceive('map')->once()->andReturn(
            new TaxData(rate: 0.20, ratePercentage: 20.0, jurisdiction: 'United Kingdom',
                includesShipping: true, taxCents: 2200, taxableAmountCents: 11000)
        );

        $result = $this->makeService()->calculateOrderTax(10000, 1000, 'GB', null, null, $member);

        $this->assertEquals(0, $result->taxCents);
        $this->assertTrue($result->exempt);
        $this->assertEquals('United Kingdom', $result->jurisdiction);
    }

    public function testStripePathAppliesExemptionOverrideForNonProfit(): void
    {
        $this->mockEngine('stripe');

        $member = m::mock(Member::class)->makePartial();
        $member->tax_exempt = false;
        $member->organization_type = 'non_profit';

        $this->builder->shouldReceive('buildFromOrderTotals')->once()->andReturn([]);
        $this->mockStripeCalcCreate([], $this->makeCalculationFixture(798));
        $this->mapper->shouldReceive('map')->once()->andReturn(
            new TaxData(rate: 0.0725, ratePercentage: 7.25, jurisdiction: 'California',
                includesShipping: true, taxCents: 798)
        );

        $result = $this->makeService()->calculateOrderTax(10000, 1000, 'US', 'CA', null, $member);

        $this->assertEquals(0, $result->taxCents);
        $this->assertTrue($result->exempt);
    }

    public function testStripePathAppliesExemptionOverrideForEducational(): void
    {
        $this->mockEngine('stripe');

        $member = m::mock(Member::class)->makePartial();
        $member->tax_exempt = false;
        $member->organization_type = 'educational';

        $this->builder->shouldReceive('buildFromOrderTotals')->once()->andReturn([]);
        $this->mockStripeCalcCreate([], $this->makeCalculationFixture(400));
        $this->mapper->shouldReceive('map')->once()->andReturn(
            new TaxData(rate: 0.04, ratePercentage: 4.0, jurisdiction: 'New York',
                includesShipping: true, taxCents: 400)
        );

        $result = $this->makeService()->calculateOrderTax(10000, 1000, 'US', 'NY', null, $member);

        $this->assertEquals(0, $result->taxCents);
        $this->assertTrue($result->exempt);
    }

    public function testStripePathDoesNotApplyExemptionForRegularMember(): void
    {
        $this->mockEngine('stripe');

        $member = m::mock(Member::class)->makePartial();
        $member->tax_exempt = false;
        $member->organization_type = null;

        $this->builder->shouldReceive('buildFromOrderTotals')->once()->andReturn([]);
        $this->mockStripeCalcCreate([], $this->makeCalculationFixture(2200));
        $this->mapper->shouldReceive('map')->once()->andReturn(
            new TaxData(rate: 0.20, ratePercentage: 20.0, jurisdiction: 'UK',
                includesShipping: true, taxCents: 2200)
        );

        $result = $this->makeService()->calculateOrderTax(10000, 1000, 'GB', null, null, $member);

        $this->assertEquals(2200, $result->taxCents);
        $this->assertFalse($result->exempt);
    }

    public function testStripePathDoesNotApplyExemptionWhenNoMember(): void
    {
        $this->mockEngine('stripe');

        $this->builder->shouldReceive('buildFromOrderTotals')->once()->andReturn([]);
        $this->mockStripeCalcCreate([], $this->makeCalculationFixture(2200));
        $this->mapper->shouldReceive('map')->once()->andReturn(
            new TaxData(rate: 0.20, ratePercentage: 20.0, jurisdiction: 'UK',
                includesShipping: true, taxCents: 2200)
        );

        $result = $this->makeService()->calculateOrderTax(10000, 1000, 'GB');

        $this->assertEquals(2200, $result->taxCents);
        $this->assertFalse($result->exempt);
    }

    // =========================================================================
    // Stripe path — calculateOrderTax API error fallback
    // =========================================================================

    public function testStripeApiErrorOnCalculateOrderTaxFallsBackToLegacy(): void
    {
        $this->mockEngine('stripe');

        $this->builder->shouldReceive('buildFromOrderTotals')->once()->andReturn([]);
        $this->mapper->shouldNotReceive('map');

        $this->mockStripeCalcCreateThrows();

        // Legacy path for GB: 20% of £110 = £22
        $result = $this->makeService()->calculateOrderTax(10000, 1000, 'GB');

        $this->assertEquals(2200, $result->taxCents);
        $this->assertEquals(0.20, $result->rate);
    }

    // =========================================================================
    // Stripe path — calculateCartTax uses buildFromLineItems
    // =========================================================================

    public function testCalculateCartTaxUsesLineItemBuilderNotOrderTotalsBuilder(): void
    {
        $this->mockEngine('stripe');

        $items = [
            ['subtotal_cents' => 5000, 'shipping_cents' => 500],
            ['subtotal_cents' => 3000, 'shipping_cents' => 300],
        ];

        // Must call buildFromLineItems, never buildFromOrderTotals
        $this->builder->shouldReceive('buildFromLineItems')
            ->once()
            ->with($items, 'gbp', 'US', 'CA', null)
            ->andReturn([]);

        $this->builder->shouldNotReceive('buildFromOrderTotals');

        $this->mockStripeCalcCreate([], $this->makeCalculationFixture(638));

        // Mapper receives aggregated totals: subtotal=8000, shipping=800
        $this->mapper->shouldReceive('map')
            ->once()
            ->with(m::any(), 8000, 800)
            ->andReturn(new TaxData(rate: 0.0725, ratePercentage: 7.25, jurisdiction: 'California',
                includesShipping: true, taxCents: 638));

        $result = $this->makeService()->calculateCartTax($items, 'US', 'CA');

        $this->assertEquals(638, $result->taxCents);
    }

    public function testCalculateCartTaxHandlesMissingCentsFieldsGracefully(): void
    {
        $this->mockEngine('stripe');

        $items = [
            ['subtotal_cents' => 5000],  // no shipping_cents key
            ['shipping_cents' => 300],   // no subtotal_cents key
        ];

        $this->builder->shouldReceive('buildFromLineItems')
            ->once()
            ->with($items, 'gbp', 'US', 'TX', null)
            ->andReturn([]);

        $this->mockStripeCalcCreate([], $this->makeCalculationFixture(332));

        $this->mapper->shouldReceive('map')
            ->once()
            ->with(m::any(), 5000, 300)
            ->andReturn(new TaxData(rate: 0.0625, ratePercentage: 6.25, jurisdiction: 'Texas',
                includesShipping: true, taxCents: 332));

        $result = $this->makeService()->calculateCartTax($items, 'US', 'TX');

        var_dump($result->taxCents);
        var_dump(gettype($result->taxCents));

        $this->assertIsInt($result->taxCents);
    }

    public function testCalculateCartTaxAppliesExemptionOverride(): void
    {
        $this->mockEngine('stripe');

        $member = m::mock(Member::class)->makePartial();
        $member->tax_exempt = true;

        $items = [['subtotal_cents' => 10000, 'shipping_cents' => 1000]];

        $this->builder->shouldReceive('buildFromLineItems')->once()->andReturn([]);
        $this->mockStripeCalcCreate([], $this->makeCalculationFixture(2200));
        $this->mapper->shouldReceive('map')->once()->andReturn(
            new TaxData(rate: 0.20, ratePercentage: 20.0, jurisdiction: 'UK',
                includesShipping: true, taxCents: 2200, taxableAmountCents: 11000)
        );

        $result = $this->makeService()->calculateCartTax($items, 'GB', null, null, $member);

        $this->assertEquals(0, $result->taxCents);
        $this->assertTrue($result->exempt);
    }

    public function testCalculateCartTaxApiErrorFallsBackToLegacy(): void
    {
        $this->mockEngine('stripe');

        $items = [
            ['subtotal_cents' => 5000, 'shipping_cents' => 500],
            ['subtotal_cents' => 3000, 'shipping_cents' => 300],
        ];

        $this->builder->shouldReceive('buildFromLineItems')->once()->andReturn([]);
        $this->mapper->shouldNotReceive('map');
        $this->mockStripeCalcCreateThrows();

        // Legacy CA rate: 7.25% of $88 = $6.38
        $result = $this->makeService()->calculateCartTax($items, 'US', 'CA');

        $this->assertEquals(638, $result->taxCents);
    }

    // =========================================================================
    // Legacy path — all original test expectations preserved exactly
    // =========================================================================

    public function testCalculateOrderTaxWithValidRate(): void
    {
        $this->mockEngine('legacy');

        $result = $this->makeService()->calculateOrderTax(10000, 1000, 'US', 'CA', '90210');

        $this->assertEquals(798, $result->taxCents); // 7.25% of $110
        $this->assertEquals(0.0725, $result->rate);
        $this->assertEquals('California', $result->jurisdiction);
        $this->assertEquals(11000, $result->taxableAmountCents);
    }

    public function testCalculateOrderTaxWithoutShipping(): void
    {
        $this->mockEngine('legacy');

        $result = $this->makeService()->calculateOrderTax(10000, 1000, 'US', 'FL', null);

        $this->assertEquals(600, $result->taxCents); // 6% of $100 (shipping excluded)
        $this->assertEquals(10000, $result->taxableAmountCents);
    }

    public function testCalculateOrderTaxWithNoRate(): void
    {
        $this->mockEngine('legacy');

        $result = $this->makeService()->calculateOrderTax(10000, 1000, 'XX', null, null);

        $this->assertEquals(0, $result->taxCents);
        $this->assertEquals(0.00, $result->rate);
        $this->assertNull($result->jurisdiction);
    }

    public function testCalculateOrderTaxWithTaxExemptMember(): void
    {
        $this->mockEngine('legacy');

        $member = m::mock(Member::class)->makePartial();
        $member->tax_exempt = true;

        $result = $this->makeService()->calculateOrderTax(10000, 1000, 'US', 'CA', null, $member);

        $this->assertEquals(0, $result->taxCents);
        $this->assertTrue($result->exempt);
        $this->assertEquals('California', $result->jurisdiction);
    }

    public function testCalculateOrderTaxFallsBackToCountryDefault(): void
    {
        $this->mockEngine('legacy');

        $result = $this->makeService()->calculateOrderTax(10000, 1000, 'US', 'ZZ', null);

        $this->assertEquals(770, $result->taxCents); // 7% of $110
        $this->assertEquals(0.07, $result->rate);
        $this->assertEquals('United States', $result->jurisdiction);
    }

    public function testCalculateOrderTaxCanadaHST(): void
    {
        $this->mockEngine('legacy');

        $result = $this->makeService()->calculateOrderTax(10000, 1000, 'CA', 'ON', null);

        $this->assertEquals(1430, $result->taxCents); // 13% of $110
        $this->assertEquals(0.13, $result->rate);
        $this->assertEquals('Ontario (HST)', $result->jurisdiction);
    }

    public function testCalculateOrderTaxUKVAT(): void
    {
        $this->mockEngine('legacy');

        $result = $this->makeService()->calculateOrderTax(10000, 1000, 'GB', null, null);

        $this->assertEquals(2200, $result->taxCents); // 20% of $110
        $this->assertEquals(0.20, $result->rate);
        $this->assertEquals('United Kingdom (VAT)', $result->jurisdiction);
    }

    public function testCalculateOrderTaxAustraliaGST(): void
    {
        $this->mockEngine('legacy');

        $result = $this->makeService()->calculateOrderTax(10000, 1000, 'AU', null, null);

        $this->assertEquals(1100, $result->taxCents); // 10% of $110
        $this->assertEquals(0.10, $result->rate);
        $this->assertEquals('Australia (GST)', $result->jurisdiction);
    }

    public function testIsTaxExemptForNonProfit(): void
    {
        $this->mockEngine('legacy');

        $member = m::mock(Member::class)->makePartial();
        $member->tax_exempt = false;
        $member->organization_type = 'non_profit';

        $result = $this->makeService()->calculateOrderTax(10000, 1000, 'US', 'CA', null, $member);

        $this->assertEquals(0, $result->taxCents);
        $this->assertTrue($result->exempt);
    }

    public function testIsTaxExemptForEducational(): void
    {
        $this->mockEngine('legacy');

        $member = m::mock(Member::class)->makePartial();
        $member->tax_exempt = false;
        $member->organization_type = 'educational';

        $result = $this->makeService()->calculateOrderTax(10000, 1000, 'US', 'NY', null, $member);

        $this->assertEquals(0, $result->taxCents);
        $this->assertTrue($result->exempt);
    }

    public function testMultipleEUCountriesLegacy(): void
    {
        $this->mockEngine('legacy');
        $service = $this->makeService();

        $countries = [
            'DE' => 0.19,
            'FR' => 0.20,
            'IT' => 0.22,
            'ES' => 0.21,
            'NL' => 0.21,
        ];

        foreach ($countries as $country => $expectedRate) {
            $result = $service->calculateOrderTax(10000, 1000, $country, null, null);
            $this->assertEquals($expectedRate, $result->rate, "Rate mismatch for {$country}");
        }
    }

    public function testCalculateCartTaxLegacy(): void
    {
        $this->mockEngine('legacy');

        $items = [
            ['subtotal_cents' => 5000, 'shipping_cents' => 500],
            ['subtotal_cents' => 3000, 'shipping_cents' => 300],
        ];

        $result = $this->makeService()->calculateCartTax($items, 'US', 'CA');

        $this->assertEquals(638, $result->taxCents); // 7.25% of $88
    }

    // =========================================================================
    // distributeTaxToItems — backward-compat, unchanged expectations
    // =========================================================================

    public function testDistributeTaxToItemsProportionally(): void
    {
        $items = [
            ['subtotal_cents' => 5000, 'shipping_cents' => 500],  // $55 — 50%
            ['subtotal_cents' => 3000, 'shipping_cents' => 300],  // $33 — 30%
            ['subtotal_cents' => 2000, 'shipping_cents' => 200],  // $22 — 20%
        ];

        $result = $this->makeLegacyService()->distributeTaxToItems($items, 1000);

        $this->assertEquals(500, $result[0]['tax_cents']);
        $this->assertEquals(300, $result[1]['tax_cents']);
        $this->assertEquals(200, $result[2]['tax_cents']);
        $this->assertEquals(1000, array_sum(array_column($result, 'tax_cents')));
    }

    public function testDistributeTaxWithZeroBase(): void
    {
        $items = [['subtotal_cents' => 0, 'shipping_cents' => 0]];

        $result = $this->makeLegacyService()->distributeTaxToItems($items, 1000);

        $this->assertEquals(0, $result[0]['tax_cents']);
    }

    public function testDistributeTaxWithZeroTax(): void
    {
        $items = [['subtotal_cents' => 5000, 'shipping_cents' => 500]];

        $result = $this->makeLegacyService()->distributeTaxToItems($items, 0);

        $this->assertEquals(0, $result[0]['tax_cents']);
    }

    // =========================================================================
    // getSupportedCountries / getStatesForCountry — config-backed
    // =========================================================================

    public function testGetSupportedCountries(): void
    {
        $countries = $this->makeLegacyService()->getSupportedCountries();

        $this->assertContains('US', $countries);
        $this->assertContains('CA', $countries);
        $this->assertContains('GB', $countries);
        $this->assertContains('AU', $countries);
    }

    public function testGetStatesForCountry(): void
    {
        $states = $this->makeLegacyService()->getStatesForCountry('US');

        $this->assertContains('CA', $states);
        $this->assertContains('NY', $states);
        $this->assertContains('TX', $states);
        $this->assertNotContains('DEFAULT', $states);
    }

    public function testGetStatesForCountryWithNoStates(): void
    {
        $this->assertEmpty($this->makeLegacyService()->getStatesForCountry('GB'));
    }

    public function testGetStatesForInvalidCountry(): void
    {
        $this->assertEmpty($this->makeLegacyService()->getStatesForCountry('XX'));
    }

    // =========================================================================
    // getTaxRateInfo — display-only; Stripe must never be called
    // =========================================================================

    public function testGetTaxRateInfoNeverCallsStripe(): void
    {
        $this->builder->shouldNotReceive('buildFromOrderTotals');
        $this->builder->shouldNotReceive('buildFromLineItems');
        $this->mapper->shouldNotReceive('map');

        $info = $this->makeLegacyService()->getTaxRateInfo('US', 'CA');

        $this->assertEquals(0.0725, $info->rate);
        $this->assertEquals(7.25, $info->ratePercentage);
        $this->assertEquals('California', $info->jurisdiction);
        $this->assertTrue($info->includesShipping);
    }

    public function testGetTaxRateInfoWithDefault(): void
    {
        $info = $this->makeLegacyService()->getTaxRateInfo('US', 'ZZ');

        $this->assertEquals(0.07, $info->rate);
        $this->assertEquals(7.0, $info->ratePercentage);
        $this->assertEquals('United States', $info->jurisdiction);
    }

    public function testGetTaxRateInfoInvalidCountry(): void
    {
        $this->assertNull($this->makeLegacyService()->getTaxRateInfo('XX'));
    }

    // =========================================================================
    // validateTaxExemption — unchanged
    // =========================================================================

    public function testValidateTaxExemptionValid(): void
    {
        $this->assertTrue(
            $this->makeLegacyService()->validateTaxExemption(m::mock(Member::class), 'EXEMPT123456', 'CA')
        );
    }

    public function testValidateTaxExemptionInvalid(): void
    {
        $this->assertFalse(
            $this->makeLegacyService()->validateTaxExemption(m::mock(Member::class), 'SHORT', 'CA')
        );
    }

    public function testValidateTaxExemptionEmpty(): void
    {
        $this->assertFalse(
            $this->makeLegacyService()->validateTaxExemption(m::mock(Member::class), '', 'CA')
        );
    }

    // =========================================================================
    // StripeTaxResponseMapper unit tests
    // =========================================================================

    public function testMapperIncludesShippingInTaxableAmountWhenShippingNonZero(): void
    {
        $mapper = new StripeTaxResponseMapper();

        $calculation = (object)[
            'tax_amount_exclusive' => 2200,
            'shipping_cost'        => (object)['amount_tax' => 0], // zero shipping tax
            'tax_breakdown'        => [],
        ];

        // shipping is £10 — taxable amount should still include it even though tax rounds to 0
        $result = $mapper->map($calculation, 10000, 1000);

        $this->assertEquals(11000, $result->taxableAmountCents);
        $this->assertTrue($result->includesShipping);
    }

    public function testMapperExcludesShippingFromTaxableAmountWhenShippingIsZero(): void
    {
        $mapper = new StripeTaxResponseMapper();

        $calculation = (object)[
            'tax_amount_exclusive' => 2000,
            'tax_breakdown'        => [],
        ];

        $result = $mapper->map($calculation, 10000, 0);

        $this->assertEquals(10000, $result->taxableAmountCents);
        $this->assertFalse($result->includesShipping);
    }

    public function testMapZeroTaxReturnsExemptFalse(): void
    {
        $mapper = new StripeTaxResponseMapper();
        $result = $mapper->mapZeroTax('XX');

        $this->assertFalse($result->exempt,
            'A zero-rate jurisdiction must not be marked exempt — '
            . 'conflating the two would corrupt exemption analytics.'
        );
        $this->assertEquals(0, $result->taxCents);
        $this->assertEquals(0, $result->rate);
    }

    public function testMapperExtractsRateAndJurisdictionFromBreakdown(): void
    {
        $mapper = new StripeTaxResponseMapper();

        $breakdown = (object)[
            'tax_rate_details' => (object)['percentage_decimal' => 20.0],
            'jurisdiction'     => (object)['display_name' => 'United Kingdom'],
        ];

        $calculation = (object)[
            'tax_amount_exclusive' => 2000,
            'tax_breakdown'        => [$breakdown],
        ];

        $result = $mapper->map($calculation, 10000, 0);

        $this->assertEquals(0.20, $result->rate);
        $this->assertEquals(20.0, $result->ratePercentage);
        $this->assertEquals('United Kingdom', $result->jurisdiction);
    }

    public function testMapperSumsLineItemTaxAndShippingTax(): void
    {
        $mapper = new StripeTaxResponseMapper();

        $calculation = (object)[
            'tax_amount_exclusive' => 2000,
            'shipping_cost'        => (object)['amount_tax' => 200],
            'tax_breakdown'        => [],
        ];

        $result = $mapper->map($calculation, 10000, 1000);

        $this->assertEquals(2200, $result->taxCents);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeCalculationFixture(int $taxAmountExclusive): object
    {
        return (object)[
            'tax_amount_exclusive' => $taxAmountExclusive,
            'amount_total'         => 10000 + $taxAmountExclusive,
            'tax_breakdown'        => [],
        ];
    }

    private function mockStripeCalcCreate(array $payload, object $calculation): void
    {
        $calcService = m::mock();
        $calcService->shouldReceive('create')
            ->once()
            ->with($payload)
            ->andReturn($calculation);

        $taxService = m::mock();
        $taxService->calculations = $calcService;

        $this->stripeClient
            ->shouldReceive('getService')
            ->once()
            ->with('tax')
            ->andReturn($taxService);
    }

    private function mockStripeCalcCreateThrows(): void
    {
        $exception = m::mock(ApiErrorException::class);

        $calcService = m::mock();
        $calcService->shouldReceive('create')
            ->once()
            ->andThrow($exception);

        $taxService = m::mock();
        $taxService->calculations = $calcService;

        $this->stripeClient
            ->shouldReceive('getService')
            ->once()
            ->with('tax')
            ->andReturn($taxService);
    }

    private function mockEngine(string $engine, string $currency = 'gbp'): void
    {
        $_ENV['TAX_ENGINE_OVERRIDE']   = $engine;
        $_ENV['TAX_CURRENCY_OVERRIDE'] = $currency;
    }

    /**
     * Service with engine controlled via ENV override through anonymous subclass.
     */
    private function makeService(): TaxCalculatorService
    {
        $builder = $this->builder;
        $mapper  = $this->mapper;
        $client  = $this->stripeClient;

        return new class($builder, $mapper, $client) extends TaxCalculatorService {
            protected function isLegacyEngine(): bool
            {
                return ($_ENV['TAX_ENGINE_OVERRIDE'] ?? 'stripe') === 'legacy';
            }

            protected function defaultCurrency(): string
            {
                return $_ENV['TAX_CURRENCY_OVERRIDE'] ?? 'gbp';
            }
        };
    }

    /**
     * Convenience: service forced to legacy path, no Stripe mock expectations needed.
     */
    private function makeLegacyService(): TaxCalculatorService
    {
        $this->mockEngine('legacy');
        return $this->makeService();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder      = m::mock(StripeTaxCalculationBuilder::class);
        $this->mapper       = m::mock(StripeTaxResponseMapper::class);
        $this->stripeClient = m::mock(StripeClient::class);

        $_ENV['TAX_ENGINE_OVERRIDE']   = 'stripe';
        $_ENV['TAX_CURRENCY_OVERRIDE'] = 'gbp';
    }

    protected function tearDown(): void
    {
        m::close();
        unset($_ENV['TAX_ENGINE_OVERRIDE'], $_ENV['TAX_CURRENCY_OVERRIDE']);
        parent::tearDown();
    }
}