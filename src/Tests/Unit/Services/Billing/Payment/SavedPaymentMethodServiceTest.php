<?php

namespace App\Tests\Unit\Services\Billing\Payment;

use App\Models\Member;
use App\Services\Billing\Payments\SavedPaymentMethodService;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Stripe\StripeClient;

class SavedPaymentMethodServiceTest extends TestCase
{
    private $stripe;
    private $service;

    public function testGetMemberPaymentMethodsReturnsEmpty(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->stripe_customer_id = null;

        $result = $this->service->getMemberPaymentMethods($member);

        $this->assertEmpty($result);
    }

    public function testGetMemberPaymentMethodsSuccess(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->stripe_customer_id = 'cus_123';

        $pm1 = (object)[
            'id' => 'pm_123',
            'type' => 'card',
            'card' => (object)[
                'brand' => 'visa',
                'last4' => '4242',
                'exp_month' => 12,
                'exp_year' => 2025,
                'funding' => 'credit',
            ],
            'billing_details' => (object)[
                'name' => 'John Doe',
                'email' => 'john@example.com',
            ],
            'created' => 1609459200,
        ];

        // 👇 real Stripe Collection, not a mock
        $collection = new \Stripe\Collection();
        $collection->data = [$pm1];

        $this->stripe->paymentMethods = m::mock();
        $this->stripe->paymentMethods
            ->shouldReceive('all')
            ->once()
            ->with([
                'customer' => 'cus_123',
                'type' => 'card',
            ])
            ->andReturn($collection);

        $result = $this->service->getMemberPaymentMethods($member);

        $this->assertCount(1, $result);
        $this->assertEquals('pm_123', $result[0]['id']);
        $this->assertEquals('visa', $result[0]['card']['brand']);
        $this->assertEquals('4242', $result[0]['card']['last4']);
        $this->assertEquals('John Doe', $result[0]['billing_details']['name']);
    }

    public function testGetMemberPaymentMethodsHandlesException(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->id = 1;
        $member->stripe_customer_id = 'cus_123';

        $this->stripe->paymentMethods = m::mock();
        $this->stripe->paymentMethods->shouldReceive('all')
            ->once()
            ->andThrow(new \Exception('Stripe API error'));

        $result = $this->service->getMemberPaymentMethods($member);

        $this->assertEmpty($result);
    }

    public function testGetDefaultPaymentMethodReturnsNull(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->stripe_customer_id = null;

        $result = $this->service->getDefaultPaymentMethod($member);

        $this->assertNull($result);
    }

    public function testGetDefaultPaymentMethodSuccess(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->stripe_customer_id = 'cus_123';

        $customer = m::mock();
        $customer->invoice_settings = (object)[
            'default_payment_method' => 'pm_default'
        ];

        $pm = m::mock();
        $pm->id = 'pm_default';
        $pm->type = 'card';
        $pm->card = (object)[
            'brand' => 'mastercard',
            'last4' => '5555',
            'exp_month' => 6,
            'exp_year' => 2026,
            'funding' => 'debit'
        ];
        $pm->billing_details = (object)[
            'name' => 'Jane Doe',
            'email' => 'jane@example.com'
        ];

        $this->stripe->customers = m::mock();
        $this->stripe->customers->shouldReceive('retrieve')
            ->with('cus_123')
            ->once()
            ->andReturn($customer);

        $this->stripe->paymentMethods = m::mock();
        $this->stripe->paymentMethods->shouldReceive('retrieve')
            ->with('pm_default')
            ->once()
            ->andReturn($pm);

        $result = $this->service->getDefaultPaymentMethod($member);

        $this->assertEquals('pm_default', $result['id']);
        $this->assertTrue($result['is_default']);
        $this->assertEquals('mastercard', $result['card']['brand']);
    }

    public function testGetDefaultPaymentMethodWhenNoneSet(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->stripe_customer_id = 'cus_123';

        $customer = m::mock();
        $customer->invoice_settings = (object)[
            'default_payment_method' => null
        ];

        $this->stripe->customers = m::mock();
        $this->stripe->customers->shouldReceive('retrieve')
            ->once()
            ->andReturn($customer);

        $result = $this->service->getDefaultPaymentMethod($member);

        $this->assertNull($result);
    }

    public function testSetDefaultPaymentMethodSuccess(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->stripe_customer_id = 'cus_123';

        $pm = m::mock();
        $pm->customer = 'cus_123';

        $this->stripe->paymentMethods = m::mock();
        $this->stripe->paymentMethods->shouldReceive('retrieve')
            ->with('pm_456')
            ->once()
            ->andReturn($pm);

        $this->stripe->customers = m::mock();
        $this->stripe->customers->shouldReceive('update')
            ->with('cus_123', [
                'invoice_settings' => [
                    'default_payment_method' => 'pm_456'
                ]
            ])
            ->once();

        $this->service->setDefaultPaymentMethod($member, 'pm_456');

        $this->assertTrue(true); // If no exception, test passes
    }

    public function testSetDefaultPaymentMethodAttachesIfNeeded(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->stripe_customer_id = 'cus_123';

        $pm = m::mock();
        $pm->customer = 'cus_different';

        $this->stripe->paymentMethods = m::mock();
        $this->stripe->paymentMethods->shouldReceive('retrieve')
            ->with('pm_456')
            ->once()
            ->andReturn($pm);

        $this->stripe->paymentMethods->shouldReceive('attach')
            ->with('pm_456', ['customer' => 'cus_123'])
            ->once();

        $this->stripe->customers = m::mock();
        $this->stripe->customers->shouldReceive('update')
            ->once();

        $this->service->setDefaultPaymentMethod($member, 'pm_456');

        $this->assertTrue(true);
    }

