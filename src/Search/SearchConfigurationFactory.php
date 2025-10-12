<?php

namespace App\Search;

use App\Search\Filters\BooleanFilter;
use App\Search\Filters\CustomFilter;
use App\Search\Filters\EqualsFilter;
use App\Search\Filters\InFilter;
use App\Search\Filters\LikeFilter;
use App\Search\Filters\RelationshipExistsFilter;
use App\Search\Filters\RelationshipFilter;

class SearchConfigurationFactory
{
    public static function createPageConfiguration(): SearchConfiguration
    {
        $config = new SearchConfiguration();

        // Filters
        $config->addFilter(new InFilter('status', 'status'))
            ->addFilter(new RelationshipExistsFilter('content_type', 'metadata', 'content_type'))
            ->addFilter(new InFilter('template', 'page_type'))
            ->addFilter(new InFilter('author', 'author_id'))
            ->addFilter(new RelationshipExistsFilter('featured', 'metadata', 'featured'))
            ->addFilter(new RelationshipExistsFilter('visibility', 'metadata', 'visibility'))
            ->addFilter(new RelationshipFilter('category', 'categories', 'id'))
            ->addFilter(new RelationshipFilter('tag', 'tags', 'id'));

        self::applyMandatoryFilters($config);

        // Sorts
        $config->addSort(new SortSpecification('title', 'title'))
            ->addSort(new SortSpecification('date_created', 'created_at'))
            ->addSort(new SortSpecification('date_updated', 'updated_at'))
            ->addSort(new SortSpecification('status', 'status'));

        // Searchable columns
        $config->addSearchableColumn('title')
            ->addSearchableColumn('slug');

        // Default sort
        $config->setDefaultSort('date_created', 'desc');

        return $config;
    }

    public static function createCategoryConfiguration(): SearchConfiguration
    {
        $config = new SearchConfiguration();

        // Filters
        $config->addFilter(new EqualsFilter('parent', 'parent_id'));

        self::applyMandatoryFilters($config);

        // Sorts
        $config->addSort(new SortSpecification('name', 'name'))
            ->addSort(new SortSpecification('date', 'created_at'))
            ->addSort(new RelationshipCountSort('usage', 'pages'));

        // Searchable columns
        $config->addSearchableColumn('name')
            ->addSearchableColumn('slug');

        // Default sort
        $config->setDefaultSort('name', 'asc');

        return $config;
    }

    public static function createTagConfiguration(): SearchConfiguration
    {
        $config = new SearchConfiguration();

        // Sorts only (no filters)
        $config->addSort(new SortSpecification('name', 'name'))
            ->addSort(new SortSpecification('date', 'created_at'))
            ->addSort(new SortSpecification('usage', 'usage_count'));

        self::applyMandatoryFilters($config);

        // Searchable columns
        $config->addSearchableColumn('name')
            ->addSearchableColumn('slug');

        // Default sort
        $config->setDefaultSort('usage', 'desc');

        return $config;
    }

    public static function createAuthorConfiguration(): SearchConfiguration
    {
        $config = new SearchConfiguration();

        // Sorts only (no filters)
        $config->addSort(new SortSpecification('name', 'name'))
            ->addSort(new SortSpecification('date', 'created_at'))
            ->addSort(new SortSpecification('email', 'email'));

        self::applyMandatoryFilters($config);

        // Searchable columns
        $config->addSearchableColumn('name')
            ->addSearchableColumn('email')
            ->addSearchableColumn('bio');

        // Default sort
        $config->setDefaultSort('name', 'asc');

        return $config;
    }

    public static function createImageConfiguration(): SearchConfiguration
    {
        $config = new SearchConfiguration();

        // Filters
        $config->addFilter(new LikeFilter('query', 'filename'))
            ->addFilter(new EqualsFilter('mime_type', 'mime_type'))
            ->addFilter(new EqualsFilter('category_id', 'category_id'));

        self::applyMandatoryFilters($config);

        // Sorts
        $config->addSort(new SortSpecification('created_at', 'created_at'))
            ->addSort(new SortSpecification('original_name', 'original_name'))
            ->addSort(new SortSpecification('file_size', 'file_size'))
            ->addSort(new SortSpecification('updated_at', 'updated_at'));

        // Searchable columns
        $config->addSearchableColumn('filename')
            ->addSearchableColumn('alt_text')
            ->addSearchableColumn('caption');

        // Default sort
        $config->setDefaultSort('created_at', 'desc');

        return $config;
    }

    public static function createProductConfiguration(): SearchConfiguration
    {
        $config = new SearchConfiguration();

        // Filters
        $config->addFilter(new EqualsFilter('category_id', 'category_id'))
            ->addFilter(new LikeFilter('brand', 'brand_id'))
            ->addFilter(new CustomFilter('on_sale', function($query, $value) {
                if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                    $query->whereNotNull('sale_price')
                        ->whereColumn('sale_price', '<', 'price');
                }
                return $query;
            }));

        self::applyMandatoryFilters($config);

        // Sorts
        $config->addSort(new SortSpecification('name', 'name'))
            ->addSort(new SortSpecification('price', 'price'))
            ->addSort(new SortSpecification('created_at', 'created_at'))
            ->addSort(new SortSpecification('updated_at', 'updated_at'));

        // Searchable columns
        $config->addSearchableColumn('name')
            ->addSearchableColumn('description')
            ->addSearchableColumn('brand_id');

        // Default sort
        $config->setDefaultSort('created_at', 'desc');

        return $config;
    }

    public static function createUserConfiguration(): SearchConfiguration
    {
        $config = new SearchConfiguration();

        // Filters
        $config->addFilter(new EqualsFilter('role', 'role'))
            ->addFilter(new BooleanFilter('is_active', 'is_active'));

        // Sorts
        $config->addSort(new SortSpecification('name', 'name'))
            ->addSort(new SortSpecification('email', 'email'))
            ->addSort(new SortSpecification('created_at', 'created_at'))
            ->addSort(new SortSpecification('updated_at', 'updated_at'))
            ->addSort(new SortSpecification('role', 'role'));

        // Searchable columns
        $config->addSearchableColumn('name')
            ->addSearchableColumn('email');

        // Default sort
        $config->setDefaultSort('name', 'asc');

        return $config;
    }

    public static function createBrandConfiguration(): SearchConfiguration
    {
        $config = new SearchConfiguration();

        // No filters needed for basic brand search

        // Sorts
        $config->addSort(new SortSpecification('name', 'name'))
            ->addSort(new SortSpecification('created_at', 'created_at'))
            ->addSort(new SortSpecification('updated_at', 'updated_at'))
            ->addSort(new RelationshipCountSort('products', 'products'));

        self::applyMandatoryFilters($config);

        // Searchable columns
        $config->addSearchableColumn('name')
            ->addSearchableColumn('description');

        // Default sort
        $config->setDefaultSort('name', 'asc');

        return $config;
    }

    private static function applyMandatoryFilters(SearchConfiguration $config): void
    {
        // Example: add a filter that applies to all configs
        $config->addFilter(new EqualsFilter('site_id', 'site_id'));
    }
}