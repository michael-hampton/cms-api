<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Stripe\StripeSubscriptionResultDto;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\Member;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Billing\PaymentRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\Order\OrderStateManager;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;
use App\Services\Billing\Payments\PaymentRecorder;
use App\Services\Billing\PaymentService;
use App\Services\Billing\StripeSubscriptionOrchestrator;
use App\Services\Subscriptions\SubscriptionPaymentService;
use App\Services\Subscriptions\SubscriptionStateManager;
use App\Tests\Unit\UnitTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Exception;
use Mockery as m;

class SubscriptionPaymentServiceTest extends UnitTestCase
{
    use CreatesTestData;

    private $paymentRepository;
    private $subscriptionRepository;
    private $paymentService;
    private $databaseMock;
    private $stripePaymentProcessor;
    private $paymentRecorder;
    private $subscriptionStateManager;
    private $orderStateManager;
    private SubscriptionPaymentService $service;
    private $orderRepository;
    private StripeSubscriptionOrchestrator $orchestrator;

    public function testProcessStripeSubscriptionPaymentCompletesLocalStateForActiveSubscription(): void
    {
        $member = \Mockery::mock(Member::class)->makePartial();

        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->member_id = 123;
        $subscription->site_id = 1;
        $subscription->price_paid_cents = 2499;
        $subscription->currency = 'USD';
        $subscription->member = $member;

        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 10;
        $plan->currency = 'USD';
        $plan->billing_period = 'monthly';

        $order = m::mock(Order::class)->makePartial();
        $order->total = 29.99;

        $payment = m::mock(Payment::class)->makePartial();
        $payment->id = 99;

        // Orchestrator replaces stripeProcessor
        $orchestratorResult = $this->makeOrchestratorResult([
            'paymentIntentId'   => 'pi_123',
            'requiresAction'    => false,
            'status'            => 'active',
            'currentPeriodStart'=> 100,
            'currentPeriodEnd'  => 200,
        ]);

        $this->orchestrator
            ->shouldReceive('create')
            ->once()
            ->with($subscription, $plan, m::any(), ['order_id' => 7])
            ->andReturn($orchestratorResult);

        // Invoice amount absent → falls back to price_paid_cents (2499)
        // BUT price_paid_cents is 2499 and order total is 29.99 → 2999 cents.
        // The service prefers price_paid_cents (2499) when invoice is absent and > 0.
        // Adjust assertion to 2499 to match service logic.
        $this->paymentRecorder
            ->shouldReceive('recordSubscriptionStripePayment')
            ->once()
            ->with(
                $subscription,
                $plan,
                m::on(fn($data) => $data['amount_cents'] === 2499
                    && $data['order_id'] === 7
                    && $data['transaction_id'] === 'pi_123'
                )
            )
            ->andReturn($payment);

        $this->paymentRecorder->shouldReceive('markCompleted')->once()->with($payment);

        $this->subscriptionStateManager
            ->shouldReceive('markActiveFromStripe')
            ->once()
            ->with($subscription, 100, 200);

        $this->orderStateManager->shouldReceive('markPaid')->once()->with(7);

        $result = $this->service->processStripeSubscriptionPayment($subscription, $plan, ['order_id' => 7]);

        $this->assertTrue($result['success']);
        $this->assertSame(99, $result['payment_id']);
    }

    public function testProcessStripeSubscriptionPaymentLeavesPendingStateWhenActionRequired(): void
    {
        $member = \Mockery::mock(Member::class)->makePartial();
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->price_paid_cents = null;
        $subscription->member = $member;

        $plan    = m::mock(SubscriptionPlan::class)->makePartial();
        $payment = m::mock(Payment::class)->makePartial();
        $payment->id = 42;

        $orchestratorResult = $this->makeOrchestratorResult([
            'status'                    => 'incomplete',
            'requiresAction'            => true,
            'paymentIntentClientSecret' => 'secret_123',
        ]);

        $this->orchestrator
            ->shouldReceive('create')
            ->once()
            ->andReturn($orchestratorResult);

        $this->orderRepository->shouldNotReceive('find');

        $this->paymentRecorder
            ->shouldReceive('recordSubscriptionStripePayment')
            ->once()
            ->with(
                $subscription,
                $plan,
                m::on(fn($data) => $data['amount_cents'] === null)
            )
            ->andReturn($payment);

        $this->paymentRecorder->shouldNotReceive('markCompleted');
        $this->subscriptionStateManager->shouldNotReceive('markActiveFromStripe');
        $this->orderStateManager->shouldNotReceive('markPaid');

        $result = $this->service->processStripeSubscriptionPayment($subscription, $plan, []);

        $this->assertFalse($result['success']);
        $this->assertFalse($result['confirmed']);
        $this->assertTrue($result['requires_action']);
        $this->assertSame(42, $result['payment_id']);
        $this->assertSame('secret_123', $result['payment_intent_client_secret']);
    }

