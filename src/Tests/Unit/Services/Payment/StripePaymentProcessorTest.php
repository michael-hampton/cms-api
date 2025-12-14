<?php

namespace App\Tests\Unit\Services\Payment;

use App\Models\Member;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\PaymentRepository;
use App\Services\Payment\StripePaymentProcessor;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery as m;
use Stripe\Service\CustomerService;
use Stripe\Service\InvoiceService;
use Stripe\Service\PaymentIntentService;
use Stripe\Service\PaymentMethodService;
use Stripe\Service\PriceService;
use Stripe\Service\ProductService;
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

        $this->stripeMock->customers = $this->customerServiceMock;
        $this->stripeMock->subscriptions = $this->subscriptionServiceMock;
        $this->stripeMock->paymentMethods = $this->paymentMethodServiceMock;
        $this->stripeMock->products = $this->productServiceMock;
        $this->stripeMock->prices = $this->priceServiceMock;
        $this->stripeMock->invoices = $this->invoiceServiceMock;
        $this->stripeMock->paymentIntents = $this->paymentIntentServiceMock;

        // Inject mocked Stripe client via constructor
        $this->processor = new StripePaymentProcessor(
            $this->paymentRepository,
            $this->stripeMock
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
    public function testProcessSubscriptionPaymentWithNewCustomer(): void
    {
        $member = $this->createMockMember(null);
        $subscription = $this->createMockSubscription($member);
        $plan = $this->createMockPlan();

        // Setup expectations
        $customer = $this->expectCustomerCreation($member);
        $this->expectPaymentMethodAttachment('pm_test123', $customer->id);
        $this->expectCustomerUpdate($customer->id, $customer);
        $this->expectPriceCreation($plan);

        $stripeSubscription = $this->expectSubscriptionCreation('sub_test123', 'active', 'in_test123');
        $this->expectInvoiceRetrieval('in_test123', 'pi_test123');

        $payment = $this->expectPaymentCreation($subscription->id);
        $this->expectPaymentUpdate($payment->id, 'completed');

        $subscription->shouldReceive('update')
            ->once()
            ->with(['status' => 'active']);

        $member->shouldReceive('update')
            ->once()
            ->with(['stripe_customer_id' => $customer->id]);

        $data = ['payment_method_id' => 'pm_test123'];

        $result = $this->processor->processSubscriptionPayment($subscription, $plan, $data);

        $this->assertTrue($result['success']);
        $this->assertEquals('sub_test123', $result['subscription_id']);
        $this->assertEquals($customer->id, $result['customer_id']);
        $this->assertEquals('pi_test123', $result['payment_intent_id']);
        $this->assertFalse($result['requires_action']);
    }

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

    private function expectCustomerCreation(Member $member): \stdClass
    {
        $customer = new \stdClass();
        $customer->id = 'cus_test123';

        $this->customerServiceMock->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) use ($member) {
                return $data['email'] === $member->email
                    && isset($data['metadata']['member_id']);
            }))
            ->andReturn($customer);

        return $customer;
    }

    private function expectPaymentMethodAttachment(string $paymentMethodId, string $customerId): void
    {
        $paymentMethod = new \stdClass();
        $paymentMethod->id = $paymentMethodId;

        $this->paymentMethodServiceMock->shouldReceive('attach')
            ->once()
            ->with($paymentMethodId, ['customer' => $customerId])
            ->andReturn($paymentMethod);
    }

    private function expectCustomerUpdate(string $customerId, \stdClass $customer): void
    {
        $this->customerServiceMock->shouldReceive('update')
            ->once()
            ->with($customerId, m::type('array'))
            ->andReturn($customer);
    }

    private function expectPriceCreation(SubscriptionPlan $plan): void
    {
        if ($plan->stripe_price_id) {
            return; // Price already exists
        }

        // Create product
        $product = new \stdClass();
        $product->id = 'prod_test123';

        $this->productServiceMock->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) use ($plan) {
                return $data['name'] === $plan->name
                    && isset($data['metadata']['plan_id'])
                    && $data['metadata']['plan_id'] === $plan->id;
            }))
            ->andReturn($product);

        // Create price
        $price = new \stdClass();
        $price->id = 'price_test123';

        $this->priceServiceMock->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) use ($plan) {
                return $data['product'] === 'prod_test123'
                    && $data['unit_amount'] === (int)($plan->price * 100)
                    && $data['currency'] === strtolower($plan->currency)
                    && isset($data['recurring'])
                    && isset($data['metadata']['plan_id']);
            }))
            ->andReturn($price);

        $plan->shouldReceive('update')
            ->once()
            ->with(['stripe_price_id' => 'price_test123']);
    }

    private function expectSubscriptionCreation(
        string $subscriptionId,
        string $status,
        string $invoiceId
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
                    && isset($data['metadata']);
            }))
            ->andReturn($stripeSubscription);

        return $stripeSubscription;
    }

    private function expectInvoiceRetrieval(string $invoiceId, ?string $paymentIntentId): void
    {
        $invoice = new \stdClass();
        $invoice->id = $invoiceId;
        $invoice->payment_intent = $paymentIntentId;

        $this->invoiceServiceMock->shouldReceive('retrieve')
            ->once()
            ->with($invoiceId, m::type('array'))
            ->andReturn($invoice);
    }

    private function expectPaymentCreation(int $subscriptionId): Payment
    {
        $payment = m::mock(Payment::class)->makePartial();
        $payment->id = 1;
        $payment->subscription_id = $subscriptionId;

        $this->paymentRepository->shouldReceive('create')
            ->once()
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

    public function testProcessSubscriptionPaymentWithExistingCustomer(): void
    {
        $member = $this->createMockMember('cus_existing123');
        $subscription = $this->createMockSubscription($member);
        $plan = $this->createMockPlan();

        // Setup expectations
        $customer = $this->expectCustomerRetrieval('cus_existing123');
        $this->expectPaymentMethodAttachment('pm_test123', $customer->id);
        $this->expectCustomerUpdate($customer->id, $customer);
        $this->expectPriceCreation($plan);

        $stripeSubscription = $this->expectSubscriptionCreation('sub_test123', 'active', 'in_test123');
        $this->expectInvoiceRetrieval('in_test123', 'pi_test123');

        $payment = $this->expectPaymentCreation($subscription->id);
        $this->expectPaymentUpdate($payment->id, 'completed');

        $subscription->shouldReceive('update')->once();

        $result = $this->processor->processSubscriptionPayment(
            $subscription,
            $plan,
            ['payment_method_id' => 'pm_test123']
        );

        $this->assertTrue($result['success']);
    }

    private function expectCustomerRetrieval(string $customerId): \stdClass
    {
        $customer = new \stdClass();
        $customer->id = $customerId;

        $this->customerServiceMock->shouldReceive('retrieve')
            ->once()
            ->with($customerId)
            ->andReturn($customer);

        return $customer;
    }

    public function testProcessSubscriptionPaymentRequiresAction(): void
    {
        $member = $this->createMockMember('cus_test123');
        $subscription = $this->createMockSubscription($member);
        $plan = $this->createMockPlan();

        // Setup expectations
        $customer = $this->expectCustomerRetrieval('cus_test123');
        $this->expectPaymentMethodAttachment('pm_test123', $customer->id);
        $this->expectCustomerUpdate($customer->id, $customer);
        $this->expectPriceCreation($plan);

        // Create subscription that requires action
        $paymentIntent = new \stdClass();
        $paymentIntent->id = 'pi_test123';
        $paymentIntent->status = 'requires_action';
        $paymentIntent->client_secret = 'pi_test123_secret_abc';

        $invoice = new \stdClass();
        $invoice->payment_intent = $paymentIntent;

        $stripeSubscription = \Stripe\Subscription::constructFrom([
            'id' => 'sub_test123',
            'status' => 'incomplete',
            'latest_invoice' => $invoice,
        ]);

        $this->subscriptionServiceMock->shouldReceive('create')
            ->once()
            ->andReturn($stripeSubscription);

        $payment = $this->expectPaymentCreation($subscription->id);

        // Should NOT update payment when requires_action
        $this->paymentRepository->shouldReceive('update')->never();

        $result = $this->processor->processSubscriptionPayment(
            $subscription,
            $plan,
            ['payment_method_id' => 'pm_test123']
        );

        $this->assertTrue($result['success']);
        $this->assertTrue($result['requires_action']);
        $this->assertEquals('pi_test123_secret_abc', $result['payment_intent_client_secret']);
    }

    public function testProcessSubscriptionPaymentWithTrialPeriod(): void
    {
        $member = $this->createMockMember('cus_test123');
        $subscription = $this->createMockSubscription($member);
        $plan = $this->createMockPlan();
        $plan->trial_days = 14;

        // Setup expectations
        $customer = $this->expectCustomerRetrieval('cus_test123');
        $this->expectPaymentMethodAttachment('pm_test123', $customer->id);
        $this->expectCustomerUpdate($customer->id, $customer);
        $this->expectPriceCreation($plan);

        // Expect subscription with trial period
        $stripeSubscription = \Stripe\Subscription::constructFrom([
            'id' => 'sub_test123',
            'status' => 'trialing',
            'latest_invoice' => 'in_test123',
        ]);

        $this->subscriptionServiceMock->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) {
                return isset($data['trial_period_days'])
                    && $data['trial_period_days'] === 14;
            }))
            ->andReturn($stripeSubscription);

        // Mock invoice with no payment intent (trial)
        $invoice = new \stdClass();
        $invoice->payment_intent = null;

        $this->invoiceServiceMock->shouldReceive('retrieve')
            ->once()
            ->andReturn($invoice);

        $payment = $this->expectPaymentCreation($subscription->id);
        $this->paymentRepository->shouldReceive('update')->never();

        $subscription->shouldReceive('update')->never();

        $result = $this->processor->processSubscriptionPayment(
            $subscription,
            $plan,
            ['payment_method_id' => 'pm_test123']
        );

        $this->assertTrue($result['success']);
    }

    public function testProcessSubscriptionPaymentCreatesStripePrice(): void
    {
        $member = $this->createMockMember('cus_test123');
        $subscription = $this->createMockSubscription($member);
        $plan = $this->createMockPlan();
        $plan->stripe_price_id = null; // No existing price

        // Setup expectations
        $customer = $this->expectCustomerRetrieval('cus_test123');
        $this->expectPaymentMethodAttachment('pm_test123', $customer->id);
        $this->expectCustomerUpdate($customer->id, $customer);

        // Expect product and price creation
        $product = new \stdClass();
        $product->id = 'prod_test123';

        $this->productServiceMock->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) use ($plan) {
                return $data['name'] === $plan->name
                    && isset($data['metadata']['plan_id']);
            }))
            ->andReturn($product);

        $price = new \stdClass();
        $price->id = 'price_test123';

        $this->priceServiceMock->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) use ($plan) {
                return $data['product'] === 'prod_test123'
                    && $data['unit_amount'] === (int)($plan->price * 100)
                    && isset($data['recurring']);
            }))
            ->andReturn($price);

        $plan->shouldReceive('update')
            ->once()
            ->with(['stripe_price_id' => 'price_test123']);

        $stripeSubscription = $this->expectSubscriptionCreation('sub_test123', 'active', 'in_test123');
        $this->expectInvoiceRetrieval('in_test123', 'pi_test123');

        $payment = $this->expectPaymentCreation($subscription->id);
        $this->expectPaymentUpdate($payment->id, 'completed');

        $subscription->shouldReceive('update')->once();

        $result = $this->processor->processSubscriptionPayment(
            $subscription,
            $plan,
            ['payment_method_id' => 'pm_test123']
        );

        $this->assertTrue($result['success']);
    }

    public function testProcessSubscriptionPaymentHandlesStripeError(): void
    {
        $member = $this->createMockMember('cus_test123');
        $subscription = $this->createMockSubscription($member);
        $plan = $this->createMockPlan();

        $customer = $this->expectCustomerRetrieval('cus_test123');

        // Mock payment method attachment failure
        $exception = m::mock(\Stripe\Exception\CardException::class);
        $exception->shouldReceive('getMessage')->andReturn('Your card was declined');
        $exception->shouldReceive('getStripeCode')->andReturn('card_declined');

        $this->paymentMethodServiceMock->shouldReceive('attach')
            ->once()
            ->andThrow($exception);

        $result = $this->processor->processSubscriptionPayment(
            $subscription,
            $plan,
            ['payment_method_id' => 'pm_test123']
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('card was declined', $result['message']);
    }

    public function testCreatePaymentIntent(): void
    {
        $paymentIntent = new \stdClass();
        $paymentIntent->id = 'pi_test123';
        $paymentIntent->client_secret = 'pi_test123_secret_abc';

        $this->paymentIntentServiceMock->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) {
                return $data['amount'] === 10000
                    && $data['currency'] === 'usd'
                    && isset($data['automatic_payment_methods']);
            }))
            ->andReturn($paymentIntent);

        $result = $this->processor->createPaymentIntent([
            'amount' => 100.00,
            'currency' => 'USD',
            'order_id' => 123
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('pi_test123', $result['payment_intent_id']);
        $this->assertEquals('pi_test123_secret_abc', $result['client_secret']);
    }

    public function testProcessOneTimePaymentWithPaymentMethodId(): void
    {
        $paymentIntent = new \stdClass();
        $paymentIntent->id = 'pi_test123';
        $paymentIntent->status = 'succeeded';
        $paymentIntent->client_secret = 'pi_test123_secret';

        $this->paymentIntentServiceMock->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) {
                return $data['payment_method'] === 'pm_test123'
                    && $data['confirm'] === true
                    && isset($data['automatic_payment_methods']);
            }))
            ->andReturn($paymentIntent);

        $result = $this->processor->processOneTimePayment(
            ['amount' => 50.00, 'currency' => 'GBP', 'order_id' => 1],
            ['payment_method_id' => 'pm_test123']
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('pi_test123', $result['transaction_id']);
        $this->assertEquals('succeeded', $result['status']);
        $this->assertFalse($result['requires_action']);
    }

    public function testCancelSubscription(): void
    {
        $stripeSubscription = new \stdClass();
        $stripeSubscription->status = 'canceled';

        $this->subscriptionServiceMock->shouldReceive('cancel')
            ->once()
            ->with('sub_test123')
            ->andReturn($stripeSubscription);

        $result = $this->processor->cancelSubscription('sub_test123', false);

        $this->assertTrue($result['success']);
        $this->assertEquals('canceled', $result['status']);
    }

    public function testCancelSubscriptionImmediately(): void
    {
        $canceledSubscription = new \stdClass();
        $canceledSubscription->id = 'sub_test123';
        $canceledSubscription->status = 'canceled';
        $canceledSubscription->cancel_at_period_end = false;
        $canceledSubscription->canceled_at = time();
        $canceledSubscription->current_period_end = null;

        $this->subscriptionServiceMock->shouldReceive('cancel')
            ->once()
            ->with('sub_test123')
            ->andReturn($canceledSubscription);

        $result = $this->processor->cancelSubscription('sub_test123', false);

        $this->assertTrue($result['success']);
        $this->assertEquals('canceled', $result['status']);
        $this->assertFalse($result['cancel_at_period_end']);
    }

    public function testCancelSubscriptionAtPeriodEnd(): void
    {
        $subscription = new \stdClass();
        $subscription->id = 'sub_test123';
        $subscription->status = 'active';
        $subscription->cancel_at_period_end = true;
        $subscription->canceled_at = time();
        $subscription->current_period_end = time() + (30 * 24 * 60 * 60);

        $this->subscriptionServiceMock->shouldReceive('update')
            ->once()
            ->with('sub_test123', ['cancel_at_period_end' => true])
            ->andReturn($subscription);

        $result = $this->processor->cancelSubscription('sub_test123', true);

        $this->assertTrue($result['success']);
        $this->assertEquals('active', $result['status']);
        $this->assertTrue($result['cancel_at_period_end']);
    }

    public function testReactivateSubscription(): void
    {
        $subscription = new \stdClass();
        $subscription->id = 'sub_test123';
        $subscription->status = 'active';
        $subscription->cancel_at_period_end = true;

        $this->subscriptionServiceMock->shouldReceive('retrieve')
            ->once()
            ->with('sub_test123')
            ->andReturn($subscription);

        $this->subscriptionServiceMock->shouldReceive('update')
            ->once()
            ->with('sub_test123', ['cancel_at_period_end' => false])
            ->andReturn($subscription);

        $result = $this->processor->reactivateSubscription('sub_test123');

        $this->assertTrue($result['success']);
        $this->assertEquals('active', $result['status']);
        $this->assertFalse($result['cancel_at_period_end']);
    }

    public function testCreateRefund(): void
    {
        $refund = new \stdClass();
        $refund->id = 'ref_test123';
        $refund->amount = 5000; // cents
        $refund->status = 'succeeded';
        $refund->created = time();

        $refundServiceMock = m::mock(\Stripe\Service\RefundService::class);
        $this->stripeMock->refunds = $refundServiceMock;

        $refundServiceMock->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) {
                return $data['payment_intent'] === 'pi_test123'
                    && $data['amount'] === 5000;
            }))
            ->andReturn($refund);

        $result = $this->processor->createRefund('pi_test123', ['amount' => 50.00]);

        $this->assertTrue($result['success']);
        $this->assertEquals('ref_test123', $result['refund_id']);
        $this->assertEquals(50.00, $result['amount']);
        $this->assertEquals('succeeded', $result['status']);
    }

    public function testReactivateSubscriptionSucceeds(): void
    {
        $subscription = new \stdClass();
        $subscription->id = 'sub_test123';
        $subscription->status = 'active';
        $subscription->cancel_at_period_end = true; // Set to cancel at period end

        // First retrieve to check status
        $this->subscriptionServiceMock->shouldReceive('retrieve')
            ->once()
            ->with('sub_test123')
            ->andReturn($subscription);

        // Then update to remove cancellation
        $updatedSubscription = new \stdClass();
        $updatedSubscription->id = 'sub_test123';
        $updatedSubscription->status = 'active';
        $updatedSubscription->cancel_at_period_end = false;

        $this->subscriptionServiceMock->shouldReceive('update')
            ->once()
            ->with('sub_test123', ['cancel_at_period_end' => false])
            ->andReturn($updatedSubscription);

        $result = $this->processor->reactivateSubscription('sub_test123');

        $this->assertTrue($result['success']);
        $this->assertEquals('active', $result['status']);
        $this->assertFalse($result['cancel_at_period_end']);
    }

    public function testReactivateSubscriptionFailsIfAlreadyCanceled(): void
    {
        $subscription = new \stdClass();
        $subscription->id = 'sub_test123';
        $subscription->status = 'canceled'; // Already fully canceled

        $this->subscriptionServiceMock->shouldReceive('retrieve')
            ->once()
            ->with('sub_test123')
            ->andReturn($subscription);

        $result = $this->processor->reactivateSubscription('sub_test123');

        $this->assertFalse($result['success']);
        $this->assertEquals('subscription_already_canceled', $result['error_code']);
        $this->assertStringContainsString('already been canceled', $result['message']);
    }

    public function testReactivateSubscriptionFailsIfNotScheduledForCancellation(): void
    {
        $subscription = new \stdClass();
        $subscription->id = 'sub_test123';
        $subscription->status = 'active';
        $subscription->cancel_at_period_end = false; // Not scheduled for cancellation

        $this->subscriptionServiceMock->shouldReceive('retrieve')
            ->once()
            ->with('sub_test123')
            ->andReturn($subscription);

        $result = $this->processor->reactivateSubscription('sub_test123');

        $this->assertFalse($result['success']);
        $this->assertEquals('subscription_not_scheduled_for_cancellation', $result['error_code']);
    }

    public function testGetCustomerPaymentMethods(): void
    {
        $member = $this->createMockMember('cus_test123');

        $customer = new \stdClass();
        $customer->invoice_settings = new \stdClass();
        $customer->invoice_settings->default_payment_method = 'pm_default123';

        $this->customerServiceMock->shouldReceive('retrieve')
            ->once()
            ->with('cus_test123')
            ->andReturn($customer);

        $paymentMethod1 = new \stdClass();
        $paymentMethod1->id = 'pm_test123';
        $paymentMethod2 = new \stdClass();
        $paymentMethod2->id = 'pm_test456';

        $paymentMethodsData = new \stdClass();
        $paymentMethodsData->data = [$paymentMethod1, $paymentMethod2];

        $this->paymentMethodServiceMock->shouldReceive('all')
            ->once()
            ->with([
                'customer' => 'cus_test123',
                'type' => 'card',
            ])
            ->andReturn($paymentMethodsData);

        $result = $this->processor->getCustomerPaymentMethods($member);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['payment_methods']);
        $this->assertEquals('pm_default123', $result['default_payment_method_id']);
    }

    public function testGetCustomerPaymentMethodsWithNoCustomer(): void
    {
        $member = $this->createMockMember(null);

        $result = $this->processor->getCustomerPaymentMethods($member);

        $this->assertEmpty($result['payment_methods']);
        $this->assertNull($result['default_payment_method_id']);
    }

    public function testAddPaymentMethodWithNewCustomer(): void
    {
        $member = $this->createMockMember(null);

        $customer = $this->expectCustomerCreation($member);

        // Mock member update to return true
        $member->shouldReceive('update')
            ->once()
            ->with(['stripe_customer_id' => $customer->id])
            ->andReturn(true);

        $this->expectPaymentMethodAttachment('pm_test123', $customer->id);

        $result = $this->processor->addPaymentMethod($member, 'pm_test123', false);

        $this->assertTrue($result['success']);
    }

    public function testAddPaymentMethodWithExistingCustomer(): void
    {
        $member = $this->createMockMember('cus_test123');

        $this->expectPaymentMethodAttachment('pm_test123', 'cus_test123');

        $result = $this->processor->addPaymentMethod($member, 'pm_test123', false);

        $this->assertTrue($result['success']);
    }

    public function testAddPaymentMethodSetAsDefault(): void
    {
        $member = $this->createMockMember('cus_test123');

        $this->expectPaymentMethodAttachment('pm_test123', 'cus_test123');

        $customer = new \stdClass();
        $customer->id = 'cus_test123';

        $this->customerServiceMock->shouldReceive('update')
            ->once()
            ->with('cus_test123', [
                'invoice_settings' => [
                    'default_payment_method' => 'pm_test123'
                ]
            ])
            ->andReturn($customer);

        $result = $this->processor->addPaymentMethod($member, 'pm_test123', true);

        $this->assertTrue($result['success']);
    }

    public function testSetDefaultPaymentMethod(): void
    {
        $customer = new \stdClass();
        $customer->id = 'cus_test123';

        $this->customerServiceMock->shouldReceive('update')
            ->once()
            ->with('cus_test123', [
                'invoice_settings' => [
                    'default_payment_method' => 'pm_test123'
                ]
            ])
            ->andReturn($customer);

        $result = $this->processor->setDefaultPaymentMethod('cus_test123', 'pm_test123');

        $this->assertTrue($result['success']);
    }

    public function testRemovePaymentMethod(): void
    {
        $member = $this->createMockMember('cus_test123');

        $paymentMethod = new \stdClass();
        $paymentMethod->id = 'pm_test123';
        $paymentMethod->customer = 'cus_test123';

        $this->paymentMethodServiceMock->shouldReceive('retrieve')
            ->once()
            ->with('pm_test123')
            ->andReturn($paymentMethod);

        $detachedPaymentMethod = new \stdClass();
        $detachedPaymentMethod->id = 'pm_test123';

        $this->paymentMethodServiceMock->shouldReceive('detach')
            ->once()
            ->with('pm_test123')
            ->andReturn($detachedPaymentMethod);

        $result = $this->processor->removePaymentMethod($member, 'pm_test123');

        $this->assertTrue($result['success']);
    }

    public function testRemovePaymentMethodUnauthorized(): void
    {
        $member = $this->createMockMember('cus_test123');

        $paymentMethod = new \stdClass();
        $paymentMethod->id = 'pm_test123';
        $paymentMethod->customer = 'cus_different456'; // Different customer

        $this->paymentMethodServiceMock->shouldReceive('retrieve')
            ->once()
            ->with('pm_test123')
            ->andReturn($paymentMethod);

        $result = $this->processor->removePaymentMethod($member, 'pm_test123');

        $this->assertFalse($result['success']);
        $this->assertEquals('unauthorized', $result['error_code']);
    }

}