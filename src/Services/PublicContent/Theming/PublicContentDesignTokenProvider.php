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

        return array_replace_recursive(
            $defaults,
            $siteDefaults,
            is_array($configured) ? $configured : [],
        );
    }

    /** @return array<string, string> */
    public function cssVariablesForSite(int $siteId): array
    {
        $variables = [];
        $this->flatten($this->forSite($siteId), [], $variables);

        return $variables;
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
