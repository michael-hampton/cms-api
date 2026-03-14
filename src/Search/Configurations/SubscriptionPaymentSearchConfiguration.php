<?php

namespace App\Search\Configurations;

use App\Search\Filters\DateRangeFilter;
use App\Search\Filters\EqualsFilter;
use App\Search\Filters\InFilter;
use App\Search\Filters\RangeFilter;
use App\Search\HasSite;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class SubscriptionPaymentSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    use HasSite;

    public function configure(): void
    {
        $this->addFilter(new InFilter('status', 'status'))
            ->addFilter(new EqualsFilter('subscription_id', 'subscription_id'))
            ->addFilter(new EqualsFilter('order_id', 'order_id'))
            ->addFilter(new EqualsFilter('payment_method', 'payment_method'))
            ->addFilter(new InFilter('currency', 'currency'))
            ->addFilter(new RangeFilter('amount', 'amount'))
            ->addFilter(new DateRangeFilter('paid_at', 'paid_at'))
            ->addFilter(new DateRangeFilter('created_at', 'created_at'));

        self::applySiteFilter();

        $this->addSort(new SortSpecification('paid_at', 'paid_at'))
            ->addSort(new SortSpecification('created_at', 'created_at'))
            ->addSort(new SortSpecification('amount', 'amount'))
            ->addSort(new SortSpecification('status', 'status'));

        $this->addSearchableColumn('transaction_id')
            ->addSearchableColumn('payment_intent_id');

        $this->setDefaultSort('created_at', 'desc');
    }
}