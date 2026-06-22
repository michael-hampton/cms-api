<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Models\IssueDelivery;
use App\Models\Subscription;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\SubscriptionIssueFulfilmentRepository;
use App\Repositories\Subscriptions\PlanIssueScheduleRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\SubscriptionDeliveryService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery as m;

class SubscriptionDeliveryServiceTest extends FunctionalTestCase
{
    private $subscriptionRepository;
    private $issueDeliveryRepository;
    private $subscriptionIssueFulfilmentRepository;
    private $planIssueScheduleRepository;
    private $databaseMock;
    private SubscriptionDeliveryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $_ENV['APP_ENV'] = 'testing';

        $this->subscriptionRepository = m::mock(SubscriptionRepository::class);
        $this->issueDeliveryRepository = m::mock(IssueDeliveryRepository::class);
        $this->subscriptionIssueFulfilmentRepository = m::mock(SubscriptionIssueFulfilmentRepository::class);
        $this->planIssueScheduleRepository = m::mock(PlanIssueScheduleRepository::class);
        $this->databaseMock = m::mock(Database::class);

        $this->service = new SubscriptionDeliveryService(
            $this->subscriptionRepository,
            $this->issueDeliveryRepository,
            $this->subscriptionIssueFulfilmentRepository,
            $this->planIssueScheduleRepository,
            $this->databaseMock
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function test_pause_delivery_throws_when_subscription_not_found(): void
    {
        $this->expectTransaction();
        $this->subscriptionRepository->shouldReceive('find')->with(999)->once()->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Subscription not found');

        $this->service->pauseDelivery(999, new \DateTime('+1 day'), new \DateTime('+7 days'));
    }

    public function test_pause_delivery_throws_when_subscription_cannot_be_paused(): void
    {
        $subscription = m::mock(Subscription::class);
        $subscription->shouldReceive('canPauseDelivery')->once()->andReturn(false);

        $this->expectTransaction();
        $this->subscriptionRepository->shouldReceive('find')->with(1)->once()->andReturn($subscription);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('This subscription cannot be paused');

        $this->service->pauseDelivery(1, new \DateTime('+1 day'), new \DateTime('+7 days'));
    }

    public function test_pause_delivery_rejects_invalid_date_range(): void
    {
        $subscription = m::mock(Subscription::class);
        $subscription->shouldReceive('canPauseDelivery')->once()->andReturn(true);

        $this->expectTransaction();
        $this->subscriptionRepository->shouldReceive('find')->with(1)->once()->andReturn($subscription);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('End date must be after start date');

        $this->service->pauseDelivery(1, new \DateTime('+7 days'), new \DateTime('+1 day'));
    }

    public function test_pause_delivery_rejects_past_start_date(): void
    {
        $subscription = m::mock(Subscription::class);
        $subscription->shouldReceive('canPauseDelivery')->once()->andReturn(true);

        $this->expectTransaction();
        $this->subscriptionRepository->shouldReceive('find')->with(1)->once()->andReturn($subscription);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Start date cannot be in the past');

        $this->service->pauseDelivery(1, new \DateTime('-5 days'), new \DateTime('+7 days'));
    }

    public function test_pause_delivery_rejects_period_longer_than_ninety_days(): void
    {
        $subscription = m::mock(Subscription::class);
        $subscription->shouldReceive('canPauseDelivery')->once()->andReturn(true);

        $this->expectTransaction();
        $this->subscriptionRepository->shouldReceive('find')->with(1)->once()->andReturn($subscription);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Pause period cannot exceed 90 days');

        $this->service->pauseDelivery(1, new \DateTime('+1 day'), new \DateTime('+100 days'));
    }

    public function test_pause_delivery_throws_when_subscription_update_fails(): void
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('canPauseDelivery')->once()->andReturn(true);

        $this->expectTransaction();
        $this->subscriptionRepository->shouldReceive('find')->with(1)->once()->andReturn($subscription);
        $this->subscriptionRepository->shouldReceive('update')->with(1, m::type('array'))->once()->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to pause delivery');

        $this->service->pauseDelivery(1, new \DateTime('+1 day'), new \DateTime('+7 days'));
    }

