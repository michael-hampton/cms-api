<?php
declare(strict_types=1);

namespace App\Tests\Unit\Services\PublicContent\Directory\Listing;

use App\Data\PublicContent\PublicDirectoryListingConfigData;
use App\Factories\PublicContent\ListingFilterDataFactory;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ListingFilterDataFactoryTest extends TestCase
{
    private ListingFilterDataFactory $factory;
    private PublicDirectoryListingConfigData $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new ListingFilterDataFactory();

        // 1. Instantiate the genuine class without invoking its constructor
        $reflection = new ReflectionClass(PublicDirectoryListingConfigData::class);
        $this->config = $reflection->newInstanceWithoutConstructor();

        // 2. Safely hydrate the uninitialized readonly properties using Reflection
        $properties = [
            'perPageOptions' => [10, 20, 50],
            'defaultPerPage' => 10,
            'maxPerPage' => 50,
        ];

        foreach ($properties as $name => $value) {
            if ($reflection->hasProperty($name)) {
                $property = $reflection->getProperty($name);
                $property->setValue($this->config, $value);
            }
        }
    }

    public function testFromQueryParamsWithEmptyInputUsesDefaults(): void
    {
        $result = $this->factory->fromQueryParams([], $this->config, 'default_sort_string');

        $this->assertNull($result->search);
        $this->assertSame('default_sort_string', $result->sort);
        $this->assertSame(1, $result->page);
        $this->assertSame(10, $result->perPage);
        $this->assertEmpty($result->facets);
    }

    public function testFromQueryParamsNormalizesAndTrimsSearchQuery(): void
    {
        $query = ['q' => '   essential php testing   '];
        $result = $this->factory->fromQueryParams($query, $this->config, 'asc');
        $this->assertSame('essential php testing', $result->search);

        $queryEmpty = ['q' => '    '];
        $resultEmpty = $this->factory->fromQueryParams($queryEmpty, $this->config, 'asc');
        $this->assertNull($resultEmpty->search);

        $queryNonString = ['q' => ['not', 'a', 'string']];
        $resultNonString = $this->factory->fromQueryParams($queryNonString, $this->config, 'asc');
        $this->assertNull($resultNonString->search);
    }

    public function testFromQueryParamsValidatesAndClampsPerPage(): void
    {
        $queryValid = ['per_page' => 20];
        $resultValid = $this->factory->fromQueryParams($queryValid, $this->config, 'asc');
        $this->assertSame(20, $resultValid->perPage);

        $queryInvalid = ['per_page' => 15];
        $resultInvalid = $this->factory->fromQueryParams($queryInvalid, $this->config, 'asc');
        $this->assertSame(10, $resultInvalid->perPage);

        $queryAboveMax = ['per_page' => 9999];
        $resultAboveMax = $this->factory->fromQueryParams($queryAboveMax, $this->config, 'asc');
        $this->assertSame(10, $resultAboveMax->perPage);
    }

    public function testFromQueryParamsEnforcesPositiveIntegerForPage(): void
    {
        $queryZero = ['page' => 0];
        $resultZero = $this->factory->fromQueryParams($queryZero, $this->config, 'asc');
        $this->assertSame(1, $resultZero->page);

        $queryNegative = ['page' => -15];
        $resultNegative = $this->factory->fromQueryParams($queryNegative, $this->config, 'asc');
        $this->assertSame(1, $resultNegative->page);

        $queryValid = ['page' => 4];
        $resultValid = $this->factory->fromQueryParams($queryValid, $this->config, 'asc');
        $this->assertSame(4, $resultValid->page);

        $queryMalformed = ['page' => 'not-an-int'];
        $resultMalformed = $this->factory->fromQueryParams($queryMalformed, $this->config, 'asc');
        $this->assertSame(1, $resultMalformed->page);
    }

    public function testFromQueryParamsParsesFacetsArrayCorrectly(): void
    {
        $query = [
            'facet' => [
                'category' => ['1', '2', ''],
                'tag' => ['99', '100'],
                'invalid_nested' => 'not-an-array',
                123 => ['should-be-skipped-due-to-numeric-key']
            ]
        ];

        $result = $this->factory->fromQueryParams($query, $this->config, 'asc');

        $this->assertArrayHasKey('category', $result->facets);
        $this->assertSame(['1', '2'], $result->facets['category']);

        $this->assertArrayHasKey('tag', $result->facets);
        $this->assertSame(['99', '100'], $result->facets['tag']);

        $this->assertArrayNotHasKey('invalid_nested', $result->facets);
        $this->assertArrayNotHasKey(123, $result->facets);
    }
}