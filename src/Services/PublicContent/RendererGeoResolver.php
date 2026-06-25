<?php

namespace App\Services\PublicContent;

use App\DTO\PublicContent\ResolvedGeo;
use App\Enums\PublicContent\GeoSource;
use App\Framework\Http\Request;

class RendererGeoResolver
{
    public function resolve(Request $request): ResolvedGeo
    {
        $cfCountry = $this->normaliseCountry($request->header('CF-IPCountry'));
        $cfRegion = $this->normaliseRegion(
            $request->header('CF-Region-Code')
                ?? $request->header('CF-Region')
        );

        if ($cfCountry !== null || $cfRegion !== null) {
            return new ResolvedGeo($cfCountry, $cfRegion, GeoSource::CF_EDGE);
        }

        $proxyCountry = $this->normaliseCountry($request->header('X-Geo-Country'));
        $proxyRegion = $this->normaliseRegion($request->header('X-Geo-Region'));

        if ($proxyCountry !== null || $proxyRegion !== null) {
            return new ResolvedGeo($proxyCountry, $proxyRegion, GeoSource::PROXY_INFERRED);
        }

        return new ResolvedGeo(null, null, GeoSource::DEFAULT);
    }

    private function normaliseCountry(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = strtoupper(trim($value));

        return preg_match('/^[A-Z]{2}$/', $value) ? $value : null;
    }

    private function normaliseRegion(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = strtoupper(trim($value));

        return $value !== '' && strlen($value) <= 64 && preg_match('/^[A-Z0-9_-]+$/', $value)
            ? $value
            : null;
    }
}
