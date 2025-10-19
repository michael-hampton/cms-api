<?php

namespace App\Search\Filters;

use App\Search\FilterSpecification;

class DateRangeFilter extends FilterSpecification
{
    public function apply($query, $value)
    {
        if (is_array($value)) {
            if (isset($value['from'])) {
                $query->where($this->columnName, '>=', $value['from']);
            }
            if (isset($value['to'])) {
                $query->where($this->columnName, '<=', $value['to']);
            }
        } else {
            // If single date value, filter for that specific date
            $query->whereDate($this->columnName, '=', $value);
        }

        return $query;
    }
}