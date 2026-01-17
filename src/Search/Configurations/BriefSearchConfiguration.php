<?php

namespace App\Search\Configurations;

use App\Search\Filters\EqualsFilter;
use App\Search\HasSite;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class BriefSearchConfiguration extends SearchConfiguration
{
    use HasSite;

    public function configure(): void
    {
        $this->addFilter(new EqualsFilter('status', 'status'))
            ->addFilter(new EqualsFilter('owner_id', 'owner_id'))
            ->addFilter(new EqualsFilter('category_id', 'category_id'));

        // Sorts only (no filters)
        $this->addSort(new SortSpecification('title', 'title'))
            ->addSort(new SortSpecification('date', 'created_at'))
            ->addSort(new SortSpecification('status', 'status'));

        self::applySiteFilter();

        // Searchable columns
        $this->addSearchableColumn('title')
            ->addSearchableColumn('status');

        // Default sort
        $this->setDefaultSort('title', 'asc');
    }
}