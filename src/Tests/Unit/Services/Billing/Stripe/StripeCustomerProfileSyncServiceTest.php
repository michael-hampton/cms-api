<?php

namespace App\Tests\Unit\Services\Billing\Stripe;

use App\Models\Member;
use App\Services\Billing\Stripe\StripeCustomerGateway;
use App\Services\Billing\Stripe\StripeCustomerProfileSyncService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Stripe\Service\CustomerService;
use Stripe\StripeClient;

class StripeCustomerProfileSyncServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_sync_creates_or_reuses_customer_and_updates_profile_fields(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 12;
        $member->site_id = 7;
        $member->email = 'member@example.com';
        $member->first_name = 'Test';
        $member->last_name = 'Member';
        $member->is_active = true;
        $member->stripe_customer_id = 'cus_123';

        $customerGateway = Mockery::mock(StripeCustomerGateway::class);
        $customerGateway->shouldReceive('getOrCreate')->once()->with($member)->andReturn('cus_123');

        $customerService = Mockery::mock(CustomerService::class);
        $customerService->shouldReceive('update')->once()->with('cus_123', Mockery::on(function (array $payload) {
            return $payload['email'] === 'member@example.com'
                && $payload['name'] === 'Test Member'
                && $payload['metadata']['member_id'] === 12
                && $payload['metadata']['site_id'] === 7
                && $payload['metadata']['is_active'] === 1;
        }));

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->customers = $customerService;

        $service = new StripeCustomerProfileSyncService($stripe, $customerGateway);
        $service->sync($member);
    }

    public function test_mark_inactive_updates_existing_customer_metadata_only(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 12;
        $member->site_id = 7;
        $member->stripe_customer_id = 'cus_123';

        $customerGateway = Mockery::mock(StripeCustomerGateway::class);
        $customerService = Mockery::mock(CustomerService::class);
        $customerService->shouldReceive('update')->once()->with('cus_123', Mockery::on(function (array $payload) {
            return $payload['metadata']['is_active'] === 0;
        }));

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->customers = $customerService;

        $service = new StripeCustomerProfileSyncService($stripe, $customerGateway);
        $service->markInactive($member);
    }
}
