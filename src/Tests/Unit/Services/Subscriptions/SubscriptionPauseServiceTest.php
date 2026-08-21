<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Subscriptions\PolicyEvaluationResult;
use App\DTO\Subscriptions\SubscriptionPolicySettingOverrides;
use App\Events\Subscriptions\SubscriptionPaused;
use App\Events\Subscriptions\SubscriptionResumed;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Support\Logger;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\Stripe\StripeSubscriptionGateway;
use App\Services\Subscriptions\Contracts\ReplacementPolicyInterface;
use App\Services\Subscriptions\PolicySettingOverrideResolver;
use App\Services\Subscriptions\ReplacementPolicyResolver;
use App\Services\Subscriptions\SubscriptionPauseService;
use App\Tests\Unit\UnitTestCase;
use DateTimeImmutable;
use Mockery;
use Mockery\MockInterface;

class SubscriptionPauseServiceTest extends UnitTestCase
{
    private SubscriptionRepository&MockInterface $subscriptionRepository;
    private EventDispatcher&MockInterface $eventDispatcher;
    private Database&MockInterface $databaseMock;
    private StripeSubscriptionGateway&MockInterface $stripeGateway;
    private $logger;
    private ReplacementPolicyResolver&MockInterface $policyResolver;
    private PolicySettingOverrideResolver&MockInterface $settingOverrideResolver;
    private ReplacementPolicyInterface&MockInterface $allowAllPolicy;
    private ReplacementPolicyInterface&MockInterface $currentPolicy;
    private ?array $lastResolveForPlanArgs = null;
    private bool $transactionShouldFail = false;
    private int $transactionCallCount = 0;
    private SubscriptionPauseService $service;

    public function test_pause_sets_status_to_paused_and_disables_auto_renew(): void
    {
        $sub = $this->makeSub(1, 42, 'active');
        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, Mockery::on(function (array $data) {
                return $data['status'] === 'paused'
                    && $data['auto_renew'] === false
                    && isset($data['paused_at']);
            }));

