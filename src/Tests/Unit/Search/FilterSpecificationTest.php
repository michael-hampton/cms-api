<?php

namespace App\Tests\Unit\Search;

use App\Search\Filters\BooleanFilter;
use App\Search\Filters\EqualsFilter;
use App\Search\Filters\InFilter;
use App\Search\Filters\LikeFilter;
use Mockery;
use PHPUnit\Framework\TestCase;

class FilterSpecificationTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testEqualsFilterAppliesCorrectly()
    {
        $filter = new EqualsFilter('status', 'status');

        $query = Mockery::mock('QueryBuilder');
        $query->shouldReceive('where')
            ->with('status', 'published')
            ->once()
            ->andReturnSelf();

        $result = $filter->apply($query, 'published');

        $this->assertSame($query, $result);
        $this->assertEquals('status', $filter->getFilterKey());
    }

    public function testLikeFilterAppliesCorrectly()
    {
        $filter = new LikeFilter('title', 'title');

        $query = Mockery::mock('QueryBuilder');
        $query->shouldReceive('where')
            ->with('title', 'LIKE', '%test%')
            ->once()
            ->andReturnSelf();

        $filter->apply($query, 'test');
        $this->assertEquals('title', $filter->getFilterKey());
    }

    public function testBooleanFilterAppliesCorrectly()
    {
        $filter = new BooleanFilter('featured', 'is_featured');

        $query = Mockery::mock('QueryBuilder');
        $query->shouldReceive('where')
            ->with('is_featured', 1)
            ->once()
            ->andReturnSelf();

        $filter->apply($query, 'true');
        $this->assertEquals('featured', $filter->getFilterKey());
    }

    public function testBooleanFilterHandlesFalse()
    {
        $filter = new BooleanFilter('featured', 'is_featured');

        $query = Mockery::mock('QueryBuilder');
        $query->shouldReceive('where')
            ->with('is_featured', 0)
            ->once()
            ->andReturnSelf();

        $filter->apply($query, 'false');
        $this->assertEquals('featured', $filter->getFilterKey());
    }

    public function testInFilterAppliesWithArray()
    {
        $filter = new InFilter('status', 'status');

        $query = Mockery::mock('QueryBuilder');
        $query->shouldReceive('whereIn')
            ->with('status', ['published', 'draft'])
            ->once()
            ->andReturnSelf();

        $filter->apply($query, ['published', 'draft']);
        $this->assertEquals('status', $filter->getFilterKey());
    }

    public function testInFilterConvertsScalarToArray()
    {
        $filter = new InFilter('status', 'status');

        $query = Mockery::mock('QueryBuilder');
        $query->shouldReceive('whereIn')
            ->with('status', ['published'])
            ->once()
            ->andReturnSelf();

        $filter->apply($query, 'published');
        $this->assertEquals('status', $filter->getFilterKey());
    }
}