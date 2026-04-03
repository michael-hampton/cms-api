<?php

namespace App\Repositories\Workflow;

use App\Models\WorkflowRun;
use App\Repositories\Repository;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;

class WorkflowRunRepository extends Repository
{
    private readonly SearchEngine $searchEngine;

    public function __construct()
    {
        parent::__construct();
        $config = SearchConfigurationFactory::create('workflow-run');
        $this->searchEngine = new SearchEngine($config);
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $query = WorkflowRun::query();
        return $this->searchEngine->search($query, $criteria);
    }
    protected function getModelClass(): string
    {
        return WorkflowRun::class;
    }
}