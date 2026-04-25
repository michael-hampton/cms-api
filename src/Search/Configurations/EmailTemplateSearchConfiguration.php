<?php

namespace App\Search\Configurations;

use App\Search\HasSite;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class EmailTemplateSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    use HasSite;

    public function configure(): void
    {
        self::applySiteFilter();

        $this->addSort(new SortSpecification('created_at', 'created_at'))
            ->addSort(new SortSpecification('updated_at', 'updated_at'));

        $this->addSearchableColumn('name')
            ->addSearchableColumn('slug')
            ->addSearchableColumn('description')
            ->addSearchableColumn('category');

        $this->setDefaultSort('name', 'asc');
    }
}
