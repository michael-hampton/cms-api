<?php

namespace App\Search\Configurations;

use App\Search\Filters\ContainsFilter;
use App\Search\Filters\EqualsFilter;
use App\Search\Filters\InFilter;
use App\Search\HasSite;
use App\Search\SearchConfiguration;

/**
 * Searchable fields:
 *   - title        (text search, LIKE)
 *   - active       (boolean equals)
 *   - paused       (boolean equals)
 *   - content_type ('manual' | 'auto_pages' | 'custom_blocks')
 *   - interval     ('daily' | 'weekly' | 'biweekly' | 'monthly')
 *   - template     (string equals)
 *   - is_default   (boolean equals)
 *
 * All filters are optional. The site scope is always applied automatically.
 */
class NewsletterSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    use HasSite;

    public function configure(): void
    {
        $this->addFilter(new EqualsFilter('active', 'active'));
        $this->addFilter(new EqualsFilter('paused', 'paused'));
        $this->addFilter(new EqualsFilter('content_type', 'content_type'));
        $this->addFilter(new EqualsFilter('is_default', 'is_default'));
        $this->addFilter(new InFilter('interval', 'interval'));
        $this->addFilter(new InFilter('template', 'template'));

        $this->addSearchableColumn('title');

        self::applySiteFilter();
    }
}