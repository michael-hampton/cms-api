<?php

namespace App\Tests\Unit\Services\Billing\Payment;

use App\Services\Billing\PaymentProviders\StripePaymentProcessor;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Stripe\Exception\InvalidRequestException;
use Stripe\Service\CustomerService;
use Stripe\Service\SubscriptionService;
use Stripe\StripeClient;

class StripePaymentProcessorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private StripeClient&Mockery\MockInterface $stripe;
    private StripePaymentProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stripe = Mockery::mock(StripeClient::class);
        $this->processor = new StripePaymentProcessor($this->stripe);
    }

    public function test_get_subscription_returns_mapped_subscription_on_success(): void
    {
        $subscriptionService = Mockery::mock(SubscriptionService::class);
        $subscriptionService->shouldReceive('retrieve')
            ->once()
            ->with('sub_123', ['expand' => ['latest_invoice.payment_intent']])
            ->andReturn((object) [
                'id' => 'sub_123',
                'status' => 'active',
                'current_period_start' => 1000,
                'current_period_end' => 2000,
                'cancel_at_period_end' => false,
                'canceled_at' => null,
                'ended_at' => null,
            ]);

        $this->stripe->subscriptions = $subscriptionService;

        $result = $this->processor->getSubscription('sub_123');

        $this->assertTrue($result['success']);
        $this->assertSame('sub_123', $result['subscription']['id']);
        $this->assertSame('active', $result['subscription']['status']);
    }

    public function test_get_subscription_returns_failure_payload_when_stripe_throws(): void
    {
        $subscriptionService = Mockery::mock(SubscriptionService::class);
        $subscriptionService->shouldReceive('retrieve')
            ->once()
            ->andThrow(InvalidRequestException::factory('No such subscription'));

        $this->stripe->subscriptions = $subscriptionService;

        $result = $this->processor->getSubscription('sub_missing');

        $this->assertSame([
            'success' => false,
            'message' => 'No such subscription',
        ], $result);
    }

    public function test_update_customer_details_returns_success_payload(): void
    {
        $customerService = Mockery::mock(CustomerService::class);
        $customerService->shouldReceive('update')
            ->once()
            ->with('cus_123', ['email' => 'new@example.com'])
            ->andReturn((object) ['id' => 'cus_123']);

        $this->stripe->customers = $customerService;

        $result = $this->processor->updateCustomerDetails('cus_123', ['email' => 'new@example.com']);

        $this->assertSame([
            'success' => true,
            'customer_id' => 'cus_123',
        ], $result);
    }

    public function test_update_customer_details_returns_failure_payload_when_stripe_throws(): void
    {
        $customerService = Mockery::mock(CustomerService::class);
        $customerService->shouldReceive('update')
            ->once()
            ->andThrow(new \RuntimeException('Stripe down'));

        $this->stripe->customers = $customerService;

        $result = $this->processor->updateCustomerDetails('cus_123', ['email' => 'new@example.com']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Stripe down', $result['message']);
    }
}
