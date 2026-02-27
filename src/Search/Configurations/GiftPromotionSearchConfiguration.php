<?php

namespace App\Search\Configurations;

use App\Search\Filters\BooleanFilter;
use App\Search\Filters\DateRangeFilter;
use App\Search\Filters\EqualsFilter;
use App\Search\HasSite;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class GiftPromotionSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    use HasSite;

    public function configure(): void
    {
        $this->addFilter(new BooleanFilter('active', 'active'))
            ->addFilter(new EqualsFilter('gift_type', 'gift_type'))
            ->addFilter(new EqualsFilter('merchant_id', 'merchant_id'))
            ->addFilter(new EqualsFilter('gift_product_id', 'gift_product_id'))
            ->addFilter(new EqualsFilter('name', 'name'))
            ->addFilter(new EqualsFilter('gift_subscription_plan_id', 'gift_subscription_plan_id'))
            ->addFilter(new BooleanFilter('exclusive', 'exclusive'))
            ->addFilter(new DateRangeFilter('starts_at', 'starts_at'))
            ->addFilter(new DateRangeFilter('ends_at', 'ends_at'));

        self::applySiteFilter();

        $this->addSort(new SortSpecification('priority', 'priority'))
            ->addSort(new SortSpecification('created_at', 'created_at'))
            ->addSort(new SortSpecification('starts_at', 'starts_at'))
            ->addSort(new SortSpecification('ends_at', 'ends_at'))
            ->addSort(new SortSpecification('active', 'active'));

        $this->addSearchableColumn('gift_type')
            ->addSearchableColumn('name')
            ->addSearchableColumn('quantity_rule');

        $this->setDefaultSort('created_at', 'desc');
    }
}