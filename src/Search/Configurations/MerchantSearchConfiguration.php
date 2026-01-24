<?php

namespace App\Search\Configurations;

use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class MerchantSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    public function configure(): void
    {
        // Sorts only (no filters)
        $this->addSort(new SortSpecification('name', 'name'))
            ->addSort(new SortSpecification('date', 'created_at'));
        //->addSort(new SortSpecification('email', 'email'));

        // Searchable columns
        $this->addSearchableColumn('name');
        //->addSearchableColumn('email');

        // Default sort
        $this->setDefaultSort('name', 'asc');
    }
}