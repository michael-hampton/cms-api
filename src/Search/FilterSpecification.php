<?php

namespace App\Search;

abstract class FilterSpecification implements FilterSpecificationInterface
{
    public function __construct(
        protected string $filterKey,
        protected string $columnName
    ) {}

    public function getFilterKey(): string
    {
        return $this->filterKey;
    }
}