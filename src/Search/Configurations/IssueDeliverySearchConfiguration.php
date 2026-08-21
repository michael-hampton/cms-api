<?php

namespace App\Search\Configurations;

use App\Search\Filters\DateRangeFilter;
use App\Search\Filters\EqualsFilter;
use App\Search\HasSite;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class IssueDeliverySearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    use HasSite;

    public function configure(): void
    {

        $this->addFilter(new EqualsFilter('subscription_id', 'subscription_id'))
            ->addFilter(new EqualsFilter('status', 'status'))
            ->addFilter(new EqualsFilter('product_id', 'product_id'))
            ->addFilter(new EqualsFilter('promotion_id', 'promotion_id'))
            ->addFilter(new DateRangeFilter('from_date', 'on_sale_date'))
            ->addFilter(new DateRangeFilter('to_date', 'on_sale_date'))
            ->addFilter(new EqualsFilter('skip_reason', 'skip_reason'))
            ->addFilter(new DateRangeFilter('on_sale_date', 'on_sale_date'))
            ->addFilter(new DateRangeFilter('skipped_at', 'skipped_at'))
            ->addFilter(new DateRangeFilter('created_at', 'created_at'))
            ->addFilter(new DateRangeFilter('updated_at', 'updated_at'));

        self::applySiteFilter();

        $this->addSort(new SortSpecification('on_sale_date', 'on_sale_date'))
            ->addSort(new SortSpecification('created_at', 'created_at'))
            ->addSort(new SortSpecification('updated_at', 'updated_at'))
            ->addSort(new SortSpecification('status', 'status'))
            ->addSort(new SortSpecification('issue_number', 'issue_number'))
            ->addSort(new SortSpecification('skipped_at', 'skipped_at'));

        $this->addSearchableColumn('issue_title')
            ->addSearchableColumn('issue_number')
            ->addSearchableColumn('issue_code');

        $this->setDefaultSort('on_sale_date', 'desc');
    }
}