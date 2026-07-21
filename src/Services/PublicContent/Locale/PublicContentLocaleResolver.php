<?php

namespace App\Services\PublicContent\Locale;

use App\DTO\PublicContent\Locale\LocaleRulesArtefact;
use App\DTO\PublicContent\PublicContentLocaleContext;
use App\Models\Territory;

/**
 * Resolves language and region as independent axes from territory facts,
 * preferring the versioned locale-rules artefact when a match exists.
 */
final class PublicContentLocaleResolver
{
    public function __construct(
        private readonly ?LocaleRulesArtefact $rules = null,
    ) {
    }

    public function fromTerritory(?Territory $territory): PublicContentLocaleContext
    {
        if ($territory === null) {
            return new PublicContentLocaleContext();
        }

        if ($this->rules !== null) {
            $byPrefix = $this->rules->findByRegion((string) ($territory->slug ?? ''));
            if ($byPrefix !== null && $byPrefix->enabled) {
                return new PublicContentLocaleContext(
                    language: $byPrefix->language,
                    region: $byPrefix->region,
                );
            }

            $code = trim((string) ($territory->code ?? ''));
            if ($code !== '') {
                $byLocale = $this->rules->findByLocale($code);
                if ($byLocale !== null && $byLocale->enabled) {
                    return new PublicContentLocaleContext(
                        language: $byLocale->language,
                        region: $byLocale->region,
                    );
                }

                $byRegion = $this->rules->findByRegion($code);
                if ($byRegion !== null && $byRegion->enabled) {
                    return new PublicContentLocaleContext(
                        language: $byRegion->language,
                        region: $byRegion->region,
                    );
                }
            }
        }

        return $this->fromCodeFallback($territory);
    }

    private function fromCodeFallback(Territory $territory): PublicContentLocaleContext
    {
        $code = trim((string) ($territory->code ?? ''));

        if ($code === '') {
            return new PublicContentLocaleContext(
                language: null,
                region: $territory->slug ? strtoupper((string) $territory->slug) : null,
            );
        }

        if (str_contains($code, '-') || str_contains($code, '_')) {
            $parts = preg_split('/[-_]/', $code) ?: [];
            $language = strtolower((string) ($parts[0] ?? ''));
            $region = strtoupper((string) ($parts[1] ?? ''));

            return new PublicContentLocaleContext(
                language: $language !== '' ? $language : null,
                region: $region !== '' ? $region : null,
            );
        }

        if (strlen($code) === 2 && $code === strtoupper($code)) {
            return new PublicContentLocaleContext(language: null, region: strtoupper($code));
        }

        if (strlen($code) === 2) {
            return new PublicContentLocaleContext(language: strtolower($code), region: null);
        }

        return new PublicContentLocaleContext(language: null, region: strtoupper($code));
    }
}
