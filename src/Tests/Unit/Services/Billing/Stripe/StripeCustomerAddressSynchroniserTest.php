<?php

namespace App\Tests\Unit\Services\Billing\Stripe;

use App\DTO\Stripe\BillingAddressData;
use App\Services\Billing\Stripe\StripeCustomerAddressSynchroniser;
use Mockery as m;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Stripe\Customer;
use Stripe\Service\CustomerService;
use Stripe\StripeClient;

class StripeCustomerAddressSynchroniserTest extends TestCase
{
    private MockInterface                       $stripeClient;
    private MockInterface                       $customers;
    private StripeCustomerAddressSynchroniser   $synchroniser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customers   = m::mock(CustomerService::class);
        $this->stripeClient = m::mock(StripeClient::class)->makePartial();
        $this->stripeClient->customers = $this->customers;

        $this->synchroniser = new StripeCustomerAddressSynchroniser($this->stripeClient);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    // ── No-op when addresses match ────────────────────────────────────────────

    public function test_does_not_call_update_when_address_is_unchanged(): void
    {
        $billingAddress = $this->makeAddress('123 Same Street', 'London', 'SW1 1AA', 'GB');

        $stripeCustomer = $this->makeStripeCustomer([
            'line1'       => '123 Same Street',
            'city'        => 'London',
            'postal_code' => 'SW1 1AA',
            'country'     => 'GB',
        ]);

        $this->customers
            ->shouldReceive('retrieve')
            ->once()
            ->with('cus_test')
            ->andReturn($stripeCustomer);

        $this->customers->shouldNotReceive('update');

        $this->synchroniser->sync('cus_test', $billingAddress);

        $this->assertTrue(true);
    }

    public function test_treats_null_and_empty_string_as_equivalent_when_comparing(): void
    {
        // Local address has null line2; Stripe has empty string — should not trigger update
        $billingAddress = new BillingAddressData(
            line1:    '1 Main Road',
            line2:    null,
            city:     'Cardiff',
            state:    null,
            postcode: 'CF1 1AA',
            country:  'GB',
        );

        $stripeCustomer = $this->makeStripeCustomer([
            'line1'       => '1 Main Road',
            'line2'       => '',        // empty string ≡ null
            'city'        => 'Cardiff',
            'state'       => '',        // empty string ≡ null
            'postal_code' => 'CF1 1AA',
            'country'     => 'GB',
        ]);

        $this->customers
            ->shouldReceive('retrieve')
            ->once()
            ->andReturn($stripeCustomer);

        $this->customers->shouldNotReceive('update');

        $this->synchroniser->sync('cus_test', $billingAddress);

        $this->assertTrue(true);
    }

    // ── Updates when address differs ──────────────────────────────────────────

    public function test_calls_update_when_line1_has_changed(): void
    {
        $billingAddress = $this->makeAddress('456 New Street', 'London', 'SW1 1AA', 'GB');

        $stripeCustomer = $this->makeStripeCustomer([
            'line1'       => '123 Old Street',
            'city'        => 'London',
            'postal_code' => 'SW1 1AA',
            'country'     => 'GB',
        ]);

        $this->customers
            ->shouldReceive('retrieve')
            ->once()
            ->andReturn($stripeCustomer);

        $this->customers
            ->shouldReceive('update')
            ->once()
            ->with('cus_test', m::on(fn (array $p) =>
                $p['address']['line1'] === '456 New Street'
            ));

        $this->synchroniser->sync('cus_test', $billingAddress);

        $this->assertTrue(true);
    }

    public function test_calls_update_when_country_has_changed(): void
    {
        $billingAddress = new BillingAddressData(
            line1:    '1 Road',
            line2:    null,
            city:     'Paris',
            state:    null,
            postcode: '75001',
            country:  'FR',
        );

        $stripeCustomer = $this->makeStripeCustomer([
            'line1'       => '1 Road',
            'city'        => 'Paris',
            'postal_code' => '75001',
            'country'     => 'GB', // changed
        ]);

        $this->customers->shouldReceive('retrieve')->andReturn($stripeCustomer);

        $this->customers
            ->shouldReceive('update')
            ->once()
            ->with('cus_test', m::on(fn (array $p) =>
                $p['address']['country'] === 'FR'
            ));

        $this->synchroniser->sync('cus_test', $billingAddress);

        $this->assertTrue(true);
    }

    public function test_calls_update_when_postcode_has_changed(): void
    {
        $billingAddress = $this->makeAddress('1 Road', 'London', 'EC1 1AA', 'GB');

        $stripeCustomer = $this->makeStripeCustomer([
            'line1'       => '1 Road',
            'city'        => 'London',
            'postal_code' => 'SW1 1BB', // old postcode
            'country'     => 'GB',
        ]);

        $this->customers->shouldReceive('retrieve')->andReturn($stripeCustomer);

        $this->customers
            ->shouldReceive('update')
            ->once()
            ->with('cus_test', m::on(fn (array $p) =>
                $p['address']['postal_code'] === 'EC1 1AA'
            ));

        $this->synchroniser->sync('cus_test', $billingAddress);

        $this->assertTrue(true);
    }

    // ── Update contains correct Stripe payload ────────────────────────────────

    public function test_update_payload_uses_to_stripe_format(): void
    {
        $billingAddress = new BillingAddressData(
            line1:    '7 Oak Avenue',
            line2:    'Flat 3',
            city:     'Edinburgh',
            state:    'Scotland',
            postcode: 'EH1 1AB',
            country:  'GB',
        );

        $stripeCustomer = $this->makeStripeCustomer([
            'line1'   => 'old',
            'country' => 'US',
        ]);

        $this->customers->shouldReceive('retrieve')->andReturn($stripeCustomer);

        $this->customers
            ->shouldReceive('update')
            ->once()
            ->with('cus_test', m::on(function (array $p) {
                $a = $p['address'];
                return $a['line1']       === '7 Oak Avenue'
                    && $a['line2']       === 'Flat 3'
                    && $a['city']        === 'Edinburgh'
                    && $a['state']       === 'Scotland'
                    && $a['postal_code'] === 'EH1 1AB'
                    && $a['country']     === 'GB';
            }));

        $this->synchroniser->sync('cus_test', $billingAddress);

        $this->assertTrue(true);
    }

    // ── Stripe customer with null address ─────────────────────────────────────

    public function test_calls_update_when_stripe_customer_has_no_address(): void
    {
        $billingAddress = $this->makeAddress('1 New Lane', 'Bristol', 'BS1 1AA', 'GB');

        $stripeCustomer = $this->makeStripeCustomer(null); // no address on Stripe

        $this->customers->shouldReceive('retrieve')->andReturn($stripeCustomer);

        $this->customers
            ->shouldReceive('update')
            ->once()
            ->with('cus_test', m::on(fn (array $p) =>
                $p['address']['country'] === 'GB'
            ));

        $this->synchroniser->sync('cus_test', $billingAddress);

        $this->assertTrue(true);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeAddress(
        string $line1,
        string $city,
        string $postcode,
        string $country,
    ): BillingAddressData {
        return new BillingAddressData(
            line1:    $line1,
            line2:    null,
            city:     $city,
            state:    null,
            postcode: $postcode,
            country:  $country,
        );
    }

    private function makeStripeCustomer(?array $address): Customer
    {
        return Customer::constructFrom([
            'id'      => 'cus_test',
            'object'  => 'customer',
            'address' => $address,
        ]);
    }
}