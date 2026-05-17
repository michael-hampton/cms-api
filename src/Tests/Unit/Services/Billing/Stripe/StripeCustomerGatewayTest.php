<?php

namespace App\Tests\Unit\Services\Billing\Stripe;

use App\Models\Address;
use App\Models\Member;
use App\Services\Billing\Stripe\StripeCustomerGateway;
use Mockery as m;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Stripe\Customer;
use Stripe\Exception\InvalidRequestException;
use Stripe\Service\CustomerService;
use Stripe\Service\PaymentMethodService;
use Stripe\StripeClient;

class StripeCustomerGatewayTest extends TestCase
{
    private MockInterface       $stripeClient;
    private MockInterface       $customers;
    private MockInterface       $paymentMethods;
    private StripeCustomerGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customers      = m::mock(CustomerService::class);
        $this->paymentMethods = m::mock(PaymentMethodService::class);

        $this->stripeClient = m::mock(StripeClient::class)->makePartial();
        $this->stripeClient->customers      = $this->customers;
        $this->stripeClient->paymentMethods = $this->paymentMethods;

        $this->gateway = new StripeCustomerGateway($this->stripeClient);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    // ── getOrCreate: existing customer ───────────────────────────────────────

    public function test_returns_existing_customer_id_when_member_has_stripe_customer_id(): void
    {
        $member = $this->makeMember(stripeCustomerId: 'cus_existing');

        $stripeCustomer = $this->makeStripeCustomer('cus_existing');

        $this->customers
            ->shouldReceive('retrieve')
            ->once()
            ->with('cus_existing')
            ->andReturn($stripeCustomer);

        $this->customers->shouldNotReceive('create');

        $result = $this->gateway->getOrCreate($member);

        $this->assertSame('cus_existing', $result);
    }

    public function test_does_not_update_member_when_reusing_existing_customer(): void
    {
        $member = $this->makeMember(stripeCustomerId: 'cus_existing');

        $this->customers
            ->shouldReceive('retrieve')
            ->andReturn($this->makeStripeCustomer('cus_existing'));

        $member->shouldNotReceive('update');

        $this->gateway->getOrCreate($member);

        $this->assertTrue(true);
    }

    // ── getOrCreate: customer not found in Stripe ─────────────────────────────

    public function test_creates_new_customer_when_stripe_returns_not_found(): void
    {
        $member = $this->makeMember(stripeCustomerId: 'cus_stale');

        $this->customers
            ->shouldReceive('retrieve')
            ->andThrow($this->makeStripeNotFoundException());

        $newCustomer = $this->makeStripeCustomer('cus_new');

        $this->customers
            ->shouldReceive('create')
            ->once()
            ->andReturn($newCustomer);

        $member->shouldReceive('update')
            ->once()
            ->with(['stripe_customer_id' => 'cus_new']);

        $result = $this->gateway->getOrCreate($member);

        $this->assertSame('cus_new', $result);
    }

    // ── getOrCreate: no existing customer ────────────────────────────────────

    public function test_creates_new_customer_when_member_has_no_stripe_customer_id(): void
    {
        $member = $this->makeMember(
            stripeCustomerId: null
        );

        $customer = $this->makeStripeCustomer(
            'cus_created'
        );

        $this->customers
            ->shouldReceive('create')
            ->once()
            ->with(m::on(function (array $params) {

                $this->assertSame(
                    'test@example.com',
                    $params['email']
                );

                $this->assertArrayHasKey(
                    'name',
                    $params
                );

                $this->assertArrayHasKey(
                    'address',
                    $params
                );

                $this->assertSame(
                    1,
                    $params['metadata']['member_id']
                );

                $this->assertSame(
                    1,
                    $params['metadata']['site_id']
                );

                return true;
            }))
            ->andReturn($customer);

        $this->customers
            ->shouldNotReceive('retrieve');

        $member
            ->shouldReceive('update')
            ->once()
            ->with([
                'stripe_customer_id' => 'cus_created'
            ]);

        $result = $this->gateway->getOrCreate(
            $member
        );

        $this->assertSame(
            'cus_created',
            $result
        );
    }

