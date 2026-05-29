<?php

namespace App\Search\Configurations;

use App\Search\Filters\BooleanFilter;
use App\Search\Filters\DateRangeFilter;
use App\Search\Filters\EqualsFilter;
use App\Search\Filters\InFilter;
use App\Search\HasSite;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class SubscriptionVoucherSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    use HasSite;

    public function configure(): void
    {
        // Always scope to subscription vouchers — this configuration must never
        // return order-only vouchers. The filter is applied unconditionally so
        // callers cannot accidentally bypass it by omitting applies_to_subscriptions
        // from their criteria.
        $this->addFilter(new BooleanFilter('applies_to_subscriptions', 'applies_to_subscriptions'));

        $this->addFilter(new InFilter('status', 'status'))
            ->addFilter(new InFilter('subscription_discount_duration', 'subscription_discount_duration'))
            ->addFilter(new InFilter('discount_type', 'discount_type'))
            ->addFilter(new DateRangeFilter('starts_at', 'starts_at'))
            ->addFilter(new DateRangeFilter('expires_at', 'expires_at'))
            ->addFilter(new DateRangeFilter('created_at', 'created_at'));

        self::applySiteFilter();

        $this->addSort(new SortSpecification('code', 'code'))
            ->addSort(new SortSpecification('name', 'name'))
            ->addSort(new SortSpecification('status', 'status'))
            ->addSort(new SortSpecification('usage_count', 'usage_count'))
            ->addSort(new SortSpecification('date_created', 'created_at'))
            ->addSort(new SortSpecification('expires_at', 'expires_at'));

        $this->addSearchableColumn('code')
            ->addSearchableColumn('name')
            ->addSearchableColumn('description');

        $this->setDefaultSort('date_created', 'desc');
    }
}