<?php

namespace App\Search\Configurations;

use App\Search\Filters\BooleanFilter;
use App\Search\Filters\EqualsFilter;
use App\Search\Filters\InFilter;
use App\Search\HasSite;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class RewardDefinitionSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    use HasSite;

    public function configure(): void
    {
        $this->addFilter(new BooleanFilter('is_active', 'is_active'))
            ->addFilter(new InFilter('reward_type', 'reward_type'))
            ->addFilter(new EqualsFilter('site_id', 'site_id'));

        $this->addSort(new SortSpecification('name', 'name'))
            ->addSort(new SortSpecification('created_at', 'created_at'))
            ->addSort(new SortSpecification('updated_at', 'updated_at'))
            ->addSort(new SortSpecification('sort_order', 'sort_order'));

        $this->addSearchableColumn('name')
            ->addSearchableColumn('description')
            ->addSearchableColumn('slug');

        $this->setDefaultSort('sort_order', 'asc');

        self::applySiteFilter();
    }
}