    public function testSetDefaultPaymentMethodFailsWithoutCustomerId(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->stripe_customer_id = null;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Member does not have a Stripe customer ID');

        $this->service->setDefaultPaymentMethod($member, 'pm_456');
    }

    public function testDetachPaymentMethodSuccess(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->stripe_customer_id = 'cus_123';

        $pm = m::mock();
        $pm->customer = 'cus_123';

        $this->stripe->paymentMethods = m::mock();
        $this->stripe->paymentMethods->shouldReceive('retrieve')
            ->with('pm_456')
            ->once()
            ->andReturn($pm);

        $this->stripe->paymentMethods->shouldReceive('detach')
            ->with('pm_456')
            ->once();

        $result = $this->service->detachPaymentMethod($member, 'pm_456');

        $this->assertTrue($result);
    }

    public function testDetachPaymentMethodFailsWrongCustomer(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->stripe_customer_id = 'cus_123';

        $pm = m::mock();
        $pm->customer = 'cus_different';

        $this->stripe->paymentMethods = m::mock();
        $this->stripe->paymentMethods->shouldReceive('retrieve')
            ->once()
            ->andReturn($pm);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Payment method does not belong to this customer');

        $this->service->detachPaymentMethod($member, 'pm_456');
    }

    public function testCreateSetupIntentWithExistingCustomer(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->stripe_customer_id = 'cus_123';

        $setupIntent = m::mock();
        $setupIntent->client_secret = 'seti_secret_123';
        $setupIntent->id = 'seti_123';

        $this->stripe->setupIntents = m::mock();
        $this->stripe->setupIntents->shouldReceive('create')
            ->with([
                'customer' => 'cus_123',
                'payment_method_types' => ['card'],
                'usage' => 'off_session'
            ])
            ->once()
            ->andReturn($setupIntent);

        $result = $this->service->createSetupIntent($member);

        $this->assertTrue($result['success']);
        $this->assertEquals('seti_secret_123', $result['client_secret']);
        $this->assertEquals('seti_123', $result['setup_intent_id']);
    }

    public function testCreateSetupIntentCreatesCustomer(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->id = 1;
        $member->stripe_customer_id = null;
        $member->email = 'john@example.com';
        $member->first_name = 'John';
        $member->last_name = 'Doe';

        $customer = m::mock();
        $customer->id = 'cus_new123';

        $setupIntent = m::mock();
        $setupIntent->client_secret = 'seti_secret_123';
        $setupIntent->id = 'seti_123';

        $this->stripe->customers = m::mock();
        $this->stripe->customers->shouldReceive('create')
            ->with([
                'email' => 'john@example.com',
                'name' => 'John Doe',
                'metadata' => [
                    'member_id' => 1
                ]
            ])
            ->once()
            ->andReturn($customer);

        $member->shouldReceive('update')
            ->with(['stripe_customer_id' => 'cus_new123'])
            ->once();

        $this->stripe->setupIntents = m::mock();
        $this->stripe->setupIntents->shouldReceive('create')
            ->once()
            ->andReturn($setupIntent);

        $result = $this->service->createSetupIntent($member);

        $this->assertTrue($result['success']);
    }

    public function testCreateSetupIntentHandlesException(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->stripe_customer_id = 'cus_123';

        $this->stripe->setupIntents = m::mock();
        $this->stripe->setupIntents->shouldReceive('create')
            ->once()
            ->andThrow(new \Exception('Stripe error'));

        $result = $this->service->createSetupIntent($member);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Failed to create setup intent', $result['message']);
    }

    public function testVerifyPaymentMethodOwnershipSuccess(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->stripe_customer_id = 'cus_123';

        $pm = m::mock();
        $pm->customer = 'cus_123';

        $this->stripe->paymentMethods = m::mock();
        $this->stripe->paymentMethods->shouldReceive('retrieve')
            ->with('pm_456')
            ->once()
            ->andReturn($pm);

        $result = $this->service->verifyPaymentMethodOwnership($member, 'pm_456');

        $this->assertTrue($result);
    }

    public function testVerifyPaymentMethodOwnershipFails(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->stripe_customer_id = 'cus_123';

        $pm = m::mock();
        $pm->customer = 'cus_different';

        $this->stripe->paymentMethods = m::mock();
        $this->stripe->paymentMethods->shouldReceive('retrieve')
            ->once()
            ->andReturn($pm);

        $result = $this->service->verifyPaymentMethodOwnership($member, 'pm_456');

        $this->assertFalse($result);
    }

    public function testVerifyPaymentMethodOwnershipNoCustomerId(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->stripe_customer_id = null;

        $result = $this->service->verifyPaymentMethodOwnership($member, 'pm_456');

        $this->assertFalse($result);
    }

    public function testVerifyPaymentMethodOwnershipHandlesException(): void
    {
        $member = m::mock(Member::class)->makePartial();
        $member->stripe_customer_id = 'cus_123';

        $this->stripe->paymentMethods = m::mock();
        $this->stripe->paymentMethods->shouldReceive('retrieve')
            ->once()
            ->andThrow(new \Exception('Stripe error'));

        $result = $this->service->verifyPaymentMethodOwnership($member, 'pm_456');

        $this->assertFalse($result);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->stripe = m::mock(StripeClient::class);
        $this->service = new SavedPaymentMethodService($this->stripe);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}