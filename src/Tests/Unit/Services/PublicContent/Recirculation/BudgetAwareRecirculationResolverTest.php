<?php

namespace App\Tests\Unit\Services\PublicContent\Recirculation;

use App\DTO\PublicContent\Sources\SourceResult;
use App\Enums\PublicContent\SourceResultStatus;
use App\Models\Page;
use App\Services\PublicContent\CompositionDeadline;
use App\Services\PublicContent\Recirculation\BudgetAwareRecirculationResolver;
use App\Services\PublicContent\Recirculation\RecirculationSourceInterface;
use Mockery;
use PHPUnit\Framework\TestCase;

final class BudgetAwareRecirculationResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_degrades_when_budget_exhausted(): void
    {
        $source = Mockery::mock(RecirculationSourceInterface::class);
        $source->shouldReceive('resolve')->never();

        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;

        $result = (new BudgetAwareRecirculationResolver($source, 300))->resolve(
            $page,
            1,
            new CompositionDeadline(static fn(): int => 50),
        );

        self::assertSame(SourceResultStatus::Degraded, $result->status);
        self::assertSame('budget_exhausted', $result->reason);
    }

    public function test_delegates_when_budget_remains(): void
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 1;

        $source = Mockery::mock(RecirculationSourceInterface::class);
        $source->shouldReceive('resolve')->once()->with($page, 1, 4)->andReturn(SourceResult::empty());

        $result = (new BudgetAwareRecirculationResolver($source, 300))->resolve(
            $page,
            1,
            new CompositionDeadline(static fn(): int => 500),
        );

        self::assertTrue($result->isEmpty());
    }
}
