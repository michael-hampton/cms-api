<?php

namespace App\Search\Configurations;

use App\Search\Filters\DateRangeFilter;
use App\Search\Filters\InFilter;
use App\Search\Filters\RangeFilter;
use App\Search\HasSite;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class VoucherSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    use HasSite;
    public function configure(): void
    {
        // Filters
        $this->addFilter(new InFilter('status', 'status'))
            ->addFilter(new InFilter('type', 'type'))
            ->addFilter(new RangeFilter('value', 'value'))
            ->addFilter(new RangeFilter('minimum_order_value', 'minimum_order_value'))
            ->addFilter(new RangeFilter('maximum_discount', 'maximum_discount'))
            ->addFilter(new RangeFilter('usage_limit', 'usage_limit'))
            ->addFilter(new RangeFilter('usage_count', 'usage_count'))
            ->addFilter(new DateRangeFilter('starts_at', 'starts_at'))
            ->addFilter(new DateRangeFilter('expires_at', 'expires_at'))
            ->addFilter(new DateRangeFilter('created_at', 'created_at'))
            ->addFilter(new DateRangeFilter('updated_at', 'updated_at'));

        self::applySiteFilter();

        // Sorts
        $this->addSort(new SortSpecification('code', 'code'))
            ->addSort(new SortSpecification('name', 'name'))
            ->addSort(new SortSpecification('value', 'value'))
            ->addSort(new SortSpecification('status', 'status'))
            ->addSort(new SortSpecification('usage_count', 'usage_count'))
            ->addSort(new SortSpecification('date_created', 'created_at'))
            ->addSort(new SortSpecification('date_updated', 'updated_at'))
            ->addSort(new SortSpecification('expires_at', 'expires_at'));

        // Searchable columns
        $this->addSearchableColumn('code')
            ->addSearchableColumn('name')
            ->addSearchableColumn('description');

        // Default sort
        $this->setDefaultSort('date_created', 'desc');
    }
}