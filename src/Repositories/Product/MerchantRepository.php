<?php

namespace App\Repositories\Product;

use App\Framework\Support\Collection;
use App\Models\Merchant;
use App\Models\MerchantUrl;
use App\Repositories\Repository;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;

class MerchantRepository extends Repository
{
    private SearchEngine $searchEngine;

    public function __construct()
    {
        parent::__construct();
        $config = SearchConfigurationFactory::create('merchant');
        $this->searchEngine = new SearchEngine($config);
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $query = Merchant::with(['contact', 'urls', 'sites', 'products']);
        return $this->searchEngine->search($query, $criteria);
    }

    public function findBySlug(string $slug): ?Merchant
    {
        return Merchant::where('slug', $slug)->first();
    }

    public function getActive(): Collection
    {
        return Merchant::active()->orderBy('name')->get();
    }

    public function syncUrls(int $merchantId, array $urls): void
    {
        MerchantUrl::where('merchant_id', $merchantId)->delete();

        $hasPrimary = false;
        foreach ($urls as $index => $urlData) {
            // Ensure only one primary URL
            $isPrimary = $urlData['is_primary'] ?? false;
            if ($isPrimary && $hasPrimary) {
                $isPrimary = false;
            }
            if ($isPrimary) {
                $hasPrimary = true;
            }

            MerchantUrl::create([
                'merchant_id' => $merchantId,
                'url' => $urlData['url'],
                'label' => $urlData['label'] ?? null,
                'is_primary' => $isPrimary,
            ]);
        }

        // If no primary was set, make the first one primary
        if (!$hasPrimary && count($urls) > 0) {
            $firstUrl = MerchantUrl::where('merchant_id', $merchantId)
                ->orderBy('id')
                ->first();
            if ($firstUrl) {
                $firstUrl->update(['is_primary' => true]);
            }
        }
    }

    public function syncSites(int $merchantId, array $siteIds): void
    {
        $merchant = $this->find($merchantId);
        if ($merchant) {
            $merchant->sites(true)->sync($siteIds);
        }
    }

    public function getUrls(int $merchantId): Collection
    {
        return MerchantUrl::where('merchant_id', $merchantId)
            ->orderBy('is_primary', 'desc')
            ->get();
    }

    public function bulkUpdateStatus(array $ids, bool $isActive): int
    {
        return Merchant::whereIn('id', $ids)->update(['is_active' => (int)$isActive]);
    }

    public function bulkDelete(array $ids): int
    {
        foreach ($ids as $id) {
            $this->deleteUrls($id);
        }
        return Merchant::whereIn('id', $ids)->delete();
    }

    public function deleteUrls(int $merchantId): void
    {
        MerchantUrl::where('merchant_id', $merchantId)->delete();
    }

    public function getMerchantsWithProductCount(): Collection
    {
        return Merchant::withCount('products')
            ->orderBy('name')
            ->get();
    }

    public function findBySite(int $siteId): Collection
    {
        return Merchant::bySite($siteId)->orderBy('name')->get();
    }

    protected function getModelClass(): string
    {
        return Merchant::class;
    }
}