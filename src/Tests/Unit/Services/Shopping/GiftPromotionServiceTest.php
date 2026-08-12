<?php

namespace App\Tests\Unit\Services\Shopping;

use App\Framework\Database\Database;
use App\Models\GiftPromotion;
use App\Repositories\Shopping\GiftPromotionRepository;
use App\Services\Shopping\GiftPromotionService;
use App\Tests\Unit\UnitTestCase;
use Mockery;
use Mockery\MockInterface;

class GiftPromotionServiceTest extends UnitTestCase
{
    private GiftPromotionRepository|MockInterface $repository;
    private Database|MockInterface $databaseMock;
    private GiftPromotionService $service;

    protected function setUp(): void
    {

        $this->repository = Mockery::mock(GiftPromotionRepository::class);
        $this->databaseMock = Mockery::mock(Database::class);

        $this->service = new GiftPromotionService(
            $this->repository,
            $this->databaseMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_create(): void
    {
        $promotion = new GiftPromotion();
        $promotion->name = 'Campaign';
        $promotion->type = 'gift';
        $promotion->site_id = 1;

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository->shouldReceive('find')->andReturn($promotion);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function (array $p) {
                return $p['site_id'] === 1
                    && $p['name'] === 'Campaign'
                    && $p['type'] === 'gift';
            })->andReturn($promotion);

        $promotion = $this->service->create(1, [
            'name' => 'Campaign',
            'type' => 'gift'
        ]);

        $this->assertInstanceOf(GiftPromotion::class, $promotion);
        $this->assertSame('Campaign', $promotion->name);
        $this->assertSame(1, $promotion->site_id);
    }

    public function test_update(): void
    {
        $promotion = new GiftPromotion();

        $this->repository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($promotion);

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->repository
            ->shouldReceive('update')
            ->once();

        $result = $this->service->update(1, ['name' => 'Updated']);

        $this->assertSame($promotion, $result);
    }

    public function test_toggle_active(): void
    {
        $promotion = new GiftPromotion();
        $promotion->is_active = false;
        $promotion->id = 1;

        $this->repository
            ->shouldReceive('find')
            ->andReturn($promotion);

        $this->repository
            ->shouldReceive('update')
            ->with(1, ['active' => true])
            ->once()
            ->andReturn($promotion);

        $result = $this->service->toggleActive(1);
        $this->assertInstanceOf(GiftPromotion::class, $result);
    }

    public function test_is_eligible_for_issue_returns_false_when_inactive(): void
    {
        $promotion = new GiftPromotion();
        $promotion->is_active = false;

        $result = $this->service->isEligibleForIssue($promotion, 100);

        $this->assertFalse($result);
    }

    public function test_is_eligible_for_issue_returns_false_when_issue_is_excluded(): void
    {
        $promotion = Mockery::mock(GiftPromotion::class)->makePartial();
        $promotion->is_active = true;

        $promotion
            ->shouldReceive('supportsIssueExclusions')
            ->andReturn(true);

        $promotion
            ->shouldReceive('hasExcludedIssue')
            ->with(100)
            ->andReturn(true);

        $result = $this->service->isEligibleForIssue($promotion, 100);

        $this->assertFalse($result);
    }

    public function test_is_eligible_for_issue_returns_true_when_valid(): void
    {
        $promotion = Mockery::mock(GiftPromotion::class)->makePartial();
        $promotion->active = true;

        $promotion
            ->shouldReceive('supportsIssueExclusions')
            ->andReturn(false);

        $result = $this->service->isEligibleForIssue($promotion, 100);

        $this->assertTrue($result);
    }
}