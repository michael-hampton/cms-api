<?php

namespace App\Search\Filters;

use App\Search\FilterSpecification;

class LikeFilter extends FilterSpecification
{
    public function apply($query, mixed $value)
    {
        return $query->where($this->columnName, 'LIKE', "%{$value}%");
    }
}