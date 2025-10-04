<?php

namespace App\Search\Filters;

use App\Search\FilterSpecification;

class BooleanFilter extends FilterSpecification
{
    public function apply($query, mixed $value)
    {
        $boolValue = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        return $query->where($this->columnName, $boolValue ? 1 : 0);
    }
}