<?php

namespace App\Search;

trait HasSite
{
    protected function applySiteFilter(): void
    {
        $this->addFilter(new \App\Search\Filters\EqualsFilter('site_id', 'site_id'));
    }
}