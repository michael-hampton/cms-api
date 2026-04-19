<?php

namespace App\Tests\Unit\Services\Members\Segmentation;

use App\Models\MemberSegment;
use App\Repositories\MemberInsights\MemberSegmentRepository;
use App\Repositories\MemberInsights\SegmentRepository;
use App\Services\MemberInsights\Segmentation\SegmentPersister;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class SegmentPersisterTest extends TestCase
{
    private SegmentRepository|MockInterface $segmentRepository;
    private MemberSegmentRepository|MockInterface $memberSegmentRepository;
    private SegmentPersister $persister;

    public function test_does_nothing_when_segment_keys_empty(): void
    {
        $this->segmentRepository->shouldNotReceive('getActiveIdsByKeys');

        $this->persister->persist(memberId: 1, siteId: 1, segmentKeys: []);
        $this->addToAssertionCount(1);
    }

    public function test_creates_new_assignment_when_not_existing(): void
    {
        $this->mockSegmentLookup(['churning'], ['churning' => 5]);

        $this->memberSegmentRepository->shouldReceive('findForMemberSiteSegment')
            ->once()
            ->with(1, 10, 5)
            ->andReturnNull();

        $this->memberSegmentRepository->shouldReceive('createAssignment')
            ->once()
            ->with(1, 10, 5, Mockery::type(\DateTimeInterface::class));

        $this->persister->persist(memberId: 1, siteId: 10, segmentKeys: ['churning']);
        $this->addToAssertionCount(1);
    }

    private function mockSegmentLookup(array $segmentKeys, array $keyToId): void
    {
        $this->segmentRepository->shouldReceive('getActiveIdsByKeys')
            ->once()
            ->with($segmentKeys)
            ->andReturn(collect($keyToId));
    }

    public function test_updates_last_seen_at_when_assignment_already_exists(): void
    {
        $this->mockSegmentLookup(['churning'], ['churning' => 5]);

        $existing = Mockery::mock(MemberSegment::class)->makePartial();

        $this->memberSegmentRepository->shouldReceive('findForMemberSiteSegment')
            ->once()
            ->with(1, 10, 5)
            ->andReturn($existing);

        $this->memberSegmentRepository->shouldReceive('touchLastSeen')
            ->once()
            ->with($existing, Mockery::type(\DateTimeInterface::class));

        $this->persister->persist(memberId: 1, siteId: 10, segmentKeys: ['churning']);
        $this->addToAssertionCount(1);
    }

    public function test_silently_skips_unknown_segment_key(): void
    {
        // Segment 'unknown_key' is not in the DB — the pluck returns empty
        $this->mockSegmentLookup(['unknown_key'], []);  // no matches

        $this->memberSegmentRepository->shouldNotReceive('createAssignment');

        $this->persister->persist(memberId: 1, siteId: 10, segmentKeys: ['unknown_key']);
        $this->addToAssertionCount(1);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();
        $this->segmentRepository = Mockery::mock(SegmentRepository::class);
        $this->memberSegmentRepository = Mockery::mock(MemberSegmentRepository::class);
        $this->persister = new SegmentPersister($this->segmentRepository, $this->memberSegmentRepository);
    }
}
