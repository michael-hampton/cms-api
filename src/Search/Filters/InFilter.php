<?php

namespace App\Search\Filters;

use App\Search\FilterSpecification;

class InFilter extends FilterSpecification
{
    public function apply($query, mixed $value)
    {
        if (!is_array($value)) {
            // Handle comma-separated string
            if (is_string($value) && str_contains($value, ',')) {
                $value = array_map('trim', explode(',', $value));
            } else {
                $value = [$value];
            }
        }

        return $query->whereIn($this->columnName, $value);
    }
}