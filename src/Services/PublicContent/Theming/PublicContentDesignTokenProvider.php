<?php

namespace App\Services\PublicContent\Theming;

use App\Repositories\Cms\SiteRepository;

final class PublicContentDesignTokenProvider
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
        private readonly PublicContentDesignTokenSource $designTokens,
    ) {
    }

    public function forSite(int $siteId): array
    {
        $site = $this->siteRepository->find($siteId);
        $defaults = $this->designTokens->defaults($siteId);

        if ($site === null) {
            return $defaults;
        }

        $overrides = $this->designTokens->overrides($siteId);
        $configured = $site->getSetting('design_tokens', []);

        $tokens = array_replace_recursive(
            $defaults,
            $overrides,
            is_array($configured) ? $configured : [],
        );

        $tokens['brand'] = array_replace(
            $tokens['brand'] ?? [],
            [
                'site_name' => (string) $site->name,
                'tagline' => (string) $site->getSetting('tagline', ''),
                'logo_url' => $this->logoUrl($site),
            ],
        );

        return $tokens;
    }

    /** @return array<string, string> */
    public function cssVariablesForSite(int $siteId): array
    {
        $variables = [];
        $tokens = $this->forSite($siteId);

        unset(
            $tokens['brand']['site_name'],
            $tokens['brand']['tagline'],
            $tokens['brand']['logo_url'],
        );

        $this->flatten($tokens, [], $variables);

        return $variables;
    }

    private function logoUrl(\App\Models\Site $site): string
    {
        if (is_string($site->logo) && trim($site->logo) !== '') {
            return trim($site->logo);
        }

        if ($site->logo_image_id && $site->logoImage) {
            return (string) $site->logoImage->url;
        }

        return '';
    }

    private function flatten(array $tokens, array $path, array &$variables): void
    {
        foreach ($tokens as $key => $value) {
            $segment = preg_replace('/[^a-z0-9-]+/', '-', strtolower(str_replace('_', '-', (string) $key)));
            $currentPath = [...$path, trim((string) $segment, '-')];

            if (is_array($value)) {
                $this->flatten($value, $currentPath, $variables);
                continue;
            }

            if (!is_string($value) && !is_int($value) && !is_float($value)) {
                continue;
            }

            $variables['--' . implode('-', array_filter($currentPath))] = (string) $value;
        }
    }
}