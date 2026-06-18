<?php

namespace App\Tests\Unit\Services\PublicContent;

use App\Enums\PublicContent\GeoSource;
use App\Framework\Http\Request;
use App\Services\PublicContent\ResolvedGeoQueryParser;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ResolvedGeoQueryParserTest extends TestCase
{
    public function test_parses_and_normalises_coarse_geo_query_parameters(): void
    {
        $geo = (new ResolvedGeoQueryParser())->parse(new Request([
            'country' => 'gb',
            'region' => 'eng',
            'geo_source' => 'cf-edge',
        ]));

        self::assertSame('GB', $geo->country);
        self::assertSame('ENG', $geo->region);
        self::assertSame(GeoSource::CF_EDGE, $geo->source);
    }

    public function test_defaults_source_without_deriving_geo_from_ip(): void
    {
        $geo = (new ResolvedGeoQueryParser())->parse(new Request([]));

        self::assertNull($geo->country);
        self::assertNull($geo->region);
        self::assertSame(GeoSource::DEFAULT, $geo->source);
    }

    public function test_rejects_unknown_geo_source(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ResolvedGeoQueryParser())->parse(new Request([
            'geo_source' => 'browser-gps',
        ]));
    }
}
