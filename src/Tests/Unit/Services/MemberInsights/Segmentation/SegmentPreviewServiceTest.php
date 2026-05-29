<?php

namespace App\Tests\Unit\Services\MemberInsights\Segmentation;

use App\Framework\Support\Collection;
use App\Models\Segment;
use App\Models\Subscription;
use App\Repositories\MemberInsights\SegmentPreviewRepository;
use App\Services\MemberInsights\Segmentation\SegmentPreviewService;
use App\Services\MemberInsights\Segmentation\SegmentRuleEngine;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class SegmentPreviewServiceTest extends TestCase
{
    private SegmentRuleEngine|MockInterface $ruleEngine;
    private SegmentPreviewRepository|MockInterface $previewRepository;
    private SegmentPreviewService $service;

    // =========================================================================
    // Count
    // =========================================================================

    public function test_it_returns_matching_count(): void
    {
        $segment = $this->makeSegment(1);
        $subs    = $this->makeSubscriptions([10, 11, 12]);

        $this->mockChunk($segment, [$subs]);
        // Only sub 10 and 12 match
        $this->ruleEngine->allows('matches')
            ->andReturnUsing(fn($sub) => in_array($sub->id, [10, 12]));

        $result = $this->service->preview($segment);

        $this->assertSame(2, $result['count']);
    }

    public function test_it_returns_zero_count_when_nothing_matches(): void
    {
        $segment = $this->makeSegment(1);
        $subs    = $this->makeSubscriptions([10, 11]);

        $this->mockChunk($segment, [$subs]);
        $this->ruleEngine->allows('matches')->andReturn(false);

        $result = $this->service->preview($segment);

        $this->assertSame(0, $result['count']);
    }

    // =========================================================================
    // Sample records
    // =========================================================================

    public function test_it_returns_sample_records(): void
    {
        $segment = $this->makeSegment(1);
        $subs    = $this->makeSubscriptions([10, 11, 12]);

        $this->mockChunk($segment, [$subs]);
        $this->ruleEngine->allows('matches')->andReturn(true);

        $result = $this->service->preview($segment, sampleSize: 2);

        $this->assertCount(2, $result['sample']);
    }

    public function test_it_does_not_exceed_sample_size(): void
    {
        $segment = $this->makeSegment(1);
        // 20 subscriptions, sample cap = 5
        $subs    = $this->makeSubscriptions(range(1, 20));

        $this->mockChunk($segment, [$subs]);
        $this->ruleEngine->allows('matches')->andReturn(true);

        $result = $this->service->preview($segment, sampleSize: 5);

        $this->assertCount(5, $result['sample']);
        // But count should still be 20
        $this->assertSame(20, $result['count']);
    }

    public function test_it_returns_empty_sample_when_nothing_matches(): void
    {
        $segment = $this->makeSegment(1);
        $subs    = $this->makeSubscriptions([10, 11]);

        $this->mockChunk($segment, [$subs]);
        $this->ruleEngine->allows('matches')->andReturn(false);

        $result = $this->service->preview($segment);

        $this->assertSame([], $result['sample']);
    }

    // =========================================================================
    // No assignments written
    // =========================================================================

    public function test_it_does_not_create_assignments(): void
    {
        // The service only calls ruleEngine and previewRepository — never an
        // assignment repository. We assert the two collaborators are the only
        // ones invoked by verifying no unexpected calls occur.
        $segment = $this->makeSegment(1);
        $subs    = $this->makeSubscriptions([10]);

        $this->mockChunk($segment, [$subs]);
        $this->ruleEngine->allows('matches')->andReturn(true);

        // No SubscriptionSegmentRepository injected → any call would error.
        $result = $this->service->preview($segment);

        $this->assertSame(1, $result['count']);
    }

    // =========================================================================
    // Multiple chunks
    // =========================================================================

    public function test_it_counts_across_multiple_chunks(): void
    {
        $segment = $this->makeSegment(1);
        $chunk1  = $this->makeSubscriptions([1, 2, 3]);
        $chunk2  = $this->makeSubscriptions([4, 5]);

        $this->mockChunk($segment, [$chunk1, $chunk2]);
        $this->ruleEngine->allows('matches')->andReturn(true);

        $result = $this->service->preview($segment);

        $this->assertSame(5, $result['count']);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeSegment(int $id): Segment
    {
        $segment     = Mockery::mock(Segment::class)->makePartial();
        $segment->id = $id;

        return $segment;
    }

    /** @param int[] $ids */
    private function makeSubscriptions(array $ids): Collection
    {
        $items = array_map(function (int $id) {
            $sub     = Mockery::mock(Subscription::class)->makePartial();
            $sub->id = $id;

            return $sub;
        }, $ids);

        return new Collection($items);
    }

    /**
     * Wire the preview repository to call the callback once per chunk.
     *
     * @param Collection[] $chunks
     */
    private function mockChunk(Segment $segment, array $chunks): void
    {
        $this->previewRepository->allows('chunkActiveSubscriptionsForSegment')
            ->with($segment, Mockery::type('callable'))
            ->andReturnUsing(function (Segment $seg, callable $callback) use ($chunks) {
                foreach ($chunks as $chunk) {
                    $callback($chunk);
                }
            });
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->ruleEngine        = Mockery::mock(SegmentRuleEngine::class);
        $this->previewRepository = Mockery::mock(SegmentPreviewRepository::class);
        $this->service           = new SegmentPreviewService(
            $this->ruleEngine,
            $this->previewRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}