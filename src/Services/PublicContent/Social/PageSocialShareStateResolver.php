<?php

namespace App\Services\PublicContent\Social;

use App\DTO\PublicContent\Social\PageSocialShareState;
use App\Models\Page;

/**
 * Builds share-island state from page_social for editorial page kinds.
 */
final class PageSocialShareStateResolver
{
    public function resolve(Page $page, string $canonicalUrl): ?PageSocialShareState
    {
        $social = $page->social ?? null;

        if ($social === null || empty($social->enable_sharing)) {
            return null;
        }

        $platforms = is_array($social->platforms ?? null)
            ? array_values(array_filter(array_map('strval', $social->platforms)))
            : [];

        if ($platforms === []) {
            return null;
        }

        return new PageSocialShareState(
            enableSharing: true,
            platforms: $platforms,
            shareText: (string) ($social->share_text ?: $page->title ?: ''),
            shareUrl: $canonicalUrl,
            shareHashtags: isset($social->share_hashtags) ? (string) $social->share_hashtags : null,
        );
    }
}
