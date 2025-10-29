<?php

namespace App\Search\Configurations;

use App\Search\HasSite;
use App\Search\RelationshipCountSort;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class BrandSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    use HasSite;

    public function configure(): void
    {
        // No filters needed for basic brand search

        // Sorts
        $this->addSort(new SortSpecification('name', 'name'))
            ->addSort(new SortSpecification('created_at', 'created_at'))
            ->addSort(new SortSpecification('updated_at', 'updated_at'))
            ->addSort(new RelationshipCountSort('products', 'products'));

        self::applySiteFilter();

        // Searchable columns
        $this->addSearchableColumn('name')
            ->addSearchableColumn('description');

        // Default sort
        $this->setDefaultSort('name', 'asc');
    }
}