        $this->service->pause(1, 42);
    }

    public function test_pause_stores_pause_until_when_provided(): void
    {
        $sub = $this->makeSub(1, 42, 'active');
        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, Mockery::on(fn(array $data) => $data['pause_until'] !== null));

        $this->service->pause(1, 42, date('Y-m-d', strtotime('+30 days')));
    }

    public function test_pause_rejects_past_pause_until_date(): void
    {
        $sub = $this->makeSub(1, 42, 'active');
        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);
        $this->subscriptionRepository->shouldNotReceive('update');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Pause date must be after today.');

        $this->service->pause(1, 42, date('Y-m-d', strtotime('-1 day')));
    }

    public function test_pause_rejects_today_as_pause_until_date(): void
    {
        $sub = $this->makeSub(1, 42, 'active');
        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);

        $this->expectException(\RuntimeException::class);
        $this->service->pause(1, 42, date('Y-m-d'));
    }

    public function test_pause_caps_pause_until_at_90_days(): void
    {
        $sub = $this->makeSub(1, 42, 'active');
        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);

        $requestedDate = date('Y-m-d', strtotime('+200 days'));
        $maxDate = date('Y-m-d', strtotime('+90 days'));

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, Mockery::on(function (array $data) use ($maxDate) {
                return $data['pause_until'] <= $maxDate;
            }));

        $this->service->pause(1, 42, $requestedDate);
    }

    public function test_pause_synchronises_stripe_collection_for_stripe_subscription(): void
    {
        $sub = $this->makeSub(1, 42, 'active');
        $sub->setAttribute('payment_subscription_id', 'sub_123');
        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);

        $this->stripeGateway
            ->shouldReceive('pauseCollection')
            ->once()
            ->with('sub_123');

        $this->service->pause(1, 42);
    }

    public function test_pause_compensates_stripe_when_local_transaction_fails(): void
    {
        $sub = $this->makeSub(1, 42, 'active');
        $sub->setAttribute('payment_subscription_id', 'sub_123');
        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);

        $this->transactionShouldFail = true;

        $this->stripeGateway->shouldReceive('pauseCollection')->once()->with('sub_123');
        $this->stripeGateway->shouldReceive('resumeCollection')->once()->with('sub_123');

        $this->expectException(\RuntimeException::class);
        $this->service->pause(1, 42);
    }

    public function test_pause_dispatches_subscription_paused_event(): void
    {
        $sub = $this->makeSub(1, 42, 'active');
        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(fn($e) => $e instanceof SubscriptionPaused && $e->memberId === 42));

        $this->service->pause(1, 42);
    }

    public function test_pause_uses_a_transaction(): void
    {
        $sub = $this->makeSub(1, 42, 'active');
        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);

        $this->service->pause(1, 42);

        $this->assertSame(1, $this->transactionCallCount);
    }

    public function test_pause_throws_for_non_active_subscription(): void
    {
        $sub = $this->makeSub(1, 42, 'paused');
        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);

        $this->expectException(\RuntimeException::class);
        $this->service->pause(1, 42);
    }

    public function test_pause_throws_if_not_found(): void
    {
        $this->subscriptionRepository->shouldReceive('find')->andReturn(null);
        $this->expectException(\RuntimeException::class);
        $this->service->pause(999, 42);
    }

    public function test_pause_throws_if_wrong_member(): void
    {
        $sub = $this->makeSub(1, 99, 'active');
        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);
        $this->expectException(\RuntimeException::class);
        $this->service->pause(1, 42);
    }

    public function test_resume_transitions_to_active_and_restores_auto_renew(): void
    {
        $sub = $this->makeSub(1, 42, 'paused');
        $sub->setAttribute('next_billing_date', '2026-07-01 00:00:00');
        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, Mockery::on(function (array $data) {
                return $data['status'] === 'active'
                    && $data['auto_renew'] === true
                    && $data['paused_at'] === null
                    && $data['pause_until'] === null
                    && isset($data['resumed_at'])
                    && $data['next_billing_date'] === '2026-07-01 00:00:00';
            }));

        $this->service->resume(1, 42);
    }

    public function test_resume_uses_stripe_current_period_end_as_authoritative_billing_date(): void
    {
        $sub = $this->makeSub(1, 42, 'paused');
        $sub->setAttribute('payment_subscription_id', 'sub_123');
        $sub->setAttribute('next_billing_date', '2026-07-01 00:00:00');
        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);

        $this->stripeGateway
            ->shouldReceive('resumeCollection')
            ->once()
            ->with('sub_123')
            ->andReturn(new DateTimeImmutable('2026-07-21 00:00:00'));

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, Mockery::on(
                fn(array $data) => $data['next_billing_date'] === '2026-07-21 00:00:00'
            ));

        $this->service->resume(1, 42);
    }

    public function test_resume_preserves_existing_local_billing_date_without_stripe(): void
    {
        $sub = $this->makeSub(1, 42, 'paused');
        $sub->setAttribute('paused_at', date('Y-m-d H:i:s', strtotime('-30 days')));
        $sub->setAttribute('next_billing_date', '2026-07-01 00:00:00');
        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, Mockery::on(
                fn(array $data) => $data['next_billing_date'] === '2026-07-01 00:00:00'
            ));

        $this->service->resume(1, 42);
    }

    public function test_resume_compensates_stripe_when_local_transaction_fails(): void
    {
        $sub = $this->makeSub(1, 42, 'paused');
        $sub->setAttribute('payment_subscription_id', 'sub_123');
        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);

        $this->transactionShouldFail = true;

        $this->stripeGateway
            ->shouldReceive('resumeCollection')
            ->once()
            ->with('sub_123')
            ->andReturn(new DateTimeImmutable('2026-07-01 00:00:00'));
        $this->stripeGateway->shouldReceive('pauseCollection')->once()->with('sub_123');

        $this->expectException(\RuntimeException::class);
        $this->service->resume(1, 42);
    }

    public function test_resume_dispatches_subscription_resumed_event(): void
    {
        $sub = $this->makeSub(1, 42, 'paused');
        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::on(fn($e) => $e instanceof SubscriptionResumed && $e->memberId === 42));

        $this->service->resume(1, 42);
    }

    public function test_resume_uses_a_transaction(): void
    {
        $sub = $this->makeSub(1, 42, 'paused');
        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);
        $this->service->resume(1, 42);

        $this->assertSame(1, $this->transactionCallCount);
    }

    public function test_resume_throws_if_not_paused(): void
    {
        $sub = $this->makeSub(1, 42, 'active');
        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);
        $this->expectException(\RuntimeException::class);
        $this->service->resume(1, 42);
    }

    public function test_resume_throws_if_not_found(): void
    {
        $this->subscriptionRepository->shouldReceive('find')->andReturn(null);
        $this->expectException(\RuntimeException::class);
        $this->service->resume(999, 42);
    }

    public function test_can_pause_returns_true_for_active(): void
    {
        $sub = $this->makeSub(1, 42, 'active');
        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);
        $this->assertTrue($this->service->canPause(1, 42));
    }

    public function test_can_pause_returns_false_for_paused(): void
    {
        $sub = $this->makeSub(1, 42, 'paused');
        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);
        $this->assertFalse($this->service->canPause(1, 42));
    }

    public function test_can_pause_returns_false_for_wrong_member(): void
    {
        $sub = $this->makeSub(1, 99, 'active');
        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);
        $this->assertFalse($this->service->canPause(1, 42));
    }

    public function test_can_resume_returns_true_for_paused(): void
    {
        $sub = $this->makeSub(1, 42, 'paused');
        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);
        $this->assertTrue($this->service->canResume(1, 42));
    }

    public function test_can_resume_returns_false_for_active(): void
    {
        $sub = $this->makeSub(1, 42, 'active');
        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);
        $this->assertFalse($this->service->canResume(1, 42));
    }

    public function test_resume_with_future_date_schedules_instead_of_resuming(): void
    {
        $sub = $this->makeSub(1, 42, 'paused');
        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);

        $futureDate = date('Y-m-d', strtotime('+10 days'));

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->once()
            ->with(1, Mockery::on(fn(array $d) =>
                $d['pause_until'] === $futureDate && $d['scheduled_resume_at'] === $futureDate
            ));
        $this->eventDispatcher->shouldNotReceive('dispatch');

        $this->service->resume(1, 42, $futureDate);
    }

    public function test_resume_with_today_date_resumes_immediately(): void
    {
        $sub = $this->makeSub(1, 42, 'paused');
        $sub->setAttribute('next_billing_date', '2026-07-01 00:00:00');
        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);

        $this->eventDispatcher->shouldReceive('dispatch')->once();

        $this->service->resume(1, 42, date('Y-m-d'));
    }

    public function test_process_scheduled_resume_resumes_when_due(): void
    {
        $sub = $this->makeSub(1, 42, 'paused');
        $sub->setAttribute('scheduled_resume_at', date('Y-m-d', strtotime('-1 day')));
        $sub->setAttribute('next_billing_date', '2026-07-01 00:00:00');
        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);

        $this->eventDispatcher->shouldReceive('dispatch')->once();

        $this->service->processScheduledResume(1);
    }

    public function test_process_scheduled_resume_returns_null_when_not_scheduled(): void
    {
        $sub = $this->makeSub(1, 42, 'active');
        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);

        $this->assertNull($this->service->processScheduledResume(1));
    }

    public function test_pause_resolves_policy_for_the_subscriptions_plan(): void
    {
        $sub = $this->makeSub(1, 42, 'active');
        $sub->setAttribute('plan_id', 7);
        $sub->setAttribute('site_id', 3);
        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);

        $this->service->pause(1, 42);

        $this->assertSame([7, 3, 1], $this->lastResolveForPlanArgs);
    }

    public function test_pause_throws_and_does_not_persist_when_policy_denies(): void
    {
        $sub = $this->makeSub(1, 42, 'active');
        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);

        $deniedPolicy = Mockery::mock(ReplacementPolicyInterface::class);
        $deniedPolicy->shouldReceive('evaluatePause')->andReturn(
            PolicyEvaluationResult::denied('This plan allows one pause per subscription term, which has already been used.')
        );
        $this->currentPolicy = $deniedPolicy;

        $this->subscriptionRepository->shouldNotReceive('update');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('This plan allows one pause per subscription term, which has already been used.');

        $this->service->pause(1, 42);
    }

    public function test_pause_context_carries_the_requested_resume_date(): void
    {
        $sub = $this->makeSub(1, 42, 'active');
        $this->subscriptionRepository->shouldReceive('find')->andReturn($sub);

        $capturedContext = null;
        $capturingPolicy = Mockery::mock(ReplacementPolicyInterface::class);
        $capturingPolicy->shouldReceive('evaluatePause')
            ->andReturnUsing(function ($context) use (&$capturedContext) {
                $capturedContext = $context;

                return PolicyEvaluationResult::allowed();
            });
        $this->currentPolicy = $capturingPolicy;

        $tomorrow = (new DateTimeImmutable('+2 days'))->format('Y-m-d');
        $this->service->pause(1, 42, $tomorrow);

        $this->assertNotNull($capturedContext);
        $this->assertSame($tomorrow, $capturedContext->requestedResumeDate->format('Y-m-d'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class)->shouldIgnoreMissing();
        $this->eventDispatcher = Mockery::mock(EventDispatcher::class)->shouldIgnoreMissing();
        $this->databaseMock = Mockery::mock(Database::class);
        $this->stripeGateway = Mockery::mock(StripeSubscriptionGateway::class)->shouldIgnoreMissing();

        $this->databaseMock
            ->shouldReceive('transaction')
            ->andReturnUsing(function (callable $cb) {
                $this->transactionCallCount++;

                if ($this->transactionShouldFail) {
                    throw new \RuntimeException('database failed');
                }

                return $cb();
            });

        // Default: policy resolution always yields a policy that allows
        // the pause. Tests override $this->currentPolicy (not a second
        // ->shouldReceive('resolveForPlan') call — Mockery mocks use the
        // most-recently-defined matching expectation, so a second call()
        // configuration here would silently shadow this default instead of
        // composing with it, which is not what these tests want) to
        // exercise denial/capture paths.
        $this->allowAllPolicy = Mockery::mock(ReplacementPolicyInterface::class);
        $this->allowAllPolicy->shouldReceive('evaluatePause')->andReturn(PolicyEvaluationResult::allowed());
        $this->currentPolicy = $this->allowAllPolicy;

        $this->policyResolver = Mockery::mock(ReplacementPolicyResolver::class);
        $this->policyResolver->shouldReceive('resolveForPlan')->andReturnUsing(
            function (int $planId, int $siteId, ?int $subscriptionId = null) {
                $this->lastResolveForPlanArgs = [$planId, $siteId, $subscriptionId];

                return $this->currentPolicy;
            }
        );

        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $this->service = new SubscriptionPauseService(
            $this->subscriptionRepository,
            $this->eventDispatcher,
            $this->databaseMock,
            $this->stripeGateway,
            $this->logger,
            $this->policyResolver,
        );
    }

    private function makeSub(int $id, int $memberId, string $status): Subscription
    {
        $sub = Mockery::mock(Subscription::class)->makePartial();
        $sub->setAttribute('id', $id);
        $sub->setAttribute('member_id', $memberId);
        $sub->setAttribute('status', $status);
        $sub->setAttribute('payment_subscription_id', null);

        return $sub;
    }
}
