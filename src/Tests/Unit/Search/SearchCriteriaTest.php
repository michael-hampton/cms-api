<?php

namespace App\Tests\Unit\Search;

use App\Search\SearchCriteria;
use PHPUnit\Framework\TestCase;

class SearchCriteriaTest extends TestCase
{
    public function testSearchCriteriaGettersReturnCorrectValues()
    {
        $filters = ['status' => 'published', 'featured' => true];
        $criteria = new SearchCriteria(
            filters: $filters,
            sortBy: 'created_at',
            sortOrder: 'desc',
            page: 2,
            perPage: 15,
            searchQuery: 'test'
        );

        $this->assertEquals($filters, $criteria->getFilters());
        $this->assertEquals('published', $criteria->getFilter('status'));
        $this->assertEquals('created_at', $criteria->getSortBy());
        $this->assertEquals('desc', $criteria->getSortOrder());
        $this->assertEquals(2, $criteria->getPage());
        $this->assertEquals(15, $criteria->getPerPage());
        $this->assertEquals('test', $criteria->getSearchQuery());
    }

    public function testSearchCriteriaCalculatesOffset()
    {
        $criteria = new SearchCriteria(
            page: 3,
            perPage: 20
        );

        $this->assertEquals(40, $criteria->getOffset());
    }

    public function testSearchCriteriaGetFilterReturnsDefault()
    {
        $criteria = new SearchCriteria();

        $this->assertNull($criteria->getFilter('nonexistent'));
        $this->assertEquals('default', $criteria->getFilter('nonexistent', 'default'));
    }
}