<?php

namespace App\Services\PublicContent\Theming;

use App\Models\Site;

final class PublicContentDesignTokenProvider
{
    public function forSite(int $siteId): array
    {
        $site = Site::find($siteId);
        $defaults = (array) config('public-content-design-tokens.defaults', []);

        if ($site === null) {
            return $defaults;
        }

        $siteDefaults = (array) config('public-content-design-tokens.sites.' . $site->slug, []);
        $configured = $site->getSetting('design_tokens', []);

        $tokens = array_replace_recursive(
            $defaults,
            $siteDefaults,
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

    private function logoUrl(Site $site): string
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
