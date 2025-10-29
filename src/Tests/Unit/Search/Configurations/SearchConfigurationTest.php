<?php

namespace App\Tests\Unit\Search\Configurations;

use App\Search\Configurations\AuthorSearchConfiguration;
use App\Search\Configurations\BrandSearchConfiguration;
use App\Search\Configurations\CategorySearchConfiguration;
use App\Search\Configurations\ImageSearchConfiguration;
use App\Search\Configurations\PageSearchConfiguration;
use App\Search\Configurations\ProductSearchConfiguration;
use App\Search\Configurations\TagSearchConfiguration;
use App\Search\Configurations\TerritorySearchConfiguration;
use App\Search\Configurations\UserSearchConfiguration;
use App\Search\Configurations\VoucherSearchConfiguration;
use App\Search\Filters\BooleanFilter;
use App\Search\Filters\CustomFilter;
use App\Search\Filters\DateRangeFilter;
use App\Search\Filters\EqualsFilter;
use App\Search\Filters\InFilter;
use App\Search\Filters\LikeFilter;
use App\Search\Filters\RangeFilter;
use App\Search\Filters\RelationshipExistsFilter;
use App\Search\Filters\RelationshipFilter;
use App\Search\RelationshipCountSort;
use App\Search\SortSpecification;
use PHPUnit\Framework\TestCase;

class SearchConfigurationTest extends TestCase
{
    public function testAuthorSearchConfigurationHasCorrectSetup()
    {
        $config = new AuthorSearchConfiguration();
        $config->configure();

        // Check sorts
        $sorts = $config->getSorts();
        $this->assertArrayHasKey('name', $sorts);
        $this->assertArrayHasKey('date', $sorts);
        $this->assertArrayHasKey('email', $sorts);
        $this->assertInstanceOf(SortSpecification::class, $sorts['name']);

        // Check searchable columns
        $searchableColumns = $config->getSearchableColumns();
        $this->assertContains('name', $searchableColumns);
        $this->assertContains('email', $searchableColumns);
        $this->assertContains('bio', $searchableColumns);

        // Check default sort
        $this->assertEquals('name', $config->getDefaultSort());
        $this->assertEquals('asc', $config->getDefaultSortDirection());
    }

    public function testBrandSearchConfigurationHasCorrectSetup()
    {
        $config = new BrandSearchConfiguration();
        $config->configure();

        // Check sorts
        $sorts = $config->getSorts();
        $this->assertArrayHasKey('name', $sorts);
        $this->assertArrayHasKey('created_at', $sorts);
        $this->assertArrayHasKey('updated_at', $sorts);
        $this->assertArrayHasKey('products', $sorts);
        $this->assertInstanceOf(RelationshipCountSort::class, $sorts['products']);

        // Check searchable columns
        $searchableColumns = $config->getSearchableColumns();
        $this->assertContains('name', $searchableColumns);
        $this->assertContains('description', $searchableColumns);

        // Check default sort
        $this->assertEquals('name', $config->getDefaultSort());
        $this->assertEquals('asc', $config->getDefaultSortDirection());

        // Check no filters
        $this->assertArrayHasKey('site_id', $config->getFilters());
    }

    public function testCategorySearchConfigurationHasCorrectSetup()
    {
        $config = new CategorySearchConfiguration();
        $config->configure();

        // Check filters
        $filters = $config->getFilters();
        $this->assertArrayHasKey('parent', $filters);
        $this->assertInstanceOf(EqualsFilter::class, $filters['parent']);

        // Check sorts including RelationshipCountSort
        $sorts = $config->getSorts();
        $this->assertArrayHasKey('name', $sorts);
        $this->assertArrayHasKey('date', $sorts);
        $this->assertArrayHasKey('usage', $sorts);
        $this->assertInstanceOf(RelationshipCountSort::class, $sorts['usage']);

        // Check searchable columns
        $searchableColumns = $config->getSearchableColumns();
        $this->assertContains('name', $searchableColumns);
        $this->assertContains('slug', $searchableColumns);

        // Check default sort
        $this->assertEquals('name', $config->getDefaultSort());
    }

