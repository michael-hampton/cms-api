<?php

namespace App\Repositories\Product;

use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\Merchant;
use App\Models\MerchantNote;
use App\Models\MerchantUrl;
use App\Models\Model;
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

    public function getStatistics(?int $merchantId = null): array
    {
        $query = Merchant::query();

        if ($merchantId) {
            $query->where('id', $merchantId);
        }

        $totalMerchants = (clone $query)->count();
        $activeMerchants = (clone $query)->where('is_active', true)->count();

        // Build product merchants query with optional merchant filter
        $pmQuery = Database::table('product_merchants as pm')
            ->join('merchants as m', 'pm.merchant_id', '=', 'm.id')
            ->where('m.is_active', true);

        if ($merchantId) {
            $pmQuery->where('m.id', $merchantId);
        }

        $productMerchants = $pmQuery->select([
            'pm.product_id',
            'pm.merchant_id',
            'pm.price',
            'pm.sale_price',
        ])->get();

        $totalProducts = $productMerchants
            ->pluck('product_id')
            ->unique()
            ->count();

        $productsOnSale = $productMerchants
            ->filter(fn($pm) => isset($pm['sale_price'], $pm['price']) &&
                $pm['sale_price'] > 0 &&
                $pm['price'] > 0 &&
                $pm['sale_price'] < $pm['price']
            )
            ->pluck('product_id')
            ->unique()
            ->count();

        $discounts = $productMerchants
            ->filter(fn($pm) => $pm['sale_price'] !== null &&
                $pm['sale_price'] > 0 &&
                $pm['price'] > 0 &&
                $pm['sale_price'] < $pm['price']
            )
            ->map(fn($pm) => (($pm['price'] - $pm['sale_price']) / $pm['price']) * 100
            );

        $avgDiscount = $discounts->isEmpty()
            ? 0
            : round($discounts->avg(), 2);

        $totalRevenue = round(
            $productMerchants->sum(fn($pm) => $pm['sale_price'] ?? $pm['price'] ?? 0
            ),
            2
        );

        // Get merchant names for the filtered set
        $merchantQuery = Merchant::where('is_active', true);
        if ($merchantId) {
            $merchantQuery->where('id', $merchantId);
        }
        $merchantNames = $merchantQuery->pluck('name', 'id');

        $topMerchantsByProducts = $productMerchants
            ->groupBy('merchant_id')
            ->map(fn($items) => $items->pluck('product_id')->unique()->count())
            ->sortByDesc()
            ->take(5)
            ->map(function ($count, $id) use ($merchantNames) {
                return [
                    'merchant_id' => $id,
                    'product_count' => $count,
                    'name' => $merchantNames[$id] ?? null,
                ];
            })
            ->values();

        return [
            'total_merchants' => $totalMerchants,
            'active_merchants' => $activeMerchants,
            'total_products' => $totalProducts,
            'products_on_sale' => $productsOnSale,
            'avg_discount_percentage' => $avgDiscount,
            'total_revenue_estimate' => $totalRevenue,
            'top_merchants_by_products' => $topMerchantsByProducts,
            'filtered_merchant_id' => $merchantId,
        ];
    }

    public function getNotes(int $merchantId): Collection
    {
        return MerchantNote::where('merchant_id', $merchantId)
            ->with(['user'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function createNote(int $merchantId, int $userId, string $content): Model
    {
        return MerchantNote::create([
            'merchant_id' => $merchantId,
            'user_id' => $userId,
            'content' => $content,
        ]);
    }

    public function updateNote(int $noteId, string $content): ?Model
    {
        $note = MerchantNote::find($noteId);

        if (!$note) {
            return null;
        }

        $note->update(['content' => $content]);
        return $note->fresh(['user']);
    }

    public function deleteNote(int $noteId): bool
    {
        return MerchantNote::where('id', $noteId)->delete() > 0;
    }

}