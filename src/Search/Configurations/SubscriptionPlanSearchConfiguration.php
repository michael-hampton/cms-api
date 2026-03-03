<?php

namespace App\Search\Configurations;

use App\Search\Filters\BooleanFilter;
use App\Search\Filters\CustomFilter;
use App\Search\Filters\InFilter;
use App\Search\HasSite;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class SubscriptionPlanSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    use HasSite;

    public function configure(): void
    {
        $this->addFilter(new BooleanFilter('is_active', 'is_active'))
            ->addFilter(new BooleanFilter('is_featured', 'is_featured'))
            ->addFilter(new InFilter('billing_period', 'billing_period'))
            ->addFilter(new CustomFilter('plan_type', function ($query, $value) {
                // Value can be comma-separated tag IDs

                if (!empty($value) && $value === 'digital') {
                    $query->whereNotNull('digital_download_url');
                } else if ($value === 'print') {
                    $query->where('print_shipping_required', 1);
                } else {
                    $query->where('plan_type', $value);
                }
                return $query;
            }));

        self::applySiteFilter();

        $this->addSort(new SortSpecification('name', 'name'))
            ->addSort(new SortSpecification('price', 'price'))
            ->addSort(new SortSpecification('sort_order', 'sort_order'))
            ->addSort(new SortSpecification('created_at', 'created_at'))
            ->addSort(new SortSpecification('updated_at', 'updated_at'));

        $this->addSearchableColumn('name')
            ->addSearchableColumn('description');

        $this->setDefaultSort('sort_order', 'asc');
    }
}