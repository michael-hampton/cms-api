<?php

namespace App\Search\Configurations;

use App\Search\Filters\BooleanFilter;
use App\Search\Filters\DateRangeFilter;
use App\Search\Filters\EqualsFilter;
use App\Search\Filters\InFilter;
use App\Search\HasSite;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class CampaignSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    use HasSite;

    public function configure(): void
    {
        $this->addFilter(new InFilter('status', 'status'))
            ->addFilter(new EqualsFilter('type', 'type'))
            ->addFilter(new BooleanFilter('is_active', 'is_active'))
            ->addFilter(new DateRangeFilter('starts_at', 'starts_at'))
            ->addFilter(new DateRangeFilter('ends_at', 'ends_at'));

        self::applySiteFilter();

        $this->addSort(new SortSpecification('name', 'name'))
            ->addSort(new SortSpecification('created_at', 'created_at'))
            ->addSort(new SortSpecification('updated_at', 'updated_at'))
            ->addSort(new SortSpecification('starts_at', 'starts_at'))
            ->addSort(new SortSpecification('ends_at', 'ends_at'))
            ->addSort(new SortSpecification('status', 'status'));

        $this->addSearchableColumn('name')
            ->addSearchableColumn('description');

        $this->setDefaultSort('created_at', 'desc');
    }
}