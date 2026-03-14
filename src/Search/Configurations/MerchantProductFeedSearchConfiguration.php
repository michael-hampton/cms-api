<?php

namespace App\Search\Configurations;

use App\Search\Filters\BooleanFilter;
use App\Search\Filters\EqualsFilter;
use App\Search\Filters\InFilter;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class MerchantProductFeedSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    public function configure(): void
    {
        $this->addFilter(new EqualsFilter('merchant_id', 'merchant_id'))
            ->addFilter(new BooleanFilter('is_active', 'is_active'))
            ->addFilter(new InFilter('status', 'status'))
            ->addFilter(new EqualsFilter('feed_type', 'feed_type'))
            ->addFilter(new EqualsFilter('fetch_frequency', 'fetch_frequency'));

        $this->addSort(new SortSpecification('created_at', 'created_at'))
            ->addSort(new SortSpecification('updated_at', 'updated_at'))
            ->addSort(new SortSpecification('last_fetched_at', 'last_fetched_at'))
            ->addSort(new SortSpecification('feed_type', 'feed_type'))
            ->addSort(new SortSpecification('status', 'status'));

        $this->addSearchableColumn('feed_url')
            ->addSearchableColumn('feed_type');

        $this->setDefaultSort('created_at', 'desc');
    }
}