    public function testImageSearchConfigurationHasCorrectFilters()
    {
        $config = new ImageSearchConfiguration();
        $config->configure();

        // Check filters
        $filters = $config->getFilters();
        $this->assertArrayHasKey('query', $filters);
        $this->assertArrayHasKey('mime_type', $filters);
        $this->assertArrayHasKey('category_id', $filters);
        $this->assertArrayHasKey('tags', $filters);

        $this->assertInstanceOf(LikeFilter::class, $filters['query']);
        $this->assertInstanceOf(EqualsFilter::class, $filters['mime_type']);
        $this->assertInstanceOf(CustomFilter::class, $filters['tags']);

        // Check searchable columns
        $searchableColumns = $config->getSearchableColumns();
        $this->assertContains('filename', $searchableColumns);
        $this->assertContains('alt_text', $searchableColumns);
        $this->assertContains('caption', $searchableColumns);
        $this->assertContains('name', $searchableColumns);
        $this->assertContains('credit', $searchableColumns);

        // Check default sort
        $this->assertEquals('created_at', $config->getDefaultSort());
        $this->assertEquals('desc', $config->getDefaultSortDirection());
    }

    public function testProductSearchConfigurationHasCorrectFilters()
    {
        $config = new ProductSearchConfiguration();
        $config->configure();

        // Check filters
        $filters = $config->getFilters();
        $this->assertArrayHasKey('categories', $filters);
        $this->assertArrayHasKey('brands', $filters);
        $this->assertArrayHasKey('merchant', $filters);
        $this->assertArrayHasKey('on_sale', $filters);

        $this->assertInstanceOf(InFilter::class, $filters['categories']);
        $this->assertInstanceOf(InFilter::class, $filters['brands']);
        $this->assertInstanceOf(CustomFilter::class, $filters['merchant']);
        $this->assertInstanceOf(CustomFilter::class, $filters['on_sale']);

        // Check sorts
        $sorts = $config->getSorts();
        $this->assertArrayHasKey('name', $sorts);
        $this->assertArrayHasKey('price', $sorts);
        $this->assertArrayHasKey('created_at', $sorts);

        // Check searchable columns
        $searchableColumns = $config->getSearchableColumns();
        $this->assertContains('name', $searchableColumns);
        $this->assertContains('description', $searchableColumns);
        $this->assertContains('brand_id', $searchableColumns);

        // Check default sort
        $this->assertEquals('created_at', $config->getDefaultSort());
        $this->assertEquals('desc', $config->getDefaultSortDirection());
    }

    public function testPageSearchConfigurationHasCorrectFilters()
    {
        $config = new PageSearchConfiguration();
        $config->configure();

        // Check filters
        $filters = $config->getFilters();
        $this->assertArrayHasKey('status', $filters);
        $this->assertArrayHasKey('content_type', $filters);
        $this->assertArrayHasKey('region_set_id', $filters);
        $this->assertArrayHasKey('territory_id', $filters);
        $this->assertArrayHasKey('template', $filters);
        $this->assertArrayHasKey('author', $filters);
        $this->assertArrayHasKey('category', $filters);
        $this->assertArrayHasKey('tag', $filters);

        $this->assertInstanceOf(InFilter::class, $filters['status']);
        $this->assertInstanceOf(RelationshipExistsFilter::class, $filters['content_type']);
        $this->assertInstanceOf(RelationshipFilter::class, $filters['region_set_id']);
        $this->assertInstanceOf(RelationshipFilter::class, $filters['author']);

        // Check searchable columns
        $searchableColumns = $config->getSearchableColumns();
        $this->assertContains('title', $searchableColumns);
        $this->assertContains('slug', $searchableColumns);

        // Check default sort
        $this->assertEquals('date_created', $config->getDefaultSort());
        $this->assertEquals('desc', $config->getDefaultSortDirection());
    }

