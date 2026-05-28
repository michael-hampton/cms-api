<?php

namespace App\Tests\Unit\Services\Billing\Stripe;

use App\Services\Billing\Stripe\StripeCustomerDetailsUpdater;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Stripe\Service\CustomerService;
use Stripe\StripeClient;

class StripeCustomerDetailsUpdaterTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_update_forwards_fields_to_stripe_customer_update(): void
    {
        $customers = Mockery::mock(CustomerService::class);
        $customers->shouldReceive('update')
            ->once()
            ->with('cus_123', [
                'email' => 'member@example.com',
                'name' => 'Test Member',
            ])
            ->andReturn((object) ['id' => 'cus_123']);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->customers = $customers;

        $service = new StripeCustomerDetailsUpdater($stripe);

        $this->assertSame([
            'success' => true,
            'customer_id' => 'cus_123',
        ], $service->update('cus_123', [
            'email' => 'member@example.com',
            'name' => 'Test Member',
        ]));
    }
}
