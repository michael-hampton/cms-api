<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Enums\Subscriptions\IssueDeliveryStatus;
use App\Framework\Container;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Jobs\Subscriptions\GenerateIssueDeliveriesJob;
use App\Models\IssueDelivery;
use App\Models\Newsletter;
use App\Models\Subscription;
use App\Models\SubscriptionWindow;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\IssuesDeliveredRepository;
use App\Services\Newsletter\NewsletterSendService;
use App\Services\Subscriptions\IssueDeliveryEligibilityService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use DomainException;
use Mockery;

class GenerateIssueDeliveriesJobTest extends FunctionalTestCase
{
    use CreatesTestData;

    private $mockSendService;
    private $mockResolver;
    private $mockRepository;
    private $mockIssueDeliveryRepository;
    private $mockLogger;
    private Database $databaseMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockSendService = Mockery::mock(NewsletterSendService::class);
        $this->mockResolver = Mockery::mock(IssueDeliveryEligibilityService::class);
        $this->mockRepository = Mockery::mock(IssuesDeliveredRepository::class);
        $this->mockIssueDeliveryRepository = Mockery::mock(IssueDeliveryRepository::class);
        $this->mockLogger = Mockery::mock(Logger::class)->shouldIgnoreMissing();
        $this->databaseMock = Mockery::mock(Database::class);

