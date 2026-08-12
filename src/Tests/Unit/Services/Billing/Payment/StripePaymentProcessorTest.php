<?php

namespace App\Tests\Unit\Services\Billing\Payment;

use App\Enums\Vouchers\VoucherType;
use App\Models\Member;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Voucher;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Billing\PaymentRepository;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use DateTime;
use Exception;
use Mockery as m;
use ReflectionClass;
use stdClass;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Price;
use Stripe\Service\CouponService;
use Stripe\Service\CustomerService;
use Stripe\Service\InvoiceService;
use Stripe\Service\PaymentIntentService;
use Stripe\Service\PaymentMethodService;
use Stripe\Service\PriceService;
use Stripe\Service\ProductService;
use Stripe\Service\RefundService;
use Stripe\Service\SubscriptionService;
use Stripe\StripeClient;

class StripePaymentProcessorTest extends FunctionalTestCase
{
    private $paymentRepository;
    private $processor;
    private $stripeMock;
    private $customerServiceMock;
    private $subscriptionServiceMock;
    private $paymentMethodServiceMock;
    private $productServiceMock;
    private $priceServiceMock;
    private $invoiceServiceMock;
    private $paymentIntentServiceMock;
    private $couponServiceMock;
    private OrderRepository $orderRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentRepository = m::mock(PaymentRepository::class);

        // Mock Stripe client and services
        $this->stripeMock = m::mock(StripeClient::class);
        $this->customerServiceMock = m::mock(CustomerService::class);
        $this->subscriptionServiceMock = m::mock(SubscriptionService::class);
        $this->paymentMethodServiceMock = m::mock(PaymentMethodService::class);
        $this->productServiceMock = m::mock(ProductService::class);
        $this->priceServiceMock = m::mock(PriceService::class);
        $this->invoiceServiceMock = m::mock(InvoiceService::class);
        $this->paymentIntentServiceMock = m::mock(PaymentIntentService::class);
        $this->couponServiceMock = m::mock(CouponService::class);
        $this->orderRepository = m::mock(OrderRepository::class);

        $this->stripeMock->customers = $this->customerServiceMock;
        $this->stripeMock->subscriptions = $this->subscriptionServiceMock;
        $this->stripeMock->paymentMethods = $this->paymentMethodServiceMock;
        $this->stripeMock->products = $this->productServiceMock;
        $this->stripeMock->prices = $this->priceServiceMock;
        $this->stripeMock->invoices = $this->invoiceServiceMock;
        $this->stripeMock->paymentIntents = $this->paymentIntentServiceMock;
        $this->stripeMock->coupons = $this->couponServiceMock;

        // Inject mocked Stripe client via constructor
        $this->processor = new StripePaymentProcessor(
            $this->paymentRepository,
            $this->orderRepository,
            $this->stripeMock
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
//    public function testProcessSubscriptionPaymentWithNewCustomer(): void
//    {
//        $member = $this->createMockMember(null);
//        $subscription = $this->createMockSubscription($member);
//        $plan = $this->createMockPlan();
//
//        // Setup expectations
//        $customer = $this->expectCustomerCreation($member);
//        $this->expectPaymentMethodAttachment('pm_test123', $customer->id);
//        $this->expectCustomerUpdate($customer->id, $customer);
//
//        $this->expectPriceCreation($plan, 2499); // assert correct amount on price
//        $stripeSubscription = $this->expectSubscriptionCreation('sub_test123', 'active', 'in_test123');
//        $this->expectInvoiceRetrieval('in_test123', 'pi_test123');
//
//        $member->shouldReceive('update')
//            ->once()
//            ->with(['stripe_customer_id' => $customer->id]);
//
//        $data = ['payment_method_id' => 'pm_test123'];
//
//        $result = $this->processor->processSubscriptionPayment($subscription, $plan, $data);
//
//        $this->assertTrue($result['success']);
//        $this->assertEquals('sub_test123', $result['subscription_id']);
//        $this->assertEquals($customer->id, $result['customer_id']);
//        $this->assertEquals('pi_test123', $result['payment_intent_id']);
//        $this->assertFalse($result['requires_action']);
//    }

    private function createMockMember(?string $stripeCustomerId): Member
    {
        $member = m::mock(Member::class)->makePartial();
        $member->id = 1;
        $member->email = 'test@example.com';
        $member->full_name = 'Test User';
        $member->first_name = 'Test';
        $member->last_name = 'User';
        $member->site_id = 1;
        $member->stripe_customer_id = $stripeCustomerId;

        return $member;
    }

    private function createMockSubscription(Member $member): Subscription
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->member_id = $member->id;
        $subscription->site_id = 1;
        $subscription->price = 24.99;
        $subscription->price_paid_cents = 2499;
        $subscription->currency = 'USD';
        $subscription->member = $member;

        return $subscription;
    }

    private function createMockPlan(): SubscriptionPlan
    {
        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;
        $plan->name = 'Premium Plan';
        $plan->description = 'Premium subscription';
        $plan->price = 29.99;
        $plan->currency = 'USD';
        $plan->billing_period = 'monthly';
        $plan->trial_days = 0;
        $plan->stripe_price_id = null;

        return $plan;
    }

    private function expectCustomerCreation(Member $member): stdClass
    {
        $customer = new stdClass();
        $customer->id = 'cus_test123';

        $this->customerServiceMock->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) use ($member) {
                return $data['email'] === $member->email
                    && $data['name'] === $member->full_name
                    && isset($data['metadata']['member_id'])
                    && $data['metadata']['member_id'] === $member->id;
            }))
            ->andReturn($customer);

        return $customer;
    }

    private function expectPaymentMethodAttachment(string $paymentMethodId, string $customerId): void
    {
        $paymentMethod = new stdClass();
        $paymentMethod->id = $paymentMethodId;

        $this->paymentMethodServiceMock->shouldReceive('attach')
            ->once()
            ->with($paymentMethodId, ['customer' => $customerId])
            ->andReturn($paymentMethod);
    }

    private function expectCustomerUpdate(string $customerId, stdClass $customer): void
    {
        $this->customerServiceMock->shouldReceive('update')
            ->once()
            ->with($customerId, m::type('array'))
            ->andReturn($customer);
    }

    private function expectPriceCreation(SubscriptionPlan $plan, ?int $expectedAmountCents = null): void
    {
        if ($plan->stripe_price_id) {
            return;
        }

        $product = new stdClass();
        $product->id = 'prod_test123';

        $this->productServiceMock->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) use ($plan) {
                return $data['name'] === $plan->name
                    && isset($data['metadata']['plan_id'])
                    && $data['metadata']['plan_id'] === $plan->id;
            }))
            ->andReturn($product);

        $price = new stdClass();
        $price->id = 'price_test123';

        $this->priceServiceMock->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) use ($expectedAmountCents) {
                return $data['product'] === 'prod_test123'
                    && ($expectedAmountCents === null || $data['unit_amount'] === $expectedAmountCents)
                    && isset($data['recurring'])
                    && isset($data['metadata']['plan_id'])
                    && $data['tax_behavior'] === 'exclusive'; // assert Stripe Tax is set
            }))
            ->andReturn($price);

        $plan->shouldReceive('update')
            ->once()
            ->with(['stripe_price_id' => 'price_test123']);
    }

    private function expectSubscriptionCreation(
        string $subscriptionId,
        string $status,
        string $invoiceId,
        ?int   $expectedAmount = null
    ): \Stripe\Subscription
    {
        $stripeSubscription = \Stripe\Subscription::constructFrom([
            'id' => $subscriptionId,
            'status' => $status,
            'latest_invoice' => $invoiceId,
        ]);

        $this->subscriptionServiceMock->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) {
                return isset($data['customer'])
                    && isset($data['items'])
                    && isset($data['metadata'])
                    && isset($data['items'][0]['price'])  // price ID, not price_data
                    && isset($data['automatic_tax'])       // Stripe Tax enabled
                    && $data['automatic_tax']['enabled'] === true;
            }))
            ->andReturn($stripeSubscription);

        return $stripeSubscription;
    }

    private function expectInvoiceRetrieval(string $invoiceId, ?string $paymentIntentId): void
    {
        $invoice = new stdClass();
        $invoice->id = $invoiceId;
        $invoice->payment_intent = $paymentIntentId;

        $this->invoiceServiceMock->shouldReceive('retrieve')
            ->once()
            ->with($invoiceId, m::type('array'))
            ->andReturn($invoice);
    }

    private function expectPaymentCreation(int $subscriptionId, ?float $expectedAmount = null): Payment
    {
        $payment = m::mock(Payment::class)->makePartial();
        $payment->id = 1;
        $payment->subscription_id = $subscriptionId;

        $this->paymentRepository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) use ($subscriptionId, $expectedAmount) {
                return $data['subscription_id'] === $subscriptionId
                    && ($expectedAmount === null || $data['amount'] === $expectedAmount);
            }))
            ->andReturn($payment);

        return $payment;
    }