    public function testTagSearchConfigurationHasCorrectSetup()
    {
        $config = new TagSearchConfiguration();
        $config->configure();

        // Check sorts
        $sorts = $config->getSorts();
        $this->assertArrayHasKey('name', $sorts);
        $this->assertArrayHasKey('date', $sorts);
        $this->assertArrayHasKey('usage', $sorts);

        // Check searchable columns
        $searchableColumns = $config->getSearchableColumns();
        $this->assertContains('name', $searchableColumns);
        $this->assertContains('slug', $searchableColumns);

        // Check default sort is by usage descending
        $this->assertEquals('usage', $config->getDefaultSort());
        $this->assertEquals('desc', $config->getDefaultSortDirection());

        // Check no filters
        $this->assertArrayHasKey('site_id', $config->getFilters());
    }

    public function testTerritorySearchConfigurationHasCorrectSetup()
    {
        $config = new TerritorySearchConfiguration();
        $config->configure();

        // Check filters
        $filters = $config->getFilters();
        $this->assertArrayHasKey('site_id', $filters);
        $this->assertArrayHasKey('is_active', $filters);
        $this->assertArrayHasKey('region_set_id', $filters);

        // Check sorts
        $sorts = $config->getSorts();
        $this->assertArrayHasKey('name', $sorts);
        $this->assertArrayHasKey('code', $sorts);
        $this->assertArrayHasKey('is_active', $sorts);
        $this->assertArrayHasKey('sort_order', $sorts);

        // Check searchable columns
        $searchableColumns = $config->getSearchableColumns();
        $this->assertContains('name', $searchableColumns);
        $this->assertContains('code', $searchableColumns);

        // Check default sort
        $this->assertEquals('sort_order', $config->getDefaultSort());
        $this->assertEquals('asc', $config->getDefaultSortDirection());
    }

    public function testUserSearchConfigurationHasCorrectSetup()
    {
        $config = new UserSearchConfiguration();
        $config->configure();

        // Check filters
        $filters = $config->getFilters();
        $this->assertArrayHasKey('role', $filters);
        $this->assertArrayHasKey('is_active', $filters);
        $this->assertInstanceOf(EqualsFilter::class, $filters['role']);
        $this->assertInstanceOf(BooleanFilter::class, $filters['is_active']);

        // Check sorts
        $sorts = $config->getSorts();
        $this->assertArrayHasKey('name', $sorts);
        $this->assertArrayHasKey('email', $sorts);
        $this->assertArrayHasKey('created_at', $sorts);
        $this->assertArrayHasKey('role', $sorts);

        // Check searchable columns
        $searchableColumns = $config->getSearchableColumns();
        $this->assertContains('name', $searchableColumns);
        $this->assertContains('email', $searchableColumns);

        // Check default sort
        $this->assertEquals('name', $config->getDefaultSort());
        $this->assertEquals('asc', $config->getDefaultSortDirection());
    }

    public function testVoucherSearchConfigurationHasCorrectSetup()
    {
        $config = new VoucherSearchConfiguration();
        $config->configure();

        // Check filters - multiple types
        $filters = $config->getFilters();
        $this->assertArrayHasKey('status', $filters);
        $this->assertArrayHasKey('type', $filters);
        $this->assertArrayHasKey('value', $filters);
        $this->assertArrayHasKey('starts_at', $filters);
        $this->assertArrayHasKey('expires_at', $filters);

        $this->assertInstanceOf(InFilter::class, $filters['status']);
        $this->assertInstanceOf(InFilter::class, $filters['type']);
        $this->assertInstanceOf(RangeFilter::class, $filters['value']);
        $this->assertInstanceOf(DateRangeFilter::class, $filters['starts_at']);
        $this->assertInstanceOf(DateRangeFilter::class, $filters['expires_at']);

        // Check sorts
        $sorts = $config->getSorts();
        $this->assertArrayHasKey('code', $sorts);
        $this->assertArrayHasKey('name', $sorts);
        $this->assertArrayHasKey('value', $sorts);
        $this->assertArrayHasKey('expires_at', $sorts);

        // Check searchable columns
        $searchableColumns = $config->getSearchableColumns();
        $this->assertContains('code', $searchableColumns);
        $this->assertContains('name', $searchableColumns);
        $this->assertContains('description', $searchableColumns);

        // Check default sort
        $this->assertEquals('date_created', $config->getDefaultSort());
        $this->assertEquals('desc', $config->getDefaultSortDirection());
    }