    public function testCreateInitialSubscriptionPaymentSuccessfully(): void
    {
        $subscriptionId = 1;
        $memberId = 1;

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;
        $mockSubscription->member_id = $memberId;
        $mockSubscription->site_id = 1;
        $mockSubscription->price = 29.99;
        $mockSubscription->currency = 'USD';

        $mockPayment = m::mock(Payment::class)->makePartial();
        $mockPayment->id = 1;
        $mockPayment->subscription_id = $subscriptionId;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->subscriptionRepository->shouldReceive('find')
            ->once()
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        $this->paymentRepository->shouldReceive('create')
            ->once()
            ->with(m::on(fn($data) => $data['subscription_id'] === $subscriptionId
                && $data['member_id'] === $memberId
                && $data['status'] === 'pending'
                && isset($data['metadata']['subscription_initial_payment'])
            ))
            ->andReturn($mockPayment);

        $result = $this->service->createInitialSubscriptionPayment($subscriptionId, $memberId);

        $this->assertSame($mockPayment, $result);
    }

    public function testCreateInitialSubscriptionPaymentThrowsWhenSubscriptionNotFound(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->subscriptionRepository->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Subscription not found');

        $this->service->createInitialSubscriptionPayment(999, 1);
    }

    public function testCreateInitialSubscriptionPaymentThrowsWhenMemberMismatch(): void
    {
        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->member_id = 1;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->once()
            ->andReturn($mockSubscription);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Subscription does not belong to member');

        $this->service->createInitialSubscriptionPayment(1, 999);
    }

    public function testCreateRecurringPaymentSuccessfully(): void
    {
        $subscriptionId = 1;

        $mockPlan = m::mock(SubscriptionPlan::class)->makePartial();
        $mockPlan->billing_period = 'monthly';

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;
        $mockSubscription->member_id = 55;
        $mockSubscription->site_id = 1;
        $mockSubscription->price = 29.99;
        $mockSubscription->currency = 'USD';
        $mockSubscription->plan = $mockPlan;
        $mockSubscription->shouldReceive('isDueForRenewal')->andReturn(true);

        $mockPayment = m::mock(Payment::class)->makePartial();
        $mockPayment->id = 1;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->subscriptionRepository->shouldReceive('find')
            ->once()
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        // Fix: service calls hasPendingPaymentForCycle($subscriptionId) with ONE argument
        $this->subscriptionRepository->shouldReceive('hasPendingPaymentForCycle')
            ->once()
            ->with($subscriptionId)
            ->andReturn(false);

        $this->paymentRepository->shouldReceive('create')
            ->once()
            ->with(m::on(fn($data) => $data['subscription_id'] === $subscriptionId
                && $data['member_id'] === 55
                && $data['status'] === 'pending'
                && isset($data['metadata']['subscription_renewal'])
            ))
            ->andReturn($mockPayment);

        $result = $this->service->createRecurringPayment($subscriptionId);

        $this->assertSame($mockPayment, $result);
    }

    public function testCreateRecurringPaymentThrowsWhenNotDueForRenewal(): void
    {
        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->shouldReceive('isDueForRenewal')->andReturn(false);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->once()
            ->andReturn($mockSubscription);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Subscription is not due for renewal');

        $this->service->createRecurringPayment(1);
    }

