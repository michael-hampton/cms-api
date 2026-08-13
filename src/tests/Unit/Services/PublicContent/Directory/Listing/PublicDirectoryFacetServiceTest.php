<?php

namespace App\Tests\Unit\Services\PublicContent\Directory\Listing;

use App\Data\PublicContent\Listing\FacetGroupData;
use App\Data\PublicContent\Listing\ListingFilterData;
use App\Framework\Database\QueryBuilder;
use App\Framework\Support\Collection;
use App\Services\PublicContent\Directory\Listing\PublicDirectoryFacetService;
use App\Services\PublicContent\Directory\Listing\PublicDirectoryFilterBuilder;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PublicDirectoryFacetServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_build_returns_one_group_per_enabled_facet_with_options_from_the_query(): void
    {
        $baseQuery = Mockery::mock(QueryBuilder::class);
        $baseQuery->shouldReceive('whereNotNull')->with('page_type')->andReturnSelf();
        $baseQuery->shouldReceive('selectRaw')->andReturnSelf();
        $baseQuery->shouldReceive('groupBy')->andReturnSelf();
        $baseQuery->shouldReceive('orderByRaw')->andReturnSelf();
        $baseQuery->shouldReceive('get')->andReturn(new Collection([
            (object) ['facet_value' => 'article', 'facet_label' => 'article', 'facet_count' => 5],
            (object) ['facet_value' => 'review', 'facet_label' => 'review', 'facet_count' => 2],
        ]));

        $service = new PublicDirectoryFacetService(new PublicDirectoryFilterBuilder());

        $groups = $service->build($baseQuery, $this->filter(), ['article_type']);

        self::assertCount(1, $groups);
        self::assertInstanceOf(FacetGroupData::class, $groups[0]);
        self::assertSame('article_type', $groups[0]->key);
        self::assertSame('Type', $groups[0]->label);
        self::assertCount(2, $groups[0]->options);
        self::assertSame('article', $groups[0]->options[0]->value);
        self::assertSame(5, $groups[0]->options[0]->count);
    }

    public function test_build_marks_options_the_filter_has_already_selected(): void
    {
        $baseQuery = Mockery::mock(QueryBuilder::class);
        $baseQuery->shouldReceive('whereNotNull')->andReturnSelf();
        $baseQuery->shouldReceive('selectRaw')->andReturnSelf();
        $baseQuery->shouldReceive('groupBy')->andReturnSelf();
        $baseQuery->shouldReceive('orderByRaw')->andReturnSelf();
        $baseQuery->shouldReceive('get')->andReturn(new Collection([
            (object) ['facet_value' => 'article', 'facet_label' => 'article', 'facet_count' => 5],
            (object) ['facet_value' => 'review', 'facet_label' => 'review', 'facet_count' => 2],
        ]));

        $service = new PublicDirectoryFacetService(new PublicDirectoryFilterBuilder());

        $filter = $this->filter(facets: ['article_type' => ['article']]);
        $groups = $service->build($baseQuery, $filter, ['article_type']);

        self::assertTrue($groups[0]->options[0]->selected);
        self::assertFalse($groups[0]->options[1]->selected);
    }

    public function test_build_skips_an_unrecognised_facet_key(): void
    {
        $baseQuery = Mockery::mock(QueryBuilder::class);

        $service = new PublicDirectoryFacetService(new PublicDirectoryFilterBuilder());

        $groups = $service->build($baseQuery, $this->filter(), ['not-a-real-facet']);

        self::assertSame([], $groups);
    }

    public function test_build_returns_no_groups_when_no_facets_are_enabled(): void
    {
        $baseQuery = Mockery::mock(QueryBuilder::class);

        $service = new PublicDirectoryFacetService(new PublicDirectoryFilterBuilder());

        self::assertSame([], $service->build($baseQuery, $this->filter(), []));
    }

    private function filter(array $facets = []): ListingFilterData
    {
        return new ListingFilterData(search: null, sort: 'newest', page: 1, perPage: 24, facets: $facets);
    }
}