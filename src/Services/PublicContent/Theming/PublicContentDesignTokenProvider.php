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
}