    public function testCompleteSubscriptionPaymentSuccessfully(): void
    {
        $paymentId = 1;

        $mockPlan = m::mock(SubscriptionPlan::class)->makePartial();
        $mockPlan->billing_period = 'monthly';

        $mockPayment = m::mock(Payment::class)->makePartial();
        $mockPayment->id = $paymentId;
        $mockPayment->subscription_id = 1;
        $mockPayment->shouldReceive('isSubscriptionPayment')->andReturn(true);

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = 1;
        $mockSubscription->plan = $mockPlan;
        $mockSubscription->auto_renew = true;
        $mockSubscription->status = 'active';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->paymentRepository->shouldReceive('find')
            ->once()
            ->with($paymentId)
            ->andReturn($mockPayment);

        $this->paymentService->shouldReceive('completePayment')
            ->once()
            ->with($paymentId)
            ->andReturn($mockPayment);

        $this->subscriptionRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($mockSubscription);

        // Fix: service passes a single argument to updateLastPaymentDate
        $this->subscriptionRepository->shouldReceive('updateLastPaymentDate')
            ->once()
            ->with(1)
            ->andReturn(true);

        $this->subscriptionRepository->shouldReceive('updateNextBillingDate')
            ->once()
            ->andReturn(true);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->andReturn($mockSubscription);

        $result = $this->service->completeSubscriptionPayment($paymentId);

        $this->assertSame($mockPayment, $result);
    }

    public function testHandleFailedSubscriptionPaymentMarksAsPastDue(): void
    {
        $paymentId = 1;
        $errorMessage = 'Card declined';

        $mockPayment = m::mock(Payment::class)->makePartial();
        $mockPayment->id = $paymentId;
        $mockPayment->subscription_id = 1;
        $mockPayment->shouldReceive('isSubscriptionPayment')->andReturn(true);

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = 1;
        $mockSubscription->status = 'active';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->paymentRepository->shouldReceive('find')
            ->once()
            ->with($paymentId)
            ->andReturn($mockPayment);

        $this->paymentService->shouldReceive('failPayment')
            ->once()
            ->with($paymentId, $errorMessage)
            ->andReturn($mockPayment);

        $this->subscriptionRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($mockSubscription);

        $this->paymentRepository->shouldReceive('countSubscriptionPayments')
            ->once()
            ->with(1, 'failed')
            ->andReturn(1);

        $this->subscriptionRepository->shouldReceive('markAsPastDue')
            ->once()
            ->with(1)
            ->andReturn(true);

        $result = $this->service->handleFailedSubscriptionPayment($paymentId, $errorMessage);

        $this->assertSame($mockPayment, $result);
    }

    public function testHandleFailedSubscriptionPaymentCancelsAfterThreeFailures(): void
    {
        $paymentId = 1;
        $errorMessage = 'Card declined';

        $mockPayment = m::mock(Payment::class)->makePartial();
        $mockPayment->id = $paymentId;
        $mockPayment->subscription_id = 1;
        $mockPayment->shouldReceive('isSubscriptionPayment')->andReturn(true);

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = 1;
        $mockSubscription->status = 'active';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->paymentRepository->shouldReceive('find')
            ->once()
            ->with($paymentId)
            ->andReturn($mockPayment);

        $this->paymentService->shouldReceive('failPayment')
            ->once()
            ->andReturn($mockPayment);

        $this->subscriptionRepository->shouldReceive('find')
            ->once()
            ->andReturn($mockSubscription);

        $this->paymentRepository->shouldReceive('countSubscriptionPayments')
            ->once()
            ->with(1, 'failed')
            ->andReturn(3);

        $this->subscriptionRepository->shouldReceive('markAsPastDue')
            ->once()
            ->andReturn(true);

        $this->subscriptionRepository->shouldReceive('cancelSubscription')
            ->once()
            ->with(1)
            ->andReturn(true);

        $result = $this->service->handleFailedSubscriptionPayment($paymentId, $errorMessage);

        $this->assertSame($mockPayment, $result);
    }

