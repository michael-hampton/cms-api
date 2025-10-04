<?php

namespace App\Search;

interface FilterSpecificationInterface
{
    public function apply($query, mixed $value);
    public function getFilterKey(): string;
}