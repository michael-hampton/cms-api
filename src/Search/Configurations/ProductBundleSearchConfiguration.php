<?php

namespace App\Search\Configurations;

use App\Search\Filters\InFilter;
use App\Search\SearchConfiguration;

class ProductBundleSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{

    public function configure(): void
    {
        $this->addFilter(new InFilter('status', 'status'));
    }
}