        $container = Container::getInstance();
        $container->instance(IssuesDeliveredRepository::class, $this->mockRepository);
        $container->instance(IssueDeliveryRepository::class, $this->mockIssueDeliveryRepository);
        $container->instance(IssueDeliveryEligibilityService::class, $this->mockResolver);
        $container->instance(Database::class, $this->databaseMock);
        $container->instance(Logger::class, $this->mockLogger);
    }

    public function test_creates_deliveries_for_eligible_subscriptions(): void
    {
        $page = $this->createPage();
        $plan = $this->createSubscriptionPlan(['premium_access' => [['identifier' => 'test', 'type' => 'newsletter']]]);
        $member = $this->createMember();
        $issueDelivery = IssueDelivery::create([
            'subscription_plan_id' => $plan->id,
            'on_sale_date' => now_datetime()->addDays(7),
            'status' => IssueDeliveryStatus::ACTIVE->value,
            'site_id' => $this->siteId
        ]);

        $subscription = Subscription::create([
            'plan_id' => $plan->id,
            'status' => 'active',
            'start_date' => now_datetime()->subDays(30),
            'end_date' => now_datetime()->addDays(30),
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Test Plan',
            'type' => 'paid',
            'delivery_type' => 'digital'
        ]);

        SubscriptionWindow::create([
            'subscription_id' => $subscription->id,
            'window_start' => now_datetime()->subDays(30),
            'window_end' => now_datetime()->addDays(30),
            'member_id' => $member->id,
            'site_id' => $this->siteId
        ]);

        $eligibleSubscriptions = collect([$subscription]);

        $this->mockIssueDeliveryRepository->shouldReceive('find')
            ->with($issueDelivery->id)
            ->andReturn($issueDelivery);

        $this->mockResolver->shouldReceive('getEligibleSubscriptions')
            ->with($issueDelivery)
            ->andReturn($eligibleSubscriptions);

        $this->mockRepository->shouldReceive('existsForSubscriptionAndSchedule')
            ->with($subscription->id, $issueDelivery->id)
            ->andReturn(false);

        $this->mockRepository->shouldReceive('createForSubscription')
            ->with($subscription->id, $issueDelivery->id)
            ->andReturnUsing(function ($subscriptionId, $issueDeliveryId) {
                return \App\Models\IssuesDelivered::create([
                    'subscription_id' => $subscriptionId,
                    'issue_delivery_id' => $issueDeliveryId,
                ]);
            });

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn(callable $cb) => $cb());

        $job = GenerateIssueDeliveriesJob::for($issueDelivery->id);
        $job->__wakeup();
        $result = $job->handle();

        $this->assertEquals(1, $result['created']);
        $this->assertEquals(1, $result['dispatched']);
        $this->assertEquals(0, $result['skipped']);

        // Assert that the delivery record actually exists in the database
        $this->assertDatabaseHas('issues_delivered', [
            'subscription_id' => $subscription->id,
            'issue_delivery_id' => $issueDelivery->id,
        ]);
    }

    public function test_skips_existing_deliveries_idempotent(): void
    {
        $issueDelivery = $this->makeIssueDelivery(IssueDeliveryStatus::ACTIVE);
        $subscription = Subscription::create([
            'plan_id' => $this->createSubscriptionPlan()->id,
            'status' => 'active',
            'member_id' => $this->createMember()->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Test Plan',
            'start_date' => now_datetime()->subDays(30),
            'end_date' => now_datetime()->addDays(30),
            'type' => 'paid',
            'delivery_type' => 'digital',
        ]);

        $eligibleSubscriptions = collect([$subscription]);
        $alreadyCreated = [];

        $this->mockIssueDeliveryRepository
            ->shouldReceive('find')
            ->with($issueDelivery->id)
            ->andReturn($issueDelivery);

        $this->mockResolver
            ->shouldReceive('getEligibleSubscriptions')
            ->andReturn($eligibleSubscriptions);

        $this->mockRepository
            ->shouldReceive('existsForSubscriptionAndSchedule')
            ->andReturnUsing(function ($subscriptionId) use (&$alreadyCreated) {
                return in_array($subscriptionId, $alreadyCreated);
            });

        $this->mockRepository
            ->shouldReceive('createForSubscription')
            ->andReturnUsing(function ($subscriptionId, $issueDeliveryId) use (&$alreadyCreated) {
                $alreadyCreated[] = $subscriptionId;
                $issueDelivered = new \App\Models\IssuesDelivered();
                $issueDelivered->id = 99;
                $issueDelivered->subscription_id = $subscriptionId;
                $issueDelivered->issue_delivery_id = $issueDeliveryId;
                return $issueDelivered;
            });

        $this->databaseMock
            ->shouldReceive('transaction')
            ->andReturnUsing(fn($cb) => $cb());

        // First run — should create
        $job = GenerateIssueDeliveriesJob::for($issueDelivery->id);
        $job->__wakeup();
        $firstResult = $job->handle();

        $this->assertEquals(1, $firstResult['created']);
        $this->assertEquals(0, $firstResult['skipped']);

        // Second run — should skip because existsForSubscriptionAndSchedule now returns true
        $job2 = GenerateIssueDeliveriesJob::for($issueDelivery->id);
        $job2->__wakeup();
        $secondResult = $job2->handle();

        $this->assertEquals(0, $secondResult['created']);
        $this->assertEquals(1, $secondResult['skipped']);
    }

    public function test_only_processes_subscriptions_within_active_window(): void
    {
        $issueDelivery = $this->makeIssueDelivery(IssueDeliveryStatus::ACTIVE);

        $this->mockIssueDeliveryRepository
            ->shouldReceive('find')
            ->once()
            ->with($issueDelivery->id)
            ->andReturn($issueDelivery);

        // Eligibility service returns empty — simulates all subscriptions being
        // outside their active window for this issue delivery date
        $this->mockResolver
            ->shouldReceive('getEligibleSubscriptions')
            ->once()
            ->with($issueDelivery)
            ->andReturn(collect([]));

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn(callable $cb) => $cb());

        $issueDelivery->shouldReceive('markDispatched')->once();

        $job = GenerateIssueDeliveriesJob::for($issueDelivery->id);
        $job->__wakeup();
        $result = $job->handle();

        $this->assertEquals(0, $result['created']);
        $this->assertEquals(0, $result['skipped']);
    }

    public function test_skips_non_active_delivery(): void
    {
        $issueDelivery = $this->makeIssueDelivery(IssueDeliveryStatus::DISPATCHED);

        $this->mockIssueDeliveryRepository
            ->shouldReceive('find')
            ->once()
            ->with($issueDelivery->id)
            ->andReturn($issueDelivery);

        $this->mockResolver->shouldNotReceive('resolve');
        $this->mockSendService->shouldNotReceive('sendNewsletter');

        $job = GenerateIssueDeliveriesJob::for($issueDelivery->id);
        $job->__wakeup();
        $job->handle();
        $this->assertTrue(true);
    }

    public function test_marks_failed_and_fires_event_when_eligibility_resolution_throws(): void
    {
        $issueDelivery = $this->makeIssueDelivery(IssueDeliveryStatus::ACTIVE);
        $errorMessage = 'subscription plan has no associated newsletter';

        $this->mockIssueDeliveryRepository
            ->shouldReceive('find')
            ->once()
            ->with($issueDelivery->id)
            ->andReturn($issueDelivery);

        $this->mockResolver
            ->shouldReceive('getEligibleSubscriptions')
            ->once()
            ->with($issueDelivery)
            ->andThrow(new DomainException($errorMessage));

        $issueDelivery
            ->shouldReceive('markDispatchFailed')
            ->once()
            ->with($errorMessage);

        $job = GenerateIssueDeliveriesJob::for($issueDelivery->id);
        $job->__wakeup();
        $result = $job->handle();

        $this->assertSame([], $result);
    }

    public function test_skips_cancelled_delivery(): void
    {
        $issueDelivery = $this->makeIssueDelivery(IssueDeliveryStatus::CANCELLED);

        $this->mockIssueDeliveryRepository
            ->shouldReceive('find')
            ->once()
            ->with($issueDelivery->id)
            ->andReturn($issueDelivery);

        $this->mockResolver->shouldNotReceive('resolve');

        $job = GenerateIssueDeliveriesJob::for($issueDelivery->id);
        $job->__wakeup();
        $job->handle();
        $this->assertTrue(true);
    }

    public function test_marks_dispatched_after_successful_run(): void
    {
        $issueDelivery = $this->makeIssueDelivery(IssueDeliveryStatus::ACTIVE);
        $collection = new \App\Framework\Support\Collection([]);

        $this->mockIssueDeliveryRepository
            ->shouldReceive('find')
            ->once()
            ->with($issueDelivery->id)
            ->andReturn($issueDelivery);

        $this->mockResolver
            ->shouldReceive('getEligibleSubscriptions')
            ->once()
            ->andReturn($collection);

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn(callable $cb) => $cb());

        $issueDelivery
            ->shouldReceive('markDispatched')
            ->once();

        $job = GenerateIssueDeliveriesJob::for($issueDelivery->id);
        $job->__wakeup();
        $result = $job->handle();

        $this->assertEquals(0, $result['created']);
        $this->assertEquals(0, $result['skipped']);
    }

    // =========================================================================
    // Newsletter resolution failure
    // =========================================================================

    public function test_marks_failed_and_fires_event_when_newsletter_cannot_be_resolved(): void
    {
        $issueDelivery = $this->makeIssueDelivery(IssueDeliveryStatus::ACTIVE);

        $this->mockIssueDeliveryRepository
            ->shouldReceive('find')
            ->once()
            ->with($issueDelivery->id)
            ->andReturn($issueDelivery);

        $this->mockResolver->shouldReceive('getEligibleSubscriptions')
            ->once()
            ->with($issueDelivery)
            ->andThrow(new DomainException('subscription plan has no associated newsletter'));

        $issueDelivery->shouldReceive('markDispatchFailed')
            ->once()
            ->with('subscription plan has no associated newsletter');

        $this->mockSendService->shouldNotReceive('sendNewsletter');

        $job = GenerateIssueDeliveriesJob::for($issueDelivery->id);
        $job->__wakeup();
        $job->handle();
        $this->assertTrue(true);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeNewsletter(): Newsletter
    {
        $newsletter = Mockery::mock(Newsletter::class)->makePartial();
        $newsletter->id = 10;
        $newsletter->shouldReceive('isAutomated')->andReturn(false);
        return $newsletter;
    }

    private function makeIssueDelivery(IssueDeliveryStatus $status): IssueDelivery
    {
        $delivery = Mockery::mock(IssueDelivery::class)->makePartial();
        $delivery->id = 1;
        $delivery->site_id = 1;
        $delivery->status = $status->value;
        $delivery->subscription_plan_id = 1;

        $delivery->shouldReceive('isActive')
            ->zeroOrMoreTimes()
            ->andReturn($status === IssueDeliveryStatus::ACTIVE);

        $delivery->shouldReceive('update')
            ->zeroOrMoreTimes()
            ->andReturn(true);

        $delivery->shouldReceive('markDispatched')
            ->zeroOrMoreTimes()
            ->andReturnNull();

        $delivery->shouldReceive('markDispatchFailed')
            ->zeroOrMoreTimes()
            ->andReturnNull();

        return $delivery;
    }
}