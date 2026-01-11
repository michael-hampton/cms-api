<?php

namespace App\Repositories\Members;

use App\Models\RefundItem;
use App\Repositories\Repository;

class RefundItemRepository extends Repository
{
    public function deleteByRefundId(int $refundId): bool
    {
        return RefundItem::where('refund_id', $refundId)->delete();
    }

    protected function getModelClass(): string
    {
        return RefundItem::class;
    }
}