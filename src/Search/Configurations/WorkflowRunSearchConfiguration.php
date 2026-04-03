<?php

namespace App\Search\Configurations;

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
            ->addFilter(new EqualsFilter('issues_delivered_id', 'issues_delivered_id'))
            ->addFilter(new RelationshipFilter('issue_id', 'batch', 'issue_delivery_id')) // Added for consistency
            ->addFilter(new EqualsFilter('subscription_id', 'subscription_id'))
            ->addFilter(new EqualsFilter('batch_id', 'batch_id'))
            ->addFilter(new InFilter('status', 'status'));

        // Sorts
        $this->addSort(new SortSpecification('id', 'id'))
            ->addSort(new SortSpecification('tracking_number', 'tracking_number'))
            ->addSort(new SortSpecification('status', 'status'));

        // Searchable columns
        $this->addSearchableColumn('workflow_type');

        // Default sort
        $this->setDefaultSort('id', 'desc');
    }
}