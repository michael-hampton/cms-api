<?php

namespace App\Search\Configurations;

use App\Search\HasSite;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class EmailThemeSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    use HasSite;

    public function configure(): void
    {
        self::applySiteFilter();

        // Sorts
        $this->addSort(new SortSpecification('created_at', 'created_at'))
            ->addSort(new SortSpecification('updated_at', 'updated_at'));

        $this->addSearchableColumn('name')
            ->addSearchableColumn('description');

        // Default sort
        $this->setDefaultSort('name', 'asc');
    }
}