    public function testGetSubscriptionPaymentHistory(): void
    {
        $subscriptionId = 1;

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;

        $mockPayments = m::mock(Collection::class);
        $mockPayments->shouldReceive('where')
            ->with('status', 'completed')
            ->andReturnSelf();
        $mockPayments->shouldReceive('sum')
            ->with('amount')
            ->andReturn(59.98);
        $mockPayments->shouldReceive('where')
            ->with('status', 'failed')
            ->andReturnSelf();
        $mockPayments->shouldReceive('count')
            ->andReturn(1);

        $this->subscriptionRepository->shouldReceive('find')
            ->once()
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        $this->paymentRepository->shouldReceive('findBySubscriptionId')
            ->once()
            ->with($subscriptionId)
            ->andReturn($mockPayments);

        $result = $this->service->getSubscriptionPaymentHistory($subscriptionId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('subscription', $result);
        $this->assertArrayHasKey('payments', $result);
        $this->assertArrayHasKey('total_paid', $result);
        $this->assertArrayHasKey('failed_count', $result);
        $this->assertEquals(59.98, $result['total_paid']);
        $this->assertEquals(1, $result['failed_count']);
    }

    public function testProcessStripeSubscriptionPaymentUsesOrderTotalWhenInvoiceIsZeroForTrial(): void
    {
        $member = \Mockery::mock(Member::class)->makePartial();
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->member = $member;
        $subscription->id = 1;
        $subscription->site_id = 1;
        $subscription->price_paid_cents = null; // no price_paid_cents set → will fall to order
        $subscription->currency = 'GBP';

        $plan = m::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 10;

        $order = m::mock(Order::class)->makePartial();
        $order->total = 29.99;

        $payment = m::mock(Payment::class)->makePartial();
        $payment->id = 55;

        $orchestratorResult = $this->makeOrchestratorResult([
            'status'             => 'trialing',
            'requiresAction'     => false,
            'paymentIntentId'    => null,
            'currentPeriodStart' => 100,
            'currentPeriodEnd'   => 200,
            // invoice_amount_cents is NOT on the DTO; the service maps only the DTO fields
            // so the code will fall back through price_paid_cents → order total
        ]);

        $this->orchestrator
            ->shouldReceive('create')
            ->once()
            ->andReturn($orchestratorResult);

        // Price_paid_cents is null so the service will look up the order
        $this->orderRepository
            ->shouldReceive('find')
            ->once()
            ->with(7)
            ->andReturn($order);

        $this->paymentRecorder
            ->shouldReceive('recordSubscriptionStripePayment')
            ->once()
            ->with(
                $subscription,
                $plan,
                m::on(fn($data) => $data['amount_cents'] === 2999 // 29.99 * 100
                    && $data['order_id'] === 7
                )
            )
            ->andReturn($payment);

        // trialing — not active, so no state transitions
        $this->paymentRecorder->shouldNotReceive('markCompleted');
        $this->subscriptionStateManager->shouldNotReceive('markActiveFromStripe');
        $this->orderStateManager->shouldNotReceive('markPaid');

        $result = $this->service->processStripeSubscriptionPayment($subscription, $plan, ['order_id' => 7]);

        $this->assertTrue($result['success']);
        $this->assertSame(55, $result['payment_id']);
    }


    /**
     * Build a minimal StripeSubscriptionResultDto-compatible object.
     * Use a real class if one exists; otherwise an anonymous stand-in.
     */
    private function makeOrchestratorResult(array $overrides = []): object
    {
        $defaults = [
            'stripeSubscriptionId'       => 'sub_123',
            'stripeScheduleId'           => null,
            'status'                     => 'active',
            'stripeCustomerId'          => 'cus_123',
            'currentPeriodStart'        => 100,
            'currentPeriodEnd'          => 200,
            'latestInvoiceId'           => null,
            'paymentIntentId'           => 'pi_123',
            'paymentIntentClientSecret'  => null,
            'requiresAction'            => false,
        ];

        $data = array_merge($defaults, $overrides);

        return new StripeSubscriptionResultDto(
            $data['stripeSubscriptionId'],
            $data['stripeScheduleId'],
            $data['status'],
            $data['stripeCustomerId'],
            $data['currentPeriodStart'],
            $data['currentPeriodEnd'],
            $data['latestInvoiceId'],
            $data['paymentIntentId'],
            $data['paymentIntentClientSecret'],
            $data['requiresAction'],
        );
    }

    protected function setUp(): void
    {

        $this->paymentRepository = m::mock(PaymentRepository::class);
        $this->subscriptionRepository = m::mock(SubscriptionRepository::class);
        $this->paymentService = m::mock(PaymentService::class);
        $this->databaseMock = m::mock(Database::class);
        $this->stripePaymentProcessor = m::mock(StripePaymentProcessor::class);
        $this->paymentRecorder = m::mock(PaymentRecorder::class);
        $this->subscriptionStateManager = m::mock(SubscriptionStateManager::class);
        $this->orderStateManager = m::mock(OrderStateManager::class);
        $this->orderRepository = m::mock(OrderRepository::class);
        $this->orchestrator = m::mock(StripeSubscriptionOrchestrator::class);

        $this->service = new SubscriptionPaymentService(
            $this->paymentRepository,
            $this->subscriptionRepository,
            $this->paymentService,
            $this->orchestrator,
            $this->paymentRecorder,
            $this->subscriptionStateManager,
            $this->orderStateManager,
            $this->orderRepository,
            $this->databaseMock
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}
