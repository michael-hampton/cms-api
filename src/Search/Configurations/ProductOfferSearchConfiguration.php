<?php

namespace App\Search\Configurations;

use App\Search\Filters\BooleanFilter;
use App\Search\Filters\DateRangeFilter;
use App\Search\Filters\EqualsFilter;
use App\Search\Filters\InFilter;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class ProductOfferSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    public function configure(): void
    {
        $this->addFilter(new EqualsFilter('product_id', 'product_id'))
            ->addFilter(new EqualsFilter('merchant_id', 'merchant_id'))
            ->addFilter(new BooleanFilter('is_active', 'is_active'))
            ->addFilter(new InFilter('status', 'status'))
            ->addFilter(new DateRangeFilter('start_date', 'start_date'))
            ->addFilter(new DateRangeFilter('end_date', 'end_date'));

        $this->addSort(new SortSpecification('start_date', 'start_date'))
            ->addSort(new SortSpecification('end_date', 'end_date'))
            ->addSort(new SortSpecification('sale_price', 'sale_price'))
            ->addSort(new SortSpecification('created_at', 'created_at'));

        $this->setDefaultSort('start_date', 'desc');
    }
}