<?php

namespace App\Search;

interface SortSpecificationInterface
{
    public function apply($query, string $direction);
    public function getSortKey(): string;
}