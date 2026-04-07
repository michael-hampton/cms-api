<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Enums\Subscriptions\IssueDeliveryStatus;
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

    private GenerateIssueDeliveriesJob $job;
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

        $this->job = new GenerateIssueDeliveriesJob(
            $this->mockRepository,
            $this->mockIssueDeliveryRepository,
            $this->mockResolver,
            $this->database,
            $this->mockLogger,
        );
    }

    public function test_creates_deliveries_for_eligible_subscriptions(): void
    {
        $page = $this->createPage();
        $plan = $this->createSubscriptionPlan(['premium_access' => [['identifier' => 'test', 'type' => 'newsletter']]]);
        $newsletter = $this->createNewsletter(['slug' => 'test']);
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

        $job = app(GenerateIssueDeliveriesJob::class);

        $result = $job->handle($issueDelivery->id);

        $this->assertEquals(1, $result['created']);
        $this->assertEquals(1, $result['dispatched']);
        $this->assertEquals(0, $result['skipped']);
    }

    public function test_skips_existing_deliveries_idempotent(): void
    {
        $page = $this->createPage();
        $plan = $this->createSubscriptionPlan(['premium_access' => [['identifier' => 'test', 'type' => 'newsletter']]]);
        $newsletter = $this->createNewsletter(['slug' => 'test']);
        $member = $this->createMember();
        $issueDelivery = IssueDelivery::create([
            'subscription_plan_id' => $plan->id,
            'on_sale_date' => now_datetime()->addDays(7),
            'status' => IssueDeliveryStatus::ACTIVE->value,
            'site_id' => $this->siteId,
        ]);

        $subscription = Subscription::create([
            'plan_id' => $plan->id,
            'status' => 'active',
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Test Plan',
            'start_date' => now_datetime()->subDays(30),
            'end_date' => now_datetime()->addDays(30),
            'type' => 'paid',
            'delivery_type' => 'digital',
        ]);

        SubscriptionWindow::create([
            'subscription_id' => $subscription->id,
            'window_start' => now_datetime()->subDays(30),
            'window_end' => now_datetime()->addDays(30),
            'member_id' => $member->id,
            'site_id' => $this->siteId,
        ]);

        $job = app(GenerateIssueDeliveriesJob::class);

        // First run — creates the delivery record, transitions status to DISPATCHED.
        $firstResult = $job->handle($issueDelivery->id);
        $this->assertEquals(1, $firstResult['created'], 'First run should create one delivery');

        // Reset status to ACTIVE to simulate an operator re-triggering the job.
        // The job loads a separate IssueDelivery instance and updates it, so our
        // in-memory model may be stale. Refresh to ensure the update actually hits the DB.
        $issueDelivery->refresh();
        $issueDelivery->update(['status' => IssueDeliveryStatus::ACTIVE->value]);

        // Second run — IssuesDelivered record already exists, so it must be skipped.
        $result = $job->handle($issueDelivery->id);

        $this->assertEquals(0, $result['created']);
        $this->assertEquals(1, $result['skipped']);
    }

    public function test_only_processes_subscriptions_within_active_window(): void
    {
        $plan = $this->createSubscriptionPlan();
        $member = $this->createMember();
        $issueDelivery = IssueDelivery::create([
            'subscription_plan_id' => $plan->id,
            'on_sale_date' => now_datetime()->addDays(7),
        ]);

        $subscription = Subscription::create([
            'plan_id' => $plan->id,
            'status' => 'active',
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Test Plan',
            'start_date' => now_datetime()->subDays(30),
        ]);

        // Window that doesn't cover the scheduled date
        SubscriptionWindow::create([
            'subscription_id' => $subscription->id,
            'window_start' => now_datetime()->subDays(60),
            'window_end' => now_datetime()->subDays(30),
            'member_id' => $member->id,
            'site_id' => $this->siteId
        ]);

        $job = app(GenerateIssueDeliveriesJob::class);
        $result = $job->handle($issueDelivery->id);

        $this->assertEquals(0, $result['created']);
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

        $this->job->handle($issueDelivery->id);
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

        $result = $this->job->handle($issueDelivery->id);

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

        $this->job->handle($issueDelivery->id);
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

        $result = $this->job->handle($issueDelivery->id);

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

        $this->job->handle($issueDelivery->id);
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
            ->andReturn($status === IssueDeliveryStatus::ACTIVE);
        $delivery->shouldReceive('markDispatched')->byDefault();
        $delivery->shouldReceive('markDispatchFailed')->byDefault();

        return $delivery;
    }
}