<?php

namespace App\Search\Configurations;

use App\Search\Filters\BooleanFilter;
use App\Search\Filters\InFilter;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class VariantSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    public function configure(): void
    {
        // Sorts only (no filters)
        $this->addSort(new SortSpecification('name', 'name'))
            ->addSort(new SortSpecification('sku', 'sku'))
            ->addSort(new SortSpecification('price', 'price'))
            ->addSort(new SortSpecification('date', 'created_at'));
        //->addSort(new SortSpecification('email', 'email'));

        $this->addFilter(new InFilter('product_ids', 'product_id'));
        $this->addFilter(new BooleanFilter('is_active', 'is_active'));

        // Searchable columns
        $this->addSearchableColumn('name')
            ->addSearchableColumn('sku');

        // Default sort
        $this->setDefaultSort('name', 'asc');
    }
}