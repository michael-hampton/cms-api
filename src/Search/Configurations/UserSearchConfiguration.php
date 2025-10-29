<?php

namespace App\Search\Configurations;

use App\Search\Filters\BooleanFilter;
use App\Search\Filters\EqualsFilter;
use App\Search\HasSite;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class UserSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    use HasSite;

    public function configure(): void
    {
        // Filters
        $this->addFilter(new EqualsFilter('role', 'role'))
            ->addFilter(new BooleanFilter('is_active', 'is_active'));

        // Sorts
        $this->addSort(new SortSpecification('name', 'name'))
            ->addSort(new SortSpecification('email', 'email'))
            ->addSort(new SortSpecification('created_at', 'created_at'))
            ->addSort(new SortSpecification('updated_at', 'updated_at'))
            ->addSort(new SortSpecification('role', 'role'));

        // Searchable columns
        $this->addSearchableColumn('name')
            ->addSearchableColumn('email');

        // Default sort
        $this->setDefaultSort('name', 'asc');
        $this->applySiteFilter();
    }
}