<?php

namespace App\Tests\Unit\Services\MemberInsights\Segmentation;

use App\Framework\Database\Database;
use App\Models\Segment;
use App\Models\Subscription;
use App\Models\SubscriptionSegment;
use App\Repositories\MemberInsights\SegmentRepository;
use App\Repositories\MemberInsights\SubscriptionSegmentRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\MemberInsights\Segmentation\ManualSegmentOverrideService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class ManualSegmentOverrideServiceTest extends TestCase
{
    private SubscriptionRepository|MockInterface        $subscriptionRepository;
    private SegmentRepository|MockInterface             $segmentRepository;
    private SubscriptionSegmentRepository|MockInterface $subscriptionSegmentRepository;
    private Database|MockInterface                      $database;
    private ManualSegmentOverrideService                $service;

    // =========================================================================
    // Happy path
    // =========================================================================

    public function test_it_creates_manual_override_replacing_existing_assignment(): void
    {
        $sub        = $this->makeSub(1);
        $segment    = $this->makeSeg(10);
        $assignment = Mockery::mock(SubscriptionSegment::class)->makePartial();

        $this->subscriptionRepository->allows('find')->with(1)->andReturn($sub);
        $this->segmentRepository->allows('findWithRules')->with(10)->andReturn($segment);
        $this->database->allows('transaction')->andReturnUsing(fn(callable $cb) => $cb());

        $this->subscriptionSegmentRepository->expects('replaceActive')
            ->once()->with(1);

        $this->subscriptionSegmentRepository->expects('createManual')
            ->once()
            ->with(
                1,
                10,
                Mockery::any(), // Safely captures the App\Framework\Date instance positionally
                'Manual retention override',
                99,
                null,
            )
            ->andReturn($assignment);

        $result = $this->service->override(
            subscriptionId:   1,
            segmentId:        10,
            reason:           'Manual retention override',
            assignedByUserId: 99,
        );

        $this->assertSame($assignment, $result);
    }

    public function test_it_stores_expiry_date_when_provided(): void
    {
        $sub        = $this->makeSub(1);
        $segment    = $this->makeSeg(10);
        $assignment = Mockery::mock(SubscriptionSegment::class)->makePartial();

        $this->subscriptionRepository->allows('find')->andReturn($sub);
        $this->segmentRepository->allows('findWithRules')->andReturn($segment);
        $this->database->allows('transaction')->andReturnUsing(fn(callable $cb) => $cb());
        $this->subscriptionSegmentRepository->allows('replaceActive');
        $this->subscriptionSegmentRepository->expects('createManual')
            ->once()
            ->with(
                1,
                10,
                Mockery::any(),
                'Retention offer',
                null,
                Mockery::type(\DateTimeImmutable::class),
            )
            ->andReturn($assignment);

        $this->service->override(
            subscriptionId:   1,
            segmentId:        10,
            reason:           'Retention offer',
            assignedByUserId: null,
            expiresAt:        '2099-12-31',
        );

        $this->addToAssertionCount(1);
    }

    // =========================================================================
    // Transaction wrapping
    // =========================================================================

    public function test_it_wraps_override_in_a_transaction(): void
    {
        $sub     = $this->makeSub(1);
        $segment = $this->makeSeg(10);
        $assign  = Mockery::mock(SubscriptionSegment::class)->makePartial();

        $this->subscriptionRepository->allows('find')->andReturn($sub);
        $this->segmentRepository->allows('findWithRules')->andReturn($segment);

        $this->database->expects('transaction')->once()->andReturnUsing(fn(callable $cb) => $cb());

        $this->subscriptionSegmentRepository->allows('replaceActive');
        $this->subscriptionSegmentRepository->allows('createManual')->andReturn($assign);

        $this->service->override(1, 10, 'Test reason', null);

        $this->addToAssertionCount(1);
    }

    // =========================================================================
    // Validation — reason required
    // =========================================================================

    public function test_it_throws_when_reason_is_empty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/reason is required/');

        $this->subscriptionRepository->allows('find')->andReturn($this->makeSub(1));
        $this->segmentRepository->allows('findWithRules')->andReturn($this->makeSeg(10));

        $this->service->override(1, 10, '   ', null);
    }

    public function test_it_throws_when_reason_is_empty_string(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->subscriptionRepository->allows('find')->andReturn($this->makeSub(1));
        $this->segmentRepository->allows('findWithRules')->andReturn($this->makeSeg(10));

        $this->service->override(1, 10, '', null);
    }

    // =========================================================================
    // Validation — expires_at must be future
    // =========================================================================

    public function test_it_throws_when_expires_at_is_in_the_past(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/future date/');

        $this->subscriptionRepository->allows('find')->andReturn($this->makeSub(1));
        $this->segmentRepository->allows('findWithRules')->andReturn($this->makeSeg(10));

        $this->service->override(1, 10, 'Valid reason', null, '2020-01-01');
    }

    public function test_it_throws_when_expires_at_is_invalid_date(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not a valid date/');

        $this->subscriptionRepository->allows('find')->andReturn($this->makeSub(1));
        $this->segmentRepository->allows('findWithRules')->andReturn($this->makeSeg(10));

        $this->service->override(1, 10, 'Valid reason', null, 'not-a-date');
    }

    // =========================================================================
    // Not found
    // =========================================================================

    public function test_it_throws_when_subscription_not_found(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Subscription #99 not found/');

        $this->subscriptionRepository->allows('find')->with(99)->andReturnNull();

        $this->service->override(99, 10, 'Valid reason', null);
    }

    public function test_it_throws_when_segment_not_found(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Segment #99 not found/');

        $this->subscriptionRepository->allows('find')->andReturn($this->makeSub(1));
        $this->segmentRepository->allows('findWithRules')->with(99)->andReturnNull();

        $this->service->override(1, 99, 'Valid reason', null);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeSub(int $id): Subscription
    {
        $sub     = Mockery::mock(Subscription::class)->makePartial();
        $sub->id = $id;

        return $sub;
    }

    private function makeSeg(int $id): Segment
    {
        $seg     = Mockery::mock(Segment::class)->makePartial();
        $seg->id = $id;

        return $seg;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository        = Mockery::mock(SubscriptionRepository::class);
        $this->segmentRepository             = Mockery::mock(SegmentRepository::class);
        $this->subscriptionSegmentRepository = Mockery::mock(SubscriptionSegmentRepository::class);
        $this->database                      = Mockery::mock(Database::class);

        $this->service = new ManualSegmentOverrideService(
            $this->subscriptionRepository,
            $this->segmentRepository,
            $this->subscriptionSegmentRepository,
            $this->database,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}