    public function test_pause_delivery_creates_and_defers_issues_in_delivery_window(): void
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->plan_id = 10;
        $subscription->shouldReceive('canPauseDelivery')->once()->andReturn(true);

        $pauseStart = new \DateTime('+5 days');
        $pauseEnd = new \DateTime('+15 days');

        $issue = m::mock(IssueDelivery::class)->makePartial();
        $issue->id = 11;
        $issue->estimated_delivery_date = new \DateTime('+10 days');

        $this->expectTransaction();
        $this->subscriptionRepository->shouldReceive('find')->with(1)->once()->andReturn($subscription);
        $this->subscriptionRepository->shouldReceive('update')->once()->andReturn($subscription);
        $this->planIssueScheduleRepository
            ->shouldReceive('findWithinDeliveryWindow')
            ->with(10, $pauseStart, $pauseEnd)
            ->once()
            ->andReturn(collect([$issue]));
        $this->subscriptionIssueFulfilmentRepository
            ->shouldReceive('createForSubscription')
            ->with(1, 11, m::type(\DateTimeInterface::class))
            ->once();
        $this->subscriptionIssueFulfilmentRepository
            ->shouldReceive('deferForSubscriptionAndIssues')
            ->with(1, [11], m::on(function ($date) use ($pauseEnd) {
                return $date instanceof \DateTimeInterface
                    && $date->format('Y-m-d') === (clone $pauseEnd)->modify('+1 day')->format('Y-m-d');
            }))
            ->once()
            ->andReturn(1);

        $result = $this->service->pauseDelivery(1, $pauseStart, $pauseEnd, 'Holiday');

