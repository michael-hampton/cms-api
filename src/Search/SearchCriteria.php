<?php

namespace App\Search;

class SearchCriteria
{
    public function __construct(
        private array $filters = [],
        private ?string $sortBy = null,
        private ?string $sortOrder = null,
        private int $page = 1,
        private int $perPage = 20,
        private ?string $searchQuery = null
    ) {}

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function hasFilter(string $key): bool
    {
        return isset($this->filters[$key]);
    }

    public function getFilter(string $key, mixed $default = null): mixed
    {
        return $this->filters[$key] ?? $default;
    }

    public function getSortBy(): ?string
    {
        return $this->sortBy;
    }

    public function getSortOrder(): ?string
    {
        return $this->sortOrder;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    public function getSearchQuery(): ?string
    {
        return $this->searchQuery;
    }

    public function getOffset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function addFilter(string $column, mixed $value): void
    {
        $this->filters[$column] = $value;
    }

    public function setSortBy(?string $sortBy): void
    {
        $this->sortBy = $sortBy;
    }

    public function setSortOrder(string $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }

    public function setPage(int $page): void
    {
        $this->page = $page;
    }

    public function setPerPage(int $perPage): void
    {
        $this->perPage = $perPage;
    }

    public function setSearchQuery(?string $searchQuery): void
    {
        $this->searchQuery = $searchQuery;
    }
}