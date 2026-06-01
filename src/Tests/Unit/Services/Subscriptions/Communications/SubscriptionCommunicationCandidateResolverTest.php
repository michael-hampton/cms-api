<?php

namespace App\Tests\Unit\Services\Subscriptions\Communications;

use App\Models\Segment;
use App\Models\Subscription;
use App\Models\SubscriptionCommunication;
use App\Models\SubscriptionCommunicationSchedule;
use App\Models\SubscriptionSegment;
use App\Repositories\MemberInsights\SubscriptionSegmentRepository;
use App\Repositories\Subscriptions\SubscriptionCommunicationDeliveryRepository;
use App\Repositories\Subscriptions\SubscriptionCommunicationRepository;
use App\Services\Subscriptions\Communications\SubscriptionCommunicationCandidateResolver;
use App\Services\Subscriptions\Communications\SubscriptionCommunicationDueResolver;
use DateTimeImmutable;
use Mockery;
use PHPUnit\Framework\TestCase;

class SubscriptionCommunicationCandidateResolverTest extends TestCase
{
    private SubscriptionCommunicationRepository $communicationRepository;
    private SubscriptionCommunicationDeliveryRepository $deliveryRepository;
    private SubscriptionSegmentRepository $segmentRepository;
    private SubscriptionCommunicationDueResolver $dueResolver;
    private SubscriptionCommunicationCandidateResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->communicationRepository = Mockery::mock(SubscriptionCommunicationRepository::class);
        $this->deliveryRepository = Mockery::mock(SubscriptionCommunicationDeliveryRepository::class);
        $this->segmentRepository = Mockery::mock(SubscriptionSegmentRepository::class);
        $this->dueResolver = Mockery::mock(SubscriptionCommunicationDueResolver::class);