//    public function testHandleWebhookInvoicePaymentSucceeded(): void
//    {
//        // Create a real Payment instance for the test database
//        $testPayment = Payment::create([
//            'site_id' => $this->siteId,
//            'payment_method' => 'stripe',
//            'payment_provider' => 'stripe',
//            'status' => 'pending',
//            'amount' => 29.99,
//            'currency' => 'USD',
//            'metadata' => ['stripe_subscription_id' => 'sub_test123']
//        ]);
//
//        $invoice = new \stdClass();
//        $invoice->id = 'in_test123';
//        $invoice->subscription = 'sub_test123';
//
//        $this->paymentRepository->shouldReceive('update')
//            ->once()
//            ->with($testPayment->id, m::on(function ($data) {
//                return $data['status'] === 'completed'
//                    && isset($data['paid_at']);
//            }));
//
//        $payload = [
//            'type' => 'invoice.payment_succeeded',
//            'data' => ['object' => $invoice]
//        ];
//
//        $result = $this->processor->handleWebhook($payload, 'test_signature');
//
//        $this->assertTrue($result['success']);
//
//        // Clean up
//        $testPayment->delete();
//    }

//    public function testHandleWebhookSubscriptionDeleted(): void
//    {
//        $stripeSubscription = m::mock(\stdClass::class);
//        $stripeSubscription->id = 'sub_test123';
//
//        $subscription = m::mock(Subscription::class)->makePartial();
//        $subscription->id = 1;
//
//        Subscription::shouldReceive('where')
//            ->once()
//            ->with('payment_subscription_id', 'sub_test123')
//            ->andReturnSelf();
//
//        Subscription::shouldReceive('first')
//            ->once()
//            ->andReturn($subscription);
//
//        $subscription->shouldReceive('update')
//            ->once()
//            ->with(['status' => 'cancelled']);
//
//        $payload = [
//            'type' => 'customer.subscription.deleted',
//            'data' => ['object' => $stripeSubscription]
//        ];
//
//        $result = $this->processor->handleWebhook($payload, 'test_signature');
//
//        $this->assertTrue($result['success']);
//    }

// Helper methods

    private function expectPaymentUpdate(int $paymentId, string $status): void
    {
        $this->paymentRepository->shouldReceive('update')
            ->once()
            ->with($paymentId, m::on(function ($data) use ($status) {
                return $data['status'] === $status
                    && isset($data['paid_at']);
            }))
            ->andReturn(m::mock(Payment::class));
    }

//    public function testProcessSubscriptionPaymentWithExistingCustomer(): void
//    {
//        $member = $this->createMockMember('cus_existing123');
//        $subscription = $this->createMockSubscription($member);
//        $plan = $this->createMockPlan();
//
//        // Setup expectations
//        $customer = $this->expectCustomerRetrieval('cus_existing123');
//        $this->expectPaymentMethodAttachment('pm_test123', $customer->id);
//        $this->expectCustomerUpdate($customer->id, $customer);
//
//        $this->expectPriceCreation($plan, 2499);
//
//        $stripeSubscription = $this->expectSubscriptionCreation('sub_test123', 'active', 'in_test123');
//        $this->expectInvoiceRetrieval('in_test123', 'pi_test123');
//
//        $result = $this->processor->processSubscriptionPayment(
//            $subscription,
//            $plan,
//            ['payment_method_id' => 'pm_test123']
//        );
//
//        $this->assertTrue($result['success']);
//    }

    private function expectCustomerRetrieval(string $customerId): stdClass
    {
        $customer = new stdClass();
        $customer->id = $customerId;

        $this->customerServiceMock->shouldReceive('retrieve')
            ->once()
            ->with($customerId)
            ->andReturn($customer);

        return $customer;
    }

//    public function testProcessSubscriptionPaymentRequiresAction(): void
//    {
//        $member = $this->createMockMember('cus_test123');
//        $subscription = $this->createMockSubscription($member);
//        $plan = $this->createMockPlan();
//
//        $this->expectPriceCreation($plan, 2499);
//
//        // Setup expectations
//        $customer = $this->expectCustomerRetrieval('cus_test123');
//        $this->expectPaymentMethodAttachment('pm_test123', $customer->id);
//        $this->expectCustomerUpdate($customer->id, $customer);
//
//        // Create subscription that requires action
//        $paymentIntent = new stdClass();
//        $paymentIntent->id = 'pi_test123';
//        $paymentIntent->status = 'requires_action';
//        $paymentIntent->client_secret = 'pi_test123_secret_abc';
//
//        $invoice = new stdClass();
//        $invoice->payment_intent = $paymentIntent;
//
//        $stripeSubscription = \Stripe\Subscription::constructFrom([
//            'id' => 'sub_test123',
//            'status' => 'incomplete',
//            'latest_invoice' => $invoice,
//        ]);
//
//        $this->subscriptionServiceMock->shouldReceive('create')
//            ->once()
//            ->andReturn($stripeSubscription);
//
//        $result = $this->processor->processSubscriptionPayment(
//            $subscription,
//            $plan,
//            ['payment_method_id' => 'pm_test123']
//        );
//
//        $this->assertTrue($result['success']);
//        $this->assertTrue($result['requires_action']);
//        $this->assertEquals('pi_test123_secret_abc', $result['payment_intent_client_secret']);
//    }
//
//    public function testProcessSubscriptionPaymentWithTrialPeriod(): void
//    {
//        $member = $this->createMockMember('cus_test123');
//
//        $subscription = $this->createMockSubscription($member);
//        $subscription->price_paid_cents = 1000;
//
//        $plan = $this->createMockPlan();
//        $plan->trial_days = 14;
//        $plan->currency = 'GBP';
//        $plan->billing_period = 'monthly';
//        $plan->stripe_price_id = 'price_test123';
//
//        // Existing customer
//        $customer = $this->expectCustomerRetrieval('cus_test123');
//
//        // Attach payment method
//        $this->expectPaymentMethodAttachment('pm_test123', $customer->id);
//
//        // Set default payment method
//        $this->expectCustomerUpdate($customer->id, $customer);
//
//        // Existing Stripe price
//        $stripePrice = Price::constructFrom([
//            'id' => 'price_test123',
//            'unit_amount' => 1000,
//        ]);
//
//        $this->priceServiceMock->shouldReceive('retrieve')
//            ->once()
//            ->with('price_test123')
//            ->andReturn($stripePrice);
//
//        // Trial subscription
//        $stripeSubscription = \Stripe\Subscription::constructFrom([
//            'id' => 'sub_test123',
//            'status' => 'trialing',
//            'latest_invoice' => 'in_test123',
//            'current_period_start' => time(),
//            'current_period_end' => strtotime('+14 days'),
//        ]);
//
//        $this->subscriptionServiceMock->shouldReceive('create')
//            ->once()
//            ->with(m::on(function (array $data) {
//                return isset($data['trial_period_days'])
//                    && $data['trial_period_days'] === 14;
//            }))
//            ->andReturn($stripeSubscription);
//
//        // Trial invoice
//        $invoice = new stdClass();
//        $invoice->payment_intent = null;
//        $invoice->amount_due = 0;
//        $invoice->tax = 0;
//
//        $this->invoiceServiceMock->shouldReceive('retrieve')
//            ->once()
//            ->with('in_test123', [
//                'expand' => ['payment_intent']
//            ])
//            ->andReturn($invoice);
//
//        $result = $this->processor->processSubscriptionPayment(
//            $subscription,
//            $plan,
//            [
//                'payment_method_id' => 'pm_test123'
//            ]
//        );
//
//        $this->assertTrue($result['success']);
//        $this->assertEquals('trialing', $result['status']);
//        $this->assertEquals('sub_test123', $result['subscription_id']);
//        $this->assertNull($result['paymentIntentId']);
//    }
//
//    public function testProcessSubscriptionPaymentUsesSubscriptionPricePaidCentsForStripeAmount(): void
//    {
//        $member = $this->createMockMember('cus_test123');
//        $subscription = $this->createMockSubscription($member);
//        $plan = $this->createMockPlan();
//
//        // Setup expectations
//        $customer = $this->expectCustomerRetrieval('cus_test123');
//        $this->expectPaymentMethodAttachment('pm_test123', $customer->id);
//        $this->expectCustomerUpdate($customer->id, $customer);
//        $subscription->price_paid_cents = 1599;
//        $subscription->price = 15.99;
//
//        $this->expectPriceCreation($plan, 1599); // 1599 cents from subscription->price_paid_cents
//
//        $stripeSubscription = $this->expectSubscriptionCreation('sub_test123', 'active', 'in_test123');
//        $this->expectInvoiceRetrieval('in_test123', 'pi_test123');
//
//        $result = $this->processor->processSubscriptionPayment(
//            $subscription,
//            $plan,
//            ['payment_method_id' => 'pm_test123']
//        );
//
//        $this->assertTrue($result['success']);
//    }

