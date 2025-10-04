<?php

namespace App\Tests\Unit\Search;

use App\Search\PaginatedResult;
use PHPUnit\Framework\TestCase;

class PaginatedResultTest extends TestCase
{
    public function testPaginatedResultCalculatesTotalPages()
    {
        $result = new PaginatedResult(
            data: [],
            total: 100,
            page: 1,
            perPage: 20
        );

        $this->assertEquals(5, $result->getTotalPages());
    }

    public function testPaginatedResultCalculatesHasMore()
    {
        $result = new PaginatedResult(
            data: [],
            total: 100,
            page: 3,
            perPage: 20
        );

        $this->assertTrue($result->hasMore());

        $lastPageResult = new PaginatedResult(
            data: [],
            total: 100,
            page: 5,
            perPage: 20
        );

        $this->assertFalse($lastPageResult->hasMore());
    }

    public function testPaginatedResultToArray()
    {
        $data = [['id' => 1], ['id' => 2]];
        $result = new PaginatedResult(
            data: $data,
            total: 50,
            page: 2,
            perPage: 10
        );

        $array = $result->toArray();

        $this->assertArrayHasKey('data', $array);
        $this->assertArrayHasKey('pagination', $array);
        $this->assertEquals($data, $array['data']);
        $this->assertEquals(50, $array['pagination']['total']);
        $this->assertEquals(2, $array['pagination']['current_page']);
        $this->assertEquals(10, $array['pagination']['per_page']);
        $this->assertEquals(5, $array['pagination']['total_pages']);
        $this->assertTrue($array['pagination']['has_more']);
    }
}