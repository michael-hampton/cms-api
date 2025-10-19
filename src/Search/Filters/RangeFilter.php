<?php

namespace App\Search\Filters;

use App\Search\FilterSpecification;

class RangeFilter extends FilterSpecification
{
    public function apply($query, $value)
    {
        if (is_array($value)) {
            if (isset($value['min'])) {
                $query->where($this->columnName, '>=', $value['min']);
            }
            if (isset($value['max'])) {
                $query->where($this->columnName, '<=', $value['max']);
            }
        } else {
            $query->where($this->columnName, '=', $value);
        }

        return $query;
    }
}