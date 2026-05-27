<?php

namespace App\Tests\Unit\Services\Billing\Stripe;

use App\Services\Billing\Stripe\StripeCustomerEmailUpdater;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Stripe\Service\CustomerService;
use Stripe\StripeClient;

class StripeCustomerEmailUpdaterTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_update_returns_success_payload_when_stripe_update_succeeds(): void
    {
        $customerService = Mockery::mock(CustomerService::class);
        $customerService->shouldReceive('update')
            ->once()
            ->with('cus_123', ['email' => 'new@example.com'])
            ->andReturn((object) ['id' => 'cus_123', 'email' => 'new@example.com']);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->customers = $customerService;

        $service = new StripeCustomerEmailUpdater($stripe);

        $this->assertSame([
            'success' => true,
            'customer_id' => 'cus_123',
            'email' => 'new@example.com',
        ], $service->update('cus_123', 'new@example.com'));
    }

    public function test_update_returns_failure_payload_when_stripe_throws(): void
    {
        $customerService = Mockery::mock(CustomerService::class);
        $customerService->shouldReceive('update')
            ->once()
            ->andThrow(new \RuntimeException('Stripe down'));

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->customers = $customerService;

        $service = new StripeCustomerEmailUpdater($stripe);

        $this->assertSame([
            'success' => false,
            'message' => 'Failed to update customer email in Stripe',
        ], $service->update('cus_123', 'new@example.com'));
    }
}
