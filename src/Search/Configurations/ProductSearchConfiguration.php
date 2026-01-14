<?php

namespace App\Search\Configurations;

use App\Search\Filters\CustomFilter;
use App\Search\Filters\InFilter;
use App\Search\HasSite;
use App\Search\SearchConfiguration;
use App\Search\SortSpecification;

class ProductSearchConfiguration extends SearchConfiguration implements SearchConfigurationInterface
{
    use HasSite;

    public function configure(): void
    {
        // Filters
        $this->addFilter(new InFilter('categories', 'category_id'))
            ->addFilter(new InFilter('brands', 'brand_id'))
            ->addFilter(new CustomFilter('specifications', function ($query, $value) {
                return $query->whereHas('specifications', function ($q) use ($value) {
                    // Check if values are numeric (IDs) or strings (names)
                    if (is_numeric($value[0])) {
                        $q->whereIn('id', $value);
                    } else {
                        $q->whereIn('name', $value);
                    }
                });
            }))->addFilter(new CustomFilter('merchant', function ($query, $value) {
                // Handle comma-separated string or array
                if (is_string($value) && str_contains($value, ',')) {
                    $value = explode(',', $value);
                }

                if (!is_array($value)) {
                    $value = [$value];
                }

                // Filter out empty values
                $value = array_filter($value, fn($v) => $v !== '' && $v !== null);

                if (empty($value)) {
                    return $query;
                }

                // Filter products that have ANY of these merchants
                return $query->whereHas('availableMerchants', function($q) use ($value) {
                    // Check if values are numeric (IDs) or strings (names)
                    if (is_numeric($value[0])) {
                        $q->whereIn('merchant_id', $value);
                    } else {
                        $q->whereIn('name', $value);
                    }
                });
            }))
            ->addFilter(new CustomFilter('on_sale', function($query, $value) {
                if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                    $query->whereNotNull('sale_price')
                        ->whereColumn('sale_price', '<', 'price');
                }
                return $query;
            }));

        self::applySiteFilter();

        // Sorts
        $this->addSort(new SortSpecification('name', 'name'))
            ->addSort(new SortSpecification('price', 'price'))
            ->addSort(new SortSpecification('created_at', 'created_at'))
            ->addSort(new SortSpecification('updated_at', 'updated_at'));

        // Searchable columns
        $this->addSearchableColumn('name')
            ->addSearchableColumn('description')
            ->addSearchableColumn('brand_id');

        // Default sort
        $this->setDefaultSort('created_at', 'desc');
    }
}