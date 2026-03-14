<?php

namespace App\Repositories\Product;

use App\Framework\Support\Collection;
use App\Models\MerchantContact;
use App\Repositories\Repository;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;

class MerchantContactRepository extends Repository
{
    public function getByMerchant(int $merchantId): Collection
    {
        return MerchantContact::where('merchant_id', $merchantId)
            ->orderBy('name')
            ->get();
    }

    public function findByEmail(string $email): ?MerchantContact
    {
        return MerchantContact::where('email', $email)->first();
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $configuration = SearchConfigurationFactory::create('merchant_contact');
        $engine = new SearchEngine($configuration);

        // Replace with however your repository accesses its base query builder,
        // e.g. Campaign::query() or $this->model->newQuery()
        return $engine->search($this->query(), $criteria);
    }

    protected function getModelClass(): string
    {
        return MerchantContact::class;
    }
}