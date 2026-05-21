<?php

namespace App\Tests\Unit\Services\Billing\Stripe;

use App\DTO\Stripe\BillingAddressData;
use PHPUnit\Framework\TestCase;

class BillingAddressDataTest extends TestCase
{
    // ── toStripe ──────────────────────────────────────────────────────────────

    public function test_to_stripe_maps_postcode_to_postal_code(): void
    {
        $dto = new BillingAddressData(
            line1:    '1 High Street',
            line2:    null,
            city:     'London',
            state:    null,
            postcode: 'SW1 1AA',
            country:  'GB',
        );

        $stripe = $dto->toStripe();

        $this->assertArrayHasKey('postal_code', $stripe);
        $this->assertSame('SW1 1AA', $stripe['postal_code']);
        $this->assertArrayNotHasKey('postcode', $stripe);
    }

    public function test_to_stripe_omits_null_fields(): void
    {
        $dto = new BillingAddressData(
            line1:    '1 Road',
            line2:    null,
            city:     null,
            state:    null,
            postcode: null,
            country:  'GB',
        );

        $stripe = $dto->toStripe();

        $this->assertArrayNotHasKey('line2',       $stripe);
        $this->assertArrayNotHasKey('city',        $stripe);
        $this->assertArrayNotHasKey('state',       $stripe);
        $this->assertArrayNotHasKey('postal_code', $stripe);
    }

    public function test_to_stripe_omits_empty_string_fields(): void
    {
        $dto = new BillingAddressData(
            line1:    '1 Road',
            line2:    '',
            city:     '',
            state:    '',
            postcode: '',
            country:  'GB',
        );

        $stripe = $dto->toStripe();

        $this->assertArrayNotHasKey('line2',       $stripe);
        $this->assertArrayNotHasKey('city',        $stripe);
        $this->assertArrayNotHasKey('state',       $stripe);
        $this->assertArrayNotHasKey('postal_code', $stripe);
    }

    public function test_to_stripe_includes_all_non_null_fields(): void
    {
        $dto = new BillingAddressData(
            line1:    '1 Road',
            line2:    'Apt 1',
            city:     'London',
            state:    'England',
            postcode: 'SW1 1AA',
            country:  'GB',
        );

        $stripe = $dto->toStripe();

        $this->assertCount(6, $stripe);
        $this->assertSame('1 Road',   $stripe['line1']);
        $this->assertSame('Apt 1',    $stripe['line2']);
        $this->assertSame('London',   $stripe['city']);
        $this->assertSame('England',  $stripe['state']);
        $this->assertSame('SW1 1AA',  $stripe['postal_code']);
        $this->assertSame('GB',       $stripe['country']);
    }

    // ── isUsable ──────────────────────────────────────────────────────────────

    public function test_is_usable_returns_true_when_country_is_set(): void
    {
        $dto = new BillingAddressData(null, null, null, null, null, 'GB');
        $this->assertTrue($dto->isUsable());
    }

    public function test_is_usable_returns_false_when_country_is_null(): void
    {
        $dto = new BillingAddressData('1 Road', null, 'London', null, 'SW1', null);
        $this->assertFalse($dto->isUsable());
    }

    public function test_is_usable_returns_false_when_country_is_empty_string(): void
    {
        $dto = new BillingAddressData('1 Road', null, 'London', null, 'SW1', '');
        $this->assertFalse($dto->isUsable());
    }

    // ── differsWith ──────────────────────────────────────────────────────────

    public function test_differs_with_returns_false_when_all_fields_match(): void
    {
        $dto = new BillingAddressData(
            line1:    '1 Road',
            line2:    null,
            city:     'London',
            state:    null,
            postcode: 'SW1 1AA',
            country:  'GB',
        );

        $stripeAddress = [
            'line1'       => '1 Road',
            'line2'       => null,
            'city'        => 'London',
            'state'       => null,
            'postal_code' => 'SW1 1AA',
            'country'     => 'GB',
        ];

        $this->assertFalse($dto->differsWith($stripeAddress));
    }

    public function test_differs_with_returns_true_when_line1_differs(): void
    {
        $dto = new BillingAddressData('New Street', null, 'London', null, 'SW1', 'GB');

        $this->assertTrue($dto->differsWith(['line1' => 'Old Street', 'country' => 'GB']));
    }

    public function test_differs_with_returns_true_when_country_differs(): void
    {
        $dto = new BillingAddressData('1 Road', null, 'London', null, 'SW1', 'FR');

        $this->assertTrue($dto->differsWith(['line1' => '1 Road', 'country' => 'GB']));
    }

    public function test_differs_with_treats_null_and_empty_string_as_equal(): void
    {
        $dto = new BillingAddressData('1 Road', null, null, null, 'SW1', 'GB');

        // Stripe has empty strings for line2, city, state
        $stripeAddress = [
            'line1'       => '1 Road',
            'line2'       => '',
            'city'        => '',
            'state'       => '',
            'postal_code' => 'SW1',
            'country'     => 'GB',
        ];

        $this->assertFalse($dto->differsWith($stripeAddress));
    }

    public function test_differs_with_returns_true_when_postcode_changes(): void
    {
        $dto = new BillingAddressData('1 Road', null, 'London', null, 'EC1 1AA', 'GB');

        $this->assertTrue($dto->differsWith([
            'line1'       => '1 Road',
            'city'        => 'London',
            'postal_code' => 'SW1 1BB',
            'country'     => 'GB',
        ]));
    }

    public function test_differs_with_returns_true_when_stripe_has_no_address_and_local_has_data(): void
    {
        $dto = new BillingAddressData('1 Road', null, 'London', null, 'SW1', 'GB');

        // Stripe address is empty (no prior address set)
        $this->assertTrue($dto->differsWith([]));
    }
}