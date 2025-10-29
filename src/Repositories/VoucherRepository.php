<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Models\Voucher;
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
        $query = Voucher::with(['products']);
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
}