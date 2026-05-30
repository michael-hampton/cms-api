<?php

namespace App\Framework\Database;

class SubqueryExpression extends RawExpression
{
    public array $bindings = [];

    public function __construct(string $value, array $bindings = [])
    {
        parent::__construct($value);
        $this->bindings = $bindings;
    }
}