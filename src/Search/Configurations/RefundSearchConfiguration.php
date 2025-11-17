<?php

namespace App\Search\Configurations;

use App\Search\HasSite;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class RefundSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    use HasSite;

    public function configure(): void
    {
        self::applySiteFilter();

        // Sorts
        $this->addSort(new SortSpecification('created_at', 'created_at'))
            ->addSort(new SortSpecification('updated_at', 'updated_at'));

        // Default sort
        $this->setDefaultSort('created_at', 'desc');
    }
}