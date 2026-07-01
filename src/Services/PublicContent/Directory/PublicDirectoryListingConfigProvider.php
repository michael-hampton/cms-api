<?php
declare(strict_types=1);

namespace App\Services\PublicContent\Directory;

use App\Data\PublicContent\PublicDirectoryListingConfigData;
use App\Enums\PublicContent\PublicDirectoryFacet;
use App\Enums\PublicContent\PublicDirectoryIndexSort;
use App\Enums\PublicContent\PublicDirectoryPageSort;
use App\Enums\PublicContent\PublicDirectoryType;
use App\Models\Site;

final class PublicDirectoryListingConfigProvider
{
    private const array DEFAULTS = [
        'per_page_options' => [12, 24, 48, 96],
        'default_per_page' => 24,
        'max_per_page' => 96,
        'index_sorts' => ['name_asc', 'name_desc', 'newest', 'oldest', 'most_articles'],
        'page_sorts' => ['newest', 'oldest', 'title_asc', 'title_desc', 'most_viewed', 'most_commented'],
        'page_facets' => ['category', 'tag', 'author', 'year'],
    ];

    /**
     * Resolves listing config for a given directory type on a given site.
     *
     * Precedence (lowest to highest): DEFAULTS -> site-wide settings
     * (public_directory.listing.*) -> type-specific overrides
     * (public_directory.listing.{type}.*). A site that has never configured
     * per-type overrides behaves exactly as before this change.
     */
    public function forSite(Site $site, PublicDirectoryType $type): PublicDirectoryListingConfigData
    {
        $publicDirectory = $site->getSetting('public_directory', []);
        $listing = is_array($publicDirectory) && is_array($publicDirectory['listing'] ?? null)
            ? $publicDirectory['listing']
            : [];

        $siteWide = $this->siteWideSettings($listing);
        $typeOverrides = is_array($listing[$type->value] ?? null) ? $listing[$type->value] : [];

        $settings = array_replace(self::DEFAULTS, $siteWide, $typeOverrides);

        $perPageOptions = $this->positiveIntList($settings['per_page_options'], self::DEFAULTS['per_page_options']);
        $maxPerPage = $this->positiveInt($settings['max_per_page'], self::DEFAULTS['max_per_page']);
        $defaultPerPage = $this->positiveInt($settings['default_per_page'], self::DEFAULTS['default_per_page']);

        return new PublicDirectoryListingConfigData(
            perPageOptions: $perPageOptions,
            defaultPerPage: in_array($defaultPerPage, $perPageOptions, true) ? $defaultPerPage : $perPageOptions[0],
            maxPerPage: $maxPerPage,
            indexSorts: $this->enumList($settings['index_sorts'], PublicDirectoryIndexSort::class, self::DEFAULTS['index_sorts']),
            pageSorts: $this->enumList($settings['page_sorts'], PublicDirectoryPageSort::class, self::DEFAULTS['page_sorts']),
            pageFacets: $this->enumList($settings['page_facets'], PublicDirectoryFacet::class, self::DEFAULTS['page_facets']),
        );
    }

    /**
     * Strips type-keyed override blocks (e.g. "buying-guide" => [...]) out of
     * the listing settings, leaving only the flat, site-wide keys that apply
     * to every directory type unless a type-specific override exists.
     *
     * @param array<string,mixed> $listing
     * @return array<string,mixed>
     */
    private function siteWideSettings(array $listing): array
    {
        $typeKeys = array_map(static fn(PublicDirectoryType $type): string => $type->value, PublicDirectoryType::cases());

        return array_diff_key($listing, array_flip($typeKeys));
    }

    private function positiveIntList(mixed $value, array $default): array
    {
        if (!is_array($value) || empty($value)) {
            return $default;
        }

        $ints = array_values(array_unique(array_filter(
            array_map(static fn($item) => filter_var($item, FILTER_VALIDATE_INT), $value),
            static fn($item) => $item !== false && $item > 0,
        )));

        sort($ints);

        return $ints ?: $default;
    }

    private function positiveInt(mixed $value, int $default): int
    {
        $value = filter_var($value, FILTER_VALIDATE_INT);

        return is_int($value) && $value > 0 ? $value : $default;
    }

    /**
     * @param class-string<PublicDirectoryIndexSort|PublicDirectoryPageSort|PublicDirectoryFacet> $enumClass
     */
    private function enumList(mixed $value, string $enumClass, array $default): array
    {
        if (!is_array($value) || empty($value)) {
            return $default;
        }

        $valid = array_values(array_filter(
            $value,
            static fn($item) => is_string($item) && $enumClass::tryFrom($item) !== null,
        ));

        return $valid ?: $default;
    }
}