        $this->assertTrue($result['success']);
        $this->assertEquals(10, $result['paused_days']);
    }

    public function test_pause_delivery_only_creates_fulfilments_for_selected_subscription(): void
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->plan_id = 10;
        $subscription->shouldReceive('canPauseDelivery')->once()->andReturn(true);

        $pauseStart = new \DateTime('+1 day');
        $pauseEnd = new \DateTime('+7 days');
        $issue = m::mock(IssueDelivery::class)->makePartial();
        $issue->id = 11;
        $issue->estimated_delivery_date = new \DateTime('+3 days');

        $this->expectTransaction();
        $this->subscriptionRepository->shouldReceive('find')->with(25)->once()->andReturn($subscription);
        $this->subscriptionRepository->shouldReceive('update')->with(25, m::type('array'))->once()->andReturn($subscription);
        $this->planIssueScheduleRepository->shouldReceive('findWithinDeliveryWindow')
            ->with(10, $pauseStart, $pauseEnd)->once()->andReturn(collect([$issue]));
        $this->subscriptionIssueFulfilmentRepository->shouldReceive('createForSubscription')
            ->with(25, 11, m::type(\DateTimeInterface::class))->once();
        $this->subscriptionIssueFulfilmentRepository->shouldReceive('deferForSubscriptionAndIssues')
            ->with(25, [11], m::type(\DateTimeInterface::class))->once()->andReturn(1);

        $result = $this->service->pauseDelivery(25, $pauseStart, $pauseEnd);

        $this->assertTrue($result['success']);
    }

    public function test_resume_delivery_throws_when_subscription_not_found(): void
    {
        $this->expectTransaction();
        $this->subscriptionRepository->shouldReceive('find')->with(999)->once()->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Subscription not found');

        $this->service->resumeDelivery(999);
    }

    public function test_resume_delivery_throws_when_not_paused(): void
    {
        $subscription = m::mock(Subscription::class);
        $subscription->shouldReceive('canResumeDelivery')->once()->andReturn(false);

        $this->expectTransaction();
        $this->subscriptionRepository->shouldReceive('find')->with(1)->once()->andReturn($subscription);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('This subscription is not paused');

        $this->service->resumeDelivery(1);
    }

    public function test_resume_delivery_throws_when_subscription_update_fails(): void
    {
        $subscription = m::mock(Subscription::class);
        $subscription->shouldReceive('canResumeDelivery')->once()->andReturn(true);

        $this->expectTransaction();
        $this->subscriptionRepository->shouldReceive('find')->with(1)->once()->andReturn($subscription);
        $this->subscriptionRepository->shouldReceive('update')->with(1, m::type('array'))->once()->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to resume delivery');

        $this->service->resumeDelivery(1);
    }

    public function test_resume_delivery_releases_deferred_fulfilments(): void
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->member_id = 42;
        $subscription->shouldReceive('canResumeDelivery')->once()->andReturn(true);

        $this->expectTransaction();
        $this->subscriptionRepository->shouldReceive('find')->with(1)->once()->andReturn($subscription);
        $this->subscriptionRepository->shouldReceive('update')->with(1, [
            'delivery_paused' => false,
            'delivery_pause_start' => null,
            'delivery_pause_end' => null,
            'delivery_pause_reason' => null,
        ])->once()->andReturn($subscription);
        $this->subscriptionIssueFulfilmentRepository
            ->shouldReceive('releaseDeferredForSubscription')
            ->with(1)
            ->once()
            ->andReturn(2);

        $result = $this->service->resumeDelivery(1);

        $this->assertTrue($result['success']);
        $this->assertEquals('Delivery resumed successfully', $result['message']);
    }

    public function test_get_pause_status_returns_error_when_subscription_not_found(): void
    {
        $this->subscriptionRepository->shouldReceive('find')->with(999)->once()->andReturn(null);

        $result = $this->service->getPauseStatus(999);

        $this->assertFalse($result['success']);
        $this->assertEquals('Subscription not found', $result['message']);
    }

    public function test_get_pause_status_returns_subscription_state(): void
    {
        $subscription = m::mock(Subscription::class)->makePartial();
        $subscription->delivery_pause_start = new \DateTime('+1 day');
        $subscription->delivery_pause_end = new \DateTime('+6 days');
        $subscription->delivery_pause_reason = 'Holiday';
        $subscription->shouldReceive('isDeliveryPaused')->once()->andReturn(true);
        $subscription->shouldReceive('canPauseDelivery')->once()->andReturn(false);
        $subscription->shouldReceive('canResumeDelivery')->once()->andReturn(true);
        $subscription->shouldReceive('getDaysUntilPauseEnds')->once()->andReturn(6);

        $this->subscriptionRepository->shouldReceive('find')->with(1)->once()->andReturn($subscription);

        $result = $this->service->getPauseStatus(1);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['is_paused']);
        $this->assertFalse($result['can_pause']);
        $this->assertTrue($result['can_resume']);
        $this->assertEquals(6, $result['days_until_resume']);
        $this->assertEquals('Holiday', $result['reason']);
    }

    public function test_set_start_issue_updates_subscription(): void
    {
        $issue = m::mock(IssueDelivery::class)->makePartial();
        $issue->publication_date = date('Y-m-d', strtotime('+1 month'));

        $this->expectTransaction();
        $this->issueDeliveryRepository->shouldReceive('find')->with(5)->once()->andReturn($issue);
        $this->subscriptionRepository->shouldReceive('update')->with(1, [
            'start_issue_id' => 5,
            'start_date' => $issue->publication_date,
        ])->once();

        $this->service->setStartIssue(1, 5);
        $this->assertTrue(true);
    }

    public function test_set_start_issue_throws_when_issue_not_found(): void
    {
        $this->expectTransaction();
        $this->issueDeliveryRepository->shouldReceive('find')->with(5)->once()->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Issue not found');

        $this->service->setStartIssue(1, 5);
    }

    public function test_set_start_issue_throws_for_past_issue(): void
    {
        $issue = m::mock(IssueDelivery::class)->makePartial();
        $issue->publication_date = date('Y-m-d', strtotime('-1 month'));

        $this->expectTransaction();
        $this->issueDeliveryRepository->shouldReceive('find')->with(5)->once()->andReturn($issue);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot start subscription with past issue');

        $this->service->setStartIssue(1, 5);
    }

    private function expectTransaction(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });
    }
}
