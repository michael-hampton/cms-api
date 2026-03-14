<?php

namespace App\Repositories\Product;

use App\Framework\Support\Collection;
use App\Models\MerchantProductFeed;
use App\Repositories\Repository;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;

class MerchantProductFeedRepository extends Repository
{
    public function getByMerchant(int $merchantId): Collection
    {
        return MerchantProductFeed::where('merchant_id', $merchantId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getActiveFeedsByMerchant(int $merchantId): Collection
    {
        return MerchantProductFeed::where('merchant_id', $merchantId)
            ->active()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getDueForFetch(): Collection
    {
        return MerchantProductFeed::dueForFetch()->get();
    }

    public function getByStatus(string $status): Collection
    {
        return MerchantProductFeed::where('status', $status)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $configuration = SearchConfigurationFactory::create('merchant_product_feed');
        $engine = new SearchEngine($configuration);

        // Replace with however your repository accesses its base query builder,
        // e.g. Campaign::query() or $this->model->newQuery()
        return $engine->search($this->query(), $criteria);
    }

    protected function getModelClass(): string
    {
        return MerchantProductFeed::class;
    }
}