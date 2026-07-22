<?php

namespace App\DTO\PublicContent\Locale;

/**
 * Portable edge-redirect rules shared with locale artefact delivery.
 *
 * Kept beside locale rules so disabled-locale, doubled-region, and
 * global-vs-regional home behaviour is described once.
 */
final readonly class LocaleEdgeRedirectRules
{
    public function __construct(
        public string $disabledLocaleFallbackPath = '/',
        public bool $collapseDoubledRegion = true,
        public bool $preferRegionalHome = true,
        public string $globalHomePath = '/',
        public string $regionalHomePathTemplate = '/{prefix}',
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            disabledLocaleFallbackPath: isset($data['disabled_locale_fallback_path'])
                ? (string) $data['disabled_locale_fallback_path']
                : '/',
            collapseDoubledRegion: array_key_exists('collapse_doubled_region', $data)
                ? (bool) $data['collapse_doubled_region']
                : true,
            preferRegionalHome: array_key_exists('prefer_regional_home', $data)
                ? (bool) $data['prefer_regional_home']
                : true,
            globalHomePath: isset($data['global_home_path'])
                ? (string) $data['global_home_path']
                : '/',
            regionalHomePathTemplate: isset($data['regional_home_path_template'])
                ? (string) $data['regional_home_path_template']
                : '/{prefix}',
        );
    }

    public function regionalHomePath(string $prefix): string
    {
        $prefix = trim($prefix, '/');

        return str_replace('{prefix}', $prefix, $this->regionalHomePathTemplate);
    }
}
