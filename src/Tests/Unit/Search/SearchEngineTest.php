<?php

namespace App\Tests\Unit\Search;

use App\Search\Configurations\SearchConfigurationInterface;
use App\Search\Filters\EqualsFilter;
use App\Search\Filters\LikeFilter;
use App\Search\Filters\CustomFilter;
use App\Search\PaginatedResult;
use App\Search\RelationshipCountSort;
use App\Search\SearchConfiguration;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;
use App\Search\SortSpecification;
use Mockery;
use PHPUnit\Framework\TestCase;

class SearchEngineTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createTestConfiguration(): SearchConfiguration
    {
        return new class extends SearchConfiguration implements SearchConfigurationInterface {
            public function configure(): void
            {
                // Configuration will be added in individual tests
            }
        };
    }

    public function testSearchAppliesFiltersCorrectly()
    {
        $config = $this->createTestConfiguration();
        $config->addFilter(new EqualsFilter('status', 'status'));

        $engine = new SearchEngine($config);

        $query = Mockery::mock('QueryBuilder');
        $query->shouldReceive('where')
            ->with('status', 'published')
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('count')
            ->once()
            ->andReturn(10);
        $query->shouldReceive('limit')
            ->with(20)
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('offset')
            ->with(0)
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('get')
            ->once()
            ->andReturn(Mockery::mock(['toArray' => []]));

        $criteria = new SearchCriteria(
            filters: ['status' => 'published'],
            page: 1,
            perPage: 20
        );

        $result = $engine->search($query, $criteria);

        $this->assertInstanceOf(PaginatedResult::class, $result);
    }

    public function testSearchAppliesMultipleFiltersCorrectly()
    {
        $config = $this->createTestConfiguration();
        $config->addFilter(new EqualsFilter('status', 'status'))
            ->addFilter(new EqualsFilter('category_id', 'category_id'));

        $engine = new SearchEngine($config);

        $query = Mockery::mock('QueryBuilder');
        $query->shouldReceive('where')
            ->with('status', 'published')
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('where')
            ->with('category_id', '5')
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('count')
            ->once()
            ->andReturn(3);
        $query->shouldReceive('limit')
            ->with(20)
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('offset')
            ->with(0)
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('get')
            ->once()
            ->andReturn(Mockery::mock(['toArray' => []]));

        $criteria = new SearchCriteria(
            filters: ['status' => 'published', 'category_id' => '5'],
            page: 1,
            perPage: 20
        );

        $result = $engine->search($query, $criteria);

        $this->assertInstanceOf(PaginatedResult::class, $result);
    }

    public function testSearchAppliesLikeFilterCorrectly()
    {
        $config = $this->createTestConfiguration();
        $config->addFilter(new LikeFilter('query', 'filename'));

        $engine = new SearchEngine($config);

        $query = Mockery::mock('QueryBuilder');
        $query->shouldReceive('where')
            ->with('filename', 'LIKE', '%test%')
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('count')
            ->once()
            ->andReturn(5);
        $query->shouldReceive('limit')
            ->with(20)
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('offset')
            ->with(0)
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('get')
            ->once()
            ->andReturn(Mockery::mock(['toArray' => []]));

        $criteria = new SearchCriteria(
            filters: ['query' => 'test'],
            page: 1,
            perPage: 20
        );

        $result = $engine->search($query, $criteria);

        $this->assertInstanceOf(PaginatedResult::class, $result);
    }

    public function testSearchAppliesCustomFilterCorrectly()
    {
        $config = $this->createTestConfiguration();
        $config->addFilter(new CustomFilter('tags', function($query, $value) {
            $tagIds = explode(',', $value);
            $query->whereIn('tag_id', $tagIds);
            return $query;
        }));

        $engine = new SearchEngine($config);

        $query = Mockery::mock('QueryBuilder');
        $query->shouldReceive('whereIn')
            ->with('tag_id', ['1', '2', '3'])
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('count')
            ->once()
            ->andReturn(7);
        $query->shouldReceive('limit')
            ->with(20)
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('offset')
            ->with(0)
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('get')
            ->once()
            ->andReturn(Mockery::mock(['toArray' => []]));

        $criteria = new SearchCriteria(
            filters: ['tags' => '1,2,3'],
            page: 1,
            perPage: 20
        );

        $result = $engine->search($query, $criteria);

        $this->assertInstanceOf(PaginatedResult::class, $result);
    }

    public function testSearchAppliesSortingCorrectly()
    {
        $config = $this->createTestConfiguration();
        $config->addSort(new SortSpecification('name', 'name'));

        $engine = new SearchEngine($config);

        $query = Mockery::mock('QueryBuilder');
        $query->shouldReceive('orderBy')
            ->with('name', 'asc')
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('count')
            ->once()
            ->andReturn(5);
        $query->shouldReceive('limit')
            ->with(20)
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('offset')
            ->with(0)
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('get')
            ->once()
            ->andReturn(Mockery::mock(['toArray' => []]));

        $criteria = new SearchCriteria(
            sortBy: 'name',
            sortOrder: 'asc',
            page: 1,
            perPage: 20
        );

        $result = $engine->search($query, $criteria);

        $this->assertInstanceOf(PaginatedResult::class, $result);
    }

    public function testSearchAppliesDefaultSortWhenNoSortSpecified()
    {
        $config = $this->createTestConfiguration();
        $config->addSort(new SortSpecification('name', 'name'))
            ->setDefaultSort('name', 'desc');

        $engine = new SearchEngine($config);

        $query = Mockery::mock('QueryBuilder');
        $query->shouldReceive('orderBy')
            ->with('name', 'asc')
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('count')
            ->once()
            ->andReturn(5);
        $query->shouldReceive('limit')
            ->with(20)
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('offset')
            ->with(0)
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('get')
            ->once()
            ->andReturn(Mockery::mock(['toArray' => []]));

        $criteria = new SearchCriteria(
            page: 1,
            perPage: 20
        );

        $result = $engine->search($query, $criteria);

        $this->assertInstanceOf(PaginatedResult::class, $result);
    }

    public function testSearchIgnoresInvalidSortKey()
    {
        $config = $this->createTestConfiguration();
        $config->addSort(new SortSpecification('name', 'name'));

        $engine = new SearchEngine($config);

        $query = Mockery::mock('QueryBuilder');
        // Should not call orderBy with invalid sort key
        $query->shouldNotReceive('orderBy');
        $query->shouldReceive('count')
            ->once()
            ->andReturn(5);
        $query->shouldReceive('limit')
            ->with(20)
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('offset')
            ->with(0)
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('get')
            ->once()
            ->andReturn(Mockery::mock(['toArray' => []]));

        $criteria = new SearchCriteria(
            sortBy: 'invalid_sort_key',
            sortOrder: 'asc',
            page: 1,
            perPage: 20
        );

        $result = $engine->search($query, $criteria);

        $this->assertInstanceOf(PaginatedResult::class, $result);
    }

    public function testSearchAppliesSearchQueryCorrectly()
    {
        $config = $this->createTestConfiguration();
        $config->addSearchableColumn('title')
            ->addSearchableColumn('content');

        $engine = new SearchEngine($config);

        $query = Mockery::mock('QueryBuilder');
        $query->shouldReceive('where')
            ->once()
            ->andReturnUsing(function($callback) use ($query) {
                $innerQuery = Mockery::mock('QueryBuilder');
                $innerQuery->shouldReceive('orWhere')
                    ->with('title', 'LIKE', '%test%')
                    ->once()
                    ->andReturnSelf();
                $innerQuery->shouldReceive('orWhere')
                    ->with('content', 'LIKE', '%test%')
                    ->once()
                    ->andReturnSelf();
                $callback($innerQuery);
                return $query;
            });
        $query->shouldReceive('count')
            ->once()
            ->andReturn(3);
        $query->shouldReceive('limit')
            ->with(20)
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('offset')
            ->with(0)
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('get')
            ->once()
            ->andReturn(Mockery::mock(['toArray' => []]));

        $criteria = new SearchCriteria(
            searchQuery: 'test',
            page: 1,
            perPage: 20
        );

        $result = $engine->search($query, $criteria);

        $this->assertInstanceOf(PaginatedResult::class, $result);
    }

    public function testSearchAppliesPaginationCorrectly()
    {
        $config = $this->createTestConfiguration();
        $engine = new SearchEngine($config);

        $query = Mockery::mock('QueryBuilder');
        $query->shouldReceive('count')
            ->once()
            ->andReturn(100);
        $query->shouldReceive('limit')
            ->with(10)
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('offset')
            ->with(20) // Page 3, 10 per page = offset 20
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('get')
            ->once()
            ->andReturn(Mockery::mock(['toArray' => []]));

        $criteria = new SearchCriteria(
            page: 3,
            perPage: 10
        );

        $result = $engine->search($query, $criteria);

        $this->assertEquals(3, $result->getPage());
        $this->assertEquals(10, $result->getPerPage());
        $this->assertEquals(100, $result->getTotal());
        $this->assertEquals(10, $result->getTotalPages());
    }

    public function testSearchSkipsNullOrEmptyFilters()
    {
        $config = $this->createTestConfiguration();
        $config->addFilter(new EqualsFilter('status', 'status'));

        $engine = new SearchEngine($config);

        $query = Mockery::mock('QueryBuilder');
        $query->shouldReceive('count')
            ->once()
            ->andReturn(0);
        $query->shouldReceive('limit')
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('offset')
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('get')
            ->once()
            ->andReturn(Mockery::mock(['toArray' => []]));

        // Should not call where() because filter value is empty
        $query->shouldNotReceive('where');

        $criteria = new SearchCriteria(
            filters: ['status' => ''],
            page: 1,
            perPage: 20
        );

        $result = $engine->search($query, $criteria);
        $this->assertEquals(0, $result->getTotal());
    }

    public function testSearchIgnoresUnrecognizedFilters()
    {
        $config = $this->createTestConfiguration();
        $config->addFilter(new EqualsFilter('status', 'status'));

        $engine = new SearchEngine($config);

        $query = Mockery::mock('QueryBuilder');
        // Should not apply unrecognized filter
        $query->shouldNotReceive('where');
        $query->shouldReceive('count')
            ->once()
            ->andReturn(10);
        $query->shouldReceive('limit')
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('offset')
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('get')
            ->once()
            ->andReturn(Mockery::mock(['toArray' => []]));

        $criteria = new SearchCriteria(
            filters: ['unrecognized_filter' => 'value'],
            page: 1,
            perPage: 20
        );

        $result = $engine->search($query, $criteria);
        $this->assertInstanceOf(PaginatedResult::class, $result);
    }

    public function testSearchHandlesEmptyResults()
    {
        $config = $this->createTestConfiguration();
        $engine = new SearchEngine($config);

        $query = Mockery::mock('QueryBuilder');
        $query->shouldReceive('count')
            ->once()
            ->andReturn(0);
        $query->shouldReceive('limit')
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('offset')
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('get')
            ->once()
            ->andReturn(Mockery::mock(['toArray' => []]));

        $criteria = new SearchCriteria(
            page: 1,
            perPage: 20
        );

        $result = $engine->search($query, $criteria);

        $this->assertEquals(0, $result->getTotal());
        $this->assertEquals(0, $result->getTotalPages());
        $this->assertEmpty($result->getData());
    }

    public function testSearchCalculatesTotalPagesCorrectly()
    {
        $config = $this->createTestConfiguration();
        $engine = new SearchEngine($config);

        $query = Mockery::mock('QueryBuilder');
        $query->shouldReceive('count')
            ->once()
            ->andReturn(25);
        $query->shouldReceive('limit')
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('offset')
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('get')
            ->once()
            ->andReturn(Mockery::mock(['toArray' => []]));

        $criteria = new SearchCriteria(
            page: 1,
            perPage: 10
        );

        $result = $engine->search($query, $criteria);

        $this->assertEquals(25, $result->getTotal());
        $this->assertEquals(3, $result->getTotalPages()); // 25 items / 10 per page = 3 pages
    }

    public function testSearchCombinesFiltersSearchAndSort()
    {
        $config = $this->createTestConfiguration();
        $config->addFilter(new EqualsFilter('status', 'status'))
            ->addSort(new SortSpecification('name', 'name'))
            ->addSearchableColumn('title');

        $engine = new SearchEngine($config);

        $query = Mockery::mock('QueryBuilder');
        $query->shouldReceive('where')
            ->with('status', 'active')
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('where')
            ->once()
            ->andReturnUsing(function($callback) use ($query) {
                $innerQuery = Mockery::mock('QueryBuilder');
                $innerQuery->shouldReceive('orWhere')
                    ->with('title', 'LIKE', '%search%')
                    ->once()
                    ->andReturnSelf();
                $callback($innerQuery);
                return $query;
            });
        $query->shouldReceive('orderBy')
            ->with('name', 'asc')
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('count')
            ->once()
            ->andReturn(15);
        $query->shouldReceive('limit')
            ->with(20)
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('offset')
            ->with(0)
            ->once()
            ->andReturnSelf();
        $query->shouldReceive('get')
            ->once()
            ->andReturn(Mockery::mock(['toArray' => []]));

        $criteria = new SearchCriteria(
            filters: ['status' => 'active'],
            sortBy: 'name',
            sortOrder: 'asc',
            searchQuery: 'search',
            page: 1,
            perPage: 20
        );

        $result = $engine->search($query, $criteria);

        $this->assertInstanceOf(PaginatedResult::class, $result);
        $this->assertEquals(15, $result->getTotal());
    }
}