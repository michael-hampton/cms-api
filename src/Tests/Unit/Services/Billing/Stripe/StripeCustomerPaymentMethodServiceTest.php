<?php

namespace App\Tests\Unit\Services\Billing\Stripe;

use App\DTO\Billing\PaymentMethodDto;
use App\Models\Member;
use App\Services\Billing\Stripe\StripeCustomerPaymentMethodService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Stripe\Service\CustomerService;
use Stripe\Service\PaymentMethodService;
use Stripe\StripeClient;

class StripeCustomerPaymentMethodServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_get_customer_payment_methods_returns_empty_shape_without_customer(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->stripe_customer_id = null;

        $service = new StripeCustomerPaymentMethodService(Mockery::mock(StripeClient::class));

        $this->assertSame([
            'payment_methods' => [],
            'default_payment_method_id' => null,
        ], $service->getCustomerPaymentMethods($member));
    }

    public function test_get_customer_payment_methods_returns_methods_and_default(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->stripe_customer_id = 'cus_123';
        $paymentMethod = (object) [
            'id' => 'pm_1',
            'type' => 'card',
            'card' => (object) [
                'brand' => 'visa',
                'last4' => '4242',
                'exp_month' => 8,
                'exp_year' => 2028,
            ],
        ];

        $customerService = Mockery::mock(CustomerService::class);
        $customerService->shouldReceive('retrieve')
            ->once()
            ->with('cus_123')
            ->andReturn((object) ['invoice_settings' => (object) ['default_payment_method' => 'pm_1']]);

        $paymentMethodService = Mockery::mock(PaymentMethodService::class);
        $paymentMethodService->shouldReceive('all')
            ->once()
            ->with(['customer' => 'cus_123', 'type' => 'card'])
            ->andReturn((object) ['data' => [$paymentMethod]]);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->customers = $customerService;
        $stripe->paymentMethods = $paymentMethodService;

        $service = new StripeCustomerPaymentMethodService($stripe);

        $result = $service->getCustomerPaymentMethods($member);

        $this->assertTrue($result['success']);
        $this->assertSame('pm_1', $result['default_payment_method_id']);
        $this->assertCount(1, $result['payment_methods']);
        $this->assertContainsOnlyInstancesOf(PaymentMethodDto::class, $result['payment_methods']);

        $method = $result['payment_methods'][0];

        $this->assertSame('pm_1', $method->id);
        $this->assertSame('card', $method->type);
        $this->assertSame('visa', $method->brand);
        $this->assertSame('4242', $method->last4);
        $this->assertSame(8, $method->expMonth);
        $this->assertSame(2028, $method->expYear);
        $this->assertTrue($method->isDefault);
        $this->assertTrue($method->canRemove);
    }

    public function test_get_member_payment_methods_returns_payment_method_dtos(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->stripe_customer_id = 'cus_123';
        $paymentMethod = (object) [
            'id' => 'pm_1',
            'type' => 'card',
            'card' => (object) [
                'brand' => 'visa',
                'last4' => '4242',
                'exp_month' => 8,
                'exp_year' => 2028,
                'funding' => 'credit',
            ],
            'billing_details' => (object) [
                'name' => 'Test User',
                'email' => 'member@example.com',
            ],
            'created' => 1609459200,
        ];

        $paymentMethodService = Mockery::mock(PaymentMethodService::class);
        $paymentMethodService->shouldReceive('all')
            ->once()
            ->with(['customer' => 'cus_123', 'type' => 'card'])
            ->andReturn((object) ['data' => [$paymentMethod]]);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->paymentMethods = $paymentMethodService;

        $service = new StripeCustomerPaymentMethodService($stripe);

        $methods = $service->getMemberPaymentMethods($member);

        $this->assertCount(1, $methods);
        $this->assertContainsOnlyInstancesOf(PaymentMethodDto::class, $methods);

        $method = $methods[0];

        $this->assertSame('pm_1', $method->id);
        $this->assertSame('card', $method->type);
        $this->assertSame('visa', $method->brand);
        $this->assertSame('4242', $method->last4);
        $this->assertSame(8, $method->expMonth);
        $this->assertSame(2028, $method->expYear);
        $this->assertSame('credit', $method->funding);
        $this->assertSame('Test User', $method->billingName);
        $this->assertSame('member@example.com', $method->billingEmail);
        $this->assertSame(1609459200, $method->created);
        $this->assertFalse($method->isDefault);
        $this->assertTrue($method->canRemove);
    }

    public function test_add_payment_method_creates_customer_when_missing(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 5;
        $member->site_id = 9;
        $member->email = 'member@example.com';
        $member->first_name = 'Test';
        $member->last_name = 'User';
        $member->stripe_customer_id = null;
        $member->shouldReceive('getAttribute')->with('full_name')->andReturn('Test User');
        $member->shouldReceive('update')->once()->with(['stripe_customer_id' => 'cus_123']);

        $customerService = Mockery::mock(CustomerService::class);
        $customerService->shouldReceive('create')
            ->once()
            ->andReturn((object) ['id' => 'cus_123']);
        $customerService->shouldReceive('update')
            ->once()
            ->with('cus_123', ['invoice_settings' => ['default_payment_method' => 'pm_123']]);

        $paymentMethodService = Mockery::mock(PaymentMethodService::class);
        $paymentMethodService->shouldReceive('retrieve')
            ->once()
            ->with('pm_123')
            ->andReturn((object) ['customer' => null]);
        $paymentMethodService->shouldReceive('attach')
            ->once()
            ->with('pm_123', ['customer' => 'cus_123']);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->customers = $customerService;
        $stripe->paymentMethods = $paymentMethodService;

        $service = new StripeCustomerPaymentMethodService($stripe);

        $this->assertSame(['success' => true], $service->addPaymentMethod($member, 'pm_123', true));
    }

    public function test_set_default_payment_method_returns_success(): void
    {
        $paymentMethodService = Mockery::mock(PaymentMethodService::class);
        $paymentMethodService->shouldReceive('retrieve')
            ->once()
            ->with('pm_123')
            ->andReturn((object) ['customer' => 'cus_123']);

        $customerService = Mockery::mock(CustomerService::class);
        $customerService->shouldReceive('update')
            ->once()
            ->with('cus_123', ['invoice_settings' => ['default_payment_method' => 'pm_123']]);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->customers = $customerService;
        $stripe->paymentMethods = $paymentMethodService;

        $service = new StripeCustomerPaymentMethodService($stripe);

        $this->assertSame(['success' => true], $service->setDefaultPaymentMethod('cus_123', 'pm_123'));
    }

    public function test_set_default_payment_method_rejects_other_customer_method(): void
    {
        $paymentMethodService = Mockery::mock(PaymentMethodService::class);
        $paymentMethodService->shouldReceive('retrieve')
            ->once()
            ->with('pm_123')
            ->andReturn((object) ['customer' => 'cus_other']);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->paymentMethods = $paymentMethodService;

        $service = new StripeCustomerPaymentMethodService($stripe);

        $this->assertSame([
            'success' => false,
            'message' => 'Unauthorized',
            'error_code' => 'unauthorized',
        ], $service->setDefaultPaymentMethod('cus_123', 'pm_123'));
    }

    public function test_remove_payment_method_rejects_when_method_belongs_to_other_customer(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->stripe_customer_id = 'cus_123';

        $paymentMethodService = Mockery::mock(PaymentMethodService::class);
        $paymentMethodService->shouldReceive('retrieve')
            ->once()
            ->with('pm_123')
            ->andReturn((object) ['customer' => 'cus_other']);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->paymentMethods = $paymentMethodService;

        $service = new StripeCustomerPaymentMethodService($stripe);

        $this->assertSame([
            'success' => false,
            'message' => 'Unauthorized',
            'error_code' => 'unauthorized',
        ], $service->removePaymentMethod($member, 'pm_123'));
    }
}
