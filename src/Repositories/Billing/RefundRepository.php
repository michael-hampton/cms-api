<?php

namespace App\Repositories\Billing;

use App\Framework\Support\Collection;
use App\Models\Refund;
use App\Models\RefundItem;
use App\Repositories\Repository;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;

class RefundRepository extends Repository
{
    private SearchEngine $searchEngine;

    public function __construct()
    {
        parent::__construct();
        $config = SearchConfigurationFactory::create('refund');
        $this->searchEngine = new SearchEngine($config);
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $query = Refund::with(['order', 'items', 'processedBy'])->withCount(['items']);
        return $this->searchEngine->search($query, $criteria);
    }

    public function findByOrderId(int $orderId): Collection
    {
        return Refund::where('order_id', $orderId)
            ->with(['items', 'processedBy'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getByStatus(string $status): Collection
    {
        return $this->applySiteFilter(
            Refund::where('status', $status)
                ->orderBy('created_at', 'desc')
        )->get();
    }

    public function getTotalRefundedAmount(int $orderId): float
    {
        $refunds = Refund::where('order_id', $orderId)
            ->whereIn('status', ['processed'])
            ->get();

        return $refunds->sum('refund_amount');
    }

    public function createRefundItem(array $data): RefundItem
    {
        return RefundItem::create($data);
    }

    public function getRefundItems(int $refundId): Collection
    {
        return RefundItem::where('refund_id', $refundId)
            ->with(['orderItem', 'product'])
            ->get();
    }

    public function updateRefundStatus(int $refundId, string $status, ?int $processedBy = null): bool
    {
        $data = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($status === 'processed' && $processedBy) {
            $data['processed_by'] = $processedBy;
            $data['processed_at'] = date('Y-m-d H:i:s');
        }

        return Refund::where('id', $refundId)->update($data);
    }

    public function deleteRefundItems(int $refundId): bool
    {
        return RefundItem::where('refund_id', $refundId)->delete();
    }

    protected function getModelClass(): string
    {
        return Refund::class;
    }
}