    public function testAllConfigurationsHaveDefaultSort()
    {
        $configurations = [
            new AuthorSearchConfiguration(),
            new BrandSearchConfiguration(),
            new CategorySearchConfiguration(),
            new ImageSearchConfiguration(),
            new PageSearchConfiguration(),
            new ProductSearchConfiguration(),
            new TagSearchConfiguration(),
            new TerritorySearchConfiguration(),
            new UserSearchConfiguration(),
            new VoucherSearchConfiguration(),
        ];

        foreach ($configurations as $config) {
            $config->configure();
            $this->assertNotNull(
                $config->getDefaultSort(),
                get_class($config) . ' should have a default sort'
            );
            $this->assertContains(
                $config->getDefaultSortDirection(),
                ['asc', 'desc'],
                get_class($config) . ' should have valid sort direction'
            );
        }
    }

    public function testAllConfigurationsHaveSearchableColumns()
    {
        $configurations = [
            new AuthorSearchConfiguration(),
            new BrandSearchConfiguration(),
            new CategorySearchConfiguration(),
            new ImageSearchConfiguration(),
            new PageSearchConfiguration(),
            new ProductSearchConfiguration(),
            new TagSearchConfiguration(),
            new TerritorySearchConfiguration(),
            new UserSearchConfiguration(),
            new VoucherSearchConfiguration(),
        ];

        foreach ($configurations as $config) {
            $config->configure();
            $this->assertNotEmpty(
                $config->getSearchableColumns(),
                get_class($config) . ' should have searchable columns'
            );
        }
    }

    public function testConfigurationsSortsAreProperlyTyped()
    {
        $config = new BrandSearchConfiguration();
        $config->configure();

        $sorts = $config->getSorts();
        foreach ($sorts as $key => $sort) {
            $this->assertTrue(
                $sort instanceof SortSpecification || $sort instanceof RelationshipCountSort,
                "Sort '{$key}' should be either SortSpecification or RelationshipCountSort"
            );
        }
    }

    public function testProductSearchConfigurationHasCustomFilters()
    {
        $config = new ProductSearchConfiguration();
        $config->configure();

        $filters = $config->getFilters();

        // Test merchant filter exists and is custom
        $this->assertArrayHasKey('merchant', $filters);
        $this->assertInstanceOf(CustomFilter::class, $filters['merchant']);

        // Test on_sale filter exists and is custom
        $this->assertArrayHasKey('on_sale', $filters);
        $this->assertInstanceOf(CustomFilter::class, $filters['on_sale']);
    }

    public function testPageSearchConfigurationHasRelationshipFilters()
    {
        $config = new PageSearchConfiguration();
        $config->configure();

        $filters = $config->getFilters();

        // Test RelationshipFilter instances
        $this->assertInstanceOf(RelationshipFilter::class, $filters['region_set_id']);
        $this->assertInstanceOf(RelationshipFilter::class, $filters['territory_id']);
        $this->assertInstanceOf(RelationshipFilter::class, $filters['author']);
        $this->assertInstanceOf(RelationshipFilter::class, $filters['category']);

        // Test RelationshipExistsFilter instances
        $this->assertInstanceOf(RelationshipExistsFilter::class, $filters['content_type']);
        $this->assertInstanceOf(RelationshipExistsFilter::class, $filters['featured']);
    }
}