<?php

namespace App\Tests\Unit\Services\PublicContent\Recirculation;

use App\DTO\PublicContent\Sources\SourceResult;
use App\Framework\Support\Collection;
use App\Models\Page;
use App\Services\PublicContent\Config\PublicContentConfigSource;
use App\Services\PublicContent\Recirculation\RecirculationRecommendationsSource;
use App\Services\PublicContent\Recirculation\RecirculationSourceLogger;
use App\Services\Recommendations\ContentRecommendationService;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RecirculationRecommendationsSourceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_ok_result_when_recommendations_returned(): void
    {
        $page = $this->page('article');

        $recommendations = Mockery::mock(ContentRecommendationService::class);
        $recommendations->shouldReceive('forPage')->once()->with($page, 1, 4)->andReturn(new Collection([(object) ['id' => 2]]));

        $logger = Mockery::mock(RecirculationSourceLogger::class);
        $logger->shouldReceive('warning')->never();

        $result = $this->source($recommendations, $logger)->resolve($page, 1);

        self::assertTrue($result->isOk());
        self::assertCount(1, $result->items());
    }

    public function test_empty_result_when_no_recommendations(): void
    {
        $page = $this->page('article');

        $recommendations = Mockery::mock(ContentRecommendationService::class);
        $recommendations->shouldReceive('forPage')->once()->andReturn(new Collection());

        $logger = Mockery::mock(RecirculationSourceLogger::class);
        $logger->shouldReceive('warning')->never();

        $result = $this->source($recommendations, $logger)->resolve($page, 1);

        self::assertTrue($result->isEmpty());
        self::assertFalse($result->isDegraded());
        self::assertSame([], $result->items());
    }

    public function test_skips_ineligible_page_types_from_config(): void
    {
        $page = $this->page('landing-page');

        $recommendations = Mockery::mock(ContentRecommendationService::class);
        $recommendations->shouldReceive('forPage')->never();

        $logger = Mockery::mock(RecirculationSourceLogger::class);
        $logger->shouldReceive('warning')->never();

        $config = Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')
            ->once()
            ->with(1, 'widgets.recirculation.page_types', ['*'])
            ->andReturn(['article', 'review', 'buying-guide']);

        $result = $this->source($recommendations, $logger, $config)->resolve($page, 1);

        self::assertTrue($result->isEmpty());
    }

    public function test_degraded_on_exception(): void
    {
        $page = $this->page('article');

        $recommendations = Mockery::mock(ContentRecommendationService::class);
        $recommendations->shouldReceive('forPage')->once()->andThrow(new RuntimeException('down'));

        $logger = Mockery::mock(RecirculationSourceLogger::class);
        $logger->shouldReceive('warning')->once();

        $result = $this->source($recommendations, $logger)->resolve($page, 1);

        self::assertTrue($result->isDegraded());
        self::assertSame([], $result->items());
        self::assertSame('unavailable', $result->reason);
    }

    public function test_degraded_on_malformed_response(): void
    {
        $page = $this->page('article');

        $recommendations = Mockery::mock(ContentRecommendationService::class);
        $recommendations->shouldReceive('forPage')->once()->andReturn(new Collection(['not-a-page']));

        $logger = Mockery::mock(RecirculationSourceLogger::class);
        $logger->shouldReceive('warning')->once();

        $result = $this->source($recommendations, $logger)->resolve($page, 1);

        self::assertTrue($result->isDegraded());
        self::assertSame('malformed', $result->reason);
        self::assertInstanceOf(SourceResult::class, $result);
    }

    private function page(string $pageType): Page
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = 10;
        $page->page_type = $pageType;

        return $page;
    }

    private function source(
        ContentRecommendationService $recommendations,
        RecirculationSourceLogger $logger,
        ?PublicContentConfigSource $config = null,
    ): RecirculationRecommendationsSource {
        $config ??= Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')
            ->byDefault()
            ->with(Mockery::any(), 'widgets.recirculation.page_types', ['*'])
            ->andReturn(['article', 'review', 'buying-guide']);

        return new RecirculationRecommendationsSource($recommendations, $logger, $config);
    }
}
