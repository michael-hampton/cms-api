<?php

namespace App\Search;

class SearchEngine
{
    public function __construct(
        private SearchConfiguration $configuration
    ) {}

    public function search($queryBuilder, SearchCriteria $criteria): PaginatedResult
    {
        $this->configuration->configure();

        if ($criteria->getSearchQuery()) {
            $queryBuilder = $this->applySearchQuery($queryBuilder, $criteria->getSearchQuery());
        }

        foreach ($criteria->getFilters() as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $filter = $this->configuration->getFilters()[$key] ?? null;

            if ($filter) {
                $queryBuilder = $filter->apply($queryBuilder, $value);
            }
        }

        $sortBy = $criteria->getSortBy() ?? $this->configuration->getDefaultSort();

        if ($sortBy) {
            $sort = $this->configuration->getSorts()[$sortBy] ?? null;
            if ($sort) {
                $sortDirection = $criteria->getSortOrder() ?: $this->configuration->getDefaultSortDirection();
                $queryBuilder = $sort->apply($queryBuilder, $sortDirection);
            }
        }

        $total = $queryBuilder->count();

        $queryBuilder = $queryBuilder
            ->limit($criteria->getPerPage())
            ->offset($criteria->getOffset());

        $results = $queryBuilder->get();

        return new PaginatedResult(
            $results->toArray(),
            $total,
            $criteria->getPage(),
            $criteria->getPerPage()
        );
    }

    private function applySearchQuery($queryBuilder, string $searchQuery)
    {
        $searchableColumns = $this->configuration->getSearchableColumns();

        if (empty($searchableColumns)) {
            return $queryBuilder;
        }

        $clauses = [];
        $bindings = [];
        $token = bin2hex(random_bytes(4));

        foreach (array_values($searchableColumns) as $index => $column) {
            $parameter = "search_{$token}_{$index}";
            $safeColumn = $this->quoteSearchColumn($column);
            $clauses[] = "{$safeColumn} LIKE :{$parameter}";
            $bindings[$parameter] = "%{$searchQuery}%";
        }

        return $queryBuilder->whereRaw(
            '(' . implode(' OR ', $clauses) . ')',
            $bindings,
        );
    }

    private function quoteSearchColumn(string $column): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $column)) {
            throw new \InvalidArgumentException('Invalid searchable column: ' . $column);
        }

        if (!str_contains($column, '.')) {
            return '`' . $column . '`';
        }

        [$table, $field] = explode('.', $column, 2);

        return sprintf('`%s`.`%s`', $table, $field);
    }
}
