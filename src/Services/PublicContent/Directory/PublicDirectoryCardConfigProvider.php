<?php

declare(strict_types=1);

namespace App\Services\PublicContent\Directory;

use App\Data\PublicContent\PublicDirectoryPageCardConfigData;
use App\Models\Site;

final class PublicDirectoryCardConfigProvider
{
    private const array DEFAULTS = [
        'show_image' => true,
        'show_summary' => true,
        'show_categories' => true,
        'show_tags' => true,
        'show_authors' => true,
        'show_published_date' => true,
        'category_limit' => 2,
        'tag_limit' => 3,
        'author_limit' => 3,
        'summary_length' => 150,
    ];

    public function forSite(Site $site): PublicDirectoryPageCardConfigData
    {
        $publicDirectory = $site->getSetting('public_directory', []);
        $configured = is_array($publicDirectory) && is_array($publicDirectory['page_card'] ?? null)
            ? $publicDirectory['page_card']
            : [];
        $settings = array_replace(self::DEFAULTS, $configured);

        return new PublicDirectoryPageCardConfigData(
            showImage: (bool) $settings['show_image'],
            showSummary: (bool) $settings['show_summary'],
            showCategories: (bool) $settings['show_categories'],
            showTags: (bool) $settings['show_tags'],
            showAuthors: (bool) $settings['show_authors'],
            showPublishedDate: (bool) $settings['show_published_date'],
            categoryLimit: $this->positiveInt($settings['category_limit'], self::DEFAULTS['category_limit']),
            tagLimit: $this->positiveInt($settings['tag_limit'], self::DEFAULTS['tag_limit']),
            authorLimit: $this->positiveInt($settings['author_limit'], self::DEFAULTS['author_limit']),
            summaryLength: $this->positiveInt($settings['summary_length'], self::DEFAULTS['summary_length']),
        );
    }

    private function positiveInt(mixed $value, int $default): int
    {
        $value = filter_var($value, FILTER_VALIDATE_INT);

        return is_int($value) && $value > 0 ? $value : $default;
    }
}
