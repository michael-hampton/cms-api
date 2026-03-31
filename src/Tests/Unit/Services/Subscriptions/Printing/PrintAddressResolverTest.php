<?php

namespace App\Tests\Unit\Services\Subscriptions\Printing;

use App\Models\Member;
use App\Models\Subscription;
use App\Services\Subscriptions\Printing\PrintAddressResolver;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class PrintAddressResolverTest extends FunctionalTestCase
{
    private PrintAddressResolver $resolver;

    public function test_uses_delivery_address_when_present(): void
    {
        $subscription = $this->makeSubscription([
            $this->validAddress(['type' => 'shipping', 'address_line_1' => '1 Delivery Rd']),
            $this->validAddress(['type' => 'billing', 'address_line_1' => '2 Billing Ave']),
        ]);

        $result = $this->resolver->resolve($subscription);

        $this->assertSame('1 Delivery Rd', $result['address_line_1']);
    }

    private function makeSubscription(array $addresses = []): Subscription
    {
        $member = Mockery::mock(Member::class)->makePartial();

        $address = is_string($addresses[0]) ? json_decode($addresses[0], true) : $addresses[0];

        $member->first_name = $address['first_name'];
        $member->last_name = $address['last_name'];
        $member->addresses = collect($addresses);

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 10;
        $subscription->member = $member;

        return $subscription;
    }

    private function validAddress(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Test',
            'last_name' => 'User',
            'address_line_1' => '1 Main St',
            'address_line_2' => null,
            'city' => 'London',
            'postcode' => 'E1 1AA',
            'country' => 'GB',
        ], $overrides);
    }

    public function test_falls_back_to_billing_address_when_delivery_absent(): void
    {
        $subscription = $this->makeSubscription([
            $this->validAddress(['type' => 'billing', 'address_line_1' => '2 Billing Ave']),
        ]);

        $result = $this->resolver->resolve($subscription);

        $this->assertSame('2 Billing Ave', $result['address_line_1']);
    }

    public function test_throws_when_no_address_available(): void
    {
        $subscription = $this->makeSubscription([]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot fulfil print subscription #10: no valid delivery address found');

        $this->resolver->resolve($subscription);
    }

    public function test_throws_when_delivery_address_missing_required_fields(): void
    {
        $subscription = $this->makeSubscription([
            ['type' => 'shipping', 'first_name' => 'Jane'], // invalid
        ]);

        $this->expectException(\RuntimeException::class);

        $this->resolver->resolve($subscription);
    }

    public function test_snapshots_address_in_result(): void
    {
        $address = $this->validAddress([
            'type' => 'shipping',
            'address_line_1' => '99 Test St'
        ]);

        $subscription = $this->makeSubscription([$address]);

        $result = $this->resolver->resolve($subscription);

        $this->assertArrayHasKey('snapshot', $result);
        $this->assertSame('99 Test St', $result['snapshot']['address_line_1']);
    }

    public function test_builds_full_name_from_first_and_last_name(): void
    {
        $address = $this->validAddress(['type' => 'shipping', 'first_name' => 'Jane', 'last_name' => 'Doe']);
        $subscription = $this->makeSubscription([$address]);

        $result = $this->resolver->resolve($subscription);

        $this->assertSame('Jane Doe', $result['full_name']);
    }

    public function test_accepts_json_encoded_address(): void
    {
        $address = json_encode($this->validAddress(['type' => 'shipping', 'address_line_1' => '5 Json Lane']));
        $subscription = $this->makeSubscription([$address]);

        $result = $this->resolver->resolve($subscription);

        $this->assertSame('5 Json Lane', $result['address_line_1']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new PrintAddressResolver();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}