//    public function testProcessSubscriptionPaymentHandlesStripeError(): void
//    {
//        $member = $this->createMockMember('cus_test123');
//        $subscription = $this->createMockSubscription($member);
//        $plan = $this->createMockPlan();
//
//        $customer = $this->expectCustomerRetrieval('cus_test123');
//
//        // Mock payment method attachment failure
//        $exception = m::mock(CardException::class);
//        $exception->shouldReceive('getMessage')->andReturn('Your card was declined');
//        $exception->shouldReceive('getStripeCode')->andReturn('card_declined');
//
//        $this->paymentMethodServiceMock->shouldReceive('attach')
//            ->once()
//            ->andThrow($exception);
//
//        $result = $this->processor->processSubscriptionPayment(
//            $subscription,
//            $plan,
//            ['payment_method_id' => 'pm_test123']
//        );
//
//        $this->assertFalse($result['success']);
//        $this->assertStringContainsString('card was declined', $result['message']);
//    }
//
//    public function testCreatePaymentIntent(): void
//    {
//        $paymentIntent = new stdClass();
//        $paymentIntent->id = 'pi_test123';
//        $paymentIntent->client_secret = 'pi_test123_secret_abc';
//
//        $this->paymentIntentServiceMock->shouldReceive('create')
//            ->once()
//            ->with(m::on(function ($data) {
//                return $data['amount'] === 10000
//                    && $data['currency'] === 'usd'
//                    && isset($data['automatic_payment_methods']);
//            }))
//            ->andReturn($paymentIntent);
//
//        $result = $this->processor->createPaymentIntent([
//            'amount' => 100.00,
//            'currency' => 'USD',
//            'order_id' => 123
//        ]);
//
//        $this->assertTrue($result['success']);
//        $this->assertEquals('pi_test123', $result['payment_intent_id']);
//        $this->assertEquals('pi_test123_secret_abc', $result['client_secret']);
//    }
//
//    public function testCreatePaymentIntentWithCustomerCreatesNewCustomer(): void
//    {
//        $member = $this->createMockMember(null);
//
//        $customer = $this->expectCustomerCreation($member);
//
//        $member->shouldReceive('update')
//            ->once()
//            ->with(['stripe_customer_id' => $customer->id])
//            ->andReturn(true);
//
//        $paymentIntent = new stdClass();
//        $paymentIntent->id = 'pi_test123';
//        $paymentIntent->client_secret = 'pi_test123_secret_abc';
//
//        $this->paymentIntentServiceMock->shouldReceive('create')
//            ->once()
//            ->with(m::on(function ($data) use ($customer) {
//                return $data['amount'] === 9999
//                    && $data['currency'] === 'usd'
//                    && $data['customer'] === $customer->id
//                    && $data['setup_future_usage'] === 'off_session'
//                    && isset($data['metadata']['member_id']);
//            }))
//            ->andReturn($paymentIntent);
//
//        $result = $this->processor->createPaymentIntentWithCustomer([
//            'amount' => 99.99,
//            'currency' => 'USD',
//            'member' => $member,
//            'metadata' => [
//                'member_id' => $member->id,
//                'subscription_id' => 1
//            ]
//        ]);
//
//        $this->assertTrue($result['success']);
//        $this->assertEquals('pi_test123', $result['payment_intent_id']);
//        $this->assertEquals($customer->id, $result['customer_id']);
//    }
//
//    public function testCreatePaymentIntentWithCustomerUsesExistingCustomer(): void
//    {
//        $member = $this->createMockMember('cus_existing123');
//
//        $customer = $this->expectCustomerRetrieval('cus_existing123');
//
//        $paymentIntent = new stdClass();
//        $paymentIntent->id = 'pi_test123';
//        $paymentIntent->client_secret = 'pi_test123_secret_abc';
//
//        $this->paymentIntentServiceMock->shouldReceive('create')
//            ->once()
//            ->with(m::on(function ($data) {
//                return $data['customer'] === 'cus_existing123'
//                    && $data['setup_future_usage'] === 'off_session';
//            }))
//            ->andReturn($paymentIntent);
//
//        $result = $this->processor->createPaymentIntentWithCustomer([
//            'amount' => 99.99,
//            'currency' => 'USD',
//            'member' => $member,
//            'metadata' => ['member_id' => $member->id]
//        ]);
//
//        $this->assertTrue($result['success']);
//        $this->assertEquals('cus_existing123', $result['customer_id']);
//    }
//
//    public function testCreatePaymentIntentWithCustomerWorksWithoutMember(): void
//    {
//        $paymentIntent = new stdClass();
//        $paymentIntent->id = 'pi_test123';
//        $paymentIntent->client_secret = 'pi_test123_secret_abc';
//
//        $this->paymentIntentServiceMock->shouldReceive('create')
//            ->once()
//            ->with(m::on(function ($data) {
//                return !isset($data['customer'])
//                    && !isset($data['setup_future_usage']);
//            }))
//            ->andReturn($paymentIntent);
//
//        $result = $this->processor->createPaymentIntentWithCustomer([
//            'amount' => 99.99,
//            'currency' => 'USD'
//        ]);
//
//        $this->assertTrue($result['success']);
//        $this->assertArrayNotHasKey('customer_id', $result);
//    }

