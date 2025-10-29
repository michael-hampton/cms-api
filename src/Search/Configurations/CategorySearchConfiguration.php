<?php

namespace App\Search\Configurations;

use App\Search\Filters\EqualsFilter;
use App\Search\HasSite;
use App\Search\RelationshipCountSort;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class CategorySearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    use HasSite;

    public function configure(): void
    {
        // Filters
        $this->addFilter(new EqualsFilter('parent', 'parent_id'));

        self::applySiteFilter();

        // Sorts
        $this->addSort(new SortSpecification('name', 'name'))
            ->addSort(new SortSpecification('date', 'created_at'))
            ->addSort(new RelationshipCountSort('usage', 'pages'));

        // Searchable columns
        $this->addSearchableColumn('name')
            ->addSearchableColumn('slug');

        // Default sort
        $this->setDefaultSort('name', 'asc');
    }
}