        $this->resolver = new SubscriptionCommunicationCandidateResolver(
            $this->communicationRepository,
            $this->deliveryRepository,
            $this->segmentRepository,
            $this->dueResolver,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_returns_due_communication_for_segment(): void
    {
        $subscription = $this->makeSubscription(1);
        $segment = $this->makeSegment(10);
        $assignment = $this->makeAssignment($segment);
        $schedule = $this->makeSchedule(1, isActive: true);
        $communication = $this->makeCommunication(1, schedules: collect([$schedule]));
        $date = new DateTimeImmutable('2026-06-01');

        $this->segmentRepository->shouldReceive('findActive')
            ->with(1)
            ->andReturn($assignment);

        $this->communicationRepository->shouldReceive('findActiveForSegment')
            ->with(10)
            ->andReturn(collect([$communication]));

        $this->dueResolver->shouldReceive('isDue')
            ->with($subscription, $schedule, $date)
            ->andReturn(true);

        $this->deliveryRepository->shouldReceive('hasAlreadySent')
            ->with(1, 1, 1)
            ->andReturn(false);

        $candidates = $this->resolver->dueForSubscription($subscription, $date);

        $this->assertCount(1, $candidates);
        $this->assertSame($communication, $candidates[0]['communication']);
        $this->assertSame($schedule, $candidates[0]['schedule']);
        $this->assertSame($segment, $candidates[0]['segment']);
    }

    public function test_returns_global_communication_when_no_segment(): void
    {
        $subscription = $this->makeSubscription(2);
        $schedule = $this->makeSchedule(1, isActive: true);
        $communication = $this->makeCommunication(1, schedules: collect([$schedule]));
        $date = new DateTimeImmutable('today');

        $this->segmentRepository->shouldReceive('findActive')
            ->with(2)
            ->andReturn(null);

        $this->communicationRepository->shouldReceive('findActiveForSegment')
            ->with(null)
            ->andReturn(collect([$communication]));

        $this->dueResolver->shouldReceive('isDue')
            ->andReturn(true);

        $this->deliveryRepository->shouldReceive('hasAlreadySent')
            ->andReturn(false);

        $candidates = $this->resolver->dueForSubscription($subscription, $date);

        $this->assertCount(1, $candidates);
        $this->assertNull($candidates[0]['segment']);
    }

    public function test_skips_already_sent_communication(): void
    {
        $subscription = $this->makeSubscription(1);
        $schedule = $this->makeSchedule(1, isActive: true);
        $communication = $this->makeCommunication(1, schedules: collect([$schedule]));
        $date = new DateTimeImmutable('today');

        $this->segmentRepository->shouldReceive('findActive')->andReturn(null);

        $this->communicationRepository->shouldReceive('findActiveForSegment')
            ->andReturn(collect([$communication]));

        $this->dueResolver->shouldReceive('isDue')
            ->andReturn(true);

        $this->deliveryRepository->shouldReceive('hasAlreadySent')
            ->with(1, 1, 1)
            ->andReturn(true);

        $candidates = $this->resolver->dueForSubscription($subscription, $date);

        $this->assertEmpty($candidates);
    }

    public function test_skips_inactive_schedules(): void
    {
        $subscription = $this->makeSubscription(1);
        $schedule = $this->makeSchedule(1, isActive: false);
        $communication = $this->makeCommunication(1, schedules: collect([$schedule]));
        $date = new DateTimeImmutable('today');

        $this->segmentRepository->shouldReceive('findActive')->andReturn(null);

        $this->communicationRepository->shouldReceive('findActiveForSegment')
            ->andReturn(collect([$communication]));

        $this->dueResolver->shouldReceive('isDue')->never();
        $this->deliveryRepository->shouldReceive('hasAlreadySent')->never();

        $candidates = $this->resolver->dueForSubscription($subscription, $date);

        $this->assertEmpty($candidates);
    }

    public function test_skips_schedule_not_due_today(): void
    {
        $subscription = $this->makeSubscription(1);
        $schedule = $this->makeSchedule(1, isActive: true);
        $communication = $this->makeCommunication(1, schedules: collect([$schedule]));
        $date = new DateTimeImmutable('today');

        $this->segmentRepository->shouldReceive('findActive')->andReturn(null);

        $this->communicationRepository->shouldReceive('findActiveForSegment')
            ->andReturn(collect([$communication]));

        $this->dueResolver->shouldReceive('isDue')
            ->andReturn(false);

        $this->deliveryRepository->shouldReceive('hasAlreadySent')->never();

        $candidates = $this->resolver->dueForSubscription($subscription, $date);

        $this->assertEmpty($candidates);
    }

    public function test_returns_multiple_due_candidates(): void
    {
        $subscription = $this->makeSubscription(1);
        $schedule1 = $this->makeSchedule(1, isActive: true);
        $schedule2 = $this->makeSchedule(2, isActive: true);
        $comm1 = $this->makeCommunication(1, schedules: collect([$schedule1]));
        $comm2 = $this->makeCommunication(2, schedules: collect([$schedule2]));
        $date = new DateTimeImmutable('today');

        $this->segmentRepository->shouldReceive('findActive')->andReturn(null);

        $this->communicationRepository->shouldReceive('findActiveForSegment')
            ->andReturn(collect([$comm1, $comm2]));

        $this->dueResolver->shouldReceive('isDue')
            ->andReturn(true);

        $this->deliveryRepository->shouldReceive('hasAlreadySent')
            ->andReturn(false);

        $candidates = $this->resolver->dueForSubscription($subscription, $date);

        $this->assertCount(2, $candidates);
    }

    private function makeSubscription(int $id): Subscription
    {
        $sub = Mockery::mock(Subscription::class)->makePartial();
        $sub->id = $id;

        return $sub;
    }

    private function makeSegment(int $id): Segment
    {
        $segment = Mockery::mock(Segment::class)->makePartial();
        $segment->id = $id;

        return $segment;
    }

    private function makeAssignment(Segment $segment): SubscriptionSegment
    {
        $assignment = Mockery::mock(SubscriptionSegment::class)->makePartial();
        $assignment->segment = $segment;

        return $assignment;
    }

    private function makeSchedule(int $id, bool $isActive): SubscriptionCommunicationSchedule
    {
        $schedule = Mockery::mock(SubscriptionCommunicationSchedule::class)->makePartial();
        $schedule->id = $id;
        $schedule->is_active = $isActive;

        return $schedule;
    }

    private function makeCommunication(int $id, $schedules): SubscriptionCommunication
    {
        $comm = Mockery::mock(SubscriptionCommunication::class)->makePartial();
        $comm->id = $id;
        $comm->schedules = $schedules;

        return $comm;
    }
}