//    public function testHandleOneTimeSubscriptionPaymentSavesPaymentMethod(): void
//    {
//        $paymentIntent = new stdClass();
//        $paymentIntent->id = 'pi_test123';
//        $paymentIntent->status = 'succeeded';
//        $paymentIntent->amount = 9999;
//        $paymentIntent->currency = 'usd';
//        $paymentIntent->customer = 'cus_test123';
//        $paymentIntent->payment_method = 'pm_test123';
//
//        $this->paymentIntentServiceMock->shouldReceive('retrieve')
//            ->once()
//            ->with('pi_test123')
//            ->andReturn($paymentIntent);
//
//        // Expect payment method retrieval
//        $paymentMethod = new stdClass();
//        $paymentMethod->id = 'pm_test123';
//        $paymentMethod->customer = null; // Not yet attached
//
//        $this->paymentMethodServiceMock->shouldReceive('retrieve')
//            ->once()
//            ->with('pm_test123')
//            ->andReturn($paymentMethod);
//
//        // Expect payment method attachment
//        $this->paymentMethodServiceMock->shouldReceive('attach')
//            ->once()
//            ->with('pm_test123', ['customer' => 'cus_test123'])
//            ->andReturn($paymentMethod);
//
//        // Expect customer update to set default payment method
//        $customer = new stdClass();
//        $customer->id = 'cus_test123';
//
//        $this->customerServiceMock->shouldReceive('update')
//            ->once()
//            ->with('cus_test123', [
//                'invoice_settings' => [
//                    'default_payment_method' => 'pm_test123'
//                ]
//            ])
//            ->andReturn($customer);
//
//        $this->setMockOrderExpectations();
//
//        $payment = m::mock(Payment::class)->makePartial();
//        $payment->id = 1;
//
//        $this->paymentRepository->shouldReceive('create')
//            ->once()
//            ->with(m::on(function ($data) {
//                return $data['metadata']['stripe_customer_id'] === 'cus_test123'
//                    && $data['metadata']['payment_method_saved'] === true;
//            }))
//            ->andReturn($payment);
//
//        $result = $this->processor->handleOneTimeSubscriptionPayment(
//            'pi_test123',
//            1,
//            1,
//            1
//        );
//
//        $this->assertTrue($result['success']);
//    }

//    public function testHandleOneTimeSubscriptionPaymentContinuesIfSavingPaymentMethodFails(): void
//    {
//        $paymentIntent = new stdClass();
//        $paymentIntent->id = 'pi_test123';
//        $paymentIntent->status = 'succeeded';
//        $paymentIntent->amount = 9999;
//        $paymentIntent->currency = 'usd';
//        $paymentIntent->customer = 'cus_test123';
//        $paymentIntent->payment_method = 'pm_test123';
//
//        $this->paymentIntentServiceMock->shouldReceive('retrieve')
//            ->once()
//            ->andReturn($paymentIntent);
//
//        $this->setMockOrderExpectations();
//
//        // Payment method retrieval fails
//        $this->paymentMethodServiceMock->shouldReceive('retrieve')
//            ->once()
//            ->andThrow(new Exception('Payment method not found'));
//
//        $payment = m::mock(Payment::class)->makePartial();
//        $payment->id = 1;
//
//        $this->paymentRepository->shouldReceive('create')
//            ->once()
//            ->andReturn($payment);
//
//        // Should still succeed even though saving payment method failed
//        $result = $this->processor->handleOneTimeSubscriptionPayment(
//            'pi_test123',
//            1,
//            1,
//            1
//        );
//
//        $this->assertTrue($result['success']);
//    }

//    public function testProcessOneTimePaymentWithPaymentMethodId(): void
//    {
//        $paymentIntent = new stdClass();
//        $paymentIntent->id = 'pi_test123';
//        $paymentIntent->status = 'succeeded';
//        $paymentIntent->client_secret = 'pi_test123_secret';
//
//        $this->paymentIntentServiceMock->shouldReceive('create')
//            ->once()
//            ->with(m::on(function ($data) {
//                return $data['payment_method'] === 'pm_test123'
//                    && $data['confirm'] === true
//                    && isset($data['automatic_payment_methods']);
//            }))
//            ->andReturn($paymentIntent);
//
//        $result = $this->processor->processOneTimePayment(
//            ['amount' => 50.00, 'currency' => 'GBP', 'order_id' => 1],
//            ['payment_method_id' => 'pm_test123']
//        );
//
//        $this->assertTrue($result['success']);
//        $this->assertEquals('pi_test123', $result['transaction_id']);
//        $this->assertEquals('succeeded', $result['status']);
//        $this->assertFalse($result['requires_action']);
//    }

//    public function testCancelSubscription(): void
//    {
//        $stripeSubscription = new stdClass();
//        $stripeSubscription->status = 'canceled';
//
//        $this->subscriptionServiceMock->shouldReceive('cancel')
//            ->once()
//            ->with('sub_test123')
//            ->andReturn($stripeSubscription);
//
//        $result = $this->processor->cancelSubscription('sub_test123', false);
//
//        $this->assertTrue($result['success']);
//        $this->assertEquals('canceled', $result['status']);
//    }
//
//    public function testCancelSubscriptionImmediately(): void
//    {
//        $canceledSubscription = new stdClass();
//        $canceledSubscription->id = 'sub_test123';
//        $canceledSubscription->status = 'canceled';
//        $canceledSubscription->cancel_at_period_end = false;
//        $canceledSubscription->canceled_at = time();
//        $canceledSubscription->current_period_end = null;
//
//        $this->subscriptionServiceMock->shouldReceive('cancel')
//            ->once()
//            ->with('sub_test123')
//            ->andReturn($canceledSubscription);
//
//        $result = $this->processor->cancelSubscription('sub_test123', false);
//
//        $this->assertTrue($result['success']);
//        $this->assertEquals('canceled', $result['status']);
//        $this->assertFalse($result['cancel_at_period_end']);
//    }
//
//    public function testCancelSubscriptionAtPeriodEnd(): void
//    {
//        $subscription = new stdClass();
//        $subscription->id = 'sub_test123';
//        $subscription->status = 'active';
//        $subscription->cancel_at_period_end = true;
//        $subscription->canceled_at = time();
//        $subscription->current_period_end = time() + (30 * 24 * 60 * 60);
//
//        $this->subscriptionServiceMock->shouldReceive('update')
//            ->once()
//            ->with('sub_test123', ['cancel_at_period_end' => true])
//            ->andReturn($subscription);
//
//        $result = $this->processor->cancelSubscription('sub_test123', true);
//
//        $this->assertTrue($result['success']);
//        $this->assertEquals('active', $result['status']);
//        $this->assertTrue($result['cancel_at_period_end']);
//    }

//    public function testReactivateSubscription(): void
//    {
//        $subscription = new stdClass();
//        $subscription->id = 'sub_test123';
//        $subscription->status = 'active';
//        $subscription->cancel_at_period_end = true;
//
//        $this->subscriptionServiceMock->shouldReceive('retrieve')
//            ->once()
//            ->with('sub_test123')
//            ->andReturn($subscription);
//
//        $this->subscriptionServiceMock->shouldReceive('update')
//            ->once()
//            ->with('sub_test123', ['cancel_at_period_end' => false])
//            ->andReturn($subscription);
//
//        $result = $this->processor->reactivateSubscription('sub_test123');
//
//        $this->assertTrue($result['success']);
//        $this->assertEquals('active', $result['status']);
//        $this->assertFalse($result['cancel_at_period_end']);
//    }

//    public function testCreateRefund(): void
//    {
//        $refund = new stdClass();
//        $refund->id = 'ref_test123';
//        $refund->amount = 5000; // cents
//        $refund->status = 'succeeded';
//        $refund->created = time();
//
//        $refundServiceMock = m::mock(RefundService::class);
//        $this->stripeMock->refunds = $refundServiceMock;
//
//        $refundServiceMock->shouldReceive('create')
//            ->once()
//            ->with(m::on(function ($data) {
//                return $data['payment_intent'] === 'pi_test123'
//                    && $data['amount'] === 5000;
//            }))
//            ->andReturn($refund);
//
//        $result = $this->processor->createRefund('pi_test123', ['amount' => 50.00]);
//
//        $this->assertTrue($result['success']);
//        $this->assertEquals('ref_test123', $result['refund_id']);
//        $this->assertEquals(50.00, $result['amount']);
//        $this->assertEquals('succeeded', $result['status']);
//    }

