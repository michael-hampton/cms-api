<?php

namespace App\Tests\Unit\Services\Billing\Stripe;

use App\Services\Billing\Stripe\StripeOffSessionCharger;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Stripe\Service\CustomerService;
use Stripe\Service\PaymentIntentService;
use Stripe\StripeClient;

class StripeOffSessionChargerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_charge_returns_error_without_default_payment_method(): void
    {
        $customers = Mockery::mock(CustomerService::class);
        $customers->shouldReceive('retrieve')
            ->once()
            ->with('cus_123', ['expand' => ['invoice_settings.default_payment_method']])
            ->andReturn((object) [
                'invoice_settings' => (object) ['default_payment_method' => null],
            ]);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->customers = $customers;

        $service = new StripeOffSessionCharger($stripe);

        $this->assertSame(false, $service->charge('cus_123', 1500, 'gbp')['success']);
    }

    public function test_charge_creates_payment_intent_with_default_method(): void
    {
        $customers = Mockery::mock(CustomerService::class);
        $customers->shouldReceive('retrieve')
            ->once()
            ->andReturn((object) [
                'invoice_settings' => (object) ['default_payment_method' => 'pm_123'],
            ]);

        $paymentIntents = Mockery::mock(PaymentIntentService::class);
        $paymentIntents->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn ($payload) => $payload['payment_method'] === 'pm_123' && $payload['off_session'] === true))
            ->andReturn((object) [
                'id' => 'pi_123',
                'client_secret' => 'secret_123',
                'status' => 'succeeded',
            ]);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->customers = $customers;
        $stripe->paymentIntents = $paymentIntents;

        $service = new StripeOffSessionCharger($stripe);

        $this->assertSame('pi_123', $service->charge('cus_123', 1500, 'gbp')['payment_intent_id']);
    }
}
