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

        // Apply search query
        if ($criteria->getSearchQuery()) {
            $queryBuilder = $this->applySearchQuery($queryBuilder, $criteria->getSearchQuery());
        }

        // Apply filters
        foreach ($criteria->getFilters() as $key => $value) {

            if ($value === null || $value === '') {
                continue;
            }

            $filter = $this->configuration->getFilters()[$key] ?? null;

            if ($filter) {
                $queryBuilder = $filter->apply($queryBuilder, $value);
            }
        }

        // Apply sorting
        $sortBy = $criteria->getSortBy() ?? $this->configuration->getDefaultSort();
        if ($sortBy) {
            $sort = $this->configuration->getSorts()[$sortBy] ?? null;
            if ($sort) {
                $sortDirection = $criteria->getSortOrder();
                $queryBuilder = $sort->apply($queryBuilder, $sortDirection);
            }
        }

        // Get total count before pagination
        $total = $queryBuilder->count();

        // Apply pagination
        $queryBuilder = $queryBuilder
            ->limit($criteria->getPerPage())
            ->offset($criteria->getOffset());

        // Execute query
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

        return $queryBuilder->where(function($q) use ($searchQuery, $searchableColumns) {
            foreach ($searchableColumns as $column) {
                $q->orWhere($column, 'LIKE', "%{$searchQuery}%");
            }
        });
    }
}