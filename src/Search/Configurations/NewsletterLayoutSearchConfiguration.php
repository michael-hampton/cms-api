<?php

namespace App\Search\Configurations;

use App\Search\Filters\BooleanFilter;
use App\Search\Filters\EqualsFilter;
use App\Search\HasSite;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class NewsletterLayoutSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    use HasSite;

    public function configure(): void
    {
        $this->addFilter(new BooleanFilter('is_system_layout', 'is_system_layout'))
            ->addFilter(new EqualsFilter('created_by', 'created_by'));

        self::applySiteFilter();

        $this->addSort(new SortSpecification('name', 'name'))
            ->addSort(new SortSpecification('slug', 'slug'))
            ->addSort(new SortSpecification('created_at', 'created_at'))
            ->addSort(new SortSpecification('updated_at', 'updated_at'));

        $this->addSearchableColumn('name')
            ->addSearchableColumn('slug');

        $this->setDefaultSort('name', 'asc');
    }
}