<?php

namespace App\Search;

class RelationshipCountSort implements SortSpecificationInterface
{
    public function __construct(
        private string $sortKey,
        private string $relationshipName
    ) {}

    public function apply($query, string $direction)
    {
        return $query->withCount($this->relationshipName)
            ->orderBy($this->relationshipName . '_count', $direction);
    }

    public function getSortKey(): string
    {
        return $this->sortKey;
    }
}