<?php

namespace App\Search;

use App\Models\Site;

class SearchCriteriaParser
{
    private const FILTER_PARAMS = [
        'status',
        'visibility',
        'page_type',
        'author',
        'featured',
        'categories',
        'category',
        'category_id',
        'tag',
        'tag_id',
        'parent',
        'template',
        'role',
        'is_active',
        'site_id',
        'region_set_id',
        'territory_id',
        'merchant',
        'brands',
        'is_featured',
        'content_type',
        'owner_id',
        'product_ids',
        'reward_type',
        'merchant_id',
        'start_date',
        'member_id',
        'reward_definition_id',
        'date_from',
        'active',
        'name'
    ];

    public static function fromRequest($request, string $siteName): SearchCriteria
    {
        // Check if request has a 'search' object with nested parameters
        $searchParams = $request->get('search');

        if (is_array($searchParams)) {
            return self::fromSearchObject($searchParams, $request, $siteName);
        }

        return self::fromFlatRequest($request, $siteName);
    }

    private static function fromSearchObject(array $searchParams, $request, string $siteName): SearchCriteria
    {
        $filters = [];

        // Extract filters from search object
        if (isset($searchParams['filters']) && is_array($searchParams['filters'])) {
            foreach ($searchParams['filters'] as $key => $value) {
                if ($value !== '' && $value !== null) {
                    $filters[$key] = $searchParams['filters'][$key];
                }
            }

            $filters['site_id'] = Site::resolveSite($siteName);

        }

        return new SearchCriteria(
            filters: $filters,
            sortBy: $searchParams['sort_by'] ?? $searchParams['sortBy'] ?? null,
            sortOrder: strtolower($searchParams['sort_order'] ?? $searchParams['sortOrder'] ?? 'asc'),
            page: max(1, (int)($searchParams['page'] ?? 1)),
            perPage: min(1000, max(1, (int)($searchParams['per_page'] ?? $searchParams['perPage'] ?? 1000))),
            searchQuery: $searchParams['query'] ?? $searchParams['q'] ?? null
        );
    }

    private static function fromFlatRequest($request, string $siteName): SearchCriteria
    {
        $filters = [];

        // Extract filters from flat query params
        foreach (self::FILTER_PARAMS as $key) {

            $value = $request->get($key);

            if ($value !== null && $value !== '') {
                $filters[$key] = $value;
            }
        }

        $siteId =  Site::resolveSite($siteName);

        $filters['site_id'] = $siteId;

        return new SearchCriteria(
            filters: $filters,
            sortBy: $request->get('sort_by'),
            sortOrder: strtolower($request->get('sort_order', 'asc')),
            page: max(1, (int)$request->get('page', 1)),
            perPage: min(1000, max(1, (int)$request->get('per_page', 1000))),
            searchQuery: $request->get('q') ?: $request->get('search')
        );
    }
}