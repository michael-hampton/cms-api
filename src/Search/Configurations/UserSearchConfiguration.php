<?php

namespace App\Search\Configurations;

use App\Search\Filters\BooleanFilter;
use App\Search\Filters\CustomFilter;
use App\Search\Filters\EqualsFilter;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class UserSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    public function configure(): void
    {
        $this->addFilter(new EqualsFilter('role', 'role'))
            ->addFilter(new BooleanFilter('is_active', 'is_active'))
            ->addFilter(new CustomFilter('site_id', function ($query, mixed $value) {
                if (is_string($value) && str_contains($value, ',')) {
                    $value = array_map('trim', explode(',', $value));
                }

                if (!is_array($value)) {
                    $value = [$value];
                }

                $siteIds = array_values(array_filter(
                    array_map('intval', $value),
                    fn(int $siteId): bool => $siteId > 0
                ));

                if ($siteIds === []) {
                    return $query;
                }

                $placeholders = [];
                $bindings = [];

                foreach ($siteIds as $index => $siteId) {
                    $param = "user_site_id_{$index}";

                    $placeholders[] = ':' . $param;
                    $bindings[$param] = $siteId;
                }

                return $query->whereRaw(
                    sprintf(
                        'id IN (
                SELECT user_id
                FROM oc_user_sites
                WHERE site_id IN (%s)
            )',
                        implode(', ', $placeholders)
                    ),
                    $bindings
                );
            }));

        $this->addSort(new SortSpecification('name', 'name'))
            ->addSort(new SortSpecification('email', 'email'))
            ->addSort(new SortSpecification('created_at', 'created_at'))
            ->addSort(new SortSpecification('updated_at', 'updated_at'))
            ->addSort(new SortSpecification('role', 'role'));

        $this->addSearchableColumn('name')
            ->addSearchableColumn('email');

        $this->setDefaultSort('name', 'asc');
    }
}