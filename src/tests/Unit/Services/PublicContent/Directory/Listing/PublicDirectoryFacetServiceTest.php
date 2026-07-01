<?php
declare(strict_types=1);

namespace App\Tests\Unit\Services\PublicContent\Directory\Listing;

use App\Data\PublicContent\Listing\FacetGroupData;
use App\Data\PublicContent\Listing\ListingFilterData;
use App\Enums\PublicContent\PublicDirectoryFacet;
use App\Framework\Database\QueryBuilder;
use App\Framework\Support\Collection;
use App\Services\PublicContent\Directory\Listing\PublicDirectoryFacetService;
use App\Services\PublicContent\Directory\Listing\PublicDirectoryFilterBuilder;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class PublicDirectoryFacetServiceTest extends MockeryTestCase
{
    private PublicDirectoryFilterBuilder $filterBuilder;
    private PublicDirectoryFacetService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filterBuilder = Mockery::mock(PublicDirectoryFilterBuilder::class);
        $this->service = new PublicDirectoryFacetService($this->filterBuilder);
    }

    public function testBuildReturnsEmptyArrayWhenNoFacetsEnabled(): void
    {
        $baseQuery = Mockery::mock(QueryBuilder::class);
        $filter = new ListingFilterData(null, 'newest', 1, 10, []);

        $result = $this->service->build($baseQuery, $filter, []);

        $this->assertEmpty($result);
    }

    public function testBuildSkipsUnknownFacetKeys(): void
    {
        $baseQuery = Mockery::mock(QueryBuilder::class);
        $filter = new ListingFilterData(null, 'newest', 1, 10, []);

        $result = $this->service->build($baseQuery, $filter, ['invalid_facet_key']);

        $this->assertEmpty($result);
    }

    public function testBuildMapsCategoryFacetCorrectlyWithSelectedOptions(): void
    {
        $baseQuery = Mockery::mock(QueryBuilder::class);

        $baseQuery->shouldReceive('join')->once()->with('page_categories', 'pages.id', '=', 'page_categories.page_id')->andReturnSelf();
        $baseQuery->shouldReceive('join')->once()->with('categories', 'page_categories.category_id', '=', 'categories.id')->andReturnSelf();
        $baseQuery->shouldReceive('selectRaw')->once()->with('categories.id as facet_value, categories.name as facet_label, COUNT(DISTINCT pages.id) as facet_count')->andReturnSelf();
        $baseQuery->shouldReceive('groupBy')->once()->with('categories.id', 'categories.name')->andReturnSelf();
        $baseQuery->shouldReceive('orderByRaw')->once()->with('facet_count desc')->andReturnSelf();

        $row1 = (object)['facet_value' => '10', 'facet_label' => 'Tech', 'facet_count' => 5];
        $row2 = (object)['facet_value' => '20', 'facet_label' => 'Life', 'facet_count' => 2];
        $baseQuery->shouldReceive('get')->once()->andReturn(new Collection([$row1, $row2]));

        $filter = new ListingFilterData(null, 'newest', 1, 10, [
            'category' => ['10']
        ]);

        // 🔑 FIX: Return the incoming query instance to satisfy the QueryBuilder typehint
        $this->filterBuilder->shouldReceive('forPages')
            ->once()
            ->withArgs(function ($query, ListingFilterData $passedFilter) {
                return $query instanceof QueryBuilder && empty($passedFilter->facets['category']);
            })
            ->andReturnUsing(fn($query) => $query);

        $result = $this->service->build($baseQuery, $filter, ['category']);

        $this->assertCount(1, $result);
        $group = $result[0];
        $this->assertInstanceOf(FacetGroupData::class, $group);
        $this->assertSame('category', $group->key);

        $this->assertCount(2, $group->options);
        $this->assertSame('10', $group->options[0]->value);
        $this->assertTrue($group->options[0]->selected);

        $this->assertSame('20', $group->options[1]->value);
        $this->assertFalse($group->options[1]->selected);
    }

    public function testBuildMaintainsIsolationAcrossMultipleFacets(): void
    {
        $baseQuery = Mockery::mock(QueryBuilder::class);

        $baseQuery->shouldReceive('join')->andReturnSelf();
        $baseQuery->shouldReceive('selectRaw')->andReturnSelf();
        $baseQuery->shouldReceive('groupBy')->andReturnSelf();
        $baseQuery->shouldReceive('groupByRaw')->andReturnSelf();
        $baseQuery->shouldReceive('whereNotNull')->andReturnSelf();
        $baseQuery->shouldReceive('orderByRaw')->andReturnSelf();
        $baseQuery->shouldReceive('get')->andReturn(new Collection([]));

        $filter = new ListingFilterData(null, 'newest', 1, 10, [
            'category' => ['10'],
            'tag' => ['50']
        ]);

        // 🔑 FIX: Return the incoming query instance to satisfy the QueryBuilder typehint
        $this->filterBuilder->shouldReceive('forPages')
            ->twice()
            ->withArgs(function ($query, ListingFilterData $f) {
                $hasCategory = isset($f->facets['category']);
                $hasTag = isset($f->facets['tag']);
                return $query instanceof QueryBuilder && ($hasCategory !== $hasTag);
            })
            ->andReturnUsing(fn($query) => $query);

        $result = $this->service->build($baseQuery, $filter, ['category', 'tag']);

        $this->assertCount(2, $result);
    }

    #[DataProvider('facetAggregateDataProvider')]
    public function testAggregateFluentChains(PublicDirectoryFacet $facet, string $method, array $args): void
    {
        $baseQuery = Mockery::mock(QueryBuilder::class);

        // Enforce the explicit specific match-arm condition we are validating
        $baseQuery->shouldReceive($method)->with(...$args)->atLeast()->once()->andReturnSelf();

        // General fluent chain processing stubs
        $baseQuery->shouldReceive('join')->andReturnSelf();
        $baseQuery->shouldReceive('selectRaw')->andReturnSelf();
        $baseQuery->shouldReceive('groupBy')->andReturnSelf();
        $baseQuery->shouldReceive('groupByRaw')->andReturnSelf();
        $baseQuery->shouldReceive('whereNotNull')->andReturnSelf();
        $baseQuery->shouldReceive('orderByRaw')->andReturnSelf();
        $baseQuery->shouldReceive('get')->andReturn(new Collection([]));

        $filter = new ListingFilterData(null, 'newest', 1, 10, []);
        $this->filterBuilder->shouldIgnoreMissing()->andReturnSelf();

        $this->service->build($baseQuery, $filter, [$facet->value]);

        $this->assertTrue(true);
    }

    public static function facetAggregateDataProvider(): array
    {
        return [
            'Category arm' => [PublicDirectoryFacet::Category, 'join', ['page_categories', 'pages.id', '=', 'page_categories.page_id']],
            'Tag arm' => [PublicDirectoryFacet::Tag, 'join', ['tags', 'page_tags.tag_id', '=', 'tags.id']],
            'Author arm' => [PublicDirectoryFacet::Author, 'join', ['authors', 'page_authors.author_id', '=', 'authors.id']],
            'Year arm' => [PublicDirectoryFacet::Year, 'whereNotNull', ['published_at']],
            'Month arm' => [PublicDirectoryFacet::Month, 'groupByRaw', ['MONTH(pages.published_at)']],
            'ArticleType arm' => [PublicDirectoryFacet::ArticleType, 'groupBy', ['page_type']],
        ];
    }
}