//    public function testReactivateSubscriptionSucceeds(): void
//    {
//        $subscription = new stdClass();
//        $subscription->id = 'sub_test123';
//        $subscription->status = 'active';
//        $subscription->cancel_at_period_end = true; // Set to cancel at period end
//
//        // First retrieve to check status
//        $this->subscriptionServiceMock->shouldReceive('retrieve')
//            ->once()
//            ->with('sub_test123')
//            ->andReturn($subscription);
//
//        // Then update to remove cancellation
//        $updatedSubscription = new stdClass();
//        $updatedSubscription->id = 'sub_test123';
//        $updatedSubscription->status = 'active';
//        $updatedSubscription->cancel_at_period_end = false;
//
//        $this->subscriptionServiceMock->shouldReceive('update')
//            ->once()
//            ->with('sub_test123', ['cancel_at_period_end' => false])
//            ->andReturn($updatedSubscription);
//
//        $result = $this->processor->reactivateSubscription('sub_test123');
//
//        $this->assertTrue($result['success']);
//        $this->assertEquals('active', $result['status']);
//        $this->assertFalse($result['cancel_at_period_end']);
//    }

//    public function testReactivateSubscriptionFailsIfAlreadyCanceled(): void
//    {
//        $subscription = new stdClass();
//        $subscription->id = 'sub_test123';
//        $subscription->status = 'canceled'; // Already fully canceled
//
//        $this->subscriptionServiceMock->shouldReceive('retrieve')
//            ->once()
//            ->with('sub_test123')
//            ->andReturn($subscription);
//
//        $result = $this->processor->reactivateSubscription('sub_test123');
//
//        $this->assertFalse($result['success']);
//        $this->assertEquals('subscription_already_canceled', $result['error_code']);
//        $this->assertStringContainsString('already been canceled', $result['message']);
//    }

//    public function testReactivateSubscriptionFailsIfNotScheduledForCancellation(): void
//    {
//        $subscription = new stdClass();
//        $subscription->id = 'sub_test123';
//        $subscription->status = 'active';
//        $subscription->cancel_at_period_end = false; // Not scheduled for cancellation
//
//        $this->subscriptionServiceMock->shouldReceive('retrieve')
//            ->once()
//            ->with('sub_test123')
//            ->andReturn($subscription);
//
//        $result = $this->processor->reactivateSubscription('sub_test123');
//
//        $this->assertFalse($result['success']);
//        $this->assertEquals('subscription_not_scheduled_for_cancellation', $result['error_code']);
//    }

//    public function testGetCustomerPaymentMethods(): void
//    {
//        $member = $this->createMockMember('cus_test123');
//
//        $customer = new stdClass();
//        $customer->invoice_settings = new stdClass();
//        $customer->invoice_settings->default_payment_method = 'pm_default123';
//
//        $this->customerServiceMock->shouldReceive('retrieve')
//            ->once()
//            ->with('cus_test123')
//            ->andReturn($customer);
//
//        $paymentMethod1 = new stdClass();
//        $paymentMethod1->id = 'pm_test123';
//        $paymentMethod2 = new stdClass();
//        $paymentMethod2->id = 'pm_test456';
//
//        $paymentMethodsData = new stdClass();
//        $paymentMethodsData->data = [$paymentMethod1, $paymentMethod2];
//
//        $this->paymentMethodServiceMock->shouldReceive('all')
//            ->once()
//            ->with([
//                'customer' => 'cus_test123',
//                'type' => 'card',
//            ])
//            ->andReturn($paymentMethodsData);
//
//        $result = $this->processor->getCustomerPaymentMethods($member);
//
//        $this->assertTrue($result['success']);
//        $this->assertCount(2, $result['payment_methods']);
//        $this->assertEquals('pm_default123', $result['default_payment_method_id']);
//    }
//
//    public function testGetCustomerPaymentMethodsWithNoCustomer(): void
//    {
//        $member = $this->createMockMember(null);
//
//        $result = $this->processor->getCustomerPaymentMethods($member);
//
//        $this->assertEmpty($result['payment_methods']);
//        $this->assertNull($result['default_payment_method_id']);
//    }

//    public function testAddPaymentMethodWithNewCustomer(): void
//    {
//        $member = $this->createMockMember(null);
//
//        $customer = $this->expectCustomerCreation($member);
//
//        // Mock member update to return true
//        $member->shouldReceive('update')
//            ->once()
//            ->with(['stripe_customer_id' => $customer->id])
//            ->andReturn(true);
//
//        $this->expectPaymentMethodAttachment('pm_test123', $customer->id);
//
//        $result = $this->processor->addPaymentMethod($member, 'pm_test123', false);
//
//        $this->assertTrue($result['success']);
//    }

//    public function testAddPaymentMethodWithExistingCustomer(): void
//    {
//        $member = $this->createMockMember('cus_test123');
//
//        $this->expectPaymentMethodAttachment('pm_test123', 'cus_test123');
//
//        $result = $this->processor->addPaymentMethod($member, 'pm_test123', false);
//
//        $this->assertTrue($result['success']);
//    }

//    public function testAddPaymentMethodSetAsDefault(): void
//    {
//        $member = $this->createMockMember('cus_test123');
//
//        $this->expectPaymentMethodAttachment('pm_test123', 'cus_test123');
//
//        $customer = new stdClass();
//        $customer->id = 'cus_test123';
//
//        $this->customerServiceMock->shouldReceive('update')
//            ->once()
//            ->with('cus_test123', [
//                'invoice_settings' => [
//                    'default_payment_method' => 'pm_test123'
//                ]
//            ])
//            ->andReturn($customer);
//
//        $result = $this->processor->addPaymentMethod($member, 'pm_test123', true);
//
//        $this->assertTrue($result['success']);
//    }
//
//    public function testSetDefaultPaymentMethod(): void
//    {
//        $customer = new stdClass();
//        $customer->id = 'cus_test123';
//
//        $this->customerServiceMock->shouldReceive('update')
//            ->once()
//            ->with('cus_test123', [
//                'invoice_settings' => [
//                    'default_payment_method' => 'pm_test123'
//                ]
//            ])
//            ->andReturn($customer);
//
//        $result = $this->processor->setDefaultPaymentMethod('cus_test123', 'pm_test123');
//
//        $this->assertTrue($result['success']);
//    }
//
//    public function testRemovePaymentMethod(): void
//    {
//        $member = $this->createMockMember('cus_test123');
//
//        $paymentMethod = new stdClass();
//        $paymentMethod->id = 'pm_test123';
//        $paymentMethod->customer = 'cus_test123';
//
//        $this->paymentMethodServiceMock->shouldReceive('retrieve')
//            ->once()
//            ->with('pm_test123')
//            ->andReturn($paymentMethod);
//
//        $detachedPaymentMethod = new stdClass();
//        $detachedPaymentMethod->id = 'pm_test123';
//
//        $this->paymentMethodServiceMock->shouldReceive('detach')
//            ->once()
//            ->with('pm_test123')
//            ->andReturn($detachedPaymentMethod);
//
//        $result = $this->processor->removePaymentMethod($member, 'pm_test123');
//
//        $this->assertTrue($result['success']);
//    }
//
//    public function testRemovePaymentMethodUnauthorized(): void
//    {
//        $member = $this->createMockMember('cus_test123');
//
//        $paymentMethod = new stdClass();
//        $paymentMethod->id = 'pm_test123';
//        $paymentMethod->customer = 'cus_different456'; // Different customer
//
//        $this->paymentMethodServiceMock->shouldReceive('retrieve')
//            ->once()
//            ->with('pm_test123')
//            ->andReturn($paymentMethod);
//
//        $result = $this->processor->removePaymentMethod($member, 'pm_test123');
//
//        $this->assertFalse($result['success']);
//        $this->assertEquals('unauthorized', $result['error_code']);
//    }

    // Add these tests to the existing class

