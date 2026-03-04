<?php

namespace App\Tests\Unit\Services\Subscriptions\Printing;

use App\Models\Subscription;
use App\Services\Subscriptions\Printing\PrintAddressResolver;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class PrintAddressResolverTest extends FunctionalTestCase
{
    private PrintAddressResolver $resolver;

    public function test_uses_delivery_address_when_present(): void
    {
        $subscription = $this->makeSubscription(
            deliveryAddress: $this->validAddress(['first_name' => 'Jane', 'address_line_1' => '1 Delivery Rd']),
            billingAddress: $this->validAddress(['first_name' => 'Jane', 'address_line_1' => '2 Billing Ave']),
        );

        $result = $this->resolver->resolve($subscription);

        $this->assertSame('1 Delivery Rd', $result['address_line_1']);
    }

    private function makeSubscription(mixed $deliveryAddress, mixed $billingAddress): Subscription
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 10;
        $subscription->delivery_address = $deliveryAddress;
        $subscription->billing_address = $billingAddress;
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
        $subscription = $this->makeSubscription(
            deliveryAddress: null,
            billingAddress: $this->validAddress(['address_line_1' => '2 Billing Ave']),
        );

        $result = $this->resolver->resolve($subscription);

        $this->assertSame('2 Billing Ave', $result['address_line_1']);
    }

    public function test_throws_when_no_address_available(): void
    {
        $subscription = $this->makeSubscription(
            deliveryAddress: null,
            billingAddress: null,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/no valid delivery address found/');

        $this->resolver->resolve($subscription);
    }

    public function test_throws_when_delivery_address_missing_required_fields(): void
    {
        $subscription = $this->makeSubscription(
            deliveryAddress: ['first_name' => 'Jane'], // missing address_line_1, city, etc.
            billingAddress: null,
        );

        $this->expectException(\RuntimeException::class);

        $this->resolver->resolve($subscription);
    }

    public function test_snapshots_address_in_result(): void
    {
        $address = $this->validAddress(['address_line_1' => '99 Test St']);
        $subscription = $this->makeSubscription(deliveryAddress: $address, billingAddress: null);

        $result = $this->resolver->resolve($subscription);

        $this->assertArrayHasKey('snapshot', $result);
        $this->assertSame('99 Test St', $result['snapshot']['address_line_1']);
    }

    public function test_builds_full_name_from_first_and_last_name(): void
    {
        $address = $this->validAddress(['first_name' => 'Jane', 'last_name' => 'Doe']);
        $subscription = $this->makeSubscription(deliveryAddress: $address, billingAddress: null);

        $result = $this->resolver->resolve($subscription);

        $this->assertSame('Jane Doe', $result['full_name']);
    }

    public function test_accepts_json_encoded_address(): void
    {
        $address = json_encode($this->validAddress(['address_line_1' => '5 Json Lane']));
        $subscription = $this->makeSubscription(deliveryAddress: $address, billingAddress: null);

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