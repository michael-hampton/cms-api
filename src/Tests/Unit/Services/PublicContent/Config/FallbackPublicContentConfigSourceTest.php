<?php

namespace App\Tests\Unit\Services\PublicContent\Config;

use App\Services\PublicContent\Config\FallbackPublicContentConfigSource;
use App\Services\PublicContent\Config\PublicContentConfigSource;
use Mockery;
use PHPUnit\Framework\TestCase;

final class FallbackPublicContentConfigSourceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_uses_file_when_site_has_no_database_document(): void
    {
        $database = Mockery::mock(PublicContentConfigSource::class);
        $database->shouldReceive('has')->with(3)->andReturn(false);

        $file = Mockery::mock(PublicContentConfigSource::class);
        $file->shouldReceive('get')
            ->once()
            ->with(3, 'widgets.recirculation.page_types', ['*'])
            ->andReturn(['article', 'review', 'buying-guide']);

        $source = new FallbackPublicContentConfigSource($database, $file);

        self::assertSame(
            ['article', 'review', 'buying-guide'],
            $source->get(3, 'widgets.recirculation.page_types', ['*']),
        );
    }

    public function test_falls_back_to_file_for_keys_missing_from_database_document(): void
    {
        $database = Mockery::mock(PublicContentConfigSource::class);
        $database->shouldReceive('has')->with(2)->andReturn(true);
        $database->shouldReceive('get')
            ->once()
            ->with(2, 'widgets.recirculation.page_types', Mockery::type(\stdClass::class))
            ->andReturnUsing(static fn (int $siteId, string $key, mixed $default): mixed => $default);

        $file = Mockery::mock(PublicContentConfigSource::class);
        $file->shouldReceive('get')
            ->once()
            ->with(2, 'widgets.recirculation.page_types', ['*'])
            ->andReturn(['article', 'review', 'buying-guide']);

        $source = new FallbackPublicContentConfigSource($database, $file);

        self::assertSame(
            ['article', 'review', 'buying-guide'],
            $source->get(2, 'widgets.recirculation.page_types', ['*']),
        );
    }

    public function test_database_value_wins_when_key_is_present(): void
    {
        $database = Mockery::mock(PublicContentConfigSource::class);
        $database->shouldReceive('has')->with(2)->andReturn(true);
        $database->shouldReceive('get')
            ->once()
            ->with(2, 'widgets.recirculation.page_types', Mockery::type(\stdClass::class))
            ->andReturn(['article']);

        $file = Mockery::mock(PublicContentConfigSource::class);
        $file->shouldReceive('get')->never();

        $source = new FallbackPublicContentConfigSource($database, $file);

        self::assertSame(['article'], $source->get(2, 'widgets.recirculation.page_types', ['*']));
    }
}
