<?php

namespace App\Search\Filters;

use App\Search\FilterSpecification;

class RelationshipExistsFilter extends FilterSpecification
{
    public function __construct(
        string $filterKey,
        private string $relationshipName,
        private string $relationshipColumn
    ) {
        parent::__construct($filterKey, $relationshipColumn);
    }

    public function apply($query, mixed $value)
    {
        $boolValue = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        if ($boolValue) {
            return $query->whereHas($this->relationshipName, function($q) {
                $q->where($this->columnName, 1);
            });
        } else {
            return $query->whereHas($this->relationshipName, function($q) {
                $q->where($this->columnName, 0);
            })->orWhereDoesntHave($this->relationshipName);
        }
    }
}