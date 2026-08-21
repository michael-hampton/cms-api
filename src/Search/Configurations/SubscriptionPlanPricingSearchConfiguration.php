<?php

namespace App\Search\Configurations;

use App\Search\Filters\BooleanFilter;
use App\Search\Filters\CustomFilter;
use App\Search\Filters\DateRangeFilter;
use App\Search\Filters\EqualsFilter;
use App\Search\Filters\RangeFilter;
use App\Search\HasSite;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class SubscriptionPlanPricingSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    use HasSite;

    public function configure(): void
    {
        $this->addFilter(new EqualsFilter('plan_id', 'plan_id'))
            ->addFilter(new BooleanFilter('is_active', 'is_active'))
            ->addFilter(new BooleanFilter('is_default', 'is_default'))
            ->addFilter(new EqualsFilter('currency', 'currency'))
            ->addFilter(new CustomFilter('status', function ($query, $value) {
                $isActive = $value === 'active';
                return $query->where('is_active', $isActive);
            }))
            ->addFilter(new RangeFilter('price', 'price'))
            ->addFilter(new RangeFilter('duration_months', 'duration_months'))
            ->addFilter(new DateRangeFilter('created_at', 'created_at'))
            ->addFilter(new DateRangeFilter('updated_at', 'updated_at'));

        self::applySiteFilter();

        $this->addSort(new SortSpecification('sort_order', 'sort_order'))
            ->addSort(new SortSpecification('price', 'price'))
            ->addSort(new SortSpecification('duration_months', 'duration_months'))
            ->addSort(new SortSpecification('created_at', 'created_at'))
            ->addSort(new SortSpecification('updated_at', 'updated_at'));

        $this->addSearchableColumn('label')
            ->addSearchableColumn('period_description');

        $this->setDefaultSort('sort_order', 'asc');
    }
}