<?php

namespace App\Tests\Unit\Services\Billing\Stripe;

use App\DTO\Stripe\BillingAddressData;
use App\Models\Address;
use App\Models\Member;
use App\Services\Billing\Stripe\BillingAddressResolver;
use Mockery as m;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class BillingAddressResolverTest extends TestCase
{
    private BillingAddressResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new BillingAddressResolver();
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    // ── Explicit checkout address ─────────────────────────────────────────────

    public function test_returns_dto_from_explicit_address_when_supplied(): void
    {
        $member  = $this->makeMember();
        $address = $this->makeAddressModel(
            line1:    '10 Downing Street',
            line2:    'Westminster',
            city:     'London',
            state:    'England',
            postcode: 'SW1A 2AA',
            country:  'GB',
        );

        $result = $this->resolver->resolve($member, $address);

        $this->assertInstanceOf(BillingAddressData::class, $result);
        $this->assertSame('10 Downing Street', $result->line1);
        $this->assertSame('Westminster',       $result->line2);
        $this->assertSame('London',            $result->city);
        $this->assertSame('England',           $result->state);
        $this->assertSame('SW1A 2AA',          $result->postcode);
        $this->assertSame('GB',                $result->country);
    }

    public function test_explicit_address_takes_precedence_over_member_billing_address(): void
    {
        $checkoutAddress = $this->makeAddressModel(
            line1:   '1 New Street',
            country: 'US',
        );

        $member = $this->makeMember();
        // resolveBillingAddress must NOT be called when address is explicitly supplied
        $member->shouldNotReceive('resolveBillingAddress');

        $result = $this->resolver->resolve($member, $checkoutAddress);

        $this->assertSame('1 New Street', $result->line1);
        $this->assertSame('US',           $result->country);
    }

    public function test_empty_address_fields_are_normalised_to_null(): void
    {
        $address = $this->makeAddressModel(
            line1:    '5 Elm St',
            line2:    '',   // empty → null
            city:     '',   // empty → null
            state:    '',   // empty → null
            postcode: 'E1 1AA',
            country:  'GB',
        );

        $result = $this->resolver->resolve($this->makeMember(), $address);

        $this->assertNull($result->line2);
        $this->assertNull($result->city);
        $this->assertNull($result->state);
    }

    // ── Member billing address fallback ──────────────────────────────────────

    public function test_falls_back_to_member_billing_address_when_no_checkout_address(): void
    {
        $memberAddress = $this->makeAddressModel(
            line1:    '99 High Street',
            city:     'Manchester',
            postcode: 'M1 1AE',
            country:  'GB',
        );

        $member = $this->makeMember();
        $member->shouldReceive('resolveBillingAddress')
            ->once()
            ->andReturn($memberAddress);

        $result = $this->resolver->resolve($member, null);

        $this->assertSame('99 High Street', $result->line1);
        $this->assertSame('Manchester',     $result->city);
        $this->assertSame('M1 1AE',         $result->postcode);
        $this->assertSame('GB',             $result->country);
    }

    // ── Country-only fallback ─────────────────────────────────────────────────

    public function test_returns_country_only_when_no_address_record_and_member_has_country(): void
    {
        $member = $this->makeMember(country: 'DE');
        $member->shouldReceive('resolveBillingAddress')
            ->once()
            ->andReturn(null);

        $result = $this->resolver->resolve($member, null);

        $this->assertNull($result->line1);
        $this->assertNull($result->city);
        $this->assertSame('DE', $result->country);
    }

    public function test_returns_null_country_when_member_has_no_country_and_no_address(): void
    {
        $member = $this->makeMember(country: null);
        $member->shouldReceive('resolveBillingAddress')
            ->once()
            ->andReturn(null);

        $result = $this->resolver->resolve($member, null);

        $this->assertNull($result->country);
    }

    // ── DTO type guarantee ────────────────────────────────────────────────────

    public function test_always_returns_billing_address_data_instance(): void
    {
        $member = $this->makeMember(country: null);
        $member->shouldReceive('resolveBillingAddress')->andReturn(null);

        $result = $this->resolver->resolve($member, null);

        $this->assertInstanceOf(BillingAddressData::class, $result);
    }

    // ── toStripe mapping ──────────────────────────────────────────────────────

    public function test_resolved_address_maps_correctly_to_stripe_format(): void
    {
        $address = $this->makeAddressModel(
            line1:    '1 Test Lane',
            line2:    'Suite 2',
            city:     'Bristol',
            state:    'Somerset',
            postcode: 'BS1 1AA',
            country:  'GB',
        );

        $result = $this->resolver->resolve($this->makeMember(), $address);
        $stripe = $result->toStripe();

        $this->assertSame('1 Test Lane', $stripe['line1']);
        $this->assertSame('Suite 2',     $stripe['line2']);
        $this->assertSame('Bristol',     $stripe['city']);
        $this->assertSame('Somerset',    $stripe['state']);
        $this->assertSame('BS1 1AA',     $stripe['postal_code']); // mapped from postcode
        $this->assertSame('GB',          $stripe['country']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeMember(?string $country = 'GB'): Member|MockInterface
    {
        $member          = m::mock(Member::class)->makePartial();
        $member->id      = 1;
        $member->site_id = 1;
        $member->country = $country;
        return $member;
    }

    private function makeAddressModel(
        ?string $line1    = null,
        ?string $line2    = null,
        ?string $city     = null,
        ?string $state    = null,
        ?string $postcode = null,
        ?string $country  = null,
    ): Address|MockInterface {
        $address = m::mock(Address::class)->makePartial();

        $address->address_line_1 = $line1;
        $address->address_line_2 = $line2;
        $address->city           = $city;
        $address->state          = $state;
        $address->postcode       = $postcode;
        $address->country        = $country;

        return $address;
    }
}