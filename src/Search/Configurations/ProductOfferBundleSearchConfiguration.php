<?php

namespace App\Search\Configurations;

use App\Search\Filters\BooleanFilter;
use App\Search\Filters\DateRangeFilter;
use App\Search\Filters\InFilter;
use App\Search\HasSite;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class ProductOfferBundleSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    use HasSite;

    public function configure(): void
    {
        $this->addFilter(new InFilter('status', 'status'))
            ->addFilter(new BooleanFilter('is_active', 'is_active'))
            ->addFilter(new DateRangeFilter('start_date', 'start_date'))
            ->addFilter(new DateRangeFilter('end_date', 'end_date'));

        $this->addSort(new SortSpecification('name', 'name'))
            ->addSort(new SortSpecification('start_date', 'start_date'))
            ->addSort(new SortSpecification('end_date', 'end_date'))
            ->addSort(new SortSpecification('bundle_price', 'bundle_price'))
            ->addSort(new SortSpecification('discount_percentage', 'discount_percentage'))
            ->addSort(new SortSpecification('created_at', 'created_at'));

        $this->addSearchableColumn('name')
            ->addSearchableColumn('description')
            ->addSearchableColumn('slug');

        $this->setDefaultSort('created_at', 'desc');
    }
}