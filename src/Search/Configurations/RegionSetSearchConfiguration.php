<?php

namespace App\Search\Configurations;

use App\Search\Filters\EqualsFilter;
use App\Search\HasSite;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class RegionSetSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    use HasSite;

    public function configure(): void
    {
        $this->addFilter(new EqualsFilter('site_id', 'site_id'));
        $this->addFilter(new EqualsFilter('is_active', 'is_active'));
        $this->addSort(new SortSpecification('name', 'name'));
        $this->addSort(new SortSpecification('slug', 'slug'));
        $this->addSort(new SortSpecification('is_active', 'is_active'));
        $this->addSort(new SortSpecification('sort_order', 'sort_order'));
        $this->addSort(new SortSpecification('created_at', 'created_at'));
        $this->addSort(new SortSpecification('updated_at', 'updated_at'));
        $this->addSearchableColumn('name');
        $this->addSearchableColumn('slug');
        $this->addSearchableColumn('description');
        $this->setDefaultSort('sort_order', 'asc');
        $this->applySiteFilter();
    }
}