<?php

namespace App\Tests\Unit\Jobs;

use App\Jobs\Subscriptions\ProcessSubscriptionCommunicationsJob;
use App\Models\Subscription;
use App\Models\SubscriptionCommunication;
use App\Models\SubscriptionCommunicationSchedule;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\Communications\SubscriptionCommunicationCandidateResolver;
use App\Services\Subscriptions\Communications\SubscriptionCommunicationSender;
use DateTimeImmutable;
use Mockery;
use PHPUnit\Framework\TestCase;

class ProcessSubscriptionCommunicationsJobTest extends TestCase
{
    private SubscriptionRepository                    $subscriptionRepository;
    private SubscriptionCommunicationCandidateResolver $candidateResolver;
    private SubscriptionCommunicationSender           $sender;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $this->candidateResolver      = Mockery::mock(SubscriptionCommunicationCandidateResolver::class);
        $this->sender                 = Mockery::mock(SubscriptionCommunicationSender::class);
    }

    private function makeJob(int $subscriptionId, ?string $date = null): ProcessSubscriptionCommunicationsJob
    {
        $job = new ProcessSubscriptionCommunicationsJob($subscriptionId, $date);
        $job->subscriptionRepository = $this->subscriptionRepository;
        $job->candidateResolver      = $this->candidateResolver;
        $job->sender                 = $this->sender;
        return $job;
    }

    public function test_exits_safely_when_subscription_not_found(): void
    {
        $this->subscriptionRepository->shouldReceive('find')
            ->with(999)->andReturn(null);

        $this->candidateResolver->shouldReceive('dueForSubscription')->never();
        $this->sender->shouldReceive('send')->never();

        $this->makeJob(999)->handle();

        $this->assertTrue(true);
    }

    public function test_dispatches_sender_for_each_due_candidate(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;

        $comm     = Mockery::mock(SubscriptionCommunication::class)->makePartial();
        $schedule = Mockery::mock(SubscriptionCommunicationSchedule::class)->makePartial();

        $candidates = [
            ['communication' => $comm, 'schedule' => $schedule, 'segment' => null],
        ];

        $this->subscriptionRepository->shouldReceive('find')->with(1)->andReturn($subscription);
        $this->candidateResolver->shouldReceive('dueForSubscription')
            ->once()->andReturn($candidates);
        $this->sender->shouldReceive('send')
            ->once()->with($subscription, $comm, $schedule);

        $this->makeJob(1)->handle();

        $this->assertTrue(true);
    }

    public function test_does_nothing_when_no_candidates_are_due(): void
    {
        $subscription     = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;

        $this->subscriptionRepository->shouldReceive('find')->andReturn($subscription);
        $this->candidateResolver->shouldReceive('dueForSubscription')->andReturn([]);
        $this->sender->shouldReceive('send')->never();

        $this->makeJob(1)->handle();

        $this->assertTrue(true);
    }

    public function test_logs_failure_but_continues_processing_other_candidates(): void
    {
        $subscription     = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;

        $failingComm  = Mockery::mock(SubscriptionCommunication::class)->makePartial();
        $failingComm->id = 1;
        $succeedingComm = Mockery::mock(SubscriptionCommunication::class)->makePartial();
        $succeedingComm->id = 2;
        $schedule = Mockery::mock(SubscriptionCommunicationSchedule::class)->makePartial();

        $candidates = [
            ['communication' => $failingComm,   'schedule' => $schedule, 'segment' => null],
            ['communication' => $succeedingComm, 'schedule' => $schedule, 'segment' => null],
        ];

        $this->subscriptionRepository->shouldReceive('find')->andReturn($subscription);
        $this->candidateResolver->shouldReceive('dueForSubscription')->andReturn($candidates);

        $this->sender->shouldReceive('send')
            ->with($subscription, $failingComm, $schedule)
            ->andThrow(new \RuntimeException('Send failed'));

        $this->sender->shouldReceive('send')
            ->with($subscription, $succeedingComm, $schedule)
            ->once();

        $this->makeJob(1)->handle();

        $this->assertTrue(true);
    }

    public function test_parses_custom_date_when_provided(): void
    {
        $subscription     = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;

        $this->subscriptionRepository->shouldReceive('find')->andReturn($subscription);
        $expectedDate = new DateTimeImmutable('2026-07-15');

        $this->candidateResolver->shouldReceive('dueForSubscription')
            ->once()
            ->withArgs(function ($sub, $date) use ($expectedDate) {
                return $date == $expectedDate;
            })
            ->andReturn([]);

        $this->makeJob(1, '2026-07-15')->handle();

        $this->assertTrue(true);
    }
}