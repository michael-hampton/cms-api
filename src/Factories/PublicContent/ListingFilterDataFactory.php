<?php
declare(strict_types=1);

namespace App\Factories\PublicContent;

use App\Data\PublicContent\Listing\ListingFilterData;
use App\Data\PublicContent\PublicDirectoryListingConfigData;

final class ListingFilterDataFactory
{
    /**
     * @param array<string,mixed> $query raw query params, e.g. $request->query->all() or $_GET
     */
    public function fromQueryParams(array $query, PublicDirectoryListingConfigData $config, string $defaultSort): ListingFilterData
    {
        $perPage = filter_var($query['per_page'] ?? null, FILTER_VALIDATE_INT);
        $perPage = ($perPage !== false && in_array($perPage, $config->perPageOptions, true))
            ? $perPage
            : $config->defaultPerPage;
        $perPage = min($perPage, $config->maxPerPage);

        $page = filter_var($query['page'] ?? 1, FILTER_VALIDATE_INT);
        $page = ($page !== false && $page > 0) ? $page : 1;

        $search = isset($query['q']) && is_string($query['q']) ? trim($query['q']) : null;
        $search = ($search === '') ? null : $search;

        $sort = isset($query['sort']) && is_string($query['sort']) ? $query['sort'] : $defaultSort;

        $facets = [];
        if (isset($query['facet']) && is_array($query['facet'])) {
            foreach ($query['facet'] as $key => $values) {
                if (!is_string($key) || !is_array($values)) {
                    continue;
                }
                $facets[$key] = array_values(array_filter(array_map('strval', $values)));
            }
        }

        return new ListingFilterData($search, $sort, $page, $perPage, $facets);
    }
}