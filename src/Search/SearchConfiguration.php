<?php

namespace App\Search;

class SearchConfiguration
{
    private array $filters = [];
    private array $sorts = [];
    private array $searchableColumns = [];
    private ?string $defaultSort = null;
    private string $defaultSortDirection = 'asc';

    public function addFilter(FilterSpecificationInterface $filter): self
    {
        $this->filters[$filter->getFilterKey()] = $filter;
        return $this;
    }

    public function addSort(SortSpecificationInterface $sort): self
    {
        $this->sorts[$sort->getSortKey()] = $sort;
        return $this;
    }

    public function addSearchableColumn(string $column): self
    {
        $this->searchableColumns[] = $column;
        return $this;
    }

    public function setDefaultSort(string $sortKey, string $direction = 'asc'): self
    {
        $this->defaultSort = $sortKey;
        $this->defaultSortDirection = $direction;
        return $this;
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getSorts(): array
    {
        return $this->sorts;
    }

    public function getSearchableColumns(): array
    {
        return $this->searchableColumns;
    }

    public function getDefaultSort(): ?string
    {
        return $this->defaultSort;
    }

    public function getDefaultSortDirection(): string
    {
        return $this->defaultSortDirection;
    }
}