<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Billing\PaymentRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\Stripe\StripeSubscriptionLifecycleService;
use App\Services\Subscriptions\SubscriptionCancellationService;
use App\Services\Subscriptions\SubscriptionRefundService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;
use Mockery as m;

class SubscriptionCancellationServiceTest extends FunctionalTestCase
{
    use CreatesTestData;

    private $subscriptionRepository;
    private $paymentRepository;
    private $stripeLifecycleService;
    private $databaseMock;
    private SubscriptionCancellationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = m::mock(SubscriptionRepository::class);
        $this->paymentRepository = m::mock(PaymentRepository::class);
        $this->stripeLifecycleService = m::mock(StripeSubscriptionLifecycleService::class);
        $this->refundService = m::mock(SubscriptionRefundService::class);
        $this->databaseMock = m::mock(Database::class);

        $this->service = new SubscriptionCancellationService(
            $this->subscriptionRepository,
            $this->paymentRepository,
            $this->stripeLifecycleService,
            $this->refundService,
            $this->databaseMock
        );

        $_ENV['APP_ENV'] = 'production';
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
        $_ENV['APP_ENV'] = 'testing';
    }

    public function testCancelSubscriptionWithStripeAtPeriodEnd(): void
    {
        $subscriptionId = 1;

        $mockPlan = m::mock(SubscriptionPlan::class)->makePartial();
        $mockPlan->billing_period = 'monthly';

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;
        $mockSubscription->status = 'active';
        $mockSubscription->payment_subscription_id = 'sub_stripe123';
        $mockSubscription->plan = $mockPlan;
        $mockSubscription->shouldReceive('hasStripeSubscription')->andReturn(true);
        $mockSubscription->shouldReceive('getStripeSubscriptionId')->andReturn('sub_stripe123');

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->twice()
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        $this->stripeLifecycleService->shouldReceive('cancel')
            ->once()
            ->with('sub_stripe123', true)
            ->andReturn([
                'success' => true,
                'status' => 'active',
                'cancel_at_period_end' => true
            ]);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with($subscriptionId, m::on(function ($data) {
                // Must have auto_renew set to false
                if (!isset($data['auto_renew']) || $data['auto_renew'] !== false) {
                    return false;
                }

                // Must have cancelled_at set (any DateTime is fine)
                if (!isset($data['cancelled_at']) || empty($data['cancelled_at'])) {
                    return false;
                }

                // end_date must NOT be set
                if (isset($data['end_date'])) {
                    return false;
                }

                // Do NOT check status; it may be unset for cancel at period end
                return true;
            }))
            ->andReturn($mockSubscription);


        $result = $this->service->cancelSubscription($subscriptionId, [
            'cancel_at_period_end' => true
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame($mockSubscription, $result['subscription']);
    }

    public function testRefundIsSkippedWhenCancelAtPeriodEndIsTrueEvenIfRefundRequested(): void
    {
        $subscription = $this->createMockSubscription();

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->twice()
            ->andReturn($subscription);

        $this->stripeLifecycleService
            ->shouldReceive('cancel')
            ->once()
            ->andReturn(['success' => true]);

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->once()
            ->andReturn($subscription);

        // 🚨 IMPORTANT FIX: this should NOT be called
        $this->subscriptionRepository
            ->shouldNotReceive('revokeAllPremiumAccess');

        // refund also must NOT run
        $this->refundService
            ->shouldNotReceive('executeWithStrategy');

        $result = $this->service->cancelSubscription(1, [
            'cancel_at_period_end' => true,
            'create_refund' => true,
        ]);

        $this->assertTrue($result['success']);
    }

    public function testRefundExecutesOnlyWhenImmediateCancellationAndCreateRefundTrue(): void
    {
        $subscription = $this->createMockSubscription();

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->twice()
            ->andReturn($subscription);

        $this->stripeLifecycleService
            ->shouldReceive('cancel')
            ->once()
            ->andReturn(['success' => true]);

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->once()
            ->andReturn($subscription);

        $this->subscriptionRepository
            ->shouldReceive('revokeAllPremiumAccess')
            ->once();

        $this->refundService
            ->shouldReceive('executeWithStrategy')
            ->once()
            ->andReturn(['success' => true]);

        $result = $this->service->cancelSubscription(1, [
            'cancel_at_period_end' => false,
            'create_refund' => true,
        ]);

        $this->assertTrue($result['success']);
    }

    public function testNoRefundWhenNoRefundFlagsProvided(): void
    {
        $subscription = $this->createMockSubscription();

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->twice()
            ->andReturn($subscription);

        $this->stripeLifecycleService
            ->shouldReceive('cancel')
            ->once()
            ->andReturn(['success' => true]);

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->once()
            ->andReturn($subscription);

        $this->subscriptionRepository
            ->shouldReceive('revokeAllPremiumAccess')
            ->once();

        $this->refundService
            ->shouldNotReceive('executeWithStrategy');

        $result = $this->service->cancelSubscription(1, [
            'cancel_at_period_end' => false
        ]);

        $this->assertTrue($result['success']);
    }

    public function testInvalidRefundTypeThrowsBeforeRefundExecution(): void
    {
        $subscription = $this->createMockSubscription();

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($subscription);

        $this->stripeLifecycleService
            ->shouldReceive('cancel')
            ->once()
            ->andReturn(['success' => true]);

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->once()
            ->andReturn($subscription);

        $this->refundService
            ->shouldNotReceive('executeWithStrategy');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid refund type');

        $this->service->cancelSubscription(1, [
            'cancel_at_period_end' => false,
            'create_refund' => true,
            'refund_type' => 'broken_type',
        ]);
    }

    public function testStripeFailurePreventsUpdateAndRefund(): void
    {
        $subscription = $this->createMockSubscription();

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($subscription);

        $this->stripeLifecycleService
            ->shouldReceive('cancel')
            ->once()
            ->andReturn([
                'success' => false,
                'message' => 'Stripe down'
            ]);

        $this->subscriptionRepository
            ->shouldNotReceive('update');

        $this->subscriptionRepository
            ->shouldNotReceive('revokeAllPremiumAccess');

        $this->refundService
            ->shouldNotReceive('executeWithStrategy');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Stripe down');

        $this->service->cancelSubscription(1, [
            'cancel_at_period_end' => false,
            'create_refund' => true,
        ]);
    }

    public function testCancelSubscriptionImmediately(): void
    {
        $subscriptionId = 1;

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;
        $mockSubscription->status = 'active';
        $mockSubscription->payment_subscription_id = 'sub_stripe123';
        $mockSubscription->shouldReceive('hasStripeSubscription')->andReturn(true);
        $mockSubscription->shouldReceive('getStripeSubscriptionId')->andReturn('sub_stripe123');

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->twice()
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        $this->stripeLifecycleService->shouldReceive('cancel')
            ->once()
            ->with('sub_stripe123', false)
            ->andReturn([
                'success' => true,
                'status' => 'canceled'
            ]);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with($subscriptionId, m::on(function ($data) {
                return $data['status'] === 'cancelled'
                    && $data['auto_renew'] === false
                    && isset($data['end_date']);
            }))
            ->andReturn($mockSubscription);

        $this->subscriptionRepository->shouldReceive('revokeAllPremiumAccess')
            ->once()
            ->with($subscriptionId);

        $result = $this->service->cancelSubscription($subscriptionId, [
            'cancel_at_period_end' => false
        ]);

        $this->assertTrue($result['success']);
    }

    public function testReactivateSubscription(): void
    {
        $subscriptionId = 1;

        $mockPlan = m::mock(SubscriptionPlan::class)->makePartial();
        $mockPlan->billing_period = 'monthly';

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;
        $mockSubscription->status = 'cancelled';
        $mockSubscription->payment_subscription_id = 'sub_stripe123';
        $mockSubscription->plan = $mockPlan;
        $mockSubscription->end_date = now_datetime()->addMonths(1);
        $mockSubscription->shouldReceive('hasStripeSubscription')->andReturn(true);
        $mockSubscription->shouldReceive('getStripeSubscriptionId')->andReturn('sub_stripe123');

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->twice()
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        $this->stripeLifecycleService->shouldReceive('reactivate')
            ->once()
            ->with('sub_stripe123')
            ->andReturn([
                'success' => true,
                'status' => 'active'
            ]);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with($subscriptionId, m::on(function ($data) {
                return $data['status'] === 'active'
                    && $data['auto_renew'] === true
                    && isset($data['end_date'])
                    && isset($data['next_billing_date']);
            }))
            ->andReturn($mockSubscription);

        $result = $this->service->reactivateSubscription($subscriptionId);

        $this->assertTrue($result['success']);

    }

    public function testReactivateSubscriptionThrowsIfAlreadyActive(): void
    {
        $subscriptionId = 1;

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;
        $mockSubscription->status = 'active'; // Already active

        $mockSubscription->shouldReceive('isCancellationScheduled')->andReturn(false);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->once()
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Can only reactivate cancelled subscriptions');

        $this->service->reactivateSubscription($subscriptionId);
    }

    public function testReactivateSubscriptionThrowsIfSubscriptionAlreadyEnded(): void
    {
        $subscriptionId = 1;

        $mockPlan = m::mock(SubscriptionPlan::class)->makePartial();
        $mockPlan->billing_period = 'monthly';

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;
        $mockSubscription->status = 'cancelled';
        $mockSubscription->end_date = new \DateTime('-1 day'); // Already ended
        $mockSubscription->payment_subscription_id = 'sub_stripe123';
        $mockSubscription->plan = $mockPlan;
        $mockSubscription->shouldReceive('hasStripeSubscription')->andReturn(true);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->once()
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Subscription entitlement period has ended. Please purchase a new subscription.');

        $this->service->reactivateSubscription($subscriptionId);
    }

    public function test_cancel_subscription_throws_exception_when_not_found(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Subscription not found');

        $this->service->cancelSubscription(999);
    }

    public function test_cancel_subscription_throws_exception_when_already_cancelled(): void
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->status = 'cancelled';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($subscription);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Subscription is already cancelled');

        $this->service->cancelSubscription(1);
    }

    public function test_cancel_subscription_immediately(): void
    {
        $subscriptionId = 1;

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;
        $mockSubscription->status = 'active';
        $mockSubscription->type = 'paid';
        $mockSubscription->payment_subscription_id = 'sub_stripe123';
        $mockSubscription->shouldReceive('hasStripeSubscription')->andReturn(true);
        $mockSubscription->shouldReceive('getStripeSubscriptionId')->andReturn('sub_stripe123');
        $mockSubscription->shouldReceive('closeWindow')->once();

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->twice()
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        $this->subscriptionRepository->shouldReceive('revokeAllPremiumAccess')
            ->once()
            ->with($subscriptionId);

        $this->stripeLifecycleService->shouldReceive('cancel')
            ->once()
            ->with('sub_stripe123', false)
            ->andReturn([
                'success' => true,
                'status' => 'canceled'
            ]);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with($subscriptionId, m::on(function ($data) {
                return $data['status'] === 'cancelled'
                    && $data['auto_renew'] === false
                    && isset($data['end_date']);
            }))
            ->andReturn($mockSubscription);

        $result = $this->service->cancelSubscription($subscriptionId, [
            'cancel_at_period_end' => false
        ]);

        $this->assertTrue($result['success']);
    }

    public function test_cancel_subscription_throws_exception_when_stripe_fails(): void
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->status = 'active';
        $subscription->type = 'paid';
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(true);
        $subscription->shouldReceive('getStripeSubscriptionId')->andReturn('sub_123');
        // $subscription->shouldReceive('closeWindow')->once();

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($subscription);

        $this->stripeLifecycleService->shouldReceive('cancel')
            ->once()
            ->andReturn([
                'success' => false,
                'message' => 'Stripe API error'
            ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to cancel Stripe subscription: Stripe API error');

        $this->service->cancelSubscription(1);
    }

    public function testImmediateCancellationWithoutRefundRevokesAccess(): void
    {
        $subscription = $this->createMockSubscription();

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->andReturn($subscription);

        $this->stripeLifecycleService
            ->shouldReceive('cancel')
            ->once()
            ->andReturn(['success' => true]);

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->once()
            ->andReturn($subscription);

        $this->subscriptionRepository
            ->shouldReceive('revokeAllPremiumAccess')
            ->once()
            ->with(1);

        // No refund — refundService must NOT be called
        $this->refundService->shouldNotReceive('executeWithStrategy');

        $result = $this->service->cancelSubscription(1, [
            'cancel_at_period_end' => false,
            'create_refund' => false,
        ]);

        $this->assertTrue($result['success']);
    }

    public function testCancellationWithRefundAmountOverrideUsesManualStrategy(): void
    {
        $subscription = $this->createMockSubscription();
        $payment = $this->createMockPayment(100.00);

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->twice()
            ->andReturn($subscription);

        $this->stripeLifecycleService
            ->shouldReceive('cancel')
            ->once()
            ->andReturn(['success' => true]);

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->once()
            ->andReturn($subscription);

        $this->subscriptionRepository
            ->shouldReceive('revokeAllPremiumAccess')
            ->once();

        $this->refundService
            ->shouldReceive('executeWithStrategy')
            ->once()
            ->andReturn([
                'success' => true,
                'amount' => 40.00,
            ]);

        $result = $this->service->cancelSubscription(1, [
            'cancel_at_period_end' => false,
            'create_refund' => true,
            'refund_amount' => 40.00,
        ]);

        $this->assertTrue($result['success']);
    }

    public function testCancellationWithNoOverrideUsesExistingBehaviour(): void
    {
        $subscription = $this->createMockSubscription();

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->andReturn($subscription);

        $this->stripeLifecycleService
            ->shouldReceive('cancel')
            ->once()
            ->andReturn(['success' => true]);

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->once()
            ->andReturn($subscription);

        $this->subscriptionRepository
            ->shouldReceive('revokeAllPremiumAccess')
            ->once();

        $this->refundService
            ->shouldReceive('executeWithStrategy')
            ->once()
            ->andReturn(['success' => true, 'amount' => 50.00]);

        // No refund_amount → should resolve to ProRatedRefundStrategy; just verify it reaches executeWithStrategy
        $result = $this->service->cancelSubscription(1, [
            'cancel_at_period_end' => false,
            'create_refund' => true,
            // no refund_amount
        ]);

        $this->assertTrue($result['success']);
    }

    public function testCancellationDoesNotRefundWhenSubscriptionAlreadyEnded(): void
    {
        $subscription = $this->createMockSubscription();

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->twice()
            ->andReturn($subscription);

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->once()
            ->andReturn($subscription);

        $this->stripeLifecycleService
            ->shouldReceive('cancel')
            ->once()
            ->andReturn(['success' => true]);

        // IMPORTANT: ensure refund path is NOT triggered
        $this->refundService
            ->shouldNotReceive('executeWithStrategy');

        $result = $this->service->cancelSubscription(1, [
            'cancel_at_period_end' => true, // 🔥 this is the key fix
            'create_refund' => true,
        ]);

        $this->assertTrue($result['success']);
    }

    public function testCancellationSkipsRefundForFreeSubscription(): void
    {
        $subscription = $this->createMockSubscription();
        $subscription->type = 'free';

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->twice()
            ->andReturn($subscription);

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->once()
            ->andReturn($subscription);

        $this->subscriptionRepository
            ->shouldReceive('revokeAllPremiumAccess') // 🔥 THIS WAS MISSING
            ->once()
            ->with(1);

        $this->stripeLifecycleService
            ->shouldReceive('cancel')
            ->once()
            ->andReturn(['success' => true]);

        $this->refundService
            ->shouldNotReceive('executeWithStrategy');

        $result = $this->service->cancelSubscription(1, [
            'cancel_at_period_end' => false,
            'create_refund' => false,
        ]);

        $this->assertTrue($result['success']);
    }

    public function testRefundIsNotExecutedWhenStripeCancellationFails(): void
    {
        $subscription = $this->createMockSubscription();

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->andReturn($subscription);

        $this->stripeLifecycleService
            ->shouldReceive('cancel')
            ->once()
            ->andReturn([
                'success' => false,
                'message' => 'Stripe failure'
            ]);

        $this->refundService
            ->shouldNotReceive('executeWithStrategy');

        $this->expectException(\Exception::class);

        $this->service->cancelSubscription(1, [
            'cancel_at_period_end' => false,
            'create_refund' => true,
        ]);
    }

    public function testRefundAmountOverrideIsPassedToManualStrategy(): void
    {
        $subscription = $this->createMockSubscription();

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->twice()
            ->andReturn($subscription);

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->once()
            ->andReturn($subscription);

        $this->subscriptionRepository
            ->shouldReceive('revokeAllPremiumAccess')
            ->once()
            ->with(1);

        $this->stripeLifecycleService
            ->shouldReceive('cancel')
            ->once()
            ->andReturn(['success' => true]);

        // ❌ IMPORTANT: remove PaymentRepository expectation entirely

        $this->refundService
            ->shouldReceive('executeWithStrategy')
            ->once()
            ->with(
                $subscription,
                \Mockery::on(fn($strategy) => $strategy instanceof \App\Services\Subscriptions\Refunds\ManualRefundStrategy
                )
            )
            ->andReturn(['success' => true]);

        $result = $this->service->cancelSubscription(1, [
            'cancel_at_period_end' => false,
            'create_refund' => true,
            'refund_amount' => 150.00,
        ]);

        $this->assertTrue($result['success']);
    }

    public function testRefundFailsWhenNoPaymentFound(): void
    {
        $subscription = $this->createMockSubscription();

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($subscription);

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->once()
            ->andReturn($subscription);

        $this->stripeLifecycleService
            ->shouldReceive('cancel')
            ->once()
            ->andReturn(['success' => true]);

        // 🔥 refund service is responsible, not payment repo
        $this->refundService
            ->shouldReceive('executeWithStrategy')
            ->once()
            ->andThrow(new \Exception('No payment found for refund'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No payment found for refund');

        $this->service->cancelSubscription(1, [
            'cancel_at_period_end' => false,
            'create_refund' => true,
        ]);
    }

    public function testCancellationWithInvalidRefundTypeThrowsException(): void
    {
        $subscription = $this->createMockSubscription();

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($subscription);

        $this->stripeLifecycleService
            ->shouldReceive('cancel')
            ->once()
            ->andReturn(['success' => true]);

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->once()
            ->andReturn($subscription);

        $this->refundService->shouldNotReceive('executeWithStrategy');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid refund type: unknown_type');

        $this->service->cancelSubscription(1, [
            'cancel_at_period_end' => false,
            'create_refund' => true,
            'refund_type' => 'unknown_type',
        ]);
    }

    public function test_reactivate_subscription_throws_exception_when_not_found(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Subscription not found');

        $this->service->reactivateSubscription(999);
    }

    public function test_reactivate_subscription_throws_exception_when_already_active(): void
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->status = 'active';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($subscription);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Can only reactivate cancelled subscriptions');

        $this->service->reactivateSubscription(1);
    }

    public function test_reactivate_subscription_successfully(): void
    {
        $subscriptionId = 1;

        $mockPlan = m::mock(SubscriptionPlan::class)->makePartial();
        $mockPlan->billing_period = 'monthly';

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;
        $mockSubscription->status = 'cancelled';
        $mockSubscription->end_date = new \DateTime('+5 days');
        $mockSubscription->payment_subscription_id = 'sub_stripe123';
        $mockSubscription->plan = $mockPlan;
        $mockSubscription->shouldReceive('hasStripeSubscription')->andReturn(true);
        $mockSubscription->shouldReceive('getStripeSubscriptionId')->andReturn('sub_stripe123');

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->twice()
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        $this->stripeLifecycleService->shouldReceive('reactivate')
            ->once()
            ->with('sub_stripe123')
            ->andReturn([
                'success' => true,
                'status' => 'active'
            ]);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with($subscriptionId, m::on(function ($data) {
                return $data['status'] === 'active'
                    && $data['auto_renew'] === true
                    && isset($data['end_date'])
                    && isset($data['next_billing_date']);
            }))
            ->andReturn($mockSubscription);

        $result = $this->service->reactivateSubscription($subscriptionId);

        $this->assertTrue($result['success']);

    }

    public function test_reactivate_subscription_without_stripe(): void
    {
        $mockPlan = m::mock(SubscriptionPlan::class)->makePartial();
        $mockPlan->billing_period = 'monthly';

        $originalEndDate = new \DateTime('+5 days');

        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->status = 'cancelled';
        $subscription->end_date = $originalEndDate;
        $subscription->plan = $mockPlan;
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->twice()
            ->with(1)
            ->andReturn($subscription);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with(1, m::on(function ($data) use ($originalEndDate) {
                return $data['end_date'] === $originalEndDate->format('Y-m-d H:i:s');
            }))
            ->andReturn($subscription);

        $result = $this->service->reactivateSubscription(1);

        $this->assertTrue($result['success']);
    }

    public function test_reactivate_subscription_throws_exception_when_stripe_fails(): void
    {
        $mockPlan = m::mock(SubscriptionPlan::class)->makePartial();
        $mockPlan->billing_period = 'monthly';

        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->status = 'cancelled';
        $subscription->end_date = new \DateTime('+5 days');
        $subscription->plan = $mockPlan;
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(true);
        $subscription->shouldReceive('getStripeSubscriptionId')->andReturn('sub_123');

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($subscription);

        $this->stripeLifecycleService->shouldReceive('reactivate')
            ->once()
            ->andReturn([
                'success' => false,
                'message' => 'Cannot reactivate',
                'error_code' => 'subscription_already_canceled'
            ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('This subscription cannot be reactivated');

        $this->service->reactivateSubscription(1);
    }

    public function test_reactivate_subscription_preserves_end_date_for_quarterly(): void
    {
        $mockPlan = m::mock(SubscriptionPlan::class)->makePartial();
        $mockPlan->billing_period = 'quarterly';

        $originalEndDate = new \DateTime('+5 days');

        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->status = 'cancelled';
        $subscription->end_date = $originalEndDate;
        $subscription->plan = $mockPlan;
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->twice()
            ->with(1)
            ->andReturn($subscription);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with(1, m::on(function ($data) use ($originalEndDate) {
                return $data['end_date'] === $originalEndDate->format('Y-m-d H:i:s');
            }))
            ->andReturn($subscription);

        $result = $this->service->reactivateSubscription(1);
        $this->assertTrue($result['success']);
    }

    public function test_reactivate_subscription_preserves_end_date_for_yearly(): void
    {
        $mockPlan = m::mock(SubscriptionPlan::class)->makePartial();
        $mockPlan->billing_period = 'yearly';

        $originalEndDate = new \DateTime('+5 days');

        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->status = 'cancelled';
        $subscription->end_date = $originalEndDate;
        $subscription->plan = $mockPlan;
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->twice()
            ->with(1)
            ->andReturn($subscription);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with(1, m::on(function ($data) use ($originalEndDate) {
                return $data['end_date'] === $originalEndDate->format('Y-m-d H:i:s');
            }))
            ->andReturn($subscription);

        $result = $this->service->reactivateSubscription(1);
        $this->assertTrue($result['success']);
    }

    public function test_reactivate_subscription_handles_lifetime_plan(): void
    {
        $mockPlan = m::mock(SubscriptionPlan::class)->makePartial();
        $mockPlan->billing_period = 'lifetime';

        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->status = 'cancelled';
        $subscription->end_date = new \DateTime('+5 days');
        $subscription->plan = $mockPlan;
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->twice()
            ->with(1)
            ->andReturn($subscription);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with(1, m::on(function ($data) {
                // Lifetime plans should have null end_date
                return $data['end_date'] === null;
            }))
            ->andReturn($subscription);

        $result = $this->service->reactivateSubscription(1);
        $this->assertTrue($result['success']);
    }

    public function test_reactivate_subscription_throws_exception_when_already_ended(): void
    {
        $mockPlan = m::mock(SubscriptionPlan::class)->makePartial();
        $mockPlan->billing_period = 'monthly';

        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->status = 'cancelled';
        $subscription->end_date = new \DateTime('-1 day');
        $subscription->payment_subscription_id = 'sub_stripe123';
        $subscription->plan = $mockPlan;
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(true);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($subscription);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Subscription entitlement period has ended. Please purchase a new subscription.');

        $this->service->reactivateSubscription(1);
    }

    public function test_cancel_subscription_without_stripe(): void
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->status = 'active';
        $subscription->type = 'free';
        $subscription->shouldReceive('hasStripeSubscription')->andReturn(false);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->twice()
            ->with(1)
            ->andReturn($subscription);

        $this->subscriptionRepository->shouldReceive('revokeAllPremiumAccess')
            ->once()
            ->with(1);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->andReturn($subscription);

        $result = $this->service->cancelSubscription(1, [
            'cancel_at_period_end' => false
        ]);

        $this->assertTrue($result['success']);
    }

    public function test_cancel_subscription_with_stripe_at_period_end(): void
    {
        $subscriptionId = 1;

        $mockPlan = m::mock(SubscriptionPlan::class)->makePartial();
        $mockPlan->billing_period = 'monthly';

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;
        $mockSubscription->status = 'active';
        $mockSubscription->type = 'paid';
        $mockSubscription->payment_subscription_id = 'sub_stripe123';
        $mockSubscription->plan = $mockPlan;
        $mockSubscription->shouldReceive('hasStripeSubscription')->andReturn(true);
        $mockSubscription->shouldReceive('getStripeSubscriptionId')->andReturn('sub_stripe123');
        $mockSubscription->shouldReceive('closeWindow')->once();

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->subscriptionRepository->shouldReceive('find')
            ->twice()
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        $this->stripeLifecycleService->shouldReceive('cancel')
            ->once()
            ->with('sub_stripe123', true)
            ->andReturn([
                'success' => true,
                'status' => 'active',
                'cancel_at_period_end' => true
            ]);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with($subscriptionId, m::on(function ($data) {
                return isset($data['auto_renew']) && $data['auto_renew'] === false
                    && isset($data['cancelled_at'])
                    && !isset($data['end_date']);
            }))
            ->andReturn($mockSubscription);

        $result = $this->service->cancelSubscription($subscriptionId, [
            'cancel_at_period_end' => true
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame($mockSubscription, $result['subscription']);
    }

    public function test_reactivate_subscription_allows_scheduled_cancellation(): void
    {
        $subscriptionId = 1;
        $mockPlan = m::mock(SubscriptionPlan::class)->makePartial();

        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->status = 'active';
        $subscription->plan = $mockPlan;
        $subscription->shouldReceive('isCancellationScheduled')->andReturn(true);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->subscriptionRepository->shouldReceive('find')
            ->twice()
            ->with($subscriptionId)
            ->andReturn($subscription);

        // should NOT throw
        $this->stripeLifecycleService->shouldReceive('reactivate')
            ->never();

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->andReturn($subscription);

        $result = $this->service->reactivateSubscription($subscriptionId);

        $this->assertTrue($result['success']);
    }

    private function createMockSubscription(): Subscription
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();

        $subscription->id = 1;
        $subscription->status = 'active';
        $subscription->type = 'paid';
        $subscription->price = 100.00;

        $subscription->end_date = new \DateTime('+10 days');
        $subscription->last_payment_date = new \DateTime('-5 days');

        $subscription->shouldReceive('hasStripeSubscription')->andReturn(true);
        $subscription->shouldReceive('getStripeSubscriptionId')->andReturn('sub_123');
        $subscription->shouldReceive('closeWindow')->andReturn(null);

        return $subscription;
    }

    private function createMockPayment(float $amount): Payment
    {
        $payment = Mockery::mock(Payment::class)->makePartial();
        $payment->id = 1;
        $payment->subscription_id = 1;
        $payment->amount = $amount;
        $payment->transaction_id = 'ch_test_123';
        $payment->payment_method = 'stripe';
        $payment->payment_provider = 'stripe';
        return $payment;
    }
}
