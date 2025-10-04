<?php

namespace App\Search;

class SortSpecification implements SortSpecificationInterface
{
    public function __construct(
        private string $sortKey,
        private string $columnName
    ) {}

    public function apply($query, string $direction)
    {
        return $query->orderBy($this->columnName, $direction);
    }

    public function getSortKey(): string
    {
        return $this->sortKey;
    }
}