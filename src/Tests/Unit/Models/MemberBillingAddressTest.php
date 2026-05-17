<?php

namespace App\Tests\Unit\Models;

use App\Enums\Address\AddressType;
use App\Models\Address;
use App\Models\Member;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class MemberBillingAddressTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function test_returns_default_billing_address_first(): void
    {
        $defaultBilling  = $this->makeAddress(AddressType::Billing, isDefault: true);
        $otherBilling    = $this->makeAddress(AddressType::Billing, isDefault: false);
        $defaultShipping = $this->makeAddress(AddressType::Shipping, isDefault: true);

        $member = $this->makeMember([
            $defaultShipping,
            $otherBilling,
            $defaultBilling,
        ]);

        $this->assertSame($defaultBilling, $member->resolveBillingAddress());
    }

    public function test_returns_any_billing_address_when_no_default_billing(): void
    {
        $billing  = $this->makeAddress(AddressType::Billing, isDefault: false);
        $shipping = $this->makeAddress(AddressType::Shipping, isDefault: true);

        $member = $this->makeMember([$shipping, $billing]);

        $this->assertSame($billing, $member->resolveBillingAddress());
    }

    public function test_returns_default_shipping_when_no_billing_address(): void
    {
        $defaultShipping = $this->makeAddress(AddressType::Shipping, isDefault: true);
        $otherShipping   = $this->makeAddress(AddressType::Shipping, isDefault: false);

        $member = $this->makeMember([$otherShipping, $defaultShipping]);

        $this->assertSame($defaultShipping, $member->resolveBillingAddress());
    }

    public function test_returns_any_shipping_when_no_default_shipping(): void
    {
        $shipping = $this->makeAddress(AddressType::Shipping, isDefault: false);

        $member = $this->makeMember([$shipping]);

        $this->assertSame($shipping, $member->resolveBillingAddress());
    }

    public function test_returns_default_address_when_no_typed_addresses(): void
    {
        $defaultAddress = $this->makeAddress(type: null, isDefault: true);
        $otherAddress   = $this->makeAddress(type: null, isDefault: false);

        $member = $this->makeMember([$otherAddress, $defaultAddress]);

        $this->assertSame($defaultAddress, $member->resolveBillingAddress());
    }

    public function test_returns_first_address_as_last_resort(): void
    {
        $first  = $this->makeAddress(type: null, isDefault: false);
        $second = $this->makeAddress(type: null, isDefault: false);

        $member = $this->makeMember([$first, $second]);

        $this->assertSame($first, $member->resolveBillingAddress());
    }

    public function test_returns_null_when_member_has_no_addresses(): void
    {
        $member = $this->makeMember([]);

        $this->assertNull($member->resolveBillingAddress());
    }

    public function test_billing_takes_priority_over_shipping_regardless_of_default(): void
    {
        $defaultShipping = $this->makeAddress(AddressType::Shipping, isDefault: true);
        $nonDefaultBilling = $this->makeAddress(AddressType::Billing, isDefault: false);

        $member = $this->makeMember([$defaultShipping, $nonDefaultBilling]);

        // Any billing address beats a default shipping address
        $this->assertSame($nonDefaultBilling, $member->resolveBillingAddress());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeAddress(?AddressType $type, bool $isDefault): Address
    {
        $address = m::mock(Address::class)->makePartial();
        $address->type       = $type?->value;
        $address->is_default = $isDefault;

        return $address;
    }

    private function makeMember(array $addresses): Member
    {
        $member = m::mock(Member::class)->makePartial();
        $member->shouldReceive('getAttribute')
            ->with('addresses')
            ->andReturn(collect($addresses));
        $member->addresses = collect($addresses);

        return $member;
    }
}