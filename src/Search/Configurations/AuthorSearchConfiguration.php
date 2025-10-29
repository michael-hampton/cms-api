<?php

namespace App\Search\Configurations;

use App\Search\HasSite;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class AuthorSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    use HasSite;

    public function configure(): void
    {
        // Sorts only (no filters)
        $this->addSort(new SortSpecification('name', 'name'))
            ->addSort(new SortSpecification('date', 'created_at'))
            ->addSort(new SortSpecification('email', 'email'));

        self::applySiteFilter();

        // Searchable columns
        $this->addSearchableColumn('name')
            ->addSearchableColumn('email')
            ->addSearchableColumn('bio');

        // Default sort
        $this->setDefaultSort('name', 'asc');
    }
}