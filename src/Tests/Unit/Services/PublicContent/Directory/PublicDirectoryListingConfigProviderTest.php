<?php

namespace App\Tests\Unit\Services\PublicContent\Directory;

use App\Enums\PublicContent\PublicDirectoryType;
use App\Models\Site;
use App\Services\PublicContent\Directory\PublicDirectoryListingConfigProvider;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PublicDirectoryListingConfigProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_returns_defaults_when_the_site_has_no_settings(): void
    {
        $provider = new PublicDirectoryListingConfigProvider();
        $config = $provider->forSite($this->site(null), PublicDirectoryType::Author);

        self::assertSame([12, 24, 48, 96], $config->perPageOptions);
        self::assertSame(24, $config->defaultPerPage);
        self::assertSame(96, $config->maxPerPage);
        self::assertSame(['name_asc', 'name_desc', 'newest', 'oldest', 'most_articles'], $config->indexSorts);
        self::assertSame(['category', 'tag', 'author', 'year'], $config->pageFacets);
    }

    public function test_site_wide_settings_override_defaults_for_every_type(): void
    {
        $provider = new PublicDirectoryListingConfigProvider();
        $config = $provider->forSite($this->site([
            'listing' => [
                'max_per_page' => 48,
            ],
        ]), PublicDirectoryType::Tag);

        self::assertSame(48, $config->maxPerPage);
    }

    public function test_type_specific_overrides_win_over_site_wide_settings(): void
    {
        $provider = new PublicDirectoryListingConfigProvider();
        $config = $provider->forSite($this->site([
            'listing' => [
                'max_per_page' => 48,
                'buying-guide' => [
                    'max_per_page' => 12,
                ],
            ],
        ]), PublicDirectoryType::BuyingGuide);

        self::assertSame(12, $config->maxPerPage);
    }

    public function test_type_keyed_blocks_do_not_leak_into_other_types_as_site_wide_settings(): void
    {
        $provider = new PublicDirectoryListingConfigProvider();
        $config = $provider->forSite($this->site([
            'listing' => [
                'buying-guide' => [
                    'max_per_page' => 12,
                ],
            ],
        ]), PublicDirectoryType::Tag);

        self::assertSame(96, $config->maxPerPage);
    }

    public function test_default_per_page_falls_back_to_the_first_option_when_not_in_the_option_list(): void
    {
        $provider = new PublicDirectoryListingConfigProvider();
        $config = $provider->forSite($this->site([
            'listing' => [
                'per_page_options' => [10, 20],
                'default_per_page' => 24,
            ],
        ]), PublicDirectoryType::Author);

        self::assertSame([10, 20], $config->perPageOptions);
        self::assertSame(10, $config->defaultPerPage);
    }

    public function test_invalid_per_page_options_fall_back_to_defaults(): void
    {
        $provider = new PublicDirectoryListingConfigProvider();
        $config = $provider->forSite($this->site([
            'listing' => [
                'per_page_options' => ['not-a-number', -5, 0],
            ],
        ]), PublicDirectoryType::Author);

        self::assertSame([12, 24, 48, 96], $config->perPageOptions);
    }

    public function test_unknown_sort_and_facet_values_are_dropped_and_fall_back_to_defaults_when_all_invalid(): void
    {
        $provider = new PublicDirectoryListingConfigProvider();
        $config = $provider->forSite($this->site([
            'listing' => [
                'index_sorts' => ['not-a-real-sort'],
                'page_facets' => ['category', 'not-a-real-facet'],
            ],
        ]), PublicDirectoryType::Author);

        self::assertSame(['name_asc', 'name_desc', 'newest', 'oldest', 'most_articles'], $config->indexSorts);
        self::assertSame(['category'], $config->pageFacets);
    }

    private function site(?array $publicDirectorySetting): Site
    {
        $site = Mockery::mock(Site::class)->makePartial();
        $site->shouldReceive('getSetting')
            ->with('public_directory', [])
            ->andReturn($publicDirectorySetting ?? []);

        return $site;
    }
}