//    public function testProcessSubscriptionPaymentWithVoucher(): void
//    {
//        $member = $this->createMockMember(null);
//        $subscription = $this->createMockSubscription($member);
//        $plan = $this->createMockPlan();
//
//        $voucher = m::mock(Voucher::class)->makePartial();
//        $voucher->id = 1;
//        $voucher->code = 'SUB10';
//        $voucher->type = VoucherType::Percentage->value;
//        $voucher->value = 10;
//        $voucher->stripe_coupon_id = null;
//        $voucher->shouldReceive('appliesToSubscriptions')->andReturn(true);
//        $voucher->shouldReceive('update')->once();
//
//        $subscription->discount_amount = 2.99;
//        $subscription->original_price = 29.99;
//        $subscription->shouldReceive('getDiscountedPrice')->andReturn(27.00);
//
//        $customer = $this->expectCustomerCreation($member);
//        $this->expectPaymentMethodAttachment('pm_test123', $customer->id);
//        $this->expectCustomerUpdate($customer->id, $customer);
//
//        // Expect coupon creation
//        $this->expectCouponCreation($voucher);
//
//        $this->expectPriceCreation($plan, 2499);
//
//        $stripeSubscription = $this->expectSubscriptionCreationWithCoupon(
//            'sub_test123',
//            'active',
//            'in_test123',
//            'coupon_test123'
//        );
//
//        $this->expectInvoiceRetrieval('in_test123', 'pi_test123');
//
//        $payment = $this->expectPaymentCreationWithVoucher($subscription->id, $voucher);
//        $this->expectPaymentUpdate($payment->id, 'completed');
//
//        $subscription->shouldReceive('update')
//            ->once()
//            ->with([
//                'status' => 'active',
//                'current_period_start' => now_datetime()->format('Y-m-d H:i:s'),
//                'current_period_end' => now_datetime()->format('Y-m-d H:i:s'),
//            ]);
//
//        $member->shouldReceive('update')
//            ->once()
//            ->with(['stripe_customer_id' => $customer->id]);
//
//        $data = ['payment_method_id' => 'pm_test123'];
//
//        $result = $this->processor->processSubscriptionPaymentWithVoucher(
//            $subscription,
//            $plan,
//            $voucher,
//            $data
//        );
//
//        $this->assertTrue($result['success']);
//        $this->assertTrue($result['discount_applied']);
//        $this->assertEquals('sub_test123', $result['subscription_id']);
//    }

    public function testGetOrCreateStripeCouponCreatesNewCoupon(): void
    {
        $this->markTestSkipped('getOrCreateStripeCoupon was removed from StripePaymentProcessor; coupon creation lives in StripeCouponGateway.');

        $voucher = m::mock(Voucher::class)->makePartial();
        $voucher->id = 1;
        $voucher->code = 'SUB10';
        $voucher->name = '10% Off';
        $voucher->type = VoucherType::Percentage->value;
        $voucher->value = 10;
        $voucher->stripe_coupon_id = null;
        $voucher->duration_in_months = null;
        $voucher->maximum_discount = null;

        $stripeCoupon = new stdClass();
        $stripeCoupon->id = 'coupon_test123';

        $plan = $this->createMockPlan();
        $plan->currency = 'gbp';

        $this->couponServiceMock->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) {
                return $data['name'] === '10% Off'
                    && $data['percent_off'] === 10
                    && $data['duration'] === 'once'
                    && isset($data['metadata']['voucher_id']);
            }))
            ->andReturn($stripeCoupon);

        $voucher->shouldReceive('update')
            ->once()
            ->with(['stripe_coupon_id' => 'coupon_test123']);

        // Use reflection to test private method
        $reflection = new ReflectionClass($this->processor);
        $method = $reflection->getMethod('getOrCreateStripeCoupon');
        $method->setAccessible(true);

        $result = $method->invoke($this->processor, $voucher, $plan);

        $this->assertEquals('coupon_test123', $result);
    }

    public function testGetOrCreateStripeCouponUsesExisting(): void
    {
        $this->markTestSkipped('getOrCreateStripeCoupon was removed from StripePaymentProcessor; coupon creation lives in StripeCouponGateway.');

        $voucher = m::mock(Voucher::class)->makePartial();
        $voucher->stripe_coupon_id = 'coupon_existing123';
        $stripeCoupon = new stdClass();
        $stripeCoupon->id = 'coupon_existing123';

        $this->couponServiceMock->shouldReceive('retrieve')
            ->once()
            ->with('coupon_existing123')
            ->andReturn($stripeCoupon);

        $reflection = new ReflectionClass($this->processor);
        $method = $reflection->getMethod('getOrCreateStripeCoupon');
        $method->setAccessible(true);

        $plan = $this->createMockPlan();
        $plan->currency = 'gbp';

        $result = $method->invoke($this->processor, $voucher, $plan);

        $this->assertEquals('coupon_existing123', $result);
    }

    public function testGetOrCreateStripeCouponWithFixedAmount(): void
    {
        $this->markTestSkipped('getOrCreateStripeCoupon was removed from StripePaymentProcessor; coupon creation lives in StripeCouponGateway.');

        $voucher = m::mock(Voucher::class)->makePartial();
        $voucher->id = 1;
        $voucher->code = 'SUB15';
        $voucher->name = '$15 Off';
        $voucher->type = 'fixed';
        $voucher->value = 15;
        $voucher->stripe_coupon_id = null;
        $voucher->duration_in_months = null;
        $stripeCoupon = new stdClass();
        $stripeCoupon->id = 'coupon_test123';

        $this->couponServiceMock->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) {
                return $data['amount_off'] === 1500 // $15 in cents
                    && $data['currency'] === 'usd';
            }))
            ->andReturn($stripeCoupon);

        $voucher->shouldReceive('update')->once();

        $reflection = new ReflectionClass($this->processor);
        $method = $reflection->getMethod('getOrCreateStripeCoupon');
        $method->setAccessible(true);

        $plan = $this->createMockPlan();
        $plan->currency = 'usd';

        $result = $method->invoke($this->processor, $voucher, $plan);

        $this->assertEquals('coupon_test123', $result);
    }

    public function testGetOrCreateStripeCouponWithDuration(): void
    {
        $this->markTestSkipped('getOrCreateStripeCoupon was removed from StripePaymentProcessor; coupon creation lives in StripeCouponGateway.');

        $voucher = m::mock(Voucher::class)->makePartial();
        $voucher->id = 1;
        $voucher->code = 'SUB10';
        $voucher->name = '10% Off';
        $voucher->type = VoucherType::Percentage->value;
        $voucher->value = 10;
        $voucher->stripe_coupon_id = null;
        $voucher->duration_in_months = 3;
        $stripeCoupon = new stdClass();
        $stripeCoupon->id = 'coupon_test123';

        $this->couponServiceMock->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) {
                return $data['duration'] === 'repeating'
                    && $data['duration_in_months'] === 3;
            }))
            ->andReturn($stripeCoupon);

        $voucher->shouldReceive('update')->once();

        $reflection = new ReflectionClass($this->processor);
        $method = $reflection->getMethod('getOrCreateStripeCoupon');
        $method->setAccessible(true);

        $plan = $this->createMockPlan();
        $plan->currency = 'gbp';

        $result = $method->invoke($this->processor, $voucher, $plan);

        $this->assertEquals('coupon_test123', $result);
    }

