<?php

namespace App\Search\Filters;

use App\Search\FilterSpecification;

class CustomFilter extends FilterSpecification
{
    private $callback;

    public function __construct(string $filterKey, callable $callback)
    {
        parent::__construct($filterKey, $filterKey);
        $this->callback = $callback;
    }

    public function apply($query, mixed $value)
    {
        return call_user_func($this->callback, $query, $value);
    }
}