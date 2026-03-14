<?php

namespace App\Search\Configurations;

use App\Search\Filters\DateRangeFilter;
use App\Search\Filters\EqualsFilter;
use App\Search\Filters\InFilter;
use App\Search\HasSite;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class NewsletterIssueSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    use HasSite;

    public function configure(): void
    {
        $this->addFilter(new EqualsFilter('newsletter_id', 'newsletter_id'))
            ->addFilter(new InFilter('status', 'status'))
            ->addFilter(new DateRangeFilter('scheduled_at', 'scheduled_at'))
            ->addFilter(new DateRangeFilter('sent_at', 'sent_at'))
            ->addFilter(new DateRangeFilter('created_at', 'created_at'));

        self::applySiteFilter();

        $this->addSort(new SortSpecification('title', 'title'))
            ->addSort(new SortSpecification('created_at', 'created_at'))
            ->addSort(new SortSpecification('updated_at', 'updated_at'))
            ->addSort(new SortSpecification('scheduled_at', 'scheduled_at'))
            ->addSort(new SortSpecification('sent_at', 'sent_at'))
            ->addSort(new SortSpecification('status', 'status'));

        $this->addSearchableColumn('title')
            ->addSearchableColumn('subject');

        $this->setDefaultSort('created_at', 'desc');
    }
}