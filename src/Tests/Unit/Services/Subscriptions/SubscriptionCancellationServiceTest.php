<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Subscriptions\BusinessDecisions\ResolvedCancellationOptions;
use App\DTO\Subscriptions\PolicyEvaluationResult;
use App\DTO\Subscriptions\SubscriptionPolicySettingOverrides;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\CancellationReason;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Billing\PaymentRepository;
use App\Repositories\Subscriptions\BusinessDecisions\CancellationReasonRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\Stripe\StripeSubscriptionLifecycleService;
use App\Services\Members\Consents\ConsentService;
use App\Services\Subscriptions\BusinessDecisions\CancellationOptionsResolver;
use App\Services\Subscriptions\Contracts\ReplacementPolicyInterface;
use App\Services\Subscriptions\PolicySettingOverrideResolver;
use App\Services\Subscriptions\ReplacementPolicyResolver;
use App\Services\Subscriptions\SubscriptionCancellationService;
use App\Services\Subscriptions\SubscriptionRefundService;
use Mockery;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class SubscriptionCancellationServiceTest extends TestCase
{
    private $subscriptionRepository;
    private $paymentRepository;
    private $stripeLifecycleService;
    private $databaseMock;
    private $policyResolver;
    private $settingOverrideResolver;
    private $cancellationReasonRepository;
    private $cancellationOptionsResolver;
    private $consentService;
    private $logger;
    private $events;
    private $allowAllPolicy;
    private SubscriptionCancellationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = m::mock(SubscriptionRepository::class);
        $this->paymentRepository = m::mock(PaymentRepository::class);
        $this->stripeLifecycleService = m::mock(StripeSubscriptionLifecycleService::class);
        $this->refundService = m::mock(SubscriptionRefundService::class);
        $this->databaseMock = m::mock(Database::class);

        // Default: no reason resolved unless a test explicitly stubs
        // one — keeps every pre-existing test (written before reasons
        // were DB-driven) unaffected, since a null resolved reason
        // skips both the Business Decision allow_cancel check and the
        // marketing-consent write entirely.
        $this->cancellationReasonRepository = m::mock(CancellationReasonRepository::class);
        $this->cancellationReasonRepository->shouldReceive('findActive')->andReturn(null)->byDefault();
        $this->cancellationReasonRepository->shouldReceive('findActiveByCode')->andReturn(null)->byDefault();

        $this->cancellationOptionsResolver = m::mock(CancellationOptionsResolver::class);
        $this->consentService = m::mock(ConsentService::class);

        // Default: policy resolution always yields a policy that allows
        // the cancellation, so pre-existing tests below (written before
        // policy integration existed) don't need to know about it.
        // Tests exercising denial override these expectations directly.
        $this->allowAllPolicy = m::mock(ReplacementPolicyInterface::class);
        $this->allowAllPolicy->shouldReceive('evaluateCancellation')
            ->andReturn(PolicyEvaluationResult::allowed())
            ->byDefault();

        $this->policyResolver = m::mock(ReplacementPolicyResolver::class);
        $this->policyResolver->shouldReceive('resolveForPlan')
            ->andReturn($this->allowAllPolicy)
            ->byDefault();

        // Default: no admin overrides configured for the site — a plain
        // mock (not a real PolicySettingOverrideResolver) so tests never
        // hit a real repository/DB. See PolicySettingOverrideResolverTest
        // and StandardConsumerPolicyTest for override-resolution and
        // override-interpretation coverage respectively; this service
        // only resolves and forwards overrides onto the context.
        $this->settingOverrideResolver = m::mock(PolicySettingOverrideResolver::class);
        $this->settingOverrideResolver->shouldReceive('resolveForSitePolicy')
            ->andReturn(new SubscriptionPolicySettingOverrides())
            ->byDefault();

        $this->events = \App\Tests\Support\CapturingEventDispatcher::fake();

        $this->logger = m::mock(Logger::class)->shouldIgnoreMissing();

        $this->service = new SubscriptionCancellationService(
            $this->subscriptionRepository,
            $this->paymentRepository,
            $this->stripeLifecycleService,
            $this->refundService,
            $this->cancellationReasonRepository,
            $this->cancellationOptionsResolver,
            $this->consentService,
            $this->logger,
            $this->databaseMock,
            $this->policyResolver,
            null,
            $this->settingOverrideResolver,
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
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
        $mockSubscription->shouldReceive('closeWindow')->never();

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

    public function testCancelSubscriptionResolvesPolicyForTheSubscriptionsPlan(): void
    {
        $subscriptionId = 1;

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;
        $mockSubscription->status = 'active';
        $mockSubscription->plan_id = 9;
        $mockSubscription->site_id = 4;
        $mockSubscription->shouldReceive('hasStripeSubscription')->andReturn(false);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->subscriptionRepository->shouldReceive('find')
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->andReturn($mockSubscription);

        $this->policyResolver->shouldReceive('resolveForPlan')
            ->once()
            ->with(9, 4, $subscriptionId)
            ->andReturn($this->allowAllPolicy);

        $this->service->cancelSubscription($subscriptionId);

        $this->assertTrue(true);
    }

    public function testCancelSubscriptionThrowsAndDoesNotPersistWhenPolicyDenies(): void
    {
        $subscriptionId = 1;

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;
        $mockSubscription->status = 'active';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->subscriptionRepository->shouldReceive('find')
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        $this->subscriptionRepository->shouldReceive('update')->never();

        $deniedPolicy = m::mock(ReplacementPolicyInterface::class);
        $deniedPolicy->shouldReceive('evaluateCancellation')
            ->andReturn(PolicyEvaluationResult::requiresManagerApproval(
                'This plan requires manager approval before a cancellation can be processed.'
            ));
        $this->policyResolver->shouldReceive('resolveForPlan')->andReturn($deniedPolicy);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('This plan requires manager approval before a cancellation can be processed.');

        $this->service->cancelSubscription($subscriptionId);
    }

    public function testCancelSubscriptionPassesCancellationReasonIntoPolicyContext(): void
    {
        $subscriptionId = 1;

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;
        $mockSubscription->status = 'active';
        $mockSubscription->plan_id = 9;
        $mockSubscription->site_id = 4;
        $mockSubscription->shouldReceive('member')->andReturn(null);

        $mockReason = m::mock(CancellationReason::class)->makePartial();
        $mockReason->id = 42;
        $mockReason->code = 'too_expensive';
        $this->cancellationReasonRepository->shouldReceive('findActiveByCode')
            ->with('too_expensive')
            ->andReturn($mockReason);

        $this->cancellationOptionsResolver->shouldReceive('resolveOptionsForReasonId')
            ->with(9, 4, 42)
            ->andReturn(new ResolvedCancellationOptions(
                showSaveActions: true,
                allowDiscount: true,
                allowOfferSwitch: true,
                allowCancel: true,
                refundMaxPercent: 50,
                marketingConsent: false,
            ));

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->subscriptionRepository->shouldReceive('find')
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->andReturn($mockSubscription);

        $capturedContext = null;
        $capturingPolicy = m::mock(ReplacementPolicyInterface::class);
        $capturingPolicy->shouldReceive('evaluateCancellation')
            ->andReturnUsing(function ($context) use (&$capturedContext) {
                $capturedContext = $context;

                return PolicyEvaluationResult::allowed();
            });
        $this->policyResolver->shouldReceive('resolveForPlan')->andReturn($capturingPolicy);

        $this->service->cancelSubscription($subscriptionId, [
            'cancellation_reason' => 'too_expensive',
        ]);

        $this->assertNotNull($capturedContext);
        $this->assertSame('too_expensive', $capturedContext->reason);
    }

    public function testCancelSubscriptionResolvesSettingOverridesForTheSubscriptionsSiteAndPolicyClass(): void
    {
        $subscriptionId = 1;

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;
        $mockSubscription->status = 'active';
        $mockSubscription->plan_id = 9;
        $mockSubscription->site_id = 4;
        $mockSubscription->shouldReceive('hasStripeSubscription')->andReturn(false);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->subscriptionRepository->shouldReceive('find')
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->andReturn($mockSubscription);

        $this->policyResolver->shouldReceive('resolveForPlan')
            ->andReturn($this->allowAllPolicy);

        $capturedArgs = null;
        $this->settingOverrideResolver->shouldReceive('resolveForSitePolicy')
            ->once()
            ->andReturnUsing(function (int $siteId, string $policyClass) use (&$capturedArgs) {
                $capturedArgs = [$siteId, $policyClass];

                return new SubscriptionPolicySettingOverrides();
            });

        $this->service->cancelSubscription($subscriptionId);

        $this->assertSame(4, $capturedArgs[0]);
        $this->assertSame($this->allowAllPolicy::class, $capturedArgs[1]);
    }

    public function testCancelSubscriptionContextCarriesTheResolvedSettingOverrides(): void
    {
        $subscriptionId = 1;

        $mockSubscription = m::mock(Subscription::class)->makePartial();
        $mockSubscription->id = $subscriptionId;
        $mockSubscription->status = 'active';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->subscriptionRepository->shouldReceive('find')
            ->with($subscriptionId)
            ->andReturn($mockSubscription);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->andReturn($mockSubscription);

        $overrides = new SubscriptionPolicySettingOverrides(['cancellation_allowed' => false === false]);
        $this->settingOverrideResolver->shouldReceive('resolveForSitePolicy')->andReturn($overrides);

        $capturedContext = null;
        $capturingPolicy = m::mock(ReplacementPolicyInterface::class);
        $capturingPolicy->shouldReceive('evaluateCancellation')
            ->andReturnUsing(function ($context) use (&$capturedContext) {
                $capturedContext = $context;

                return PolicyEvaluationResult::allowed();
            });
        $this->policyResolver->shouldReceive('resolveForPlan')->andReturn($capturingPolicy);

        $this->service->cancelSubscription($subscriptionId);

        $this->assertSame($overrides, $capturedContext->settingOverrides);
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