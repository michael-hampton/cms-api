<?php

namespace App\Tests\Unit\Search;

use App\Search\Filters\EqualsFilter;
use App\Search\PaginatedResult;
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

    public function testSearchAppliesFiltersCorrectly()
    {
        $config = new SearchConfiguration();
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

    public function testSearchAppliesSortingCorrectly()
    {
        $config = new SearchConfiguration();
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

    public function testSearchAppliesSearchQueryCorrectly()
    {
        $config = new SearchConfiguration();
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
        $config = new SearchConfiguration();
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
        $config = new SearchConfiguration();
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
}