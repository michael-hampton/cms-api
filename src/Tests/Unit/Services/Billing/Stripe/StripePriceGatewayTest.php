<?php

namespace App\Tests\Unit\Services\Billing\Stripe;

use App\Services\Billing\Stripe\StripePriceGateway;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Stripe\Service\PriceService;
use Stripe\StripeClient;

class StripePriceGatewayTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private PriceService $prices;
    private StripePriceGateway $gateway;

    public function testCreateRecurringPriceCallsStripeWithCorrectPayload(): void
    {
        $this->prices
            ->shouldReceive('create')->once()
            ->with([
                'product' => 'prod_abc',
                'unit_amount' => 999,
                'currency' => 'gbp',
                'recurring' => ['interval' => 'month'],
            ])
            ->andReturn((object)['id' => 'price_abc']);

        $this->gateway->createRecurringPrice('prod_abc', 999, 'gbp', 'month');
    }

    // ---------------------------------------------------------------------------
    // createRecurringPrice — payload shape
    // ---------------------------------------------------------------------------

    public function testCreateRecurringPriceReturnsStripePriceId(): void
    {
        $this->prices
            ->shouldReceive('create')
            ->andReturn((object)['id' => 'price_returned_id']);

        $this->assertEquals(
            'price_returned_id',
            $this->gateway->createRecurringPrice('prod_x', 1999, 'usd', 'year')
        );
    }

    public function testCreateRecurringPriceNormalisesCurrencyToLowercase(): void
    {
        // Stripe rejects uppercase currencies — the gateway must always send lowercase.
        $this->prices
            ->shouldReceive('create')->once()
            ->with(Mockery::on(fn($d) => $d['currency'] === 'gbp'))
            ->andReturn((object)['id' => 'price_lower']);

        $this->gateway->createRecurringPrice('prod_y', 500, 'GBP', 'month');
    }

    public function testCreateRecurringPricePassesAmountCentsAsUnitAmount(): void
    {
        $this->prices
            ->shouldReceive('create')->once()
            ->with(Mockery::on(fn($d) => $d['unit_amount'] === 2499))
            ->andReturn((object)['id' => 'price_unit']);

        $this->gateway->createRecurringPrice('prod_z', 2499, 'eur', 'month');
    }

    public function testCreateRecurringPricePassesIntervalInsideRecurringBlock(): void
    {
        $this->prices
            ->shouldReceive('create')->once()
            ->with(Mockery::on(fn($d) => ($d['recurring']['interval'] ?? null) === 'year'))
            ->andReturn((object)['id' => 'price_interval']);

        $this->gateway->createRecurringPrice('prod_a', 9999, 'usd', 'year');
    }

    public function testCreateRecurringPricePropagatesStripeRateLimitException(): void
    {
        $this->prices
            ->shouldReceive('create')
            ->andThrow(new \Stripe\Exception\RateLimitException('Rate limit exceeded'));

        $this->expectException(\Stripe\Exception\RateLimitException::class);

        $this->gateway->createRecurringPrice('prod_fail', 100, 'gbp', 'month');
    }

    // ---------------------------------------------------------------------------
    // createRecurringPrice — failure propagation
    // ---------------------------------------------------------------------------

    public function testCreateRecurringPricePropagatesInvalidRequestException(): void
    {
        $this->prices
            ->shouldReceive('create')
            ->andThrow(new \Stripe\Exception\InvalidRequestException(
                'No such product: prod_missing',
                null,
                null,
            ));

        $this->expectException(\Stripe\Exception\InvalidRequestException::class);

        $this->gateway->createRecurringPrice('prod_missing', 100, 'gbp', 'month');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->prices = Mockery::mock(PriceService::class);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->prices = $this->prices;

        $this->gateway = new StripePriceGateway($stripe);
    }
}