<?php

namespace App\Tests\Unit\Services\PublicContent\Deals;

use App\Enums\PublicContent\SourceResultStatus;
use App\Framework\Support\Logger;
use App\Services\Offers\DealsService;
use App\Services\PublicContent\Config\PublicContentConfigSource;
use App\Services\PublicContent\Deals\PublicContentDealsSource;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PublicContentDealsSourceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_ok_when_active_deals_exist(): void
    {
        $deals = Mockery::mock(DealsService::class);
        $deals->shouldReceive('getActiveDeals')->once()->with(10, 1)->andReturn([
            ['id' => 1, 'title' => 'Deal'],
        ]);

        $config = Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')->with(1, 'widgets.deals.page_types', ['*'])
            ->andReturn(['article', 'landing-page']);

        $logger = Mockery::mock(Logger::class);

        $result = (new PublicContentDealsSource($deals, $config, $logger))->resolve(1, 'article', 10);

        self::assertTrue($result->isOk());
        self::assertCount(1, $result->items());
    }

    public function test_empty_when_no_active_deals(): void
    {
        $deals = Mockery::mock(DealsService::class);
        $deals->shouldReceive('getActiveDeals')->once()->andReturn([]);

        $config = Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')->andReturn(['article']);

        $logger = Mockery::mock(Logger::class);

        $result = (new PublicContentDealsSource($deals, $config, $logger))->resolve(1, 'article');

        self::assertTrue($result->isEmpty());
        self::assertSame(SourceResultStatus::Empty, $result->status);
    }

    public function test_degraded_on_exception_without_inventing_deals(): void
    {
        $deals = Mockery::mock(DealsService::class);
        $deals->shouldReceive('getActiveDeals')->once()
            ->andThrow(new RuntimeException('upstream down'));
        $deals->shouldReceive('getTodaysDeals')->never();
        $deals->shouldReceive('getFeaturedDealsOnly')->never();

        $config = Mockery::mock(PublicContentConfigSource::class);
        $config->shouldReceive('get')->andReturn(['*']);

        $logger = Mockery::mock(Logger::class);
        $logger->shouldReceive('warning')->once();

        $result = (new PublicContentDealsSource($deals, $config, $logger))->resolve(1, 'article');

        self::assertTrue($result->isDegraded());
        self::assertSame([], $result->items());
        self::assertSame('unavailable', $result->reason);
    }
}
