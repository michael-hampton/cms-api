<?php

namespace App\Search\Configurations;

use App\Search\Filters\EqualsFilter;
use App\Search\HasSite;
use App\Search\SearchConfiguration;

class NewsletterSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    use HasSite;

    public function configure(): void
    {
        $this->addFilter(new EqualsFilter('active', 'active'));

        self::applySiteFilter();
    }
}