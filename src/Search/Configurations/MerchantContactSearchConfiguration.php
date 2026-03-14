<?php

namespace App\Search\Configurations;

use App\Search\Filters\EqualsFilter;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class MerchantContactSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    public function configure(): void
    {
        $this->addFilter(new EqualsFilter('merchant_id', 'merchant_id'))
            ->addFilter(new EqualsFilter('role', 'role'));

        $this->addSort(new SortSpecification('name', 'name'))
            ->addSort(new SortSpecification('email', 'email'))
            ->addSort(new SortSpecification('created_at', 'created_at'))
            ->addSort(new SortSpecification('updated_at', 'updated_at'));

        $this->addSearchableColumn('name')
            ->addSearchableColumn('email')
            ->addSearchableColumn('role');

        $this->setDefaultSort('name', 'asc');
    }
}