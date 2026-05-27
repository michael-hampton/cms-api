<?php

namespace App\Tests\Unit\Services\Billing\Stripe;

use App\Models\Address;
use App\Models\Member;
use App\Services\Billing\Stripe\StripeCustomerAddressSyncService;
use App\Services\Billing\Stripe\StripeCustomerGateway;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class StripeCustomerAddressSyncServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_sync_forwards_member_and_address_to_gateway(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 8;
        $member->site_id = 3;

        $address = Mockery::mock(Address::class)->makePartial();
        $address->id = 4;
        $address->member_id = 8;

        $gateway = Mockery::mock(StripeCustomerGateway::class);
        $gateway->shouldReceive('getOrCreate')->once()->with($member, $address);

        $service = new StripeCustomerAddressSyncService($gateway);
        $service->sync($member, $address);
    }

    public function test_sync_without_address_uses_resolved_member_address(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 8;
        $member->site_id = 3;

        $gateway = Mockery::mock(StripeCustomerGateway::class);
        $gateway->shouldReceive('getOrCreate')->once()->with($member, null);

        $service = new StripeCustomerAddressSyncService($gateway);
        $service->sync($member);
    }
}