// Helper methods
    private function expectCouponCreation($voucher): void
    {
        $stripeCoupon = new stdClass();
        $stripeCoupon->id = 'coupon_test123';
        $couponServiceMock = m::mock(CouponService::class);
        $this->stripeMock->coupons = $couponServiceMock;

        $couponServiceMock->shouldReceive('create')
            ->once()
            ->andReturn($stripeCoupon);
    }

    private function expectSubscriptionCreationWithCoupon(
        string $subscriptionId,
        string $status,
        string $invoiceId,
        string $couponId
    ): \Stripe\Subscription
    {
        $stripeSubscription = \Stripe\Subscription::constructFrom([
            'id' => $subscriptionId,
            'status' => $status,
            'latest_invoice' => $invoiceId,
        ]);
        $this->subscriptionServiceMock->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) use ($couponId) {
                return isset($data['discounts'][0]['coupon'])
                    && $data['discounts'][0]['coupon'] === $couponId
                    && isset($data['metadata']['voucher_id']);
            }))
            ->andReturn($stripeSubscription);

        return $stripeSubscription;
    }

    private function expectPaymentCreationWithVoucher($subscriptionId, $voucher)
    {
        $payment = m::mock(Payment::class)->makePartial();
        $payment->id = 1;
        $payment->subscription_id = $subscriptionId;
        $this->paymentRepository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) use ($voucher) {
                return isset($data['metadata']['voucher_id'])
                    && $data['metadata']['voucher_id'] === $voucher->id
                    && isset($data['metadata']['discount_amount']);
            }))
            ->andReturn($payment);

        return $payment;
    }

//    public function testCreatePaymentIntentForOneTimeSubscription(): void
//    {
//        $paymentIntent = new stdClass();
//        $paymentIntent->id = 'pi_test123';
//        $paymentIntent->client_secret = 'pi_test123_secret_abc';
//
//        $this->paymentIntentServiceMock->shouldReceive('create')
//            ->once()
//            ->with(m::on(function ($data) {
//                return $data['amount'] === 9999
//                    && $data['currency'] === 'usd'
//                    && isset($data['metadata']['subscription_id'])
//                    && $data['metadata']['subscription_id'] === 1;
//            }))
//            ->andReturn($paymentIntent);
//
//        $result = $this->processor->createPaymentIntent([
//            'amount' => 99.99,
//            'currency' => 'USD',
//            'subscription_id' => 1,
//            'site_id' => 1
//        ]);
//
//        $this->assertTrue($result['success']);
//        $this->assertEquals('pi_test123', $result['payment_intent_id']);
//        $this->assertEquals('pi_test123_secret_abc', $result['client_secret']);
//    }

//    public function testHandleOneTimeSubscriptionPaymentSuccess(): void
//    {
//        $paymentIntent = new stdClass();
//        $paymentIntent->id = 'pi_test123';
//        $paymentIntent->status = 'succeeded';
//        $paymentIntent->amount = 9999;
//        $paymentIntent->currency = 'usd';
//
//        $this->paymentIntentServiceMock->shouldReceive('retrieve')
//            ->once()
//            ->with('pi_test123')
//            ->andReturn($paymentIntent);
//
//        $this->setMockOrderExpectations();
//
//        $payment = m::mock(Payment::class)->makePartial();
//        $payment->id = 1;
//
//        $this->paymentRepository->shouldReceive('create')
//            ->once()
//            ->with(m::on(function ($data) {
//                return $data['subscription_id'] === 1
//                    && $data['order_id'] === 1
//                    && $data['status'] === 'completed'
//                    && $data['amount'] === 99.99
//                    && isset($data['metadata']['one_time_subscription'])
//                    && $data['metadata']['one_time_subscription'] === true;
//            }))
//            ->andReturn($payment);
//
//        $result = $this->processor->handleOneTimeSubscriptionPayment(
//            'pi_test123',
//            1, // subscription_id
//            1, // order_id
//            1  // site_id
//        );
//
//        $this->assertTrue($result['success']);
//        $this->assertEquals(1, $result['payment_id']);
//        $this->assertEquals('pi_test123', $result['transaction_id']);
//    }

//    public function testHandleOneTimeSubscriptionPaymentFailsWhenNotSucceeded(): void
//    {
//        $paymentIntent = new stdClass();
//        $paymentIntent->id = 'pi_test123';
//        $paymentIntent->status = 'requires_action';
//
//        $this->paymentIntentServiceMock->shouldReceive('retrieve')
//            ->once()
//            ->with('pi_test123')
//            ->andReturn($paymentIntent);
//
//        $result = $this->processor->handleOneTimeSubscriptionPayment(
//            'pi_test123',
//            1,
//            1,
//            1
//        );
//
//        $this->assertFalse($result['success']);
//        $this->assertEquals('Payment not completed', $result['message']);
//    }

//    public function testConfirmPaymentIntentSuccess(): void
//    {
//        $paymentIntent = new stdClass();
//        $paymentIntent->id = 'pi_test123';
//        $paymentIntent->status = 'succeeded';
//        $paymentIntent->amount = 9999;
//        $paymentIntent->currency = 'usd';
//
//        $this->paymentIntentServiceMock->shouldReceive('retrieve')
//            ->once()
//            ->with('pi_test123')
//            ->andReturn($paymentIntent);
//
//        $result = $this->processor->confirmPaymentIntent('pi_test123');
//
//        $this->assertTrue($result['success']);
//        $this->assertEquals('succeeded', $result['status']);
//        $this->assertEquals(99.99, $result['amount']);
//        $this->assertEquals('usd', $result['currency']);
//    }

//    public function testUpdateCustomerEmail(): void
//    {
//        $customer = new stdClass();
//        $customer->id = 'cus_test123';
//        $customer->email = 'new@example.com';
//
//        $this->customerServiceMock->shouldReceive('update')
//            ->once()
//            ->with('cus_test123', ['email' => 'new@example.com'])
//            ->andReturn($customer);
//
//        $result = $this->processor->updateCustomerEmail('cus_test123', 'new@example.com');
//
//        $this->assertTrue($result['success']);
//        $this->assertEquals('cus_test123', $result['customer_id']);
//        $this->assertEquals('new@example.com', $result['email']);
//    }

//    public function testUpdatePaymentMethodSucceeds(): void
//    {
//        $member = $this->createMockMember('cus_test123');
//
//        // Mock removing old payment method
//        $oldPaymentMethod = new stdClass();
//        $oldPaymentMethod->id = 'pm_old123';
//        $oldPaymentMethod->customer = 'cus_test123';
//
//        $this->paymentMethodServiceMock->shouldReceive('retrieve')
//            ->once()
//            ->with('pm_old123')
//            ->andReturn($oldPaymentMethod);
//
//        $this->paymentMethodServiceMock->shouldReceive('detach')
//            ->once()
//            ->with('pm_old123')
//            ->andReturn($oldPaymentMethod);
//
//        // Mock adding new payment method
//        $this->expectPaymentMethodAttachment('pm_new456', 'cus_test123');
//
//        $customer = new stdClass();
//        $customer->id = 'cus_test123';
//
//        $this->customerServiceMock->shouldReceive('update')
//            ->once()
//            ->with('cus_test123', [
//                'invoice_settings' => [
//                    'default_payment_method' => 'pm_new456'
//                ]
//            ])
//            ->andReturn($customer);
//
//        // Remove old
//        $removeResult = $this->processor->removePaymentMethod($member, 'pm_old123');
//        $this->assertTrue($removeResult['success']);
//
//        // Add new
//        $addResult = $this->processor->addPaymentMethod($member, 'pm_new456', true);
//        $this->assertTrue($addResult['success']);
//    }

