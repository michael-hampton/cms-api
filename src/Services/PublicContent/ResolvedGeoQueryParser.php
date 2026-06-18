<?php

namespace App\Services\PublicContent;

use App\DTO\PublicContent\ResolvedGeo;
use App\Enums\PublicContent\GeoSource;
use App\Framework\Http\Request;
use InvalidArgumentException;

final class ResolvedGeoQueryParser
{
    public function parse(Request $request): ResolvedGeo
    {
        $country = $this->normaliseCountry($request->get('country'));
        $region = $this->normaliseRegion($request->get('region'));
        $source = $this->normaliseSource($request->get('geo_source'));

        return new ResolvedGeo($country, $region, $source);
    }

    private function normaliseCountry(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $country = strtoupper(trim((string) $value));

        if (!preg_match('/^[A-Z]{2}$/', $country)) {
            throw new InvalidArgumentException('country must be a two-letter country code.');
        }

        return $country;
    }

    private function normaliseRegion(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $region = strtoupper(trim((string) $value));

        if (strlen($region) > 64 || !preg_match('/^[A-Z0-9_-]+$/', $region)) {
            throw new InvalidArgumentException('region contains invalid characters.');
        }

        return $region;
    }

    private function normaliseSource(mixed $value): GeoSource
    {
        if ($value === null || trim((string) $value) === '') {
            return GeoSource::DEFAULT;
        }

        return GeoSource::tryFrom(strtolower(trim((string) $value)))
            ?? throw new InvalidArgumentException('geo_source is invalid.');
    }
}
