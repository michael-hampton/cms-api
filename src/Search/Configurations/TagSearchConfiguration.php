<?php

namespace App\Search\Configurations;

use App\Search\HasSite;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class TagSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    use HasSite;
    public function configure(): void
    {
        // Sorts only (no filters)
        $this->addSort(new SortSpecification('name', 'name'))
            ->addSort(new SortSpecification('date', 'created_at'))
            ->addSort(new SortSpecification('created_at', 'created_at'))
            ->addSort(new SortSpecification('usage', 'usage_count'));

        self::applySiteFilter();

        // Searchable columns
        $this->addSearchableColumn('name')
            ->addSearchableColumn('slug');

        // Default sort
        $this->setDefaultSort('usage', 'desc');
    }
}