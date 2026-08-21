<?php

namespace App\Search\Configurations;

use App\Search\Filters\DateRangeFilter;
use App\Search\Filters\EqualsFilter;
use App\Search\Filters\InFilter;
use App\Search\Filters\LikeFilter;
use App\Search\Filters\RelationshipFilter;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class WorkflowRunSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    public function configure(): void
    {
        $this->addFilter(new LikeFilter('workflow_type', 'workflow_type'))
            ->addFilter(new EqualsFilter('subscription_issue_fulfilment_id', 'subscription_issue_fulfilment_id'))
            ->addFilter(new RelationshipFilter('issue_id', 'batch', 'issue_delivery_id')) // Added for consistency
            ->addFilter(new EqualsFilter('subscription_id', 'subscription_id'))
            ->addFilter(new EqualsFilter('batch_id', 'batch_id'))
            ->addFilter(new InFilter('status', 'status'))
            ->addFilter(new DateRangeFilter('created_at', 'created_at'))
            ->addFilter(new DateRangeFilter('updated_at', 'updated_at'));

        // Sorts
        $this->addSort(new SortSpecification('id', 'id'))
            ->addSort(new SortSpecification('tracking_number', 'tracking_number'))
            ->addSort(new SortSpecification('status', 'status'))
            ->addSort(new SortSpecification('created_at', 'created_at'))
            ->addSort(new SortSpecification('updated_at', 'updated_at'));

        // Searchable columns
        $this->addSearchableColumn('workflow_type');

        // Default sort
        $this->setDefaultSort('id', 'desc');
    }
}