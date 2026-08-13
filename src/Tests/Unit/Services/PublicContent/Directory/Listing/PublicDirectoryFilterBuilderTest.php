<?php

namespace App\Tests\Unit\Services\PublicContent\Directory\Listing;

use App\Data\PublicContent\Listing\ListingFilterData;
use App\Framework\Database\QueryBuilder;
use App\Services\PublicContent\Directory\Listing\PublicDirectoryFilterBuilder;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PublicDirectoryFilterBuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_for_index_applies_a_name_search_when_present(): void
    {
        $query = Mockery::mock(QueryBuilder::class);
        $query->shouldReceive('whereLike')->once()->with('name', '%estate agents%')->andReturnSelf();

        $builder = new PublicDirectoryFilterBuilder();
        $result = $builder->forIndex($query, $this->filter(search: 'estate agents'));

        self::assertSame($query, $result);
    }

    public function test_for_index_does_not_filter_when_search_is_blank(): void
    {
        $query = Mockery::mock(QueryBuilder::class);
        $query->shouldReceive('whereLike')->never();

        $builder = new PublicDirectoryFilterBuilder();
        $result = $builder->forIndex($query, $this->filter(search: '   '));

        self::assertSame($query, $result);
    }

    public function test_for_pages_applies_title_and_description_search(): void
    {
        $query = Mockery::mock(QueryBuilder::class);
        $query->shouldReceive('where')->once()->with(Mockery::type('callable'))->andReturnUsing(
            function (callable $callback) use ($query) {
                $inner = Mockery::mock(QueryBuilder::class);
                $inner->shouldReceive('whereLike')->once()->with('title', '%buying guide%')->andReturnSelf();
                $inner->shouldReceive('orWhereLike')->once()->with('meta_description', '%buying guide%')->andReturnSelf();
                $callback($inner);

                return $query;
            },
        );

        $builder = new PublicDirectoryFilterBuilder();
        $result = $builder->forPages($query, $this->filter(search: 'buying guide'));

        self::assertSame($query, $result);
    }

    public function test_for_pages_applies_a_category_facet_via_where_has(): void
    {
        $query = Mockery::mock(QueryBuilder::class);
        $query->shouldReceive('whereHas')->once()->with('categories', Mockery::type('callable'))->andReturnUsing(
            function (string $relation, callable $callback) use ($query) {
                $inner = Mockery::mock(QueryBuilder::class);
                $inner->shouldReceive('whereIn')->once()->with('categories.id', ['5', '6'])->andReturnSelf();
                $callback($inner);

                return $query;
            },
        );

        $builder = new PublicDirectoryFilterBuilder();
        $result = $builder->forPages($query, $this->filter(facets: ['category' => ['5', '6']]));

        self::assertSame($query, $result);
    }

    public function test_for_pages_ignores_an_unknown_facet_key(): void
    {
        $query = Mockery::mock(QueryBuilder::class);
        $query->shouldReceive('whereHas')->never();
        $query->shouldReceive('where')->never();

        $builder = new PublicDirectoryFilterBuilder();
        $result = $builder->forPages($query, $this->filter(facets: ['not-a-real-facet' => ['1']]));

        self::assertSame($query, $result);
    }

    public function test_for_pages_ignores_a_facet_with_no_selected_values(): void
    {
        $query = Mockery::mock(QueryBuilder::class);
        $query->shouldReceive('whereHas')->never();

        $builder = new PublicDirectoryFilterBuilder();
        $result = $builder->forPages($query, $this->filter(facets: ['category' => []]));

        self::assertSame($query, $result);
    }

    private function filter(?string $search = null, array $facets = []): ListingFilterData
    {
        return new ListingFilterData(search: $search, sort: 'newest', page: 1, perPage: 24, facets: $facets);
    }
}