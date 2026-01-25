<?php

namespace App\Search\Configurations;

use App\Search\Filters\DateRangeFilter;
use App\Search\Filters\EqualsFilter;
use App\Search\Filters\InFilter;
use App\Search\HasSite;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class RewardSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    use HasSite;

    public function configure(): void
    {
        $this->addFilter(new InFilter('status', 'status'))
            ->addFilter(new EqualsFilter('member_id', 'member_id'))
            ->addFilter(new EqualsFilter('reward_definition_id', 'reward_definition_id'))
            ->addFilter(new DateRangeFilter('date_from', 'created_at'))
            ->addFilter(new DateRangeFilter('date_to', 'created_at'))
            ->addFilter(new EqualsFilter('site_id', 'site_id'));

        $this->addSort(new SortSpecification('created_at', 'created_at'))
            ->addSort(new SortSpecification('updated_at', 'updated_at'))
            ->addSort(new SortSpecification('status', 'status'));

        $this->addSearchableColumn('admin_notes')
            ->addSearchableColumn('decline_reason');

        $this->setDefaultSort('created_at', 'desc');

        self::applySiteFilter();
    }
}