<?php

namespace App\Search\Filters;

use App\Search\FilterSpecification;

class RelationshipFilter extends FilterSpecification
{
    public function __construct(
        string         $filterKey,
        private string $relationshipName,
        private string $relationshipColumn
    )
    {
        parent::__construct($filterKey, $relationshipColumn);
    }

    public function apply($query, mixed $value)
    {
        if (str_contains($value, ',')) {
            $value = explode(',', $value);
        }

        return $query->whereHas($this->relationshipName, function ($q) use ($value) {
            if (is_array($value)) {
                $q->whereIn($this->columnName, $value);
            } else {
                $q->where($this->columnName, $value);
            }
        });
    }
}