//    public function testUpdatePaymentMethodFailsIfOldCardDoesNotBelongToMember(): void
//    {
//        $member = $this->createMockMember('cus_test123');
//
//        $oldPaymentMethod = new stdClass();
//        $oldPaymentMethod->id = 'pm_old123';
//        $oldPaymentMethod->customer = 'cus_different456'; // Different customer
//
//        $this->paymentMethodServiceMock->shouldReceive('retrieve')
//            ->once()
//            ->with('pm_old123')
//            ->andReturn($oldPaymentMethod);
//
//        $result = $this->processor->removePaymentMethod($member, 'pm_old123');
//
//        $this->assertFalse($result['success']);
//        $this->assertEquals('unauthorized', $result['error_code']);
//    }

//    public function testIsPaymentMethodExpiring(): void
//    {
//        $paymentMethod = new stdClass();
//        $paymentMethod->card = new stdClass();
//
//        // Card expiring in 1 month
//        $nextMonth = new DateTime('+1 month');
//        $paymentMethod->card->exp_month = (int)$nextMonth->format('m');
//        $paymentMethod->card->exp_year = (int)$nextMonth->format('Y');
//
//        $isExpiring = $this->processor->isPaymentMethodExpiring($paymentMethod, 2);
//
//        $this->assertTrue($isExpiring);
//    }

//    public function testIsPaymentMethodExpired(): void
//    {
//        $paymentMethod = new stdClass();
//        $paymentMethod->card = new stdClass();
//
//        // Card expired last month
//        $lastMonth = new DateTime('-1 month');
//        $paymentMethod->card->exp_month = (int)$lastMonth->format('m');
//        $paymentMethod->card->exp_year = (int)$lastMonth->format('Y');
//
//        $isExpired = $this->processor->isPaymentMethodExpired($paymentMethod);
//
//        $this->assertTrue($isExpired);
//    }

//    public function testGetPaymentMethodsWithWarnings(): void
//    {
//        $member = $this->createMockMember('cus_test123');
//
//        $customer = new stdClass();
//        $customer->invoice_settings = new stdClass();
//        $customer->invoice_settings->default_payment_method = 'pm_test123';
//
//        $this->customerServiceMock->shouldReceive('retrieve')
//            ->once()
//            ->andReturn($customer);
//
//        // Expiring card
//        $expiringCard = new stdClass();
//        $expiringCard->id = 'pm_test123';
//        $expiringCard->card = new stdClass();
//        $nextMonth = new DateTime('+1 month');
//        $expiringCard->card->exp_month = (int)$nextMonth->format('m');
//        $expiringCard->card->exp_year = (int)$nextMonth->format('Y');
//        $expiringCard->card->brand = 'visa';
//        $expiringCard->card->last4 = '4242';
//
//        $paymentMethodsData = new stdClass();
//        $paymentMethodsData->data = [$expiringCard];
//
//        $this->paymentMethodServiceMock->shouldReceive('all')
//            ->once()
//            ->andReturn($paymentMethodsData);
//
//        $result = $this->processor->getPaymentMethodsWithWarnings($member);
//
//        $this->assertFalse($result['success']);
//        $this->assertTrue($result['has_warnings']);
//        $this->assertCount(1, $result['warnings']);
//        $this->assertEquals('expiring', $result['warnings'][0]['status']);
//    }

//    public function testRefundWithChargeId()
//    {
//        $chargeId = 'ch_' . uniqid();
//        $amount = 99.99;
//
//        $mockRefund = new stdClass();
//        $mockRefund->id = 'refund_' . uniqid();
//        $mockRefund->status = 'succeeded';
//
//        $this->stripeMock->refunds = $this->createMock(RefundService::class);
//        $this->stripeMock->refunds
//            ->expects($this->once())
//            ->method('create')
//            ->with($this->callback(function ($params) use ($chargeId, $amount) {
//                return $params['charge'] === $chargeId
//                    && $params['amount'] === (int)($amount * 100);
//            }))
//            ->willReturn($mockRefund);
//
//        $result = $this->processor->refund($chargeId, $amount);
//
//        $this->assertTrue($result['success']);
//        $this->assertEquals($mockRefund->id, $result['refund_id']);
//        $this->assertEquals($amount, $result['amount']);
//        $this->assertEquals('succeeded', $result['status']);
//    }

//    public function testRefundWithPaymentIntentId()
//    {
//        $paymentIntentId = 'pi_' . uniqid();
//        $amount = 150.00;
//
//        $mockRefund = new stdClass();
//        $mockRefund->id = 'refund_' . uniqid();
//        $mockRefund->status = 'succeeded';
//
//        $this->stripeMock->refunds = $this->createMock(RefundService::class);
//        $this->stripeMock->refunds
//            ->expects($this->once())
//            ->method('create')
//            ->with($this->callback(function ($params) use ($paymentIntentId, $amount) {
//                return $params['payment_intent'] === $paymentIntentId
//                    && $params['amount'] === (int)($amount * 100);
//            }))
//            ->willReturn($mockRefund);
//
//        $result = $this->processor->refund($paymentIntentId, $amount);
//
//        $this->assertTrue($result['success']);
//        $this->assertEquals($mockRefund->id, $result['refund_id']);
//    }

//    public function testRefundWithReasonAndMetadata()
//    {
//        $chargeId = 'ch_' . uniqid();
//        $amount = 50.00;
//        $reason = 'customer_request';
//        $metadata = ['order_id' => '12345', 'user_id' => '67890'];
//
//        $mockRefund = new stdClass();
//        $mockRefund->id = 'refund_' . uniqid();
//        $mockRefund->status = 'succeeded';
//
//        $this->stripeMock->refunds = $this->createMock(RefundService::class);
//        $this->stripeMock->refunds
//            ->expects($this->once())
//            ->method('create')
//            ->with($this->callback(function ($params) use ($reason, $metadata) {
//                return $params['reason'] === $reason
//                    && $params['metadata'] === $metadata;
//            }))
//            ->willReturn($mockRefund);
//
//        $result = $this->processor->refund($chargeId, $amount, [
//            'reason' => $reason,
//            'metadata' => $metadata
//        ]);
//
//        $this->assertTrue($result['success']);
//    }

//    public function testRefundFailsWithApiError()
//    {
//        $chargeId = 'ch_invalid';
//        $amount = 100.00;
//
//        $this->stripeMock->refunds = $this->createMock(RefundService::class);
//        $this->stripeMock->refunds
//            ->expects($this->once())
//            ->method('create')
//            ->willThrowException(new InvalidRequestException(
//                'Charge not found',
//                null,
//                null,
//            ));
//
//        $result = $this->processor->refund($chargeId, $amount);
//
//        $this->assertFalse($result['success']);
//        $this->assertArrayHasKey('message', $result);
//        $this->assertArrayHasKey('error_code', $result);
//    }

//    public function testRefundAmountConvertedToCents()
//    {
//        $chargeId = 'ch_' . uniqid();
//        $amount = 123.45;
//
//        $mockRefund = new stdClass();
//        $mockRefund->id = 'refund_' . uniqid();
//        $mockRefund->status = 'succeeded';
//
//        $this->stripeMock->refunds = $this->createMock(RefundService::class);
//        $this->stripeMock->refunds
//            ->expects($this->once())
//            ->method('create')
//            ->with($this->callback(function ($params) {
//                return $params['amount'] === 12345; // 123.45 * 100
//            }))
//            ->willReturn($mockRefund);
//
//        $result = $this->processor->refund($chargeId, $amount);
//
//        $this->assertTrue($result['success']);
//    }

    private function setMockOrderExpectations()
    {
        $order = m::mock(Order::class)->makePartial();

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->with(1, ['status' => 'completed', 'payment_status' => 'paid'])
            ->andReturn($order);
    }
}
