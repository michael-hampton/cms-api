<?php

namespace App\Search\Configurations;

use App\Search\Filters\DateRangeFilter;
use App\Search\Filters\EqualsFilter;
use App\Search\Filters\InFilter;
use App\Search\Filters\RangeFilter;
use App\Search\HasSite;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class OrderSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    use HasSite;

    public function configure(): void
    {
        // Filters
        $this->addFilter(new InFilter('status', 'status'))
            ->addFilter(new InFilter('payment_status', 'payment_status'))
            ->addFilter(new EqualsFilter('user_id', 'user_id'))
            ->addFilter(new RangeFilter('total', 'total'))
            ->addFilter(new RangeFilter('subtotal', 'subtotal'))
            ->addFilter(new RangeFilter('tax', 'tax'))
            ->addFilter(new RangeFilter('shipping', 'shipping'))
            ->addFilter(new RangeFilter('discount', 'discount'))
            ->addFilter(new DateRangeFilter('created_at', 'created_at'))
            ->addFilter(new DateRangeFilter('updated_at', 'updated_at'))
            ->addFilter(new DateRangeFilter('completed_at', 'completed_at'))
            ->addFilter(new DateRangeFilter('cancelled_at', 'cancelled_at'));

        self::applySiteFilter();

        // Sorts
        $this->addSort(new SortSpecification('order_number', 'order_number'))
            ->addSort(new SortSpecification('status', 'status'))
            ->addSort(new SortSpecification('payment_status', 'payment_status'))
            ->addSort(new SortSpecification('total', 'total'))
            ->addSort(new SortSpecification('subtotal', 'subtotal'))
            ->addSort(new SortSpecification('created_at', 'created_at'))
            ->addSort(new SortSpecification('date_updated', 'updated_at'))
            ->addSort(new SortSpecification('date_completed', 'completed_at'))
            ->addSort(new SortSpecification('date_cancelled', 'cancelled_at'));

        // Searchable columns
        $this->addSearchableColumn('order_number')
            ->addSearchableColumn('status')
            ->addSearchableColumn('payment_status')
            ->addSearchableColumn('shipping_address')
            ->addSearchableColumn('billing_address')
            ->addSearchableColumn('customer_notes')
            ->addSearchableColumn('admin_notes');


        // Default sort
        $this->setDefaultSort('date_created', 'desc');
    }
}