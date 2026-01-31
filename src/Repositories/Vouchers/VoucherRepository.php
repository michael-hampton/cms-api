<?php

namespace App\Repositories\Vouchers;

use App\Framework\Support\Collection;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use App\Repositories\Repository;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;

class VoucherRepository extends Repository
{
    private SearchEngine $searchEngine;

    public function __construct()
    {
        parent::__construct();
        $config = SearchConfigurationFactory::create('voucher');
        $this->searchEngine = new SearchEngine($config);
    }

    protected function getModelClass(): string
    {
        return Voucher::class;
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $query = Voucher::with(['products', 'categories', 'brands']);
        return $this->searchEngine->search($query, $criteria);
    }

    public function findByCode(string $code): ?Voucher
    {
        $query = Voucher::where('code', $code);
        return $this->applySiteFilter($query)->first();
    }

    public function getActiveVouchers(): Collection
    {
        $query = Voucher::where('status', 'active')
            ->where(function($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', date('Y-m-d H:i:s'));
            });

        return $this->applySiteFilter($query)->get();
    }

    public function incrementUsageCount(int $voucherId): bool
    {
        $voucher = $this->find($voucherId);

        if (!$voucher) {
            return false;
        }

        $voucher->usage_count = $voucher->usage_count + 1;

        // Auto-expire if usage limit reached
        if ($voucher->usage_limit && $voucher->usage_count >= $voucher->usage_limit) {
            $voucher->status = 'expired';
        }

        return $voucher->save();
    }

    public function checkDeletable(int $voucherId): array
    {
        $voucher = $this->find($voucherId);

        if (!$voucher) {
            throw new \Exception('Voucher not found');
        }

        $hasUsage = $voucher->usage_count > 0;

        return [
            'can_delete' => !$hasUsage,
            'usage_count' => $voucher->usage_count,
            'requires_confirmation' => $hasUsage
        ];
    }

    public function getAlternatives(int $voucherId, ?int $siteId = null): Collection
    {
        $voucher = $this->find($voucherId);

        if (!$voucher) {
            return collect([]);
        }

        $query = Voucher::where('id', '!=', $voucherId)
            ->where('status', 'active');

        return !empty($siteId) ? $query->where('site_id', $siteId)->get() : $this->applySiteFilter($query)->get();
    }

    public function codeExistsInSite(string $code, int $siteId, ?int $excludeId = null): bool
    {
        $query = Voucher::where('code', $code)
            ->where('site_id', $siteId);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function updateExpiredVouchers(): int
    {
        $count = 0;

        $query = Voucher::where('status', 'active')
            ->where('expires_at', '<', date('Y-m-d H:i:s'));

        $vouchers = $this->applySiteFilter($query)->get();

        foreach ($vouchers as $voucher) {
            $voucher->status = 'expired';
            $voucher->save();
            $count++;
        }

        return $count;
    }

    public function syncCategories(int $voucherId, array $categoryIds): void
    {
        $voucher = Voucher::find($voucherId);
        if ($voucher) {
            $voucher->categories(true)->sync($categoryIds);
        }
    }

    public function syncBrands(int $voucherId, array $brandIds): void
    {
        $voucher = Voucher::find($voucherId);
        if ($voucher) {
            $voucher->brands(true)->sync($brandIds);
        }
    }

    public function syncProducts(int $voucherId, array $productIds): void
    {
        $voucher = Voucher::find($voucherId);
        if ($voucher) {
            $voucher->products(true)->sync(array_unique($productIds));
        }
    }

    public function createRedemption(int $voucherId, ?int $userId, float $discountAmount, ?int $orderId = null): bool
    {

        try {
            VoucherRedemption::create([
                'voucher_id' => $voucherId,
                'member_id' => $userId,
                'order_id' => $orderId ?? null,
                'discount_amount' => $discountAmount,
                'redeemed_at' => date('Y-m-d H:i:s')
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getRedemptionsByVoucher(int $voucherId): Collection
    {
        return VoucherRedemption::where('voucher_id', $voucherId)
            ->orderBy('redeemed_at', 'desc')
            ->get();
    }

    public function getRedemptionsByUser(int $userId): Collection
    {
        return VoucherRedemption::where('member_id', $userId)
            ->orderBy('redeemed_at', 'desc')
            ->get();
    }
}