    public function test_creates_customer_with_full_address_when_address_provided(): void
    {
        $member  = $this->makeMember(stripeCustomerId: null);
        $address = $this->makeAddress();

        $this->customers
            ->shouldReceive('create')
            ->once()
            ->with(m::on(function (array $params) {
                return $params['address']['line1']       === '123 Test Street'
                    && $params['address']['city']        === 'London'
                    && $params['address']['postal_code'] === 'SW1A 1AA'
                    && $params['address']['country']     === 'GB';
            }))
            ->andReturn($this->makeStripeCustomer('cus_new'));

        $member->shouldReceive('update')->once();

        $this->gateway->getOrCreate($member, $address);

        $this->assertTrue(true);
    }

    public function test_creates_customer_with_country_only_when_no_address_provided(): void
    {
        $member = $this->makeMember(stripeCustomerId: null);

        $this->customers
            ->shouldReceive('create')
            ->once()
            ->with(m::on(function (array $params) {
                return $params['address'] === ['country' => 'GB']
                    || (isset($params['address']['country'])
                        && count($params['address']) === 1);
            }))
            ->andReturn($this->makeStripeCustomer('cus_new'));

        $member->shouldReceive('update')->once();

        $this->gateway->getOrCreate($member, null);

        $this->assertTrue(true);
    }

    public function test_falls_back_to_gb_when_member_has_no_country(): void
    {
        $member = $this->makeMember(stripeCustomerId: null, country: null);

        $this->customers
            ->shouldReceive('create')
            ->once()
            ->with(m::on(fn (array $p) => $p['address']['country'] === 'GB'))
            ->andReturn($this->makeStripeCustomer('cus_new'));

        $member->shouldReceive('update')->once();

        $this->gateway->getOrCreate($member, null);

        $this->assertTrue(true);
    }

    // ── attachPaymentMethod ───────────────────────────────────────────────────

    public function test_attach_payment_method_attaches_and_sets_as_default(): void
    {
        $this->paymentMethods
            ->shouldReceive('attach')
            ->once()
            ->with(
                'pm_test',
                ['customer' => 'cus_test']
            );

        $this->customers
            ->shouldReceive('update')
            ->once()
            ->with(
                'cus_test',
                [
                    'invoice_settings' => [
                        'default_payment_method' => 'pm_test',
                    ],
                ]
            );

        $this->gateway->attachPaymentMethod(
            'cus_test',
            'pm_test'
        );

        $this->addToAssertionCount(1);
    }

    public function test_attach_payment_method_calls_attach_before_update(): void
    {
        $callOrder = [];

        $this->paymentMethods
            ->shouldReceive('attach')
            ->once()
            ->andReturnUsing(function () use (&$callOrder) {
                $callOrder[] = 'attach';
            });

        $this->customers
            ->shouldReceive('update')
            ->once()
            ->andReturnUsing(function () use (&$callOrder) {
                $callOrder[] = 'update';
            });

        $this->gateway->attachPaymentMethod('cus_test', 'pm_test');

        $this->assertSame(['attach', 'update'], $callOrder);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeMember(
        ?string $stripeCustomerId,
        ?string $country = 'GB',
    ): Member {
        $member = m::mock(Member::class)->makePartial();

        $member->id                 = 1;
        $member->site_id            = 1;
        $member->email              = 'test@example.com';
        $member->full_name          = 'Test Member';
        $member->country            = $country;
        $member->stripe_customer_id = $stripeCustomerId;

        return $member;
    }

    private function makeAddress(): Address
    {
        $address = m::mock(Address::class)->makePartial();

        $address->address_line_1 = '123 Test Street';
        $address->address_line_2 = '';
        $address->city           = 'London';
        $address->state          = '';
        $address->postcode       = 'SW1A 1AA';
        $address->country        = 'GB';

        return $address;
    }

    private function makeStripeCustomer(string $id): Customer
    {
        return Customer::constructFrom([
            'id' => $id,
            'object' => 'customer',
        ]);
    }


    private function makeStripeNotFoundException(): InvalidRequestException
    {
        $exception = m::mock(InvalidRequestException::class);

        $exception
            ->shouldReceive('getHttpStatus')
            ->andReturn(404);

        return $exception;
    }
}