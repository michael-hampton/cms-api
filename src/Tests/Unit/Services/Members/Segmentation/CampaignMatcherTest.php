<?php

namespace App\Tests\Unit\Services\Members\Segmentation;

use App\Framework\Support\Collection;
use App\Models\Campaign;
use App\Repositories\Members\CampaignRepository;
use App\Services\Members\Segmentation\CampaignMatcher;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class CampaignMatcherTest extends TestCase
{
    private CampaignRepository|MockInterface $campaignRepository;
    private CampaignMatcher $matcher;

    public function test_returns_empty_collection_for_empty_segment_keys(): void
    {
        $this->campaignRepository
            ->shouldReceive('matchActiveBySegmentKeys')
            ->once()
            ->with([])
            ->andReturn(new Collection());

        $result = $this->matcher->match([]);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function test_returns_campaigns_matching_given_segment_keys(): void
    {
        $campaign = $this->makeCampaign(id: 1, priority: 50);

        $this->campaignRepository->shouldReceive('matchActiveBySegmentKeys')
            ->once()
            ->with(['churning'])
            ->andReturn(new Collection([$campaign]));

        $result = $this->matcher->match(['churning']);

        $this->assertCount(1, $result);
        $this->assertSame(1, $result->first()->id);
    }

    private function makeCampaign(int $id, int $priority): Campaign
    {
        $campaign = Mockery::mock(Campaign::class)->makePartial();
        $campaign->id = $id;
        $campaign->priority = $priority;
        $campaign->setRelation('segment', (object)[]);
        return $campaign;
    }

    public function test_campaigns_are_ordered_by_priority_descending(): void
    {
        $high = $this->makeCampaign(id: 1, priority: 100);
        $low = $this->makeCampaign(id: 2, priority: 30);

        $this->campaignRepository->shouldReceive('matchActiveBySegmentKeys')
            ->once()
            ->with(['churning', 'lurker'])
            ->andReturn(new Collection([$high, $low]));

        $result = $this->matcher->match(['churning', 'lurker']);

        $this->assertSame(100, $result->first()->priority);
        $this->assertSame(30, $result->last()->priority);
    }

    public function test_only_active_campaigns_are_returned(): void
    {
        $this->campaignRepository->shouldReceive('matchActiveBySegmentKeys')
            ->once()
            ->with(['highly_active'])
            ->andReturn(new Collection());

        $this->matcher->match(['highly_active']);
        $this->addToAssertionCount(1);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();
        $this->campaignRepository = Mockery::mock(CampaignRepository::class);
        $this->matcher = new CampaignMatcher($this->campaignRepository);
    }
}
