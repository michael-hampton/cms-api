<?php

namespace App\Repositories;

use App\Models\RefundItem;

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