<?php

namespace App\Search\Configurations;

use App\Search\Filters\BooleanFilter;
use App\Search\Filters\DateRangeFilter;
use App\Search\Filters\InFilter;
use App\Search\Filters\RelationshipFilter;
use App\Search\HasSite;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class PageGridSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    use HasSite;

    public function configure(): void
    {
        $this->addFilter(new InFilter('layout', 'layout'))
            ->addFilter(new BooleanFilter('is_active', 'is_active'))
            ->addFilter(new DateRangeFilter('created_at', 'created_at'))
            ->addFilter(new DateRangeFilter('updated_at', 'updated_at'))
            ->addFilter(new DateRangeFilter('start_date', 'start_date'))
            ->addFilter(new DateRangeFilter('end_date', 'end_date'))
            ->addFilter(new RelationshipFilter('territory_id', 'territories', 'id'));

        self::applySiteFilter($this);

        // Sorts
        $this->addSort(new SortSpecification('title', 'title'))
            ->addSort(new SortSpecification('created_at', 'created_at'))
            ->addSort(new SortSpecification('updated_at', 'updated_at'))
            ->addSort(new SortSpecification('is_active', 'is_active'));

        // Searchable columns
        $this->addSearchableColumn('title')
            ->addSearchableColumn('subtitle')
            ->addSearchableColumn('slug');

        // Default sort
        $this->setDefaultSort('created_at', 'desc');
    }
}