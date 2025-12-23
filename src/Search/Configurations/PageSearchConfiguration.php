<?php

namespace App\Search\Configurations;

use App\Search\Filters\InFilter;
use App\Search\Filters\RelationshipExistsFilter;
use App\Search\Filters\RelationshipFilter;
use App\Search\HasSite;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class PageSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    use HasSite;

    public function configure(): void
    {
        // Filters
        $this->addFilter(new InFilter('status', 'status'))
            ->addFilter(new InFilter('page_type', 'page_type')) // Fixed: was 'template' -> 'page_type'
            ->addFilter(new RelationshipExistsFilter('content_type', 'metadata', 'content_type'))
            ->addFilter(new RelationshipFilter('region_set_id', 'regionSets', 'id'))
            ->addFilter(new RelationshipFilter('territory_id', 'territories', 'id'))
            ->addFilter(new InFilter('template', 'page_type'))
            ->addFilter(new RelationshipFilter('author', 'pageAuthors', 'author_id'))
            ->addFilter(new RelationshipExistsFilter('featured', 'metadata', 'featured'))
            ->addFilter(new RelationshipExistsFilter('visibility', 'metadata', 'visibility'))
            //->addFilter(new RelationshipFilter('category', 'categories', 'id'))
            ->addFilter(new RelationshipFilter('category_id', 'categories', 'id')) // Added for consistency
            ->addFilter(new RelationshipFilter('tag', 'tags', 'id'))
            ->addFilter(new RelationshipFilter('tag_id', 'tags', 'id')); // Added for consistency

        self::applySiteFilter();

        // Sorts
        $this->addSort(new SortSpecification('title', 'title'))
            ->addSort(new SortSpecification('date_created', 'created_at'))
            ->addSort(new SortSpecification('date_updated', 'updated_at'))
            ->addSort(new SortSpecification('status', 'status'));

        // Searchable columns
        $this->addSearchableColumn('title')
            ->addSearchableColumn('subtitle')
            ->addSearchableColumn('slug')
            ->addSearchableColumn('meta_description')
            ->addSearchableColumn('listing_synopsis')
            ->addSearchableColumn('listing_title');

        // Default sort
        $this->setDefaultSort('date_created', 'desc');
    }
}