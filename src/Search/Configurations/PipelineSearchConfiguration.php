<?php

namespace App\Search\Configurations;

use App\Search\Filters\EqualsFilter;
use App\Search\Filters\InFilter;
use App\Search\Filters\RelationshipFilter;
use App\Search\HasSite;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class PipelineSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    use HasSite;

    public function configure(): void
    {
        // Filters
        $this->addFilter(new InFilter('status', 'status'))
            ->addFilter(new InFilter('priority', 'priority'))
            ->addFilter(new InFilter('page_type', 'page_type'))
            ->addFilter(new RelationshipFilter('author', 'pageAuthors', 'author_id'))
            ->addFilter(new EqualsFilter('stage', 'status'));

        self::applySiteFilter();

        // Sorts
        $this->addSort(new SortSpecification('title', 'title'))
            ->addSort(new SortSpecification('deadline', 'deadline'))
            ->addSort(new SortSpecification('created_at', 'created_at'))
            ->addSort(new SortSpecification('updated_at', 'updated_at'))
            ->addSort(new SortSpecification('priority', 'priority'));

        // Searchable columns
        $this->addSearchableColumn('title')
            ->addSearchableColumn('subtitle')
            ->addSearchableColumn('slug');

        // Default sort
        $this->setDefaultSort('created_at', 'desc');
    }
}